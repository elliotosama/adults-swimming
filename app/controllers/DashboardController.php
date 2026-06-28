<?php
// app/controllers/DashboardController.php

class DashboardController {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/dashboard/{$view}.php";
    }

    /**
     * Build a SQL IN() clause and bind params for an array of IDs.
     * Returns [':placeholder_sql', [':k0' => v0, ':k1' => v1, ...]]
     */
    private function inClause(string $prefix, array $ids): array {
        $keys   = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key          = ":{$prefix}_{$i}";
            $keys[]       = $key;
            $params[$key] = (int) $id;
        }
        return [implode(',', $keys), $params];
    }

    // ── Resolve branch IDs for branch_manager ────────────────────────────────

private function getBranchIdByManager(int $userId): ?int {
    $stmt = $this->db->prepare(
        "SELECT branch_id FROM user_branch WHERE user_id = ? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}
    // ── Resolve branch IDs for area_manager ──────────────────────────────────

private function getBranchIdsByArea(int $userId): array {
    $stmt = $this->db->prepare("SELECT branch_id FROM user_branch WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /dashboard  (routes to role-specific method)
    // ════════════════════════════════════════════════════════════════════════

public function index(): void {
    $user = auth_user();

    if (!$user || empty($user['id'])) {
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    $role = $user['role'];



    match ($role) {
        'branch_manager'   => $this->branchManagerDashboard($user),
        'area_manager'     => $this->areaManagerDashboard($user),
        'customer_service' => $this->customerServiceDashboard($user),
        default            => $this->adminDashboard(),
    };
}

    // ════════════════════════════════════════════════════════════════════════
    // ADMIN DASHBOARD  (unchanged logic, kept intact)
    // ════════════════════════════════════════════════════════════════════════

    private function adminDashboard(): void {
        $receiptsRow = $this->db->query("
            SELECT
                COUNT(*)                                                   AS total,
                SUM(receipt_status = 'completed')                          AS completed,
                SUM(receipt_status = 'not_completed')                      AS not_completed,
                SUM(receipt_status = 'pending')                            AS pending,
                SUM(DATE(created_at) = CURDATE())                          AS today,
                SUM(YEAR(created_at)  = YEAR(NOW())
                    AND MONTH(created_at) = MONTH(NOW()))                  AS this_month
            FROM receipts
        ")->fetch(PDO::FETCH_ASSOC);


        // ── Renewal type stats ─────────────────────────────────
$renewalTypes = $this->db->query("
    SELECT
        SUM(renewal_type = 'new')              AS new_receipts,
        SUM(renewal_type = 'current_renewal')  AS current_renewal,
        SUM(renewal_type = 'previous_renewal') AS previous_renewal
    FROM receipts
")->fetch(PDO::FETCH_ASSOC);

$receiptsRow['new']              = (int) ($renewalTypes['new_receipts'] ?? 0);
$receiptsRow['current_renewal']  = (int) ($renewalTypes['current_renewal'] ?? 0);
$receiptsRow['previous_renewal'] = (int) ($renewalTypes['previous_renewal'] ?? 0);

        $txRow = $this->db->query("
            SELECT
                COUNT(*)                                                   AS count,
                COALESCE(SUM(amount), 0)                                   AS total_amount,
                COALESCE(AVG(amount), 0)                                   AS avg_amount,
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE()
                                  THEN amount END), 0)                     AS today_amount,
                COALESCE(SUM(CASE WHEN YEAR(created_at)  = YEAR(NOW())
                                   AND MONTH(created_at) = MONTH(NOW())
                              THEN amount END), 0)                         AS month_amount
            FROM transactions
        ")->fetch(PDO::FETCH_ASSOC);

        $clientsRow = $this->db->query("
            SELECT
                COUNT(*)                                                   AS total,
                SUM(YEAR(created_at)  = YEAR(NOW())
                    AND MONTH(created_at) = MONTH(NOW()))                  AS this_month
            FROM clients
        ")->fetch(PDO::FETCH_ASSOC);

        $captainsTotal = (int) $this->db->query("SELECT COUNT(*) FROM captains")->fetchColumn();
        $usersTotal    = (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $branchesTotal = (int) $this->db->query("SELECT COUNT(*) FROM branches")->fetchColumn();
        $plansTotal    = (int) $this->db->query("SELECT COUNT(*) FROM prices")->fetchColumn();

        $branchStats = $this->db->query("
            SELECT b.branch_name,
                   COUNT(DISTINCT r.id)    AS receipt_count,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM branches b
            LEFT JOIN receipts     r ON r.branch_id  = b.id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            GROUP BY b.id, b.branch_name
            ORDER BY receipt_count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $captainStats = $this->db->query("
            SELECT ca.captain_name,
                   COUNT(r.id)                       AS receipt_count,
                   SUM(r.receipt_status = 'completed') AS completed_count
            FROM captains ca
            LEFT JOIN receipts r ON r.captain_id = ca.id
            GROUP BY ca.id, ca.captain_name
            ORDER BY receipt_count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);


        

        $topClients = $this->db->query("
            SELECT c.client_name, c.phone,
                   COUNT(DISTINCT r.id)       AS receipt_count,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM clients c
            LEFT JOIN receipts     r ON r.client_id  = c.id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            GROUP BY c.id, c.client_name, c.phone
            ORDER BY total_paid DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $userStats = $this->db->query("
            SELECT u.username,
                   COUNT(r.id)                        AS total_receipts,
                   SUM(YEAR(r.created_at)  = YEAR(NOW())
                       AND MONTH(r.created_at) = MONTH(NOW())) AS month_receipts
            FROM users u
            LEFT JOIN receipts r ON r.creator_id = u.id
            GROUP BY u.id, u.username
            ORDER BY total_receipts DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentReceipts = $this->db->query("
            SELECT r.id, c.client_name, b.branch_name, r.receipt_status
            FROM receipts r
            LEFT JOIN clients  c ON c.id = r.client_id
            LEFT JOIN branches b ON b.id = r.branch_id
            ORDER BY r.created_at DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentTransactions = $this->db->query("
            SELECT t.amount, t.created_at, c.client_name
            FROM transactions t
            LEFT JOIN receipts r ON r.id = t.receipt_id
            LEFT JOIN clients  c ON c.id = r.client_id
            ORDER BY t.created_at DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentAuditLog = $this->db->query("
            SELECT al.receipt_id, al.field_name, al.old_value, al.new_value,
                   al.changed_at, u.username AS changed_by_name
            FROM receipt_audit_log al
            LEFT JOIN users u ON u.id = al.changed_by
            ORDER BY al.changed_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->renderView('admin', [
            'pageTitle'          => 'لوحة التحكم',
            'breadcrumb'         => 'لوحة التحكم',
            'stats'              => [
                'receipts'     => $receiptsRow,
                'transactions' => $txRow,
                'clients'      => $clientsRow,
                'captains'     => ['total' => $captainsTotal],
                'users'        => ['total' => $usersTotal],
                'branches'     => ['total' => $branchesTotal],
                'plans'        => ['total' => $plansTotal],
            ],
            'branchStats'        => $branchStats,
            'captainStats'       => $captainStats,
            'topClients'         => $topClients,
            'userStats'          => $userStats,
            'recentReceipts'     => $recentReceipts,
            'recentTransactions' => $recentTransactions,
            'recentAuditLog'     => $recentAuditLog,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // BRANCH MANAGER DASHBOARD
    // Scope: single branch only (branches.manager_id = user id)
    // ════════════════════════════════════════════════════════════════════════

    private function branchManagerDashboard(array $user): void {
        $branchId = $this->getBranchIdByManager($user['id']);

        // Fetch branch name regardless
        $branchName = '—';
        if ($branchId) {
            $s = $this->db->prepare("SELECT branch_name FROM branches WHERE id = ?");
            $s->execute([$branchId]);
            $branchName = $s->fetchColumn() ?: '—';
        }

        // Fallback safe ID for queries (0 matches nothing)
        $bid = $branchId ?? 0;

        // ── Receipt stats ────────────────────────────────────────────
        $receiptsRow = $this->db->prepare("
            SELECT
                COUNT(*)                                                   AS total,
                SUM(receipt_status = 'completed')                          AS completed,
                SUM(receipt_status = 'not_completed')                      AS not_completed,
                SUM(receipt_status = 'pending')                            AS pending,
                SUM(DATE(created_at) = CURDATE())                          AS today,
                SUM(YEAR(created_at)  = YEAR(NOW())
                    AND MONTH(created_at) = MONTH(NOW()))                  AS this_month
            FROM receipts
            WHERE branch_id = ?
        ");
        $receiptsRow->execute([$bid]);
        $receiptsRow = $receiptsRow->fetch(PDO::FETCH_ASSOC);

        // ── Transaction stats (only for this branch's receipts) ──────
        $txRow = $this->db->prepare("
            SELECT
                COUNT(t.id)                                                AS count,
                COALESCE(SUM(t.amount), 0)                                 AS total_amount,
                COALESCE(AVG(t.amount), 0)                                 AS avg_amount,
                COALESCE(SUM(CASE WHEN DATE(t.created_at) = CURDATE()
                                  THEN t.amount END), 0)                   AS today_amount,
                COALESCE(SUM(CASE WHEN YEAR(t.created_at)  = YEAR(NOW())
                                   AND MONTH(t.created_at) = MONTH(NOW())
                              THEN t.amount END), 0)                       AS month_amount
            FROM transactions t
            JOIN receipts r ON r.id = t.receipt_id
            WHERE r.branch_id = ?
        ");
        $txRow->execute([$bid]);
        $txRow = $txRow->fetch(PDO::FETCH_ASSOC);

        // ── Captain performance (this branch only) ───────────────────
        $captainStats = $this->db->prepare("
            SELECT ca.captain_name,
                   COUNT(r.id)                         AS receipt_count,
                   SUM(r.receipt_status = 'completed') AS completed_count
            FROM captains ca
            JOIN captain_branch cb ON cb.captain_id = ca.id AND cb.branch_id = ?
            LEFT JOIN receipts r ON r.captain_id = ca.id AND r.branch_id = ?
            GROUP BY ca.id, ca.captain_name
            ORDER BY receipt_count DESC
        ");
        $captainStats->execute([$bid, $bid]);
        $captainStats = $captainStats->fetchAll(PDO::FETCH_ASSOC);

        // ── Top clients (this branch) ────────────────────────────────
        $topClients = $this->db->prepare("
            SELECT c.client_name, c.phone,
                   COUNT(DISTINCT r.id)       AS receipt_count,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM receipts r
            JOIN clients c ON c.id = r.client_id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            WHERE r.branch_id = ?
            GROUP BY c.id, c.client_name, c.phone
            ORDER BY total_paid DESC
            LIMIT 10
        ");
        $topClients->execute([$bid]);
        $topClients = $topClients->fetchAll(PDO::FETCH_ASSOC);

        // ── Employee activity (this branch) ──────────────────────────
        $userStats = $this->db->prepare("
            SELECT u.username,
                   COUNT(r.id) AS total_receipts,
                   SUM(YEAR(r.created_at)  = YEAR(NOW())
                       AND MONTH(r.created_at) = MONTH(NOW())) AS month_receipts
            FROM users u
            JOIN receipts r ON r.creator_id = u.id AND r.branch_id = ?
            GROUP BY u.id, u.username
            ORDER BY total_receipts DESC
            LIMIT 10
        ");
        $userStats->execute([$bid]);
        $userStats = $userStats->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent receipts (this branch) ────────────────────────────
        $recentReceipts = $this->db->prepare("
            SELECT r.id, c.client_name, ca.captain_name, r.receipt_status,
                   r.first_session, r.last_session, r.created_at
            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            WHERE r.branch_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $recentReceipts->execute([$bid]);
        $recentReceipts = $recentReceipts->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent transactions (this branch) ────────────────────────
        $recentTransactions = $this->db->prepare("
            SELECT t.amount, t.created_at, t.payment_method, c.client_name
            FROM transactions t
            JOIN receipts r ON r.id = t.receipt_id AND r.branch_id = ?
            LEFT JOIN clients c ON c.id = r.client_id
            ORDER BY t.created_at DESC
            LIMIT 8
        ");
        $recentTransactions->execute([$bid]);
        $recentTransactions = $recentTransactions->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent audit log (this branch) ───────────────────────────
        $recentAuditLog = $this->db->prepare("
            SELECT al.receipt_id, al.field_name, al.old_value, al.new_value,
                   al.changed_at, u.username AS changed_by_name
            FROM receipt_audit_log al
            JOIN receipts r ON r.id = al.receipt_id AND r.branch_id = ?
            LEFT JOIN users u ON u.id = al.changed_by
            ORDER BY al.changed_at DESC
            LIMIT 10
        ");
        $recentAuditLog->execute([$bid]);
        $recentAuditLog = $recentAuditLog->fetchAll(PDO::FETCH_ASSOC);

        $this->renderView('branch_manager', [
            'pageTitle'          => 'لوحة التحكم — ' . $branchName,
            'breadcrumb'         => 'لوحة التحكم · ' . $branchName,
            'branchName'         => $branchName,
            'branchId'           => $bid,
            'stats'              => [
                'receipts'     => $receiptsRow,
                'transactions' => $txRow,
            ],
            'captainStats'       => $captainStats,
            'topClients'         => $topClients,
            'userStats'          => $userStats,
            'recentReceipts'     => $recentReceipts,
            'recentTransactions' => $recentTransactions,
            'recentAuditLog'     => $recentAuditLog,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // AREA MANAGER DASHBOARD
    // Scope: all branches where branches.user_id = this user
    // ════════════════════════════════════════════════════════════════════════

    private function areaManagerDashboard(array $user): void {
        $branchIds = $this->getBranchIdsByArea($user['id']);

        // If no branches assigned, render an empty state
        if (empty($branchIds)) {
            $this->renderView('area_manager', [
                'pageTitle'          => 'لوحة التحكم — مدير المنطقة',
                'breadcrumb'         => 'لوحة التحكم',
                'branches'           => [],
                'stats'              => ['receipts' => [], 'transactions' => []],
                'branchStats'        => [],
                'captainStats'       => [],
                'topClients'         => [],
                'userStats'          => [],
                'recentReceipts'     => [],
                'recentTransactions' => [],
                'recentAuditLog'     => [],
            ]);
            return;
        }

        [$inSql, $inParams] = $this->inClause('b', $branchIds);

        // ── Branch names list (for display) ─────────────────────────
        $stmt = $this->db->prepare(
            "SELECT id, branch_name FROM branches WHERE id IN ({$inSql}) ORDER BY branch_name"
        );
        $stmt->execute($inParams);
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Receipt stats ────────────────────────────────────────────
        $sql = "
            SELECT
                COUNT(*)                                                   AS total,
                SUM(receipt_status = 'completed')                          AS completed,
                SUM(receipt_status = 'not_completed')                      AS not_completed,
                SUM(receipt_status = 'pending')                            AS pending,
                SUM(DATE(created_at) = CURDATE())                          AS today,
                SUM(YEAR(created_at)  = YEAR(NOW())
                    AND MONTH(created_at) = MONTH(NOW()))                  AS this_month
            FROM receipts
            WHERE branch_id IN ({$inSql})
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $receiptsRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── Transaction stats ────────────────────────────────────────
        $sql = "
            SELECT
                COUNT(t.id)                                                AS count,
                COALESCE(SUM(t.amount), 0)                                 AS total_amount,
                COALESCE(AVG(t.amount), 0)                                 AS avg_amount,
                COALESCE(SUM(CASE WHEN DATE(t.created_at) = CURDATE()
                                  THEN t.amount END), 0)                   AS today_amount,
                COALESCE(SUM(CASE WHEN YEAR(t.created_at)  = YEAR(NOW())
                                   AND MONTH(t.created_at) = MONTH(NOW())
                              THEN t.amount END), 0)                       AS month_amount
            FROM transactions t
            JOIN receipts r ON r.id = t.receipt_id
            WHERE r.branch_id IN ({$inSql})
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $txRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── Per-branch breakdown ─────────────────────────────────────
        $sql = "
            SELECT b.branch_name,
                   COUNT(DISTINCT r.id)       AS receipt_count,
                   SUM(r.receipt_status = 'completed') AS completed_count,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM branches b
            LEFT JOIN receipts     r ON r.branch_id  = b.id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            WHERE b.id IN ({$inSql})
            GROUP BY b.id, b.branch_name
            ORDER BY total_paid DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $branchStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Captain performance (across managed branches) ────────────
        $sql = "
            SELECT ca.captain_name,
                   COUNT(r.id)                         AS receipt_count,
                   SUM(r.receipt_status = 'completed') AS completed_count,
                   b.branch_name
            FROM captains ca
            JOIN captain_branch cb ON cb.captain_id = ca.id
            JOIN branches b ON b.id = cb.branch_id AND b.id IN ({$inSql})
            LEFT JOIN receipts r ON r.captain_id = ca.id AND r.branch_id = b.id
            GROUP BY ca.id, ca.captain_name, b.id, b.branch_name
            ORDER BY receipt_count DESC
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $captainStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Top clients ──────────────────────────────────────────────
        $sql = "
            SELECT c.client_name, c.phone,
                   COUNT(DISTINCT r.id)       AS receipt_count,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM receipts r
            JOIN clients c ON c.id = r.client_id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            WHERE r.branch_id IN ({$inSql})
            GROUP BY c.id, c.client_name, c.phone
            ORDER BY total_paid DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Employee activity ────────────────────────────────────────
        $sql = "
            SELECT u.username,
                   COUNT(r.id) AS total_receipts,
                   SUM(YEAR(r.created_at)  = YEAR(NOW())
                       AND MONTH(r.created_at) = MONTH(NOW())) AS month_receipts
            FROM users u
            JOIN receipts r ON r.creator_id = u.id
            WHERE r.branch_id IN ({$inSql})
            GROUP BY u.id, u.username
            ORDER BY total_receipts DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent receipts ──────────────────────────────────────────
        $sql = "
            SELECT r.id, c.client_name, b.branch_name, ca.captain_name,
                   r.receipt_status, r.first_session, r.created_at
            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN branches b  ON b.id  = r.branch_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            WHERE r.branch_id IN ({$inSql})
            ORDER BY r.created_at DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $recentReceipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent transactions ──────────────────────────────────────
        $sql = "
            SELECT t.amount, t.created_at, t.payment_method,
                   c.client_name, b.branch_name
            FROM transactions t
            JOIN receipts r ON r.id = t.receipt_id
            LEFT JOIN clients  c ON c.id  = r.client_id
            LEFT JOIN branches b ON b.id  = r.branch_id
            WHERE r.branch_id IN ({$inSql})
            ORDER BY t.created_at DESC
            LIMIT 8
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Recent audit log ─────────────────────────────────────────
        $sql = "
            SELECT al.receipt_id, al.field_name, al.old_value, al.new_value,
                   al.changed_at, u.username AS changed_by_name
            FROM receipt_audit_log al
            JOIN receipts r ON r.id = al.receipt_id
            LEFT JOIN users u ON u.id = al.changed_by
            WHERE r.branch_id IN ({$inSql})
            ORDER BY al.changed_at DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($inParams);
        $recentAuditLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->renderView('area_manager', [
            'pageTitle'          => 'لوحة التحكم — مدير المنطقة',
            'breadcrumb'         => 'لوحة التحكم',
            'branches'           => $branches,
            'stats'              => [
                'receipts'     => $receiptsRow,
                'transactions' => $txRow,
            ],
            'branchStats'        => $branchStats,
            'captainStats'       => $captainStats,
            'topClients'         => $topClients,
            'userStats'          => $userStats,
            'recentReceipts'     => $recentReceipts,
            'recentTransactions' => $recentTransactions,
            'recentAuditLog'     => $recentAuditLog,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // CUSTOMER SERVICE DASHBOARD
    // Scope: receipts created OR updated by this user
    // ════════════════════════════════════════════════════════════════════════

    private function customerServiceDashboard(array $user): void {
        $uid = $user['id'];

        // ── Receipt stats: created by me ─────────────────────────────
        $receiptsRow = $this->db->prepare("
            SELECT
                COUNT(*)                                                   AS total,
                SUM(receipt_status = 'completed')                          AS completed,
                SUM(receipt_status = 'not_completed')                      AS not_completed,
                SUM(receipt_status = 'pending')                            AS pending,
                SUM(DATE(created_at) = CURDATE())                          AS today,
                SUM(YEAR(created_at)  = YEAR(NOW())
                    AND MONTH(created_at) = MONTH(NOW()))                  AS this_month
            FROM receipts
            WHERE creator_id = ?
        ");
        $receiptsRow->execute([$uid]);
        $receiptsRow = $receiptsRow->fetch(PDO::FETCH_ASSOC);

        


        // ── Receipts I updated (audit log) but didn't create ─────────
        $updatedOnlyCount = $this->db->prepare("
            SELECT COUNT(DISTINCT al.receipt_id)
            FROM receipt_audit_log al
            WHERE al.changed_by = ?
              AND al.receipt_id NOT IN (SELECT id FROM receipts WHERE creator_id = ?)
        ");
        $updatedOnlyCount->execute([$uid, $uid]);
        $updatedOnlyCount = (int) $updatedOnlyCount->fetchColumn();

        // ── Transaction stats: my receipts ───────────────────────────
        $txRow = $this->db->prepare("
            SELECT
                COUNT(t.id)                                                AS count,
                COALESCE(SUM(t.amount), 0)                                 AS total_amount,
                COALESCE(SUM(CASE WHEN DATE(t.created_at) = CURDATE()
                                  THEN t.amount END), 0)                   AS today_amount,
                COALESCE(SUM(CASE WHEN YEAR(t.created_at)  = YEAR(NOW())
                                   AND MONTH(t.created_at) = MONTH(NOW())
                              THEN t.amount END), 0)                       AS month_amount
            FROM transactions t
            JOIN receipts r ON r.id = t.receipt_id AND r.creator_id = ?
        ");
        $txRow->execute([$uid]);
        $txRow = $txRow->fetch(PDO::FETCH_ASSOC);

        // ── My created receipts (recent, full detail) ────────────────
        $myReceipts = $this->db->prepare("
            SELECT r.id, c.client_name, c.phone, b.branch_name,
                   ca.captain_name, r.receipt_status,
                   r.first_session, r.last_session, r.created_at,
                   COALESCE(SUM(t.amount), 0) AS total_paid
            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN branches b  ON b.id  = r.branch_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            WHERE r.creator_id = ?
            GROUP BY r.id, c.client_name, c.phone, b.branch_name,
                     ca.captain_name, r.receipt_status,
                     r.first_session, r.last_session, r.created_at
            ORDER BY r.created_at DESC
            LIMIT 15
        ");
        $myReceipts->execute([$uid]);
        $myReceipts = $myReceipts->fetchAll(PDO::FETCH_ASSOC);

        // ── Receipts I edited (but didn't create) ────────────────────
        $myEdits = $this->db->prepare("
            SELECT DISTINCT r.id, c.client_name, b.branch_name,
                   r.receipt_status, r.created_at,
                   MAX(al.changed_at) AS last_edit
            FROM receipt_audit_log al
            JOIN receipts r ON r.id = al.receipt_id AND r.creator_id != ?
            LEFT JOIN clients  c ON c.id = r.client_id
            LEFT JOIN branches b ON b.id = r.branch_id
            WHERE al.changed_by = ?
            GROUP BY r.id, c.client_name, b.branch_name,
                     r.receipt_status, r.created_at
            ORDER BY last_edit DESC
            LIMIT 10
        ");
        $myEdits->execute([$uid, $uid]);
        $myEdits = $myEdits->fetchAll(PDO::FETCH_ASSOC);

        // ── My audit log (my edits in detail) ────────────────────────
        $myAuditLog = $this->db->prepare("
            SELECT al.receipt_id, al.field_name, al.old_value,
                   al.new_value, al.changed_at
            FROM receipt_audit_log al
            WHERE al.changed_by = ?
            ORDER BY al.changed_at DESC
            LIMIT 15
        ");
        $myAuditLog->execute([$uid]);
        $myAuditLog = $myAuditLog->fetchAll(PDO::FETCH_ASSOC);

        // ── Status breakdown over time (last 7 days created) ─────────
        $weekActivity = $this->db->prepare("
            SELECT DATE(created_at) AS day, COUNT(*) AS cnt
            FROM receipts
            WHERE creator_id = ?
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ");
        $weekActivity->execute([$uid]);
        $weekActivity = $weekActivity->fetchAll(PDO::FETCH_ASSOC);

        $this->renderView('customer_service', [
            'pageTitle'        => 'لوحتي',
            'breadcrumb'       => 'لوحة التحكم',
            'stats'            => [
                'receipts'         => $receiptsRow,
                'transactions'     => $txRow,
                'updated_only'     => $updatedOnlyCount,
            ],
            'myReceipts'       => $myReceipts,
            'myEdits'          => $myEdits,
            'myAuditLog'       => $myAuditLog,
            'weekActivity'     => $weekActivity,
        ]);
    }
}