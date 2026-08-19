<?php

declare(strict_types=1);

require __DIR__ . '/../api/auth.php';
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Mahameru — Login</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="brand">MAHAMERU</div>
    <h1>Masuk ke SFA / DMS</h1>
    <p class="muted">Gunakan akun perusahaan Anda.</p>
    <div id="login-error" class="notice" hidden></div>
    <form id="login-form" method="post" action="../api/auth.php?action=login" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Username<input name="username" autocomplete="username" required autofocus></label>
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button id="login-submit" class="primary" type="submit">Masuk</button>
    </form>
</main>
<script>
const form = document.getElementById('login-form');
const errorBox = document.getElementById('login-error');
const submit = document.getElementById('login-submit');
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    submit.disabled = true;
    submit.textContent = 'Memproses...';
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
            body: new FormData(form)
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Login gagal.');
        window.location.replace('dashboard.php');
    } catch (error) {
        errorBox.textContent = error.message || 'Login gagal.';
        errorBox.hidden = false;
        submit.disabled = false;
        submit.textContent = 'Masuk';
    }
});
</script>
</body>
</html>
