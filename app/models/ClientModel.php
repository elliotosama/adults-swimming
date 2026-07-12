<?php
// app/models/ClientModel.php

require_once dirname(__DIR__) . '/helpers/PhoneHelper.php';

class ClientModel {

    private PDO $db;

    public function __construct() {
        $this->db = get_db();
    }

    // ── Filtered + paginated ──────────────────────────────────────────────────

    public function findFiltered(array $filters = [], int $page = 1, int $perPage = 20): array {
        [$where, $params] = $this->buildWhere($filters);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT c.*, u.username AS creator_name
            FROM clients c
            LEFT JOIN users u ON u.id = c.created_by
            {$where}
            ORDER BY c.created_at DESC
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
            FROM clients c
            LEFT JOIN users u ON u.id = c.created_by
            {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── All clients (no pagination) ───────────────────────────────────────────

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT c.*, u.username AS creator_name
            FROM clients c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.visible = 1
            ORDER BY c.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Single client ─────────────────────────────────────────────────────────

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username AS creator_name
            FROM clients c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.id = ? AND c.visible = 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Uniqueness checks ─────────────────────────────────────────────────────

    public function phoneExists(string $phone, int $excludeId = 0): bool {
        return (bool) $this->findByPhone($phone, $excludeId);
    }

    public function findByPhone(string $phone, int $excludeId = 0): array|false {
        [$phoneSql, $params] = PhoneHelper::buildSearchCondition($phone);
        $stmt = $this->db->prepare("
            SELECT * FROM clients WHERE {$phoneSql} AND id != ? LIMIT 1
        ");
        $stmt->execute(array_merge($params, [$excludeId]));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists(string $email, int $excludeId = 0): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM clients WHERE email = ? AND id != ? AND visible = 1
        ");
        $stmt->execute([$email, $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO clients
                (client_name, phone, email, age, gender, created_by, created_at, visible)
            VALUES
                (:client_name, :phone, :email, :age, :gender, :created_by, CURDATE(), 1)
        ");
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE clients SET
                client_name = :client_name,
                phone       = :phone,
                email       = :email,
                age         = :age,
                gender      = :gender,
                created_by  = :created_by
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }

    // ── Delete (soft) ─────────────────────────────────────────────────────────

    public function delete(int $id): void {
        $stmt = $this->db->prepare("UPDATE clients SET visible = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    // ── WHERE builder ─────────────────────────────────────────────────────────

    private function buildWhere(array $filters): array {
        $clauses = ['c.visible = 1']; // always exclude hidden clients
        $params  = [];

        if (!empty($filters['search'])) {
            $clauses[] = '(c.client_name LIKE :search1 OR c.phone LIKE :search2 OR c.email LIKE :search3)';
            $params[':search1'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['gender'])) {
            $clauses[] = 'c.gender = :gender';
            $params[':gender'] = $filters['gender'];
        }

        $where = 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function bind(array $data): array {
        return [
            ':client_name' => $data['client_name'],
            ':phone'       => $data['phone'],
            ':email'       => $data['email']  ?: null,
            ':age'         => $data['age']    ?: null,
            ':gender'      => $data['gender'] ?: null,
            ':created_by'  => $data['created_by'],
        ];
    }
}
