<?php
// app/controllers/CaptainController.php

class CaptainController {

    private CaptainModel $captains;
    private BranchModel  $branchModel;

    private string $uploadDir;     // absolute filesystem path
    private string $uploadRelBase; // path stored in DB / used to build URLs

    private const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_MIMES = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'application/pdf'  => 'pdf',
    ];

    public function __construct() {
        $this->captains       = new CaptainModel();
        $this->branchModel    = new BranchModel();
        $this->uploadRelBase  = 'uploads/captains_ids/';
        $this->uploadDir      = ROOT . '/public/' . $this->uploadRelBase;
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

    private function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function parseForm(): array {
        $age = trim($_POST['age'] ?? '');

        return [
            'captain_name' => trim($_POST['captain_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'age'          => $age !== '' ? (int) $age : null,
            'email'        => trim($_POST['email'] ?? '') ?: null,
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

        if ($data['age'] !== null && ($data['age'] < 18 || $data['age'] > 90))
            $errors[] = 'العمر يجب أن يكون بين 18 و 90 سنة.';

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'البريد الإلكتروني غير صحيح.';

        return $errors;
    }

    // Returns branch filters scoped to the current user's role
    private function branchFilters(array $user): array {
        return $user['role'] === 'area_manager'
            ? ['area_manager_id' => $user['id']]
            : [];
    }

    // ── ID card upload handling ─────────────────────────────────────────────

    /**
     * Processes $_FILES['ssn_card_path'] if present.
     * Returns ['path' => string|null, 'errors' => string[]]
     * 'path' is null when no file was uploaded (not an error) or on failure.
     */
    private function processIdUpload(): array {
        if (empty($_FILES['ssn_card_path']) || $_FILES['ssn_card_path']['error'] === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'errors' => []];
        }

        $file = $_FILES['ssn_card_path'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'errors' => ['حدث خطأ أثناء رفع صورة البطاقة.']];
        }

        if ($file['size'] > self::MAX_UPLOAD_SIZE) {
            return ['path' => null, 'errors' => ['حجم الملف كبير جداً (الحد الأقصى 5 ميجابايت).']];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            return ['path' => null, 'errors' => ['صيغة الملف غير مدعومة. الصيغ المسموحة: JPG, PNG, WEBP, PDF.']];
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $filename    = 'id_' . uniqid('', true) . '.' . self::ALLOWED_MIMES[$mime];
        $destination = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['path' => null, 'errors' => ['تعذر حفظ الملف على الخادم.']];
        }

        return ['path' => $this->uploadRelBase . $filename, 'errors' => []];
    }

    private function deleteIdFile(?string $relativePath): void {
        if (!$relativePath) return;
        $full = ROOT . '/public/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
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
        $branches = $this->branchModel->findVisible($this->branchFilters($user));

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
            'branches'    => $this->branchModel->findVisible(),
            'assignedIds' => [],
            'ajaxPartial' => $this->isAjax(),
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
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
                return;
            }

            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'   => 'كابتن جديد',
                'breadcrumb'  => 'الإدارة · الكباتن · كابتن جديد',
                'captain'     => $data,
                'errors'      => $errors,
                'isEdit'      => false,
                'branches'    => $this->branchModel->findVisible(),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        $upload = $this->processIdUpload();
        if ($upload['errors']) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'errors' => $upload['errors']], 422);
                return;
            }

            $this->flash('flash_error', implode('<br>', $upload['errors']));
            $this->renderView('create', [
                'pageTitle'   => 'كابتن جديد',
                'breadcrumb'  => 'الإدارة · الكباتن · كابتن جديد',
                'captain'     => $data,
                'errors'      => $upload['errors'],
                'isEdit'      => false,
                'branches'    => $this->branchModel->findVisible(),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        $data['ssn_card_path'] = $upload['path'];

        $newId = $this->captains->create($data);
        $this->captains->syncBranches($newId, $data['branch_ids']);

        log_action('created_captain', "id: {$newId}, name: {$data['captain_name']}", auth_user()['id']);

        $message = 'تم إضافة الكابتن "' . htmlspecialchars($data['captain_name']) . '" بنجاح.';

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $message, 'id' => $newId]);
            return;
        }

        $this->flash('flash_success', $message);
        $this->redirect('/admin/captains');
    }

    // ── SHOW ──────────────────────────────────────────────────────────────────

    public function show(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = $_GET['id'] ?? '';
        $captain = $this->captains->findById($id);

        if (!$captain) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'الكابتن غير موجود.'], 404);
                return;
            }
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'ليس لديك صلاحية عرض هذا الكابتن.'], 403);
                return;
            }
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
            'ajaxPartial'      => $this->isAjax(),
        ]);
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────

    public function edit(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = $_GET['id'] ?? '';
        $captain = $this->captains->findById($id);

        if (!$captain) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'الكابتن غير موجود.'], 404);
                return;
            }
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذا الكابتن.'], 403);
                return;
            }
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
            'branches'    => $this->branchModel->findVisible($this->branchFilters($user)),
            'assignedIds' => $captain['branch_ids'],
            'ajaxPartial' => $this->isAjax(),
        ]);
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(): void {
        auth_require(['admin', 'area_manager']);

        $user    = auth_user();
        $id      = $_GET['id'] ?? '';
        $captain = $this->captains->findById($id);

        if (!$captain) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'الكابتن غير موجود.'], 404);
                return;
            }
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        if ($user['role'] === 'area_manager' &&
            !$this->captains->isManagedBy($id, $user['id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذا الكابتن.'], 403);
                return;
            }
            $this->flash('flash_error', 'ليس لديك صلاحية تعديل هذا الكابتن.');
            $this->redirect('/admin/captains');
            return;
        }

        $data = $this->parseForm();
        if ($user['role'] === 'area_manager') {
            $data['visible'] = (int) ($captain['visible'] ?? 1);
        }
        $errors = $this->validate($data);

        if (!$errors && $this->captains->nameExists($data['captain_name'], $id)) {
            $errors[] = 'يوجد كابتن بهذا الاسم مسبقاً.';
        }

        if ($errors) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
                return;
            }

            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'   => 'تعديل الكابتن',
                'breadcrumb'  => 'الإدارة · الكباتن · تعديل',
                'captain'     => array_merge($captain, $data),
                'errors'      => $errors,
                'isEdit'      => true,
                'branches'    => $this->branchModel->findVisible($this->branchFilters($user)),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        $upload = $this->processIdUpload();
        if ($upload['errors']) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'errors' => $upload['errors']], 422);
                return;
            }

            $this->flash('flash_error', implode('<br>', $upload['errors']));
            $this->renderView('edit', [
                'pageTitle'   => 'تعديل الكابتن',
                'breadcrumb'  => 'الإدارة · الكباتن · تعديل',
                'captain'     => array_merge($captain, $data),
                'errors'      => $upload['errors'],
                'isEdit'      => true,
                'branches'    => $this->branchModel->findVisible($this->branchFilters($user)),
                'assignedIds' => $data['branch_ids'],
            ]);
            return;
        }

        if ($upload['path']) {
            // New card uploaded — remove the old file, if any
            $this->deleteIdFile($captain['ssn_card_path'] ?? null);
            $data['ssn_card_path'] = $upload['path'];
        } elseif (!empty($_POST['remove_ssn_card'])) {
            // Explicitly removed without replacement
            $this->deleteIdFile($captain['ssn_card_path'] ?? null);
            $data['ssn_card_path'] = null;
        } else {
            // Keep whatever was already there
            $data['ssn_card_path'] = $captain['ssn_card_path'] ?? null;
        }

        $this->captains->update($id, $data);
        $this->captains->syncBranches($id, $data['branch_ids']);

        log_action('updated_captain', "id: {$id}, name: {$data['captain_name']}", $user['id']);

        $message = 'تم تحديث بيانات الكابتن "' . htmlspecialchars($data['captain_name']) . '" بنجاح.';

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $message, 'id' => $id]);
            return;
        }

        $this->flash('flash_success', $message);
        $this->redirect('/admin/captains');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────

    public function destroy(): void {
        auth_require(['admin']);

        $id      = $_GET['id'] ?? '';
        $captain = $this->captains->findById($id);

        if (!$captain) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'الكابتن غير موجود.'], 404);
                return;
            }
            $this->flash('flash_error', 'الكابتن غير موجود.');
            $this->redirect('/admin/captains');
            return;
        }

        $this->captains->hide($id);
        log_action('hidden_captain', "id: {$id}, name: {$captain['captain_name']}", auth_user()['id']);

        $message = 'تم إخفاء الكابتن "' . htmlspecialchars($captain['captain_name']) . '".';

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $message]);
            return;
        }

        $this->flash('flash_success', $message);
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