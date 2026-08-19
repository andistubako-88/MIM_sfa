<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name'] ?? 'mim_sfa_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(['success' => false, 'message' => 'CSRF token tidak valid.'], 419);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $s = db()->prepare(
        'SELECT u.id,u.username,u.full_name,u.phone,
                r.id role_id,r.code role_code,r.name role_name
         FROM users u
         JOIN roles r ON r.id=u.role_id
         WHERE u.id=? AND u.is_active=1
         LIMIT 1'
    );
    $s->execute([(int) $_SESSION['user_id']]);
    $u = $s->fetch();

    return $u ?: null;
}

function require_auth(): array
{
    $u = current_user();
    if (!$u) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    return $u;
}

function has_permission(int $userId, string $permission): bool
{
    $s = db()->prepare(
        'SELECT 1
         FROM users u
         JOIN role_permissions rp ON rp.role_id=u.role_id
         JOIN permissions p ON p.id=rp.permission_id
         WHERE u.id=? AND u.is_active=1 AND p.code=?
         LIMIT 1'
    );
    $s->execute([$userId, $permission]);
    return (bool) $s->fetchColumn();
}

function require_permission(string $permission): array
{
    $u = require_auth();
    if (!has_permission((int) $u['id'], $permission)) {
        json_response(['success' => false, 'message' => 'Anda tidak memiliki izin untuk aksi ini.'], 403);
    }
    return $u;
}

function handle_auth_request(): never
{
    $action = $_GET['action'] ?? $_POST['action'] ?? 'me';

    if ($action === 'csrf') {
        json_response(['success' => true, 'csrf_token' => csrf_token()]);
    }

    if ($action === 'login') {
        require_csrf();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            json_response(['success' => false, 'message' => 'Username dan password wajib diisi.'], 422);
        }

        $s = db()->prepare('SELECT id,password_hash FROM users WHERE username=? AND is_active=1 LIMIT 1');
        $s->execute([$username]);
        $a = $s->fetch();

        if (!$a || !password_verify($password, $a['password_hash'])) {
            json_response(['success' => false, 'message' => 'Username atau password salah.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $a['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$a['id']]);

        json_response([
            'success' => true,
            'user' => current_user(),
            'csrf_token' => csrf_token(),
        ]);
    }

    if ($action === 'logout') {
        require_auth();
        require_csrf();
        $_SESSION = [];
        session_destroy();
        json_response(['success' => true]);
    }

    if ($action === 'me') {
        $user = current_user();
        json_response([
            'success' => true,
            'authenticated' => (bool) $user,
            'user' => $user,
            'csrf_token' => csrf_token(),
        ]);
    }

    json_response(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
}

/*
 * auth.php is also included by normal PHP pages. On cPanel with the /public
 * document root, /api/auth.php is internally rewritten to public/api/index.php
 * and then this file is required. Therefore SCRIPT_FILENAME cannot reliably be
 * used to decide whether this is the public auth endpoint.
 *
 * The adapter passes endpoint=auth.php for direct API requests. Normal pages
 * such as public/login.php and public/index.php do not have that query value.
 */
$isPublicAuthRequest =
    basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'auth.php'
    || basename((string) ($_SERVER['PHP_SELF'] ?? '')) === 'auth.php'
    || basename((string) ($_GET['endpoint'] ?? '')) === 'auth.php';

if ($isPublicAuthRequest) {
    handle_auth_request();
}
