<?php
// app/controllers/AuthController.php
require ROOT . '/app/models/UserModel.php';
require ROOT . '/includes/audit.php';
class AuthController {

    private UserModel $users;

    public function __construct() {
        $this->users = new UserModel();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    // ════════════════════════════════════════════════════════════════════════
    // LOGIN
    // ════════════════════════════════════════════════════════════════════════

    public function showLogin(): void {
        if (auth_check()) { auth_redirect_by_role(); }
        $this->renderView('auth/login');
    }


public function showDashboard(string $role): void {
    auth_require([$role]);
    $this->renderView("$role/dashboard");
}
    public function handleLogin(): void {

        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Basic validation
        if (!$email || !$password) {
            $this->flash('flash_error', 'Please enter your email and password.');
            $this->redirect('/login');
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_action('login_failed', "email: {$email}");
            $this->flash('flash_error', 'Invalid email or password.');
            $this->redirect('/login');
        }

        if (!$user['is_active']) {
            $this->flash('flash_error', 'Your account has been deactivated. Contact support.');
            $this->redirect('/login');
        }


        auth_login_user($user);
        $this->users->updateLastLogin($user['id']);
        log_action('login_success', null, $user['id']);

        auth_redirect_by_role();
    }

    // ════════════════════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════════════════════

    public function showRegister(): void {
        if (auth_check()) { auth_redirect_by_role(); }
        $this->renderView('auth/register');
    }

    public function handleRegister(): void {

        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = $_POST['password']       ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        // Validation
        $errors = [];
        if (strlen($fullName) < 2)
            $errors[] = 'Full name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8)
            $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';
        if ($this->users->emailExists($email))
            $errors[] = 'An account with this email already exists.';

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->flash('flash_old_email', $email);
            $this->flash('flash_old_name', $fullName);
            $this->redirect('/register');
        }

        $userId = $this->users->create([
            'username' => $fullName,
            'email'     => $email,
            'password'  => $password,
        ]);

        $token = $this->users->getVerificationToken($userId);
        $link  = APP_URL . '/verify-email/' . urlencode($token);


        log_action('register', "email: {$email}", $userId);

        $this->redirect('/login');
    }

    // ════════════════════════════════════════════════════════════════════════
    // EMAIL VERIFICATION
    // ════════════════════════════════════════════════════════════════════════

    public function showVerifyNotice(): void {
        $this->renderView('auth/verify_notice');
    }

    public function handleVerify(string $token): void {
        $user = $this->users->findByVerificationToken($token);

        if (!$user) {
            $this->flash('flash_error', 'This verification link is invalid or has expired.');
            $this->redirect('/login');
        }

        // Check expiry
        if (strtotime($user['verification_expires']) < time()) {
            $_SESSION['unverified_user_id'] = $user['id'];
            $this->flash('flash_error', 'This link has expired. Request a new one below.');
            $this->redirect('/verify-email/notice');
        }

        $this->users->verifyEmail($user['id']);
        log_action('email_verified', null, $user['id']);

        $this->flash('flash_success', 'Email verified! You can now log in.');
        $this->redirect('/login');
    }

    public function resendVerification(): void {

        $userId = $_SESSION['unverified_user_id'] ?? null;
        if (!$userId) {
            $this->redirect('/login');
        }

        $user  = $this->users->findById((int) $userId);
        if (!$user || $user['is_verified']) {
            $this->redirect('/login');
        }

        $token = $this->users->refreshVerificationToken($user['id']);
        $link  = APP_URL . '/verify-email/' . urlencode($token);



        log_action('resend_verification', null, $user['id']);
        $this->flash('flash_success', 'A new verification email has been sent.');
        $this->redirect('/verify-email/notice');
    }

    // ════════════════════════════════════════════════════════════════════════
    // FORGOT PASSWORD
    // ════════════════════════════════════════════════════════════════════════

    public function showForgotPassword(): void {
        $this->renderView('auth/forgot_password');
    }

    public function handleForgotPassword(): void {

        $email = trim($_POST['email'] ?? '');

        // Always show same message to prevent email enumeration
        $this->flash('flash_success',
            'If that email is registered, you will receive a reset link shortly.'
        );

        $user = $this->users->findByEmail($email);
        if ($user && $user['is_active']) {
            $token = $this->users->setResetToken($user['id']);
            $link  = APP_URL . '/reset-password/' . urlencode($token);

            log_action('password_reset_requested', null, $user['id']);
        }

        $this->redirect('/forgot-password');
    }

    // ════════════════════════════════════════════════════════════════════════
    // RESET PASSWORD
    // ════════════════════════════════════════════════════════════════════════

    public function showResetPassword(string $token): void {
        $user = $this->users->findByResetToken($token);
        if (!$user) {
            $this->flash('flash_error', 'This reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }
        $this->renderView('auth/reset_password', ['token' => $token]);
    }

    public function handleResetPassword(): void {

        $token    = $_POST['token']            ?? '';
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $user = $this->users->findByResetToken($token);
        if (!$user) {
            $this->flash('flash_error', 'This reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }

        if (strlen($password) < 8) {
            $this->flash('flash_error', 'Password must be at least 8 characters.');
            $this->redirect('/reset-password/' . urlencode($token));
        }

        if ($password !== $confirm) {
            $this->flash('flash_error', 'Passwords do not match.');
            $this->redirect('/reset-password/' . urlencode($token));
        }

        $this->users->updatePassword($user['id'], $password);
        log_action('password_reset_complete', null, $user['id']);

        $this->flash('flash_success', 'Password updated successfully. You can now log in.');
        $this->redirect('/login');
    }

    // ════════════════════════════════════════════════════════════════════════
    // LOGOUT
    // ════════════════════════════════════════════════════════════════════════

    public function handleLogout(): void {
        $userId = auth_user()['id'] ?? null;
        log_action('logout', null, $userId);
        auth_logout();
        $this->flash('flash_success', 'You have been logged out.');
        $this->redirect('/login');
    }
}