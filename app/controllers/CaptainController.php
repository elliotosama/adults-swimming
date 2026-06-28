<?php
// app/controllers/CaptainController.php

class CaptainController {

    private CaptainModel $captains;
    private BranchModel  $branchModel;

    public function __construct() {
        $this->captains    = new CaptainModel();
        $this->branchModel = new BranchModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/admin/captains/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'captain_name' => trim($_POST['captain_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'visible'      => ($_POST['visible'] ?? '1') === '1' ? 1 : 0,
            'branch_ids'   => array_map('intval', $_POST['branch_ids'] ?? []),
        ];
    }

    private function validate(array $data): array {
        $errors = [];

        if (strlen($data['captain_name']) < 2)
            $errors[] = 'اسم الكابتن يجب أن يكون حرفين على الأقل.';

        if (!empty($data['phone_number']) && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $data['phone_number']))
            $errors[] = 'رقم الهاتف غير صحيح.';

        return $errors;
    }

    // Returns branch filters scoped to the current user's role
    private function branchFilters(array $user): array {
        return $user['role'] === 'area_manager'
            ? ['area_manager_id' => $user['id']]
            : [];
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────

    public function index(): void {
        auth_require(['admin', 'area_manager']);

        $user = auth_user();

        $filters = [
            'search'    => trim($_GET['search']    ?? ''),
            'branch_id' => (int) ($_GET['branch_id'] ?? 0) ?: '',
            'visible'   => $_GET['visibility'] ?? '',
        ];

        if ($user['role'] === 'area_manager') {
            $filters['area_manager_id'] = $user['id'];
        }

        $captains = $this->captains->findAll($filters);
        $branches = $this->branchModel->findAll($this->branchFilters($user));

        $this->renderView('index', [
            'pageTitle'     => 'الكباتن',
            'breadcrumb'    => 'الإدارة · الكباتن',
            'captains'      => $captains,
            'filters'       => $filters,
            'branches'      => $branches,
            'isAreaManager' => $user['role'] === 'area_manager',
        ]);
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function create(): void {
        auth_require(['admin']);

        $this->renderView('create', [
            'pageTitle'   => 'كابتن جديد',
            'breadcrumb'  => 'الإدارة · الكباتن · كابتن جديد',
            'captain'     => [],
            'errors'      => [],
            'isEdit'      => false,
            'branches'    => $this->branchModel->findAll(),
            'assignedIds' => [],
        ]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────

    public function store(): void {
        auth_require(['admin']);

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if (!$errors && $this->captains->nameExists($data['captain_name'])) {
            $errors[] = 'يوجد كابتن بهذا الاسم مسبقاً.';
        }

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'   => 'كابتن جديد',
                'breadcrumb'  => 'الإدارة · الكباتن · كابتن جديد',
                'captain'     => $data,
                'errors'      => $errors,
                'isEdit'      => false,
                'branches'    => $this->branchModel->findAll(),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        $newId = $this->captains->create($data);
        $this->captains->syncBranches($newId, $data['branch_ids']);

        log_action('created_captain', "id: {$newId}, name: {$data['captain_name']}", auth_user()['id']);
        $this->flash('flash_success', 'تم إضافة الكابتن "' . htmlspecialchars($data['captain_name']) . '" بنجاح.');
        $this->redirect('/admin/captains');
    }

    // ── SHOW ──────────────────────────────────────────────────────────────────

    public function show(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = (int) ($_GET['id'] ?? 0);
        $captain = $this->captains->findById($id);

        if (!$captain) {
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'ليس لديك صلاحية عرض هذا الكابتن.');
            $this->redirect('/admin/captains');
            return;
        }

        $assignedBranches = [];
        if (!empty($captain['branch_ids'])) {
            foreach ($captain['branch_ids'] as $bid) {
                $b = $this->branchModel->findById($bid);
                if ($b) $assignedBranches[] = $b;
            }
        }

        $this->renderView('show', [
            'pageTitle'        => htmlspecialchars($captain['captain_name']),
            'breadcrumb'       => 'الإدارة · الكباتن · ' . htmlspecialchars($captain['captain_name']),
            'captain'          => $captain,
            'assignedBranches' => $assignedBranches,
        ]);
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────

    public function edit(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = (int) ($_GET['id'] ?? 0);
        $captain = $this->captains->findById($id);

        if (!$captain) {
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'ليس لديك صلاحية تعديل هذا الكابتن.');
            $this->redirect('/admin/captains');
            return;
        }

        $this->renderView('edit', [
            'pageTitle'   => 'تعديل الكابتن',
            'breadcrumb'  => 'الإدارة · الكباتن · تعديل',
            'captain'     => $captain,
            'errors'      => [],
            'isEdit'      => true,
            'branches'    => $this->branchModel->findAll($this->branchFilters($user)),
            'assignedIds' => $captain['branch_ids'],
        ]);
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = (int) ($_GET['id'] ?? 0);
        $captain = $this->captains->findById($id);

        if (!$captain) {
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'ليس لديك صلاحية تعديل هذا الكابتن.');
            $this->redirect('/admin/captains');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if (!$errors && $this->captains->nameExists($data['captain_name'], $id)) {
            $errors[] = 'يوجد كابتن بهذا الاسم مسبقاً.';
        }

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'   => 'تعديل الكابتن',
                'breadcrumb'  => 'الإدارة · الكباتن · تعديل',
                'captain'     => array_merge($captain, $data),
                'errors'      => $errors,
                'isEdit'      => true,
                'branches'    => $this->branchModel->findAll($this->branchFilters($user)),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        $this->captains->update($id, $data);
        $this->captains->syncBranches($id, $data['branch_ids']);

        log_action('updated_captain', "id: {$id}, name: {$data['captain_name']}", $user['id']);
        $this->flash('flash_success', 'تم تحديث بيانات الكابتن "' . htmlspecialchars($data['captain_name']) . '" بنجاح.');
        $this->redirect('/admin/captains');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────

    public function destroy(): void {
        auth_require(['admin']);

        $id      = (int) ($_GET['id'] ?? 0);
        $captain = $this->captains->findById($id);

        if (!$captain) {
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        $this->captains->hide($id);
        log_action('hidden_captain', "id: {$id}, name: {$captain['captain_name']}", auth_user()['id']);

        $this->flash('flash_success', 'تم إخفاء الكابتن "' . htmlspecialchars($captain['captain_name']) . '".');
        $this->redirect('/admin/captains');
    }

    

    public function ajaxSearch(): void {
    auth_require(['admin', 'area_manager']);

    header('Content-Type: application/json');

    $user = auth_user();

    $filters = [
        'search'    => trim($_GET['search']    ?? ''),
        'branch_id' => (int) ($_GET['branch_id'] ?? 0) ?: '',
        'visible'   => $_GET['visibility'] ?? '',
    ];

    if ($user['role'] === 'area_manager') {
        $filters['area_manager_id'] = $user['id'];
    }

    echo json_encode($this->captains->findAll($filters));
    exit;
}

}