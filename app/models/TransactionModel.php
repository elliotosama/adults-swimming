<?php
// app/models/TransactionModel.php

class TransactionModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── Filtered + paginated list ─────────────────────────────────────────────

    public function findFiltered(array $filters = [], int $page = 1, int $perPage = 20): array {
        [$where, $params] = $this->buildWhere($filters);

        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.username AS creator_name,
                   r.branch_id,
                   c.phone AS client_phone,
                   c.client_name
            FROM transactions t
            LEFT JOIN users    u ON u.id = t.created_by
            LEFT JOIN receipts r ON r.id = t.receipt_id
            LEFT JOIN clients  c ON c.id = r.client_id
            {$where}
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFiltered(array $filters = []): int {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM transactions t
            LEFT JOIN users    u ON u.id = t.created_by
            LEFT JOIN receipts r ON r.id = t.receipt_id
            LEFT JOIN clients  c ON c.id = r.client_id
            {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── WHERE builder ─────────────────────────────────────────────────────────

    private function buildWhere(array $filters): array {
        $clauses = [];
        $params  = [];

        // customer_service → only their own transactions
        if (!empty($filters['created_by'])) {
            $clauses[] = 't.created_by = :created_by';
            $params[':created_by'] = $filters['created_by'];
        }

        // branch_manager → one branch
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'r.branch_id = :branch_id';
            $params[':branch_id'] = $filters['branch_id'];
        }

        // area_manager → multiple branches
        if (!empty($filters['branch_ids'])) {
            $in = implode(',', array_map('intval', $filters['branch_ids']));
            $clauses[] = "r.branch_id IN ({$in})";
        }

        // search by receipt id
        if (!empty($filters['receipt_id'])) {
            $clauses[] = 't.receipt_id = :receipt_id';
            $params[':receipt_id'] = $filters['receipt_id'];
        }

        // search by client phone
        if (!empty($filters['client_phone'])) {
            $clauses[] = 'c.phone LIKE :client_phone';
            $params[':client_phone'] = '%' . $filters['client_phone'] . '%';
        }

        // exclude transactions linked to receipts that have been refunded
        // (a receipt is considered refunded when it has a transaction with type = 'refund')
        if (!empty($filters['exclude_refunded_receipts'])) {
            $clauses[] = '(
                t.receipt_id IS NULL
                OR t.receipt_id NOT IN (
                    SELECT DISTINCT receipt_id
                    FROM transactions
                    WHERE type = :refunded_type
                    AND receipt_id IS NOT NULL
                )
            )';
            $params[':refunded_type'] = 'refund';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    // ── All transactions ──────────────────────────────────────────────────────

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT t.*,
                   u.username AS creator_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.created_by
            ORDER BY t.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Transactions for a receipt ────────────────────────────────────────────

    public function findByReceipt(int $receiptId): array {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.username AS creator_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.receipt_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$receiptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Single transaction ────────────────────────────────────────────────────

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.username AS creator_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Total paid for a receipt ──────────────────────────────────────────────

    public function totalByReceipt(int $receiptId): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM transactions
            WHERE receipt_id = ? AND type = 'payment'
        ");
        $stmt->execute([$receiptId]);
        return (float) $stmt->fetchColumn();
    }

    // ── Remaining balance for a receipt ──────────────────────────────────────

    public function getRemainingByReceipt(int $receiptId): float {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN type = 'refund'  THEN amount ELSE 0 END), 0) AS total_refunded
            FROM transactions
            WHERE receipt_id = ?
        ");
        $stmt->execute([$receiptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float) $row['total_paid'] - (float) $row['total_refunded'];
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO transactions
                (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
            VALUES
                (:payment_method, :amount, :receipt_id, :created_by, CURDATE(), :attachment, :notes, :type)
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE transactions SET
                payment_method = :payment_method,
                amount         = :amount,
                receipt_id     = :receipt_id,
                notes          = :notes,
                type           = :type,
                attachment     = :attachment
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function bind(array $data): array {
        return [
            ':payment_method' => $data['payment_method'] ?? null,
            ':amount'         => $data['amount']         ?? null,
            ':receipt_id'     => $data['receipt_id']     ?: null,
            ':created_by'     => $data['created_by']     ?: null,
            ':attachment'     => $data['attachment']     ?? null,
            ':notes'          => $data['notes']          ?? null,
            ':type'           => $data['type']           ?? 'payment',
        ];
    }
}