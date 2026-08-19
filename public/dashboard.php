<?php

declare(strict_types=1);
require __DIR__ . '/../api/auth.php';
$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}
$isOwner = strtoupper((string)$user['role_code']) === 'OWNER';
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Mahameru Control Center</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body data-role="<?= htmlspecialchars((string)$user['role_code'], ENT_QUOTES, 'UTF-8') ?>" data-report-access="<?= $isOwner ? '1' : '0' ?>">
<div class="app">
    <header class="topbar">
        <div class="brand">MAHAMERU • SFA / DMS</div>
        <div class="role">
            <?= htmlspecialchars((string)$user['full_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$user['role_name'], ENT_QUOTES, 'UTF-8') ?>
            <form method="post" action="../api/logout.php" style="display:inline;margin-left:12px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Keluar</button>
            </form>
        </div>
    </header>
    <div class="layout">
        <aside class="sidebar">
            <nav class="nav">
                <button class="active" data-section="dashboard">Dashboard</button>
                <button data-section="sales">Sales</button>
                <button data-section="outlet">Outlet</button>
                <button data-section="order">EC / OC Order</button>
                <button data-section="inventory">Inventory</button>
                <button data-section="finance">Finance</button>
                <?php if ($isOwner): ?><button data-section="report">Report Center</button><?php endif; ?>
            </nav>
        </aside>
        <main class="content">
            <div class="notice">Data report berasal dari API server-side. Hak akses ditentukan oleh RBAC backend.</div>
            <div id="view"></div>
        </main>
    </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
