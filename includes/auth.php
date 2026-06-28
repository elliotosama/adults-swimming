<?php
// includes/auth.php

function auth_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function auth_check(): bool {
    return isset($_SESSION['user']['id']);
}

function auth_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function auth_require(array $roles = []): void {
    if (!auth_check()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header('Location: ' . APP_URL . '/login');
        exit;
    }
    if (!empty($roles) && !in_array(auth_role(), $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function auth_login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['username'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_redirect_by_role(): void {
    $role = auth_role();
    $map  = ROLE_DASHBOARDS;
    $path = $map[$role] ?? '/login';
    header('Location: ' . APP_URL . $path);
    exit;
}