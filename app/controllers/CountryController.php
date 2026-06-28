<?php

class CountryController {
    private CountryModel $country;

    public function __construct() {
        $this->country = new CountryModel();
    }

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    // FIX: original had typo `exract` → `extract`
    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/countries/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'country'      => trim($_POST['country']      ?? ''),
            'country_code' => strtoupper(trim($_POST['country_code'] ?? '')),
            'visible'      => isset($_POST['visible']) ? (int) $_POST['visible'] : 1,
        ];
    }

    // FIX: original checked strlen < 1 (should be < 2) BEFORE empty check — wrong order & wrong threshold
    private function validate(array $data): array {
        $errors = [];
        if (empty($data['country'])) {
            $errors[] = 'اسم الدولة مطلوب.';
        } elseif (strlen($data['country']) < 2) {
            $errors[] = 'يجب أن يتكون اسم الدولة من حرفين على الأقل.';
        }
        return $errors;
    }

    public function index(): void {
        auth_require(['admin']);

        $countries = $this->country->findAll();
        $this->renderView('index', [
            'pageTitle'  => 'الدول',
            'breadcrumb' => 'الرئيسية · الدول',
            'countries'  => $countries,
        ]);
    }

    public function create(): void {
        auth_require(['admin']);

        $this->renderView('create', [
            'pageTitle'  => 'إضافة دولة',
            'breadcrumb' => 'الرئيسية · الدول · إضافة دولة',
            'country'    => [],
            'errors'     => [],
            'isEdit'     => false,
        ]);
    }

    public function store(): void {
        auth_require(['admin']);

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        if (!$errors && $this->country->nameExists($data['country'])) {
            $errors[] = 'دولة بهذا الاسم موجودة بالفعل.';
        }

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'  => 'إضافة دولة',
                'breadcrumb' => 'الرئيسية · الدول · إضافة دولة',
                'country'    => $data,
                'errors'     => $errors,
                'isEdit'     => false,
            ]);
            return;
        }

        $newId = $this->country->create($data);

        log_action('created_country', "id: {$newId}, name: {$data['country']}", auth_user()['id']);
        $this->flash('flash_success', 'تم إضافة الدولة "' . htmlspecialchars($data['country']) . '" بنجاح.');
        $this->redirect('/country');
    }

    public function edit(): void {
        auth_require(['admin']);

        $id      = (int) ($_GET['id'] ?? 0);
        $country = $this->country->findById($id);

        if (!$country) {
            $this->flash('flash_error', 'الدولة غير موجودة.');
            $this->redirect('/country');
            return;
        }

        $this->renderView('edit', [
            'pageTitle'  => 'تعديل الدولة',
            'breadcrumb' => 'الرئيسية · الدول · تعديل',
            'country'    => $country,
            'errors'     => [],
            'isEdit'     => true,
        ]);
    }

    public function update(): void {
        auth_require(['admin']);

        $id      = (int) ($_GET['id'] ?? 0);
        $country = $this->country->findById($id);

        if (!$country) {
            $this->flash('flash_error', 'الدولة غير موجودة.');
            $this->redirect('/country');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data);

        // FIX: original passed $data['branch_name'] (undefined key) — must be $data['country']
        if (!$errors && $this->country->nameExists($data['country'], $id)) {
            $errors[] = 'دولة بهذا الاسم موجودة بالفعل.';
        }

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'  => 'تعديل الدولة',
                'breadcrumb' => 'الرئيسية · الدول · تعديل',
                'country'    => array_merge($country, $data),
                'errors'     => $errors,
                'isEdit'     => true,
            ]);
            return;
        }

        $this->country->update($id, $data);

        // FIX: original used $data['branch_name'] (undefined) in log_action — fixed to $data['country']
        log_action('updated_country', "id: {$id}, name: {$data['country']}", auth_user()['id']);
        $this->flash('flash_success', 'تم تعديل الدولة "' . htmlspecialchars($data['country']) . '" بنجاح.');
        $this->redirect('/country');
    }

    // FIX: method was named `destory` (typo) → `destroy`
    public function destroy(): void {
        auth_require(['admin']);

        $id      = (int) ($_GET['id'] ?? 0);
        $country = $this->country->findById($id);

        if (!$country) {
            $this->flash('flash_error', 'الدولة غير موجودة.');
            $this->redirect('/country');
            return;
        }

        $this->country->hide($id);
        log_action('hidden_country', "id: {$id}, name: {$country['country']}", auth_user()['id']);

        $this->flash('flash_success', 'تم إخفاء الدولة "' . htmlspecialchars($country['country']) . '" بنجاح.');
        $this->redirect('/country');
    }
}