<?php
require_once __DIR__ . '/path.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . appUrl('login.php'));
        exit;
    }
    _checkSessionFingerprint();
    _refreshSession();
}

function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        redirectToDashboard();
    }
}

function redirectToDashboard(): void {
    $role = $_SESSION['role'] ?? '';
    match ($role) {
        'admin'       => header('Location: ' . appUrl('admin/home.php')),
        'coordinator' => header('Location: ' . appUrl('coordinator/home.php')),
        'dept_head'   => header('Location: ' . appUrl('dept_head/home.php')),
        'faculty'     => header('Location: ' . appUrl('faculty/home.php')),
        'student'     => header('Location: ' . appUrl('student/home.php')),
        default       => header('Location: ' . appUrl('login.php')),
    };
    exit;
}

function redirectIfLoggedIn(): void {
    if (!empty($_SESSION['user_id'])) {
        redirectToDashboard();
    }
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . appUrl('login.php'));
    exit;
}

function currentUserId(): int        { return (int) ($_SESSION['user_id']      ?? 0); }
function currentRole(): string       { return $_SESSION['role']                ?? ''; }
function currentName(): string       { return $_SESSION['full_name']           ?? ''; }
function currentDeptId(): int        { return (int) ($_SESSION['department_id'] ?? 0); }

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '"/>';
}

function formatScore(mixed $score): string {
    if ($score === null) return '--';
    $f = (float) $score;
    return ($f == (int)$f) ? (string)(int)$f : rtrim(rtrim(number_format($f, 2), '0'), '.');
}

function _checkSessionFingerprint(): void {
    $fp = sha1(($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (empty($_SESSION['_fp'])) {
        $_SESSION['_fp'] = $fp;
    } elseif (!hash_equals($_SESSION['_fp'], $fp)) {
        logout();
    }
}

function _refreshSession(): void {
    $interval = 1800;
    if (isset($_SESSION['_last_regen']) && time() - $_SESSION['_last_regen'] > $interval) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    } elseif (!isset($_SESSION['_last_regen'])) {
        $_SESSION['_last_regen'] = time();
    }
}
