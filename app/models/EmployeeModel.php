<?php
// app/models/EmployeeModel.php

class EmployeeModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ════════════════════════════════════════════════════════════════════════
    // READ
    // ════════════════════════════════════════════════════════════════════════


public function findFiltered(array $filters = []): array {
    $clauses = [];
    $params  = [];

    if (!empty($filters['search'])) {
        $clauses[] = '(username LIKE :search1 OR email LIKE :search2 OR phone LIKE :search3)';
        $params[':search1'] = '%' . $filters['search'] . '%';
        $params[':search2'] = '%' . $filters['search'] . '%';
        $params[':search3'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['role'])) {
        $clauses[] = 'role = :role';
        $params[':role'] = $filters['role'];
    }

    if ($filters['visible'] !== '') {
        $clauses[] = 'visible = :visible';
        $params[':visible'] = (int) $filters['visible'];
    }

    $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

    $stmt = $this->db->prepare("
        SELECT * FROM users
        {$where}
        ORDER BY username ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT * FROM users
            ORDER BY username ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Uniqueness checks ────────────────────────────────────────────────────

    public function usernameExists(string $username, int $excludeId = 0): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM users
            WHERE username = ? AND id != ?
        ");
        $stmt->execute([$username, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    public function emailExists(string $email, int $excludeId = 0): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM users
            WHERE email = ? AND id != ?
        ");
        $stmt->execute([$email, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    // ════════════════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════════════════

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users
                (username, email, password_hash, role, phone, visible, is_active, created_at)
            VALUES
                (:username, :email, :password_hash, :role, :phone, :visible, :is_active, CURDATE())
        ");
        $stmt->execute([
            ':username'      => $data['username'],
            ':email'         => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'          => $data['role'],
            ':phone'         => $data['phone'],
            ':visible'       => $data['visible'],
            ':is_active'     => $data['is_active'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════════════
    // UPDATE
    // ════════════════════════════════════════════════════════════════════════

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE users SET
                username  = :username,
                email     = :email,
                role      = :role,
                phone     = :phone,
                visible   = :visible,
                is_active = :is_active
            WHERE id = :id
        ");
        $stmt->execute([
            ':username'  => $data['username'],
            ':email'     => $data['email'],
            ':role'      => $data['role'],
            ':phone'     => $data['phone'],
            ':visible'   => $data['visible'],
            ':is_active' => $data['is_active'],
            ':id'        => $id,
        ]);
    }

    public function updatePassword(int $id, string $plainPassword): void {
        $stmt = $this->db->prepare("
            UPDATE users SET password_hash = ? WHERE id = ?
        ");
        $stmt->execute([password_hash($plainPassword, PASSWORD_BCRYPT), $id]);
    }

    public function updateLastLogin(int $id): void {
        $stmt = $this->db->prepare("
            UPDATE users SET last_login = NOW() WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // SOFT DELETE / TOGGLE
    // ════════════════════════════════════════════════════════════════════════

    public function hide(int $id): void {
        $stmt = $this->db->prepare("UPDATE users SET visible = 0, is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function activate(int $id): void {
        $stmt = $this->db->prepare("UPDATE users SET visible = 1, is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // MANY-TO-MANY  —  user_branch pivot
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Get all branches assigned to a user (with branch details).
     */
    public function getBranches(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT b.*
            FROM branches b
            INNER JOIN user_branch ub ON ub.branch_id = b.id
            WHERE ub.user_id = ?
            ORDER BY b.branch_name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all branch IDs assigned to a user (for form pre-selection).
     */
    public function getBranchIds(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT branch_id FROM user_branch WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Sync branches for a user — replaces all existing assignments.
     */
    public function syncBranches(int $userId, array $branchIds): void {
        // Remove all existing
        $del = $this->db->prepare("DELETE FROM user_branch WHERE user_id = ?");
        $del->execute([$userId]);

        if (empty($branchIds)) return;

        // Re-insert selected
        $ins = $this->db->prepare("
            INSERT IGNORE INTO user_branch (user_id, branch_id) VALUES (?, ?)
        ");
        foreach ($branchIds as $branchId) {
            $ins->execute([$userId, (int) $branchId]);
        }
    }
}