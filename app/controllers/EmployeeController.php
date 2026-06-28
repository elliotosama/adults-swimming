<?php
// app/controllers/UserController.php

class EmployeeController {

    private EmployeeModel  $users;
    private BranchModel $branches;

    public function __construct() {
        $this->users    = new EmployeeModel();
        $this->branches = new BranchModel();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/admin/users/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'username'  => trim($_POST['username']  ?? ''),
            'email'     => trim($_POST['email']     ?? ''),
            'password'  => $_POST['password']       ?? '',
            'role'      => trim($_POST['role']       ?? ''),
            'phone'     => trim($_POST['phone']      ?? ''),
            'visible'   => ($_POST['visible']   ?? '1') === '1' ? 1 : 0,
            'is_active' => ($_POST['is_active'] ?? '1') === '1' ? 1 : 0,
            'branch_ids' => array_map('intval', $_POST['branch_ids'] ?? []),
        ];
    }

    private function validate(array $data, bool $isEdit = false): array {
        $errors = [];

        if (strlen($data['username']) < 2)
            $errors[] = 'اسم المستخدم يجب أن يكون حرفين على الأقل.';

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'البريد الإلكتروني غير صالح.';

        if (!$isEdit && strlen($data['password']) < 8)
            $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';

        if ($isEdit && !empty($data['password']) && strlen($data['password']) < 8)
            $errors[] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';

        $validRoles = ['admin', 'branch_manager', 'area_manager', 'customer_service'];
        if (!in_array($data['role'], $validRoles, true))
            $errors[] = 'الدور المحدد غير صالح.';

        if (!empty($data['phone']) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $data['phone']))
            $errors[] = 'رقم الهاتف غير صالح.';

        return $errors;
    }

    private function allBranches(): array {
        return $this->branches->findAll();
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /admin/users
    // ════════════════════════════════════════════════════════════════════════

public function index(): void {
    auth_require(['admin']);

    $filters = [
        'search'  => trim($_GET['search']  ?? ''),
        'role'    => trim($_GET['role']    ?? ''),
        'visible' => $_GET['visible'] ?? '',
    ];

    $users       = $this->users->findFiltered($filters);
    $allUsers    = $this->users->findAll();
    $activeUsers = count(array_filter($allUsers, fn($u) => $u['is_active'] && $u['visible']));

    $roleCounts = [];
    foreach ($allUsers as $u) {
        $roleCounts[$u['role']] = ($roleCounts[$u['role']] ?? 0) + 1;
    }

    $this->renderView('index', [
        'pageTitle'   => 'المستخدمون',
        'breadcrumb'  => 'لوحة التحكم · المستخدمون',
        'users'       => $users,
        'filters'     => $filters,
        'totalUsers'  => count($allUsers),
        'activeUsers' => $activeUsers,
        'roleCounts'  => $roleCounts,
    ]);
}

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /admin/users/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin']);

        $this->renderView('create', [
            'pageTitle'  => 'مستخدم جديد',
            'breadcrumb' => 'لوحة التحكم · المستخدمون · مستخدم جديد',
            'user'       => [],
            'errors'     => [],
            'branches'   => $this->allBranches(),
            'assignedIds'=> [],
            'isEdit'     => false,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /admin/users/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {
        auth_require(['admin']);

        $data   = $this->parseForm();
        $errors = $this->validate($data, false);

        if (!$errors && $this->users->usernameExists($data['username']))
            $errors[] = 'اسم المستخدم مستخدم بالفعل.';

        if (!$errors && $this->users->emailExists($data['email']))
            $errors[] = 'البريد الإلكتروني مستخدم بالفعل.';

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'  => 'مستخدم جديد',
                'breadcrumb' => 'لوحة التحكم · المستخدمون · مستخدم جديد',
                'user'       => $data,
                'errors'     => $errors,
                'branches'   => $this->allBranches(),
                'assignedIds'=> $data['branch_ids'],
                'isEdit'     => false,
            ]);
            return;
        }

        $newId = $this->users->create($data);
        $this->users->syncBranches($newId, $data['branch_ids']);

        log_action('created_user', "id: {$newId}, username: {$data['username']}", auth_user()['id']);
        $this->flash('flash_success', 'تم إنشاء المستخدم "' . htmlspecialchars($data['username']) . '" بنجاح.');
        $this->redirect('/admin/users');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /admin/users/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
            return;
        }

        $this->renderView('show', [
            'pageTitle'  => htmlspecialchars($user['username']),
            'breadcrumb' => 'لوحة التحكم · المستخدمون · ' . htmlspecialchars($user['username']),
            'user'       => $user,
            'branches'   => $this->users->getBranches($id),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /admin/users/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
            return;
        }

        $this->renderView('edit', [
            'pageTitle'  => 'تعديل المستخدم',
            'breadcrumb' => 'لوحة التحكم · المستخدمون · تعديل',
            'user'       => $user,
            'errors'     => [],
            'branches'   => $this->allBranches(),
            'assignedIds'=> $this->users->getBranchIds($id),
            'isEdit'     => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /admin/users/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data, true);

        if (!$errors && $this->users->usernameExists($data['username'], $id))
            $errors[] = 'اسم المستخدم مستخدم بالفعل.';

        if (!$errors && $this->users->emailExists($data['email'], $id))
            $errors[] = 'البريد الإلكتروني مستخدم بالفعل.';

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'  => 'تعديل المستخدم',
                'breadcrumb' => 'لوحة التحكم · المستخدمون · تعديل',
                'user'       => array_merge($user, $data),
                'errors'     => $errors,
                'branches'   => $this->allBranches(),
                'assignedIds'=> $data['branch_ids'],
                'isEdit'     => true,
            ]);
            return;
        }

        $this->users->update($id, $data);

        // Update password only if a new one was provided
        if (!empty($data['password'])) {
            $this->users->updatePassword($id, $data['password']);
        }

        $this->users->syncBranches($id, $data['branch_ids']);

        log_action('updated_user', "id: {$id}, username: {$data['username']}", auth_user()['id']);
        $this->flash('flash_success', 'تم تحديث المستخدم "' . htmlspecialchars($data['username']) . '" بنجاح.');
        $this->redirect('/admin/users');
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /admin/users/delete?id=x
    // Soft-delete only — sets visible = 0, is_active = 0
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin']);

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('flash_error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
            return;
        }

        // Prevent admin from deactivating themselves
        if ($id === (int) auth_user()['id']) {
            $this->flash('flash_error', 'لا يمكنك تعطيل حسابك الخاص.');
            $this->redirect('/admin/users');
            return;
        }

        $this->users->hide($id);
        log_action('deactivated_user', "id: {$id}, username: {$user['username']}", auth_user()['id']);

        $this->flash('flash_success', 'تم تعطيل المستخدم "' . htmlspecialchars($user['username']) . '" بنجاح.');
        $this->redirect('/admin/users');
    }
}