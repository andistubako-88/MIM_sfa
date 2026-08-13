<?php

declare(strict_types=1);

require __DIR__ . '/../api/auth.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$csrf = csrf_token();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mahameru — Login</title><link rel="stylesheet" href="assets/app.css"></head><body class="auth-page"><main class="auth-card"><div class="brand">MAHAMERU</div><h1>Masuk ke SFA / DMS</h1><p class="muted">Gunakan akun perusahaan Anda.</p><form method="post" action="../api/auth.php?action=login"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label>Username<input name="username" autocomplete="username" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="primary" type="submit">Masuk</button></form></main></body></html>
