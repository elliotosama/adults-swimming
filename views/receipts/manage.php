<?php
/**
 * views/receipts/manage.php
 *
 * Unified receipt hub — five tabs in one page:
 *   #new      → create a new receipt
 *   #renew    → renew a receipt
 *   #payment  → add a payment
 *   #refund   → refund / استرداد
 *   #client   → add a new client ← NEW
 *
 * Variables required by the refund tab (supplied by the controller):
 *   $refundSearch   string        (search query)
 *   $refundClient   array|null    (found client)
 *   $refundReceipts array         (eligible receipts)
 *
 * Variables required by the client tab:
 *   $newClientData  array         (repopulate on validation failure)
 *   $clientErrors   array         (validation errors)
 */


require ROOT . '/views/includes/layout_top.php';

// ── shared defaults ───────────────────────────────────────────
$receipt          = $receipt          ?? [];
$isEdit           = $isEdit           ?? false;
$isRenewal        = $isRenewal        ?? false;
$autoRenewalType  = $autoRenewalType  ?? '';
$prevLastSession  = $prevLastSession  ?? '';
$prevFirstSession = $prevFirstSession ?? '';
$errors           = $errors           ?? [];
$paySearch        = $paySearch        ?? '';
$payClient        = $payClient        ?? null;
$payReceipts      = $payReceipts      ?? [];
$activeTab        = $activeTab        ?? 'new';

// ── refund defaults ───────────────────────────────────────────
$refundSearch   = $refundSearch   ?? '';
$refundClient   = $refundClient   ?? null;
$refundReceipts = $refundReceipts ?? [];

// ── client tab defaults ───────────────────────────────────────
$newClientData = $newClientData ?? [];
$clientErrors  = $clientErrors  ?? [];

$db = get_db();
$minPaymentRow    = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'min_payment_amount' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$minPaymentAmount = $minPaymentRow ? (float)$minPaymentRow['setting_value'] : 400;
$todayDate        = date('Y-m-d');

// ── Branch manager: resolve their fixed branch ────────────────
$currentUser     = auth_user();
$isBranchManager = ($currentUser['role'] === 'branch_manager');
$managerBranch   = null;

