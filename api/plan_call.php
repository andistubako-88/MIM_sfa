<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_auth();
$pdo = db();
$salesStmt = $pdo->prepare('SELECT id FROM sales WHERE user_id = ? AND is_active = 1 LIMIT 1');
$salesStmt->execute([(int) $user['id']]);
$salesId = (int) $salesStmt->fetchColumn();
if (!$salesId && $user['role_code'] === 'SALES') json_response(['success' => false, 'message' => 'Profil salesman belum terhubung.'], 422);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['success' => false, 'message' => 'Tanggal tidak valid.'], 422);

if ($action === 'list') {
    $targetSalesId = $salesId;
    if ($user['role_code'] !== 'SALES') {
        $targetSalesId = (int) ($_GET['sales_id'] ?? 0);
        if (!$targetSalesId) json_response(['success' => false, 'message' => 'sales_id wajib untuk role non-SALES.'], 422);
    }
    $stmt = $pdo->prepare('SELECT pc.id, pc.plan_date, pc.sequence_no, pc.status, pc.notes, o.id AS outlet_id, o.code AS outlet_code, o.name AS outlet_name, o.city, o.district, o.latitude, o.longitude, o.channel FROM plan_calls pc JOIN outlets o ON o.id = pc.outlet_id WHERE pc.sales_id = ? AND pc.plan_date = ? ORDER BY pc.sequence_no');
    $stmt->execute([$targetSalesId, $date]);
    json_response(['success' => true, 'plan_date' => $date, 'plan_calls' => $stmt->fetchAll()]);
}

if ($action === 'create') {
    require_permission('masters.manage');
    require_csrf();
    $targetSalesId = (int) ($_POST['sales_id'] ?? 0);
    $outletId = (int) ($_POST['outlet_id'] ?? 0);
    $sequence = (int) ($_POST['sequence_no'] ?? 0);
    if (!$targetSalesId || !$outletId || $sequence < 1) json_response(['success' => false, 'message' => 'sales_id, outlet_id, dan sequence_no wajib valid.'], 422);

    $assignment = $pdo->prepare('SELECT 1 FROM sales_outlet WHERE sales_id = ? AND outlet_id = ? AND is_active = 1 LIMIT 1');
    $assignment->execute([$targetSalesId, $outletId]);
    if (!$assignment->fetchColumn()) json_response(['success' => false, 'message' => 'Outlet belum ditugaskan kepada salesman.'], 422);

    try {
        $stmt = $pdo->prepare('INSERT INTO plan_calls (sales_id, plan_date, outlet_id, sequence_no, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$targetSalesId, $date, $outletId, $sequence, (int) $user['id']]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) json_response(['success' => false, 'message' => 'Urutan atau outlet sudah ada di plan tanggal tersebut.'], 409);
        throw $e;
    }
    json_response(['success' => true, 'message' => 'Plan call berhasil dibuat.']);
}

json_response(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
