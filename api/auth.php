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

    $stmt = db()->prepare('SELECT u.id, u.username, u.full_name, u.phone, r.id AS role_id, r.code AS role_code, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1 LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    return $user;
}

function has_permission(int $userId, string $permission): bool
{
    $stmt = db()->prepare('SELECT 1 FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id WHERE u.id = ? AND u.is_active = 1 AND p.code = ? LIMIT 1');
    $stmt->execute([$userId, $permission]);
    return (bool) $stmt->fetchColumn();
}

function require_permission(string $permission): array
{
    $user = require_auth();
    if (!has_permission((int) $user['id'], $permission)) {
        json_response(['success' => false, 'message' => 'Anda tidak memiliki izin untuk aksi ini.'], 403);
    }
    return $user;
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

        $stmt = db()->prepare('SELECT u.id, u.password_hash FROM users u WHERE u.username = ? AND u.is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();
        if (!$account || !password_verify($password, $account['password_hash'])) {
            json_response(['success' => false, 'message' => 'Username atau password salah.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
        json_response(['success' => true, 'user' => current_user(), 'csrf_token' => csrf_token()]);
    }

    if ($action === 'logout') {
        require_auth();
        require_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_response(['success' => true]);
    }

    if ($action === 'me') {
        $user = current_user();
        json_response(['success' => true, 'authenticated' => (bool) $user, 'user' => $user, 'csrf_token' => csrf_token()]);
    }

    json_response(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
}

handle_auth_request();
