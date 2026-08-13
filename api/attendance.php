<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_auth();
if ($user['role_code'] !== 'SALES') {
    json_response(['success' => false, 'message' => 'Attendance field hanya untuk akun SALES.'], 403);
}

require_csrf();
$pdo = db();
$salesStmt = $pdo->prepare('SELECT id FROM sales WHERE user_id = ? AND is_active = 1 LIMIT 1');
$salesStmt->execute([(int) $user['id']]);
$salesId = (int) $salesStmt->fetchColumn();
if (!$salesId) {
    json_response(['success' => false, 'message' => 'Profil salesman belum terhubung.'], 422);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';
$today = date('Y-m-d');

if ($action === 'status') {
    $stmt = $pdo->prepare('SELECT * FROM attendance WHERE sales_id = ? AND attendance_date = ? LIMIT 1');
    $stmt->execute([$salesId, $today]);
    json_response(['success' => true, 'attendance' => $stmt->fetch() ?: null]);
}

if ($action === 'checkin') {
    $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $accuracy = filter_var($_POST['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        json_response(['success' => false, 'message' => 'Koordinat GPS tidak valid.'], 422);
    }

    $settings = $pdo->query('SELECT operational_start, operational_end FROM company_settings LIMIT 1')->fetch();
    $nowTime = date('H:i:s');
    if ($settings && ($nowTime < $settings['operational_start'] || $nowTime > $settings['operational_end'])) {
        json_response(['success' => false, 'message' => 'Check-in attendance di luar jam operasional.'], 422);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO attendance (sales_id, attendance_date, status, checkin_at, checkin_latitude, checkin_longitude, checkin_accuracy_meters) VALUES (?, ?, ?, NOW(), ?, ?, ?)');
        $stmt->execute([$salesId, $today, 'PRESENT', $lat, $lng, $accuracy === false ? null : $accuracy]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['success' => false, 'message' => 'Attendance hari ini sudah tercatat.'], 409);
        }
        throw $e;
    }
    json_response(['success' => true, 'message' => 'Attendance check-in berhasil.']);
}

if ($action === 'checkout') {
    $stmt = $pdo->prepare('SELECT id, checkout_at FROM attendance WHERE sales_id = ? AND attendance_date = ? LIMIT 1');
    $stmt->execute([$salesId, $today]);
    $attendance = $stmt->fetch();
    if (!$attendance) json_response(['success' => false, 'message' => 'Belum ada attendance check-in hari ini.'], 422);
    if ($attendance['checkout_at']) json_response(['success' => false, 'message' => 'Attendance sudah checkout.'], 409);

    $pdo->prepare('UPDATE attendance SET checkout_at = NOW() WHERE id = ?')->execute([(int) $attendance['id']]);
    json_response(['success' => true, 'message' => 'Attendance checkout berhasil.']);
}

json_response(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
