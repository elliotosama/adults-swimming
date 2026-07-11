<?php
// app/models/CaptainModel.php

class CaptainModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── All captains (with branch names via GROUP_CONCAT) ─────────────────────

    public function findAll(array $filters = []): array {
        $where  = [];
        $params = [];

        if (!empty($filters['visible']) && $filters['visible'] === 'visible') {
            $where[] = 'c.visible = 1';
        } elseif (!empty($filters['visible']) && $filters['visible'] === 'hidden') {
            $where[] = 'c.visible = 0';
        }

        if (!empty($filters['search'])) {
            $where[]  = '(c.captain_name LIKE ? OR c.phone_number LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['branch_id'])) {
            $where[]  = 'c.id IN (SELECT captain_id FROM captain_branch WHERE branch_id = ?)';
            $params[] = (int) $filters['branch_id'];
        }

        if (!empty($filters['area_manager_id'])) {
            $where[]  = 'c.id IN (
                            SELECT cb.captain_id FROM captain_branch cb
                            INNER JOIN user_branch ub ON ub.branch_id = cb.branch_id
                            WHERE ub.user_id = ?
                         )';
            $params[] = (int) $filters['area_manager_id'];
        }

        $sql = '
            SELECT c.*,
                   GROUP_CONCAT(b.branch_name ORDER BY b.branch_name SEPARATOR ", ") AS branch_names
            FROM captains c
            LEFT JOIN captain_branch cb ON cb.captain_id = c.id
            LEFT JOIN branches b        ON b.id = cb.branch_id
        ';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY c.id ORDER BY c.captain_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Single captain with assigned branch IDs ───────────────────────────────

    public function findById(string $id): array|false {
        $stmt = $this->db->prepare('SELECT * FROM captains WHERE id = ?');
        $stmt->execute([$id]);
        $captain = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$captain) return false;

        $captain['branch_ids'] = $this->getBranchIds($id);

        return $captain;
    }

    // ── Get branch IDs assigned to a captain ──────────────────────────────────

    public function getBranchIds(string $captainId): array {
        $stmt = $this->db->prepare('
            SELECT branch_id FROM captain_branch WHERE captain_id = ?
        ');
        $stmt->execute([$captainId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Check if captain belongs to any branch managed by this user ───────────

    public function isManagedBy(string $captainId, int $userId): bool {
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM captain_branch cb
            INNER JOIN user_branch ub ON ub.branch_id = cb.branch_id
            WHERE cb.captain_id = ?
              AND ub.user_id    = ?
        ');
        $stmt->execute([$captainId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    // ── Sync pivot table (delete → reinsert) ──────────────────────────────────

    public function syncBranches(string $captainId, array $branchIds): void {
        $stmt = $this->db->prepare('DELETE FROM captain_branch WHERE captain_id = ?');
        $stmt->execute([$captainId]);

        if (empty($branchIds)) return;

        $stmt = $this->db->prepare('
            INSERT INTO captain_branch (captain_id, branch_id) VALUES (?, ?)
        ');
        foreach ($branchIds as $branchId) {
            $branchId = (int) $branchId;
            if ($branchId > 0) {
                $stmt->execute([$captainId, $branchId]);
            }
        }
    }

    // ── Name uniqueness check ─────────────────────────────────────────────────

    public function nameExists(string $name, string $excludeId = ''): bool {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM captains
            WHERE captain_name = ? AND id != ?
        ');
        $stmt->execute([$name, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    // ── Generate next id in the format c-N ─────────────────────────────────────

    private function generateNextId(): string {
        // Pull the highest numeric suffix currently in use, locking the rows
        // so a concurrent create() can't grab the same number.
        $stmt = $this->db->prepare("
            SELECT id FROM captains
            WHERE id REGEXP '^c-[0-9]+$'
            ORDER BY CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastId) {
            $nextNumber = (int) substr($lastId, 2) + 1;
        }

        return 'c-' . $nextNumber;
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): string {
        $this->db->beginTransaction();

        try {
            $newId = $this->generateNextId();

            $stmt = $this->db->prepare('
                INSERT INTO captains
                    (id, captain_name, phone_number, age, email, ssn_card_path, visible, created_at, created_by)
                VALUES
                    (:id, :captain_name, :phone_number, :age, :email, :ssn_card_path, :visible, CURDATE(), :created_by)
            ');
            $stmt->execute([
                ':id'            => $newId,
                ':captain_name'  => $data['captain_name'],
                ':phone_number'  => $data['phone_number'] ?: null,
                ':age'           => $data['age'] ?? null,
                ':email'         => $data['email'] ?: null,
                ':ssn_card_path' => $data['ssn_card_path'] ?? null,
                ':visible'       => $data['visible'],
                ':created_by'    => $data['created_by'] ?? null,
            ]);

            $this->db->commit();
            return $newId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(string $id, array $data): void {
        $stmt = $this->db->prepare('
            UPDATE captains SET
                captain_name  = :captain_name,
                phone_number  = :phone_number,
                age           = :age,
                email         = :email,
                ssn_card_path = :ssn_card_path,
                visible       = :visible
            WHERE id = :id
        ');
        $stmt->execute([
            ':captain_name'  => $data['captain_name'],
            ':phone_number'  => $data['phone_number'] ?: null,
            ':age'           => $data['age'] ?? null,
            ':email'         => $data['email'] ?: null,
            ':ssn_card_path' => $data['ssn_card_path'] ?? null,
            ':visible'       => $data['visible'],
            ':id'            => $id,
        ]);
    }

    // ── Soft-delete ───────────────────────────────────────────────────────────

    public function hide(string $id): void {
        $stmt = $this->db->prepare('UPDATE captains SET visible = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ── Reactivate ────────────────────────────────────────────────────────────

    public function show(string $id): void {
        $stmt = $this->db->prepare('UPDATE captains SET visible = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }
}