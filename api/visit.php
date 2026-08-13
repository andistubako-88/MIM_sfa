<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

function distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function request_float(string $key): float
{
    if (!isset($_POST[$key]) || !is_numeric($_POST[$key])) {
        json_response(['success' => false, 'message' => "Field {$key} wajib berupa angka."], 422);
    }
    return (float) $_POST[$key];
}

function request_bool(string $key): bool
{
    return filter_var($_POST[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
}

$user = require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'active';

if ($action === 'active') {
    $stmt = db()->prepare("SELECT v.*, o.code AS outlet_code, o.name AS outlet_name FROM visits v JOIN sales s ON s.id=v.sales_id JOIN outlets o ON o.id=v.outlet_id WHERE s.user_id=? AND v.status='ACTIVE' LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    json_response(['success' => true, 'visit' => $stmt->fetch() ?: null]);
}

if ($action === 'checkin') {
    require_csrf();
    require_permission('visits.create');

    $outletId = (int) ($_POST['outlet_id'] ?? 0);
    $lat = request_float('latitude');
    $lon = request_float('longitude');
    $accuracy = isset($_POST['accuracy_meters']) && is_numeric($_POST['accuracy_meters']) ? (float) $_POST['accuracy_meters'] : null;
    $mockDetected = request_bool('mock_location_detected');
    $mockReason = trim((string) ($_POST['mock_location_reason'] ?? '')) ?: null;
    $photoPath = trim((string) ($_POST['photo_path'] ?? ''));

    if ($outletId <= 0 || $photoPath === '') {
        json_response(['success' => false, 'message' => 'Outlet dan foto check-in wajib diisi.'], 422);
    }

    $cfg = db()->query('SELECT * FROM company_settings ORDER BY id LIMIT 1')->fetch();
    if (!$cfg) {
        json_response(['success' => false, 'message' => 'Company settings belum dikonfigurasi.'], 500);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone($cfg['timezone'] ?: 'Asia/Jakarta'));
    $time = $now->format('H:i:s');
    if ($time < $cfg['operational_start'] || $time > $cfg['operational_end']) {
        json_response(['success' => false, 'message' => 'Check-in di luar jam operasional.'], 422);
    }
    if ((int) $cfg['fake_gps_block_enabled'] === 1 && $mockDetected) {
        json_response(['success' => false, 'message' => 'Check-in diblokir karena indikasi fake/mock GPS.', 'reason' => $mockReason], 422);
    }

    $stmt = db()->prepare("SELECT o.* FROM outlets o JOIN sales_outlet so ON so.outlet_id=o.id JOIN sales s ON s.id=so.sales_id WHERE o.id=? AND s.user_id=? AND o.is_active=1 AND so.is_active=1 LIMIT 1");
    $stmt->execute([$outletId, (int) $user['id']]);
    $outlet = $stmt->fetch();
    if (!$outlet) {
        json_response(['success' => false, 'message' => 'Outlet tidak aktif atau tidak ditugaskan kepada salesman.'], 403);
    }

    $distance = distance_meters($lat, $lon, (float) $outlet['latitude'], (float) $outlet['longitude']);
    if ($distance > (float) $cfg['checkin_radius_meters']) {
        json_response(['success' => false, 'message' => 'Di luar radius check-in.', 'distance_meters' => round($distance, 2), 'allowed_radius_meters' => (float) $cfg['checkin_radius_meters']], 422);
    }

    $salesStmt = db()->prepare('SELECT id FROM sales WHERE user_id=? AND is_active=1 LIMIT 1');
    $salesStmt->execute([(int) $user['id']]);
    $salesId = (int) $salesStmt->fetchColumn();
    if (!$salesId) {
        json_response(['success' => false, 'message' => 'Akun belum memiliki profile salesman aktif.'], 422);
    }

    $activeStmt = db()->prepare("SELECT id FROM visits WHERE sales_id=? AND status='ACTIVE' LIMIT 1");
    $activeStmt->execute([$salesId]);
    if ($activeStmt->fetchColumn()) {
        json_response(['success' => false, 'message' => 'Masih ada kunjungan aktif. Silakan checkout terlebih dahulu.'], 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO visits (sales_id,outlet_id,visit_date,status,checkin_at,checkin_latitude,checkin_longitude,checkin_accuracy_meters,distance_meters,checkin_photo_path,mock_location_detected,mock_location_reason) VALUES (?,?,?,\'ACTIVE\',?,?,?,?,?,?,?,?)');
        $insert->execute([$salesId, $outletId, $now->format('Y-m-d'), $now->format('Y-m-d H:i:s'), $lat, $lon, $accuracy, $distance, $photoPath, $mockDetected ? 1 : 0, $mockReason]);
        $visitId = (int) $pdo->lastInsertId();
        $photo = $pdo->prepare("INSERT INTO visit_photos (visit_id,photo_type,file_path,latitude,longitude,captured_at) VALUES (?, 'CHECKIN', ?, ?, ?, ?)");
        $photo->execute([$visitId, $photoPath, $lat, $lon, $now->format('Y-m-d H:i:s')]);
        $activity = $pdo->prepare('INSERT INTO visit_activities (visit_id,activity_type,notes) VALUES (?,?,?)');
        $activity->execute([$visitId, 'CHECKIN', 'Check-in berhasil']);
        $pdo->commit();
        json_response(['success' => true, 'visit_id' => $visitId, 'distance_meters' => round($distance, 2), 'checkin_at' => $now->format(DATE_ATOM)], 201);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Check-in gagal diproses.'], 500);
    }
}

if ($action === 'checkout') {
    require_csrf();
    require_permission('visits.checkout');

    $lat = request_float('latitude');
    $lon = request_float('longitude');
    $stmt = db()->prepare("SELECT v.*, o.latitude AS outlet_latitude, o.longitude AS outlet_longitude, cs.minimum_visit_minutes FROM visits v JOIN sales s ON s.id=v.sales_id JOIN outlets o ON o.id=v.outlet_id CROSS JOIN company_settings cs WHERE s.user_id=? AND v.status='ACTIVE' ORDER BY v.id DESC LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    $visit = $stmt->fetch();
    if (!$visit) {
        json_response(['success' => false, 'message' => 'Tidak ada kunjungan aktif.'], 404);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $checkin = new DateTimeImmutable($visit['checkin_at'], new DateTimeZone('Asia/Jakarta'));
    $elapsed = $now->getTimestamp() - $checkin->getTimestamp();
    $minimumSeconds = ((int) $visit['minimum_visit_minutes']) * 60;
    if ($elapsed < $minimumSeconds) {
        $remaining = $minimumSeconds - $elapsed;
        json_response(['success' => false, 'message' => 'Minimum durasi kunjungan belum terpenuhi.', 'remaining_seconds' => $remaining], 422);
    }

    $pdo = db();
    $update = $pdo->prepare("UPDATE visits SET status='COMPLETED',checkout_at=?,checkout_latitude=?,checkout_longitude=? WHERE id=? AND status='ACTIVE'");
    $update->execute([$now->format('Y-m-d H:i:s'), $lat, $lon, $visit['id']]);
    $activity = $pdo->prepare('INSERT INTO visit_activities (visit_id,activity_type,notes) VALUES (?,?,?)');
    $activity->execute([(int) $visit['id'], 'CHECKOUT', 'Checkout berhasil']);
    json_response(['success' => true, 'visit_id' => (int) $visit['id'], 'duration_seconds' => $elapsed, 'checkout_at' => $now->format(DATE_ATOM)]);
}

json_response(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
