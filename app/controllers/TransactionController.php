<?php
// app/controllers/TransactionController.php

class TransactionController {

    private TransactionModel     $transactions;
    private ReceiptModel         $receipts;
    private ReceiptAuditLogModel $auditLog;

    public function __construct() {
        $this->transactions = new TransactionModel();
        $this->receipts     = new ReceiptModel();
        $this->auditLog     = new ReceiptAuditLogModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    // ════════════════════════════════════════════════════════════════════════
    // effectiveCreatedAt
    //
    // Business-day cutoff: a transaction created between 12:00 AM and
    // 2:59:59 AM is recorded as belonging to the PREVIOUS calendar day.
    // Only the date component shifts — the time-of-day is preserved as-is.
    //
    // e.g. created 2026-07-07 01:45 → stored as 2026-07-06 01:45
    //      created 2026-07-07 02:59 → stored as 2026-07-06 02:59
    //      created 2026-07-07 03:00 → stored as 2026-07-07 03:00
    //
    // This mirrors ReceiptController::effectiveCreatedAt() exactly, so a
    // receipt and any transaction tied to it always land on the same
    // "business day" regardless of which controller created the transaction.
    //
    // Also used to stamp updated_at on edits, and as the changed_at passed
    // to ReceiptAuditLogModel so an audit row always agrees with the
    // updated_at of the row it's describing.
    // ════════════════════════════════════════════════════════════════════════
    private function effectiveCreatedAt(): string {
        $now = new DateTime('now', new DateTimeZone('Africa/Cairo'));
        if ((int) $now->format('H') < 3) {
            $now->modify('-1 day');
        }
        return $now->format('Y-m-d H:i:s');
    }

    private function handleUpload(): string|null {
        if (empty($_FILES['attachment']['name'])) return null;

        $file    = $_FILES['attachment'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if ($file['error'] !== UPLOAD_ERR_OK)
            throw new RuntimeException('فشل رفع الملف.');

        if ($file['size'] > $maxSize)
            throw new RuntimeException('حجم الملف يتجاوز الحد المسموح به (5MB).');

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed))
            throw new RuntimeException('نوع الملف غير مسموح به.');

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'txn_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir      = ROOT . '/public/uploads/transactions/';

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        move_uploaded_file($file['tmp_name'], $dir . $filename);

        return '/uploads/transactions/' . $filename;
    }

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/transactions/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    private function parseForm(): array {
        return [
            'payment_method' => trim($_POST['payment_method'] ?? ''),
            'amount'         => (float) ($_POST['amount'] ?? 0),
            'receipt_id'     => (int) ($_POST['receipt_id'] ?? 0) ?: null,
            'created_by'     => auth_user()['id'],
            'notes'          => trim($_POST['notes'] ?? ''),
            'type'           => 'payment',
        ];
    }

    private function validate(array $data, bool $isEdit = false): array {
        $errors = [];

        if (empty($data['receipt_id']))
            $errors[] = 'رقم الإيصال مطلوب.';

        if (empty($data['payment_method']))
            $errors[] = 'طريقة الدفع مطلوبة.';

        if ($data['amount'] <= 0)
            $errors[] = 'يجب أن يكون المبلغ أكبر من صفر.';

        $isCash = ($data['payment_method'] === 'نقداً');

        if (!$isEdit && !$isCash && empty($_FILES['attachment']['name']))
            $errors[] = 'صورة الإيصال مطلوبة.';

        return $errors;
    }

    // ── Build filters based on role ───────────────────────────────────────

    private function buildFilters(array $user, string $role, bool $skipCreatedByForReceiptSearch = false): array {
        switch ($role) {
            case 'customer_service':
                if ($skipCreatedByForReceiptSearch) {
                    return [];
                }
                return ['created_by' => $user['id']];

            case 'branch_manager':
                return ['branch_id' => $user['branch_id']];

            case 'area_manager':
                $branchIds = $this->receipts->getBranchIdsByArea($user['id']);
                return ['branch_ids' => $branchIds];

            case 'admin':
            default:
                return [];
        }
    }

