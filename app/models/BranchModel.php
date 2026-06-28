<?php
// app/models/BranchModel.php

class BranchModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── All branches ─────────────────────────────────────────────────────────

    public function distinctCountries(): array {
        $stmt = $this->db->query('
            SELECT c.id, c.country
            FROM countries c
            INNER JOIN branches b ON b.country_id = c.id
            WHERE c.visible = 1
            GROUP BY c.id, c.country
            ORDER BY c.country
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

public function findAll(array $filters = []): array {
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['search'])) {
        $where[]          = 'b.branch_name LIKE :search';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['country_id'])) {
        $where[]              = 'b.country_id = :country_id';
        $params['country_id'] = (int) $filters['country_id'];
    }
    if (($filters['visibility'] ?? '') === 'visible') {
        $where[] = 'b.visible = 1';
    } elseif (($filters['visibility'] ?? '') === 'hidden') {
        $where[] = 'b.visible = 0';
    }

    // ← ADD THIS
    if (!empty($filters['area_manager_id'])) {
        $where[]                    = 'b.id IN (SELECT branch_id FROM user_branch WHERE user_id = :area_manager_id)';
        $params['area_manager_id']  = (int) $filters['area_manager_id'];
    }

    $sql  = '
        SELECT b.*, c.country
        FROM branches b
        LEFT JOIN countries c ON c.id = b.country_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY b.id DESC
    ';
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Name uniqueness check (exclude current id on edit) ───────────────────

    public function nameExists(string $name, int $excludeId = 0): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM branches
            WHERE branch_name = ? AND id != ?
        ");
        $stmt->execute([$name, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO branches
                (branch_name, country_id, visible,
                 working_days1, working_days2, working_days3,
                 working_time_from, working_time_to,
                 created_at)
            VALUES
                (:branch_name, :country_id, :visible,
                 :working_days1, :working_days2, :working_days3,
                 :working_time_from, :working_time_to,
                 CURDATE())
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE branches SET
                branch_name       = :branch_name,
                country_id        = :country_id,
                visible           = :visible,
                working_days1     = :working_days1,
                working_days2     = :working_days2,
                working_days3     = :working_days3,
                working_time_from = :working_time_from,
                working_time_to   = :working_time_to
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }

    // ── Soft-delete (hide) ───────────────────────────────────────────────────

    public function hide(int $id): void {
        $stmt = $this->db->prepare("UPDATE branches SET visible = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── Reactivate ───────────────────────────────────────────────────────────

    public function show(int $id): void {
        $stmt = $this->db->prepare("UPDATE branches SET visible = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Map form data to PDO-named parameters.
     */
    private function bind(array $data): array {
        return [
            ':branch_name'       => $data['branch_name'],
            ':country_id'        => $data['country_id'],
            ':visible'           => $data['visible'],
            ':working_days1'     => $this->daysToSet($data['working_days1'] ?? []),
            ':working_days2'     => $this->daysToSet($data['working_days2'] ?? []),
            ':working_days3'     => $this->daysToSet($data['working_days3'] ?? []),
            ':working_time_from' => $data['working_time_from'] ?: null,
            ':working_time_to'   => $data['working_time_to']   ?: null,
        ];
    }
public function isManagedBy(int $branchId, int $userId): bool {
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM user_branch
        WHERE branch_id = ?
          AND user_id   = ?
    ");
    $stmt->execute([$branchId, $userId]);
    return (bool) $stmt->fetchColumn();
}

    /**
     * MySQL SET columns are stored as comma-separated strings.
     * Accept either an array ['Sunday','Monday'] or a string and normalise.
     */
    private function daysToSet(array|string $days): string {
        if (is_string($days)) return $days;
        return implode(',', array_filter($days));
    }
}