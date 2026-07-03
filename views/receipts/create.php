<?php
// views/receipts/create.php  (also used as edit.php with $isEdit = true, and renew.php with $isRenewal = true)
require ROOT . '/views/includes/layout_top.php';

$formTitle = $isEdit ? 'تعديل الإيصال' : 'ايصال جديد';
$action    = $isEdit
    ? APP_URL . '/receipt/edit?id=' . $receipt['id']
    : APP_URL . '/receipt/create';

$formTitle = $isRenewal ? 'تجديد ايصال' : 'ايصال جديد';
$db = get_db();
$minPaymentRow    = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'min_payment_amount' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$minPaymentAmount = $minPaymentRow ? (float)$minPaymentRow['setting_value'] : 400;

$todayDate = date('Y-m-d');

// For the renewal same-date guard in JS: pass the previous receipt's first_session
$prevFirstSession = '';
if (!empty($isRenewal) && !empty($client['id'])) {
    $prevStmt = $db->prepare("
        SELECT first_session FROM receipts
        WHERE client_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $prevStmt->execute([$client['id']]);
    $prevFirstSession = (string)($prevStmt->fetchColumn() ?: '');
}

// Auto renewal type label map
$renewalTypeLabels = [
    'current_renewal'  => ['label' => 'تجديد حالي',  'icon' => '🔁', 'color' => '#4f7cff'],
    'previous_renewal' => ['label' => 'تجديد سابق',  'icon' => '⏪', 'color' => '#f59e0b'],
];
$autoRenewalType = $autoRenewalType ?? ($receipt['renewal_type'] ?? '');

// ── Branch manager: resolve their fixed branch ──────────────────────────────
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
            if ((int)$b['id'] === $managerBranchId) {
                $managerBranch = $b;
                break;
            }
        }
    }
}
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
    font-family: 'Cairo', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    direction: rtl;
  }
  .receipt-page { max-width: 980px; margin: 0 auto; padding: 32px 20px 60px; }

  .page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border);
  }
  .page-header h1 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
  .breadcrumb { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

  .btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text-muted); font-family: 'Cairo', sans-serif;
    font-size: 13px; cursor: pointer; text-decoration: none;
    transition: all var(--transition);
  }
  .btn-back:hover { background: var(--surface); color: var(--text); border-color: var(--accent); }

  .alert { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
  .alert-error   { background: #2a1515; border: 1px solid #5a2020; color: #fca5a5; }
  .alert-success { background: #0f2a1a; border: 1px solid #1a5c30; color: #86efac; }

  .form-section {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; margin-bottom: 20px; overflow: hidden;
    animation: slideUp 0.35s ease both;
  }
  .form-section:nth-child(1){animation-delay:.05s}
  .form-section:nth-child(2){animation-delay:.10s}
  .form-section:nth-child(3){animation-delay:.15s}
  .form-section:nth-child(4){animation-delay:.20s}
  .form-section:nth-child(5){animation-delay:.25s}
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
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
    font-family: 'Cairo', sans-serif; font-size: 14px;
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
    font-family: 'Cairo', sans-serif; font-size: 13px; font-weight: 700;
    letter-spacing: 0.5px; flex-shrink: 0;
    transition: all var(--transition); white-space: nowrap;
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

  #evidence-field { display: none; }
  #evidence-field.visible { display: flex; }

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

  .computed-field .form-control {
    background: rgba(79,124,255,0.05);
    border-color: var(--accent-dim);
    color: var(--accent);
    font-weight: 600;
  }

  /* ── Branch manager locked-branch badge ── */
  .branch-locked {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    background: rgba(79,124,255,0.07);
    border: 1px solid var(--accent-dim);
    border-radius: var(--radius);
    font-size: 14px;
    color: var(--text);
  }
  .branch-locked .branch-locked-name {
    font-weight: 700;
    color: var(--accent);
  }
  .branch-locked .branch-locked-note {
    font-size: 11px;
    color: var(--text-muted);
    margin-right: auto;
  }

  .form-actions { display: flex; gap: 12px; justify-content: flex-end; padding: 24px 0 0; }
  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px; border-radius: var(--radius);
    font-family: 'Cairo', sans-serif; font-size: 14px; font-weight: 600;
    cursor: pointer; border: none; transition: all var(--transition); text-decoration: none;
  }
  .btn-primary {
    background: var(--accent); color: #fff;
    box-shadow: 0 4px 20px rgba(79,124,255,0.35);
  }
  .btn-primary:hover { background: #3a68e8; transform: translateY(-1px); box-shadow: 0 6px 28px rgba(79,124,255,0.45); }
  .btn-secondary { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
  .btn-secondary:hover { color: var(--text); border-color: var(--accent); }
  .btn-email {
    background: #0f2a1a; color: #86efac;
    border: 1px solid #1a5c30;
    box-shadow: 0 4px 16px rgba(34,197,94,0.2);
  }
  .btn-email:hover { background: #163d26; border-color: #22c55e; transform: translateY(-1px); }
  .btn-email:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

  /* ── Renewal type selector (employee-facing) ── */
  .renewal-type-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  @media (max-width: 540px) {
    .renewal-type-selector { grid-template-columns: 1fr; }
  }

  .rtype-option {
    position: relative;
    cursor: pointer;
  }
  .rtype-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }
  .rtype-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface-2);
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    user-select: none;
  }
  .rtype-option input:checked ~ .rtype-card {
    border-color: var(--accent);
    background: rgba(79, 124, 255, 0.07);
    box-shadow: 0 0 0 3px rgba(79,124,255,0.12);
  }
  .rtype-option input:checked ~ .rtype-card.card-previous {
    border-color: var(--warning);
    background: rgba(245, 158, 11, 0.07);
    box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
  }
  .rtype-card.card-invalid {
    border-color: var(--danger) !important;
    background: rgba(239,68,68,0.06) !important;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.12) !important;
  }
  .rtype-icon {
    font-size: 26px;
    flex-shrink: 0;
    line-height: 1;
  }
  .rtype-body { display: flex; flex-direction: column; gap: 3px; }
  .rtype-label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
  }
  .rtype-hint {
    font-size: 11px;
    color: var(--text-muted);
    line-height: 1.4;
  }
  .rtype-check {
    margin-right: auto;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--border);
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color 0.2s, background 0.2s;
  }
  .rtype-option input:checked ~ .rtype-card .rtype-check {
    border-color: var(--accent);
    background: var(--accent);
  }
  .rtype-option input:checked ~ .rtype-card.card-previous .rtype-check {
    border-color: var(--warning);
    background: var(--warning);
  }
  .rtype-check::after {
    content: '';
    display: none;
    width: 5px;
    height: 9px;
    border: 2px solid #fff;
    border-top: none;
    border-left: none;
    transform: rotate(45deg) translateY(-1px);
  }
  .rtype-option input:checked ~ .rtype-card .rtype-check::after { display: block; }

  .renewal-type-error {
    display: none;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 16px;
    background: #2a1515;
    border: 1px solid #5a2020;
    border-radius: var(--radius);
    color: #fca5a5;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 14px;
  }
  .renewal-type-error.visible { display: flex; }
  .renewal-type-error .rte-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }

  .correct-answer-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 6px;
  }
  .correct-answer-pill.current  { background: rgba(79,124,255,0.15); color: var(--accent); border: 1px solid var(--accent); }
  .correct-answer-pill.previous { background: rgba(245,158,11,0.15); color: var(--warning); border: 1px solid var(--warning); }

  .eligibility-error {
    padding: 14px 18px;
    background: #2a1515;
    border: 1px solid #5a2020;
    border-radius: 14px;
    color: #fca5a5;
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 20px;
  }

  /* ── Email modal overlay ── */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.7); z-index: 1000;
    align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
  }
  .modal-overlay.open { display: flex; }
  .modal-box {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 28px 32px; width: 100%; max-width: 460px;
    animation: slideUp 0.25s ease;
  }
  .modal-title {
    font-size: 17px; font-weight: 700; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
  }
  .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
  .modal-status {
    margin-top: 14px; padding: 10px 14px; border-radius: var(--radius);
    font-size: 13px; display: none;
  }
  .modal-status.success { display: block; background: #0f2a1a; border: 1px solid #1a5c30; color: #86efac; }
  .modal-status.error   { display: block; background: #2a1515; border: 1px solid #5a2020; color: #fca5a5; }
  .spinner {
    display: inline-block; width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff; border-radius: 50%;
    animation: spin 0.7s linear infinite; vertical-align: middle; margin-left: 6px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ═══════════════════════════════════════════════════════════
     CONFIRMATION POPUP STYLES
     ═══════════════════════════════════════════════════════════ */
  .confirm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.78);
    backdrop-filter: blur(7px);
    z-index: 2000;
    align-items: center; justify-content: center;
    padding: 20px;
  }
  .confirm-overlay.open { display: flex; }

  .confirm-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%; max-width: 560px;
    max-height: 90vh;
    display: flex; flex-direction: column;
    overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(79,124,255,0.1);
    animation: confirmPop 0.32s cubic-bezier(0.34,1.56,0.64,1) both;
  }
  @keyframes confirmPop {
    from { opacity: 0; transform: translateY(28px) scale(0.95); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
  }

  .confirm-header {
    display: flex; align-items: center; gap: 14px;
    padding: 22px 26px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, var(--surface-2) 0%, rgba(79,124,255,0.05) 100%);
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
  }
  .confirm-header::before {
    content: '';
    position: absolute; top: 0; right: 0;
    width: 160px; height: 100%;
    background: radial-gradient(ellipse at top right, rgba(79,124,255,0.12) 0%, transparent 70%);
    pointer-events: none;
  }
  .confirm-header-icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: var(--accent-dim);
    border: 1px solid rgba(79,124,255,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(79,124,255,0.2);
  }
  .confirm-header-text { flex: 1; }
  .confirm-header-title { font-size: 17px; font-weight: 700; line-height: 1.3; }
  .confirm-header-sub   { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

  .confirm-progress {
    display: flex; align-items: center; gap: 6px;
    padding: 10px 26px;
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
  }
  .confirm-step {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; color: var(--text-muted);
    padding: 4px 10px; border-radius: 999px;
    border: 1px solid var(--border);
    background: transparent;
    transition: all 0.2s;
  }
  .confirm-step.done {
    color: var(--success); border-color: rgba(34,197,94,0.3);
    background: rgba(34,197,94,0.08);
  }
  .confirm-step-sep { color: var(--border); font-size: 10px; }

  .confirm-body {
    padding: 20px 26px 24px;
    overflow-y: auto;
    flex: 1;
  }
  .confirm-body::-webkit-scrollbar { width: 5px; }
  .confirm-body::-webkit-scrollbar-track { background: transparent; }
  .confirm-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

  .confirm-group { margin-bottom: 18px; }
  .confirm-group:last-of-type { margin-bottom: 0; }

  .confirm-group-title {
    font-size: 10.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--text-muted);
    margin-bottom: 9px;
    display: flex; align-items: center; gap: 7px;
  }
  .confirm-group-title::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, var(--border), transparent);
  }

  .confirm-rows { display: flex; flex-direction: column; gap: 6px; }

  .confirm-row {
    display: flex; align-items: center;
    justify-content: space-between; gap: 14px;
    padding: 9px 13px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 9px;
    transition: border-color 0.15s, background 0.15s;
  }
  .confirm-row:hover {
    border-color: rgba(79,124,255,0.25);
    background: rgba(79,124,255,0.04);
  }
  .confirm-row-key {
    font-size: 12px; color: var(--text-muted);
    flex-shrink: 0; min-width: 100px;
  }
  .confirm-row-val {
    font-size: 13px; font-weight: 600; color: var(--text);
    text-align: left; word-break: break-all;
  }
  .confirm-row-val.accent  { color: var(--accent); }
  .confirm-row-val.success { color: var(--success); }
  .confirm-row-val.warning { color: var(--warning); }
  .confirm-row-val.empty   { color: var(--text-muted); font-weight: 400; font-style: italic; font-size: 12px; }

  .confirm-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
    margin: 4px 0;
  }

  .confirm-edit-hint {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 12px 14px; border-radius: 10px;
    background: rgba(79,124,255,0.06);
    border: 1px solid rgba(79,124,255,0.15);
    font-size: 12px; color: var(--text-muted);
    margin-top: 16px; line-height: 1.6;
  }
  .confirm-edit-hint .hint-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

  .confirm-footer {
    display: flex; gap: 10px; justify-content: space-between; align-items: center;
    padding: 16px 26px;
    border-top: 1px solid var(--border);
    background: var(--surface-2);
    flex-shrink: 0;
  }
  .confirm-footer-left {
    font-size: 11.5px; color: var(--text-muted);
    display: flex; align-items: center; gap: 6px;
  }

  .btn-confirm-edit {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
    font-family: 'Cairo', sans-serif;
  }
  .btn-confirm-edit:hover { color: var(--text); border-color: var(--accent); background: var(--surface-2); }

  .btn-confirm-submit {
    background: var(--accent); color: #fff;
    font-family: 'Cairo', sans-serif;
    box-shadow: 0 4px 20px rgba(79,124,255,0.4);
    position: relative; overflow: hidden;
  }
  .btn-confirm-submit::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 60%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 0.5s;
  }
  .btn-confirm-submit:hover::before { left: 150%; }
  .btn-confirm-submit:hover { background: #3a68e8; transform: translateY(-1px); box-shadow: 0 6px 28px rgba(79,124,255,0.5); }

  /* Badge pill for renewal type inside confirm */
  .rtype-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
  }
  .rtype-pill.current  { background: rgba(79,124,255,0.15); color: var(--accent); }
  .rtype-pill.previous { background: rgba(245,158,11,0.15);  color: var(--warning); }