    // ── Shared fetch logic used by both index() and searchJson() ──────────

    private function fetchTransactionsData(): array {
        $user    = auth_user();
        $role    = $user['role'];
        $perPage = 20;
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $searchReceiptId   = (int)  ($_GET['receipt_id']   ?? 0) ?: null;
        $searchClientPhone = trim(   $_GET['client_phone'] ?? '');

        $filters = $this->buildFilters($user, $role, skipCreatedByForReceiptSearch: (bool) $searchReceiptId);

        if ($searchReceiptId)   $filters['receipt_id']   = $searchReceiptId;
        if ($searchClientPhone) $filters['client_phone'] = $searchClientPhone;

        $filters['exclude_refunded_receipts'] = true;

        $transactions = $this->transactions->findFiltered($filters, $page, $perPage);
        $total        = $this->transactions->countFiltered($filters);
        $totalPages   = (int) ceil($total / $perPage);

        return [
            'transactions' => $transactions,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /transactions
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $result = $this->fetchTransactionsData();

        $this->renderView('index', [
            'pageTitle'    => 'المعاملات المالية',
            'breadcrumb'   => 'لوحة التحكم · المعاملات',
            'transactions' => $result['transactions'],
            'page'         => $result['page'],
            'totalPages'   => $result['totalPages'],
            'total'        => $result['total'],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEARCH JSON  —  GET /transaction/search-json
    //
    // AJAX endpoint powering the live filter form on the index page.
    // Accepts the same query params as index() (receipt_id, client_phone,
    // page) and returns the same data as JSON instead of rendering HTML.
    // ════════════════════════════════════════════════════════════════════════

    public function searchJson(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        header('Content-Type: application/json; charset=utf-8');

        $result = $this->fetchTransactionsData();

        echo json_encode([
            'data'       => $result['transactions'],
            'page'       => $result['page'],
            'totalPages' => $result['totalPages'],
            'total'      => $result['total'],
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // RECEIPT SEARCH JSON  —  GET /transaction/receipt-search
    // ════════════════════════════════════════════════════════════════════════

    public function receiptSearch(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        header('Content-Type: application/json; charset=utf-8');

        $q  = trim($_GET['q'] ?? '');
        $db = get_db();

        if ($q === '') {
            echo json_encode(['data' => []]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT r.id,
                   r.receipt_ref,
                   r.receipt_status,
                   c.client_name,
                   c.phone,
                   b.branch_name,
                   p.price        AS plan_price,
                   p.description  AS plan_name,
                   (
                       SELECT COALESCE(SUM(CASE WHEN type='payment' AND is_admin_adjustment = 0 THEN amount ELSE 0 END), 0)
                            - COALESCE(SUM(CASE WHEN type='refund' AND is_admin_adjustment = 0 THEN amount ELSE 0 END), 0)
                       FROM transactions t WHERE t.receipt_id = r.id
                   ) AS net_paid
            FROM receipts r
            LEFT JOIN clients  c ON c.id = r.client_id
            LEFT JOIN branches b ON b.id = r.branch_id
            LEFT JOIN prices   p ON p.id = r.plan_id
            WHERE r.id          = :exact_id
               OR r.receipt_ref LIKE :ref_like
               OR c.phone       LIKE :phone_like
               OR c.client_name LIKE :name_like
            ORDER BY r.id DESC
            LIMIT 10
        ");

        $exactId = ctype_digit($q) ? (int) $q : 0;
        $like    = '%' . $q . '%';

        $stmt->execute([
            ':exact_id'   => $exactId,
            ':ref_like'   => $like,
            ':phone_like' => $like,
            ':name_like'  => $like,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['data' => $rows]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE  —  GET /transaction/create
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $receiptId = (int) ($_GET['receipt_id'] ?? 0);
        $receipt   = $receiptId ? $this->receipts->findById($receiptId) : null;

        $this->renderView('create', [
            'pageTitle'   => 'معاملة جديدة',
            'breadcrumb'  => 'لوحة التحكم · المعاملات · جديدة',
            'transaction' => ['receipt_id' => $receiptId],
            'receipt'     => $receipt,
            'errors'      => [],
            'isEdit'      => false,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE  —  POST /transaction/create
    // ════════════════════════════════════════════════════════════════════════

    public function store(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $data   = $this->parseForm();
        $errors = $this->validate($data, false);

        if ($errors) {
            $receiptId = $data['receipt_id'];
            $receipt   = $receiptId ? $this->receipts->findById($receiptId) : null;
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('create', [
                'pageTitle'   => 'معاملة جديدة',
                'breadcrumb'  => 'لوحة التحكم · المعاملات · جديدة',
                'transaction' => $data,
                'receipt'     => $receipt,
                'errors'      => $errors,
                'isEdit'      => false,
            ]);
            return;
        }

        try {
            $data['attachment'] = $this->handleUpload();
        } catch (RuntimeException $e) {
            $this->flash('flash_error', $e->getMessage());
            $receiptId = $data['receipt_id'];
            $receipt   = $receiptId ? $this->receipts->findById($receiptId) : null;
            $this->renderView('create', [
                'pageTitle'   => 'معاملة جديدة',
                'breadcrumb'  => 'لوحة التحكم · المعاملات · جديدة',
                'transaction' => $data,
                'receipt'     => $receipt,
                'errors'      => [$e->getMessage()],
                'isEdit'      => false,
            ]);
            return;
        }

        // ── Business-day cutoff: a transaction created between 12:00–2:59 AM
        // is recorded under the previous calendar day. See effectiveCreatedAt()
        // for details — kept identical to ReceiptController's version so a
        // transaction created here lands on the same business day as it would
        // if it had been created through the receipt flow instead.
        $data['created_at'] = $this->effectiveCreatedAt();

        $newId = $this->transactions->create($data);

        if (!empty($data['receipt_id'])) {
            $this->auditLog->log(
                $data['receipt_id'],
                auth_user()['id'],
                auth_user()['role'],
                'transaction_added',
                null,
                "id:{$newId}, amount:{$data['amount']}, type:{$data['type']}",
                $data['created_at']
            );
        }

        log_action('created_transaction', "id: {$newId}, amount: {$data['amount']}", auth_user()['id']);
        $this->flash('flash_success', 'تمت إضافة المعاملة بنجاح.');

        if (!empty($data['receipt_id'])) {
            $this->redirect('/receipt/show?id=' . $data['receipt_id']);
        } else {
            $this->redirect('/transactions');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /transaction/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id          = (int) ($_GET['id'] ?? 0);
        $transaction = $this->transactions->findById($id);

        if (!$transaction) {
            $this->flash('flash_error', 'المعاملة غير موجودة.');
            $this->redirect('/transactions');
            return;
        }

        $receipt = $transaction['receipt_id']
            ? $this->receipts->findById($transaction['receipt_id'])
            : null;

        $this->renderView('show', [
            'pageTitle'   => 'عرض المعاملة #' . $id,
            'breadcrumb'  => 'لوحة التحكم · المعاملات · عرض',
            'transaction' => $transaction,
            'receipt'     => $receipt,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT  —  GET /transaction/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function edit(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id          = (int) ($_GET['id'] ?? 0);
        $transaction = $this->transactions->findById($id);

        if (!$transaction) {
            $this->flash('flash_error', 'المعاملة غير موجودة.');
            $this->redirect('/transactions');
            return;
        }

        $receipt = $transaction['receipt_id']
            ? $this->receipts->findById($transaction['receipt_id'])
            : null;

        $this->renderView('edit', [
            'pageTitle'   => 'تعديل المعاملة',
            'breadcrumb'  => 'لوحة التحكم · المعاملات · تعديل',
            'transaction' => $transaction,
            'receipt'     => $receipt,
            'errors'      => [],
            'isEdit'      => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE  —  POST /transaction/edit?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function update(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id          = (int) ($_GET['id'] ?? 0);
        $transaction = $this->transactions->findById($id);

        if (!$transaction) {
            $this->flash('flash_error', 'المعاملة غير موجودة.');
            $this->redirect('/transactions');
            return;
        }

        $data   = $this->parseForm();
        $errors = $this->validate($data, true);

        if ($errors) {
            $receipt = $data['receipt_id'] ? $this->receipts->findById($data['receipt_id']) : null;
            $this->flash('flash_error', implode('<br>', $errors));
            $this->renderView('edit', [
                'pageTitle'   => 'تعديل المعاملة',
                'breadcrumb'  => 'لوحة التحكم · المعاملات · تعديل',
                'transaction' => array_merge($transaction, $data),
                'receipt'     => $receipt,
                'errors'      => $errors,
                'isEdit'      => true,
            ]);
            return;
        }

        // NOTE: created_at is intentionally left untouched — editing an
        // existing transaction must not retroactively change which business
        // day it was originally recorded under. updated_at, however, should
        // reflect *this* edit's business day, using the same 12:00–2:59 AM
        // cutoff rule — see effectiveCreatedAt(). The same timestamp is also
        // passed to the audit log so both rows agree exactly.
        $updatedAt = $this->effectiveCreatedAt();
        $data['updated_at'] = $updatedAt;

        $this->transactions->update($id, $data);

        if (!empty($data['receipt_id'])) {
            $this->auditLog->log(
                $data['receipt_id'],
                auth_user()['id'],
                auth_user()['role'],
                'transaction_updated',
                "id:{$id}, amount:{$transaction['amount']}, type:{$transaction['type']}",
                "id:{$id}, amount:{$data['amount']}, type:{$data['type']}",
                $updatedAt
            );
        }

        log_action('updated_transaction', "id: {$id}", auth_user()['id']);
        $this->flash('flash_success', 'تم تحديث المعاملة بنجاح.');
        $this->redirect('/transactions');
    }

    // ════════════════════════════════════════════════════════════════════════
    // REMOVE EVIDENCE  —  GET /transaction/remove-evidence?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function removeEvidence(): void {
        auth_require(['admin']);

        $id = (int) ($_GET['id'] ?? 0);
        $db = get_db();

        $stmt = $db->prepare("SELECT attachment FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            $this->flash('flash_error', 'المعاملة غير موجودة.');
            $this->redirect('/receipts');
            return;
        }

        if (!empty($tx['attachment'])) {
            $filePath = ROOT . '/public' . $tx['attachment'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $db->prepare("UPDATE transactions SET attachment = NULL, updated_at = ? WHERE id = ?")
           ->execute([$this->effectiveCreatedAt(), $id]);

        $receiptStmt = $db->prepare("SELECT receipt_id FROM transactions WHERE id = ?");
        $receiptStmt->execute([$id]);
        $receiptId = $receiptStmt->fetchColumn();

        log_action('removed_evidence', "transaction_id: {$id}", auth_user()['id']);
        $this->flash('flash_success', 'تم حذف الإثبات بنجاح.');
        $this->redirect('/receipt/show?id=' . $receiptId);
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /transaction/delete?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id          = (int) ($_GET['id'] ?? 0);
        $transaction = $this->transactions->findById($id);

        if (!$transaction) {
            $this->flash('flash_error', 'المعاملة غير موجودة.');
            $this->redirect('/transactions');
            return;
        }

        $receiptId = $transaction['receipt_id'];
        $this->transactions->delete($id);
        log_action('deleted_transaction', "id: {$id}", auth_user()['id']);
        $this->flash('flash_success', 'تم حذف المعاملة بنجاح.');

        if ($receiptId) {
            $this->redirect('/receipt/show?id=' . $receiptId);
        } else {
            $this->redirect('/transactions');
        }
    }
}