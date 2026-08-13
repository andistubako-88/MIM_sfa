<?php

declare(strict_types=1);
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>MIM SFA — Login</title>
    <style>
        :root { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #0f172a; background: #f8fafc; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; }
        main { width: min(92vw, 420px); padding: 32px; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 16px 40px rgba(15,23,42,.08); }
        h1 { margin: 0 0 8px; }
        p { color: #475569; line-height: 1.5; }
        label { display:block; margin: 16px 0 6px; font-weight: 600; }
        input, button { width: 100%; box-sizing: border-box; padding: 12px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font: inherit; }
        button { margin-top: 18px; border: 0; background: #0f172a; color: #fff; font-weight: 700; cursor: pointer; }
        #message { margin-top: 14px; min-height: 20px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #e2e8f0; font-size: 13px; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <span class="badge">MIM SFA</span>
    <h1>Masuk</h1>
    <p>PT Mahameru Insan Mandiri — Sales Force Automation.</p>
    <form id="loginForm">
        <input type="hidden" id="csrf">
        <label for="username">Username</label>
        <input id="username" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" type="password" autocomplete="current-password" required>
        <button type="submit">Masuk</button>
        <div id="message" role="status"></div>
    </form>
</main>
<script>
(async () => {
    const msg = document.getElementById('message');
    try {
        const response = await fetch('api/auth.php?action=csrf', { credentials: 'same-origin' });
        const data = await response.json();
        document.getElementById('csrf').value = data.csrf_token || '';
    } catch (_) { msg.textContent = 'Gagal menyiapkan keamanan sesi.'; }
})();

document.getElementById('loginForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const msg = document.getElementById('message');
    const csrf = document.getElementById('csrf').value;
    const body = new URLSearchParams({ action: 'login', username: document.getElementById('username').value.trim(), password: document.getElementById('password').value, csrf_token: csrf });
    const response = await fetch('api/auth.php', { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf}, body });
    const data = await response.json();
    msg.textContent = data.success ? `Selamat datang, ${data.user.full_name}.` : (data.message || 'Login gagal.');
    if (data.csrf_token) document.getElementById('csrf').value = data.csrf_token;
});
</script>
</body>
</html>
