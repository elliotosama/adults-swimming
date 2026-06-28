<?php

class CountryModel {
    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // FIX: was only used in create/update — centralised binding for all 3 fields
    private function bind(array $data): array {
        return [
            ':country'      => $data['country'],
            ':country_code' => $data['country_code'],
            ':visible'      => $data['visible'],
        ];
    }

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT * FROM countries
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: original INSERT was missing the `visible` column entirely



    public function findVisible(): array {
    $stmt = $this->db->query("
        SELECT id, country FROM countries
        WHERE visible = 1
        ORDER BY country ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
}

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO countries (country, country_code, visible, created_at)
            VALUES (:country, :country_code, :visible, CURDATE())
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    public function nameExists(string $name, int $excludeId = 0): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM countries
            WHERE country = ? AND id != ?
        ");
        $stmt->execute([$name, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare('SELECT * FROM countries WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hide(int $id): void {
        $stmt = $this->db->prepare("UPDATE countries SET visible = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    // FIX: reused bind() and merged :id cleanly instead of repeating keys
    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE countries
            SET country      = :country,
                country_code = :country_code,
                visible      = :visible
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }
}