<?php
// app/controllers/ReceiptController.php
require ROOT . '/app/models/ReceiptAuditLogModel.php';
require_once ROOT . '/app/helpers/PhoneHelper.php';

class ReceiptController {

    private ReceiptModel         $receipts;
    private ReceiptAuditLogModel $auditLog;
    private TransactionModel     $transactions;

    private const PER_PAGE = 25;

    // Minimum net-paid ratio to allow a renewal (e.g. 0.30 = 30 %)
    private const RENEWAL_MIN_NET_RATIO = 0.30;

    // Academy-fault refund thresholds (based on what client actually PAID)
    private const ACADEMY_FAULT_MIN_RATIO = 0.50;  // 50 %
    private const ACADEMY_FAULT_MAX_RATIO = 0.99;  // <100 %

    public function __construct() {
        $this->receipts     = new ReceiptModel();
        $this->auditLog     = new ReceiptAuditLogModel();
        $this->transactions = new TransactionModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/receipts/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    // ── Branch scoping for branch_manager (many-to-many via user_branch) ──
    //
    // Returns the full list of branch IDs this user manages when they are a
    // branch_manager, or an empty array for every other role (meaning: no
    // restriction — searchClientFlexible() and friends treat [] as "no scope").

    private function managerBranchIds(): array {
        $user = auth_user();
        return $user['role'] === 'branch_manager'
            ? $this->receipts->getBranchIdsByManager($user['id'])
            : [];
    }

    private function parseForm(): array {
        return [
            'client_name'     => trim($_POST['client_name']     ?? ''),
            'phone'           => trim($_POST['full_phone']       ?? trim($_POST['phone'] ?? '')),
            'client_email'    => trim($_POST['client_email']     ?? ''),
            'client_age'      => (int)($_POST['client_age']      ?? 0) ?: null,
            'client_gender'   => trim($_POST['client_gender']    ?? ''),
            'client_id'       => (int) ($_POST['client_id']      ?? 0),
            'creator_id'      => (int) ($_POST['creator_id']     ?? 0),
            'captain_id'      => (int) ($_POST['captain_id']     ?? 0),
            'branch_id'       => (int) ($_POST['branch_id']      ?? 0),
            'first_session'   => trim($_POST['first_session']    ?? ''),
            'last_session'    => trim($_POST['last_session']     ?? ''),
            'renewal_session' => trim($_POST['renewal_session']  ?? ''),
            'receipt_status'  => trim($_POST['receipt_status']   ?? 'not_completed'),
            'exercise_time'   => trim($_POST['exercise_time']    ?? ''),
            'plan_id'         => (int) ($_POST['plan_id']        ?? 0) ?: null,
            'level'           => (int) ($_POST['level']          ?? 0) ?: null,
            'pdf_path'        => trim($_POST['pdf_path']         ?? ''),
            'amount'          => (float) ($_POST['amount']       ?? 0),
            'remaining'       => (float) ($_POST['remaining']    ?? 0),
            'payment_method'  => trim($_POST['payment_method']   ?? ''),
            'notes'           => trim($_POST['notes']            ?? ''),
            'renewal_type'    => trim($_POST['renewal_type']     ?? 'new'),
        ];
    }

    // ── Session-aware filter persistence ─────────────────────────────────

    private function resolveFilters(): array {
        if (!empty($_GET['reset'])) {
            unset($_SESSION['receipt_filters']);
            $this->redirect('/receipts');
        }

        $hasInput = count(array_diff(array_keys($_GET), ['page'])) > 0;

        if ($hasInput) {
            $filters = $this->parseFilters();
            $_SESSION['receipt_filters'] = $filters;
        } elseif (!empty($_SESSION['receipt_filters'])) {
            $filters = $_SESSION['receipt_filters'];
        } else {
            $filters = $this->parseFilters();
        }

        return $filters;
    }

    // ════════════════════════════════════════════════════════════════════════
    // SEARCH JSON — GET /receipts/search-json
    // ════════════════════════════════════════════════════════════════════════

    public function searchJson(): void {
        auth_require(['admin', 'branch_manager', 'customer_service', 'area_manager']);

        $scope   = $this->roleScope();
        $filters = array_merge($this->parseFilters(), $scope['forced']);
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->receipts->search($filters, $page, self::PER_PAGE);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'     => $result['data'],
            'total'    => $result['total'],
            'page'     => $page,
            'lastPage' => (int) ceil($result['total'] / self::PER_PAGE),
            'perPage'  => self::PER_PAGE,
        ]);
        exit;
    }

    private function validate(array $data): array {
        $errors = [];

        if (empty($data['branch_id']))
            $errors[] = 'يجب اختيار الفرع.';

        if (!empty($data['first_session']) && !empty($data['last_session'])
            && $data['last_session'] < $data['first_session']) {
            $errors[] = 'تاريخ آخر جلسة لا يمكن أن يكون قبل تاريخ أول جلسة.';
        }

        if (empty($data['payment_method']))
            $errors[] = 'يجب اختيار طريقة الدفع.';

        return $errors;
    }

    private function parseFilters(): array {
        return [
            'search'               => trim($_GET['search']               ?? ''),
            'first_session_from'   => trim($_GET['first_session_from']   ?? ''),
            'first_session_to'     => trim($_GET['first_session_to']     ?? ''),
            'last_session_from'    => trim($_GET['last_session_from']    ?? ''),
            'last_session_to'      => trim($_GET['last_session_to']      ?? ''),
            'created_from'         => trim($_GET['created_from']         ?? ''),
            'created_to'           => trim($_GET['created_to']           ?? ''),
            'statuses'             => (array) ($_GET['statuses']         ?? []),
            'renewal_types'        => array_filter((array) ($_GET['renewal_types'] ?? [])),
            'has_refund'           => !empty($_GET['has_refund']),
            'creator_id'           => (int)   ($_GET['creator_id']       ?? 0) ?: null,
            'creator_created_only' => !empty($_GET['creator_created_only']),
            'branch_ids'           => array_filter(array_map('intval', (array) ($_GET['branch_ids'] ?? []))),
            'has_updates'          => !empty($_GET['has_updates']),
        ];
    }

    private function roleScope(): array {
        $user = auth_user();
        $role = $user['role'];

        $allFilterControls = [
            'search', 'first_session', 'last_session', 'created',
            'statuses', 'renewal_types', 'has_refund',
            'branch', 'creator', 'has_updates',
        ];

        switch ($role) {
            case 'branch_manager':
                $branchIds = $this->receipts->getBranchIdsByManager($user['id']);
                return [
                    'forced'          => [
                        'force_branch_ids' => $branchIds ?: [0],
                    ],
                    'allowed_filters' => array_diff($allFilterControls, ['branch', 'creator']),
                    'managed_branch_ids' => $branchIds,
                ];

            case 'customer_service':
                return [
                    'forced'          => [
                        'force_creator_id' => $user['id'],
                    ],
                    'allowed_filters' => array_diff($allFilterControls, ['branch', 'creator']),
                ];

            case 'area_manager':
                $branchIds = $this->receipts->getBranchIdsByArea($user['id']);
                return [
                    'forced'          => [
                        'force_branch_ids' => $branchIds ?: [0],
                    ],
                    'allowed_filters' => array_diff($allFilterControls, ['creator']),
                    'managed_branch_ids' => $branchIds,
                ];

            default: // admin
                return [
                    'forced'          => [],
                    'allowed_filters' => $allFilterControls,
                ];
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // formDropdowns
    // ════════════════════════════════════════════════════════════════════════

    private function formDropdowns(): array {
        $db = get_db();

        $branches = $db->query("
            SELECT b.id, b.branch_name,
                   b.working_days1, b.working_days2, b.working_days3,
                   b.working_time_from, b.working_time_to,
                   c.id AS country_id, c.country, c.country_code
            FROM branches b
            JOIN countries c ON c.id = b.country_id
            WHERE b.visible = 1
            ORDER BY b.branch_name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $captainRows = $db->query("
            SELECT cb.branch_id, c.id, c.captain_name
            FROM captain_branch cb
            JOIN captains c ON c.id = cb.captain_id
            WHERE c.visible = 1
            ORDER BY c.captain_name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $captainsByBranch = [];
        foreach ($captainRows as $row) {
            $captainsByBranch[$row['branch_id']][] = [
                'id'   => $row['id'],
                'name' => $row['captain_name'],
            ];
        }

        $plans = $db->query("
            SELECT p.id, p.description, p.price, p.number_of_sessions,
                   p.country_id, c.country
            FROM prices p
            JOIN countries c ON c.id = p.country_id
            WHERE p.visible = 1
            ORDER BY p.description
        ")->fetchAll(PDO::FETCH_ASSOC);

        $plansByCountry = [];
        foreach ($plans as $plan) {
            $plansByCountry[$plan['country_id']][] = $plan;
        }

        return [
            'branches'         => $branches,
            'captainsByBranch' => $captainsByBranch,
            'plans'            => $plans,
            'plansByCountry'   => $plansByCountry,
        ];
    }

    // ── File upload helper ────────────────────────────────────────────────────

    private function handleEvidenceUpload(): ?string {
        if (empty($_FILES['transaction_evidence']['tmp_name'])) {
            return null;
        }

        $file    = $_FILES['transaction_evidence'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            return null;
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('ev_', true) . '.' . $ext;
        $saveDir  = ROOT . '/public/uploads/evidence';

        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0775, true);
        }

        $dest = $saveDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return '/uploads/evidence/' . $filename;
        }

        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // findClientByPhone
    // ════════════════════════════════════════════════════════════════════════

    private function findClientByPhone(string $rawPhone): ?array {
        $db = get_db();
        [$sql, $params] = PhoneHelper::buildSearchCondition($rawPhone);
        $stmt = $db->prepare("SELECT * FROM clients WHERE {$sql} LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // searchClientFlexible
    //
    // $allowedBranchIds: when non-empty (branch_manager with a many-to-many
    // user_branch mapping), every search path is additionally scoped so a
    // matched client must have at least one receipt in one of those branches.
    // Empty array = no restriction (admin, area_manager, customer_service —
    // area_manager/customer_service scoping is enforced elsewhere via
    // roleScope(), not here).
    // ════════════════════════════════════════════════════════════════════════

    private function searchClientFlexible(string $q, array $allowedBranchIds = []): ?array {
        $db     = get_db();
        $scoped = !empty($allowedBranchIds);
        $ph     = $scoped ? implode(',', array_fill(0, count($allowedBranchIds), '?')) : '';

        // ── Receipt ID / ref search (short numeric query) ──────────────────
        if (ctype_digit($q) && strlen($q) <= 7) {
            $sql = "
                SELECT cl.* FROM receipts r
                JOIN clients cl ON cl.id = r.client_id
                WHERE r.id = ?" . ($scoped ? " AND r.branch_id IN ({$ph})" : "") . "
                LIMIT 1
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge([(int)$q], $allowedBranchIds));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }

        // ── Phone search ─────────────────────────────────────────────────
        [$phoneSql, $phoneParams] = PhoneHelper::buildSearchCondition($q);

        if ($scoped) {
            $stmt = $db->prepare("
                SELECT cl.* FROM clients cl
                WHERE ({$phoneSql})
                  AND EXISTS (
                      SELECT 1 FROM receipts r
                      WHERE r.client_id = cl.id AND r.branch_id IN ({$ph})
                  )
                LIMIT 1
            ");
            $stmt->execute(array_merge($phoneParams, $allowedBranchIds));
        } else {
            $stmt = $db->prepare("SELECT * FROM clients WHERE {$phoneSql} LIMIT 1");
            $stmt->execute($phoneParams);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        // ── Name search ──────────────────────────────────────────────────
        if ($scoped) {
            $stmt = $db->prepare("
                SELECT cl.* FROM clients cl
                WHERE cl.client_name LIKE ?
                  AND EXISTS (
                      SELECT 1 FROM receipts r
                      WHERE r.client_id = cl.id AND r.branch_id IN ({$ph})
                  )
                LIMIT 1
            ");
            $stmt->execute(array_merge(['%' . $q . '%'], $allowedBranchIds));
        } else {
            $stmt = $db->prepare("SELECT * FROM clients WHERE client_name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $q . '%']);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // searchClientByIdOrPhone
    //
    // Used ONLY by the renewal tab/page. Matches the client's own primary key
    // (clients.id) or phone number — never receipt IDs and never client name.
    // Renewal eligibility is sensitive enough that a fuzzy name match could
    // pull up the wrong person, so this intentionally has no name fallback.
    // ════════════════════════════════════════════════════════════════════════

    private function searchClientByIdOrPhone(string $q, array $allowedBranchIds = []): ?array {
        $db     = get_db();
        $scoped = !empty($allowedBranchIds);
        $ph     = $scoped ? implode(',', array_fill(0, count($allowedBranchIds), '?')) : '';

        // ── Client ID (clients.id) ──────────────────────────────────────
        if (ctype_digit($q)) {
            if ($scoped) {
                $stmt = $db->prepare("
                    SELECT cl.* FROM clients cl
                    WHERE cl.id = ?
                      AND EXISTS (
                          SELECT 1 FROM receipts r
                          WHERE r.client_id = cl.id AND r.branch_id IN ({$ph})
                      )
                    LIMIT 1
                ");
                $stmt->execute(array_merge([(int)$q], $allowedBranchIds));
            } else {
                $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$q]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }

        // ── Phone number ────────────────────────────────────────────────
        [$phoneSql, $phoneParams] = PhoneHelper::buildSearchCondition($q);

        if ($scoped) {
            $stmt = $db->prepare("
                SELECT cl.* FROM clients cl
                WHERE ({$phoneSql})
                  AND EXISTS (
                      SELECT 1 FROM receipts r
                      WHERE r.client_id = cl.id AND r.branch_id IN ({$ph})
                  )
                LIMIT 1
            ");
            $stmt->execute(array_merge($phoneParams, $allowedBranchIds));
        } else {
            $stmt = $db->prepare("SELECT * FROM clients WHERE {$phoneSql} LIMIT 1");
            $stmt->execute($phoneParams);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // getReceiptNetStatus
    // ════════════════════════════════════════════════════════════════════════

    private function getReceiptNetStatus(int $receiptId, float $planPrice): array {
        $db   = get_db();
        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type='payment' THEN amount ELSE 0 END), 0) AS gross_paid,
                COALESCE(SUM(CASE WHEN type='refund'  THEN amount ELSE 0 END), 0) AS total_refunded
            FROM transactions WHERE receipt_id = ?
        ");
        $stmt->execute([$receiptId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        $grossPaid      = (float) $tx['gross_paid'];
        $totalRefunded  = (float) $tx['total_refunded'];
        $netPaid        = $grossPaid - $totalRefunded;
        $remaining      = max(0, $planPrice - $netPaid);
        $refundRatio    = $planPrice > 0 ? ($totalRefunded / $planPrice) : 0;

        return compact('grossPaid', 'totalRefunded', 'netPaid', 'remaining', 'refundRatio');
    }

    // ════════════════════════════════════════════════════════════════════════
    // resolveRenewalType
    // ════════════════════════════════════════════════════════════════════════

    private function resolveRenewalType(string $lastSession): string {
        if (!$lastSession) return 'current_renewal';

        try {
            $lastDate = new DateTime($lastSession);
        } catch (\Exception $e) {
            return 'current_renewal';
        }

        $lastDay   = (int) $lastDate->format('j');
        $lastMonth = (int) $lastDate->format('n');
        $lastYear  = (int) $lastDate->format('Y');

        $today     = new DateTime();
        $todayDay  = (int) $today->format('j');
        $thisMonth = (int) $today->format('n');
        $thisYear  = (int) $today->format('Y');

        if ($lastYear === $thisYear && $lastMonth === $thisMonth) {
            return $lastDay <= 21 ? 'current_renewal' : 'previous_renewal';
        }

        $prevMonth = $thisMonth === 1 ? 12 : $thisMonth - 1;
        $prevYear  = $thisMonth === 1 ? $thisYear - 1 : $thisYear;

        if (
            $lastYear  === $prevYear  &&
            $lastMonth === $prevMonth &&
            $lastDay   >  21          &&
            $todayDay  <  20
        ) {
            return 'previous_renewal';
        }

        return 'current_renewal';
    }

    // ════════════════════════════════════════════════════════════════════════
    // checkRenewalEligibility
    // ════════════════════════════════════════════════════════════════════════

private function checkRenewalEligibility(int $clientId, string $newFirstSession = ''): array {
    $db = get_db();

    $stmt = $db->prepare("
        SELECT r.*, p.price AS plan_price
        FROM receipts r
        LEFT JOIN prices p ON p.id = r.plan_id
        WHERE r.client_id = ?
        ORDER BY r.id DESC
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);

    // No previous receipt → brand-new client
    if (!$prev) {
        return ['ok' => true, 'is_new' => true, 'is_academy_fault' => false, 'message' => ''];
    }

    $planPrice = (float) ($prev['plan_price'] ?? 0);
    $status    = $prev['receipt_status'] ?? 'not_completed';

    // Same-date guard
    if ($newFirstSession && $prev['first_session'] === $newFirstSession) {
        return [
            'ok'         => false,
            'is_new'     => false,
            'block_type' => 'same_date',
            'message'    => 'لا يمكن إنشاء إيصال تجديد بنفس تاريخ بداية الإيصال السابق ('
                . $prev['first_session'] . '). يرجى اختيار تاريخ مختلف.',
        ];
    }

    // Today's date guard
    if ($newFirstSession && $newFirstSession === date('Y-m-d')) {
        return [
            'ok'         => false,
            'is_new'     => false,
            'block_type' => 'today_date',
            'message'    => 'لا يمكن إنشاء إيصال تجديد بتاريخ اليوم. يرجى اختيار تاريخ مستقبلي.',
        ];
    }

    // Completed → renewal is fine
    if ($status === 'completed') {
        return ['ok' => true, 'is_new' => false, 'is_academy_fault' => false, 'message' => ''];
    }

    // not_completed — check payment & refund details
    $ns = $this->getReceiptNetStatus((int)$prev['id'], $planPrice);

    $paidRefundRatio = $ns['grossPaid'] > 0
        ? ($ns['totalRefunded'] / $ns['grossPaid'])
        : 0;

    // not_completed but fully paid (gross_paid >= plan_price) → allow renewal
    if ($planPrice > 0 && $ns['grossPaid'] >= $planPrice) {
        return ['ok' => true, 'is_new' => false, 'is_academy_fault' => false, 'message' => ''];
    }

    // 100% of what they paid was refunded → only admin can proceed
    if ($ns['grossPaid'] > 0 && $paidRefundRatio >= 1.0) {
        return [
            'ok'               => false,
            'is_new'           => false,
            'is_academy_fault' => true,
            'block_type'       => 'full_refund_needs_admin',
            'prev_receipt_id'  => $prev['id'],
            'message'          => sprintf(
                'الإيصال السابق (#%d) تم استرداده بالكامل. '
                . 'يمكن للمشرف (admin) فقط إنشاء إيصال في هذه الحالة.',
                $prev['id']
            ),
        ];
    }

    // 50-99% of what they paid was refunded → academy fault
    // Allow RENEWAL to proceed; block only NEW receipt (handled in store())
    if ($paidRefundRatio >= self::ACADEMY_FAULT_MIN_RATIO) {
        return [
            'ok'               => true,   // ← renewal allowed
            'is_new'           => false,
            'is_academy_fault' => true,
            'block_type'       => 'academy_fault_partial_refund',
            'prev_receipt_id'  => $prev['id'],
            'refund_pct'       => round($paidRefundRatio * 100),
            'message'          => '',     // ← store() builds its own message
        ];
    }

    // 30%+ of what they paid was refunded → allow renewal
    if ($paidRefundRatio >= self::RENEWAL_MIN_NET_RATIO) {
        return ['ok' => true, 'is_new' => false, 'is_academy_fault' => false, 'message' => ''];
    }

    // Less than 30% refunded and not completed → silently treat as new receipt
    return [
        'ok'         => false,
        'is_new'     => false,
        'block_type' => 'not_completed_no_refund',
        'message'    => '',
    ];
}




    // ════════════════════════════════════════════════════════════════════════
    // autoReceiptStatus
    // ════════════════════════════════════════════════════════════════════════

    private function autoReceiptStatus(int $receiptId, float $planPrice): string {
        $ns = $this->getReceiptNetStatus($receiptId, $planPrice);
        return ($ns['netPaid'] > 0 && $planPrice > 0 && $ns['netPaid'] >= $planPrice)
            ? 'completed'
            : 'not_completed';
    }

    // ════════════════════════════════════════════════════════════════════════
    // buildReceiptRef
    // ════════════════════════════════════════════════════════════════════════

private function buildReceiptRef(int $rawId, string $createdAt = ''): string
{
    $dt = $createdAt ? new DateTime($createdAt) : new DateTime();

    $yy = $dt->format('y');
    $mm = $dt->format('m');

    // Keep only the last 4 digits
    $seq = substr(str_pad((string) $rawId, 4, '0', STR_PAD_LEFT), -4);

    return $yy . $mm . $seq;
}

    // ════════════════════════════════════════════════════════════════════════
    // INDEX
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin', 'branch_manager', 'customer_service', 'area_manager']);

        $scope   = $this->roleScope();
        $filters = array_merge($this->resolveFilters(), $scope['forced']);
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result   = $this->receipts->search($filters, $page, self::PER_PAGE);
        $receipts = $result['data'];
        $total    = $result['total'];
        $lastPage = (int) ceil($total / self::PER_PAGE);

        $db = get_db();

        if (!empty($scope['managed_branch_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope['managed_branch_ids']), '?'));
            $stmt = $db->prepare("SELECT id, branch_name FROM branches WHERE id IN ({$placeholders}) ORDER BY branch_name");
            $stmt->execute($scope['managed_branch_ids']);
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $branches = $db->query("SELECT id, branch_name FROM branches ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
        }

        $creators = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

        $this->renderView('index', [
            'pageTitle'      => 'الإيصالات',
            'breadcrumb'     => 'لوحة التحكم · الإيصالات',
            'receipts'       => $receipts,
            'filters'        => $filters,
            'allowedFilters' => $scope['allowed_filters'],
            'page'           => $page,
            'lastPage'       => $lastPage,
            'total'          => $total,
            'perPage'        => self::PER_PAGE,
            'branches'       => $branches,
            'creators'       => $creators,
            'isAdmin'        => (auth_user()['role'] === 'admin'),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EXPORT
    // ════════════════════════════════════════════════════════════════════════


    public function export(): void {
    auth_require(['admin', 'branch_manager', 'customer_service', 'area_manager']);

    $scope    = $this->roleScope();
    $hasInput = count(array_diff(array_keys($_GET), ['page'])) > 0;
    $filters  = array_merge(
        $hasInput ? $this->parseFilters() : ($_SESSION['receipt_filters'] ?? []),
        $scope['forced']
    );

    $rows = $this->receipts->searchAll($filters);

    $statusLabels = [
        'completed'     => 'مكتمل',
        'not_completed' => 'غير مكتمل',
        'pending'       => 'معلّق',
    ];

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="receipts_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        '#', 'رقم الإيصال', 'اسم العميل', 'هاتف العميل', 'الفرع', 'الكابتن', 'الخطة',
        'أول جلسة', 'آخر جلسة', 'جلسة التجديد', 'نوع التجديد', 'الحالة',
        'وقت التمرين', 'المستوى', 'المنشئ', 'تاريخ الإنشاء',
        'إجمالي المدفوع', 'إجمالي المسترد', 'المتبقي',
        'عدد التعديلات', 'عدد المعاملات', 'مسترد؟'
    ]);

    foreach ($rows as $r) {
        $planPrice     = (float)($r['plan_price']      ?? 0);
        $grossPaid     = (float)($r['gross_paid']       ?? $r['total_paid'] ?? 0);
        $totalRefunded = (float)($r['total_refunded']   ?? 0);
        $netPaid       = $grossPaid - $totalRefunded;
        $remaining     = max(0, $planPrice - $netPaid);

        fputcsv($out, [
            $r['id'],
            $r['receipt_ref']     ?? $this->buildReceiptRef((int)$r['id'], $r['created_at'] ?? ''),
            $r['client_name']     ?? '',
    '="' . $r['phone'] . '"',
            $r['branch_name']     ?? '',
            $r['captain_name']    ?? '',
            $r['plan_name']       ?? '',
            $r['first_session']   ?? '',
            $r['last_session']    ?? '',
            $r['renewal_session'] ?? '',
            $r['renewal_type']    ?? '',
            $statusLabels[$r['receipt_status']] ?? $r['receipt_status'],
            $r['exercise_time']   ?? '',
            $r['level']           ?? '',
            $r['creator_name']    ?? '',
            $r['created_at']      ?? '',
            number_format($netPaid,       2),
            number_format($totalRefunded, 2),
            number_format($remaining,     2),
            $r['audit_count']       ?? 0,
            $r['transaction_count'] ?? 0,
            !empty($r['is_refunded']) ? 'نعم' : 'لا',
        ]);
    }

    fclose($out);
    log_action('exported_receipts', 'filters: ' . json_encode($filters), auth_user()['id']);
    exit;
}


    // ════════════════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════════════════

    public function create(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $this->renderView('create', array_merge($this->formDropdowns(), [
            'pageTitle'  => 'إيصال جديد',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · إيصال جديد',
            'receipt'    => [],
            'errors'     => [],
            'isEdit'     => false,
            'isAdmin'    => (auth_user()['role'] === 'admin'),
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════
    // FIND OR CREATE CLIENT
    // ════════════════════════════════════════════════════════════════════════

    private function findOrCreateClient(string $name, string $phone, array $extra = []): int {
        $db = get_db();

        [$sql, $params] = PhoneHelper::buildSearchCondition($phone);
        $stmt = $db->prepare("SELECT id FROM clients WHERE {$sql} LIMIT 1");
        $stmt->execute($params);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $updates = [];
            $params2  = [];

            if (!empty($extra['email'])) {
                $updates[]        = "email = COALESCE(NULLIF(email,''), :email)";
                $params2[':email'] = $extra['email'];
            }
            if (!empty($extra['age'])) {
                $updates[]      = "age = COALESCE(age, :age)";
                $params2[':age'] = $extra['age'];
            }
            if (!empty($extra['gender'])) {
                $updates[]         = "gender = COALESCE(NULLIF(gender,''), :gender)";
                $params2[':gender'] = $extra['gender'];
            }

            if ($updates) {
                $params2[':id'] = (int) $existing;
                $db->prepare("UPDATE clients SET " . implode(', ', $updates) . " WHERE id = :id")
                   ->execute($params2);
            }

            return (int) $existing;
        }

        $stmt = $db->prepare("
            INSERT INTO clients
                (client_name, phone, email, age, gender, created_by, created_at)
            VALUES
                (:client_name, :phone, :email, :age, :gender, :created_by, CURDATE())
        ");
        $stmt->execute([
            ':client_name' => $name,
            ':phone'       => $phone,
            ':email'       => $extra['email']  ?? null,
            ':age'         => $extra['age']    ?? null,
            ':gender'      => $extra['gender'] ?? null,
            ':created_by'  => auth_user()['id'],
        ]);

        return (int) $db->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE
    // ════════════════════════════════════════════════════════════════════════



public function store(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $data               = $this->parseForm();
    $data['creator_id'] = auth_user()['id'];
    $isAdmin            = (auth_user()['role'] === 'admin');

    $user = auth_user();
    if ($user['role'] === 'branch_manager') {
        $managerBranchId = $this->receipts->getBranchIdByManager($user['id']);
        if ($managerBranchId) {
            $data['branch_id'] = $managerBranchId;
        }
    }

    $errors = $this->validate($data);

    // ── Phone-existence check ──────────────────────────────────────────
    if (empty($errors) && !empty($data['phone'])) {
        $existingClient = $this->findClientByPhone($data['phone']);
        if ($existingClient) {
            $check     = $this->checkRenewalEligibility((int)$existingClient['id']);
            $blockType = $check['block_type'] ?? '';

            if ($blockType === 'not_completed_no_refund') {
                // Silent allow — previous receipt not completed, no meaningful refund

            } elseif ($check['is_new'] ?? false) {
                // Client exists in DB but has NO receipts yet → silent allow

            } elseif ($blockType === 'full_refund_needs_admin') {
                // 100% of what they paid was refunded
                if ($isAdmin) {
                    // Admin can create a new receipt after full refund → silent allow
                } else {
                    $errors[] = $check['message'];
                }

            } elseif ($blockType === 'academy_fault_partial_refund') {
                // 50-99% academy-fault refund → block new receipt for everyone
                $errors[] = sprintf(
                    'رقم الهاتف "%s" مسجّل مسبقاً باسم "%s". '
                    . 'تم استرداد %d%% من الإيصال السابق — '
                    . 'يرجى استخدام صفحة التجديد.',
                    htmlspecialchars($data['phone']),
                    htmlspecialchars($existingClient['client_name']),
                    $check['refund_pct'] ?? 0
                );
                }
            // } else {
            //     // Client has a completed receipt or ≥30% refund → must renew
            //     $errors[] = sprintf(
            //         'رقم الهاتف "%s" مسجّل مسبقاً باسم "%s". '
            //         . 'يرجى استخدام صفحة التجديد لإنشاء إيصال جديد.',
            //         htmlspecialchars($data['phone']),
            //         htmlspecialchars($existingClient['client_name'])
            //     );
            // }
        }
    }

    if ($errors) {
        $this->flash('flash_error', implode('<br>', $errors));
        $this->renderView('create', array_merge($this->formDropdowns(), [
            'pageTitle'  => 'إيصال جديد',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · إيصال جديد',
            'receipt'    => array_merge($data, [
                'age'    => $data['client_age'],
                'gender' => $data['client_gender'],
            ]),
            'errors'     => $errors,
            'isEdit'     => false,
            'isAdmin'    => $isAdmin,
        ]));
        return;
    }

    $data['client_id'] = $this->findOrCreateClient(
        $data['client_name'],
        $data['phone'],
        [
            'email'  => $data['client_email'],
            'age'    => $data['client_age'],
            'gender' => $data['client_gender'],
        ]
    );

    $newId = $this->receipts->create($data);

    $receiptRef = $this->buildReceiptRef($newId);
    get_db()->prepare("UPDATE receipts SET receipt_ref = ? WHERE id = ?")
            ->execute([$receiptRef, $newId]);

    $evidencePath = $this->handleEvidenceUpload();

    if ((float) $data['amount'] > 0) {
        $this->transactions->create([
            'receipt_id'     => $newId,
            'payment_method' => $data['payment_method'],
            'amount'         => $data['amount'],
            'created_by'     => auth_user()['id'],
            'type'           => 'payment',
            'notes'          => 'دفعة أولى عند إنشاء الإيصال / Initial payment',
            'attachment'     => $evidencePath,
        ]);
    }

    require_once ROOT . '/app/Services/ReceiptPdfGenerator.php';
    $fullReceipt = $this->receipts->findById($newId);
    $planPrice   = (float) ($fullReceipt['plan_price'] ?? 0);

    $autoStatus = $this->autoReceiptStatus($newId, $planPrice);
    get_db()->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
            ->execute([$autoStatus, $newId]);

    $ns = $this->getReceiptNetStatus($newId, $planPrice);

    $saveDir = ROOT . '/public/uploads/receipts';
    $pdfFile = ReceiptPdfGenerator::save(
        $fullReceipt,
        $ns['netPaid'],
        $ns['remaining'],
        $data['payment_method'],
        $saveDir
    );

    get_db()->prepare("UPDATE receipts SET pdf_path = ? WHERE id = ?")
            ->execute([$pdfFile, $newId]);

    log_action('created_receipt', "id: {$newId}, ref: {$receiptRef}, client: {$data['client_name']}", auth_user()['id']);
    $this->flash('flash_success', 'تم إنشاء الإيصال بنجاح.');
    $this->redirect('/receipt/preview?id=' . $newId);
}



    // ════════════════════════════════════════════════════════════════════════
    // PREVIEW
    // ════════════════════════════════════════════════════════════════════════


public function preview(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $id      = (int) ($_GET['id'] ?? 0);
    $type    = trim($_GET['type'] ?? 'new');  // new | payment | refund | renewal
    $receipt = $this->receipts->findById($id);

    if (!$receipt) {
        $this->flash('flash_error', 'الإيصال غير موجود.');
        $this->redirect('/receipts');
        return;
    }

    if (empty($receipt['receipt_ref'])) {
        $ref = $this->buildReceiptRef($id, $receipt['created_at'] ?? '');
        get_db()->prepare("UPDATE receipts SET receipt_ref = ? WHERE id = ?")
                ->execute([$ref, $id]);
        $receipt['receipt_ref'] = $ref;
    }

    $planPrice = (float) ($receipt['plan_price'] ?? 0);
    $ns        = $this->getReceiptNetStatus($id, $planPrice);

    $db = get_db();

    // ── Fallback: pull payment method from latest transaction if not set on receipt ──
    if (empty($receipt['payment_method'])) {
        $stmt = $db->prepare("
            SELECT payment_method
            FROM transactions
            WHERE receipt_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$id]);
        $lastTx = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lastTx && !empty($lastTx['payment_method'])) {
            $receipt['payment_method'] = $lastTx['payment_method'];
        }
    }

    // ── Refund summary (only when arriving from a refund action) ──
    $refundData = null;
    if ($type === 'refund') {
        $stmt = $db->prepare("
            SELECT id, amount, payment_method, created_at
            FROM transactions
            WHERE receipt_id = ? AND type = 'refund'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$id]);
        $lastRefundTx = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastRefundTx) {
            $grossPaid      = $ns['grossPaid'];
            $totalRefunded  = $ns['totalRefunded'];
            $refundPct      = $grossPaid > 0
                ? round(($totalRefunded / $grossPaid) * 100)
                : 0;

            $refundData = [
                'tx_id'          => (int) $lastRefundTx['id'],
                'refund_amount'  => (float) $lastRefundTx['amount'],
                'gross_paid'     => $grossPaid,
                'total_refunded' => $totalRefunded,
                'remaining'      => $ns['remaining'],
                'refund_pct'     => $refundPct,
                'created_at'     => $lastRefundTx['created_at'],
            ];
        }
    }

    $this->renderView('preview', [
        'pageTitle'  => 'تفاصيل الإيصال #' . ($receipt['receipt_ref'] ?? $id),
        'breadcrumb' => 'لوحة التحكم · الإيصالات · معاينة',
        'receipt'    => $receipt,
        'type'       => $type,
        'ns'         => $ns,
        'refundData' => $refundData,
    ]);
}

    // ════════════════════════════════════════════════════════════════════════
    // SHOW
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id      = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receipts->findById($id);

        if (!$receipt) {
            $this->flash('flash_error', 'الإيصال غير موجود.');
            $this->redirect('/receipts');
            return;
        }

        $transactions = $this->transactions->findByReceipt($id);
        $auditLogs    = $this->auditLog->findByReceipt($id);
        $planPrice    = (float) ($receipt['plan_price'] ?? 0);
        $ns           = $this->getReceiptNetStatus($id, $planPrice);

        $this->renderView('show', [
            'pageTitle'    => 'عرض الإيصال #' . ($receipt['receipt_ref'] ?? $id),
            'breadcrumb'   => 'لوحة التحكم · الإيصالات · عرض',
            'receipt'      => $receipt,
            'transactions' => $transactions,
            'auditLogs'    => $auditLogs,
            'totalPaid'    => $ns['netPaid'],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDIT
    // ════════════════════════════════════════════════════════════════════════


public function edit(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $id      = (int) ($_GET['id'] ?? 0);
    $receipt = $this->receipts->findById($id);

    if (!$receipt) {
        $this->flash('flash_error', 'الإيصال غير موجود.');
        $this->redirect('/receipts');
        return;
    }

    $db = get_db();

    $clientStmt = $db->prepare("SELECT client_name, phone, email, age, gender FROM clients WHERE id = ? LIMIT 1");
    $clientStmt->execute([$receipt['client_id']]);
    $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $receipt['client_name']  = $clientRow['client_name'] ?? ($receipt['client_name'] ?? '');
    $receipt['phone']        = $clientRow['phone']       ?? ($receipt['phone'] ?? '');
    $receipt['client_email'] = $clientRow['email']        ?? '';
    $receipt['age']          = $clientRow['age']          ?? '';
    $receipt['gender']       = $clientRow['gender']       ?? '';

    $planPrice = (float) ($receipt['plan_price'] ?? 0);
    $ns        = $this->getReceiptNetStatus($id, $planPrice);

    $receipt['total_paid']     = $ns['netPaid'];
    $receipt['total_refunded'] = $ns['totalRefunded'];

    $pmStmt = $db->prepare("
        SELECT payment_method FROM transactions
        WHERE receipt_id = ? AND type = 'payment'
        ORDER BY id DESC LIMIT 1
    ");
    $pmStmt->execute([$id]);
    $lastPm = $pmStmt->fetchColumn();
    if ($lastPm && empty($receipt['payment_method'])) {
        $receipt['payment_method'] = $lastPm;
    }

    $captainStmt = $db->prepare("
        SELECT ca.id, ca.captain_name
        FROM captain_branch cb
        JOIN captains ca ON ca.id = cb.captain_id
        WHERE cb.branch_id = ? AND ca.visible = 1
        ORDER BY ca.captain_name
    ");
    $captainStmt->execute([$receipt['branch_id'] ?? 0]);
    $captains = $captainStmt->fetchAll(PDO::FETCH_ASSOC);

    $this->renderView('edit', array_merge($this->formDropdowns(), [
        'pageTitle'  => 'تعديل الإيصال',
        'breadcrumb' => 'لوحة التحكم · الإيصالات · تعديل',
        'receipt'    => $receipt,
        'captains'   => $captains,
        'errors'     => [],
        'isEdit'     => true,
        'isAdmin'    => (auth_user()['role'] === 'admin'),
    ]));
}

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE
    // ════════════════════════════════════════════════════════════════════════

public function update(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $id      = (int) ($_GET['id'] ?? 0);
    $receipt = $this->receipts->findById($id);

    if (!$receipt) {
        $this->flash('flash_error', 'الإيصال غير موجود.');
        $this->redirect('/receipts');
        return;
    }

    $data = $this->parseForm();

    $data['client_id']      = (int)    $receipt['client_id'];
    $data['creator_id']     = (int)    $receipt['creator_id'];
    $data['receipt_status'] = (string) $receipt['receipt_status'];
    $data['pdf_path']       = (string) ($receipt['pdf_path'] ?? '');
    $data['renewal_type']   = (string) ($receipt['renewal_type'] ?? 'new');

    $errors = $this->validate($data);

    if ($errors) {
        $this->flash('flash_error', implode('<br>', $errors));
        $this->renderView('edit', array_merge($this->formDropdowns(), [
            'pageTitle'  => 'تعديل الإيصال',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · تعديل',
            'receipt'    => array_merge($receipt, $data, [
                'age'    => $data['client_age'],
                'gender' => $data['client_gender'],
            ]),
            'errors'     => $errors,
            'isEdit'     => true,
            'isAdmin'    => (auth_user()['role'] === 'admin'),
        ]));
        return;
    }

    $db = get_db();

    // ── Fetch current client values BEFORE the update (needed for audit) ──
    $oldClient = [];
    if (!empty($receipt['client_id'])) {
        $clientStmt = $db->prepare(
            "SELECT client_name, phone, email, age, gender FROM clients WHERE id = ? LIMIT 1"
        );
        $clientStmt->execute([$receipt['client_id']]);
        $oldClient = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Update clients table ───────────────────────────────────────────────
    if (!empty($receipt['client_id'])) {
        $clientFields = [];
        $clientParams = [':id' => $receipt['client_id']];

        if ($data['client_name'] !== '') {
            $clientFields[]               = 'client_name = :client_name';
            $clientParams[':client_name'] = $data['client_name'];
        }
        if ($data['phone'] !== '') {
            $clientFields[]         = 'phone = :phone';
            $clientParams[':phone'] = $data['phone'];
        }
        if ($data['client_email'] !== '') {
            $clientFields[]         = 'email = :email';
            $clientParams[':email'] = $data['client_email'];
        }
        if ($data['client_age'] !== null) {
            $clientFields[]       = 'age = :age';
            $clientParams[':age'] = $data['client_age'];
        }
        if ($data['client_gender'] !== '') {
            $clientFields[]          = 'gender = :gender';
            $clientParams[':gender'] = $data['client_gender'];
        }

        if ($clientFields) {
            $db->prepare("UPDATE clients SET " . implode(', ', $clientFields) . " WHERE id = :id")
               ->execute($clientParams);
        }
    }

    // ── Audit: receipt-level fields (only columns that receipts table stores) ──
    // NOTE: payment_method and notes are NOT receipts columns — removed to
    //       prevent permanent NULL old_value noise in the log.
    $receiptAuditFields = [
        'branch_id', 'captain_id', 'plan_id', 'level',
        'first_session', 'last_session', 'renewal_session', 'renewal_type',
        'exercise_time',
    ];

    $oldReceiptAuditable = array_intersect_key($receipt, array_flip($receiptAuditFields));
    $newReceiptAuditable = array_intersect_key($data,    array_flip($receiptAuditFields));

    $this->auditLog->logChanges(
        $id,
        auth_user()['id'],
        auth_user()['role'],
        $oldReceiptAuditable,
        $newReceiptAuditable
    );

    // ── Audit: client-level fields (old values fetched from DB above) ──────
    $oldClientAuditable = [
        'client_name'   => $oldClient['client_name'] ?? null,
        'phone'         => $oldClient['phone']        ?? null,
        'client_email'  => $oldClient['email']        ?? null,
        'client_age'    => isset($oldClient['age'])   ? (string) $oldClient['age']    : null,
        'client_gender' => $oldClient['gender']       ?? null,
    ];

    $newClientAuditable = [
        'client_name'   => $data['client_name']   ?: null,
        'phone'         => $data['phone']          ?: null,
        'client_email'  => $data['client_email']   ?: null,
        'client_age'    => $data['client_age'] !== null ? (string) $data['client_age'] : null,
        'client_gender' => $data['client_gender']  ?: null,
    ];

    $this->auditLog->logChanges(
        $id,
        auth_user()['id'],
        auth_user()['role'],
        $oldClientAuditable,
        $newClientAuditable
    );

    // ── Update receipt ────────────────────────────────────────────────────
    $this->receipts->update($id, $data);

    $updatedReceipt = $this->receipts->findById($id);
    $planPrice      = (float) ($updatedReceipt['plan_price'] ?? 0);
    $autoStatus     = $this->autoReceiptStatus($id, $planPrice);
    $db->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
       ->execute([$autoStatus, $id]);

    log_action('updated_receipt', "id: {$id}", auth_user()['id']);
    $this->flash('flash_success', 'تم تحديث الإيصال بنجاح.');
    $this->redirect('/receipts');
}


    // ════════════════════════════════════════════════════════════════════════
    // DESTROY
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id      = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receipts->findById($id);

        if (!$receipt) {
            $this->flash('flash_error', 'الإيصال غير موجود.');
            $this->redirect('/receipts');
            return;
        }

        $db = get_db();
        $db->prepare("DELETE FROM transactions       WHERE receipt_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM receipt_audit_log  WHERE receipt_id = ?")->execute([$id]);
        $this->receipts->delete($id);

        log_action('deleted_receipt', "id: {$id}", auth_user()['id']);
        $this->flash('flash_success', 'تم حذف الإيصال بنجاح.');
        $this->redirect('/receipts');
    }

    // ════════════════════════════════════════════════════════════════════════
    // RENEW — GET: show form
    // ════════════════════════════════════════════════════════════════════════

    public function renew(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $isAdmin          = (auth_user()['role'] === 'admin');
        $managerBranchIds = $this->managerBranchIds();
        $client           = null;
        $search           = trim($_GET['search'] ?? '');
        $eligibilityError = '';
        $autoRenewalType  = '';
        $prevLastSession  = '';

        if ($search) {
            $client = $this->searchClientByIdOrPhone($search, $managerBranchIds);

            if ($client) {
                $check = $this->checkRenewalEligibility((int)$client['id']);

                if (!$check['ok']) {
                    $blockType = $check['block_type'] ?? '';

                    if ($blockType === 'full_refund_needs_admin') {
                        if ($isAdmin) {
                            // Admin can continue — fall through to form
                        } else {
                            $eligibilityError = $check['message'];
                            $client = null;
                        }
                    } elseif ($blockType === 'not_completed_no_refund') {
                        $eligibilityError = sprintf(
                            'لا يمكن تجديد الاشتراك لأن الإيصال السابق لهذا العميل غير مكتمل '
                            . 'ولم يتم استرداد ما يكفي منه. '
                            . 'يرجى إتمام الدفع أو الاسترداد قبل التجديد.'
                        );
                        $client = null;
                    } else {
                        $eligibilityError = $check['message'];
                        $client = null;
                    }
                }

                if ($client) {
                    $db       = get_db();
                    $prevStmt = $db->prepare("
                        SELECT last_session FROM receipts
                        WHERE client_id = ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $prevStmt->execute([$client['id']]);
                    $lastSession = (string)($prevStmt->fetchColumn() ?: '');

                    if ($lastSession) {
                        $prevLastSession = $lastSession;
                        $autoRenewalType = $this->resolveRenewalType($lastSession);
                    }
                }
            }
        }

        $phoneLocal = '';
        if (!empty($client['phone'])) {
            $raw        = $client['phone'];
            $knownCodes = ['+966', '+20'];
            $stripped   = $raw;

            foreach ($knownCodes as $code) {
                if (str_starts_with($raw, $code)) {
                    $stripped = substr($raw, strlen($code));
                    if ($code === '+20' && !str_starts_with($stripped, '0')) {
                        $stripped = '0' . $stripped;
                    }
                    break;
                }
            }
            $phoneLocal = $stripped;
        }
        // echo var_dump($client);
        // exit;

        $this->renderView('create', array_merge($this->formDropdowns(), [
            'pageTitle'       => 'تجديد اشتراك',
            'breadcrumb'      => 'لوحة التحكم · الإيصالات · تجديد',
            'receipt' => $client ? [
                'client_name'  => $client['client_name'],
                'phone'        => $client['phone'],
                'phone_local'  => $phoneLocal,
                'country_code' => '',
                'client_email' => $client['email']  ?? '',
                'age'          => $client['age']    ?? '',
                'gender'       => $client['gender'] ?? '',
                'client_id'    => $client['id'],
                'renewal_type' => $autoRenewalType,
            ] : [],
            'client'           => $client,
            'search'           => $search,
            'eligibilityError' => $eligibilityError,
            'autoRenewalType'  => $autoRenewalType,
            'prevLastSession'  => $prevLastSession,
            'errors'           => [],
            'isEdit'           => false,
            'isRenewal'        => true,
            'isAdmin'          => $isAdmin,
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE RENEWAL
    // ════════════════════════════════════════════════════════════════════════


    public function storeRenewal(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $isAdmin            = (auth_user()['role'] === 'admin');
    $user               = auth_user();
    $data               = $this->parseForm();
    $data['creator_id'] = $user['id'];

    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if (!$clientId && !empty($data['phone'])) {
        $existingClient = $this->findClientByPhone($data['phone']);
        if ($existingClient) $clientId = (int)$existingClient['id'];
    }

    // branch_manager always uses their own branch
    if ($user['role'] === 'branch_manager') {
        $managerBranchId = $this->receipts->getBranchIdByManager($user['id']);
        if ($managerBranchId) {
            $data['branch_id'] = $managerBranchId;
        }
    }

    // Server-compute the correct renewal type from previous receipt's last_session
    // — used ONLY to validate what the user submitted, never to overwrite it.
    $serverRenewalType = 'current_renewal';
    if ($clientId) {
        $db       = get_db();
        $prevStmt = $db->prepare("
            SELECT last_session FROM receipts
            WHERE client_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $prevStmt->execute([$clientId]);
        $lastSession = (string)($prevStmt->fetchColumn() ?: '');
        if ($lastSession) {
            $serverRenewalType = $this->resolveRenewalType($lastSession);
        }
    }

    $errors = $this->validate($data);

    // Validate user-chosen renewal_type against server-computed value
    $submittedRenewalType = trim($_POST['renewal_type'] ?? '');
    $validRenewalTypes    = ['new', 'current_renewal', 'previous_renewal'];

    if (empty($errors)) {
        if (!in_array($submittedRenewalType, $validRenewalTypes, true)) {
            $errors[] = 'نوع التجديد المختار غير صحيح. يرجى اختيار قيمة صحيحة.';
        } elseif ($clientId && $submittedRenewalType !== $serverRenewalType) {
            $typeLabels = [
                'new'              => 'جديد',
                'current_renewal'  => 'تجديد حالي',
                'previous_renewal' => 'تجديد سابق',
            ];
            $errors[] = sprintf(
                'نوع التجديد المختار ("%s") لا يتطابق مع النوع المحسوب تلقائياً ("%s") '
                . 'بناءً على تاريخ آخر جلسة للعميل. يرجى اختيار النوع الصحيح.',
                $typeLabels[$submittedRenewalType] ?? $submittedRenewalType,
                $typeLabels[$serverRenewalType]    ?? $serverRenewalType
            );
        }
    }

    // ── IMPORTANT: use what the user actually chose, not the server-computed
    // value. The block above already guarantees that if we reach this point
    // with no errors, $submittedRenewalType === $serverRenewalType — so this
    // is never "wrong", it just means the system validates rather than
    // silently overrides the user's selection.
    if (empty($errors)) {
        $data['renewal_type'] = $submittedRenewalType;
    }

    // Block renewal if first_session is today or in the past
    if (empty($errors) && !empty($data['first_session'])) {
        if ($data['first_session'] <= date('Y-m-d')) {
            $errors[] = 'لا يمكن إنشاء إيصال تجديد بتاريخ اليوم أو تاريخ سابق. يرجى اختيار تاريخ مستقبلي.';
        }
    }

    // Eligibility check
    if (empty($errors) && $clientId) {
        $check     = $this->checkRenewalEligibility($clientId, $data['first_session']);
        $blockType = $check['block_type'] ?? '';

        if (!$check['ok']) {
            if ($blockType === 'full_refund_needs_admin' && $isAdmin) {
                // Admin override — allow renewal after 100% refund
            } elseif ($blockType === 'not_completed_no_refund') {
                $errors[] = 'الإيصال السابق غير مكتمل ولم يُسترَد ما يكفي منه للسماح بالتجديد. '
                    . 'يرجى إتمام الدفع أو الاسترداد أولاً.';
            } else {
                $errors[] = $check['message'];
            }
        }
        // Note: academy_fault_partial_refund now returns ok=true, so it falls
        // through here without error — renewal is allowed for that case.
    }

    if ($errors) {
        $clientData      = [];
        $prevLastSession = '';

        if ($clientId) {
            $db         = get_db();
            $clientStmt = $db->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
            $clientStmt->execute([$clientId]);
            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if ($clientRow) {
                $clientData = [
                    'client_name'  => $clientRow['client_name'],
                    'phone'        => $clientRow['phone'],
                    'phone_local'  => preg_replace('/^\+?\d{1,3}0?/', '', $clientRow['phone']),
                    'country_code' => '',
                    'client_email' => $clientRow['email']  ?? '',
                    'age'          => $clientRow['age']    ?? '',
                    'gender'       => $clientRow['gender'] ?? '',
                    'client_id'    => $clientRow['id'],
                ];
            }

            $prevStmt = $db->prepare("
                SELECT last_session FROM receipts
                WHERE client_id = ? ORDER BY id DESC LIMIT 1
            ");
            $prevStmt->execute([$clientId]);
            $prevLastSession = (string)($prevStmt->fetchColumn() ?: '');
        }

        $this->flash('flash_error', implode('<br>', $errors));
        $this->renderView('create', array_merge($this->formDropdowns(), [
            'pageTitle'       => 'تجديد اشتراك',
            'breadcrumb'      => 'لوحة التحكم · الإيصالات · تجديد',
            'receipt'         => array_merge($data, $clientData, [
                'age'          => $data['client_age']    ?: ($clientData['age']    ?? ''),
                'gender'       => $data['client_gender'] ?: ($clientData['gender'] ?? ''),
                'renewal_type' => $submittedRenewalType ?: $serverRenewalType,
            ]),
            'client'          => !empty($clientData) ? $clientData : null,
            'search'          => '',
            'autoRenewalType' => $serverRenewalType,
            'prevLastSession' => $prevLastSession,
            'errors'          => $errors,
            'isEdit'          => false,
            'isRenewal'       => true,
            'isAdmin'         => $isAdmin,
        ]));
        return;
    }

    if ($clientId) {
        $db = get_db();
        $db->prepare("
            UPDATE clients SET
                email  = COALESCE(NULLIF(:email,''),  email),
                age    = COALESCE(:age,               age),
                gender = COALESCE(NULLIF(:gender,''), gender)
            WHERE id = :id
        ")->execute([
            ':email'  => $data['client_email']  ?: null,
            ':age'    => $data['client_age']    ?: null,
            ':gender' => $data['client_gender'] ?: null,
            ':id'     => $clientId,
        ]);
        $data['client_id'] = $clientId;
    } else {
        $data['client_id'] = $this->findOrCreateClient(
            $data['client_name'],
            $data['phone'],
            [
                'email'  => $data['client_email'],
                'age'    => $data['client_age'],
                'gender' => $data['client_gender'],
            ]
        );
    }

    $newId = $this->receipts->create($data);

    $receiptRef = $this->buildReceiptRef($newId);
    get_db()->prepare("UPDATE receipts SET receipt_ref = ? WHERE id = ?")
            ->execute([$receiptRef, $newId]);

    $evidencePath = $this->handleEvidenceUpload();

    if ((float) $data['amount'] > 0) {
        $this->transactions->create([
            'receipt_id'     => $newId,
            'payment_method' => $data['payment_method'],
            'amount'         => $data['amount'],
            'created_by'     => $user['id'],
            'type'           => 'payment',
            'notes'          => 'دفعة تجديد / Renewal payment',
            'attachment'     => $evidencePath,
        ]);
    }

    $fullReceipt = $this->receipts->findById($newId);
    $planPrice   = (float) ($fullReceipt['plan_price'] ?? 0);
    $autoStatus  = $this->autoReceiptStatus($newId, $planPrice);
    get_db()->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
            ->execute([$autoStatus, $newId]);

    log_action(
        'renewed_receipt',
        "id: {$newId}, ref: {$receiptRef}, client: {$data['client_name']}, type: {$data['renewal_type']}",
        $user['id']
    );
    $this->flash('flash_success', 'تم إنشاء إيصال التجديد بنجاح.');
    $this->redirect('/receipt/preview?id=' . $newId . '&type=renewal');
}


    // ════════════════════════════════════════════════════════════════════════
    // PAYMENT PAGE
    // ════════════════════════════════════════════════════════════════════════

    public function paymentPage(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $managerBranchIds = $this->managerBranchIds();
        $client   = null;
        $receipts = [];
        $search   = trim($_GET['search'] ?? '');

        if ($search) {
            $client = $this->searchClientFlexible($search, $managerBranchIds);

            if ($client) {
                $db = get_db();

                $branchFilter = '';
                $params       = [$client['id']];
                if (!empty($managerBranchIds)) {
                    $bph          = implode(',', array_fill(0, count($managerBranchIds), '?'));
                    $branchFilter = " AND r.branch_id IN ({$bph})";
                    $params       = array_merge($params, $managerBranchIds);
                }

                $stmt = $db->prepare("
                    SELECT r.*,
                           p.price       AS plan_price,
                           p.description AS plan_name,
                           b.branch_name,
                           (
                               SELECT COALESCE(SUM(CASE WHEN type='payment' THEN amount ELSE 0 END),0)
                                    - COALESCE(SUM(CASE WHEN type='refund'  THEN amount ELSE 0 END),0)
                               FROM transactions t WHERE t.receipt_id = r.id
                           ) AS total_paid
                    FROM receipts r
                    LEFT JOIN prices   p ON p.id = r.plan_id
                    LEFT JOIN branches b ON b.id = r.branch_id
                    WHERE r.client_id = ?
                      AND r.receipt_status = 'not_completed'
                      {$branchFilter}
                    ORDER BY r.id DESC
                ");
                $stmt->execute($params);
                $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $this->renderView('payment', [
            'pageTitle'  => 'إضافة دفعة',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · إضافة دفعة',
            'client'     => $client,
            'receipts'   => $receipts,
            'search'     => $search,
            'errors'     => [],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // PDF
    // ════════════════════════════════════════════════════════════════════════

    public function pdf(): void {
        $id      = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receipts->findById($id);

        if (!$receipt) {
            $this->flash('flash_error', 'الإيصال غير موجود.');
            $this->redirect('/receipts');
            return;
        }

        $planPrice = (float) ($receipt['plan_price'] ?? 0);
        $ns        = $this->getReceiptNetStatus($id, $planPrice);

        $db     = get_db();
        $pmStmt = $db->prepare("
            SELECT payment_method FROM transactions
            WHERE receipt_id = ? AND type = 'payment'
            ORDER BY id DESC LIMIT 1
        ");
        $pmStmt->execute([$id]);
        $paymentMethod = $pmStmt->fetchColumn() ?: '';

        $lang = (trim($_GET['lang'] ?? '') === 'en') ? 'en' : 'ar';

        require_once ROOT . '/app/Services/ReceiptPdfGenerator.php';
        ReceiptPdfGenerator::generate($receipt, $ns['netPaid'], $ns['remaining'], $paymentMethod, $lang);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // REFUND PDF
    //
    // Streams a "refund receipt" PDF: same layout as the normal receipt,
    // but with an extra "amount refunded" row and the paid / remaining
    // figures reflecting the state AFTER the refund. Uses the most
    // recent refund transaction on the receipt for the refunded amount
    // and refund method (pass ?tx_id=123 to target a specific refund
    // transaction instead of the latest one).
    // ════════════════════════════════════════════════════════════════════════

public function refundPdf(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $id      = (int) ($_GET['id'] ?? 0);
    $receipt = $this->receipts->findById($id);

    if (!$receipt) {
        $this->flash('flash_error', 'الإيصال غير موجود.');
        $this->redirect('/receipts');
        return;
    }

    $db   = get_db();
    $txId = (int) ($_GET['tx_id'] ?? 0);

    if ($txId) {
        $stmt = $db->prepare("
            SELECT amount, payment_method FROM transactions
            WHERE id = ? AND receipt_id = ? AND type = 'refund'
            LIMIT 1
        ");
        $stmt->execute([$txId, $id]);
    } else {
        $stmt = $db->prepare("
            SELECT amount, payment_method FROM transactions
            WHERE receipt_id = ? AND type = 'refund'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$id]);
    }

    $lastRefund = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lastRefund) {
        $this->flash('flash_error', 'لا يوجد استرداد مسجّل على هذا الإيصال.');
        $this->redirect('/receipt/preview?id=' . $id);
        return;
    }

    $planPrice    = (float) ($receipt['plan_price'] ?? 0);
    $ns           = $this->getReceiptNetStatus($id, $planPrice);
    $refundAmount = (float) $lastRefund['amount'];
    $refundMethod = (string) $lastRefund['payment_method'];
    $lang         = (trim($_GET['lang'] ?? '') === 'en') ? 'en' : 'ar';

    require_once ROOT . '/app/Services/ReceiptPdfGenerator.php';
    ReceiptPdfGenerator::generateRefund(
        $receipt,
        $ns['grossPaid'],      // ← gross paid
        $ns['totalRefunded'],  // ← total refunded
        $ns['remaining'],
        $refundAmount,
        $refundMethod,
        $lang
    );
    exit;
}
    // ════════════════════════════════════════════════════════════════════════
    // STORE PAYMENT
    // ════════════════════════════════════════════════════════════════════════


public function storePayment(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $receiptId     = (int) ($_POST['receipt_id']     ?? 0);
    $amount        = (float) ($_POST['amount']        ?? 0);
    $paymentMethod = trim($_POST['payment_method']    ?? '');
    $notes         = trim($_POST['notes']             ?? '');

    $receipt = $this->receipts->findById($receiptId);
    $errors  = [];

    if (!$receipt)       $errors[] = 'الإيصال غير موجود.';
    if ($amount <= 0)    $errors[] = 'يجب إدخال مبلغ أكبر من صفر.';
    if (!$paymentMethod) $errors[] = 'يجب اختيار طريقة الدفع.';

    if ($errors) {
        $this->flash('flash_error', implode('<br>', $errors));
        $this->redirect('/receipt/payment?search=' . urlencode($_POST['search'] ?? ''));
        return;
    }

    $evidencePath = $this->handleEvidenceUpload();

    $this->transactions->create([
        'receipt_id'     => $receiptId,
        'payment_method' => $paymentMethod,
        'amount'         => $amount,
        'created_by'     => auth_user()['id'],
        'type'           => 'payment',
        'notes'          => $notes ?: 'دفعة إضافية / Additional payment',
        'attachment'     => $evidencePath,
    ]);

    $planPrice  = (float) ($receipt['plan_price'] ?? 0);
    $autoStatus = $this->autoReceiptStatus($receiptId, $planPrice);
    $db         = get_db();
    $db->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
       ->execute([$autoStatus, $receiptId]);

    $user = auth_user();
    if ($user['role'] === 'branch_manager') {
        $managerBranchId = $this->receipts->getBranchIdByManager($user['id']);
        if ($managerBranchId) {
            $db->prepare("UPDATE receipts SET branch_id = ? WHERE id = ?")
               ->execute([$managerBranchId, $receiptId]);

            $this->auditLog->logChanges(
                $receiptId,
                $user['id'],
                $user['role'],
                ['branch_id' => $receipt['branch_id']],
                ['branch_id' => $managerBranchId]
            );
        }
    }

    // Regenerate PDF to reflect the new payment totals
    require_once ROOT . '/app/Services/ReceiptPdfGenerator.php';
    $fullReceipt = $this->receipts->findById($receiptId);
    $planPrice   = (float) ($fullReceipt['plan_price'] ?? 0);
    $ns          = $this->getReceiptNetStatus($receiptId, $planPrice);
    $saveDir     = ROOT . '/public/uploads/receipts';

    $pdfFile = ReceiptPdfGenerator::save(
        $fullReceipt,
        $ns['netPaid'],
        $ns['remaining'],
        $paymentMethod,
        $saveDir
    );

    $db->prepare("UPDATE receipts SET pdf_path = ? WHERE id = ?")
       ->execute([$pdfFile, $receiptId]);

    log_action('added_payment', "receipt_id: {$receiptId}, amount: {$amount}", auth_user()['id']);
    $this->flash('flash_success', 'تم تسجيل الدفعة بنجاح.');
    $this->redirect('/receipt/preview?id=' . $receiptId . '&type=payment');
}


    // ════════════════════════════════════════════════════════════════════════
    // REFUND PAGE
    // ════════════════════════════════════════════════════════════════════════

    public function refundPage(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $managerBranchIds = $this->managerBranchIds();
        $client   = null;
        $receipts = [];
        $search   = trim($_GET['search'] ?? '');

        if ($search) {
            $client = $this->searchClientFlexible($search, $managerBranchIds);

            if ($client) {
                $receipts = $this->receipts->findByClientWithTotals($client['id']);

                if (!empty($managerBranchIds)) {
                    $receipts = array_values(array_filter($receipts, function (array $r) use ($managerBranchIds): bool {
                        return in_array((int)($r['branch_id'] ?? 0), $managerBranchIds, true);
                    }));
                }
            }
        }

        $this->renderView('refund', [
            'pageTitle'  => 'استرداد مبلغ',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · استرداد',
            'client'     => $client,
            'receipts'   => $receipts,
            'search'     => $search,
            'errors'     => [],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // STORE REFUND
    // ════════════════════════════════════════════════════════════════════════


        public function storeRefund(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $receiptId     = (int) ($_POST['receipt_id']     ?? 0);
        $amount        = (float) ($_POST['amount']        ?? 0);
        $paymentMethod = trim($_POST['payment_method']    ?? '');
        $notes         = trim($_POST['notes']             ?? '');

        $receipt = $this->receipts->findById($receiptId);
        $errors  = [];

        if (!$receipt)       $errors[] = 'الإيصال غير موجود.';
        if ($amount <= 0)    $errors[] = 'يجب إدخال مبلغ أكبر من صفر.';
        if (!$paymentMethod) $errors[] = 'يجب اختيار طريقة الدفع.';

        // Verify there is enough gross payment to cover the refund
        if (!$errors && $receipt) {
            $planPrice = (float) ($receipt['plan_price'] ?? 0);
            $ns        = $this->getReceiptNetStatus($receiptId, $planPrice);
            $maxRefund = $ns['grossPaid'] - $ns['totalRefunded'];
            if ($amount > $maxRefund) {
                $errors[] = sprintf(
                    'مبلغ الاسترداد المطلوب (%.2f) يتجاوز الحد الأقصى المتاح للاسترداد (%.2f).',
                    $amount,
                    $maxRefund
                );
            }
        }

        if ($errors) {
            $this->flash('flash_error', implode('<br>', $errors));
            $this->redirect('/receipt/refund?search=' . urlencode($_POST['search'] ?? ''));
            return;
        }

        $evidencePath = $this->handleEvidenceUpload();

        $this->transactions->create([
            'receipt_id'     => $receiptId,
            'payment_method' => $paymentMethod,
            'amount'         => $amount,
            'created_by'     => auth_user()['id'],
            'type'           => 'refund',
            'notes'          => $notes ?: 'استرداد مبلغ / Refund',
            'attachment'     => $evidencePath,
        ]);

        $planPrice  = (float) ($receipt['plan_price'] ?? 0);
        $autoStatus = $this->autoReceiptStatus($receiptId, $planPrice);
        get_db()->prepare("UPDATE receipts SET receipt_status = ?, is_refunded = 1 WHERE id = ?")
                ->execute([$autoStatus, $receiptId]);

        // ── Auto-generate & save a copy of the refund receipt PDF ──────────
        // (Mirrors what store() does for the original receipt PDF. This is
        // a best-effort save — if it fails for any reason we don't want to
        // block the refund flow, so wrap in try/catch.)
        try {
    require_once ROOT . '/app/Services/ReceiptPdfGenerator.php';

    $updatedReceipt = $this->receipts->findById($receiptId);
    $planPriceAfter = (float) ($updatedReceipt['plan_price'] ?? 0);
    $nsAfter        = $this->getReceiptNetStatus($receiptId, $planPriceAfter);

    ReceiptPdfGenerator::saveRefund(
        $updatedReceipt,
        $nsAfter['grossPaid'],      // ← total gross paid (what client paid in total)
        $nsAfter['totalRefunded'],  // ← total refunded
        $nsAfter['remaining'],
        $amount,
        $paymentMethod,
        ROOT . '/public/uploads/receipts'
    );
} catch (\Throwable $e) {
    error_log('[ReceiptPdfGenerator::saveRefund] ' . $e->getMessage());
}
        log_action('refunded', "receipt_id: {$receiptId}, amount: {$amount}", auth_user()['id']);
        $this->flash('flash_success', 'تم تسجيل الاسترداد بنجاح.');
        $this->redirect('/receipt/preview?id=' . $receiptId . '&type=refund');
    }


    // ════════════════════════════════════════════════════════════════════════
    // SEND EMAIL
    // ════════════════════════════════════════════════════════════════════════

    public function sendEmail(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        header('Content-Type: application/json; charset=utf-8');

        $receiptId = (int) ($_POST['receipt_id'] ?? 0);
        $type      = trim($_POST['type'] ?? 'new');
        $receipt   = $this->receipts->findById($receiptId);

        if (!$receipt) {
            echo json_encode(['success' => false, 'message' => 'الإيصال غير موجود.']);
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $db        = get_db();
            $emailStmt = $db->prepare("SELECT email FROM clients WHERE id = ? LIMIT 1");
            $emailStmt->execute([$receipt['client_id']]);
            $email = (string) ($emailStmt->fetchColumn() ?: '');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'لا يوجد بريد إلكتروني مسجّل لهذا العميل أو البريد غير صحيح.',
            ]);
            exit;
        }

        $planPrice = (float) ($receipt['plan_price'] ?? 0);
        $ns        = $this->getReceiptNetStatus($receiptId, $planPrice);

        require_once ROOT . '/app/Services/ReceiptMailer.php';

        try {
            ReceiptMailer::send($receipt, $ns['netPaid'], $ns['remaining'], $type, $email);
            log_action('sent_receipt_email', "receipt_id: {$receiptId}, to: {$email}", auth_user()['id']);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('[ReceiptMailer] ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }



    // ════════════════════════════════════════════════════════════════════════
// PAYMENT BY RECEIPT ID  (customer_service + all higher roles)
// Search any receipt by its ID, add a payment, reassign creator to self
// ════════════════════════════════════════════════════════════════════════

public function paymentByReceiptId(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $receipt  = null;
    $search   = trim($_GET['search'] ?? '');
    $errors   = [];

    if ($search !== '') {
        // Accept both the raw numeric ID and the formatted receipt_ref (e.g. 260500042)
        if (ctype_digit($search)) {
            $db   = get_db();
            $stmt = $db->prepare("
                SELECT r.*,
                       c.client_name,
                       c.phone         AS phone_number,
                       b.branch_name,
                       ca.captain_name,
                       p.description   AS plan_name,
                       p.price         AS plan_price,
                       u.username      AS creator_name
                FROM receipts r
                LEFT JOIN clients  c  ON c.id  = r.client_id
                LEFT JOIN branches b  ON b.id  = r.branch_id
                LEFT JOIN captains ca ON ca.id = r.captain_id
                LEFT JOIN prices   p  ON p.id  = r.plan_id
                LEFT JOIN users    u  ON u.id  = r.creator_id
                WHERE r.id = :id OR r.receipt_ref = :ref
                LIMIT 1
            ");
            $stmt->execute([':id' => (int)$search, ':ref' => $search]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$receipt) {
            $errors[] = 'لم يتم العثور على إيصال بهذا الرقم.';
        }
    }

    $planPrice    = $receipt ? (float)($receipt['plan_price'] ?? 0) : 0;
    $ns           = $receipt ? $this->getReceiptNetStatus((int)$receipt['id'], $planPrice) : null;

    $this->renderView('payment_by_id', [
        'pageTitle'  => 'دفعة على إيصال',
        'breadcrumb' => 'لوحة التحكم · الإيصالات · دفعة على إيصال',
        'receipt'    => $receipt,
        'ns'         => $ns,
        'search'     => $search,
        'errors'     => $errors,
        'isAdmin'    => (auth_user()['role'] === 'admin'),
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// STORE PAYMENT BY RECEIPT ID
// ════════════════════════════════════════════════════════════════════════

public function storePaymentByReceiptId(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $receiptId     = (int)   ($_POST['receipt_id']    ?? 0);
    $amount        = (float) ($_POST['amount']         ?? 0);
    $paymentMethod = trim(   $_POST['payment_method']  ?? '');
    $notes         = trim(   $_POST['notes']           ?? '');
    $user          = auth_user();

    $receipt = $this->receipts->findById($receiptId);
    $errors  = [];

    if (!$receipt)       $errors[] = 'الإيصال غير موجود.';
    if ($amount <= 0)    $errors[] = 'يجب إدخال مبلغ أكبر من صفر.';
    if (!$paymentMethod) $errors[] = 'يجب اختيار طريقة الدفع.';

    // Cap at remaining balance
    if (!$errors && $receipt) {
        $planPrice  = (float)($receipt['plan_price'] ?? 0);
        $ns         = $this->getReceiptNetStatus($receiptId, $planPrice);
        $maxPayment = max(0, $planPrice - $ns['netPaid']);

        if ($planPrice > 0 && $amount > $maxPayment) {
            $errors[] = sprintf(
                'المبلغ المدخل (%.2f) يتجاوز المبلغ المتبقي على الإيصال (%.2f).',
                $amount,
                $maxPayment
            );
        }
    }

    if ($errors) {
        $this->flash('flash_error', implode('<br>', $errors));
        $this->redirect('/receipt/payment-by-id?search=' . urlencode((string)$receiptId));
        return;
    }

    $evidencePath = $this->handleEvidenceUpload();

    // Record the transaction — created_by = current user (customer_service)
    $this->transactions->create([
        'receipt_id'     => $receiptId,
        'payment_method' => $paymentMethod,
        'amount'         => $amount,
        'created_by'     => $user['id'],
        'type'           => 'payment',
        'notes'          => $notes ?: 'دفعة مضافة بواسطة خدمة العملاء / Payment added by customer service',
        'attachment'     => $evidencePath,
    ]);

    // Reassign receipt creator to the acting user and log the change
    $oldCreatorId = (int)($receipt['creator_id'] ?? 0);

    if ($oldCreatorId !== $user['id']) {
        get_db()->prepare("UPDATE receipts SET creator_id = ? WHERE id = ?")
                ->execute([$user['id'], $receiptId]);

        $this->auditLog->logChanges(
            $receiptId,
            $user['id'],
            $user['role'],
            ['creator_id' => $oldCreatorId],
            ['creator_id' => $user['id']]
        );
    }

    // Auto-update receipt status
    $planPrice  = (float)($receipt['plan_price'] ?? 0);
    $autoStatus = $this->autoReceiptStatus($receiptId, $planPrice);
    get_db()->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
            ->execute([$autoStatus, $receiptId]);

    log_action(
        'payment_by_receipt_id',
        sprintf(
            'receipt_id: %d, ref: %s, amount: %.2f, method: %s, previous_creator_id: %d, new_creator_id: %d',
            $receiptId,
            $receipt['receipt_ref'] ?? '',
            $amount,
            $paymentMethod,
            $oldCreatorId,
            $user['id']
        ),
        $user['id']
    );

    $this->flash('flash_success', 'تم تسجيل الدفعة بنجاح وتحديث المسؤول عن الإيصال.');
    $this->redirect('/receipt/preview?id=' . $receiptId . '&type=payment');
}


public function manage(): void {
    auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

    $isAdmin          = (auth_user()['role'] === 'admin');
    $managerBranchIds = $this->managerBranchIds(); // [] for non-managers
    $db               = get_db();

    // ── Which tab should open on load? ──────────────────────────────────
    $tabParam  = trim($_GET['tab'] ?? '');
    $activeTab = in_array($tabParam, ['new', 'renew', 'payment', 'refund'], true)
                 ? $tabParam
                 : 'new';

    // ════════════════════════════════════════════════════════════════
    // RENEW TAB — client search
    // ════════════════════════════════════════════════════════════════
    $renewSearch      = trim($_GET['renew_search'] ?? '');
    $renewClient      = null;
    $eligibilityError = '';
    $autoRenewalType  = '';
    $prevLastSession  = '';
    $prevFirstSession = '';

    if ($renewSearch) {
        $activeTab   = 'renew';
        $renewClient = $this->searchClientByIdOrPhone($renewSearch, $managerBranchIds);

        if ($renewClient) {
            $check = $this->checkRenewalEligibility((int)$renewClient['id']);

            if (!$check['ok']) {
                $blockType = $check['block_type'] ?? '';

                if ($blockType === 'full_refund_needs_admin') {
                    if (!$isAdmin) {
                        $eligibilityError = $check['message'];
                        $renewClient = null;
                    }
                } elseif ($blockType === 'not_completed_no_refund') {
                    $eligibilityError =
                        'لا يمكن تجديد الاشتراك لأن الإيصال السابق لهذا العميل غير مكتمل '
                        . 'ولم يتم استرداد ما يكفي منه. '
                        . 'يرجى إتمام الدفع أو الاسترداد قبل التجديد.';
                    $renewClient = null;
                } else {
                    $eligibilityError = $check['message'];
                    $renewClient = null;
                }
            }

            if ($renewClient) {
                $prevStmt = $db->prepare("
                    SELECT last_session, first_session FROM receipts
                    WHERE client_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $prevStmt->execute([$renewClient['id']]);
                $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);

                if ($prevRow) {
                    $prevLastSession  = (string)($prevRow['last_session']  ?: '');
                    $prevFirstSession = (string)($prevRow['first_session'] ?: '');
                    if ($prevLastSession) {
                        $autoRenewalType = $this->resolveRenewalType($prevLastSession);
                    }
                }

                if (!empty($renewClient['phone'])) {
                    $raw        = $renewClient['phone'];
                    $knownCodes = ['+966', '+20'];
                    $stripped   = $raw;
                    foreach ($knownCodes as $code) {
                        if (str_starts_with($raw, $code)) {
                            $stripped = substr($raw, strlen($code));
                            break;
                        }
                    }
                    $renewClient['phone_local']  = $stripped;
                    $renewClient['country_code'] = '';
                }
            }
        }
    }

    // ════════════════════════════════════════════════════════════════
    // PAYMENT TAB — client search
    // ════════════════════════════════════════════════════════════════
    $paySearch   = trim($_GET['pay_search'] ?? '');
    $payClient   = null;
    $payReceipts = [];

    if ($paySearch) {
        $activeTab = 'payment';
        $payClient = $this->searchClientFlexible($paySearch, $managerBranchIds);

        if ($payClient) {
            $payBranchFilter = '';
            $payParams       = [$payClient['id']];
            if (!empty($managerBranchIds)) {
                $bph              = implode(',', array_fill(0, count($managerBranchIds), '?'));
                $payBranchFilter  = " AND r.branch_id IN ({$bph})";
                $payParams        = array_merge($payParams, $managerBranchIds);
            }

$stmt = $db->prepare("
    SELECT r.*,
           p.price       AS plan_price,
           p.description AS plan_name,
           b.branch_name,
           COALESCE(SUM(CASE WHEN t.type='payment' THEN t.amount ELSE 0 END), 0)
               - COALESCE(SUM(CASE WHEN t.type='refund'  THEN t.amount ELSE 0 END), 0)
               AS total_paid,
           COALESCE(SUM(CASE WHEN t.type='payment' THEN t.amount ELSE 0 END), 0)
               AS gross_paid,
           COALESCE(SUM(CASE WHEN t.type='refund'  THEN t.amount ELSE 0 END), 0)
               AS total_refunded
    FROM receipts r
    LEFT JOIN prices       p ON p.id = r.plan_id
    LEFT JOIN branches     b ON b.id = r.branch_id
    LEFT JOIN transactions t ON t.receipt_id = r.id
    WHERE r.client_id = ?
      AND r.receipt_status = 'not_completed'
      {$payBranchFilter}
    GROUP BY r.id
    HAVING (
        COALESCE(SUM(CASE WHEN t.type='payment' THEN t.amount ELSE 0 END), 0)
        - COALESCE(SUM(CASE WHEN t.type='refund'  THEN t.amount ELSE 0 END), 0)
    ) < COALESCE(p.price, 0)
    ORDER BY r.id DESC
");
            $stmt->execute($payParams);
            $payReceipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // REFUND TAB — client search
    // ════════════════════════════════════════════════════════════════
    $refundSearch   = trim($_GET['refund_search'] ?? '');
    $refundClient   = null;
    $refundReceipts = [];

    if ($refundSearch) {
        $activeTab    = 'refund';
        $refundClient = $this->searchClientFlexible($refundSearch, $managerBranchIds);

        if ($refundClient) {
            $refundBranchFilter = '';
            $refundParams       = [$refundClient['id']];
            if (!empty($managerBranchIds)) {
                $bph                 = implode(',', array_fill(0, count($managerBranchIds), '?'));
                $refundBranchFilter  = " AND r.branch_id IN ({$bph})";
                $refundParams        = array_merge($refundParams, $managerBranchIds);
            }

            $stmt = $db->prepare("
                SELECT r.*,
                       p.price       AS plan_price,
                       p.description AS plan_name,
                       b.branch_name,
                       c.client_name,
                       (
                           SELECT COALESCE(SUM(amount), 0)
                           FROM transactions t
                           WHERE t.receipt_id = r.id AND t.type = 'payment'
                       ) AS gross_paid,
                       (
                           SELECT COALESCE(SUM(amount), 0)
                           FROM transactions t
                           WHERE t.receipt_id = r.id AND t.type = 'refund'
                       ) AS total_refunded
                FROM receipts r
                LEFT JOIN prices   p ON p.id = r.plan_id
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN clients  c ON c.id = r.client_id
                WHERE r.client_id = ?
                  {$refundBranchFilter}
                ORDER BY r.id DESC
            ");
            $stmt->execute($refundParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Only include receipts where there is something to refund
            $refundReceipts = array_filter($rows, function (array $r): bool {
                $grossPaid     = (float)($r['gross_paid']      ?? 0);
                $totalRefunded = (float)($r['total_refunded']  ?? 0);
                return ($grossPaid - $totalRefunded) > 0;
            });

            $refundReceipts = array_values($refundReceipts);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Render unified view
    // ════════════════════════════════════════════════════════════════
    $this->renderView('manage', array_merge($this->formDropdowns(), [
        'pageTitle'        => 'إدارة الإيصالات',
        'breadcrumb'       => 'لوحة التحكم · الإيصالات · إدارة',
        'isAdmin'          => $isAdmin,

        // new-receipt tab
        'receipt'          => [],
        'errors'           => [],
        'isEdit'           => false,
        'isRenewal'        => false,

        // renew tab
        'renewSearch'      => $renewSearch,
        'renewClient'      => $renewClient,
        'eligibilityError' => $eligibilityError,
        'autoRenewalType'  => $autoRenewalType,
        'prevLastSession'  => $prevLastSession,
        'prevFirstSession' => $prevFirstSession,

        // payment tab
        'paySearch'        => $paySearch,
        'payClient'        => $payClient,
        'payReceipts'      => $payReceipts,

        // refund tab
        'refundSearch'     => $refundSearch,
        'refundClient'     => $refundClient,
        'refundReceipts'   => $refundReceipts,

        // active tab
        'activeTab'        => $activeTab,
    ]));
}

}