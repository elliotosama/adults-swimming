<?php
// app/models/AuditLogModel.php

class AuditLogModel {
    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── READ ──────────────────────────────────────────────────────────────────

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM audit_log WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findAll(): array {
        $sql = "
            SELECT
                al.*,
                u.username AS user_name,
                u.email     AS user_email,
                u.role      AS user_role
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
        ";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT al.*, u.username AS user_name
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.user_id = ?
            ORDER BY al.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByAction(string $action): array {
        $stmt = $this->db->prepare("
            SELECT al.*, u.username AS user_name
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.action = ?
            ORDER BY al.created_at DESC
        ");
        $stmt->execute([$action]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $keyword): array {
        $stmt = $this->db->prepare("
            SELECT al.*, u.username AS user_name
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.action LIKE ? OR al.detail LIKE ? OR al.ip LIKE ? OR u.username LIKE ?
            ORDER BY al.created_at DESC
        ");
        $like = "%$keyword%";
        $stmt->execute([$like, $like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paginate(int $page = 1, int $perPage = 50): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->db->prepare("
            SELECT al.*, u.username AS user_name
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    }

    public function getDistinctActions(): array {
        return $this->db->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC")
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (user_id, action, detail, ip, created_at)
            VALUES (:user_id, :action, :detail, :ip, NOW())
        ");
        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':action'  => $data['action'],
            ':detail'  => $data['detail'] ?? null,
            ':ip'      => $data['ip']     ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function delete(int $id): int {
        $stmt = $this->db->prepare("DELETE FROM audit_log WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function clearAll(): int {
        return (int) $this->db->exec("DELETE FROM audit_log");
    }

    public function clearByUser(int $userId): int {
        $stmt = $this->db->prepare("DELETE FROM audit_log WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    // ── VALIDATION HELPERS ────────────────────────────────────────────────────

    public function exists(int $id): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM audit_log WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}