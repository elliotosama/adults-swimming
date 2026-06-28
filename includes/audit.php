<?php
// includes/audit.php

function log_action(string $action, ?string $detail = null, ?int $user_id = null): void {
    try {
        $pdo = get_db();
        $uid = $user_id ?? (auth_user()['id'] ?? null);
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR']
             ?? $_SERVER['REMOTE_ADDR']
             ?? null;

        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$uid, $action, $detail, $ip]);
    } catch (Throwable $e) {
        error_log('audit log failed: ' . $e->getMessage());
    }
}