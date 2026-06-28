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
               ) AS has_refund

        FROM receipts r
        LEFT JOIN clients  c  ON c.id  = r.client_id
        LEFT JOIN users    cr ON cr.id = r.creator_id
        LEFT JOIN captains ca ON ca.id = r.captain_id
        LEFT JOIN branches b  ON b.id  = r.branch_id
        LEFT JOIN prices   p  ON p.id  = r.plan_id

        ORDER BY r.created_at DESC
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
           p.description   AS plan_name,
           COALESCE(p.price, 0) AS plan_price,
           (SELECT COUNT(*) FROM receipt_audit_log al WHERE al.receipt_id = r.id) AS audit_count,
           (SELECT COUNT(*) FROM transactions t WHERE t.receipt_id = r.id) AS transaction_count,

        EXISTS (
    SELECT 1
    FROM transactions tr
    WHERE tr.receipt_id = r.id
      AND tr.type = 'refund'
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
    ORDER BY r.created_at DESC
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



public function searchAll(array $filters = []): array {
        [$where, $params] = $this->buildWhere($filters);

        $sql = "
            SELECT r.id,
                   c.client_name,
                   c.phone         AS phone,
                   b.branch_name,
                   ca.captain_name,
                   p.description   AS plan_name,
                   p.price         AS plan_price,
                   r.first_session,
                   r.last_session,
                   r.renewal_session,
                   r.renewal_type,
                   r.receipt_status,
                   r.exercise_time,
                   r.level,
                   r.receipt_ref,
                   r.is_refunded,
                   cr.username     AS creator_name,
                   r.created_at,
                   (SELECT COUNT(*) FROM receipt_audit_log al WHERE al.receipt_id = r.id) AS audit_count,
                   (SELECT COUNT(*) FROM transactions        t  WHERE t.receipt_id  = r.id) AS transaction_count,
                   (SELECT COALESCE(SUM(CASE WHEN t2.type='payment' THEN t2.amount ELSE 0 END),0)
                        FROM transactions t2 WHERE t2.receipt_id = r.id) AS gross_paid,
                   (SELECT COALESCE(SUM(CASE WHEN t2.type='refund' THEN t2.amount ELSE 0 END),0)
                        FROM transactions t2 WHERE t2.receipt_id = r.id) AS total_refunded

            FROM receipts r
            LEFT JOIN clients  c  ON c.id  = r.client_id
            LEFT JOIN users    cr ON cr.id = r.creator_id
            LEFT JOIN captains ca ON ca.id = r.captain_id
            LEFT JOIN branches b  ON b.id  = r.branch_id
            LEFT JOIN prices   p  ON p.id  = r.plan_id
            {$where}
            ORDER BY r.created_at DESC
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
                   cr.username     AS creator_name,
                   ca.captain_name AS captain_name,
                   c.phone         AS phone_number,
                   b.branch_name,


                EXISTS (
    SELECT 1
    FROM transactions t
    WHERE t.receipt_id = r.id
      AND t.type = 'refund'
) AS has_refund,


                   p.price         AS plan_price,
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
       ) AS has_refund


            FROM receipts r
            LEFT JOIN prices   p ON p.id = r.plan_id
            LEFT JOIN branches b ON b.id = r.branch_id
            WHERE r.client_id = ?
            ORDER BY r.created_at DESC
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
            ORDER BY r.created_at DESC
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

    // ── Branch ID for a branch_manager ───────────────────────────────────────

    public function getBranchIdByManager(int $userId): ?int {
        $stmt = $this->db->prepare(
            "SELECT branch_id FROM user_branch WHERE user_id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO receipts
                (client_id, creator_id, captain_id, branch_id,
                 first_session, last_session, renewal_session,
                 created_at, renewal_type, receipt_status,
                 exercise_time, plan_id, level, pdf_path)
            VALUES
                (:client_id, :creator_id, :captain_id, :branch_id,
                 :first_session, :last_session, :renewal_session,
                 CURDATE(), :renewal_type, :receipt_status,
                 :exercise_time, :plan_id, :level, :pdf_path)
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE receipts SET
                client_id       = :client_id,
                creator_id      = :creator_id,
                captain_id      = :captain_id,
                branch_id       = :branch_id,
                first_session   = :first_session,
                last_session    = :last_session,
                renewal_session = :renewal_session,
                renewal_type    = :renewal_type,
                receipt_status  = :receipt_status,
                exercise_time   = :exercise_time,
                plan_id         = :plan_id,
                level           = :level,
                pdf_path        = :pdf_path
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
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
            $conditions[] = "(c.client_name LIKE :search_name OR c.phone LIKE :search_phone OR r.client_id = :search_id)";
            $params[':search_name']  = '%' . $filters['search'] . '%';
            $params[':search_phone'] = '%' . $filters['search'] . '%';
            $params[':search_id']    = (int) $filters['search'];
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

        if (!empty($filters['created_from'])) {
            $conditions[]       = "DATE(r.created_at) >= :cr_from";
            $params[':cr_from'] = $filters['created_from'];
        }
        if (!empty($filters['created_to'])) {
            $conditions[]     = "DATE(r.created_at) <= :cr_to";
            $params[':cr_to'] = $filters['created_to'];
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

        // ── NEW: renewal_type filter ──────────────────────────────────────────
        if (!empty($filters['renewal_types']) && is_array($filters['renewal_types'])) {
            $placeholders = [];
            foreach ($filters['renewal_types'] as $i => $rt) {
                $key            = ":rtype_{$i}";
                $placeholders[] = $key;
                $params[$key]   = $rt;
            }
            $conditions[] = "r.renewal_type IN (" . implode(',', $placeholders) . ")";
        }

        // ── NEW: has_refund filter ────────────────────────────────────────────
        if (!empty($filters['has_refund'])) {
            $conditions[] = "EXISTS (SELECT 1 FROM transactions tr WHERE tr.receipt_id = r.id AND tr.type = 'refund')";
        }

        if (!empty($filters['has_updates'])) {
            $conditions[] = "
                (EXISTS (SELECT 1 FROM receipt_audit_log al WHERE al.receipt_id = r.id)
                 OR
                 EXISTS (SELECT 1 FROM transactions t WHERE t.receipt_id = r.id))
            ";
        }

if (!empty($filters['force_creator_id'])) {
    // Role-forced (customerService): always strict, never changes
    $conditions[]          = "r.creator_id = :creator_id";
    $params[':creator_id'] = (int) $filters['force_creator_id'];

} elseif (!empty($filters['creator_id'])) {
    $creatorId = (int) $filters['creator_id'];

    if (!empty($filters['creator_created_only'])) {
        // Checkbox ON → only receipts this employee originally created
        $conditions[]          = "r.creator_id = :creator_id";
        $params[':creator_id'] = $creatorId;
    } else {
        // Checkbox OFF (default) → receipts created by OR touched by this employee
        // "touched" = recorded a transaction OR appears in audit log
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

    private function bind(array $data): array {
        return [
            ':client_id'       => $data['client_id']       ?: null,
            ':creator_id'      => $data['creator_id']      ?: null,
            ':captain_id'      => $data['captain_id']      ?: null,
            ':branch_id'       => $data['branch_id']       ?: null,
            ':first_session'   => $data['first_session']   ?: null,
            ':last_session'    => $data['last_session']    ?: null,
            ':renewal_session' => $data['renewal_session'] ?: null,
            ':renewal_type'    => $data['renewal_type']    ?: null,
            ':receipt_status'  => $data['receipt_status']  ?? 'not_completed',
            ':exercise_time'   => $data['exercise_time']   ?: null,
            ':plan_id'         => $data['plan_id']         ?: null,
            ':level'           => $data['level']           ?: null,
            ':pdf_path'        => $data['pdf_path']        ?: null,
        ];
    }
}