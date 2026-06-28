<?php
// app/models/UserModel.php

class UserModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

// UserModel::findAll() — apply filters
public function findAll(array $filters = []): array {
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['search'])) {
        $where[]          = '(username LIKE :search OR email LIKE :search OR phone LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['role'])) {
        $where[]         = 'role = :role';
        $params['role']  = $filters['role'];
    }
    if ($filters['bisible'] !== '') {
        $where[]              = 'visible = :visible';
        $params['visible']  = (int) $filters['visible'];
    }

    $sql  = 'SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC';
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }



    public function getBranchForLogedIn(int $userId): int {
        $stmt = $this->db->prepare('SELECT branch_id FROM user_branch WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getGroupsForBranch(int $branchId): array {
        $stmt = $this->db->prepare("SELECT * FROM groups WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getEnrollmentsForGroup(int $groupId): array {
        $stmt = $this->db->prepare("SELECT * FROM enrollments WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentsByBranch(): array {
        $branchId = $this->getBranchForLogedIn($_SESSION['user']['id']);
        $stmt = $this->db->prepare("
            SELECT u.*, g.id AS group_id
            FROM enrollments e
            INNER JOIN groups g ON e.group_id = g.id
            INNER JOIN users u ON u.id = e.student_id
            WHERE g.branch_id = ?
        ");

        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByRole(string $role): array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE role = ?');
        $stmt->execute([strtolower(trim($role))]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByVerificationToken(string $token): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE verification_token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function findByResetToken(string $token): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $token = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare(
            'INSERT INTO users
             (username, email, phone_number, password_hash, role, is_active, is_verified, verification_token, verification_expires)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        );
        $stmt->execute([
            $data['full_name'],
            strtolower(trim($data['email'])),
            $data['phone_number'] ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            $data['role'] ?? 'student',
            $data['is_active'] ?? 1,
            $data['is_verified'] ?? 0,
            $token,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update(int $id, array $data): bool {
        $fields = [
            'username     = ?',
            'email         = ?',
            'phone_number  = ?',
            'role          = ?',
            'is_active     = ?',
        ];
        $params = [
            $data['full_name'],
            strtolower(trim($data['email'])),
            $data['phone_number'] ?? null,
            $data['role'],
            $data['is_active'],
        ];

        // Only update password if provided
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $params[] = $id;

        $stmt = $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Delete / Deactivate ──────────────────────────────────────────────────

    public function deactivate(int $id): bool {
        $stmt = $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Checks ───────────────────────────────────────────────────────────────

    public function emailExists(string $email): bool {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return (bool) $stmt->fetch();
    }

    public function assignUser(int $branchId, int $userId): void {
        $db   = get_db();
        $stmt = $db->prepare("
            INSERT IGNORE INTO user_branch (branch_id, user_id)
            VALUES (:branch_id, :user_id)
        ");
        $stmt->execute([':branch_id' => $branchId, ':user_id' => $userId]);
    }

    public function isEmailTaken(string $email, ?int $excludeId = null): bool {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $stmt->execute([strtolower(trim($email)), $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([strtolower(trim($email))]);
        }
        return (bool) $stmt->fetch();
    }

    // ── Verify email ─────────────────────────────────────────────────────────

    public function verifyEmail(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET is_verified = 1, verification_token = NULL, verification_expires = NULL
             WHERE id = ? AND is_verified = 0'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function getVerificationToken(int $id): ?string {
        $stmt = $this->db->prepare(
            'SELECT verification_token FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row['verification_token'] ?? null;
    }

    public function refreshVerificationToken(int $id): string {
        $token = bin2hex(random_bytes(32));
        $stmt  = $this->db->prepare(
            'UPDATE users
             SET verification_token = ?, verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE id = ?'
        );
        $stmt->execute([$token, $id]);
        return $token;
    }

    // ── Password reset ───────────────────────────────────────────────────────

    public function setResetToken(int $id): string {
        $token = bin2hex(random_bytes(32));
        $stmt  = $this->db->prepare(
            'UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?'
        );
        $stmt->execute([$token, $id]);
        return $token;
    }

    public function updatePassword(int $id, string $newPassword): bool {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password_hash = ?, reset_token = NULL, reset_expires = NULL
             WHERE id = ?'
        );
        $stmt->execute([
            password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // ── Misc ─────────────────────────────────────────────────────────────────

    public function updateLastLogin(int $id): void {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function countByRole(): array {
        $stmt = $this->db->query(
            'SELECT role, COUNT(*) AS total FROM users GROUP BY role'
        );
        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['role']] = (int) $row['total'];
        }
        return $result;
    }
}