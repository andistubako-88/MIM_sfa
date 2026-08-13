<?php
declare(strict_types=1);
require __DIR__ . '/../api/auth.php';
$user = require_auth();
$canOrder = has_permission((int)$user['id'],'orders.create');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mahameru — Sales</title><link rel="stylesheet" href="assets/app.css"></head><body><main class="container"><header class="topbar"><div><div class="brand">MAHAMERU</div><div class="muted">Sales Workspace</div></div><a class="button" href="dashboard.php">Dashboard</a></header><section class="grid"><article class="card"><h2>Visit Hari Ini</h2><p class="muted">Kelola kunjungan, check-in GPS, foto outlet, dan check-out.</p><a class="primary button" href="visit.php">Buka Visit</a></article><article class="card"><h2>EC / OC Order</h2><p class="muted">Buat order setelah check-in dan sebelum check-out.</p><?php if($canOrder): ?><a class="primary button" href="order.php">Buka Order</a><?php else: ?><span class="muted">Tidak memiliki permission order.</span><?php endif; ?></article></section></main></body></html>
