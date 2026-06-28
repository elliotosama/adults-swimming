<?php
// app/controllers/UserController.php

class UserController {

    private UserModel $users;

    public function __construct() {
        $this->users = new UserModel();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView($view = 'users/index', array $data = []) {
        $base = ROOT . '/views/admin/';
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

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /admin/users
    // ════════════════════════════════════════════════════════════════════════

    public function getUsersByBranch() {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $students = $this->users->getStudentsByBranch();
        $totalStudents = count($students);
        
        $this->renderView('receptionist/dashboard', [
            'pageTitle' => 'Clients',
            'breadcrumb' => 'receptionist',
            'users' => $students,
            'totalUsers' => $totalStudents
        ]);
    }


    public function index(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $filters = [
            'role'      => $_GET['role']      ?? '',
            'is_active' => $_GET['is_active'] ?? '',
            'search'    => $_GET['search']    ?? '',
        ];

        $users      = $this->users->findAll($filters);
        $roleCounts = $this->users->countByRole();

        $totalUsers  = count($users);
        $activeUsers = count(array_filter($users, fn($u) => !empty($u['is_active'])));

        $this->renderView('users/index', [
            'pageTitle'   => 'Users',
            'breadcrumb'  => 'Admin · Users',
            'users'       => $users,
            'filters'     => $filters,
            'roleCounts'  => $roleCounts,
            'totalUsers'  => $totalUsers,
            'activeUsers' => $activeUsers,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /admin/users/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $this->renderView('users/create', [
            'pageTitle'  => 'New User',
            'breadcrumb' => 'Admin · Users · New User',
            'user'       => [],
            'errors'     => [],
            'isEdit'     => false,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /admin/users/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {

        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);


        $data   = $this->parseForm();
        $errors = $this->validate($data, false);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('users/create', [
                'pageTitle'  => 'New User',
                'breadcrumb' => 'Admin · Users · New User',
                'user'       => $data,
                'errors'     => $errors,
                'isEdit'     => false,
            ]);
            return;
        }

        $newId = $this->users->create($data);
        log_action('created_user', "id: {$newId}, email: {$data['email']}", auth_user()['id']);

        $this->flash('flash_success', 'User "' . htmlspecialchars($data['username']) . '" created successfully.');
        $this->redirect('/users');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /admin/users/show?id=
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'User not found.');
            $this->redirect('/users');
        }

        $this->renderView('users/show', [
            'pageTitle'  => htmlspecialchars($user['username']),
            'breadcrumb' => 'Admin · Users · ' . htmlspecialchars($user['username']),
            'user'       => $user,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /admin/users/edit?id=
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'User not found.');
            $this->redirect('/users');
        }

        $this->renderView('users/edit', [
            'pageTitle'  => 'Edit User',
            'breadcrumb' => 'Admin · Users · Edit',
            'user'       => $user,
            'errors'     => [],
            'isEdit'     => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /admin/users/edit?id=
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'User not found.');
            $this->redirect('/users');
        }

        // Prevent admin from deactivating their own account
        if ($id === auth_user()['id'] && ($_POST['is_active'] ?? '1') === '0') {
            $this->flash('flash_error', 'You cannot deactivate your own account.');
            $this->redirect('/users/edit?id=' . $id);
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data, true, $id);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('users/edit', [
                'pageTitle'  => 'Edit User',
                'breadcrumb' => 'Admin · Users · Edit',
                'user'       => array_merge($user, $data),
                'errors'     => $errors,
                'isEdit'     => true,
            ]);
            return;
        }

        $this->users->update($id, $data);
        log_action('updated_user', "id: {$id}, email: {$data['email']}", auth_user()['id']);

        $this->flash('flash_success', 'User "' . htmlspecialchars($data['full_name']) . '" updated successfully.');
        $this->redirect('/users');
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /admin/users/delete?id=
    // Soft-delete only — sets is_active = 0
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'User not found.');
            $this->redirect('/users');
            return;
        }

        if ($id === auth_user()['id']) {
            $this->flash('flash_error', 'You cannot delete your own account.');
            $this->redirect('/users');
            return;
        }

        $this->users->deactivate($id);
        log_action('deactivated_user', "id: {$id}, email: {$user['email']}", auth_user()['id']);

        $this->flash('flash_success', 'User "' . htmlspecialchars($user['username']) . '" has been deactivated.');
        $this->redirect('/users');
    }
}