</style>
<div class="receipt-page">

<?php if (!empty($isRenewal)): ?>
<!-- § 0 — Client Search (Renewal only) -->
<div class="form-section" style="margin-bottom:20px;">
  <div class="section-header">
    <div class="section-icon">🔍</div>
    <span class="section-title">البحث عن العميل</span>
  </div>
  <div class="section-body">
    <form method="GET" action="<?= APP_URL ?>/receipt/renew"
          style="display:flex;gap:10px;align-items:flex-end;">
      <div class="form-field" style="flex:1;">
        <label class="form-label">ابحث بالاسم، رقم الهاتف (مع أو بدون كود الدولة)، أو رقم الإيصال</label>
        <input type="text" name="search" class="form-control"
               placeholder="مثال: أحمد محمد أو 01012345678 أو 1012345678 أو #1234"
               value="<?= htmlspecialchars($search ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
    </form>

    <?php if (!empty($search) && empty($client) && empty($eligibilityError)): ?>
      <div class="alert alert-error" style="margin-top:12px;">
        ⚠️ لم يتم العثور على عميل بهذا الاسم أو الرقم.
      </div>
    <?php endif; ?>

    <?php if (!empty($client)): ?>
      <div style="margin-top:16px;padding:14px;background:var(--surface-2);
                  border:1px solid var(--border);border-radius:var(--radius);
                  display:flex;gap:24px;flex-wrap:wrap;">
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">الاسم</div>
          <div style="font-weight:700;"><?= htmlspecialchars($client['client_name']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">الهاتف</div>
          <div style="font-weight:700;"><?= htmlspecialchars($client['phone']) ?></div>
        </div>
        <?php if (!empty($client['age'])): ?>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">العمر</div>
          <div style="font-weight:700;"><?= htmlspecialchars($client['age']) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($client['gender'])): ?>
        <div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">الجنس</div>
          <div style="font-weight:700;"><?= htmlspecialchars($client['gender']) ?></div>
        </div>
        <?php endif; ?>
        <div style="margin-right:auto;align-self:center;">
          <span style="background:#0f2a1a;border:1px solid #1a5c30;color:#86efac;
                       padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;">
            ✅ تم العثور على العميل
          </span>
        </div>
      </div>
      <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
    <?php endif; ?>

    <?php if (!empty($eligibilityError)): ?>
      <div class="eligibility-error" style="margin-top:14px;">
        ❌ <?= htmlspecialchars($eligibilityError) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
  if (!empty($eligibilityError)):
?>
</div><!-- /.receipt-page -->
  <?php require ROOT . '/views/includes/layout_bottom.php'; return; ?>
<?php endif; ?>

