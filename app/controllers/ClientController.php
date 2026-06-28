<?php
// app/controllers/ClientController.php

class ClientController {

    private ClientModel $clients;

    public function __construct() {
        $this->clients = new ClientModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/clients/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'client_name' => trim($_POST['client_name'] ?? ''),
            'phone'       => trim($_POST['phone']       ?? ''),
            'email'       => trim($_POST['email']       ?? ''),
            'age'         => (int) ($_POST['age']       ?? 0) ?: null,
            'gender'      => trim($_POST['gender']      ?? ''),
            'created_by'  => auth_user()['id'],
        ];
    }

    private function validate(array $data, bool $isEdit = false, int $id = 0): array {
        $errors = [];

        if (strlen($data['client_name']) < 2)
            $errors[] = 'اسم العميل يجب أن يكون حرفين على الأقل.';

        if (empty($data['phone']))
            $errors[] = 'رقم الهاتف مطلوب.';
        elseif (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $data['phone']))
            $errors[] = 'رقم الهاتف غير صالح.';
        elseif ($this->clients->phoneExists($data['phone'], $id))
            $errors[] = 'رقم الهاتف مستخدم بالفعل.';

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'البريد الإلكتروني غير صالح.';
        elseif (!empty($data['email']) && $this->clients->emailExists($data['email'], $id))
            $errors[] = 'البريد الإلكتروني مستخدم بالفعل.';

        if (!empty($data['age']) && ($data['age'] < 1 || $data['age'] > 120))
            $errors[] = 'العمر غير صالح.';

        $validGenders = ['male', 'female', ''];
        if (!in_array($data['gender'], $validGenders, true))
            $errors[] = 'الجنس غير صالح.';

        return $errors;
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /clients
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $perPage = 20;
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'gender' => trim($_GET['gender'] ?? ''),
        ];

        $clients    = $this->clients->findFiltered($filters, $page, $perPage);
        $total      = $this->clients->countFiltered($filters);
        $totalPages = (int) ceil($total / $perPage);

        $this->renderView('index', [
            'pageTitle'  => 'العملاء',
            'breadcrumb' => 'لوحة التحكم · العملاء',
            'clients'    => $clients,
            'filters'    => $filters,
            'page'       => $page,
            'total'      => $total,
            'totalPages' => $totalPages,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /client/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $this->renderView('create', [
            'pageTitle'  => 'عميل جديد',
            'breadcrumb' => 'لوحة التحكم · العملاء · جديد',
            'client'     => [],
            'errors'     => [],
            'isEdit'     => false,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /client/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'  => 'عميل جديد',
                'breadcrumb' => 'لوحة التحكم · العملاء · جديد',
                'client'     => $data,
                'errors'     => $errors,
                'isEdit'     => false,
            ]);
            return;
        }

        $newId = $this->clients->create($data);
        log_action('created_client', "id: {$newId}, name: {$data['client_name']}", auth_user()['id']);
        $this->flash('flash_success', 'تم إضافة العميل "' . htmlspecialchars($data['client_name']) . '" بنجاح.');
        $this->redirect('/clients');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /client/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id     = (int) ($_GET['id'] ?? 0);
        $client = $this->clients->findById($id);

        if (!$client) {
            $this->flash('flash_error', 'العميل غير موجود.');
            $this->redirect('/clients');
            return;
        }

        $this->renderView('show', [
            'pageTitle'  => htmlspecialchars($client['client_name']),
            'breadcrumb' => 'لوحة التحكم · العملاء · ' . htmlspecialchars($client['client_name']),
            'client'     => $client,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /client/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id     = (int) ($_GET['id'] ?? 0);
        $client = $this->clients->findById($id);

        if (!$client) {
            $this->flash('flash_error', 'العميل غير موجود.');
            $this->redirect('/clients');
            return;
        }

        $this->renderView('edit', [
            'pageTitle'  => 'تعديل العميل',
            'breadcrumb' => 'لوحة التحكم · العملاء · تعديل',
            'client'     => $client,
            'errors'     => [],
            'isEdit'     => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /client/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id     = (int) ($_GET['id'] ?? 0);
        $client = $this->clients->findById($id);

        if (!$client) {
            $this->flash('flash_error', 'العميل غير موجود.');
            $this->redirect('/clients');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data, true, $id);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'  => 'تعديل العميل',
                'breadcrumb' => 'لوحة التحكم · العملاء · تعديل',
                'client'     => array_merge($client, $data),
                'errors'     => $errors,
                'isEdit'     => true,
            ]);
            return;
        }

        $this->clients->update($id, $data);
        log_action('updated_client', "id: {$id}, name: {$data['client_name']}", auth_user()['id']);
        $this->flash('flash_success', 'تم تحديث بيانات العميل "' . htmlspecialchars($data['client_name']) . '" بنجاح.');
        $this->redirect('/clients');
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /client/delete?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin']);

        $id     = (int) ($_GET['id'] ?? 0);
        $client = $this->clients->findById($id);

        if (!$client) {
            $this->flash('flash_error', 'العميل غير موجود.');
            $this->redirect('/clients');
            return;
        }

        $this->clients->delete($id);
        log_action('deleted_client', "id: {$id}, name: {$client['client_name']}", auth_user()['id']);
        $this->flash('flash_success', 'تم حذف العميل "' . htmlspecialchars($client['client_name']) . '" بنجاح.');
        $this->redirect('/clients');
    }
}