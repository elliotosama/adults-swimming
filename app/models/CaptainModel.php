<?php
// app/models/CaptainModel.php
require_once dirname(__DIR__) . '/helpers/PhoneHelper.php';

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
            [$primaryPhoneSql, $primaryPhoneParams] = PhoneHelper::buildSearchCondition($filters['search'], 'c.phone_number');
            [$secondaryPhoneSql, $secondaryPhoneParams] = PhoneHelper::buildSearchCondition($filters['search'], 'c.secondary_phone_number');
            $where[]  = "(c.captain_name LIKE ? OR {$primaryPhoneSql} OR {$secondaryPhoneSql})";
            $params[] = '%' . $filters['search'] . '%';
            $params = array_merge($params, $primaryPhoneParams, $secondaryPhoneParams);
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

        if (!empty($filters['managed_branch_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['managed_branch_ids']), '?'));
            $where[] = "c.id IN (
                            SELECT captain_id FROM captain_branch
                            WHERE branch_id IN ({$placeholders})
                        )";
            foreach ($filters['managed_branch_ids'] as $branchId) {
                $params[] = (int) $branchId;
            }
        }

        $sql = '
            SELECT c.*,
                   GROUP_CONCAT(b.branch_name ORDER BY b.branch_name SEPARATOR ", ") AS branch_names,
                   GROUP_CONCAT(cb.branch_id ORDER BY cb.branch_id SEPARATOR ",") AS branch_ids_csv
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

    public function addBranches(string $captainId, array $branchIds): void {
        if (empty($branchIds)) return;

        $stmt = $this->db->prepare('
            INSERT IGNORE INTO captain_branch (captain_id, branch_id) VALUES (?, ?)
        ');

        foreach ($branchIds as $branchId) {
            $branchId = (int) $branchId;
            if ($branchId > 0) {
                $stmt->execute([$captainId, $branchId]);
            }
        }
    }

    public function removeBranches(string $captainId, array $branchIds): void {
        $branchIds = array_values(array_filter(array_map('intval', $branchIds)));
        if (empty($branchIds)) return;

        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            DELETE FROM captain_branch
            WHERE captain_id = ?
              AND branch_id IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$captainId], $branchIds));
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

    public function phoneExists(string $phone, string $excludeId = ''): bool {
        [$primarySql, $primaryParams] = PhoneHelper::buildSearchCondition($phone, 'phone_number');
        [$secondarySql, $secondaryParams] = PhoneHelper::buildSearchCondition($phone, 'secondary_phone_number');

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM captains
            WHERE id != ?
              AND ({$primarySql} OR {$secondarySql})
        ");
        $stmt->execute(array_merge([$excludeId], $primaryParams, $secondaryParams));

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
                    (id, captain_name, phone_number, secondary_phone_number, age, email, academic_qualification, ssn_card_path, certificate_image_path, visible, created_at, created_by)
                VALUES
                    (:id, :captain_name, :phone_number, :secondary_phone_number, :age, :email, :academic_qualification, :ssn_card_path, :certificate_image_path, :visible, CURDATE(), :created_by)
            ');
            $stmt->execute([
                ':id'                     => $newId,
                ':captain_name'           => $data['captain_name'],
                ':phone_number'           => $data['phone_number'] ?: null,
                ':secondary_phone_number' => $data['secondary_phone_number'] ?: null,
                ':age'                    => $data['age'] ?? null,
                ':email'                  => $data['email'] ?: null,
                ':academic_qualification' => $data['academic_qualification'] ?: null,
                ':ssn_card_path'          => $data['ssn_card_path'] ?? null,
                ':certificate_image_path' => $data['certificate_image_path'] ?? null,
                ':visible'                => $data['visible'],
                ':created_by'             => $data['created_by'] ?? null,
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
                secondary_phone_number = :secondary_phone_number,
                age           = :age,
                email         = :email,
                academic_qualification = :academic_qualification,
                ssn_card_path = :ssn_card_path,
                certificate_image_path = :certificate_image_path,
                visible       = :visible
            WHERE id = :id
        ');
        $stmt->execute([
            ':captain_name'           => $data['captain_name'],
            ':phone_number'           => $data['phone_number'] ?: null,
            ':secondary_phone_number' => $data['secondary_phone_number'] ?: null,
            ':age'                    => $data['age'] ?? null,
            ':email'                  => $data['email'] ?: null,
            ':academic_qualification' => $data['academic_qualification'] ?: null,
            ':ssn_card_path'          => $data['ssn_card_path'] ?? null,
            ':certificate_image_path' => $data['certificate_image_path'] ?? null,
            ':visible'                => $data['visible'],
            ':id'                     => $id,
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