<?php endif; // end isRenewal search section ?>

  <!-- Header -->
  <div class="page-header">
    <div>
      <h1><?= $formTitle ?></h1>
      <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <button type="button" class="btn-back" onclick="history.back()">← رجوع</button>
  </div>

  <!-- Alerts -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div>⚠️ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST"
        action="<?= $isRenewal ? APP_URL . '/receipt/renew' : $action ?>"
        enctype="multipart/form-data"
        id="receiptForm">

    <?php
      if (empty($isRenewal)):
    ?>
      <input type="hidden" name="renewal_type" value="new">
    <?php endif; ?>

    <?php if (!empty($receipt['client_id'])): ?>
      <input type="hidden" name="client_id" value="<?= (int)$receipt['client_id'] ?>">
    <?php endif; ?>

    <!-- § 0.5 — نوع التجديد (Renewal only) -->
    <?php if (!empty($isRenewal) && !empty($client)): ?>
    <div class="form-section">
      <div class="section-header">
        <div class="section-icon">🔄</div>
        <span class="section-title">نوع التجديد <span style="color:var(--danger);margin-right:4px;">*</span></span>
      </div>
      <div class="section-body">

        <?php
          $serverType    = $autoRenewalType ?: 'current_renewal';
          $submittedType = trim($_POST['renewal_type'] ?? '');
          $preSelected   = !empty($submittedType) ? $submittedType : '';
        ?>

        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;line-height:1.7;">
          اختر نوع التجديد بناءً على تاريخ آخر جلسة للعميل. النظام سيتحقق من اختيارك تلقائياً.
          <?php if (!empty($prevLastSession)): ?>
            <br>
            <span style="color:var(--text-label);">
              📅 آخر جلسة مسجّلة:
              <strong style="color:var(--text);"><?= htmlspecialchars($prevLastSession) ?></strong>
            </span>
          <?php endif; ?>
        </p>

        <div class="renewal-type-selector" role="radiogroup" aria-label="نوع التجديد">

          <label class="rtype-option">
            <input type="radio" name="renewal_type" value="current_renewal" id="rt_current"
                   <?= ($preSelected === 'current_renewal') ? 'checked' : '' ?>
                   required>
            <div class="rtype-card card-current" id="card_current">
              <span class="rtype-icon">🔁</span>
              <span class="rtype-body">
                <span class="rtype-label">تجديد حالي</span>
                <span class="rtype-hint">آخر جلسة في نفس الشهر الحالي وقبل يوم 21</span>
              </span>
              <span class="rtype-check"></span>
            </div>
          </label>

          <label class="rtype-option">
            <input type="radio" name="renewal_type" value="previous_renewal" id="rt_previous"
                   <?= ($preSelected === 'previous_renewal') ? 'checked' : '' ?>>
            <div class="rtype-card card-previous" id="card_previous">
              <span class="rtype-icon">⏪</span>
              <span class="rtype-body">
                <span class="rtype-label">تجديد سابق</span>
                <span class="rtype-hint">آخر جلسة بعد يوم 21 من الشهر السابق أو الحالي</span>
              </span>
              <span class="rtype-check"></span>
            </div>
          </label>

        </div>

        <div class="renewal-type-error" id="renewal_type_error">
          <span class="rte-icon">⚠️</span>
          <span id="renewal_type_error_msg">
            نوع التجديد المختار غير صحيح بناءً على تاريخ آخر جلسة.
          </span>
        </div>

        <div class="renewal-type-error" id="renewal_type_required_error" style="margin-top:8px;">
          <span class="rte-icon">❌</span>
          <span>يجب اختيار نوع التجديد قبل المتابعة.</span>
        </div>

      </div>
    </div>
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
            <input type="text" name="client_name" id="client_name_input" class="form-control"
                   placeholder="الاسم الكامل (3 كلمات على الأقل)"
                   value="<?= htmlspecialchars($receipt['client_name'] ?? '') ?>" required <?php if(!empty($isRenewal)) echo "readonly" ?>>
            <span class="field-hint">يجب إدخال 3 كلمات على الأقل</span>
            <div class="inline-error" id="name_error">
              ❌ يجب أن يحتوي الاسم على 3 كلمات على الأقل.
            </div>
          </div>

          <div class="form-field">
            <label class="form-label">هاتف العميل <span class="req">*</span></label>
            <div class="phone-row">
              <span class="phone-prefix" id="phone_prefix_badge">
                <?= htmlspecialchars($receipt['country_code'] ?? '—') ?>
              </span>
              <input type="hidden" name="country_code" id="country_code_input"
                     value="<?= htmlspecialchars($receipt['country_code'] ?? '') ?>">
              <input type="hidden" name="full_phone" id="full_phone_input"
                     value="<?= htmlspecialchars($receipt['phone'] ?? '') ?>">
              <input type="text" name="phone_local" id="phone_input" class="form-control"
                     placeholder="رقم الهاتف بدون كود الدولة"
                     inputmode="numeric"
                     maxlength="11"
                     value="<?= htmlspecialchars($receipt['phone_local'] ?? '') ?>"
                     required>
            </div>
            <span class="field-hint">كود الدولة يُحدَّد تلقائياً عند اختيار الفرع</span>
            <div class="inline-error" id="phone_error">
              ❌ <span id="phone_error_msg">رقم الهاتف غير صحيح.</span>
            </div>
          </div>

          <div class="form-field full">
            <label class="form-label">البريد الإلكتروني للعميل</label>
            <input type="text" name="client_email" id="client_email_input" class="form-control"
                   placeholder="example@gmail.com"
                   value="<?= htmlspecialchars($receipt['client_email'] ?? '') ?>">
            <span class="field-hint">اختياري — يجب أن ينتهي بـ @gmail.com</span>
            <div class="inline-error" id="email_error">
              ❌ يجب أن يكون البريد الإلكتروني بصيغة name@gmail.com فقط.
            </div>
          </div>

          <div class="form-field">
            <label class="form-label">العمر</label>
            <input type="number" name="client_age" id="client_age_input" class="form-control"
                   placeholder="مثال: 25"
                   min="5" max="99"
                   value="<?= htmlspecialchars($receipt['age'] ?? '') ?>">
            <span class="field-hint">اختياري</span>
          </div>

          <div class="form-field">
            <label class="form-label">الجنس</label>
            <select name="client_gender" id="client_gender_input" class="form-control">
              <option value="">— اختر —</option>
              <option value="ذكر"   <?= ($receipt['gender'] ?? '') === 'ذكر'   ? 'selected' : '' ?>>ذكر</option>
              <option value="أنثى" <?= ($receipt['gender'] ?? '') === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
            </select>
            <span class="field-hint">اختياري</span>
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
              <input type="hidden" name="branch_id" id="branch"
                     value="<?= (int)$managerBranch['id'] ?>"
                     data-country-id="<?= (int)($managerBranch['country_id'] ?? 0) ?>"
                     data-country-code="<?= htmlspecialchars($managerBranch['country_code'] ?? '') ?>">
              <div class="branch-locked">
                <span>🏢</span>
                <span class="branch-locked-name"><?= htmlspecialchars($managerBranch['branch_name']) ?></span>
                <span class="branch-locked-note">فرعك — غير قابل للتغيير</span>
              </div>

            <?php else: ?>
              <select name="branch_id" id="branch" class="form-control" required>
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
            <select name="plan_id" id="price" class="form-control" required>
              <option value="">— اختر الفرع أولاً —</option>
            </select>
            <div class="no-plans-notice" id="no_plans_notice">
              ℹ️ لا توجد خطط مرتبطة ببلد هذا الفرع بعد.
            </div>
          </div>

          <div class="form-field">
            <label class="form-label">الكابتن</label>
            <select name="captain_id" id="captain" class="form-control">
              <option value="">— اختر الفرع أولاً —</option>
            </select>
          </div>

          <div class="form-field">
            <label class="form-label">المستوى</label>
            <select name="level" class="form-control" id="level">
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
            <input type="date" name="first_session" id="start_date" class="form-control"
                   min="<?= $todayDate ?>"
                   value="<?= htmlspecialchars($receipt['first_session'] ?? '') ?>" required>
            <?php if (!empty($isRenewal)): ?>
              <span class="field-hint">يجب أن يكون تاريخاً مستقبلياً ومختلفاً عن الإيصال السابق</span>
            <?php else: ?>
              <span class="field-hint">لا يمكن اختيار تاريخ في الماضي</span>
            <?php endif; ?>
          </div>

          <div class="form-field">
            <label class="form-label">وقت التمرين</label>
            <input type="time" name="exercise_time" id="exercise_time" class="form-control"
                   value="<?= htmlspecialchars($receipt['exercise_time'] ?? '') ?>">
            <div class="inline-error" id="time_error">
              ❌ <span id="time_error_msg">وقت التمرين خارج ساعات عمل الفرع.</span>
            </div>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">تاريخ جلسة التجديد</label>
            <input type="text" name="renewal_session" id="renewal_date" class="form-control"
                   value="<?= htmlspecialchars($receipt['renewal_session'] ?? '') ?>" readonly>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">تاريخ آخر جلسة</label>
            <input type="text" name="last_session" id="last_date" class="form-control"
                   value="<?= htmlspecialchars($receipt['last_session'] ?? '') ?>" readonly>
          </div>

          <div class="form-field full">
            <label class="toggle-row">
              <input type="checkbox" name="double" id="double">
              <span class="toggle-thumb"></span>
              <span class="toggle-label">مكثف (جلستان في اليوم)</span>
            </label>
          </div>

          <div class="inline-error full" id="day_error">
            ❌ هذا الفرع لا يعمل في اليوم المختار — أيام العمل:
            <span id="day_error_hint" style="font-weight:600; margin-right:4px;"></span>
          </div>

          <div class="inline-error full" id="past_date_error">
            ❌ لا يمكن اختيار تاريخ في الماضي. يرجى اختيار اليوم أو تاريخ مستقبلي.
          </div>

          <div class="inline-error full" id="same_date_error" style="display:none;">
            ❌ <span id="same_date_error_msg"></span>
          </div>

          <div class="inline-error full" id="today_date_error" style="display:none;">
            ❌ لا يمكن إنشاء إيصال تجديد بتاريخ اليوم. يرجى اختيار تاريخ مستقبلي.
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
            <input type="text" name="amount" id="paidAmount" class="form-control"
                   placeholder="0"
                   value="<?= htmlspecialchars($receipt['amount'] ?? '0') ?>"
                   min="<?= $minPaymentAmount ?>"  required>
            <div class="pay-warn" id="pay_warn">
              ⚠️ الحد الأدنى للدفع هو
              <strong id="min_pay_display"><?= number_format($minPaymentAmount, 0) ?></strong>
              جنيه. لا يمكن المتابعة بمبلغ أقل.
            </div>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">المتبقي</label>
            <input type="number" name="remaining" id="remainingAmount" class="form-control"
                   value="<?= htmlspecialchars($receipt['remaining'] ?? '0') ?>" min="0" readonly>
          </div>

          <div class="form-field">
            <label class="form-label">طريقة الدفع <span class="req">*</span></label>
            <select name="payment_method" id="payment_method" class="form-control" required>
              <option value="">— اختر —</option>
              <option value="cash"          <?= ($receipt['payment_method'] ?? '') === 'cash'          ? 'selected' : '' ?>>نقداً</option>
              <option value="instapay"      <?= ($receipt['payment_method'] ?? '') === 'instapay'      ? 'selected' : '' ?>>InstaPay</option>
              <option value="vodafone_cash" <?= ($receipt['payment_method'] ?? '') === 'vodafone_cash' ? 'selected' : '' ?>>Vodafone Cash</option>
              <option value="bank_transfer" <?= ($receipt['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>تحويل بنكي</option>
            </select>
          </div>

          <div class="form-field" id="evidence-field">
            <label class="form-label">إثبات الدفع <span class="req">*</span></label>
            <input type="file" name="transaction_evidence" id="transaction_evidence"
                   class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/*">
            <span class="field-hint">صور فقط (JPG، PNG، GIF، WEBP) — لا يُقبل PDF</span>
            <div class="inline-error" id="evidence_error">
              ❌ يُسمح بالصور فقط. الرجاء اختيار ملف صورة (JPG، PNG، GIF، WEBP).
            </div>
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
      <?php if ($isEdit && !empty($receipt['id'])): ?>
        <button type="button" class="btn btn-email" id="sendEmailBtn"
                onclick="openEmailModal()">
          📧 إرسال الإيصال بالبريد
        </button>
      <?php endif; ?>
      <button type="button" class="btn btn-primary" id="submitBtn">
        <?php if ($isRenewal): ?>
          🔄 إنشاء إيصال التجديد
        <?php elseif ($isEdit): ?>
          💾 حفظ التعديلات
        <?php else: ?>
          ➕ إنشاء الإيصال
        <?php endif; ?>
      </button>
    </div>

  </form>
</div>

<!-- ═══════════════════════════════════════════════════════════
     EMAIL MODAL
     ═══════════════════════════════════════════════════════════ -->
<?php if ($isEdit && !empty($receipt['id'])): ?>
<div class="modal-overlay" id="emailModal">
  <div class="modal-box">
    <div class="modal-title">📧 إرسال الإيصال بالبريد الإلكتروني</div>

    <div class="form-field" style="margin-bottom:14px;">
      <label class="form-label">البريد الإلكتروني للمستلم <span class="req">*</span></label>
      <input type="email" id="modalEmailInput" class="form-control"
             placeholder="example@gmail.com"
             value="<?= htmlspecialchars($receipt['client_email'] ?? '') ?>">
      <span class="field-hint">يمكنك تغيير العنوان قبل الإرسال</span>
    </div>

    <div class="modal-status" id="modalStatus"></div>

    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">إلغاء</button>
      <button type="button" class="btn btn-email" id="modalSendBtn" onclick="sendReceiptEmail()">
        📤 إرسال
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     CONFIRMATION POPUP
     ═══════════════════════════════════════════════════════════ -->
<div class="confirm-overlay" id="confirmOverlay" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
  <div class="confirm-box">

    <!-- Header -->
    <div class="confirm-header">
      <div class="confirm-header-icon" id="confirmHeaderIcon">✅</div>
      <div class="confirm-header-text">
        <div class="confirm-header-title" id="confirmTitle">مراجعة البيانات قبل الحفظ</div>
        <div class="confirm-header-sub">تأكد من صحة جميع المعلومات قبل المتابعة</div>
      </div>
    </div>

    <!-- Progress steps -->
    <div class="confirm-progress">
      <span class="confirm-step done">✓ بيانات العميل</span>
      <span class="confirm-step-sep">›</span>
      <span class="confirm-step done">✓ الاشتراك</span>
      <span class="confirm-step-sep">›</span>
      <span class="confirm-step done">✓ الجلسات</span>
      <span class="confirm-step-sep">›</span>
      <span class="confirm-step done">✓ الدفع</span>
      <span class="confirm-step-sep">›</span>
      <span class="confirm-step" style="color:var(--accent);border-color:rgba(79,124,255,0.4);background:rgba(79,124,255,0.08);">⏳ مراجعة</span>
    </div>

    <!-- Body — filled by JS -->
    <div class="confirm-body" id="confirmBody"></div>

    <!-- Footer -->
    <div class="confirm-footer">
      <span class="confirm-footer-left">
        <span style="font-size:15px;">🔒</span> البيانات لن تُحفظ حتى تضغط تأكيد
      </span>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-confirm-edit" onclick="closeConfirm()">
          ✏️ تعديل
        </button>
        <button type="button" class="btn btn-confirm-submit" id="confirmSubmitBtn" onclick="proceedSubmit()">
          ✅ تأكيد الإنشاء
        </button>
      </div>
    </div>

  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
//  PHP → JS  data injection
// ═══════════════════════════════════════════════════════════════
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
    $days        = array_values(array_unique($days));
    $countryId   = isset($b['country_id'])        ? (int)$b['country_id']   : 0;
    $countryCode = isset($b['country_code'])       ? $b['country_code']      : '';
    $timeFrom    = isset($b['working_time_from'])  ? substr($b['working_time_from'], 0, 5) : '';
    $timeTo      = isset($b['working_time_to'])    ? substr($b['working_time_to'],   0, 5) : '';
?>
BRANCH_META[<?= (int)$b['id'] ?>] = {
    country_id:        <?= $countryId ?>,
    country_code:      <?= json_encode($countryCode) ?>,
    days:              <?= json_encode($days) ?>,
    working_time_from: <?= json_encode($timeFrom) ?>,
    working_time_to:   <?= json_encode($timeTo) ?>
};
<?php endforeach; ?>

const CAPTAINS_BY_BRANCH  = <?= json_encode($captainsByBranch ?? new stdClass()) ?>;
const IS_RENEWAL          = <?= json_encode(!empty($isRenewal)) ?>;
const IS_EDIT             = <?= json_encode(!empty($isEdit)) ?>;
const PREV_FIRST_SESSION  = <?= json_encode($prevFirstSession) ?>;
const SERVER_RENEWAL_TYPE = <?= json_encode($serverType ?? $autoRenewalType ?? '') ?>;
const IS_BRANCH_MANAGER   = <?= json_encode($isBranchManager) ?>;

const PLANS_BY_COUNTRY_ID = {};
<?php foreach (($plans ?? []) as $p):
    $cid = (int)($p['country_id'] ?? 0);
    if (!$cid) continue;
?>
PLANS_BY_COUNTRY_ID[<?= $cid ?>] = PLANS_BY_COUNTRY_ID[<?= $cid ?>] || [];
PLANS_BY_COUNTRY_ID[<?= $cid ?>].push({
    id:       <?= (int)$p['id'] ?>,
    label:    <?= json_encode($p['description']) ?>,
    price:    <?= (float)$p['price'] ?>,
    sessions: <?= (int)$p['number_of_sessions'] ?>
});
<?php endforeach; ?>

const MIN_PAYMENT      = <?= (float)$minPaymentAmount ?>;
const TODAY            = <?= json_encode($todayDate) ?>;
const SAVED_PLAN_ID    = <?= json_encode((string)($receipt['plan_id']    ?? '')) ?>;
const SAVED_CAPTAIN_ID = <?= json_encode((string)($receipt['captain_id'] ?? '')) ?>;
const RECEIPT_ID       = <?= json_encode((int)($receipt['id'] ?? 0)) ?>;
const SEND_EMAIL_URL   = <?= json_encode(APP_URL . '/receipt/send-email') ?>;

const RENEWAL_TYPE_LABELS = {
    'current_renewal':  'تجديد حالي 🔁',
    'previous_renewal': 'تجديد سابق ⏪',
};

// ═══════════════════════════════════════════════════════════════
//  DOM refs
// ═══════════════════════════════════════════════════════════════
const branchSel           = document.getElementById('branch');
const planSel             = document.getElementById('price');
const captainSel          = document.getElementById('captain');
const paidInput           = document.getElementById('paidAmount');
const remainingIn         = document.getElementById('remainingAmount');
const startDateIn         = document.getElementById('start_date');
const renewalIn           = document.getElementById('renewal_date');
const lastDateIn          = document.getElementById('last_date');
const doubleChk           = document.getElementById('double');
const dayErrorEl          = document.getElementById('day_error');
const dayErrorHint        = document.getElementById('day_error_hint');
const pastDateErrorEl     = document.getElementById('past_date_error');
const sameDateErrorEl     = document.getElementById('same_date_error');
const sameDateErrorMsg    = document.getElementById('same_date_error_msg');
const todayDateErrorEl    = document.getElementById('today_date_error');
const payMethodSel        = document.getElementById('payment_method');
const evidenceField       = document.getElementById('evidence-field');
const evidenceIn          = document.getElementById('transaction_evidence');
const evidenceErrorEl     = document.getElementById('evidence_error');
const payWarnEl           = document.getElementById('pay_warn');
const noPlansNotice       = document.getElementById('no_plans_notice');
const minPayDisplay       = document.getElementById('min_pay_display');
const submitBtn           = document.getElementById('submitBtn');
const form                = document.getElementById('receiptForm');
const clientNameIn        = document.getElementById('client_name_input');
const clientEmailIn       = document.getElementById('client_email_input');
const phonePrefixBadge    = document.getElementById('phone_prefix_badge');
const countryCodeIn       = document.getElementById('country_code_input');
const phoneLocalIn        = document.getElementById('phone_input');
const fullPhoneIn         = document.getElementById('full_phone_input');
const nameErrorEl         = document.getElementById('name_error');
const phoneErrorEl        = document.getElementById('phone_error');
const phoneErrorMsg       = document.getElementById('phone_error_msg');
const emailErrorEl        = document.getElementById('email_error');
const exerciseTimeIn      = document.getElementById('exercise_time');
const timeErrorEl         = document.getElementById('time_error');
const timeErrorMsg        = document.getElementById('time_error_msg');

const rtCurrentRadio      = document.getElementById('rt_current');
const rtPreviousRadio     = document.getElementById('rt_previous');
const rtCurrentCard       = document.getElementById('card_current');
const rtPreviousCard      = document.getElementById('card_previous');
const rtMismatchError     = document.getElementById('renewal_type_error');
const rtMismatchMsg       = document.getElementById('renewal_type_error_msg');
const rtRequiredError     = document.getElementById('renewal_type_required_error');

// ═══════════════════════════════════════════════════════════════
//  RENEWAL TYPE VALIDATION
// ═══════════════════════════════════════════════════════════════
function getSelectedRenewalType() {
    if (!rtCurrentRadio) return null;
    if (rtCurrentRadio.checked)  return 'current_renewal';
    if (rtPreviousRadio.checked) return 'previous_renewal';
    return null;
}
function clearRenewalTypeErrors() {
    if (!rtMismatchError) return;
    rtMismatchError.classList.remove('visible');
    rtRequiredError.classList.remove('visible');
    [rtCurrentCard, rtPreviousCard].forEach(c => c && c.classList.remove('card-invalid'));
}
function validateRenewalType() {
    if (!IS_RENEWAL || !rtCurrentRadio) return true;
    clearRenewalTypeErrors();
    const chosen = getSelectedRenewalType();
    if (!chosen) { rtRequiredError.classList.add('visible'); return false; }
    if (chosen === SERVER_RENEWAL_TYPE) return true;
    const chosenLabel  = RENEWAL_TYPE_LABELS[chosen]             || chosen;
    const correctLabel = RENEWAL_TYPE_LABELS[SERVER_RENEWAL_TYPE] || SERVER_RENEWAL_TYPE;
    const wrongCard    = chosen === 'current_renewal' ? rtCurrentCard : rtPreviousCard;
    if (wrongCard) wrongCard.classList.add('card-invalid');
    const pillClass = SERVER_RENEWAL_TYPE === 'current_renewal' ? 'current' : 'previous';
    rtMismatchMsg.innerHTML =
        `اخترت <strong>${chosenLabel}</strong> لكن النوع الصحيح بناءً على تاريخ آخر جلسة هو:<br>` +
        `<span class="correct-answer-pill ${pillClass}">${correctLabel}</span>`;
    rtMismatchError.classList.add('visible');
    return false;
}
if (rtCurrentRadio)  rtCurrentRadio.addEventListener('change',  validateRenewalType);
if (rtPreviousRadio) rtPreviousRadio.addEventListener('change', validateRenewalType);

// ═══════════════════════════════════════════════════════════════
//  Name validation
// ═══════════════════════════════════════════════════════════════
function validateName() {
    const words = clientNameIn.value.trim().split(/\s+/).filter(w => w.length > 0);
    const invalid = words.length < 3;
    nameErrorEl.classList.toggle('visible', invalid);
    clientNameIn.classList.toggle('field-invalid', invalid);
    return !invalid;
}
clientNameIn.addEventListener('input', validateName);
clientNameIn.addEventListener('blur',  validateName);

// ═══════════════════════════════════════════════════════════════
//  Phone validation
// ═══════════════════════════════════════════════════════════════
const PHONE_RULES = {
    '+966': { regex: /^(05\d{8}|5\d{8})$/, hint: 'مثال: 0512345678 أو 512345678 (9-10 أرقام تبدأ بـ 5)' },
    '+20':  { regex: /^(01[0-9]\d{8}|1[0-9]\d{8})$/, hint: 'مثال: 01012345678 أو 1012345678 (10-11 رقماً تبدأ بـ 01)' },
};
function validatePhone() {
    const raw        = phoneLocalIn.value;
    const digitsOnly = raw.replace(/\D/g, '');
    if (raw !== digitsOnly) phoneLocalIn.value = digitsOnly;
    const prefix = countryCodeIn.value;
    const rule   = PHONE_RULES[prefix];
    let invalid  = false;
    if (digitsOnly.length > 0 && rule) {
        invalid = !rule.regex.test(digitsOnly);
        if (invalid) phoneErrorMsg.textContent = '❌ رقم غير صحيح — ' + rule.hint;
    }
    phoneErrorEl.classList.toggle('visible', invalid);
    phoneLocalIn.classList.toggle('field-invalid', invalid);
    assembleFullPhone();
    return !invalid;
}
phoneLocalIn.addEventListener('input', validatePhone);
phoneLocalIn.addEventListener('blur',  validatePhone);

// ═══════════════════════════════════════════════════════════════
//  Email validation
// ═══════════════════════════════════════════════════════════════
function validateEmail() {
    const val = clientEmailIn.value.trim();
    if (!val) {
        emailErrorEl.classList.remove('visible');
        clientEmailIn.classList.remove('field-invalid');
        return true;
    }
    const invalid = !/^[a-zA-Z0-9._%+\-]+@gmail\.com$/.test(val);
    emailErrorEl.classList.toggle('visible', invalid);
    clientEmailIn.classList.toggle('field-invalid', invalid);
    return !invalid;
}
clientEmailIn.addEventListener('input', validateEmail);
clientEmailIn.addEventListener('blur',  validateEmail);

// ═══════════════════════════════════════════════════════════════
//  Evidence validation
// ═══════════════════════════════════════════════════════════════
function validateEvidence() {
    if (!evidenceIn.files || evidenceIn.files.length === 0) {
        evidenceErrorEl.classList.remove('visible');
        evidenceIn.classList.remove('field-invalid');
        return true;
    }
    const file    = evidenceIn.files[0];
    const isImage = file.type.startsWith('image/');
    evidenceErrorEl.classList.toggle('visible', !isImage);
    evidenceIn.classList.toggle('field-invalid', !isImage);
    if (!isImage) evidenceIn.value = '';
    return isImage;
}
evidenceIn.addEventListener('change', validateEvidence);

// ═══════════════════════════════════════════════════════════════
//  Exercise time validation
// ═══════════════════════════════════════════════════════════════

function formatTime12h(time24) {
    let [hours, minutes] = time24.split(':').map(Number);
    // Normalize 24 to 0 for proper AM/PM calculation
    if (hours === 24) hours = 0; 
    
    const period = hours >= 12 ? 'م' : 'ص'; // Using Arabic indicators directly to match your UI
    let hours12 = hours % 12 || 12;
    
    return `${hours12}:${minutes.toString().padStart(2, '0')} ${period}`;
}

function timeToMinutes(time) {
    const [h, m] = time.split(':').map(Number);
    // Convert 00:00 to 1440 ONLY if it's the end of a range, 
    // but a cleaner way is to handle it in the range checker itself.
    return h * 60 + m;
}

function isTimeInRange(time, from, to) {
    const t = timeToMinutes(time);
    let f = timeToMinutes(from);
    let e = timeToMinutes(to);

    // If closing time is midnight (00:00), treat it as the end of the day (1440 mins)
    // so standard comparison works flawlessly.
    if (e === 0) e = 24 * 60; 
    if (f === 0 && e < f) f = 0;

    // Standard day range
    if (f <= e) {
        return t >= f && t <= e;
    }

    // Overnight range (e.g., 9:00 PM to 2:00 AM)
    return t >= f || t <= e;
}

function validateExerciseTime() {
    let time = exerciseTimeIn.value; // e.g., "22:00"

    timeErrorEl.classList.remove('visible');
    exerciseTimeIn.classList.remove('field-invalid');

    if (!time) return true;

    const meta = branchMeta();
    if (!meta || !meta.working_time_from || !meta.working_time_to) return true;

    const from = meta.working_time_from;
    const to = meta.working_time_to;

    if (!isTimeInRange(time, from, to)) {
        timeErrorMsg.textContent =
            `وقت التمرين يجب أن يكون بين ${formatTime12h(from)} و ${formatTime12h(to)} (ساعات عمل الفرع).`;

        timeErrorEl.classList.add('visible');
        exerciseTimeIn.classList.add('field-invalid');
        return false;
    }

    return true;
}


exerciseTimeIn.addEventListener('change', validateExerciseTime);
exerciseTimeIn.addEventListener('blur',   validateExerciseTime);

// ═══════════════════════════════════════════════════════════════
//  Renewal date guards
// ═══════════════════════════════════════════════════════════════
function validateRenewalDateGuards() {
    if (!IS_RENEWAL) return true;
    const chosen = startDateIn.value;
    sameDateErrorEl.style.display = 'none';
    todayDateErrorEl.style.display = 'none';
    if (!chosen) return true;
    if (chosen === TODAY) {
        todayDateErrorEl.style.display = 'flex';
        submitBtn.disabled = true;
        return false;
    }
    if (PREV_FIRST_SESSION && chosen === PREV_FIRST_SESSION) {
        sameDateErrorMsg.textContent =
            'لا يمكن استخدام نفس تاريخ بداية الإيصال السابق ('
            + PREV_FIRST_SESSION
            + '). يرجى اختيار تاريخ مختلف.';
        sameDateErrorEl.style.display = 'flex';
        submitBtn.disabled = true;
        return false;
    }
    return true;
}

// ═══════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════
function branchMeta() {
    if (!branchSel) return null;
    const id = branchSel.value
        || (IS_BRANCH_MANAGER ? branchSel.getAttribute('value') : null);
    return id ? (BRANCH_META[id] || null) : null;
}
function selectedSessions() {
    const opt = planSel.options[planSel.selectedIndex];
    return parseInt(opt?.dataset.sessions) || 0;
}
function selectedPrice() {
    const opt = planSel.options[planSel.selectedIndex];
    return parseFloat(opt?.dataset.price) || 0;
}
function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// ═══════════════════════════════════════════════════════════════
//  Country code badge
// ═══════════════════════════════════════════════════════════════
function updateCountryCode() {
    const meta   = branchMeta();
    const prefix = (meta && meta.country_code) ? meta.country_code : '—';
    phonePrefixBadge.textContent = prefix;
    countryCodeIn.value          = prefix !== '—' ? prefix : '';
    validatePhone();
    assembleFullPhone();
}
function assembleFullPhone() {
    const prefix = countryCodeIn.value;
    let local    = phoneLocalIn.value.trim();
    if (prefix && local.startsWith('0')) local = local.slice(1);
    fullPhoneIn.value = prefix ? (prefix + local) : local;
}

// ═══════════════════════════════════════════════════════════════
//  Plans dropdown
// ═══════════════════════════════════════════════════════════════
function populatePlans() {
    const meta      = branchMeta();
    const countryId = meta ? meta.country_id : null;
    const plans     = (countryId && PLANS_BY_COUNTRY_ID[countryId])
                        ? PLANS_BY_COUNTRY_ID[countryId] : [];
    if (noPlansNotice) {
        noPlansNotice.classList.toggle('visible', meta !== null && plans.length === 0);
    }
    planSel.innerHTML = plans.length
        ? '<option value="">— اختر الخطة —</option>'
        : '<option value="">— لا توجد خطط لهذا الفرع —</option>';
    plans.forEach(p => {
        const o = document.createElement('option');
        o.value            = p.id;
        o.dataset.price    = p.price;
        o.dataset.sessions = p.sessions;
        o.textContent      = `${p.label} — ${p.price} (${p.sessions} جلسة)`;
        if (String(p.id) === SAVED_PLAN_ID) o.selected = true;
        planSel.appendChild(o);
    });
    calculateRemaining();
    updateSessionDates();
}

// ═══════════════════════════════════════════════════════════════
//  Captains dropdown
// ═══════════════════════════════════════════════════════════════
function populateCaptains() {
    const branchId = branchSel ? branchSel.value : null;
    const captains = branchId ? (CAPTAINS_BY_BRANCH[branchId] || []) : [];
    captainSel.innerHTML = captains.length
        ? '<option value="">— اختر الكابتن —</option>'
        : '<option value="">— لا يوجد كباتن لهذا الفرع —</option>';
    captains.forEach(c => {
        const o = document.createElement('option');
        o.value       = c.id;
        o.textContent = c.name;
        if (String(c.id) === SAVED_CAPTAIN_ID) o.selected = true;
        captainSel.appendChild(o);
    });
}

// ═══════════════════════════════════════════════════════════════
//  Payment remaining + validation
// ═══════════════════════════════════════════════════════════════
function calculateRemaining() {
    const price = selectedPrice();
    const paid  = parseFloat(paidInput.value) || 0;
    price > 0 ? paidInput.setAttribute('max', price) : paidInput.removeAttribute('max');
    remainingIn.value = price > 0 ? Math.max(price - paid, 0) : 0;
    validatePayment(paid);
}
function validatePayment(paid) {
    if (paid > 0 && paid < MIN_PAYMENT) {
        payWarnEl.classList.add('visible');
        if (minPayDisplay) minPayDisplay.textContent = MIN_PAYMENT.toLocaleString('ar-EG');
        submitBtn.disabled = true;
    } else {
        payWarnEl.classList.remove('visible');
        if (!dayErrorEl.classList.contains('visible') &&
            !pastDateErrorEl.classList.contains('visible') &&
            sameDateErrorEl.style.display === 'none' &&
            todayDateErrorEl.style.display === 'none') {
            submitBtn.disabled = false;
        }
    }
}

// ═══════════════════════════════════════════════════════════════
//  Session date logic
// ═══════════════════════════════════════════════════════════════
function pickActiveDays(startDayName, allowedDays, totalSessions, isDouble) {
    const idx = allowedDays.indexOf(startDayName);
    if (idx === -1) return [];
    const pairStart = idx % 2 === 0 ? idx : idx - 1;
    const pair1 = allowedDays.slice(pairStart, pairStart + 2);
    if (pair1[0] !== startDayName) pair1.reverse();
    if (!isDouble) return totalSessions >= 8 ? pair1 : [startDayName];
    if (totalSessions >= 8) {
        const pair2Start = pairStart === 0 ? 2 : 0;
        return [...new Set([...pair1, ...allowedDays.slice(pair2Start, pair2Start + 2)])];
    }
    return pair1;
}
function buildSessionDates(firstSession, allowedDays, totalSessions, isDouble) {
    const sessionsPerVisit = isDouble ? 2 : 1;
    const totalVisits      = Math.ceil(totalSessions / sessionsPerVisit);
    const start            = new Date(firstSession + 'T00:00:00');
    const startDayName     = start.toLocaleDateString('en-US', { weekday: 'long' });
    const activeDays       = pickActiveDays(startDayName, allowedDays, totalSessions, isDouble);
    if (!activeDays.length) return { renewal: '', last: '' };
    const dates = []; const cursor = new Date(start); let safety = 0;
    while (dates.length < totalVisits && safety < 365) {
        if (activeDays.includes(cursor.toLocaleDateString('en-US', { weekday: 'long' })))
            dates.push(formatLocalDate(cursor));
        cursor.setDate(cursor.getDate() + 1); safety++;
    }
    if (dates.length < 2) return { renewal: '', last: dates[0] ?? '' };
    return { renewal: dates[dates.length - 2], last: dates[dates.length - 1] };
}
function updateSessionDates() {
    const startDate = startDateIn.value;
    renewalIn.value = ''; lastDateIn.value = '';
    dayErrorEl.classList.remove('visible');
    pastDateErrorEl.classList.remove('visible');
    dayErrorHint.textContent = '';
    if (!startDate || !branchSel.value) return;
    if (startDate < TODAY) {
        pastDateErrorEl.classList.add('visible');
        submitBtn.disabled = true;
        return;
    }
    if (!validateRenewalDateGuards()) return;
    const meta = branchMeta();
    if (!meta || !meta.days.length) return;
    const startDayName = new Date(startDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
    if (!meta.days.includes(startDayName)) {
        dayErrorHint.textContent = meta.days.join('، ');
        dayErrorEl.classList.add('visible'); submitBtn.disabled = true; return;
    }
    submitBtn.disabled = false;
    validatePayment(parseFloat(paidInput.value) || 0);
    const total = selectedSessions();
    if (!total) return;
    const result = buildSessionDates(startDate, meta.days, total, doubleChk.checked);
    renewalIn.value = result.renewal; lastDateIn.value = result.last;
}

// ═══════════════════════════════════════════════════════════════
//  Payment evidence toggle
// ═══════════════════════════════════════════════════════════════
function toggleEvidence() {
    const m = payMethodSel.value;
    if (m && m !== 'cash') {
        evidenceField.classList.add('visible'); evidenceIn.required = true;
    } else {
        evidenceField.classList.remove('visible'); evidenceIn.required = false;
        evidenceIn.value = '';
        evidenceErrorEl.classList.remove('visible');
    }
}

// ═══════════════════════════════════════════════════════════════
//  Email modal
// ═══════════════════════════════════════════════════════════════
function openEmailModal() {
    const modalInput = document.getElementById('modalEmailInput');
    if (modalInput && clientEmailIn) modalInput.value = clientEmailIn.value;
    const statusEl = document.getElementById('modalStatus');
    if (statusEl) { statusEl.className = 'modal-status'; statusEl.textContent = ''; }
    document.getElementById('emailModal').classList.add('open');
}
function closeEmailModal() {
    document.getElementById('emailModal').classList.remove('open');
}
async function sendReceiptEmail() {
    const emailInput = document.getElementById('modalEmailInput');
    const statusEl   = document.getElementById('modalStatus');
    const sendBtn    = document.getElementById('modalSendBtn');
    const email      = emailInput.value.trim();
    if (!email || !/^[a-zA-Z0-9._%+\-]+@gmail\.com$/.test(email)) {
        statusEl.className = 'modal-status error';
        statusEl.textContent = '⚠️ يرجى إدخال بريد إلكتروني صحيح بصيغة name@gmail.com';
        return;
    }
    sendBtn.disabled = true;
    sendBtn.innerHTML = '⏳ جارٍ الإرسال... <span class="spinner"></span>';
    statusEl.className = 'modal-status'; statusEl.textContent = '';
    try {
        const res  = await fetch(SEND_EMAIL_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ receipt_id: RECEIPT_ID, email: email })
        });
        const data = await res.json();
        if (data.success) {
            statusEl.className = 'modal-status success';
            statusEl.textContent = '✅ تم إرسال الإيصال بنجاح إلى ' + email;
            if (clientEmailIn) clientEmailIn.value = email;
            sendBtn.innerHTML = '✅ تم الإرسال';
        } else {
            statusEl.className = 'modal-status error';
            statusEl.textContent = '❌ فشل الإرسال: ' + (data.message || 'خطأ غير معروف');
            sendBtn.disabled = false;
            sendBtn.innerHTML = '📤 إرسال';
        }
    } catch (err) {
        statusEl.className = 'modal-status error';
        statusEl.textContent = '❌ تعذّر الاتصال بالخادم. حاول مرة أخرى.';
        sendBtn.disabled = false;
        sendBtn.innerHTML = '📤 إرسال';
    }
}
document.getElementById('emailModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEmailModal();
});

// ═══════════════════════════════════════════════════════════════
//  Event listeners
// ═══════════════════════════════════════════════════════════════
if (!IS_BRANCH_MANAGER) {
    branchSel.addEventListener('change', () => {
        updateCountryCode();
        populatePlans();
        populateCaptains();
        updateSessionDates();
        validateExerciseTime();
    });
}
planSel.addEventListener('change',        () => { calculateRemaining(); updateSessionDates(); });
doubleChk.addEventListener('change',      updateSessionDates);
paidInput.addEventListener('input',       calculateRemaining);
startDateIn.addEventListener('change',    updateSessionDates);
payMethodSel.addEventListener('change',   toggleEvidence);
phoneLocalIn.addEventListener('input',    validatePhone);
exerciseTimeIn.addEventListener('change', validateExerciseTime);

// ═══════════════════════════════════════════════════════════════
//  Init
// ═══════════════════════════════════════════════════════════════
(function init() {
    if (branchSel.value) {
        updateCountryCode();
        populatePlans();
        populateCaptains();
    }
    toggleEvidence();
    calculateRemaining();
    updateSessionDates();
    assembleFullPhone();
    validateExerciseTime();
    if (minPayDisplay) minPayDisplay.textContent = MIN_PAYMENT.toLocaleString('ar-EG');
})();

// ═══════════════════════════════════════════════════════════════
//  CONFIRMATION POPUP — helpers
// ═══════════════════════════════════════════════════════════════
let _confirmBypass = false;

function selText(el) {
    return (el && el.selectedIndex > 0) ? el.options[el.selectedIndex].text : null;
}
function fval(el) {
    return el ? el.value.trim() : '';
}

function buildConfirmHTML() {
    // ── collect values ──────────────────────────────────────────
    const clientName   = fval(clientNameIn);
    const phone        = fval(fullPhoneIn) || (fval(countryCodeIn) + fval(phoneLocalIn));
    const email        = fval(clientEmailIn);
    const age          = fval(document.querySelector('[name="client_age"]'));
    const gender       = fval(document.querySelector('[name="client_gender"]'));

    const branchText   = IS_BRANCH_MANAGER
        ? (document.querySelector('.branch-locked-name')?.textContent?.trim() || '—')
        : (selText(branchSel) || '—');
    const planText     = selText(planSel);
    const captainText  = selText(captainSel);
    const level        = fval(document.querySelector('[name="level"]'));

    const firstSession = fval(startDateIn);
    const renewalSess  = fval(renewalIn);
    const lastSess     = fval(lastDateIn);
    const exTime       = fval(exerciseTimeIn);
    const isDouble     = doubleChk.checked;

    const amount       = fval(paidInput);
    const remaining    = fval(remainingIn);
    const payMethod    = selText(payMethodSel);
    const notes        = fval(document.querySelector('[name="notes"]'));
    const evidenceFile = (evidenceIn.files && evidenceIn.files.length > 0)
                            ? evidenceIn.files[0].name : null;

    // Renewal type
    let renewalTypeHTML = '';
    if (IS_RENEWAL && rtCurrentRadio) {
        if (rtCurrentRadio.checked) {
            renewalTypeHTML = '<span class="rtype-pill current">🔁 تجديد حالي</span>';
        } else if (rtPreviousRadio?.checked) {
            renewalTypeHTML = '<span class="rtype-pill previous">⏪ تجديد سابق</span>';
        }
    }

    // Payment method display map
    const payMethodMap = {
        'cash':          'نقداً 💵',
        'instapay':      'InstaPay 📱',
        'vodafone_cash': 'Vodafone Cash 📲',
        'bank_transfer': 'تحويل بنكي 🏦',
    };
    const payMethodDisplay = payMethodSel.value ? (payMethodMap[payMethodSel.value] || payMethod) : null;

    function row(key, value, cls = '') {
        if (value === null || value === undefined || value === '') return '';
        if (typeof value === 'string' && value.trim() === '') return '';
        const isRaw = cls === '__raw'; // pass raw HTML as value
        const displayVal = isRaw
            ? `<span class="confirm-row-val">${value}</span>`
            : `<span class="confirm-row-val ${cls}">${value}</span>`;
        return `<div class="confirm-row">
            <span class="confirm-row-key">${key}</span>
            ${displayVal}
        </div>`;
    }

    let html = '';

    // ── Client ──────────────────────────────────────────────────
    html += `<div class="confirm-group">
      <div class="confirm-group-title">👤 بيانات العميل</div>
      <div class="confirm-rows">
        ${row('الاسم الكامل', clientName, 'accent')}
        ${row('رقم الهاتف', phone)}
        ${email ? row('البريد الإلكتروني', email) : ''}
        ${age    ? row('العمر', age + ' سنة') : ''}
        ${gender ? row('الجنس', gender) : ''}
      </div>
    </div>`;

    // ── Subscription ─────────────────────────────────────────────
    html += `<div class="confirm-group">
      <div class="confirm-group-title">📋 تفاصيل الاشتراك</div>
      <div class="confirm-rows">
        ${row('الفرع', branchText)}
        ${planText ? row('الخطة / العرض', planText) : ''}
        ${(captainText && !captainText.includes('—') && !captainText.includes('لا يوجد')) ? row('الكابتن', captainText) : ''}
        ${row('المستوى', level)}
        ${renewalTypeHTML ? `<div class="confirm-row"><span class="confirm-row-key">نوع التجديد</span>${renewalTypeHTML}</div>` : ''}
      </div>
    </div>`;

    // ── Sessions ──────────────────────────────────────────────────
    html += `<div class="confirm-group">
      <div class="confirm-group-title">📅 الجلسات</div>
      <div class="confirm-rows">
        ${row('أول جلسة', firstSession)}
        ${exTime      ? row('وقت التمرين', exTime) : ''}
        ${renewalSess ? row('جلسة التجديد', renewalSess) : ''}
        ${lastSess    ? row('آخر جلسة',    lastSess)    : ''}
        ${row('نظام مكثف', isDouble ? '<span style="color:var(--success);">✅ جلستان في اليوم</span>' : '<span style="color:var(--text-muted);">❌ جلسة واحدة</span>', '__raw')}
      </div>
    </div>`;

    // ── Payment ───────────────────────────────────────────────────
    html += `<div class="confirm-group">
      <div class="confirm-group-title">💳 الدفع</div>
      <div class="confirm-rows">
        ${amount    ? row('المبلغ المدفوع', amount + ' ج.م', 'accent') : ''}
        ${row('المتبقي', (remaining && remaining !== '0') ? remaining + ' ج.م' : '0 ج.م')}
        ${payMethodDisplay ? row('طريقة الدفع', payMethodDisplay) : ''}
        ${evidenceFile ? row('إثبات الدفع', '📎 ' + evidenceFile) : ''}
        ${notes ? row('ملاحظات', notes) : ''}
      </div>
    </div>`;

    // ── Edit hint ─────────────────────────────────────────────────
    html += `<div class="confirm-edit-hint">
      <span class="hint-icon">💡</span>
      <span>إذا كانت أي بيانات غير صحيحة، اضغط <strong style="color:var(--text);margin:0 3px;">✏️ تعديل</strong> للعودة وتصحيحها دون فقدان أي شيء.</span>
    </div>`;

    return html;
}

// ═══════════════════════════════════════════════════════════════
//  CONFIRMATION POPUP — open / close / submit
// ═══════════════════════════════════════════════════════════════
function openConfirm() {
    document.getElementById('confirmBody').innerHTML = buildConfirmHTML();

    const icon  = document.getElementById('confirmHeaderIcon');
    const title = document.getElementById('confirmTitle');
    const btn   = document.getElementById('confirmSubmitBtn');

    if (IS_RENEWAL) {
        icon.textContent  = '🔄';
        title.textContent = 'مراجعة بيانات التجديد';
        btn.textContent   = '🔄 تأكيد التجديد';
    } else if (IS_EDIT) {
        icon.textContent  = '💾';
        title.textContent = 'مراجعة التعديلات';
        btn.textContent   = '💾 تأكيد الحفظ';
    } else {
        icon.textContent  = '✅';
        title.textContent = 'مراجعة البيانات قبل الحفظ';
        btn.textContent   = '✅ تأكيد الإنشاء';
    }

    document.getElementById('confirmOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function proceedSubmit() {
    _confirmBypass = true;
    closeConfirm();
    setTimeout(() => {
        // Trigger the real form submit (bypass flag is set so validators won't re-block it)
        const realSubmit = document.createElement('input');
        realSubmit.type = 'submit';
        form.appendChild(realSubmit);
        realSubmit.click();
        form.removeChild(realSubmit);
    }, 80);
}

// Close on backdrop click
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('confirmOverlay').classList.contains('open')) {
        closeConfirm();
    }
});

// ═══════════════════════════════════════════════════════════════
//  INTERCEPT submitBtn click → validate → show confirm popup
// ═══════════════════════════════════════════════════════════════
submitBtn.addEventListener('click', function(e) {
    e.preventDefault();

    // Run all existing validators
    const nameOk        = validateName();
    const phoneOk       = validatePhone();
    const emailOk       = validateEmail();
    const evidenceOk    = validateEvidence();
    const timeOk        = validateExerciseTime();
    const dateGuardsOk  = validateRenewalDateGuards();
    const renewalTypeOk = validateRenewalType();

    if (!nameOk || !phoneOk || !emailOk || !evidenceOk || !timeOk || !dateGuardsOk || !renewalTypeOk) {
        const firstErr = form.querySelector(
            '.inline-error.visible, .renewal-type-error.visible, [style*="display: flex"]'
        );
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (startDateIn.value && startDateIn.value < TODAY) { startDateIn.focus(); return; }

    const paid = parseFloat(paidInput.value) || 0;
    if (paid > 0 && paid < MIN_PAYMENT) { paidInput.focus(); return; }

    // All valid — show confirmation popup
    openConfirm();
});

// ═══════════════════════════════════════════════════════════════
//  Allow real form submit when _confirmBypass is true
// ═══════════════════════════════════════════════════════════════
form.addEventListener('submit', function(e) {
    if (_confirmBypass) {
        _confirmBypass = false;
        assembleFullPhone();
        return true; // allow real POST
    }
    e.preventDefault(); // block accidental native submit
}, true);
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>