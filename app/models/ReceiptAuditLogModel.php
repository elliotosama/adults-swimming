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

        // ════════════════════════════════════════════════════════════════════════
        // effectiveNow
        //
        // Fallback business-day-aware timestamp for changed_at, used only when
        // the caller didn't pass one explicitly. Mirrors
        // ReceiptController::effectiveCreatedAt() exactly: a moment between
        // 12:00–2:59:59 AM is attributed to the previous calendar day. This
        // means every log()/logChanges() call site keeps working unchanged —
        // the business-day rule is applied automatically.
        // ════════════════════════════════════════════════════════════════════════
        private function effectiveNow(): string {
            $now = new DateTime('now', new DateTimeZone('Africa/Cairo'));
            if ((int) $now->format('H') < 3) {
                $now->modify('-1 day');
            }
            return $now->format('Y-m-d H:i:s');
        }

        // ── Insert one audit row ──────────────────────────────────────────────────
        //
        // $changedAt is optional — pass it explicitly (e.g. to line it up
        // exactly with a receipt's own effectiveCreatedAt() value) or leave
        // it null to let this model compute the business-day-aware "now".

        public function log(
            int $receiptId,
            int $changedBy,
            string $role,
            string $fieldName,
            $oldValue,
            $newValue,
            ?string $changedAt = null
        ): void {

            $stmt = $this->db->prepare("
                INSERT INTO receipt_audit_log
                    (receipt_id, changed_by, role, field_name, old_value, new_value, changed_at)
                VALUES
                    (:receipt_id, :changed_by, :role, :field_name, :old_value, :new_value, :changed_at)
            ");

            $stmt->execute([
                ':receipt_id' => $receiptId,
                ':changed_by' => $changedBy,
                ':role'       => $role,
                ':field_name' => $fieldName,
                ':old_value'  => $this->normalize($oldValue),
                ':new_value'  => $this->normalize($newValue),
                ':changed_at' => $changedAt ?: $this->effectiveNow(),
            ]);
        }

        // ── Compare and log changes ───────────────────────────────────────────────
        //
        // $changedAt is applied uniformly to every field changed in this
        // batch, so a single update() call produces audit rows that all
        // share the exact same business-day-aware timestamp.

        public function logChanges(
            int $receiptId,
            int $changedBy,
            string $role,
            array $old,
            array $new,
            ?string $changedAt = null
        ): void {

            $changedAt = $changedAt ?: $this->effectiveNow();

            foreach ($new as $field => $newVal) {

                $oldVal = $old[$field] ?? null;

                if (!$this->isEqual($oldVal, $newVal)) {
                    $this->log(
                        $receiptId,
                        $changedBy,
                        $role,
                        $field,
                        $oldVal,
                        $newVal,
                        $changedAt
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