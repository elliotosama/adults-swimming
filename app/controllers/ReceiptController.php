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

    private function redirectAfterReceiptUpdate(int $receiptId): void {
        $target = APP_URL . '/receipts?updated_receipt_id=' . $receiptId;

        echo '<!doctype html><html><head><meta charset="utf-8"><title>تم تحديث الإيصال</title></head><body>';
        echo '<script>';
        echo 'const target = ' . json_encode($target) . ';';
        echo 'if (window.parent && window.parent !== window) { window.parent.location.href = target; }';
        echo 'else { window.location.href = target; }';
        echo '</script>';
        echo '</body></html>';
        exit;
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
        'captain_id'      => trim($_POST['captain_id']       ?? ''),   // ← was (int) cast — broke non-numeric IDs like "c-289"
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

        // ── Admin-only edit-form override (not a receipts column — see update()) ──
        'total_paid'          => isset($_POST['total_paid'])          ? (float) $_POST['total_paid']          : null,
        'original_total_paid' => isset($_POST['original_total_paid']) ? (float) $_POST['original_total_paid'] : null,
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

    if ($data['captain_id'] === '' || $data['captain_id'] === null)
        $errors[] = 'يجب اختيار الكابتن.';

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
            'has_no_updates'       => !empty($_GET['has_no_updates']),
        ];
    }

    private function roleScope(): array {
        $user = auth_user();
        $role = $user['role'];

        $allFilterControls = [
            'search', 'first_session', 'last_session', 'created',
            'statuses', 'renewal_types', 'has_refund',
            'branch', 'creator', 'has_updates', 'has_no_updates',
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
    // findClientByEmail
    //
    // Used to block receipt creation when the submitted email already
    // belongs to a different client. Simple exact-match lookup — emails
    // are stored as entered, so no normalization is applied here.
    // ════════════════════════════════════════════════════════════════════════

    private function findClientByEmail(string $email): ?array {
        $db   = get_db();
        $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // findNewReceiptForClient
    //
    // Returns the most recent receipt created via the "new" flow
    // (renewal_type = 'new') for this client, or null if none exists.
    // Used by store() to block creating a second "new" receipt for a
    // client that already has one — this is the actual duplicate-receipt
    // bug guard (two accounts both hitting /receipt/create for the same
    // client). See also acquireCreationLock() for the race-condition fix
    // that closes the gap between this check and the INSERT.
    // ════════════════════════════════════════════════════════════════════════

    private function findNewReceiptForClient(int $clientId): ?array {
        $stmt = get_db()->prepare("
            SELECT id, receipt_ref
            FROM receipts
            WHERE client_id = ? AND renewal_type = 'new'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Concurrency guard
    //
    // Prevents two simultaneous requests (e.g. two different logged-in
    // accounts) from both passing the "does this client already have a
    // blocking / new receipt?" check before either one has actually
    // inserted its receipt. Without this, two POSTs racing each other
    // both see "no existing receipt yet" and both succeed, producing
    // duplicate receipts for the same client.
    //
    // Relies on MySQL's session-scoped named locks: the lock is
    // automatically released when the PHP request's DB connection closes
    // (i.e. at the end of this request), so as long as get_db() does NOT
    // hand out a persistent connection, no manual release is required —
    // even across the various early-return paths in store()/storeRenewal().
    // If get_db() ever switches to PDO::ATTR_PERSISTENT, RELEASE_LOCK()
    // must be called explicitly before every exit point instead.
    // ════════════════════════════════════════════════════════════════════════

    private function creationLockKey(string $identifier): string {
        $digitsOnly = preg_replace('/\D+/', '', $identifier);
        $normalized = $digitsOnly !== '' ? $digitsOnly : strtolower(trim($identifier));
        return 'receipt_create_' . md5($normalized);
    }

    private function acquireCreationLock(string $lockName, int $timeoutSeconds = 10): void {
        $db  = get_db();
        $got = $db->query('SELECT GET_LOCK(' . $db->quote($lockName) . ', ' . (int)$timeoutSeconds . ')')
                   ->fetchColumn();

        if (!$got) {
            throw new \RuntimeException(
                'يوجد طلب آخر قيد المعالجة لنفس العميل حالياً. يرجى الانتظار قليلاً ثم إعادة المحاولة.'
            );
        }
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

    private function searchClientByPhone(string $q, array $allowedBranchIds = []): ?array {
        $db     = get_db();
        $scoped = !empty($allowedBranchIds);
        $ph     = $scoped ? implode(',', array_fill(0, count($allowedBranchIds), '?')) : '';

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

    // The renewal cutoff is always the 21st of the month the last
    // session fell in — NOT the 21st of today's month. If "today" is
    // on or after that date, the grace period has expired and it's a
    // previous_renewal, whether that happens later in the same month
    // or after rolling into a following month.
    //
    // e.g. last_session = 2026-06-18 → cutoff = 2026-06-21.
    //   renewing on 2026-06-20  → current_renewal   (before cutoff)
    //   renewing on 2026-06-21  → previous_renewal  (on/after cutoff)
    //   renewing on 2026-07-06  → previous_renewal  (well past cutoff)
    $cutoff = new DateTime($lastDate->format('Y-m') . '-21');
    $today  = new DateTime();

    return ($today < $cutoff) ? 'current_renewal' : 'previous_renewal';
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
        $renewalTypeLabels = [
            'new'              => 'جديد',
            'renew'            => 'تجديد',
            'renewal'          => 'تجديد',
            'current_renewal'  => 'تجديد حالي',
            'previous_renewal' => 'تجديد سابق',
            'جديد'             => 'جديد',
            'تجديد'            => 'تجديد',
        ];
        $renewalTypeKey = mb_strtolower(trim((string) ($r['renewal_type'] ?? '')));

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
            $renewalTypeLabels[$renewalTypeKey] ?? ($r['renewal_type'] ?? ''),
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
        ':email'       => !empty($extra['email'])  ? $extra['email']  : null,
        ':age'         => $extra['age']    ?? null,
        ':gender'      => !empty($extra['gender']) ? $extra['gender'] : null,
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

    // ── Concurrency guard ────────────────────────────────────────────────
    // Serialize per phone/email so two simultaneous submissions for the
    // same client (e.g. from two different logged-in accounts) can't both
    // pass the "does this client already have a new receipt?" check below
    // before either has actually inserted its row. See acquireCreationLock()
    // for details.
    $lockIdentifier = $data['phone'] ?: $data['client_email'];
    if ($lockIdentifier !== '') {
        try {
            $this->acquireCreationLock($this->creationLockKey($lockIdentifier));
        } catch (\RuntimeException $e) {
            $this->flash('flash_error', $e->getMessage());
            $this->redirect('/receipt/create');
            return;
        }
    }

    $errors         = $this->validate($data);
    $existingClient = null;

    // ── Find the client by phone first, then by email ───────────────────
    if (empty($errors)) {
        if (!empty($data['phone'])) {
            $existingClient = $this->findClientByPhone($data['phone']);
        }
        if (!$existingClient && !empty($data['client_email'])) {
            $existingClient = $this->findClientByEmail($data['client_email']);
        }
    }

    // ── Block if this client already has a "new" receipt ─────────────────
    if (empty($errors) && $existingClient) {
        $existingNewReceipt = $this->findNewReceiptForClient((int)$existingClient['id']);

        if ($existingNewReceipt) {
            $errors[] = sprintf(
                'هذا العميل ("%s") لديه بالفعل إيصال جديد (#%s). '
                . 'لا يمكن إنشاء أكثر من إيصال "جديد" لنفس العميل — '
                . 'يرجى استخدام صفحة التجديد أو صفحة الدفعة بدلاً من ذلك.',
                htmlspecialchars($existingClient['client_name']),
                htmlspecialchars($existingNewReceipt['receipt_ref'] ?? (string)$existingNewReceipt['id'])
            );
        }
    }

    // ── Email-existence check ────────────────────────────────────────────
    // Only block if the email belongs to a DIFFERENT client than the one
    // already matched (by phone or email) above. A returning client
    // reusing their own email is fine and shouldn't be blocked here.
    if (empty($errors) && !empty($data['client_email'])) {
        $emailOwner = $this->findClientByEmail($data['client_email']);
        if ($emailOwner && (!$existingClient || (int)$emailOwner['id'] !== (int)$existingClient['id'])) {
            $errors[] = sprintf(
                'البريد الإلكتروني "%s" مسجّل مسبقاً لعميل آخر ("%s"). يرجى استخدام بريد إلكتروني مختلف.',
                htmlspecialchars($data['client_email']),
                htmlspecialchars($emailOwner['client_name'])
            );
        }
    }

    if ($errors) {
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
        'ns'           => $ns, // ← now also carries remaining + totalRefunded for the view
    ]);
}

    // ════════════════════════════════════════════════════════════════════════
    // VIEW MODAL — GET /receipt/view-modal?id=x
    //
    // Renders the bare views/receipts/_view_modal.php fragment (no layout —
    // that view never includes layout_top/bottom to begin with) for the
    // index page's single "عرض" button, which fetch()es this endpoint and
    // injects the HTML into an overlay above the table. The fragment itself
    // already contains the client/subscription/session/payment details,
    // uploaded payment-evidence thumbnails, and the edit / delete / PDF /
    // WhatsApp / email action buttons.
    // ════════════════════════════════════════════════════════════════════════

    public function viewModal(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id      = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receipts->findById($id);

        if (!$receipt) {
            http_response_code(404);
            echo '<p style="padding:2rem;color:#E06C75">الإيصال غير موجود.</p>';
            exit;
        }

        $transactions = $this->transactions->findByReceipt($id);
        $planPrice    = (float) ($receipt['plan_price'] ?? 0);
        $ns           = $this->getReceiptNetStatus($id, $planPrice);

        $db        = get_db();
        $emailStmt = $db->prepare("SELECT email FROM clients WHERE id = ? LIMIT 1");
        $emailStmt->execute([$receipt['client_id']]);
        $clientEmail = $emailStmt->fetchColumn() ?: null;

        $this->renderView('_view_modal', [
            'receipt'      => $receipt,
            'transactions' => $transactions,
            'ns'           => $ns,
            'clientEmail'  => $clientEmail,
            'isAdmin'      => (auth_user()['role'] === 'admin'),
        ]);
    }

    public function logsModal(): void {
        auth_require(['admin', 'branch_manager', 'area_manager', 'customer_service']);

        $id      = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receipts->findById($id);

        if (!$receipt) {
            http_response_code(404);
            echo '<p style="padding:2rem;color:#E06C75">الإيصال غير موجود.</p>';
            exit;
        }

        $isAdmin = auth_user()['role'] === 'admin';

        $this->renderView('_logs_modal', [
            'receipt'      => $receipt,
            'transactions' => $this->transactions->findByReceipt($id),
            'auditLogs'    => $isAdmin ? $this->auditLog->findByReceipt($id) : [],
            'isAdmin'      => $isAdmin,
        ]);
    }

    public function editModal(): void {
        $this->edit();
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
    $user = auth_user();
    $isAdmin            = ($user['role'] === 'admin');
    $isCustomerService  = ($user['role'] === 'customer_service');
    $isBranchManager    = ($user['role'] === 'branch_manager');
    $isRestrictedScheduleEditor = $isCustomerService || $isBranchManager;

    $data['client_id']      = (int)    $receipt['client_id'];
    $data['creator_id']     = (int)    $receipt['creator_id'];
    $data['receipt_status'] = (string) $receipt['receipt_status'];
    $data['pdf_path']       = (string) ($receipt['pdf_path'] ?? '');
    $data['renewal_type']   = (string) ($receipt['renewal_type'] ?? 'new');

    if ($isRestrictedScheduleEditor) {
        $data['client_name']     = '';
        $data['phone']           = '';
        $data['client_email']    = '';
        $data['client_age']      = null;
        $data['client_gender']   = '';
        $data['branch_id']       = $isBranchManager ? (int)($receipt['branch_id'] ?? 0) : $data['branch_id'];
        $data['plan_id']         = (int) ($receipt['plan_id'] ?? 0) ?: null;
        $data['level']           = $isCustomerService ? ((int) ($receipt['level'] ?? 0) ?: null) : $data['level'];
        $data['last_session']    = (string) ($receipt['last_session'] ?? '');
        $data['renewal_session'] = (string) ($receipt['renewal_session'] ?? '');
        $data['payment_method']  = $data['payment_method'] ?: 'preserved';
        $data['notes']           = '';
    }

    // Only admin can submit a total_paid override; anyone else's value is ignored.
    if (!$isAdmin) {
        $data['total_paid']          = null;
        $data['original_total_paid'] = null;
    }

    $errors = $this->validate($data);

    if ($errors) {
        $editReceipt = $isRestrictedScheduleEditor
            ? array_merge($receipt, [
                'branch_id'     => $isCustomerService ? $data['branch_id'] : ($receipt['branch_id'] ?? null),
                'captain_id'    => $data['captain_id'],
                'level'         => $isBranchManager ? $data['level'] : ($receipt['level'] ?? null),
                'first_session' => $data['first_session'],
                'exercise_time' => $data['exercise_time'],
            ])
            : array_merge($receipt, $data, [
                'age'    => $data['client_age'],
                'gender' => $data['client_gender'],
            ]);

        $this->flash('flash_error', implode('<br>', $errors));
        $this->renderView('edit', array_merge($this->formDropdowns(), [
            'pageTitle'  => 'تعديل الإيصال',
            'breadcrumb' => 'لوحة التحكم · الإيصالات · تعديل',
            'receipt'    => $editReceipt,
            'errors'     => $errors,
            'isEdit'     => true,
            'isAdmin'    => $isAdmin,
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

    $clientFieldFallbacks = [
        'client_name'   => 'client_name',
        'phone'         => 'phone',
        'client_email'  => 'email',
        'client_gender' => 'gender',
    ];

    foreach ($clientFieldFallbacks as $dataKey => $clientKey) {
        if (!array_key_exists($dataKey, $_POST) || $data[$dataKey] === '') {
            $data[$dataKey] = (string) ($oldClient[$clientKey] ?? $data[$dataKey]);
        }
    }

    if (!array_key_exists('client_age', $_POST) || $data['client_age'] === null) {
        $data['client_age'] = isset($oldClient['age']) && $oldClient['age'] !== ''
            ? (int) $oldClient['age']
            : null;
    }

    // ── Update clients table ───────────────────────────────────────────────
    if (!$isRestrictedScheduleEditor && !empty($receipt['client_id'])) {
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
    $receiptAuditFields = [
        'branch_id', 'captain_id', 'plan_id', 'level',
        'first_session', 'last_session', 'renewal_session', 'renewal_type',
        'exercise_time',
    ];

    $oldReceiptAuditable = array_intersect_key($receipt, array_flip($receiptAuditFields));
    $newReceiptAuditable = array_intersect_key($data,    array_flip($receiptAuditFields));

    $this->auditLog->logChanges(
        $id,
        $user['id'],
        $user['role'],
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

    if (!$isRestrictedScheduleEditor) {
        $this->auditLog->logChanges(
            $id,
            $user['id'],
            $user['role'],
            $oldClientAuditable,
            $newClientAuditable
        );
    }

    // ── Admin-only: reconcile total_paid override with an adjustment transaction ──
    // "total_paid" on the edit form is not a receipts column — it's a live
    // display of SUM(transactions). If admin changes it, insert a "payment"
    // (increase) or "refund" (decrease) transaction for the delta so the
    // transactions table remains the real source of truth and everything
    // downstream (getReceiptNetStatus, autoReceiptStatus, exports) stays correct.
    if ($isAdmin && $data['total_paid'] !== null) {
        $newTotalPaid      = $data['total_paid'];
        $currentPlanPrice  = (float) ($receipt['plan_price'] ?? 0);
        $currentNetStatus  = $this->getReceiptNetStatus($id, $currentPlanPrice);
        $originalTotalPaid = $currentNetStatus['netPaid'] ?? ($data['original_total_paid'] ?? 0.0);
        $diff              = round($newTotalPaid - $originalTotalPaid, 2);

        if (abs($diff) >= 0.01) {
            $this->transactions->create([
                'receipt_id'     => $id,
                'payment_method' => (string) ($data['payment_method'] ?: ($receipt['payment_method'] ?? 'bank_transfer')),
                'amount'         => abs($diff),
                'created_by'     => $user['id'],
                'type'           => $diff > 0 ? 'payment' : 'refund',
                'notes'          => 'تسوية إدارية / Admin balance adjustment ('
                    . ($diff > 0 ? '+' : '') . number_format($diff, 2) . ')',
                'attachment'     => null,
            ]);

            $this->auditLog->log(
                $id,
                $user['id'],
                $user['role'],
                'total_paid',
                $originalTotalPaid,
                $newTotalPaid
            );

            log_action(
                'admin_balance_adjustment',
                "receipt_id: {$id}, from: {$originalTotalPaid}, to: {$newTotalPaid}, diff: {$diff}",
                $user['id']
            );
        }
    }

    // ── Update receipt ────────────────────────────────────────────────────
    $this->receipts->update($id, $data);

    $updatedReceipt = $this->receipts->findById($id);
    $planPrice      = (float) ($updatedReceipt['plan_price'] ?? 0);
    $autoStatus     = $this->autoReceiptStatus($id, $planPrice);
    $db->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?")
       ->execute([$autoStatus, $id]);

    log_action('updated_receipt', "id: {$id}", auth_user()['id']);
    $_SESSION['updated_receipt_id'] = $id;
    $this->flash('flash_success', 'تم تحديث الإيصال بنجاح.');
    $this->redirectAfterReceiptUpdate($id);
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

    // ── Concurrency guard ────────────────────────────────────────────────
    // Serialize per client (or phone if the client wasn't resolved above)
    // so two simultaneous renewal submissions for the same client can't
    // both pass eligibility before either has actually inserted its row.
    $lockIdentifier = $clientId ? (string)$clientId : $data['phone'];
    if ($lockIdentifier !== '') {
        try {
            $this->acquireCreationLock($this->creationLockKey($lockIdentifier));
        } catch (\RuntimeException $e) {
            $this->flash('flash_error', $e->getMessage());
            $this->redirect('/receipt/renew');
            return;
        }
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
            $client = $this->searchClientByIdOrPhone($search, $managerBranchIds);

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
                      AND (r.is_refunded IS NULL OR r.is_refunded = 0)
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
    if ($receipt && !empty($receipt['is_refunded'])) {
        $errors[] = 'لا يمكن إضافة دفعة على إيصال تم استرداد مبلغ منه.';
    }
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
            // Direct receipt-ID / ref lookup — return only that one receipt.
            if (ctype_digit($search) && strlen($search) <= 9) {
                $branchFilter = '';
                $params       = [(int)$search, $search];
                if (!empty($managerBranchIds)) {
                    $bph          = implode(',', array_fill(0, count($managerBranchIds), '?'));
                    $branchFilter = " AND r.branch_id IN ({$bph})";
                    $params       = array_merge($params, $managerBranchIds);
                }

                $stmt = get_db()->prepare("
                    SELECT r.*,
                           p.price       AS plan_price,
                           p.description AS plan_name,
                           b.branch_name,
                           cl.client_name,
                           cl.phone,
                           (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.receipt_id = r.id AND t.type = 'payment') AS gross_paid,
                           (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.receipt_id = r.id AND t.type = 'refund')  AS total_refunded
                    FROM receipts r
                    LEFT JOIN prices   p  ON p.id  = r.plan_id
                    LEFT JOIN branches b  ON b.id  = r.branch_id
                    LEFT JOIN clients  cl ON cl.id = r.client_id
                    WHERE (r.id = ? OR r.receipt_ref = ?) {$branchFilter}
                    LIMIT 1
                ");
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $client   = ['id' => $row['client_id'], 'client_name' => $row['client_name'], 'phone' => $row['phone']];
                    $grossPaid     = (float)($row['gross_paid'] ?? 0);
                    $totalRefunded = (float)($row['total_refunded'] ?? 0);
                    $receipts = ($grossPaid - $totalRefunded) > 0 ? [$row] : [];
                }
            } else {
                // Phone-number search — client's latest receipt only, not full history.
                $client = $this->searchClientByIdOrPhone($search, $managerBranchIds);

                if ($client) {
                    $allReceipts = $this->receipts->findByClientWithTotals($client['id']);

                    if (!empty($managerBranchIds)) {
                        $allReceipts = array_values(array_filter($allReceipts, function (array $r) use ($managerBranchIds): bool {
                            return in_array((int)($r['branch_id'] ?? 0), $managerBranchIds, true);
                        }));
                    }

                    if (!empty($allReceipts)) {
                        $latest        = $allReceipts[0]; // findByClientWithTotals is expected to be ORDER BY id DESC
                        $grossPaid     = (float)($latest['gross_paid'] ?? $latest['total_paid'] ?? 0);
                        $totalRefunded = (float)($latest['total_refunded'] ?? 0);
                        $receipts      = ($grossPaid - $totalRefunded) > 0 ? [$latest] : [];
                    }
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

        if ($receipt && !empty($receipt['is_refunded'])) {
            $errors[] = 'لا يمكن إضافة دفعة على إيصال تم استرداد مبلغ منه.';
            $receipt  = null;
        }

        if (!$receipt && !$errors) {
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
    if ($receipt && !empty($receipt['is_refunded'])) {
        $errors[] = 'لا يمكن إضافة دفعة على إيصال تم استرداد مبلغ منه.';
    }
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
    // PAYMENT TAB — receipt-id/ref or phone search
    //
    // Accepts, in order of precedence:
    //   1. A receipt id or receipt_ref — looked up directly against receipts.
    //   2. Falls back to phone search only.
    // ════════════════════════════════════════════════════════════════
    $paySearch   = trim($_GET['pay_search'] ?? '');
    $payClient   = null;
    $payReceipts = [];

    if ($paySearch) {
        $activeTab = 'payment';

        // ── Direct receipt-ID / receipt-ref lookup ──────────────────────
        if (ctype_digit($paySearch)) {
            $payBranchFilter = '';
            $payParams       = [(int)$paySearch, $paySearch];
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
                       c.client_name,
                       c.phone       AS client_phone,
                       c.age         AS client_age,
                       COALESCE(SUM(CASE WHEN t.type='payment' THEN t.amount ELSE 0 END), 0)
                           - COALESCE(SUM(CASE WHEN t.type='refund'  THEN t.amount ELSE 0 END), 0)
                           AS total_paid
                FROM receipts r
                LEFT JOIN prices       p ON p.id = r.plan_id
                LEFT JOIN branches     b ON b.id = r.branch_id
                LEFT JOIN clients      c ON c.id = r.client_id
                LEFT JOIN transactions t ON t.receipt_id = r.id
                WHERE (r.id = ? OR r.receipt_ref = ?)
                  AND r.receipt_status = 'not_completed'
                  AND (r.is_refunded IS NULL OR r.is_refunded = 0)
                  {$payBranchFilter}
                GROUP BY r.id
                LIMIT 1
            ");
            $stmt->execute($payParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $payClient = [
                    'id'          => $row['client_id'],
                    'client_name' => $row['client_name'],
                    'phone'       => $row['client_phone'] ?? null,
                    'age'         => $row['client_age']   ?? null,
                ];
                $payReceipts = [$row];
            }
        }

        // ── Fallback: phone search only ─────────────────────────────────
        if (!$payClient) {
            $payClient = $this->searchClientByPhone($paySearch, $managerBranchIds);

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
                      AND (r.is_refunded IS NULL OR r.is_refunded = 0)
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
    }

    // ════════════════════════════════════════════════════════════════
    // REFUND TAB — client search (receipt-id/ref or phone; latest receipt only)
    // ════════════════════════════════════════════════════════════════
    $refundSearch   = trim($_GET['refund_search'] ?? '');
    $refundClient   = null;
    $refundReceipts = [];

    if ($refundSearch) {
        $activeTab = 'refund';

        if (ctype_digit($refundSearch) && strlen($refundSearch) <= 9) {
            // Direct receipt-ID or receipt_ref lookup
            $refundBranchFilter = '';
            $refundParams       = [(int)$refundSearch, $refundSearch];
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
                       (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.receipt_id = r.id AND t.type = 'payment') AS gross_paid,
                       (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.receipt_id = r.id AND t.type = 'refund')  AS total_refunded
                FROM receipts r
                LEFT JOIN prices   p ON p.id = r.plan_id
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN clients  c ON c.id = r.client_id
                WHERE (r.id = ? OR r.receipt_ref = ?) {$refundBranchFilter}
                LIMIT 1
            ");
            $stmt->execute($refundParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $refundClient = ['id' => $row['client_id'], 'client_name' => $row['client_name']];
                $gross    = (float)($row['gross_paid'] ?? 0);
                $refunded = (float)($row['total_refunded'] ?? 0);
                $refundReceipts = ($gross - $refunded) > 0 ? [$row] : [];
            }
        } else {
            // Phone-number search — client's latest receipt only
            $refundClient = $this->searchClientByIdOrPhone($refundSearch, $managerBranchIds);

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
                    LIMIT 1
                ");
                $stmt->execute($refundParams);
                $latest = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($latest) {
                    $gross    = (float)($latest['gross_paid'] ?? 0);
                    $refunded = (float)($latest['total_refunded'] ?? 0);
                    $refundReceipts = ($gross - $refunded) > 0 ? [$latest] : [];
                }
            }
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
