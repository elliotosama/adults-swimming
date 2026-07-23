<?php
// app/models/ReceiptModel.php

class ReceiptModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── All receipts (no filter) ──────────────────────────────────────────────

public function findAll(): array {
    $stmt = $this->db->query("
        SELECT r.*,
               c.client_name AS client_name,
               cr.username   AS creator_name,
               ca.captain_name AS captain_name,
               b.branch_name,
               p.description AS plan_name,

               EXISTS (
                   SELECT 1
                   FROM transactions t
                   WHERE t.receipt_id = r.id
                     AND t.type = 'refund'
                     AND t.is_admin_adjustment = 0
               ) AS has_refund

        FROM receipts r
        LEFT JOIN clients  c  ON c.id  = r.client_id
        LEFT JOIN users    cr ON cr.id = r.creator_id
        LEFT JOIN captains ca ON ca.id = r.captain_id
        LEFT JOIN branches b  ON b.id  = r.branch_id
        LEFT JOIN prices   p  ON p.id  = r.plan_id

        ORDER BY r.id DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // ── Filtered + paginated search ───────────────────────────────────────────

    public function search(array $filters = [], int $page = 1, int $perPage = 25): array {
        [$where, $params] = $this->buildWhere($filters);

        $countSql = "
            SELECT COUNT(DISTINCT r.id)
            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN users    cr ON cr.id = r.creator_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            LEFT JOIN branches b  ON b.id  = r.branch_id
            LEFT JOIN prices   p  ON p.id  = r.plan_id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            {$where}
        ";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset  = ($page - 1) * $perPage;
$dataSql = "
    SELECT r.*,
           c.client_name   AS client_name,
           c.phone         AS client_phone,
           c.age           AS client_age,
           cr.username     AS creator_name,
           ca.captain_name AS captain_name,
           b.branch_name,
           CASE
               WHEN r.level = 1 THEN b.working_days1
               WHEN r.level = 2 THEN b.working_days2
               WHEN r.level = 3 THEN b.working_days3
               ELSE COALESCE(b.working_days2, b.working_days1, b.working_days3)
           END AS exercise_days,
           p.description   AS plan_name,
           COALESCE(p.price, 0) AS plan_price,
           (SELECT COUNT(*) FROM receipt_audit_log al WHERE al.receipt_id = r.id) AS audit_count,
           (SELECT COUNT(*) FROM transactions t WHERE t.receipt_id = r.id) AS transaction_count,

        EXISTS (
    SELECT 1
    FROM transactions tr
    WHERE tr.receipt_id = r.id
      AND tr.type = 'refund'
      AND tr.is_admin_adjustment = 0
) AS has_refund,


           COALESCE(
               (SELECT SUM(CASE WHEN t2.type = 'payment' THEN t2.amount
                                WHEN t2.type = 'refund'  THEN -t2.amount
                                ELSE 0 END)
                FROM transactions t2 WHERE t2.receipt_id = r.id), 0
           ) AS total_paid
    FROM receipts r
    LEFT JOIN clients  c  ON c.id  = r.client_id
    LEFT JOIN users    cr ON cr.id = r.creator_id
    LEFT JOIN captains ca ON ca.id = r.captain_id
    LEFT JOIN branches b  ON b.id  = r.branch_id
    LEFT JOIN prices   p  ON p.id  = r.plan_id
    LEFT JOIN transactions t ON t.receipt_id = r.id
    {$where}
    GROUP BY r.id
    ORDER BY r.id DESC
    LIMIT :limit OFFSET :offset
";
        $dataStmt = $this->db->prepare($dataSql);
        foreach ($params as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data'  => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    // ── Export (no pagination) ────────────────────────────────────────────────



public function searchAll(array $filters = []): array
{
    [$where, $params] = $this->buildWhere($filters);

    $sql = "
        SELECT
            r.id,
            c.client_name,
            c.id AS client_id,
            c.phone,
            c.age,
            b.branch_name,

            CASE
                WHEN r.level = 1 THEN b.working_days1
                WHEN r.level = 2 THEN b.working_days2
                WHEN r.level = 3 THEN b.working_days3
                ELSE COALESCE(b.working_days2, b.working_days1, b.working_days3)
            END AS exercise_days,

            ca.captain_name,
            p.description AS plan_name,
            p.price AS plan_price,

            r.first_session,
            r.last_session,
            r.renewal_session,
            r.renewal_type,
            r.receipt_status,
            r.exercise_time,
            r.level,
            r.receipt_ref,
            r.is_refunded,
            cr.username AS creator_name,
            r.created_at,

            (
                SELECT COUNT(*)
                FROM receipt_audit_log al
                WHERE al.receipt_id = r.id
            ) AS audit_count,

            (
                SELECT COUNT(*)
                FROM transactions t
                WHERE t.receipt_id = r.id
            ) AS transaction_count,

            (
                SELECT COALESCE(SUM(CASE WHEN t2.type='payment' THEN t2.amount ELSE 0 END),0)
                FROM transactions t2
                WHERE t2.receipt_id = r.id
            ) AS gross_paid,

            (
                SELECT COALESCE(SUM(CASE WHEN t2.type='refund' THEN t2.amount ELSE 0 END),0)
                FROM transactions t2
                WHERE t2.receipt_id = r.id
            ) AS total_refunded,

            (
                SELECT t2.payment_method
                FROM transactions t2
                WHERE t2.receipt_id = r.id
                  AND t2.type = 'payment'
                ORDER BY t2.id DESC
                LIMIT 1
            ) AS payment_method,

            (
                SELECT t2.notes
                FROM transactions t2
                WHERE t2.receipt_id = r.id
                ORDER BY t2.id DESC
                LIMIT 1
            ) AS notes

        FROM receipts r
        LEFT JOIN clients c ON c.id = r.client_id
        LEFT JOIN users cr ON cr.id = r.creator_id
        LEFT JOIN captains ca ON ca.id = r.captain_id
        LEFT JOIN branches b ON b.id = r.branch_id
        LEFT JOIN prices p ON p.id = r.plan_id

        {$where}

        ORDER BY r.id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    // ── Single receipt ────────────────────────────────────────────────────────

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   c.client_name   AS client_name,
                   c.age           AS client_age,
                   cr.username     AS creator_name,
                   ca.captain_name AS captain_name,
                   c.phone         AS phone_number,
                   b.branch_name,
                   CASE
                       WHEN r.level = 1 THEN b.working_days1
                       WHEN r.level = 2 THEN b.working_days2
                       WHEN r.level = 3 THEN b.working_days3
                       ELSE COALESCE(b.working_days2, b.working_days1, b.working_days3)
                   END AS exercise_days,


                EXISTS (
    SELECT 1
    FROM transactions t
    WHERE t.receipt_id = r.id
      AND t.type = 'refund'
      AND t.is_admin_adjustment = 0
) AS has_refund,


                   COALESCE(p.price, 0) AS plan_price,
                   p.description   AS plan_name
            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN users    cr ON cr.id = r.creator_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            LEFT JOIN branches b  ON b.id  = r.branch_id
            LEFT JOIN prices   p  ON p.id  = r.plan_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Receipts by client (basic — no transaction totals) ────────────────────

    public function findByClient(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT r.*, p.description AS plan_name, p.price AS plan_price, b.branch_name,

       EXISTS (
           SELECT 1
           FROM transactions t
           WHERE t.receipt_id = r.id
             AND t.type = 'refund'
             AND t.is_admin_adjustment = 0
       ) AS has_refund


            FROM receipts r
            LEFT JOIN prices   p ON p.id = r.plan_id
            LEFT JOIN branches b ON b.id = r.branch_id
            WHERE r.client_id = ?
            ORDER BY r.id DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Receipts by client WITH real transaction totals ───────────────────────

    public function findByClientWithTotals(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT
                r.*,
                c.client_name,
                c.phone          AS client_phone,
                p.description    AS plan_name,
                p.price          AS plan_price,
                b.branch_name,

        EXISTS (
    SELECT 1
    FROM transactions tr
    WHERE tr.receipt_id = r.id
      AND tr.type = 'refund'
      AND tr.is_admin_adjustment = 0
) AS has_refund,


                COALESCE(
                    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0
                ) AS total_paid,
                COALESCE(
                    SUM(CASE WHEN t.type = 'refund'  THEN t.amount ELSE 0 END), 0
                ) AS total_refunded
            FROM receipts r
            LEFT JOIN clients  c ON c.id = r.client_id
            LEFT JOIN prices   p ON p.id = r.plan_id
            LEFT JOIN branches b ON b.id = r.branch_id
            LEFT JOIN transactions t ON t.receipt_id = r.id
            WHERE r.client_id = ?
            GROUP BY r.id
            ORDER BY r.id DESC
        ");
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $planPrice        = (float) ($row['plan_price']     ?? 0);
            $totalPaid        = (float) ($row['total_paid']     ?? 0);
            $totalRefunded    = (float) ($row['total_refunded'] ?? 0);
            $row['remaining'] = max(0, $planPrice - $totalPaid + $totalRefunded);
        }
        unset($row);

        return $rows;
    }

    // ── Branch IDs managed by a user (area_manager) ──────────────────────────

    public function getBranchIdsByArea(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT branch_id FROM user_branch WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Branch ID for a branch_manager (single — for locked single-branch UI) ──

    public function getBranchIdByManager(int $userId): ?int {
        $stmt = $this->db->prepare(
            "SELECT branch_id FROM user_branch WHERE user_id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ── ALL branch IDs for a branch_manager (many-to-many via user_branch) ─────
    //
    // Use this (instead of the singular version above) anywhere a manager's
    // access should be scoped across every branch they manage, not just one —
    // e.g. client search, receipt listing/filtering for the manage() hub.

    public function getBranchIdsByManager(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT branch_id FROM user_branch WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // ════════════════════════════════════════════════════════════════════════
    // effectiveNow
    //
    // Fallback business-day-aware timestamp, used only when the caller
    // (controller) didn't already supply 'created_at' / 'updated_at' in
    // $data. Mirrors ReceiptController::effectiveCreatedAt() exactly: a
    // moment between 12:00–2:59:59 AM is attributed to the previous
    // calendar day.
    // ════════════════════════════════════════════════════════════════════════
    private function effectiveNow(): string {
        $now = new DateTime();
        if ((int) $now->format('H') < 3) {
            $now->modify('-1 day');
        }
        return $now->format('Y-m-d H:i:s');
    }

    // ── Create ────────────────────────────────────────────────────────────────


public function create(array $data): int {
    $stmt = $this->db->prepare("
        INSERT INTO receipts
            (client_id, creator_id, captain_id, branch_id,
             first_session, last_session, renewal_session,
             created_at, updated_at, renewal_type, receipt_status,
             exercise_time, plan_id, level, pdf_path)
        VALUES
            (:client_id, :creator_id, :captain_id, :branch_id,
             :first_session, :last_session, :renewal_session,
             :created_at, :updated_at, :renewal_type, :receipt_status,
             :exercise_time, :plan_id, :level, :pdf_path)
    ");
    $stmt->execute($this->bind($data));
    return (int) $this->db->lastInsertId();
}


    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        // NOTE: created_at is intentionally NOT in the SET clause by default —
        // editing an existing receipt must never retroactively change which
        // business day it was originally created under. updated_at IS
        // updated, using the same business-day cutoff rule (see bind()).
        //
        // The ONE exception: if the caller (admin-only path in the
        // controller) explicitly supplies a non-empty 'created_at_override'
        // key in $data, created_at IS included in the SET clause and set to
        // that value. Every other caller simply omits that key, so this
        // behaves exactly as before for them.
        $setParts = [
            'client_id       = :client_id',
            'creator_id      = :creator_id',
            'captain_id      = :captain_id',
            'branch_id       = :branch_id',
            'first_session   = :first_session',
            'last_session    = :last_session',
            'renewal_session = :renewal_session',
            'renewal_type    = :renewal_type',
            'receipt_status  = :receipt_status',
            'exercise_time   = :exercise_time',
            'plan_id         = :plan_id',
            'level           = :level',
            'pdf_path        = :pdf_path',
            'updated_at      = :updated_at',
        ];

        $params = $this->bindForUpdate($data);

        if (!empty($data['created_at_override'])) {
            $setParts[]            = 'created_at = :created_at';
            $params[':created_at'] = $data['created_at_override'];
        }

        $sql = "UPDATE receipts SET " . implode(', ', $setParts) . " WHERE id = :id";
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    // ── Update status only ────────────────────────────────────────────────────

    public function updateStatus(int $id, string $status): void {
        $stmt = $this->db->prepare("UPDATE receipts SET receipt_status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM receipts WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    
    private function buildWhere(array $filters): array {
    $conditions = [];
    $params     = [];

    if (!empty($filters['search'])) {
        $searchTerm    = trim((string) $filters['search']);
        $searchDigits  = ctype_digit($searchTerm) ? (int) $searchTerm : null;

        $conditions[] = "(
            c.client_name  LIKE :search_name
            OR c.phone     LIKE :search_phone
            OR r.client_id = :search_client_id
            OR r.id        = :search_receipt_id
            OR r.receipt_ref LIKE :search_receipt_ref
        )";
        $params[':search_name']         = '%' . $searchTerm . '%';
        $params[':search_phone']        = '%' . $searchTerm . '%';
        $params[':search_client_id']    = $searchDigits ?? 0;
        $params[':search_receipt_id']   = $searchDigits ?? 0;
        $params[':search_receipt_ref']  = '%' . $searchTerm . '%';
    }

    if (!empty($filters['first_session_from'])) {
        $conditions[]       = "r.first_session >= :fs_from";
        $params[':fs_from'] = $filters['first_session_from'];
    }
    if (!empty($filters['first_session_to'])) {
        $conditions[]     = "r.first_session <= :fs_to";
        $params[':fs_to'] = $filters['first_session_to'];
    }

    if (!empty($filters['last_session_from'])) {
        $conditions[]       = "r.last_session >= :ls_from";
        $params[':ls_from'] = $filters['last_session_from'];
    }
    if (!empty($filters['last_session_to'])) {
        $conditions[]     = "r.last_session <= :ls_to";
        $params[':ls_to'] = $filters['last_session_to'];
    }

    $createdFrom = $this->normalizeDate($filters['created_from'] ?? '');
    $createdTo   = $this->normalizeDate($filters['created_to'] ?? '');

    if ($createdFrom && !$createdTo) {
        $createdTo = $createdFrom;
    }

    if ($createdFrom && $createdTo && $createdFrom > $createdTo) {
        [$createdFrom, $createdTo] = [$createdTo, $createdFrom];
    }

    $selectedCreatorId = !empty($filters['creator_id'])
        ? (int) $filters['creator_id']
        : null;

    // "creator_created_only" checked → strict mode: this creator's OWN
    // created receipts only. No "touched via transaction/audit" logic
    // applies anywhere, including the date-range branch below.
    $strictCreatedOnly = $selectedCreatorId !== null
        && !empty($filters['creator_created_only']);

    $creatorActivityIsIncluded = $selectedCreatorId !== null && !$strictCreatedOnly;

    if ($createdFrom || $createdTo) {
        $receiptDateParts = [];
        $transactionDateParts = [];
        $auditDateParts = [];

        if ($createdFrom) {
            $receiptDateParts[] = "r.created_at >= :cr_from_receipt";
            $transactionDateParts[] = "t_activity.created_at >= :cr_from_transaction";
            $auditDateParts[] = "al_activity.changed_at >= :cr_from_audit";
            $params[':cr_from_receipt'] = $createdFrom;
            $params[':cr_from_transaction'] = $createdFrom;
            $params[':cr_from_audit'] = $createdFrom;
        }
        if ($createdTo) {
            $createdToExclusive = (new DateTimeImmutable($createdTo))->modify('+1 day')->format('Y-m-d');
            $receiptDateParts[] = "r.created_at < :cr_to_exclusive_receipt";
            $transactionDateParts[] = "t_activity.created_at < :cr_to_exclusive_transaction";
            $auditDateParts[] = "al_activity.changed_at < :cr_to_exclusive_audit";
            $params[':cr_to_exclusive_receipt'] = $createdToExclusive;
            $params[':cr_to_exclusive_transaction'] = $createdToExclusive;
            $params[':cr_to_exclusive_audit'] = $createdToExclusive;
        }

        $receiptDateWhere = implode(' AND ', $receiptDateParts);
        $transactionDateWhere = implode(' AND ', $transactionDateParts);
        $auditDateWhere = implode(' AND ', $auditDateParts);

        if ($strictCreatedOnly) {
            $conditions[] = "({$receiptDateWhere})";
        } elseif ($creatorActivityIsIncluded) {
            $conditions[] = "(
                (r.creator_id = :creator_id_date AND {$receiptDateWhere})
                OR EXISTS (
                    SELECT 1
                    FROM transactions t_activity
                    WHERE t_activity.receipt_id = r.id
                      AND t_activity.created_by = :creator_id_tx_date
                      AND {$transactionDateWhere}
                )
                OR EXISTS (
                    SELECT 1
                    FROM receipt_audit_log al_activity
                    WHERE al_activity.receipt_id = r.id
                      AND al_activity.changed_by = :creator_id_al_date
                      AND {$auditDateWhere}
                )
            )";
            $params[':creator_id_date']    = $selectedCreatorId;
            $params[':creator_id_tx_date'] = $selectedCreatorId;
            $params[':creator_id_al_date'] = $selectedCreatorId;
        } else {
            $conditions[] = "(
                ({$receiptDateWhere})
                OR EXISTS (
                    SELECT 1
                    FROM transactions t_activity
                    WHERE t_activity.receipt_id = r.id
                      AND {$transactionDateWhere}
                )
                OR EXISTS (
                    SELECT 1
                    FROM receipt_audit_log al_activity
                    WHERE al_activity.receipt_id = r.id
                      AND {$auditDateWhere}
                )
            )";
        }
    } elseif ($creatorActivityIsIncluded) {
        $conditions[] = "(
            r.creator_id = :creator_id_only
            OR EXISTS (
                SELECT 1 FROM transactions t_activity_only
                WHERE t_activity_only.receipt_id = r.id
                  AND t_activity_only.created_by = :creator_id_tx_only
            )
            OR EXISTS (
                SELECT 1 FROM receipt_audit_log al_activity_only
                WHERE al_activity_only.receipt_id = r.id
                  AND al_activity_only.changed_by = :creator_id_al_only
            )
        )";
        $params[':creator_id_only']    = $selectedCreatorId;
        $params[':creator_id_tx_only'] = $selectedCreatorId;
        $params[':creator_id_al_only'] = $selectedCreatorId;
    }

    if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
        $placeholders = [];
        foreach ($filters['statuses'] as $i => $s) {
            $key            = ":status_{$i}";
            $placeholders[] = $key;
            $params[$key]   = $s;
        }
        $conditions[] = "r.receipt_status IN (" . implode(',', $placeholders) . ")";
    }

    if (!empty($filters['renewal_types']) && is_array($filters['renewal_types'])) {
        $placeholders = [];
        foreach ($filters['renewal_types'] as $i => $rt) {
            $key            = ":rtype_{$i}";
            $placeholders[] = $key;
            $params[$key]   = $rt;
        }
        $conditions[] = "r.renewal_type IN (" . implode(',', $placeholders) . ")";
    }

    // ── has_refund filter ────────────────────────────────────────────────
    // Admin balance-correction rows (is_admin_adjustment = 1) must NOT
    // count toward "مسترد؟" — an admin editing total_paid downward is a
    // bookkeeping fix, not a customer refund. See ReceiptController::update()
    // for where these rows get inserted with is_admin_adjustment = 1.
    if (!empty($filters['has_refund'])) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM transactions tr
            WHERE tr.receipt_id = r.id
              AND tr.type = 'refund'
              AND tr.is_admin_adjustment = 0
        )";
    }

    // ════════════════════════════════════════════════════════════════
    // has_updates / has_no_updates
    //
    // When a specific creator is selected, these are scoped to THAT
    // creator's own activity — BUT with one important rule: if the
    // creator made the touch on a receipt THEY THEMSELVES created
    // (r.creator_id = the selected creator), that touch does NOT count
    // as an "update". A creator adding a payment or fixing a detail on
    // their own receipt is still just "their created receipt", not an
    // "updated" one. Only activity by this person on a receipt someone
    // ELSE created counts as an update:
    //   - an audit log row where changed_by = this creator, on a
    //     receipt whose creator_id is someone else, OR
    //   - a transaction (beyond the receipt's first/original one)
    //     where created_by = this creator, on a receipt whose
    //     creator_id is someone else.
    // With no creator selected, falls back to the original receipt-wide
    // behavior (any audit log entry exists / total transactions >= 2).
    // ════════════════════════════════════════════════════════════════

    if (!empty($filters['has_updates'])) {
        if ($selectedCreatorId !== null) {
            $conditions[] = "(
                r.creator_id <> :has_updates_creator_owner
                AND (
                    EXISTS (
                        SELECT 1 FROM receipt_audit_log al_upd
                        WHERE al_upd.receipt_id = r.id
                          AND al_upd.changed_by = :has_updates_creator_al
                    )
                    OR EXISTS (
                        SELECT 1 FROM transactions t_upd
                        WHERE t_upd.receipt_id = r.id
                          AND t_upd.created_by = :has_updates_creator_tx
                          AND t_upd.id <> (
                              SELECT MIN(t_first.id) FROM transactions t_first
                              WHERE t_first.receipt_id = r.id
                          )
                    )
                )
            )";
            $params[':has_updates_creator_owner'] = $selectedCreatorId;
            $params[':has_updates_creator_al']    = $selectedCreatorId;
            $params[':has_updates_creator_tx']    = $selectedCreatorId;
        } else {
            $conditions[] = "
                (EXISTS (SELECT 1 FROM receipt_audit_log al WHERE al.receipt_id = r.id)
                 OR
                 (SELECT COUNT(*) FROM transactions t WHERE t.receipt_id = r.id) >= 2)
            ";
        }
    }

    if (!empty($filters['has_no_updates'])) {
        if ($selectedCreatorId !== null) {
            // Mirror of has_updates above: a receipt this creator created
            // themselves is ALWAYS "no updates" (self-touches don't count).
            // Otherwise, "no updates" means this creator never touched it
            // via audit log or an extra transaction.
            $conditions[] = "(
                r.creator_id = :has_no_updates_creator_owner
                OR (
                    NOT EXISTS (
                        SELECT 1 FROM receipt_audit_log al_noupd
                        WHERE al_noupd.receipt_id = r.id
                          AND al_noupd.changed_by = :has_no_updates_creator_al
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM transactions t_noupd
                        WHERE t_noupd.receipt_id = r.id
                          AND t_noupd.created_by = :has_no_updates_creator_tx
                          AND t_noupd.id <> (
                              SELECT MIN(t_first2.id) FROM transactions t_first2
                              WHERE t_first2.receipt_id = r.id
                          )
                    )
                )
            )";
            $params[':has_no_updates_creator_owner'] = $selectedCreatorId;
            $params[':has_no_updates_creator_al']    = $selectedCreatorId;
            $params[':has_no_updates_creator_tx']    = $selectedCreatorId;
        } else {
            $conditions[] = "
                (NOT EXISTS (SELECT 1 FROM receipt_audit_log al WHERE al.receipt_id = r.id)
                 AND
                 (SELECT COUNT(*) FROM transactions t WHERE t.receipt_id = r.id) < 2)
            ";
        }
    }

    if (!empty($filters['force_creator_id'])) {
        $conditions[]          = "r.creator_id = :creator_id";
        $params[':creator_id'] = (int) $filters['force_creator_id'];

    } elseif (!empty($filters['creator_id']) && !$creatorActivityIsIncluded) {
        $creatorId = (int) $filters['creator_id'];

        if (!empty($filters['creator_created_only'])) {
            $conditions[]          = "r.creator_id = :creator_id";
            $params[':creator_id'] = $creatorId;
        } else {
            $conditions[]             = "
                (
                    r.creator_id = :creator_id
                    OR EXISTS (
                        SELECT 1 FROM transactions t
                        WHERE t.receipt_id = r.id
                          AND t.created_by = :creator_id_tx
                    )
                    OR EXISTS (
                        SELECT 1 FROM receipt_audit_log al
                        WHERE al.receipt_id = r.id
                          AND al.changed_by = :creator_id_al
                    )
                )
            ";
            $params[':creator_id']    = $creatorId;
            $params[':creator_id_tx'] = $creatorId;
            $params[':creator_id_al'] = $creatorId;
        }
    }

$effectiveBranchIds = null;
if (!empty($filters['force_branch_ids']) && is_array($filters['force_branch_ids'])) {
    $effectiveBranchIds = array_map('intval', $filters['force_branch_ids']);

    // If the user (area_manager) selected specific branches within their
    // managed set, narrow down to that selection — don't just ignore it.
    if (!empty($filters['branch_ids']) && is_array($filters['branch_ids'])) {
        $requested   = array_map('intval', $filters['branch_ids']);
        $intersected = array_values(array_intersect($effectiveBranchIds, $requested));
        if ($intersected) {
            $effectiveBranchIds = $intersected;
        }
    }
} elseif (!empty($filters['branch_ids']) && is_array($filters['branch_ids'])) {
    $effectiveBranchIds = array_map('intval', $filters['branch_ids']);
}

    if ($effectiveBranchIds !== null && count($effectiveBranchIds) > 0) {
        $placeholders = [];
        foreach ($effectiveBranchIds as $i => $bid) {
            $key            = ":branch_{$i}";
            $placeholders[] = $key;
            $params[$key]   = $bid;
        }
        $conditions[] = "r.branch_id IN (" . implode(',', $placeholders) . ")";
    }

    $sql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    return [$sql, $params];
}
    
    private function normalizeDate(?string $value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function bind(array $data): array {
        // created_at: prefer whatever the controller computed via its own
        // effectiveCreatedAt(); fall back to this model's own business-day
        // logic if the caller didn't provide one.
        $createdAt = $data['created_at'] ?: $this->effectiveNow();

        // updated_at: prefer an explicit value from the caller; otherwise
        // fall back to created_at on insert, or to effectiveNow() on update.
        $updatedAt = $data['updated_at'] ?: $createdAt;

        return [
            ':client_id'       => $data['client_id']       ?: null,
            ':creator_id'      => $data['creator_id']      ?: null,
            ':captain_id' => $data['captain_id'] !== '' ? $data['captain_id'] : null,
            ':branch_id'       => $data['branch_id']       ?: null,
            ':first_session'   => $data['first_session']   ?: null,
            ':last_session'    => $data['last_session']    ?: null,
            ':renewal_session' => $data['renewal_session'] ?: null,
            ':created_at'      => $createdAt,
            ':updated_at'      => $updatedAt,
            ':renewal_type'    => $data['renewal_type']    ?: null,
            ':receipt_status'  => $data['receipt_status']  ?? 'not_completed',
            ':exercise_time'   => $data['exercise_time']   ?: null,
            ':plan_id'         => $data['plan_id']         ?: null,
            ':level'           => $data['level']           ?: null,
            ':pdf_path'        => $data['pdf_path']        ?: null,
        ];
    }

    // Same field mapping as create(), minus :created_at — the UPDATE query's
    // default SET clause has no created_at column, so binding it unconditionally
    // would throw HY093. update() re-adds :created_at itself, only when an
    // explicit created_at_override was supplied.
    private function bindForUpdate(array $data): array {
        $params = $this->bind($data);
        unset($params[':created_at']);
        return $params;
    }
}