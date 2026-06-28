<?php
// app/controllers/PriceController.php

class PriceController {

    private PriceModel $prices;

    public function __construct() {
        $this->prices = new PriceModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/admin/prices/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'description'        => trim($_POST['description']        ?? ''),
            'price'              => trim($_POST['price']              ?? ''),
            'country_id'         => (int) ($_POST['country_id']       ?? 0) ?: null,
            'visible'            => ($_POST['visible'] ?? '1') === '1' ? 1 : 0,
            'number_of_sessions' => (int) ($_POST['number_of_sessions'] ?? 0),
        ];
    }

    private function validate(array $data): array {
        $errors = [];

        if (strlen($data['description']) < 2)
            $errors[] = 'يجب أن يحتوي الوصف على حرفين على الأقل.';

        if (!is_numeric($data['price']) || (float)$data['price'] < 0)
            $errors[] = 'يرجى إدخال سعر صحيح.';

        if (empty($data['country_id']))
            $errors[] = 'الدولة مطلوبة.';

        if ($data['number_of_sessions'] < 1)
            $errors[] = 'يجب أن يكون عدد الجلسات 1 على الأقل.';

        return $errors;
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /admin/prices
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin']);

        $filters = [
            'search'     => trim($_GET['search']     ?? ''),
            'country_id' => (int) ($_GET['country_id'] ?? 0) ?: '',
            'visible'    => $_GET['visible'] ?? '',
        ];

        $prices    = $this->prices->findFiltered($filters);
        $countries = (new CountryModel())->findVisible();

        $this->renderView('index', [
            'pageTitle'  => 'الأسعار',
            'breadcrumb' => 'لوحة التحكم · الأسعار',
            'prices'     => $prices,
            'filters'    => $filters,
            'countries'  => $countries,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /admin/prices/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin']);
        $countries = (new CountryModel())->findVisible();
        $this->renderView('create', [
            'pageTitle'  => 'إضافة سعر',
            'breadcrumb' => 'لوحة التحكم · الأسعار · إضافة سعر',
            'price'      => [],
            'errors'     => [],
            'isEdit'     => false,
            'countries'  => $countries,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /admin/prices/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {
        auth_require(['admin']);

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $countries = (new CountryModel())->findVisible();
            $this->renderView('create', [
                'pageTitle'  => 'إضافة سعر',
                'breadcrumb' => 'لوحة التحكم · الأسعار · إضافة سعر',
                'price'      => $data,
                'errors'     => $errors,
                'isEdit'     => false,
                'countries'  => $countries,
            ]);
            return;
        }

        $newId = $this->prices->create($data);

        log_action('created_price', "id: {$newId}, description: {$data['description']}", auth_user()['id']);
        $this->flash('flash_success', 'تم إضافة السعر "' . htmlspecialchars($data['description']) . '" بنجاح.');
        $this->redirect('/admin/prices');
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /admin/prices/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin']);

        $id    = (int) ($_GET['id'] ?? 0);
        $price = $this->prices->findById($id);

        if (!$price) {
            $this->flash('flash_error', 'السعر غير موجود.');
            $this->redirect('/admin/prices');
            return;
        }

        $this->renderView('show', [
            'pageTitle'  => htmlspecialchars($price['description']),
            'breadcrumb' => 'لوحة التحكم · الأسعار · ' . htmlspecialchars($price['description']),
            'price'      => $price,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /admin/prices/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin']);

        $id    = (int) ($_GET['id'] ?? 0);
        $price = $this->prices->findById($id);

        if (!$price) {
            $this->flash('flash_error', 'السعر غير موجود.');
            $this->redirect('/admin/prices');
            return;
        }

        $countries = (new CountryModel())->findVisible();
        $this->renderView('edit', [
            'pageTitle'  => 'تعديل السعر',
            'breadcrumb' => 'لوحة التحكم · الأسعار · تعديل',
            'price'      => $price,
            'errors'     => [],
            'isEdit'     => true,
            'countries'  => $countries,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /admin/prices/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin']);

        $id    = (int) ($_GET['id'] ?? 0);
        $price = $this->prices->findById($id);

        if (!$price) {
            $this->flash('flash_error', 'السعر غير موجود.');
            $this->redirect('/admin/prices');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $countries = (new CountryModel())->findVisible();
            $this->renderView('edit', [
                'pageTitle'  => 'تعديل السعر',
                'breadcrumb' => 'لوحة التحكم · الأسعار · تعديل',
                'price'      => array_merge($price, $data),
                'errors'     => $errors,
                'isEdit'     => true,
                'countries'  => $countries,
            ]);
            return;
        }

        $this->prices->update($id, $data);

        log_action('updated_price', "id: {$id}, description: {$data['description']}", auth_user()['id']);
        $this->flash('flash_success', 'تم تحديث السعر "' . htmlspecialchars($data['description']) . '" بنجاح.');
        $this->redirect('/admin/prices');
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /admin/prices/delete?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin']);

        $id    = (int) ($_GET['id'] ?? 0);
        $price = $this->prices->findById($id);

        if (!$price) {
            $this->flash('flash_error', 'السعر غير موجود.');
            $this->redirect('/admin/prices');
            return;
        }

        $this->prices->hide($id);
        log_action('hidden_price', "id: {$id}, description: {$price['description']}", auth_user()['id']);

        $this->flash('flash_success', 'تم تعطيل السعر "' . htmlspecialchars($price['description']) . '" بنجاح.');
        $this->redirect('/admin/prices');
    }
}