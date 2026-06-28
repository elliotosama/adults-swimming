<?php


class SettingController {


    // ── Helpers ──────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView($view = '/admin/', array $data = []) {
        $base = '/var/www/blackhorse/views/';
        extract($data);
        require_once($base . $view . '.php');
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'full_name'    => trim($_POST['full_name']   ?? ''),
            'email'        => strtolower(trim($_POST['email'] ?? '')),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'role'         => trim($_POST['role']         ?? 'student'),
            'password'     => $_POST['password']          ?? '',
            'is_active'    => ($_POST['is_active'] ?? '1') === '1' ? 1 : 0,
            'is_verified'  => isset($_POST['is_verified']) ? 1 : 0,
        ];
    }

    private function validate(array $data, bool $isEdit = false, ?int $editId = null): array {
        $errors = [];

        // Full name
        if (strlen($data['full_name']) < 2)
            $errors[] = 'Full name must be at least 2 characters.';

        // Email
        if (empty($data['email']))
            $errors[] = 'Email address is required.';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'Email address is not valid.';
        elseif ($this->users->isEmailTaken($data['email'], $isEdit ? $editId : null))
            $errors[] = 'Email "' . htmlspecialchars($data['email']) . '" is already in use.';

        // Phone number — optional, but validate format if provided
        if (!empty($data['phone_number']) && !preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $data['phone_number']))
            $errors[] = 'Phone number format is not valid.';

        // Role
        $allowedRoles = ['admin', 'telesales', 'receptionist', 'instructor', 'student', 'branch_manager'];
        if (!in_array($data['role'], $allowedRoles, true))
            $errors[] = 'Invalid role selected.';

        // Password — required on create, optional on edit
        if (!$isEdit && strlen($data['password']) < 8)
            $errors[] = 'Password must be at least 8 characters.';
        elseif ($isEdit && !empty($data['password']) && strlen($data['password']) < 8)
            $errors[] = 'New password must be at least 8 characters.';

        return $errors;
    }

  public function settings(): void {
    auth_require(['admin']);
    $db       = get_db();
    $rows     = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $this->renderView('admin/settings', [
        'pageTitle' => 'إعدادات النظام',
        'settings'  => $rows,
        'saved'     => false,
    ]);
}

public function saveSettings(): void {
    auth_require(['admin']);
    $db       = get_db();
    $input    = $_POST['settings'] ?? [];
    $allowed  = ['min_payment_amount'];   // whitelist

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $input)) continue;
        $val  = (float) $input[$key];
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by    = VALUES(updated_by),
                updated_at    = NOW()
        ");
        $stmt->execute([$key, $val, auth_user()['id']]);
    }

    log_action('updated_settings', json_encode($input), auth_user()['id']);

    $rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $this->renderView('admin/settings', [
        'pageTitle' => 'إعدادات النظام',
        'settings'  => $rows,
        'saved'     => true,
    ]);
}
}