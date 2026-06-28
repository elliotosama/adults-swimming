<?php
// app/controllers/BranchController.php

class BranchController {

    private BranchModel $branches;

    public function __construct() {
        $this->branches = new BranchModel();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/admin/branches/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'branch_name'       => trim($_POST['branch_name']       ?? ''),
            'country_id'        => (int) ($_POST['country_id']      ?? 0),
            'visible'           => ($_POST['visible'] ?? '1') === '1' ? 1 : 0,
            'working_days1'     => $_POST['working_days1']          ?? [],
            'working_days2'     => $_POST['working_days2']          ?? [],
            'working_days3'     => $_POST['working_days3']          ?? [],
            'working_time_from' => trim($_POST['working_time_from'] ?? ''),
            'working_time_to'   => trim($_POST['working_time_to']   ?? ''),
        ];
    }

    private function validate(array $data): array {
        $errors = [];

        if (strlen($data['branch_name']) < 2)
            $errors[] = 'Branch name must be at least 2 characters.';

        if (empty($data['country_id']))
            $errors[] = 'Country is required.';

        if ($data['working_time_from'] !== '' && $data['working_time_to'] !== '') {
            if ($data['working_time_from'] >= $data['working_time_to'])
                $errors[] = 'Working time "from" must be earlier than "to".';
        }

        return $errors;
    }

    private function validateScheduleOnly(array $data): array {
        $errors = [];

        if ($data['working_time_from'] !== '' && $data['working_time_to'] !== '') {
            if ($data['working_time_from'] >= $data['working_time_to'])
                $errors[] = 'Working time "from" must be earlier than "to".';
        }

        return $errors;
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /admin/branches
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin', 'area_manager']);

        $user = auth_user();

        $filters = [
            'search'     => trim($_GET['search']     ?? ''),
            'country_id' => trim($_GET['country_id'] ?? ''),
            'visibility' => trim($_GET['visibility'] ?? ''),
        ];

        // area_manager sees only their assigned branches
        if ($user['role'] === 'area_manager') {
            $filters['area_manager_id'] = $user['id'];
        }

        $branches  = $this->branches->findAll($filters);
        $countries = (new CountryModel())->findVisible();

        $this->renderView('index', [
            'pageTitle'     => 'Branches',
            'breadcrumb'    => 'Admin · Branches',
            'branches'      => $branches,
            'filters'       => $filters,
            'countries'     => $countries,
            'isAreaManager' => $user['role'] === 'area_manager',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /admin/branch/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin']);
        $countries = (new CountryModel())->findVisible();

        $this->renderView('create', [
            'pageTitle'     => 'New Branch',
            'breadcrumb'    => 'Admin · Branches · New Branch',
            'branch'        => [],
            'errors'        => [],
            'isEdit'        => false,
            'isAreaManager' => false,
            'countries'     => $countries,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /admin/branch/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {
        auth_require(['admin']);

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if (!$errors && $this->branches->nameExists($data['branch_name'])) {
            $errors[] = 'A branch with this name already exists.';
        }

        if ($errors) {
            $countries = (new CountryModel())->findVisible();
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'     => 'New Branch',
                'breadcrumb'    => 'Admin · Branches · New Branch',
                'branch'        => $data,
                'errors'        => $errors,
                'isEdit'        => false,
                'isAreaManager' => false,
                'countries'     => $countries,
            ]);
            return;
        }

        $newId = $this->branches->create($data);

        log_action('created_branch', "id: {$newId}, name: {$data['branch_name']}", auth_user()['id']);
        $this->flash('flash_success', 'Branch "' . htmlspecialchars($data['branch_name']) . '" created successfully.');
        $this->redirect('/admin/branches');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /admin/branch/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin', 'area_manager']);

        $id     = (int) ($_GET['id'] ?? 0);
        $branch = $this->branches->findById($id);

        if (!$branch) {
            $this->flash('flash_error', 'Branch not found.');
            $this->redirect('/admin/branches');
            return;
        }

        $user = auth_user();

        // area_manager may only view their own branches
        if ($user['role'] === 'area_manager' && !$this->branches->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'Access denied.');
            $this->redirect('/admin/branches');
            return;
        }

        $this->renderView('show', [
            'pageTitle'  => htmlspecialchars($branch['branch_name']),
            'breadcrumb' => 'Admin · Branches · ' . htmlspecialchars($branch['branch_name']),
            'branch'     => $branch,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /admin/branch/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin', 'area_manager']);

        $countries = (new CountryModel())->findVisible();
        $id        = (int) ($_GET['id'] ?? 0);
        $branch    = $this->branches->findById($id);

        if (!$branch) {
            $this->flash('flash_error', 'Branch not found.');
            $this->redirect('/admin/branches');
            return;
        }

        $user = auth_user();

        // area_manager may only edit their own branches
        if ($user['role'] === 'area_manager' && !$this->branches->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'Access denied.');
            $this->redirect('/admin/branches');
            return;
        }

        $this->renderView('edit', [
            'pageTitle'     => 'Edit Branch',
            'breadcrumb'    => 'Admin · Branches · Edit',
            'branch'        => $branch,
            'errors'        => [],
            'isEdit'        => true,
            'isAreaManager' => $user['role'] === 'area_manager',
            'countries'     => $countries,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /admin/branch/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin', 'area_manager']);

        $id     = (int) ($_GET['id'] ?? 0);
        $branch = $this->branches->findById($id);

        if (!$branch) {
            $this->flash('flash_error', 'Branch not found.');
            $this->redirect('/admin/branches');
            return;
        }

        $user = auth_user();

        // area_manager may only update their own branches
        if ($user['role'] === 'area_manager' && !$this->branches->isManagedBy($id, $user['id'])) {
            $this->flash('flash_error', 'Access denied.');
            $this->redirect('/admin/branches');
            return;
        }

        $data = $this->parseForm();

        if ($user['role'] === 'area_manager') {
            // Validate schedule fields only; preserve everything else from DB
            $errors = $this->validateScheduleOnly($data);

            $data['branch_name'] = $branch['branch_name'];
            $data['country_id']  = $branch['country_id'];
            $data['visible']     = $branch['visible'];
        } else {
            $errors = $this->validate($data);

            if (!$errors && $this->branches->nameExists($data['branch_name'], $id)) {
                $errors[] = 'A branch with this name already exists.';
            }
        }

        if ($errors) {
            $countries = (new CountryModel())->findVisible();
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'     => 'Edit Branch',
                'breadcrumb'    => 'Admin · Branches · Edit',
                'branch'        => array_merge($branch, $data),
                'errors'        => $errors,
                'isEdit'        => true,
                'isAreaManager' => $user['role'] === 'area_manager',
                'countries'     => $countries,
            ]);
            return;
        }

        $this->branches->update($id, $data);

        log_action('updated_branch', "id: {$id}, name: {$data['branch_name']}", $user['id']);
        $this->flash('flash_success', 'Branch "' . htmlspecialchars($data['branch_name']) . '" updated successfully.');
        $this->redirect('/admin/branches');
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /admin/branch/delete?id=x
    // Soft-delete only — sets visible = 0
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin']);

        $id     = (int) ($_GET['id'] ?? 0);
        $branch = $this->branches->findById($id);

        if (!$branch) {
            $this->flash('flash_error', 'Branch not found.');
            $this->redirect('/admin/branches');
            return;
        }

        $this->branches->hide($id);
        log_action('hidden_branch', "id: {$id}, name: {$branch['branch_name']}", auth_user()['id']);

        $this->flash('flash_success', 'Branch "' . htmlspecialchars($branch['branch_name']) . '" has been deactivated.');
        $this->redirect('/admin/branches');
    }
}