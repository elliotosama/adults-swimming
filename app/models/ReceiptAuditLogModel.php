    <?php

    class ReceiptAuditLogModel {

        private PDO $db;

        public function __construct() {
            $this->db = get_db();
        }

        // ── Get logs for a receipt ────────────────────────────────────────────────

        public function findByReceipt(int $receiptId): array {
            $stmt = $this->db->prepare("
                SELECT l.*,
                    u.username AS changer_name
                FROM receipt_audit_log l
                LEFT JOIN users u ON u.id = l.changed_by
                WHERE l.receipt_id = ?
                ORDER BY l.changed_at DESC
            ");
            $stmt->execute([$receiptId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── Admin view ────────────────────────────────────────────────────────────

        public function findAll(int $limit = 200): array {
            $limit = (int)$limit;

            $stmt = $this->db->query("
                SELECT l.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS changer_name
                FROM receipt_audit_log l
                LEFT JOIN users u ON u.id = l.changed_by
                ORDER BY l.changed_at DESC
                LIMIT $limit
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── Insert one audit row ──────────────────────────────────────────────────

        public function log(
            int $receiptId,
            int $changedBy,
            string $role,
            string $fieldName,
            $oldValue,
            $newValue
        ): void {

            $stmt = $this->db->prepare("
                INSERT INTO receipt_audit_log
                    (receipt_id, changed_by, role, field_name, old_value, new_value)
                VALUES
                    (:receipt_id, :changed_by, :role, :field_name, :old_value, :new_value)
            ");

            $stmt->execute([
                ':receipt_id' => $receiptId,
                ':changed_by' => $changedBy,
                ':role'       => $role,
                ':field_name' => $fieldName,
                ':old_value'  => $this->normalize($oldValue),
                ':new_value'  => $this->normalize($newValue),
            ]);
        }

        // ── Compare and log changes ───────────────────────────────────────────────

        public function logChanges(
            int $receiptId,
            int $changedBy,
            string $role,
            array $old,
            array $new
        ): void {

            foreach ($new as $field => $newVal) {

                $oldVal = $old[$field] ?? null;

                if (!$this->isEqual($oldVal, $newVal)) {
                    $this->log(
                        $receiptId,
                        $changedBy,
                        $role,
                        $field,
                        $oldVal,
                        $newVal
                    );
                }
            }
        }

        // ── Helpers ───────────────────────────────────────────────────────────────

        private function isEqual($a, $b): bool {
            return $this->normalize($a) === $this->normalize($b);
        }

        private function normalize($value) {
            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if ($value === null) {
                return null;
            }

            return (string)$value;
        }
    }