if ($isBranchManager) {
$bmStmt = $db->prepare("
    SELECT ub.branch_id 
    FROM user_branch ub
    WHERE ub.user_id = ? 
    LIMIT 1
");
$bmStmt->execute([$currentUser['id']]);
$managerBranchId = (int)($bmStmt->fetchColumn() ?: 0);
    if ($managerBranchId) {
        foreach (($branches ?? []) as $b) {
            if ((int)$b['id'] === $managerBranchId) { $managerBranch = $b; break; }
        }
    }
}

// ── Renewal: previous receipt's first_session (same-date guard)
$prevFirstSession = '';
if ($isRenewal && !empty($client['id'])) {
    $prevStmt = $db->prepare("SELECT first_session FROM receipts WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $prevStmt->execute([$client['id']]);
    $prevFirstSession = (string)($prevStmt->fetchColumn() ?: '');
}

// ── Server-computed correct renewal type ──────────────────────
$serverType      = $autoRenewalType ?: 'current_renewal';
$submittedType   = trim($_POST['renewal_type'] ?? '');
$preSelectedType = !empty($submittedType) ? $submittedType : '';
?>
<style>
:root {
    --bg:          #0f1117;
    --surface:     #181c27;
    --surface-2:   #1e2334;
    --border:      #2a3047;
    --border-focus:#4f7cff;
    --accent:      #4f7cff;
    --accent-dim:  #2a3f7a;
    --success:     #22c55e;
    --danger:      #ef4444;
    --warning:     #f59e0b;
    --text:        #e8eaf0;
    --text-muted:  #7a84a0;
    --text-label:  #a0a9c0;
    --radius:      10px;
    --transition:  0.2s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-size: 16px;
    font-weight: bold;
    color: #fff;
    font-family: 'Tajawal', sans-serif;
}
.receipt-page { max-width: 980px; margin: 0 auto; padding: 32px 20px 60px; }

/* ── Tab bar ── */
.tab-bar {
    display: flex;
    gap: 6px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 6px;
    margin-bottom: 28px;
}
.tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 0;
    border-radius: 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    transition: background .15s, color .15s, border-color .15s;
}
.tab-btn .tab-icon { font-size: 18px; }
.tab-btn .tab-badge {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 700;
}
.tab-btn.active {
    background: var(--surface-2);
    color: var(--text);
    border: 1px solid var(--border);
}
.tab-btn.active .tab-badge-new      { background: #0f2a1a; color: #22c55e; border: 1px solid #1a5c30; }
.tab-btn.active .tab-badge-renew    { background: #0a1a2a; color: #4f7cff; border: 1px solid #2a3f7a; }
.tab-btn.active .tab-badge-payment  { background: #2a1a00; color: #f59e0b; border: 1px solid #6b4800; }
.tab-btn.active .tab-badge-refund   { background: #2a1515; color: #ef4444; border: 1px solid #5a2020; }
.tab-btn.active .tab-badge-client   { background: #1a0a2a; color: #a78bfa; border: 1px solid #4a2a7a; }
.tab-btn:not(.active) .tab-badge    { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
.tab-btn:not(.active):hover         { background: var(--surface-2); color: var(--text-muted); }

/* ── Tab panels ── */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Shared section / form styles ── */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-direction: row;
    margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border);
}
.page-header h2 { font-size: 20px; font-weight: 700; }
.breadcrumb { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text-muted); font-family: 'Tajawal', sans-serif;
    font-size: 13px; cursor: pointer; text-decoration: none;
    transition: all var(--transition);
}
.btn-back:hover { background: var(--surface); color: var(--text); border-color: var(--accent); }

.alert { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
.alert-error   { background: #2a1515; border: 1px solid #5a2020; color: #fca5a5; }
.alert-success { background: #0f2a1a; border: 1px solid #1a5c30; color: #86efac; }
.alert-info    { background: #00b4d810; border: 1px solid #00b4d840; color: #00b4d8; }

.form-section {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; margin-bottom: 20px; overflow: hidden;
}
.section-header {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 22px; border-bottom: 1px solid var(--border);
    background: var(--surface-2);
}
.section-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--accent-dim); display: flex;
    align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;
}
.section-title { font-size: 14px; font-weight: 600; }
.section-body  { padding: 22px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 22px; }
.form-grid .full { grid-column: 1 / -1; }
@media (max-width: 640px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }
}

.form-field  { display: flex; flex-direction: column; gap: 7px; }
.form-label  { font-size: 12.5px; font-weight: 600; color: var(--text-label); letter-spacing: 0.3px; text-transform: uppercase; }
.form-label .req { color: var(--danger); margin-right: 3px; }

.form-control {
    width: 100%; padding: 10px 14px;
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: 'Tajawal', sans-serif; font-size: 14px;
    outline: none; transition: border-color var(--transition), box-shadow var(--transition);
    appearance: none;
}
.form-control:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(79,124,255,0.15); }
.form-control::placeholder { color: var(--text-muted); }
.form-control:disabled { opacity: 0.45; cursor: not-allowed; }
.form-control.field-invalid { border-color: var(--danger) !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.15) !important; }

select.form-control {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237a84a0' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: left 12px center; padding-left: 34px;
}

.phone-row { display: flex; gap: 8px; align-items: stretch; }
.phone-prefix {
    display: flex; align-items: center; justify-content: center;
    min-width: 68px; padding: 10px 12px;
    background: var(--accent-dim); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--accent);
    font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700;
    letter-spacing: 0.5px; flex-shrink: 0; white-space: nowrap;
}
.phone-row .form-control { flex: 1; }
.field-hint { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

.inline-error {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #2a1515;
    border: 1px solid #5a2020; border-radius: var(--radius);
    color: #fca5a5; font-size: 13px; margin-top: 8px;
}
.inline-error.visible { display: flex; }

.pay-warn {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #2a1a00;
    border: 1px solid #6b4800; border-radius: var(--radius);
    color: #fcd34d; font-size: 13px; margin-top: 8px;
}
.pay-warn.visible { display: flex; }

.no-plans-notice {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #1a1a2a;
    border: 1px solid #3a3a6a; border-radius: var(--radius);
    color: #a0a9ff; font-size: 13px; margin-top: 8px;
}
.no-plans-notice.visible { display: flex; }

.computed-field .form-control {
    background: rgba(79,124,255,0.05);
    border-color: var(--accent-dim);
    color: var(--accent);
    font-weight: 600;
}

.toggle-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius);
    cursor: pointer; user-select: none; transition: border-color var(--transition);
}
.toggle-row:hover { border-color: var(--accent); }
.toggle-row input[type="checkbox"] { display: none; }
.toggle-thumb {
    width: 38px; height: 20px; background: var(--border);
    border-radius: 999px; position: relative; flex-shrink: 0;
    transition: background var(--transition);
}
.toggle-thumb::after {
    content: ''; position: absolute; top: 3px; right: 3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: #fff; transition: transform var(--transition);
}
.toggle-row input:checked + .toggle-thumb { background: var(--accent); }
.toggle-row input:checked + .toggle-thumb::after { transform: translateX(-18px); }
.toggle-label { font-size: 13px; color: var(--text-muted); }

.branch-locked {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    background: rgba(79,124,255,0.07);
    border: 1px solid var(--accent-dim);
    border-radius: var(--radius);
    font-size: 14px; color: var(--text);
}
.branch-locked .branch-locked-name { font-weight: 700; color: var(--accent); }
.branch-locked .branch-locked-note { font-size: 11px; color: var(--text-muted); margin-right: auto; }

.form-actions { display: flex; gap: 12px; justify-content: flex-end; padding: 24px 0 0; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px; border-radius: var(--radius);
    font-family: 'Tajawal', sans-serif; font-size: 14px; font-weight: 600;
    cursor: pointer; border: none; transition: all var(--transition); text-decoration: none;
}
.btn-primary { background: var(--accent); color: #fff; box-shadow: 0 4px 20px rgba(79,124,255,0.35); }
.btn-primary:hover { background: #3a68e8; transform: translateY(-1px); }
.btn-secondary { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
.btn-secondary:hover { color: var(--text); border-color: var(--accent); }

/* ── Renewal type selector ── */
.renewal-type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 540px) { .renewal-type-selector { grid-template-columns: 1fr; } }
.rtype-option { position: relative; cursor: pointer; }
.rtype-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.rtype-card {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px; border: 2px solid var(--border);
    border-radius: 12px; background: var(--surface-2);
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; user-select: none;
}
.rtype-option input:checked ~ .rtype-card { border-color: var(--accent); background: rgba(79,124,255,0.07); box-shadow: 0 0 0 3px rgba(79,124,255,0.12); }
.rtype-option input:checked ~ .rtype-card.card-previous { border-color: var(--warning); background: rgba(245,158,11,0.07); box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
.rtype-card.card-invalid { border-color: var(--danger) !important; background: rgba(239,68,68,0.06) !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.12) !important; }
.rtype-icon { font-size: 26px; flex-shrink: 0; line-height: 1; }
.rtype-body { display: flex; flex-direction: column; gap: 3px; }
.rtype-label { font-size: 14px; font-weight: 700; color: var(--text); }
.rtype-hint { font-size: 11px; color: var(--text-muted); line-height: 1.4; }
.rtype-check {
    margin-right: auto; width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid var(--border); background: transparent;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    transition: border-color 0.2s, background 0.2s;
}
.rtype-option input:checked ~ .rtype-card .rtype-check { border-color: var(--accent); background: var(--accent); }
.rtype-option input:checked ~ .rtype-card.card-previous .rtype-check { border-color: var(--warning); background: var(--warning); }
.rtype-check::after { content: ''; display: none; width: 5px; height: 9px; border: 2px solid #fff; border-top: none; border-left: none; transform: rotate(45deg) translateY(-1px); }
.rtype-option input:checked ~ .rtype-card .rtype-check::after { display: block; }

.renewal-type-error {
    display: none; align-items: flex-start; gap: 10px;
    padding: 13px 16px; background: #2a1515; border: 1px solid #5a2020;
    border-radius: var(--radius); color: #fca5a5; font-size: 13px; line-height: 1.6; margin-top: 14px;
}
.renewal-type-error.visible { display: flex; }
.correct-answer-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; margin-top: 6px;
}
.correct-answer-pill.current  { background: rgba(79,124,255,0.15); color: var(--accent); border: 1px solid var(--accent); }
.correct-answer-pill.previous { background: rgba(245,158,11,0.15); color: var(--warning); border: 1px solid var(--warning); }

.eligibility-error {
    padding: 14px 18px; background: #2a1515; border: 1px solid #5a2020;
    border-radius: 14px; color: #fca5a5; font-size: 14px; line-height: 1.7; margin-bottom: 20px;
}

/* ── Receipt cards (payment + refund tabs) ── */
.receipt-pick { display: flex; flex-direction: column; gap: 12px; margin-top: 20px; }
.receipt-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden; cursor: pointer; transition: border-color .2s;
}
.receipt-card:hover  { border-color: var(--accent); }
.receipt-card.selected-pay    { border-color: var(--accent); }
.receipt-card.selected-refund { border-color: var(--danger); }
.receipt-card-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 6px; padding: 12px 18px;
    background: var(--surface-2); border-bottom: 1px solid var(--border);
}
.receipt-card-body {
    padding: 16px 18px; display: grid;
    grid-template-columns: repeat(3, 1fr); gap: 12px;
}
@media (max-width: 600px) { .receipt-card-body { grid-template-columns: 1fr 1fr; } }
.rc-item label { display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 2px; }
.rc-item span { font-size: 13px; font-weight: 600; }
.renewal-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.renewal-badge.new      { background: #0f2a1a; color: #34c789; border: 1px solid #1a5c30; }
.renewal-badge.current  { background: #0a1a2a; color: #00b4d8; border: 1px solid #1a3a4a; }
.renewal-badge.previous { background: #1a1a00; color: #fbbf24; border: 1px solid #3a3a00; }

/* payment inline form */
.payment-form { margin-top: 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.payment-form-header { padding: 14px 20px; background: var(--surface-2); border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; }
.payment-form-body { padding: 22px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .form-grid-3 { grid-template-columns: 1fr; } }

#pay-evidence-field  { display: none; flex-direction: column; gap: 6px; }
#pay-evidence-field.visible  { display: flex; }
#evidence-field      { display: none; }
#evidence-field.visible      { display: flex; }

/* ── Refund tab ── */
.refund-form-wrap {
    margin-top: 24px;
    background: var(--surface);
    border: 1px solid #5a2020;
    border-radius: 12px;
    overflow: hidden;
    display: none;
}
.refund-form-wrap.visible { display: block; }
.refund-form-header {
    padding: 14px 20px;
    background: #2a1515;
    border-bottom: 1px solid #5a2020;
    font-size: 14px; font-weight: 600; color: #fca5a5;
}
.refund-form-body { padding: 22px; }

#refund-evidence-field       { display: none; flex-direction: column; gap: 6px; margin-top: 16px; }
#refund-evidence-field.visible { display: flex; }

.badge-fully-paid {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;
    background: #0a2a1a; color: #34c789; border: 1px solid #1a5c30;
}

/* ── Client tab ── */
.client-success-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 22px;
    background: #0a2010; border: 1px solid #1a5c30;
    border-radius: 14px; margin-bottom: 20px;
}
.client-success-banner .csb-icon { font-size: 28px; flex-shrink: 0; }
.client-success-banner .csb-body { display: flex; flex-direction: column; gap: 2px; }
.client-success-banner .csb-title { font-size: 15px; font-weight: 700; color: #86efac; }
.client-success-banner .csb-sub   { font-size: 12px; color: var(--text-muted); }
.client-submit-btn {
    background: #7c3aed !important;
    box-shadow: 0 4px 20px rgba(124,58,237,0.35) !important;
}
.client-submit-btn:hover { background: #6d28d9 !important; }

/* ════════════════════════════════════════════════════════════════
   RESPONSIVE — tablet ≤768px and mobile ≤480px
════════════════════════════════════════════════════════════════ */

/* ── Tablet (≤768px) ── */
@media (max-width: 768px) {
    .receipt-page { padding: 20px 14px 48px; }

    .tab-bar { gap: 4px; padding: 5px; border-radius: 12px; }
    .tab-btn {
        flex-direction: column;
        gap: 3px;
        padding: 8px 4px;
        font-size: 10px;
        border-radius: 8px;
    }
    .tab-btn .tab-icon { font-size: 16px; }
    .tab-badge { font-size: 9px; padding: 1px 6px; }

.tab-btn > span:not(.tab-badge) { display: none; }
    .tab-btn .tab-icon { display: none; }

    .section-body { padding: 16px; }
    .section-header { padding: 12px 16px; }

    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }

    .form-grid-3 { grid-template-columns: 1fr; gap: 14px; }

    .receipt-card-body { grid-template-columns: 1fr 1fr; gap: 10px; }

    .renewal-type-selector { grid-template-columns: 1fr; }

    .form-actions { flex-direction: column; gap: 10px; }
    .form-actions .btn { width: 100%; justify-content: center; }

    .page-header { flex-wrap: wrap; gap: 10px; }
    .page-header h2 { font-size: 17px; }
}

/* ── Mobile (≤480px) ── */
@media (max-width: 480px) {
    .receipt-page { padding: 14px 10px 40px; }

    .tab-bar { padding: 4px; gap: 3px; }
    .tab-btn { padding: 9px 2px; font-size: 9px; }
    .tab-btn .tab-icon { display: none; }
.tab-btn > span:not(.tab-icon):not(.tab-badge) { display: block; font-size: 10px; }
    

    .form-section { border-radius: 12px; margin-bottom: 14px; }
    .section-body { padding: 14px; }
    .section-header { padding: 11px 14px; }
    .section-icon { width: 28px; height: 28px; font-size: 13px; border-radius: 7px; }
    .section-title { font-size: 13px; }

    .form-control { font-size: 15px; padding: 11px 12px; }
    .form-label { font-size: 11px; }
    .field-hint { font-size: 10px; }

    .phone-prefix { min-width: 52px; padding: 11px 8px; font-size: 12px; }

    .receipt-card-header { padding: 10px 12px; gap: 5px; }
    .receipt-card-body { padding: 12px; grid-template-columns: 1fr 1fr; gap: 8px; }
    .rc-item label { font-size: 10px; }
    .rc-item span { font-size: 11px; }
    .renewal-badge { font-size: 10px; padding: 2px 7px; }

    .rtype-card { padding: 13px; gap: 10px; }
    .rtype-icon { font-size: 22px; }
    .rtype-label { font-size: 13px; }
    .rtype-hint { font-size: 10px; }

    .payment-form-body { padding: 14px; }
    .refund-form-body { padding: 14px; }
    .payment-form-header,
    .refund-form-header { padding: 11px 14px; font-size: 13px; }

    .alert { padding: 11px 14px; font-size: 13px; }

    .section-body form[method="GET"] { flex-wrap: wrap; }
    .section-body form[method="GET"] .form-field { flex: 1 1 100%; }
    .section-body form[method="GET"] button { width: 100%; margin-top: 4px; }

    .btn { padding: 12px 16px; font-size: 14px; }
    .btn-back { padding: 7px 12px; font-size: 12px; }

    .branch-locked { flex-wrap: wrap; gap: 6px; padding: 10px 12px; }

    .toggle-row { padding: 11px 12px; }
    .toggle-label { font-size: 12px; }
}

</style>

<div class="receipt-page">

  <!-- ══════════════════════════════════════════════════════════
       Page header
  ══════════════════════════════════════════════════════════════ -->
  <div class="page-header">
    <div>
      <h2>🧾 إدارة الإيصالات</h2>
      <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <button type="button" class="btn-back" onclick="history.back()">← رجوع</button>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════
       Tab bar  (5 tabs)
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-bar" role="tablist">
    <button class="tab-btn" id="tab-btn-new"
            onclick="switchTab('new')" role="tab">
      ➕
      <span class="tab-badge tab-badge-new">جديد</span>
    </button>
    <button class="tab-btn" id="tab-btn-renew"
            onclick="switchTab('renew')" role="tab">
      🔄
      <span class="tab-badge tab-badge-renew">تجديد</span>
    </button>
    <button class="tab-btn" id="tab-btn-payment"
            onclick="switchTab('payment')" role="tab">
      💳
      <span class="tab-badge tab-badge-payment">دفعة</span>
    </button>
    <button class="tab-btn" id="tab-btn-refund"
            onclick="switchTab('refund')" role="tab">
      ↩️
      <span class="tab-badge tab-badge-refund">استرداد</span>
    </button>
    <button class="tab-btn" id="tab-btn-client"
            onclick="switchTab('client')" role="tab">
      👤
      <span class="tab-badge tab-badge-client">عميل</span>
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1: إيصال جديد
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-panel-new" role="tabpanel">
	<h1>ايصال جديد</h1>
    <form method="POST" action="<?= APP_URL ?>/receipt/create"
          enctype="multipart/form-data" id="newReceiptForm">
      <input type="hidden" name="renewal_type" value="new">
      <?php if (!empty($receipt['client_id'])): ?>
        <input type="hidden" name="client_id" value="<?= (int)$receipt['client_id'] ?>">
      <?php endif; ?>

      <!-- § 1 — بيانات العميل -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">👤</div>
          <span class="section-title">بيانات العميل</span>
        </div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">اسم العميل <span class="req">*</span></label>
              <input type="text" name="client_name" class="form-control new-client-name"
                     placeholder="الاسم الكامل (3 كلمات على الأقل)"
                     value="<?= htmlspecialchars($receipt['client_name'] ?? '') ?>" required>
              <span class="field-hint">يجب إدخال 3 كلمات على الأقل</span>
              <div class="inline-error new-name-error">❌ يجب أن يحتوي الاسم على 3 كلمات على الأقل.</div>
            </div>
            <div class="form-field">
              <label class="form-label">هاتف العميل <span class="req">*</span></label>
              <div class="phone-row">
                <span class="phone-prefix" id="new-phone-prefix-badge"><?= htmlspecialchars($receipt['country_code'] ?? '—') ?></span>
                <input type="hidden" name="country_code" class="new-country-code" value="<?= htmlspecialchars($receipt['country_code'] ?? '') ?>">
                <input type="hidden" name="full_phone"   class="new-full-phone"   value="<?= htmlspecialchars($receipt['phone'] ?? '') ?>">
                <input type="text" name="phone_local" class="form-control new-phone-local"
                       placeholder="رقم الهاتف بدون كود الدولة" inputmode="numeric" maxlength="11"
                       value="<?= htmlspecialchars($receipt['phone_local'] ?? '') ?>" required>
              </div>
              <span class="field-hint">كود الدولة يُحدَّد تلقائياً عند اختيار الفرع</span>
              <div class="inline-error new-phone-error">❌ <span class="new-phone-error-msg">رقم الهاتف غير صحيح.</span></div>
            </div>
            <div class="form-field full">
              <label class="form-label">البريد الإلكتروني</label>
              <input type="text" name="client_email" class="form-control new-client-email"
                     placeholder="example@gmail.com"
                     value="<?= htmlspecialchars($receipt['client_email'] ?? '') ?>">
              <span class="field-hint">اختياري — يجب أن ينتهي بـ @gmail.com</span>
              <div class="inline-error new-email-error">❌ يجب أن يكون البريد بصيغة name@gmail.com فقط.</div>
            </div>
            <div class="form-field">
              <label class="form-label">العمر</label>
              <input type="number" name="client_age" class="form-control" placeholder="مثال: 25" min="5" max="99"
                     value="<?= htmlspecialchars($receipt['age'] ?? '') ?>">
            </div>
            <div class="form-field">
              <label class="form-label">الجنس</label>
              <select name="client_gender" class="form-control">
                <option value="">— اختر —</option>
                <option value="male"   <?= ($receipt['gender'] ?? '') === 'ذكر'   ? 'selected' : '' ?>>ذكر</option>
                <option value="female" <?= ($receipt['gender'] ?? '') === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- § 2 — تفاصيل الاشتراك -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">📋</div>
          <span class="section-title">تفاصيل الاشتراك</span>
        </div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">الفرع <span class="req">*</span></label>
              <?php if ($isBranchManager && $managerBranch): ?>
                <input type="hidden" name="branch_id" id="new-branch"
                       value="<?= (int)$managerBranch['id'] ?>"
                       data-country-id="<?= (int)($managerBranch['country_id'] ?? 0) ?>"
                       data-country-code="<?= htmlspecialchars($managerBranch['country_code'] ?? '') ?>">
                <div class="branch-locked">
                  <span>🏢</span>
                  <span class="branch-locked-name"><?= htmlspecialchars($managerBranch['branch_name']) ?></span>
                  <span class="branch-locked-note">فرعك — غير قابل للتغيير</span>
                </div>
              <?php else: ?>
                <select name="branch_id" id="new-branch" class="form-control" required
                        onchange="newBranchChanged()">
                  <option value="">— اختر الفرع —</option>
                  <?php foreach (($branches ?? []) as $b): ?>
                    <option value="<?= $b['id'] ?>"
                            data-country-id="<?= (int)($b['country_id'] ?? 0) ?>"
                            data-country-code="<?= htmlspecialchars($b['country_code'] ?? '') ?>"
                            <?= ($receipt['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b['branch_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div class="form-field">
              <label class="form-label">الخطة / العرض <span class="req">*</span></label>
              <select name="plan_id" id="new-plan" class="form-control" required onchange="newPlanChanged()">
                <option value="">— اختر الفرع أولاً —</option>
              </select>
              <div class="no-plans-notice" id="new-no-plans-notice">ℹ️ لا توجد خطط لهذا الفرع.</div>
            </div>
            <div class="form-field">
              <label class="form-label">الكابتن</label>
              <select name="captain_id" id="new-captain" class="form-control">
                <option value="">— اختر الفرع أولاً —</option>
              </select>
            </div>
            <div class="form-field">
              <label class="form-label">المستوى</label>
              <select name="level" class="form-control">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                  <option value="<?= $i ?>" <?= ($receipt['level'] ?? 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- § 3 — الجلسات -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">📅</div>
          <span class="section-title">الجلسات</span>
        </div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">تاريخ أول جلسة <span class="req">*</span></label>
              <input type="date" name="first_session" id="new-start-date" class="form-control"
                     min="<?= $todayDate ?>"
                     value="<?= htmlspecialchars($receipt['first_session'] ?? '') ?>" required
                     onchange="newUpdateDates()">
              <span class="field-hint">لا يمكن اختيار تاريخ في الماضي</span>
            </div>
            <div class="form-field">
              <label class="form-label">وقت التمرين</label>
              <input type="time" name="exercise_time" id="new-exercise-time" class="form-control"
                     value="<?= htmlspecialchars($receipt['exercise_time'] ?? '') ?>"
                     onchange="newValidateTime()">
              <div class="inline-error" id="new-time-error">❌ <span id="new-time-error-msg">وقت خارج ساعات عمل الفرع.</span></div>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">تاريخ جلسة التجديد</label>
              <input type="text" name="renewal_session" id="new-renewal-date" class="form-control"
                     value="<?= htmlspecialchars($receipt['renewal_session'] ?? '') ?>" readonly>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">تاريخ آخر جلسة</label>
              <input type="text" name="last_session" id="new-last-date" class="form-control"
                     value="<?= htmlspecialchars($receipt['last_session'] ?? '') ?>" readonly>
            </div>
            <div class="form-field full">
              <label class="toggle-row">
                <input type="checkbox" name="double" id="new-double" onchange="newUpdateDates()">
                <span class="toggle-thumb"></span>
                <span class="toggle-label">مكثف (جلستان في اليوم)</span>
              </label>
            </div>
            <div class="inline-error full" id="new-day-error">
              ❌ هذا الفرع لا يعمل في اليوم المختار — أيام العمل:
              <span id="new-day-error-hint" style="font-weight:600;margin-right:4px;"></span>
            </div>
            <div class="inline-error full" id="new-past-date-error">
              ❌ لا يمكن اختيار تاريخ في الماضي.
            </div>
          </div>
        </div>
      </div>

      <!-- § 4 — الدفع -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">💳</div>
          <span class="section-title">الدفع</span>
        </div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">المبلغ المدفوع <span class="req">*</span></label>
              <input type="text" name="amount" id="new-paid-amount" class="form-control"
                     placeholder="0" value="<?= htmlspecialchars($receipt['amount'] ?? '0') ?>"
                     min="<?= $minPaymentAmount ?>" required onchange="newCalcRemaining()">
              <div class="pay-warn" id="new-pay-warn">
                ⚠️ الحد الأدنى للدفع هو <strong><?= number_format($minPaymentAmount, 0) ?></strong> جنيه.
              </div>
              <div class="inline-error" id="new-overpay-error">❌ المبلغ المدفوع أكبر من سعر الخطة.</div>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">المتبقي</label>
              <input type="number" name="remaining" id="new-remaining" class="form-control"
                     value="<?= htmlspecialchars($receipt['remaining'] ?? '0') ?>" min="0" readonly>
            </div>
            <div class="form-field">
              <label class="form-label">طريقة الدفع <span class="req">*</span></label>
              <select name="payment_method" id="new-payment-method" class="form-control" required
                      onchange="newToggleEvidence()">
                <option value="">— اختر —</option>
                <option value="cash">نقداً</option>
                <option value="instapay">InstaPay</option>
                <option value="vodafone_cash">Vodafone Cash</option>
                <option value="bank_transfer">تحويل بنكي</option>
              </select>
            </div>
            <div class="form-field" id="new-evidence-field">
              <label class="form-label">إثبات الدفع <span class="req">*</span></label>
              <input type="file" name="transaction_evidence" id="new-transaction-evidence"
                     class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/*">
              <span class="field-hint">صور فقط (JPG، PNG، GIF، WEBP)</span>
              <div class="inline-error" id="new-evidence-error">❌ يُسمح بالصور فقط.</div>
            </div>
            <div class="form-field full">
              <label class="form-label">ملاحظات</label>
              <input type="text" name="notes" class="form-control"
                     placeholder="أي ملاحظات إضافية..."
                     value="<?= htmlspecialchars($receipt['notes'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="history.back()">إلغاء</button>
        <button type="submit" class="btn btn-primary" id="new-submit-btn">➕ إنشاء الإيصال</button>
      </div>
    </form>

  </div><!-- /tab-panel-new -->

  <!-- ══════════════════════════════════════════════════════════
       TAB 2: تجديد
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-panel-renew" role="tabpanel">
	<h1>تجديد ايصال</h1>
    <div class="form-section">
      <div class="section-header">
        <div class="section-icon">🔍</div>
        <span class="section-title">البحث عن العميل</span>
      </div>
      <div class="section-body">
        <form method="GET" action="<?= APP_URL ?>/receipt/manage" style="display:flex;gap:10px;align-items:flex-end;">
          <input type="hidden" name="tab" value="renew">
          <div class="form-field" style="flex:1;">
<label class="form-label">ابحث برقم العضويه أو رقم الهاتف</label>
<input type="text" name="renew_search" class="form-control"
       placeholder="مثال: 4821 أو 01012345678"
       value="<?= htmlspecialchars($renewSearch ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
        </form>

        <?php if (!empty($renewSearch) && empty($renewClient) && empty($eligibilityError)): ?>
          <div class="alert alert-error" style="margin-top:12px;">⚠️ لم يتم العثور على عميل.</div>
        <?php endif; ?>
        <?php if (!empty($eligibilityError)): ?>
          <div class="eligibility-error" style="margin-top:14px;">❌ <?= htmlspecialchars($eligibilityError) ?></div>
        <?php endif; ?>
        <?php if (!empty($renewClient)): ?>
          <div style="margin-top:16px;padding:14px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);display:flex;gap:24px;flex-wrap:wrap;">
            <div><div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">الاسم</div><div style="font-weight:700;"><?= htmlspecialchars($renewClient['client_name']) ?></div></div>
            <div><div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">الهاتف</div><div style="font-weight:700;"><?= htmlspecialchars($renewClient['phone']) ?></div></div>
            <?php if (!empty($renewClient['age'])): ?><div><div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">العمر</div><div style="font-weight:700;"><?= htmlspecialchars($renewClient['age']) ?></div></div><?php endif; ?>
            <div style="margin-right:auto;align-self:center;"><span style="background:#0f2a1a;border:1px solid #1a5c30;color:#86efac;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;">✅ تم العثور على العميل</span></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($renewClient) && empty($eligibilityError)): ?>
    <form method="POST" action="<?= APP_URL ?>/receipt/renew"
          enctype="multipart/form-data" id="renewReceiptForm">
      <input type="hidden" name="client_id" value="<?= (int)$renewClient['id'] ?>">

      <div class="form-section">
        <div class="section-header"><div class="section-icon">🔄</div><span class="section-title">نوع التجديد <span style="color:var(--danger);margin-right:4px;">*</span></span></div>
        <div class="section-body">
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;line-height:1.7;">
            اختر نوع التجديد بناءً على تاريخ آخر جلسة للعميل.
            <?php if (!empty($prevLastSession)): ?>
              <br><span style="color:var(--text-label);">📅 آخر جلسة: <strong style="color:var(--text);"><?= htmlspecialchars($prevLastSession) ?></strong></span>
            <?php endif; ?>
          </p>
          <div class="renewal-type-selector" role="radiogroup">
            <label class="rtype-option">
              <input type="radio" name="renewal_type" value="current_renewal" id="ren-rt-current"
                     <?= ($preSelectedType === 'current_renewal') ? 'checked' : '' ?> required>
              <div class="rtype-card card-current" id="ren-card-current">
                <span class="rtype-icon">🔁</span>
                <span class="rtype-body">
                  <span class="rtype-label">تجديد حالي</span>
                  <span class="rtype-hint">آخر جلسة في نفس الشهر الحالي وقبل يوم 21</span>
                </span>
                <span class="rtype-check"></span>
              </div>
            </label>
            <label class="rtype-option">
              <input type="radio" name="renewal_type" value="previous_renewal" id="ren-rt-previous"
                     <?= ($preSelectedType === 'previous_renewal') ? 'checked' : '' ?>>
              <div class="rtype-card card-previous" id="ren-card-previous">
                <span class="rtype-icon">⏪</span>
                <span class="rtype-body">
                  <span class="rtype-label">تجديد سابق</span>
                  <span class="rtype-hint">آخر جلسة بعد يوم 21 من الشهر السابق أو الحالي</span>
                </span>
                <span class="rtype-check"></span>
              </div>
            </label>
          </div>
          <div class="renewal-type-error" id="ren-type-mismatch-error"><span>⚠️</span><span id="ren-type-mismatch-msg"></span></div>
          <div class="renewal-type-error" id="ren-type-required-error" style="margin-top:8px;"><span>❌</span><span>يجب اختيار نوع التجديد قبل المتابعة.</span></div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-header"><div class="section-icon">👤</div><span class="section-title">بيانات العميل</span></div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">اسم العميل <span class="req">*</span></label>
              <input type="text" name="client_name" class="form-control ren-client-name"
                     value="<?= htmlspecialchars($renewClient['client_name'] ?? '') ?>" required>
              <div class="inline-error ren-name-error">❌ يجب أن يحتوي الاسم على 3 كلمات على الأقل.</div>
            </div>
            <div class="form-field">
              <label class="form-label">هاتف العميل <span class="req">*</span></label>
              <div class="phone-row">
                <span class="phone-prefix" id="ren-phone-prefix-badge"><?= htmlspecialchars($renewClient['country_code'] ?? '—') ?></span>
                <input type="hidden" name="country_code" class="ren-country-code" value="<?= htmlspecialchars($renewClient['country_code'] ?? '') ?>">
                <input type="hidden" name="full_phone"   class="ren-full-phone"   value="<?= htmlspecialchars($renewClient['phone'] ?? '') ?>">
                <input type="text" name="phone_local" class="form-control ren-phone-local"
                       inputmode="numeric" maxlength="11"
                       value="<?= htmlspecialchars($renewClient['phone_local'] ?? '') ?>" required>
              </div>
              <div class="inline-error ren-phone-error">❌ <span class="ren-phone-error-msg">رقم الهاتف غير صحيح.</span></div>
            </div>
            <div class="form-field full">
              <label class="form-label">البريد الإلكتروني</label>
              <input type="text" name="client_email" class="form-control ren-client-email"
                     placeholder="example@gmail.com"
                     value="<?= htmlspecialchars($renewClient['email'] ?? '') ?>">
              <div class="inline-error ren-email-error">❌ يجب أن يكون البريد بصيغة name@gmail.com فقط.</div>
            </div>
            <div class="form-field">
              <label class="form-label">العمر</label>
              <input type="number" name="client_age" class="form-control" min="5" max="99"
                     value="<?= htmlspecialchars($renewClient['age'] ?? '') ?>">
            </div>
            <div class="form-field">
              <label class="form-label">الجنس</label>
              <select name="client_gender" class="form-control">
                <option value="">— اختر —</option>
                <option value="male"   <?= ($renewClient['gender'] ?? '') === 'ذكر'   ? 'selected' : '' ?>>ذكر</option>
                <option value="female" <?= ($renewClient['gender'] ?? '') === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-header"><div class="section-icon">📋</div><span class="section-title">تفاصيل الاشتراك</span></div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">الفرع <span class="req">*</span></label>
              <?php if ($isBranchManager && $managerBranch): ?>
                <input type="hidden" name="branch_id" id="ren-branch"
                       value="<?= (int)$managerBranch['id'] ?>"
                       data-country-id="<?= (int)($managerBranch['country_id'] ?? 0) ?>"
                       data-country-code="<?= htmlspecialchars($managerBranch['country_code'] ?? '') ?>">
                <div class="branch-locked">
                  <span>🏢</span>
                  <span class="branch-locked-name"><?= htmlspecialchars($managerBranch['branch_name']) ?></span>
                  <span class="branch-locked-note">فرعك — غير قابل للتغيير</span>
                </div>
              <?php else: ?>
                <select name="branch_id" id="ren-branch" class="form-control" required onchange="renBranchChanged()">
                  <option value="">— اختر الفرع —</option>
                  <?php foreach (($branches ?? []) as $b): ?>
                    <option value="<?= $b['id'] ?>"
                            data-country-id="<?= (int)($b['country_id'] ?? 0) ?>"
                            data-country-code="<?= htmlspecialchars($b['country_code'] ?? '') ?>"
                            <?= ($renewClient['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b['branch_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div class="form-field">
              <label class="form-label">الخطة / العرض <span class="req">*</span></label>
              <select name="plan_id" id="ren-plan" class="form-control" required onchange="renPlanChanged()">
                <option value="">— اختر الفرع أولاً —</option>
              </select>
              <div class="no-plans-notice" id="ren-no-plans-notice">ℹ️ لا توجد خطط لهذا الفرع.</div>
            </div>
            <div class="form-field">
              <label class="form-label">الكابتن</label>
              <select name="captain_id" id="ren-captain" class="form-control">
                <option value="">— اختر الفرع أولاً —</option>
              </select>
            </div>
            <div class="form-field">
              <label class="form-label">المستوى</label>
              <select name="level" class="form-control">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                  <option value="<?= $i ?>" <?= ($renewClient['level'] ?? 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-header"><div class="section-icon">📅</div><span class="section-title">الجلسات</span></div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">تاريخ أول جلسة <span class="req">*</span></label>
              <input type="date" name="first_session" id="ren-start-date" class="form-control"
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required onchange="renUpdateDates()">
              <span class="field-hint">يجب أن يكون تاريخاً مستقبلياً ومختلفاً عن الإيصال السابق</span>
            </div>
            <div class="form-field">
              <label class="form-label">وقت التمرين</label>
              <input type="time" name="exercise_time" id="ren-exercise-time" class="form-control" onchange="renValidateTime()">
              <div class="inline-error" id="ren-time-error">❌ <span id="ren-time-error-msg">وقت خارج ساعات عمل الفرع.</span></div>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">تاريخ جلسة التجديد</label>
              <input type="text" name="renewal_session" id="ren-renewal-date" class="form-control" readonly>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">تاريخ آخر جلسة</label>
              <input type="text" name="last_session" id="ren-last-date" class="form-control" readonly>
            </div>
            <div class="form-field full">
              <label class="toggle-row">
                <input type="checkbox" name="double" id="ren-double" onchange="renUpdateDates()">
                <span class="toggle-thumb"></span>
                <span class="toggle-label">مكثف (جلستان في اليوم)</span>
              </label>
            </div>
            <div class="inline-error full" id="ren-day-error">❌ هذا الفرع لا يعمل في اليوم المختار — أيام العمل: <span id="ren-day-error-hint" style="font-weight:600;margin-right:4px;"></span></div>
            <div class="inline-error full" id="ren-past-date-error">❌ يجب اختيار تاريخ مستقبلي.</div>
            <div class="inline-error full" id="ren-today-error" style="display:none;">❌ لا يمكن إنشاء تجديد بتاريخ اليوم.</div>
            <div class="inline-error full" id="ren-same-date-error" style="display:none;">❌ <span id="ren-same-date-msg"></span></div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-header"><div class="section-icon">💳</div><span class="section-title">الدفع</span></div>
        <div class="section-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">المبلغ المدفوع <span class="req">*</span></label>
              <input type="text" name="amount" id="ren-paid-amount" class="form-control"
                     placeholder="0" min="<?= $minPaymentAmount ?>" required onchange="renCalcRemaining()">
              <div class="pay-warn" id="ren-pay-warn">⚠️ الحد الأدنى للدفع هو <strong><?= number_format($minPaymentAmount, 0) ?></strong> جنيه.</div>
              <div class="inline-error" id="ren-overpay-error">❌ المبلغ المدفوع أكبر من سعر الخطة.</div>
            </div>
            <div class="form-field computed-field">
              <label class="form-label">المتبقي</label>
              <input type="number" name="remaining" id="ren-remaining" class="form-control" value="0" min="0" readonly>
            </div>
            <div class="form-field">
              <label class="form-label">طريقة الدفع <span class="req">*</span></label>
              <select name="payment_method" id="ren-payment-method" class="form-control" required onchange="renToggleEvidence()">
                <option value="">— اختر —</option>
                <option value="cash">نقداً</option>
                <option value="instapay">InstaPay</option>
                <option value="vodafone_cash">Vodafone Cash</option>
                <option value="bank_transfer">تحويل بنكي</option>
              </select>
            </div>
            <div class="form-field" id="ren-evidence-field">
              <label class="form-label">إثبات الدفع <span class="req">*</span></label>
              <input type="file" name="transaction_evidence" id="ren-transaction-evidence"
                     class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/*">
              <span class="field-hint">صور فقط (JPG، PNG، GIF، WEBP)</span>
              <div class="inline-error" id="ren-evidence-error">❌ يُسمح بالصور فقط.</div>
            </div>
            <div class="form-field full">
              <label class="form-label">ملاحظات</label>
              <input type="text" name="notes" class="form-control" placeholder="أي ملاحظات إضافية...">
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="history.back()">إلغاء</button>
        <button type="submit" class="btn btn-primary" id="ren-submit-btn">🔄 إنشاء إيصال التجديد</button>
      </div>
    </form>
    <?php endif; ?>

  </div><!-- /tab-panel-renew -->

  <!-- ══════════════════════════════════════════════════════════
       TAB 3: إضافة دفعة
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-panel-payment" role="tabpanel">
	<h1>تكمله ايصال</h1>
    <div class="form-section">
      <div class="section-header"><div class="section-icon">🔍</div><span class="section-title">البحث عن العميل</span></div>
      <div class="section-body">
        <form method="GET" action="<?= APP_URL ?>/receipt/manage" style="display:flex;gap:10px;align-items:flex-end;">
          <input type="hidden" name="tab" value="payment">
          <div class="form-field" style="flex:1;">
            <label class="form-label">ابحث بالاسم، رقم الهاتف، أو رقم الإيصال</label>
            <input type="text" name="pay_search" class="form-control"
                   placeholder="مثال: أحمد أو 01012345678 أو #1234"
                   value="<?= htmlspecialchars($paySearch) ?>">
          </div>
          <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
        </form>

        <?php if (!empty($paySearch) && empty($payClient)): ?>
          <div class="alert alert-error" style="margin-top:12px;">⚠️ لم يتم العثور على عميل.</div>
        <?php endif; ?>
        <?php if (!empty($payClient) && empty($payReceipts)): ?>
          <div class="alert alert-info" style="margin-top:12px;">ℹ️ لا توجد إيصالات غير مكتملة لهذا العميل.</div>
        <?php endif; ?>

        <?php if (!empty($payClient)): ?>
          <div style="margin-top:16px;padding:14px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);display:flex;gap:24px;flex-wrap:wrap;">
            <div><div style="font-size:11px;color:var(--text-muted);">الاسم</div><div style="font-weight:700;"><?= htmlspecialchars($payClient['client_name']) ?></div></div>
            <div><div style="font-size:11px;color:var(--text-muted);">الهاتف</div><div style="font-weight:700;"><?= htmlspecialchars($payClient['phone'] ?? '—') ?></div></div>
            <?php if (!empty($payClient['age'])): ?>
              <div><div style="font-size:11px;color:var(--text-muted);">العمر</div><div style="font-weight:700;"><?= htmlspecialchars($payClient['age']) ?></div></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($payReceipts)): ?>
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
      اختر الإيصال الذي تريد إضافة دفعة عليه: (يُعرض فقط الإيصالات غير المكتملة)
    </div>

    <div class="receipt-pick">
      <?php
      $rtMap = [
          'new'              => ['label' => 'جديد',        'class' => 'new'],
          'current_renewal'  => ['label' => 'تجديد حالي', 'class' => 'current'],
          'previous_renewal' => ['label' => 'تجديد سابق', 'class' => 'previous'],
      ];
      foreach ($payReceipts as $r):
          $planPrice = (float)($r['plan_price'] ?? 0);
          $totalPaid = (float)($r['total_paid'] ?? 0);
          $remaining = max(0, $planPrice - $totalPaid);
          $rtKey     = $r['renewal_type'] ?? 'new';
          $rtMeta    = $rtMap[$rtKey] ?? ['label' => $rtKey, 'class' => 'new'];
      ?>
      <div class="receipt-card"
           data-id="<?= $r['id'] ?>"
           onclick="paySelectReceipt(<?= $r['id'] ?>, <?= $remaining ?>, <?= $planPrice ?>)">
        <div class="receipt-card-header">
          <span style="font-weight:700;color:var(--text);">#<?= $r['id'] ?> — <?= htmlspecialchars($r['plan_name'] ?? '—') ?></span>
          <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></span>
          <span class="renewal-badge <?= $rtMeta['class'] ?>"><?= $rtMeta['label'] ?></span>
          <span style="font-size:11px;font-weight:700;color:#fbbf24;">غير مكتمل</span>
        </div>
        <div class="receipt-card-body">
          <div class="rc-item"><label>أول جلسة</label><span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span></div>
          <div class="rc-item"><label>آخر جلسة</label><span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span></div>
          <div class="rc-item"><label>سعر الخطة</label><span><?= number_format($planPrice, 0) ?></span></div>
          <div class="rc-item"><label>إجمالي المدفوع</label><span style="color:var(--success);"><?= number_format($totalPaid, 0) ?></span></div>
          <div class="rc-item"><label>المتبقي</label>
            <span style="color:<?= $remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= number_format($remaining, 0) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="<?= APP_URL ?>/receipt/payment"
          id="paymentAddForm" style="display:none;" enctype="multipart/form-data">
      <input type="hidden" name="receipt_id" id="pay-selected-receipt-id">
      <input type="hidden" name="pay_search" value="<?= htmlspecialchars($paySearch) ?>">

      <div class="payment-form" style="margin-top:24px;">
        <div class="payment-form-header">💳 تفاصيل الدفعة</div>
        <div class="payment-form-body">
          <div class="form-grid-3">
            <div class="form-field">
              <label class="form-label">المبلغ <span style="color:var(--danger);">*</span></label>
              <input type="text" name="amount" id="pay-amount" class="form-control" placeholder="0" min="1" step="0.01" required oninput="payValidateAmount()">
              <span class="field-hint">المتبقي الحالي: <strong id="pay-current-remaining">—</strong></span>
              <div class="inline-error" id="pay-overpay-error">❌ المبلغ أكبر من المتبقي على هذا الإيصال.</div>
            </div>
            <div class="form-field">
              <label class="form-label">طريقة الدفع <span style="color:var(--danger);">*</span></label>
              <select name="payment_method" class="form-control" required onchange="payToggleEvidence(this.value)">
                <option value="">— اختر —</option>
                <option value="cash">نقداً</option>
                <option value="instapay">InstaPay</option>
                <option value="vodafone_cash">Vodafone Cash</option>
                <option value="bank_transfer">تحويل بنكي</option>
              </select>
            </div>
            <div class="form-field">
              <label class="form-label">ملاحظات</label>
              <input type="text" name="notes" class="form-control" placeholder="اختياري...">
            </div>
          </div>
          <div style="margin-top:16px;">
            <div class="form-field" id="pay-evidence-field">
              <label class="form-label">إثبات الدفع <span style="color:var(--danger);">*</span></label>
              <input type="file" name="transaction_evidence" id="pay-evidence" class="form-control" accept="image/*,application/pdf">
              <span class="field-hint">صورة أو ملف PDF</span>
            </div>
          </div>
          <div style="margin-top:18px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">💾 تسجيل الدفعة</button>
            <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('paymentAddForm').style.display='none'">إلغاء</button>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>

  </div><!-- /tab-panel-payment -->

  <!-- ══════════════════════════════════════════════════════════
       TAB 4: استرداد
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-panel-refund" role="tabpanel">
	<h1>استرداد</h1>
    <div class="form-section">
      <div class="section-header">
        <div class="section-icon">🔍</div>
        <span class="section-title">البحث عن العميل</span>
      </div>
      <div class="section-body">
        <form method="GET" action="<?= APP_URL ?>/receipt/manage" style="display:flex;gap:10px;align-items:flex-end;">
          <input type="hidden" name="tab" value="refund">
          <div class="form-field" style="flex:1;">
            <label class="form-label">ابحث بالاسم، رقم الهاتف (مع أو بدون كود الدولة)، أو رقم الإيصال</label>
            <input type="text" name="refund_search" class="form-control"
                   placeholder="مثال: أحمد أو 01012345678 أو 1012345678 أو #1234"
                   value="<?= htmlspecialchars($refundSearch) ?>">
          </div>
          <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
        </form>

        <?php if (!empty($refundSearch) && empty($refundClient)): ?>
          <div class="alert alert-error" style="margin-top:12px;">⚠️ لم يتم العثور على عميل.</div>
        <?php elseif (!empty($refundClient) && empty($refundReceipts)): ?>
          <div class="alert alert-error" style="margin-top:12px;">⚠️ لا توجد إيصالات مرتبطة بهذا العميل.</div>
        <?php endif; ?>

        <?php if (!empty($refundClient)): ?>
          <div style="margin-top:16px;padding:14px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
            <div>
              <div style="font-size:11px;color:var(--text-muted);">الاسم</div>
              <div style="font-weight:700;"><?= htmlspecialchars($refundClient['client_name']) ?></div>
            </div>
            <div>
              <div style="font-size:11px;color:var(--text-muted);">الهاتف</div>
              <div style="font-weight:700;"><?= htmlspecialchars($refundClient['phone'] ?? '—') ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($refundReceipts)): ?>
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
      اختر الإيصال الذي تريد استرداد مبلغ منه:
    </div>

    <div class="receipt-pick" id="refundReceiptPick">
      <?php
      $rtMap = [
          'new'              => ['label' => 'جديد',        'class' => 'new'],
          'current_renewal'  => ['label' => 'تجديد حالي', 'class' => 'current'],
          'previous_renewal' => ['label' => 'تجديد سابق', 'class' => 'previous'],
      ];
      foreach ($refundReceipts as $r):
          $planPrice = (float)($r['plan_price'] ?? 0);
          $grossPaid = (float)($r['gross_paid']  ?? $r['total_paid'] ?? 0);
          $refunded  = (float)($r['total_refunded'] ?? 0);
          $netPaid   = $grossPaid - $refunded;
          $rem       = $planPrice > 0 ? max(0, $planPrice - $netPaid) : 0;
          $maxRefund = max(0, $grossPaid - $refunded);

          $isFullyPaidNotCompleted = (
              ($r['receipt_status'] ?? '') === 'not_completed'
              && $planPrice > 0 && $grossPaid >= $planPrice
          );

          $rtKey  = $r['renewal_type'] ?? 'new';
          $rtMeta = $rtMap[$rtKey] ?? ['label' => $rtKey, 'class' => 'new'];

          $st = $r['receipt_status'] ?? '';
          $stColors = ['completed' => '#22c55e', 'not_completed' => '#fbbf24', 'pending' => '#818cf8'];
          $stLabels = ['completed' => 'مكتمل', 'not_completed' => 'غير مكتمل', 'pending' => 'معلّق'];
      ?>
      <div class="receipt-card"
           data-id="<?= $r['id'] ?>"
           data-max-refund="<?= $maxRefund ?>"
           onclick="refundSelectReceipt(<?= $r['id'] ?>, <?= $netPaid ?>, <?= $maxRefund ?>)">
        <div class="receipt-card-header">
          <span style="font-weight:700;color:var(--text);">
            #<?= htmlspecialchars($r['receipt_ref'] ?? $r['id']) ?>
            — <?= htmlspecialchars($r['client_name'] ?? '—') ?>
          </span>
          <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></span>
          <span class="renewal-badge <?= $rtMeta['class'] ?>"><?= $rtMeta['label'] ?></span>
          <span style="font-size:11px;font-weight:700;color:<?= $stColors[$st] ?? 'var(--text-muted)' ?>;">
            <?= $stLabels[$st] ?? $st ?>
          </span>
          <?php if ($isFullyPaidNotCompleted): ?>
            <span class="badge-fully-paid" title="الإيصال غير مكتمل لكن تم سداده بالكامل">💰 مدفوع بالكامل</span>
          <?php endif; ?>
        </div>
        <div class="receipt-card-body">
          <div class="rc-item"><label>الخطة</label><span><?= htmlspecialchars($r['plan_name'] ?? '—') ?></span></div>
          <div class="rc-item"><label>أول جلسة</label><span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span></div>
          <div class="rc-item"><label>آخر جلسة</label><span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span></div>
          <div class="rc-item">
            <label>إجمالي المدفوع (صافي)</label>
            <span style="color:var(--success);"><?= number_format($netPaid, 0) ?></span>
          </div>
          <div class="rc-item">
            <label>الحد الأقصى للاسترداد</label>
            <span style="color:var(--danger);"><?= number_format($maxRefund, 0) ?></span>
          </div>
          <div class="rc-item">
            <label>المتبقي للسداد</label>
            <span style="color:<?= $rem > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= number_format($rem, 0) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="refund-form-wrap" id="refundFormWrap">
      <form method="POST" action="<?= APP_URL ?>/receipt/refund"
            id="refundForm" enctype="multipart/form-data">
        <input type="hidden" name="receipt_id"  id="refund-selected-receipt-id">
        <input type="hidden" name="refund_search" value="<?= htmlspecialchars($refundSearch) ?>">
        <input type="hidden" name="tab" value="refund">

        <div class="refund-form-header">↩️ تفاصيل الاسترداد</div>
        <div class="refund-form-body">
          <div class="form-grid-3">
            <div class="form-field">
              <label class="form-label">المبلغ المُسترَد <span style="color:var(--danger);">*</span></label>
              <input type="text" name="amount" id="refund-amount"
                     class="form-control" placeholder="0" min="1" step="0.01" required oninput="refundValidateAmount()">
              <span class="field-hint">الحد الأقصى للاسترداد: <strong id="refund-max-display">—</strong></span>
              <div class="inline-error" id="refund-overamount-error">❌ المبلغ أكبر من الحد الأقصى المسموح للاسترداد.</div>
            </div>
            <div class="form-field">
              <label class="form-label">طريقة الاسترداد <span style="color:var(--danger);">*</span></label>
              <select name="payment_method" id="refund-method-select"
                      class="form-control" required
                      onchange="refundToggleEvidence(this.value)">
                <option value="">— اختر —</option>
                <option value="cash">نقداً</option>
                <option value="instapay">InstaPay</option>
                <option value="vodafone_cash">Vodafone Cash</option>
                <option value="bank_transfer">تحويل بنكي</option>
              </select>
            </div>
            <div class="form-field">
              <label class="form-label">سبب الاسترداد</label>
              <input type="text" name="notes" class="form-control" placeholder="اختياري...">
            </div>
          </div>

          <div class="form-field" id="refund-evidence-field">
            <label class="form-label">إثبات الاسترداد <span style="color:var(--danger);">*</span></label>
            <input type="file" name="transaction_evidence" id="refund-evidence"
                   class="form-control" accept="image/*,application/pdf">
            <span class="field-hint">صورة أو ملف PDF (مطلوب للاسترداد الإلكتروني)</span>
          </div>

          <div style="margin-top:18px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary"
                    style="background:var(--danger);box-shadow:0 4px 20px rgba(239,68,68,.35);">
              ↩️ تأكيد الاسترداد
            </button>
            <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('refundFormWrap').classList.remove('visible')">
              إلغاء
            </button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </div><!-- /tab-panel-refund -->

  <!-- ══════════════════════════════════════════════════════════
       TAB 5: عميل جديد
  ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-panel-client" role="tabpanel">
	<h1>اضافه عميل</h1>
    <?php if (!empty($_SESSION['flash_success_client'])): ?>
      <div class="client-success-banner">
        <span class="csb-icon">✅</span>
        <div class="csb-body">
          <span class="csb-title"><?= htmlspecialchars($_SESSION['flash_success_client']) ?></span>
          <span class="csb-sub">يمكنك إضافة عميل آخر أو الانتقال لتبويب آخر.</span>
        </div>
      </div>
      <?php unset($_SESSION['flash_success_client']); ?>
    <?php endif; ?>

    <?php if (!empty($clientErrors)): ?>
      <div class="alert alert-error">
        <?php foreach ($clientErrors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/client/create" id="newClientForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="redirect_tab" value="client">

      <!-- § 1 — بيانات العميل -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">👤</div>
          <span class="section-title">بيانات العميل الجديد</span>
        </div>
        <div class="section-body">
          <div class="form-grid">

            <div class="form-field">
              <label class="form-label">اسم العميل <span class="req">*</span></label>
              <input type="text" name="client_name" id="client-name-input" class="form-control"
                     placeholder="الاسم الكامل (3 كلمات على الأقل)"
                     value="<?= htmlspecialchars($newClientData['client_name'] ?? '') ?>" required>
              <span class="field-hint">يجب إدخال 3 كلمات على الأقل</span>
              <div class="inline-error" id="client-name-error">❌ يجب أن يحتوي الاسم على 3 كلمات على الأقل.</div>
            </div>

            <div class="form-field">
              <label class="form-label">رقم الهاتف <span class="req">*</span></label>
              <input type="text" name="phone" id="client-phone-input" class="form-control"
                     placeholder="مثال: 01012345678"
                     value="<?= htmlspecialchars($newClientData['phone'] ?? '') ?>" required
                     inputmode="numeric" maxlength="15">
            </div>

            <div class="form-field full">
              <label class="form-label">البريد الإلكتروني</label>
              <input type="text" name="email" id="client-email-input" class="form-control"
                     placeholder="example@gmail.com"
                     value="<?= htmlspecialchars($newClientData['email'] ?? '') ?>">
              <span class="field-hint">اختياري — يجب أن ينتهي بـ @gmail.com</span>
              <div class="inline-error" id="client-email-error">❌ يجب أن يكون البريد بصيغة name@gmail.com فقط.</div>
            </div>

            <div class="form-field">
              <label class="form-label">العمر</label>
              <input type="number" name="age" class="form-control"
                     placeholder="مثال: 25" min="5" max="99"
                     value="<?= htmlspecialchars($newClientData['age'] ?? '') ?>">
            </div>

            <div class="form-field">
              <label class="form-label">الجنس</label>
              <select name="gender" class="form-control">
                <option value="">— اختر —</option>
                <option value="male"  <?= ($newClientData['gender'] ?? '') === 'ذكر'  ? 'selected' : '' ?>>ذكر</option>
                <option value="female" <?= ($newClientData['gender'] ?? '') === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
              </select>
            </div>

          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="resetClientForm()">🔄 مسح</button>
        <button type="submit" class="btn btn-primary client-submit-btn" id="client-submit-btn">
          ➕ إضافة العميل
        </button>
      </div>
    </form>

  </div><!-- /tab-panel-client -->

</div><!-- /.receipt-page -->

<script>
// ════════════════════════════════════════════════════════════════
//  Shared data
// ════════════════════════════════════════════════════════════════
const BRANCH_META = {};
<?php foreach (($branches ?? []) as $b):
    $days = [];
    foreach (['working_days1','working_days2','working_days3'] as $slot) {
        if (!empty($b[$slot])) {
            foreach (array_map('trim', explode(',', $b[$slot])) as $d) {
                if ($d !== '') $days[] = $d;
            }
        }
    }
    $days     = array_values(array_unique($days));
    $timeFrom = isset($b['working_time_from']) ? substr($b['working_time_from'], 0, 5) : '';
    $timeTo   = isset($b['working_time_to'])   ? substr($b['working_time_to'],   0, 5) : '';
?>
BRANCH_META[<?= (int)$b['id'] ?>] = {
    country_id:        <?= (int)($b['country_id'] ?? 0) ?>,
    country_code:      <?= json_encode($b['country_code'] ?? '') ?>,
    days:              <?= json_encode($days) ?>,
    working_time_from: <?= json_encode($timeFrom) ?>,
    working_time_to:   <?= json_encode($timeTo) ?>
};
<?php endforeach; ?>

const CAPTAINS_BY_BRANCH = <?= json_encode($captainsByBranch ?? new stdClass()) ?>;
const PLANS_BY_COUNTRY   = {};
<?php foreach (($plans ?? []) as $p):
    $cid = (int)($p['country_id'] ?? 0);
    if (!$cid) continue;
?>
PLANS_BY_COUNTRY[<?= $cid ?>] = PLANS_BY_COUNTRY[<?= $cid ?>] || [];
PLANS_BY_COUNTRY[<?= $cid ?>].push({
    id: <?= (int)$p['id'] ?>,
    label: <?= json_encode($p['description']) ?>,
    price: <?= (float)$p['price'] ?>,
    sessions: <?= (int)$p['number_of_sessions'] ?>
});
<?php endforeach; ?>

const MIN_PAYMENT        = <?= (float)$minPaymentAmount ?>;
const TODAY              = <?= json_encode($todayDate) ?>;
const TOMORROW           = <?= json_encode(date('Y-m-d', strtotime('+1 day'))) ?>;
const IS_BRANCH_MANAGER  = <?= json_encode($isBranchManager) ?>;
const SAVED_PLAN_NEW     = <?= json_encode((string)($receipt['plan_id']    ?? '')) ?>;
const SAVED_CAPTAIN_NEW  = <?= json_encode((string)($receipt['captain_id'] ?? '')) ?>;
const SAVED_PLAN_REN     = <?= json_encode((string)($renewClient['plan_id']    ?? '')) ?>;
const SAVED_CAPTAIN_REN  = <?= json_encode((string)($renewClient['captain_id'] ?? '')) ?>;
const SERVER_RENEWAL_TYPE = <?= json_encode($serverType) ?>;
const PREV_FIRST_SESSION  = <?= json_encode($prevFirstSession) ?>;
const RENEWAL_TYPE_LABELS = { current_renewal: 'تجديد حالي 🔁', previous_renewal: 'تجديد سابق ⏪' };
const PHONE_RULES = {
    '+966': { regex: /^(05\d{8}|5\d{8})$/, hint: 'مثال: 0512345678 (9-10 أرقام)' },
    '+20':  { regex: /^(01[0-9]\d{8}|1[0-9]\d{8})$/, hint: 'مثال: 01012345678 (10-11 رقماً)' },
};

// ════════════════════════════════════════════════════════════════
//  Tab switching
// ════════════════════════════════════════════════════════════════

function to12h(time24) {
    if (!time24) return '';
    const [hStr, mStr] = time24.split(':');
    let h = parseInt(hStr, 10);
    const m   = mStr ?? '00';
    const period = h >= 12 ? 'م' : 'ص';
    h = h % 12 || 12;
    return `${h}:${m} ${period}`;
}


function switchTab(id) {
    ['new','renew','payment','refund','client'].forEach(t => {
        document.getElementById('tab-btn-'  + t).classList.toggle('active', t === id);
        document.getElementById('tab-panel-' + t).classList.toggle('active', t === id);
    });
    history.replaceState(null, '', '#' + id);
}

// ════════════════════════════════════════════════════════════════
//  Shared helpers
// ════════════════════════════════════════════════════════════════
function branchMeta(branchId)   { return branchId ? (BRANCH_META[branchId] || null) : null; }
function getBranchId(prefix)    { const el = document.getElementById(prefix + '-branch'); return el ? el.value : ''; }
function formatDate(date)       { const y=date.getFullYear(),m=String(date.getMonth()+1).padStart(2,'0'),d=String(date.getDate()).padStart(2,'0'); return `${y}-${m}-${d}`; }
function populatePlans(planSelId, noPlansId, savedPlanId, branchId) {
    const meta  = branchMeta(branchId), cid = meta ? meta.country_id : null;
    const plans = cid ? (PLANS_BY_COUNTRY[cid] || []) : [];
    const sel   = document.getElementById(planSelId), notice = document.getElementById(noPlansId);
    if (notice) notice.classList.toggle('visible', meta !== null && plans.length === 0);
    sel.innerHTML = plans.length ? '<option value="">— اختر الخطة —</option>' : '<option value="">— لا توجد خطط —</option>';
    plans.forEach(p => {
        const o = document.createElement('option');
        o.value = p.id; o.dataset.price = p.price; o.dataset.sessions = p.sessions;
        o.textContent = `${p.label} — ${p.price} (${p.sessions} جلسة)`;
        if (String(p.id) === savedPlanId) o.selected = true;
        sel.appendChild(o);
    });
}
function populateCaptains(capSelId, branchId, savedCaptainId) {
    const captains = CAPTAINS_BY_BRANCH[branchId] || [];
    const sel = document.getElementById(capSelId);
    sel.innerHTML = captains.length ? '<option value="">— اختر الكابتن —</option>' : '<option value="">— لا يوجد كباتن —</option>';
    captains.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.name;
        if (String(c.id) === savedCaptainId) o.selected = true;
        sel.appendChild(o);
    });
}
function getPlanOption(selId)   { return document.getElementById(selId)?.options[document.getElementById(selId)?.selectedIndex]; }
function getPlanPrice(selId)    { return parseFloat(getPlanOption(selId)?.dataset.price)    || 0; }
function getPlanSessions(selId) { return parseInt(getPlanOption(selId)?.dataset.sessions)   || 0; }

function pickActiveDays(startDay, days, total, isDouble) {
    const idx = days.indexOf(startDay);
    if (idx === -1) return [];
    const pairStart = idx % 2 === 0 ? idx : idx - 1;
    const pair1 = days.slice(pairStart, pairStart + 2);
    if (pair1[0] !== startDay) pair1.reverse();
    if (!isDouble) return total >= 8 ? pair1 : [startDay];
    if (total >= 8) { const p2 = pairStart === 0 ? 2 : 0; return [...new Set([...pair1, ...days.slice(p2, p2+2)])]; }
    return pair1;
}
function buildSessionDates(firstDate, days, total, isDouble) {
    const perVisit = isDouble ? 2 : 1, visits = Math.ceil(total / perVisit);
    const start = new Date(firstDate + 'T00:00:00');
    const startDay = start.toLocaleDateString('en-US', { weekday: 'long' });
    const active = pickActiveDays(startDay, days, total, isDouble);
    if (!active.length) return { renewal: '', last: '' };
    const dates = [], cursor = new Date(start); let s = 0;
    while (dates.length < visits && s < 365) {
        if (active.includes(cursor.toLocaleDateString('en-US', { weekday: 'long' }))) dates.push(formatDate(cursor));
        cursor.setDate(cursor.getDate() + 1); s++;
    }
    if (dates.length < 2) return { renewal: '', last: dates[0] ?? '' };
    return { renewal: dates[dates.length - 2], last: dates[dates.length - 1] };
}

// ════════════════════════════════════════════════════════════════
//  TAB 1 — إيصال جديد
// ════════════════════════════════════════════════════════════════
function newBranchChanged() {
    const bid = document.getElementById('new-branch')?.value;
    const meta = branchMeta(bid);
    const prefix = meta?.country_code || '—';
    document.getElementById('new-phone-prefix-badge').textContent = prefix;
    document.querySelector('.new-country-code').value = prefix !== '—' ? prefix : '';
    populatePlans('new-plan', 'new-no-plans-notice', SAVED_PLAN_NEW, bid);
    populateCaptains('new-captain', bid, SAVED_CAPTAIN_NEW);
    newUpdateDates();
}
function newPlanChanged()    { newCalcRemaining(); newUpdateDates(); }
function newCalcRemaining() {
    const price = getPlanPrice('new-plan');
    const paid  = parseFloat(document.getElementById('new-paid-amount').value) || 0;
    if (price > 0) document.getElementById('new-paid-amount').setAttribute('max', price);
    document.getElementById('new-remaining').value = price > 0 ? Math.max(price - paid, 0) : 0;

    const warn    = document.getElementById('new-pay-warn');
    const overpay = document.getElementById('new-overpay-error');
    const sub     = document.getElementById('new-submit-btn');

    const underMin = paid > 0 && paid < MIN_PAYMENT;
    const overMax  = price > 0 && paid > price;

    warn.classList.toggle('visible', underMin);
    overpay.classList.toggle('visible', overMax);
    sub.disabled = underMin || overMax;
}
function newUpdateDates() {
    const start = document.getElementById('new-start-date').value;
    document.getElementById('new-renewal-date').value = '';
    document.getElementById('new-last-date').value    = '';
    document.getElementById('new-day-error').classList.remove('visible');
    document.getElementById('new-past-date-error').classList.remove('visible');
    if (!start || !getBranchId('new')) return;
    if (start < TODAY) { document.getElementById('new-past-date-error').classList.add('visible'); document.getElementById('new-submit-btn').disabled = true; return; }
    const meta = branchMeta(getBranchId('new'));
    if (!meta?.days?.length) return;
    const day = new Date(start+'T00:00:00').toLocaleDateString('en-US',{weekday:'long'});
    if (!meta.days.includes(day)) {
        document.getElementById('new-day-error-hint').textContent = meta.days.join('، ');
        document.getElementById('new-day-error').classList.add('visible');
        document.getElementById('new-submit-btn').disabled = true; return;
    }
    document.getElementById('new-submit-btn').disabled = false;
    const total = getPlanSessions('new-plan'); if (!total) return;
    const result = buildSessionDates(start, meta.days, total, document.getElementById('new-double').checked);
    document.getElementById('new-renewal-date').value = result.renewal;
    document.getElementById('new-last-date').value    = result.last;
}


function newValidateTime() {
    const t = document.getElementById('new-exercise-time').value;
    const el = document.getElementById('new-time-error');
    
    el.classList.remove('visible'); 
    if (!t) return;
    
    const meta = branchMeta(getBranchId('new')); 
    if (!meta?.working_time_from || !meta?.working_time_to) return;

    // 1. Convert everything to minutes so we can compare actual numbers
    const currentMins = timeToMinutes(t);
    let fromMins = timeToMinutes(meta.working_time_from);
    let toMins = timeToMinutes(meta.working_time_to);

    // 2. Handle the midnight edge-case (00:00 becomes 1440 minutes)
    if (toMins === 0 && fromMins > 0) {
        toMins = 24 * 60; // 1440
    }

    // 3. Perform the correct range check
    let isValid = false;
    if (fromMins <= toMins) {
        // Standard daytime range
        isValid = (currentMins >= fromMins && currentMins <= toMins);
    } else {
        // Range that crosses midnight
        isValid = (currentMins >= fromMins || currentMins <= toMins);
    }

    // 4. Trigger error UI if the time is invalid
    if (!isValid) {
        document.getElementById('new-time-error-msg').textContent = 
            `يجب أن يكون بين ${to12h(meta.working_time_from)} و ${to12h(meta.working_time_to)}`;
        el.classList.add('visible');
    }
}

function newToggleEvidence() {
    const m = document.getElementById('new-payment-method').value;
    const f = document.getElementById('new-evidence-field'), i = document.getElementById('new-transaction-evidence');
    if (m && m !== 'cash') { f.classList.add('visible'); i.required = true; }
    else { f.classList.remove('visible'); i.required = false; i.value = ''; }
}
document.getElementById('newReceiptForm')?.addEventListener('submit', e => {
    const nameWords = document.querySelector('.new-client-name')?.value.trim().split(/\s+/).filter(w=>w.length>0) || [];
    if (nameWords.length < 3) { document.querySelector('.new-name-error').classList.add('visible'); e.preventDefault(); return; }
    document.querySelector('.new-name-error').classList.remove('visible');
    const price = getPlanPrice('new-plan');
    const paid = parseFloat(document.getElementById('new-paid-amount').value) || 0;
    if (paid > 0 && paid < MIN_PAYMENT) { e.preventDefault(); return; }
    if (price > 0 && paid > price) { document.getElementById('new-overpay-error').classList.add('visible'); e.preventDefault(); return; }
    const prefix = document.querySelector('.new-country-code').value;
    let local = document.querySelector('.new-phone-local').value.trim();
    if (prefix && local.startsWith('0')) local = local.slice(1);
    document.querySelector('.new-full-phone').value = prefix ? (prefix + local) : local;
});

// ════════════════════════════════════════════════════════════════
//  TAB 2 — تجديد
// ════════════════════════════════════════════════════════════════
function renBranchChanged() {
    const bid = document.getElementById('ren-branch')?.value, meta = branchMeta(bid);
    const prefix = meta?.country_code || '—';
    document.getElementById('ren-phone-prefix-badge').textContent = prefix;
    document.querySelector('.ren-country-code').value = prefix !== '—' ? prefix : '';
    populatePlans('ren-plan', 'ren-no-plans-notice', SAVED_PLAN_REN, bid);
    populateCaptains('ren-captain', bid, SAVED_CAPTAIN_REN);
    renUpdateDates();
}
function renPlanChanged() { renCalcRemaining(); renUpdateDates(); }
function renCalcRemaining() {
    const price = getPlanPrice('ren-plan'), paid = parseFloat(document.getElementById('ren-paid-amount').value) || 0;
    if (price > 0) document.getElementById('ren-paid-amount').setAttribute('max', price);
    document.getElementById('ren-remaining').value = price > 0 ? Math.max(price - paid, 0) : 0;

    const warn    = document.getElementById('ren-pay-warn');
    const overpay = document.getElementById('ren-overpay-error');
    const sub     = document.getElementById('ren-submit-btn');

    const underMin = paid > 0 && paid < MIN_PAYMENT;
    const overMax  = price > 0 && paid > price;

    warn.classList.toggle('visible', underMin);
    overpay.classList.toggle('visible', overMax);
    sub.disabled = underMin || overMax;
}
function renUpdateDates() {
    const start = document.getElementById('ren-start-date')?.value;
    document.getElementById('ren-renewal-date').value = '';
    document.getElementById('ren-last-date').value    = '';
    ['ren-day-error','ren-past-date-error'].forEach(id => document.getElementById(id).classList.remove('visible'));
    document.getElementById('ren-today-error').style.display    = 'none';
    document.getElementById('ren-same-date-error').style.display = 'none';
    if (!start || !getBranchId('ren')) return;
    if (start <= TODAY) {
        if (start === TODAY) document.getElementById('ren-today-error').style.display = 'flex';
        else document.getElementById('ren-past-date-error').classList.add('visible');
        document.getElementById('ren-submit-btn').disabled = true; return;
    }
    if (PREV_FIRST_SESSION && start === PREV_FIRST_SESSION) {
        document.getElementById('ren-same-date-msg').textContent = 'لا يمكن استخدام نفس تاريخ بداية الإيصال السابق (' + PREV_FIRST_SESSION + ').';
        document.getElementById('ren-same-date-error').style.display = 'flex';
        document.getElementById('ren-submit-btn').disabled = true; return;
    }
    const meta = branchMeta(getBranchId('ren')); if (!meta?.days?.length) return;
    const day = new Date(start+'T00:00:00').toLocaleDateString('en-US',{weekday:'long'});
    if (!meta.days.includes(day)) {
        document.getElementById('ren-day-error-hint').textContent = meta.days.join('، ');
        document.getElementById('ren-day-error').classList.add('visible');
        document.getElementById('ren-submit-btn').disabled = true; return;
    }
    document.getElementById('ren-submit-btn').disabled = false;
    const total = getPlanSessions('ren-plan'); if (!total) return;
    const result = buildSessionDates(start, meta.days, total, document.getElementById('ren-double').checked);
    document.getElementById('ren-renewal-date').value = result.renewal;
    document.getElementById('ren-last-date').value    = result.last;
}


function timeToMinutes(timeString) {
    if (!timeString || typeof timeString !== 'string') return 0;
    
    // Clean up strings that might have AM/PM attached to them
    let isPM = timeString.toUpperCase().includes('PM');
    let isAM = timeString.toUpperCase().includes('AM');
    
    // Extract just the numbers (e.g., "22:00:00" or "07:00 PM" -> ["22", "00"])
    const cleanTime = timeString.replace(/[^\d:]/g, '');
    const parts = cleanTime.split(':');
    
    let hours = Number(parts[0]) || 0;
    let minutes = Number(parts[1]) || 0;
    
    // If it was in 12-hour format with AM/PM text
    if (isPM && hours < 12) hours += 12;
    if (isAM && hours === 12) hours = 0;
    
    return hours * 60 + minutes;
}

function isTimeInRange(time, from, to) {
    const t = timeToMinutes(time);
    let f = timeToMinutes(from);
    let e = timeToMinutes(to);

    // If closing is midnight (00:00 or 24:00), normalize it to 1440 mins
    if (e === 0 && f > 0) e = 1440;

    // Handle standard ranges and midnight crossings
    if (f <= e) {
        return t >= f && t <= e;
    }
    return t >= f || t <= e;
}


function renValidateTime() {
    const t = document.getElementById('ren-exercise-time').value, el = document.getElementById('ren-time-error');
    el.classList.remove('visible'); if (!t) return;
    const meta = branchMeta(getBranchId('ren')); if (!meta?.working_time_from || !meta?.working_time_to) return;
    if (!isTimeInRange(t, meta.working_time_from, meta.working_time_to)) {
        document.getElementById('ren-time-error-msg').textContent = `يجب أن يكون بين ${to12h(meta.working_time_from)} و ${to12h(meta.working_time_to)}`;
      el.classList.add('visible');
    }
}

function renToggleEvidence() {
    const m = document.getElementById('ren-payment-method').value;
    const f = document.getElementById('ren-evidence-field'), i = document.getElementById('ren-transaction-evidence');
    if (m && m !== 'cash') { f.classList.add('visible'); i.required = true; }
    else { f.classList.remove('visible'); i.required = false; i.value = ''; }
}
function renValidateRenewalType() {
    const curr = document.getElementById('ren-rt-current'), prev = document.getElementById('ren-rt-previous');
    const mismatch = document.getElementById('ren-type-mismatch-error'), required = document.getElementById('ren-type-required-error');
    const cardC = document.getElementById('ren-card-current'), cardP = document.getElementById('ren-card-previous');
    mismatch.classList.remove('visible'); required.classList.remove('visible');
    [cardC, cardP].forEach(c => c && c.classList.remove('card-invalid'));
    const chosen = curr?.checked ? 'current_renewal' : prev?.checked ? 'previous_renewal' : null;
    if (!chosen) { required.classList.add('visible'); return false; }
    if (chosen === SERVER_RENEWAL_TYPE) return true;
    const wrongCard = chosen === 'current_renewal' ? cardC : cardP;
    if (wrongCard) wrongCard.classList.add('card-invalid');
    const pillClass = SERVER_RENEWAL_TYPE === 'current_renewal' ? 'current' : 'previous';
    document.getElementById('ren-type-mismatch-msg').innerHTML =
        `اخترت <strong>${RENEWAL_TYPE_LABELS[chosen]}</strong> لكن الصحيح هو:<br>` +
        `<span class="correct-answer-pill ${pillClass}">${RENEWAL_TYPE_LABELS[SERVER_RENEWAL_TYPE]}</span>`;
    mismatch.classList.add('visible'); return false;
}
document.getElementById('ren-rt-current')?.addEventListener('change',  renValidateRenewalType);
document.getElementById('ren-rt-previous')?.addEventListener('change', renValidateRenewalType);
document.getElementById('renewReceiptForm')?.addEventListener('submit', e => {
    const nameWords = document.querySelector('.ren-client-name')?.value.trim().split(/\s+/).filter(w=>w.length>0) || [];
    if (nameWords.length < 3) { document.querySelector('.ren-name-error').classList.add('visible'); e.preventDefault(); return; }
    if (!renValidateRenewalType()) { e.preventDefault(); return; }
    const paid = parseFloat(document.getElementById('ren-paid-amount').value) || 0;
    if (paid > 0 && paid < MIN_PAYMENT) { e.preventDefault(); return; }
    const price = getPlanPrice('ren-plan');
    if (price > 0 && paid > price) { document.getElementById('ren-overpay-error').classList.add('visible'); e.preventDefault(); return; }
    const prefix = document.querySelector('.ren-country-code').value;
    let local = document.querySelector('.ren-phone-local').value.trim();
    if (prefix && local.startsWith('0')) local = local.slice(1);
    document.querySelector('.ren-full-phone').value = prefix ? (prefix + local) : local;
});

// ════════════════════════════════════════════════════════════════
//  TAB 3 — إضافة دفعة
// ════════════════════════════════════════════════════════════════
let PAY_SELECTED_REMAINING = 0;

function paySelectReceipt(id, remaining, planPrice) {
    document.querySelectorAll('#tab-panel-payment .receipt-card').forEach(c => c.classList.remove('selected-pay'));
    document.querySelector('#tab-panel-payment .receipt-card[data-id="'+id+'"]').classList.add('selected-pay');
    document.getElementById('pay-selected-receipt-id').value = id;
    document.getElementById('pay-current-remaining').textContent = parseFloat(remaining).toLocaleString('ar-EG');
    PAY_SELECTED_REMAINING = parseFloat(remaining) || 0;
    document.getElementById('pay-amount').setAttribute('max', PAY_SELECTED_REMAINING);
    document.getElementById('pay-amount').value = remaining > 0 ? remaining : '';
    document.getElementById('pay-overpay-error').classList.remove('visible');
    const form = document.getElementById('paymentAddForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth' });
}
function payValidateAmount() {
    const amt = parseFloat(document.getElementById('pay-amount').value) || 0;
    const bad = amt > PAY_SELECTED_REMAINING;
    document.getElementById('pay-overpay-error').classList.toggle('visible', bad);
    return !bad;
}
function payToggleEvidence(method) {
    const f = document.getElementById('pay-evidence-field'), i = document.getElementById('pay-evidence');
    if (method && method !== 'cash') { f.classList.add('visible'); i.required = true; }
    else { f.classList.remove('visible'); i.required = false; i.value = ''; }
}
document.getElementById('paymentAddForm')?.addEventListener('submit', e => {
    if (!payValidateAmount()) e.preventDefault();
});

// ════════════════════════════════════════════════════════════════
//  TAB 4 — استرداد
// ════════════════════════════════════════════════════════════════
let REFUND_MAX_AMOUNT = 0;

function refundSelectReceipt(id, netPaid, maxRefund) {
    document.querySelectorAll('#tab-panel-refund .receipt-card').forEach(c => c.classList.remove('selected-refund'));
    document.querySelector('#tab-panel-refund .receipt-card[data-id="'+id+'"]').classList.add('selected-refund');
    document.getElementById('refund-selected-receipt-id').value = id;
    document.getElementById('refund-max-display').textContent   = parseFloat(maxRefund).toLocaleString('ar-EG');
    REFUND_MAX_AMOUNT = parseFloat(maxRefund) || 0;
    const amountInput = document.getElementById('refund-amount');
    amountInput.max   = REFUND_MAX_AMOUNT;
    amountInput.value = '';
    document.getElementById('refund-overamount-error').classList.remove('visible');
    document.getElementById('refund-method-select').value = '';
    refundToggleEvidence('');
    const wrap = document.getElementById('refundFormWrap');
    wrap.classList.add('visible');
    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function refundValidateAmount() {
    const amt = parseFloat(document.getElementById('refund-amount').value) || 0;
    const bad = amt > REFUND_MAX_AMOUNT;
    document.getElementById('refund-overamount-error').classList.toggle('visible', bad);
    return !bad;
}
function refundToggleEvidence(method) {
    const f = document.getElementById('refund-evidence-field'), i = document.getElementById('refund-evidence');
    if (method && method !== 'cash') { f.classList.add('visible'); i.required = true; }
    else { f.classList.remove('visible'); i.required = false; i.value = ''; }
}
document.getElementById('refundForm')?.addEventListener('submit', e => {
    if (!refundValidateAmount()) e.preventDefault();
});

// ════════════════════════════════════════════════════════════════
//  TAB 5 — عميل جديد
// ════════════════════════════════════════════════════════════════
document.getElementById('newClientForm')?.addEventListener('submit', e => {
    let valid = true;

    // Name: 3 words minimum
    const nameVal   = document.getElementById('client-name-input')?.value.trim() || '';
    const nameWords = nameVal.split(/\s+/).filter(w => w.length > 0);
    const nameErr   = document.getElementById('client-name-error');
    if (nameWords.length < 3) {
        nameErr.classList.add('visible');
        valid = false;
    } else {
        nameErr.classList.remove('visible');
    }

    // Email: optional but must be @gmail.com if provided
    const emailVal = document.getElementById('client-email-input')?.value.trim() || '';
    const emailErr = document.getElementById('client-email-error');
    if (emailVal && !/^[^\s@]+@gmail\.com$/i.test(emailVal)) {
        emailErr.classList.add('visible');
        valid = false;
    } else {
        emailErr.classList.remove('visible');
    }

    if (!valid) e.preventDefault();
});

function resetClientForm() {
    const form = document.getElementById('newClientForm');
    if (!form) return;
    form.reset();
    document.getElementById('client-name-error')?.classList.remove('visible');
    document.getElementById('client-email-error')?.classList.remove('visible');
}

// ════════════════════════════════════════════════════════════════
//  Init
// ════════════════════════════════════════════════════════════════
(function init() {
    const hash       = location.hash.replace('#','');
    const serverTab  = <?= json_encode($activeTab) ?>;
    const validTabs  = ['new','renew','payment','refund','client'];
    const initialTab = validTabs.includes(hash) ? hash : serverTab;
    switchTab(initialTab);

    if (IS_BRANCH_MANAGER || document.getElementById('new-branch')?.value) {
        newBranchChanged();
    }
    newToggleEvidence();
    newCalcRemaining();
    newUpdateDates();

    <?php if (!empty($renewClient) && empty($eligibilityError)): ?>
    if (IS_BRANCH_MANAGER || document.getElementById('ren-branch')?.value) {
        renBranchChanged();
    }
    renToggleEvidence();
    renCalcRemaining();
    renUpdateDates();
    <?php endif; ?>
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>