<?php
// app/models/PriceModel.php

class PriceModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── All prices ────────────────────────────────────────────────────────────

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT p.*, c.country AS country_name
            FROM prices p
            LEFT JOIN countries c ON c.id = p.country_id
            ORDER BY p.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Filtered prices ───────────────────────────────────────────────────────

    public function findFiltered(array $filters = []): array {
        $clauses = [];
        $params  = [];

        if (!empty($filters['search'])) {
            $clauses[] = 'p.description LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['country_id'])) {
            $clauses[] = 'p.country_id = :country_id';
            $params[':country_id'] = (int) $filters['country_id'];
        }

        if ($filters['visible'] !== '') {
            $clauses[] = 'p.visible = :visible';
            $params[':visible'] = (int) $filters['visible'];
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        $stmt = $this->db->prepare("
            SELECT p.*, c.country AS country_name
            FROM prices p
            LEFT JOIN countries c ON c.id = p.country_id
            {$where}
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Single price ──────────────────────────────────────────────────────────

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT p.*, c.country AS country_name
            FROM prices p
            LEFT JOIN countries c ON c.id = p.country_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO prices
                (description, price, country_id, visible, number_of_sessions, created_at, updated_at)
            VALUES
                (:description, :price, :country_id, :visible, :number_of_sessions, CURDATE(), CURDATE())
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE prices SET
                description        = :description,
                price              = :price,
                country_id         = :country_id,
                visible            = :visible,
                number_of_sessions = :number_of_sessions,
                updated_at         = CURDATE()
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }

    // ── Soft-delete ───────────────────────────────────────────────────────────

    public function hide(int $id): void {
        $stmt = $this->db->prepare("UPDATE prices SET visible = 0, updated_at = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function show(int $id): void {
        $stmt = $this->db->prepare("UPDATE prices SET visible = 1, updated_at = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function bind(array $data): array {
        return [
            ':description'        => $data['description'],
            ':price'              => $data['price'],
            ':country_id'         => $data['country_id'] ?: null,
            ':visible'            => $data['visible'],
            ':number_of_sessions' => $data['number_of_sessions'],
        ];
    }
}