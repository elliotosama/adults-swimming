<?php
// views/receipts/payment_by_id.php
// Styled to match create.php exactly — same design tokens, section cards, JS validation patterns
// Color palette / fonts now matched to views/includes/layout_top.php (dashboard theme)
require ROOT . '/views/includes/layout_top.php';

$db = get_db();

$minPaymentRow    = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'min_payment_amount' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$minPaymentAmount = $minPaymentRow ? (float)$minPaymentRow['setting_value'] : 400;

$todayDate   = date('Y-m-d');
$currentUser = auth_user();
$isAdmin     = ($isAdmin ?? ($currentUser['role'] === 'admin'));

// Resolve branch meta for JS (same pattern as create.php)
$branches = $branches ?? [];
if (empty($branches)) {
    $branches = $db->query("
        SELECT b.id, b.branch_name,
               b.working_days1, b.working_days2, b.working_days3,
               b.working_time_from, b.working_time_to,
               c.id AS country_id, c.country, c.country_code
        FROM branches b
        JOIN countries c ON c.id = b.country_id
        WHERE b.visible = 1
        ORDER BY b.branch_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$captainsByBranch = $captainsByBranch ?? [];
if (empty($captainsByBranch)) {
    $captainRows = $db->query("
        SELECT cb.branch_id, c.id, c.captain_name
        FROM captain_branch cb
        JOIN captains c ON c.id = cb.captain_id
        WHERE c.visible = 1
        ORDER BY c.captain_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($captainRows as $row) {
        $captainsByBranch[$row['branch_id']][] = ['id' => $row['id'], 'name' => $row['captain_name']];
    }
}

$plans = $plans ?? [];
if (empty($plans)) {
    $plans = $db->query("
        SELECT p.id, p.description, p.price, p.number_of_sessions,
               p.country_id, c.country
        FROM prices p
        JOIN countries c ON c.id = p.country_id
        WHERE p.visible = 1
        ORDER BY p.description
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$plansByCountry = [];
foreach ($plans as $plan) {
    $plansByCountry[$plan['country_id']][] = $plan;
}

$statusLabels = [
    'completed'     => ['label' => 'مكتمل',     'color' => '#98C379'],
    'not_completed' => ['label' => 'غير مكتمل', 'color' => '#D19A66'],
    'pending'       => ['label' => 'معلّق',      'color' => '#007ACC'],
];

// If receipt loaded, resolve phone parts for the form
$phoneLocal    = '';
$phonePrefix   = '';
if ($receipt) {
    $raw        = $receipt['phone_number'] ?? $receipt['phone'] ?? '';
    $knownCodes = ['+966', '+20'];
    $stripped   = $raw;
    foreach ($knownCodes as $code) {
        if (str_starts_with($raw, $code)) {
            $stripped = substr($raw, strlen($code));
            if ($code === '+20' && !str_starts_with($stripped, '0')) {
                $stripped = '0' . $stripped;
            }
            $phonePrefix = $code;
            break;
        }
    }
    $phoneLocal = $stripped;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:          #1E1E2D;
    --surface:     #252736;
    --surface-2:   #2C2F38;
    --border:      #3C3F58;
    --border-focus:#007ACC;
    --accent:      #007ACC;
    --accent2:     #0A3A5C;
    --accent-dim:  #0A3A5C;
    --gold:        #D19A66;
    --success:     #98C379;
    --danger:      #E06C75;
    --warning:     #D19A66;
    --highlight:   #61DAFB;
    --text:        #FFFFFF;
    --text-muted:  rgba(255,255,255,0.62);
    --text-label:  rgba(255,255,255,0.78);
    --radius:      10px;
    --transition:  0.2s ease;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Cairo', sans-serif;
    background:
      radial-gradient(ellipse 80% 50% at 20% 80%, #007ACC1a 0%, transparent 60%),
      radial-gradient(ellipse 60% 40% at 80% 20%, #0A3A5C14 0%, transparent 55%),
      var(--bg);
    color: var(--text);
    min-height: 100vh;
    direction: rtl;
  }
  .receipt-page { max-width: 980px; margin: 0 auto; padding: 32px 20px 60px; }

  .page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border);
  }
  .page-header h1 { font-size: 22px; font-weight: 900; letter-spacing: -0.3px; }
  .breadcrumb { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

  .btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text-muted); font-family: 'Cairo', sans-serif;
    font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
    transition: all var(--transition);
  }
  .btn-back:hover { background: var(--surface); color: var(--text); border-color: var(--accent); }

  .alert { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
  .alert-error   { background: #E06C7518; border: 1px solid #E06C7550; color: var(--danger); }
  .alert-success { background: #98C37918; border: 1px solid #98C37950; color: var(--success); }
  .alert-info    { background: #007ACC18; border: 1px solid #007ACC50; color: var(--accent); }

  /* ── Search card ── */
  .search-card {
    background: linear-gradient(145deg, #2C2F38, #252736);
    border: 1px solid var(--border);
    border-radius: 14px; margin-bottom: 20px; overflow: hidden;
    box-shadow: 0 0 0 1px #007ACC10, 0 24px 60px #00000060, inset 0 1px 0 #ffffff08;
    animation: slideUp 0.3s ease both;
  }

  .form-section {
    background: linear-gradient(145deg, #2C2F38, #252736);
    border: 1px solid var(--border);
    border-radius: 14px; margin-bottom: 20px; overflow: hidden;
    box-shadow: 0 0 0 1px #007ACC10, 0 24px 60px #00000060, inset 0 1px 0 #ffffff08;
    animation: slideUp 0.35s ease both;
  }
  .form-section:nth-child(2){animation-delay:.05s}
  .form-section:nth-child(3){animation-delay:.10s}
  .form-section:nth-child(4){animation-delay:.15s}
  .form-section:nth-child(5){animation-delay:.20s}
  .form-section:nth-child(6){animation-delay:.25s}
  .form-section:nth-child(7){animation-delay:.30s}
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
  .section-title { font-size: 14px; font-weight: 700; }
  .section-body  { padding: 22px; }

  /* ── Receipt summary bar (shown after search) ── */
  .receipt-summary {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 6px;
  }
  .summary-cell { display: flex; flex-direction: column; gap: 4px; }
  .summary-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
  .summary-value { font-size: 14px; font-weight: 700; color: var(--text); }

  .status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 11px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
    border: 1px solid currentColor;
  }

  /* Payment progress bar */
  .pay-progress { margin-top: 16px; }
  .pay-progress-labels {
    display: flex; justify-content: space-between;
    font-size: 12px; color: var(--text-muted); margin-bottom: 6px;
  }
  .pay-progress-bar {
    height: 8px; background: var(--surface-2); border-radius: 999px; overflow: hidden;
    border: 1px solid var(--border);
  }
  .pay-progress-fill {
    height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, var(--accent), var(--success));
    transition: width 0.4s ease;
  }
  .pay-stats {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 12px; margin-top: 16px; text-align: center;
  }
  .pay-stat-card {
    padding: 12px; background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius);
  }
  .pay-stat-label { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
  .pay-stat-value { font-size: 18px; font-weight: 900; }
  .pay-stat-value.success { color: var(--success); }
  .pay-stat-value.danger  { color: var(--danger); }
  .pay-stat-value.warning { color: var(--warning); }
  .pay-stat-value.accent  { color: var(--accent); }

  /* ── Forms ── */
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 22px; }
  .form-grid .full { grid-column: 1 / -1; }
  @media (max-width: 640px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }
    .pay-stats { grid-template-columns: 1fr; }
  }

  .form-field  { display: flex; flex-direction: column; gap: 7px; }
  .form-label  { font-size: 12.5px; font-weight: 700; color: var(--text-label); letter-spacing: 0.3px; text-transform: uppercase; }
  .form-label .req { color: var(--danger); margin-right: 3px; }

  .form-control {
    width: 100%; padding: 10px 14px;
    background: var(--surface-2); border: 1.5px solid var(--border);
    border-radius: var(--radius); color: var(--text);
    font-family: 'Cairo', sans-serif; font-size: 14px;
    outline: none; transition: border-color var(--transition), box-shadow var(--transition);
    appearance: none;
  }
  .form-control:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(0,122,204,0.20); }
  .form-control::placeholder { color: rgba(255,255,255,.35); }
  .form-control:disabled { opacity: 0.45; cursor: not-allowed; }
  .form-control.field-invalid { border-color: var(--danger) !important; box-shadow: 0 0 0 3px rgba(224,108,117,0.18) !important; }

  select.form-control {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' opacity='0.6'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: left 12px center; padding-left: 34px;
  }
  select.form-control option { background: var(--surface); }

  .phone-row { display: flex; gap: 8px; align-items: stretch; }
  .phone-prefix {
    display: flex; align-items: center; justify-content: center;
    min-width: 68px; padding: 10px 12px;
    background: var(--accent-dim); border: 1px solid var(--border);
    border-radius: var(--radius); color: var(--highlight);
    font-family: 'Cairo', sans-serif; font-size: 13px; font-weight: 700;
    letter-spacing: 0.5px; flex-shrink: 0; white-space: nowrap;
    transition: all var(--transition);
  }
  .phone-row .form-control { flex: 1; }

  .field-hint { font-size: 11px; color: var(--text-muted); margin-top: 2px; font-weight: 500; }

  .inline-error {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #E06C7518;
    border: 1px solid #E06C7550; border-radius: var(--radius);
    color: var(--danger); font-size: 13px; margin-top: 8px;
  }
  .inline-error.visible { display: flex; }

  .pay-warn {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #D19A6618;
    border: 1px solid #D19A6650; border-radius: var(--radius);
    color: var(--warning); font-size: 13px; margin-top: 8px;
  }
  .pay-warn.visible { display: flex; }

  .no-plans-notice {
    display: none; align-items: center; gap: 8px;
    padding: 10px 14px; background: #007ACC18;
    border: 1px solid #007ACC50; border-radius: var(--radius);
    color: var(--accent); font-size: 13px; margin-top: 8px;
  }
  .no-plans-notice.visible { display: flex; }

  #evidence-field { display: none; }
  #evidence-field.visible { display: flex; }

  .computed-field .form-control {
    background: rgba(0,122,204,0.06);
    border-color: var(--accent-dim);
    color: var(--highlight);
    font-weight: 700;
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
  .toggle-label { font-size: 13px; color: var(--text-muted); font-weight: 600; }

  /* ── Creator-reassign notice ── */
  .creator-notice {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 18px; background: #007ACC18;
    border: 1px solid #007ACC50; border-radius: var(--radius);
    color: var(--highlight); font-size: 13px; line-height: 1.7; margin-bottom: 20px;
  }
  .creator-notice .cn-icon { font-size: 20px; flex-shrink: 0; margin-top: 1px; }

  /* ── No-receipt state ── */
  .empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-muted);
  }
  .empty-state .es-icon { font-size: 48px; margin-bottom: 16px; }
  .empty-state p { font-size: 15px; font-weight: 600; }

  /* ── Fully-paid state ── */
  .fully-paid {
    display: flex; flex-direction: column; align-items: center; gap: 12px;
    padding: 40px 20px; text-align: center;
  }
  .fully-paid .fp-icon { font-size: 44px; }
  .fully-paid p { font-size: 15px; color: var(--text-muted); font-weight: 600; }

  .form-actions { display: flex; gap: 12px; justify-content: flex-end; padding: 24px 0 0; flex-wrap: wrap; }
  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px; border-radius: var(--radius);
    font-family: 'Cairo', sans-serif; font-size: 14px; font-weight: 700;
    cursor: pointer; border: none; transition: all var(--transition); text-decoration: none;
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--accent2), var(--accent)); color: #fff;
    box-shadow: 0 6px 20px #007ACC40;
  }
  .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 28px #007ACC60; }
  .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
  .btn-secondary { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
  .btn-secondary:hover { color: var(--text); border-color: var(--accent); }
  .btn-view {
    background: #98C37918; color: var(--success);
    border: 1px solid #98C37950;
  }
  .btn-view:hover { background: #98C37930; border-color: var(--success); transform: translateY(-1px); }
</style>
</head>
<body>
<div class="receipt-page">

  <!-- ── Page header ── -->
  <div class="page-header" style="flex-direction: row;">
    <div>
      <h1>💳 <?= htmlspecialchars($pageTitle) ?></h1>
      <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <a href="<?= APP_URL ?>/receipts" class="btn-back">← رجوع</a>
  </div>

  <!-- ── Flash messages ── -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= $_SESSION['flash_success'] ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div>⚠️ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>


  <!-- ══════════════════════════════════════════════════════════════
       § 1 — Search
       ══════════════════════════════════════════════════════════════ -->
  <div class="search-card">
    <div class="section-header">
      <div class="section-icon">🔍</div>
      <span class="section-title">البحث برقم الإيصال</span>
    </div>
    <div class="section-body">
      <form method="GET" action="<?= APP_URL ?>/receipt/payment-by-id"
            style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-field" style="flex:1;min-width:200px;">
          <label class="form-label">رقم الإيصال</label>
          <input
            type="text"
            name="search"
            class="form-control"
            placeholder="مثال: 42 أو 260500042"
            value="<?= htmlspecialchars($search) ?>"
            autofocus
          >
          <span class="field-hint">أدخل الرقم التسلسلي أو رقم الإيصال المنسّق</span>
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
        <?php if ($receipt): ?>
          <a href="<?= APP_URL ?>/receipt/payment-by-id" class="btn btn-secondary" style="height:42px;">✕ مسح</a>
        <?php endif; ?>
      </form>

      <?php if ($search !== '' && !$receipt && empty($errors)): ?>
        <div class="alert alert-error" style="margin-top:12px;">
          ⚠️ لم يتم العثور على إيصال بهذا الرقم.
        </div>
      <?php endif; ?>
    </div>
  </div>


<?php if ($receipt && $ns): ?>

  <?php
    $planPrice = (float)($receipt['plan_price'] ?? 0);
    $pct       = ($planPrice > 0) ? min(100, round(($ns['netPaid'] / $planPrice) * 100)) : 0;
    $st        = $receipt['receipt_status'] ?? 'not_completed';
    $stInfo    = $statusLabels[$st] ?? ['label' => $st, 'color' => 'rgba(255,255,255,0.6)'];

    // Field values — use POST values when re-rendering after error, otherwise receipt values
    $fv = function(string $key, $fallback = '') use ($receipt) {
        return htmlspecialchars($_POST[$key] ?? $receipt[$key] ?? $fallback);
    };

    $savedBranchId  = $_POST['branch_id']  ?? $receipt['branch_id']  ?? '';
    $savedPlanId    = $_POST['plan_id']     ?? $receipt['plan_id']    ?? '';
    $savedCaptainId = $_POST['captain_id'] ?? $receipt['captain_id'] ?? '';
  ?>

  <!-- Creator-reassign notice -->
  <div class="creator-notice">
    <span class="cn-icon">ℹ️</span>
    <span>
      عند تسجيل الدفعة، سيتم <strong>تحديث المسؤول عن الإيصال إلى حسابك</strong>
      وتسجيل ذلك في سجل التدقيق.
      المسؤول الحالي:
      <strong style="color:var(--text);"><?= htmlspecialchars($receipt['creator_name'] ?? '—') ?></strong>
    </span>
  </div>


  <!-- ══════════════════════════════════════════════════════════════
       § 2 — Receipt summary
       ══════════════════════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="section-icon">📄</div>
      <span class="section-title">
        ملخص الإيصال
        <span style="color:var(--text-muted);font-weight:400;margin-right:6px;">
          #<?= htmlspecialchars($receipt['receipt_ref'] ?? $receipt['id']) ?>
        </span>
      </span>
      <span class="status-pill" style="color:<?= $stInfo['color'] ?>;margin-right:auto;">
        <?= $stInfo['label'] ?>
      </span>
    </div>
    <div class="section-body">

      <div class="receipt-summary">
        <div class="summary-cell">
          <span class="summary-label">اسم العميل</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['client_name'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">الهاتف</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['phone_number'] ?? $receipt['phone'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">الفرع</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['branch_name'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">الخطة</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['plan_name'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">قيمه الاشتراك</span>
          <span class="summary-value"><?= number_format($planPrice, 2) ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">أول جلسة</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['first_session'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">آخر جلسة</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['last_session'] ?? '—') ?></span>
        </div>
        <div class="summary-cell">
          <span class="summary-label">تاريخ الإنشاء</span>
          <span class="summary-value"><?= htmlspecialchars($receipt['created_at'] ?? '—') ?></span>
        </div>
      </div>

      <!-- Payment stats -->
      <div class="pay-stats">
        <div class="pay-stat-card">
          <div class="pay-stat-label">إجمالي المدفوع</div>
          <div class="pay-stat-value success"><?= number_format($ns['grossPaid'], 2) ?></div>
        </div>
        <div class="pay-stat-card">
          <div class="pay-stat-label">إجمالي المُسترَد</div>
          <div class="pay-stat-value danger"><?= number_format($ns['totalRefunded'], 2) ?></div>
        </div>
        <div class="pay-stat-card">
          <div class="pay-stat-label">المتبقي</div>
          <div class="pay-stat-value <?= $ns['remaining'] > 0 ? 'warning' : 'success' ?>">
            <?= number_format($ns['remaining'], 2) ?>
          </div>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="pay-progress">
        <div class="pay-progress-labels">
          <span>نسبة السداد</span>
          <span style="font-weight:700;color:var(--text);"><?= $pct ?>%</span>
        </div>
        <div class="pay-progress-bar">
          <div class="pay-progress-fill" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? 'var(--success)' : 'linear-gradient(90deg,var(--accent),var(--success))' ?>;"></div>
        </div>
      </div>

    </div>
  </div>


  <?php if ($ns['remaining'] <= 0): ?>
  <!-- Fully paid state -->
  <div class="form-section">
    <div class="section-body">
      <div class="fully-paid">
        <div class="fp-icon">✅</div>
        <p>هذا الإيصال مكتمل السداد — لا يوجد مبلغ متبقٍ.</p>
        <a href="<?= APP_URL ?>/receipt/show?id=<?= (int)$receipt['id'] ?>" class="btn btn-view">
          👁 عرض الإيصال كاملاً
        </a>
      </div>
    </div>
  </div>

  <?php else: ?>

  <!-- ══════════════════════════════════════════════════════════════
       MAIN FORM — payment + editable receipt fields
       ══════════════════════════════════════════════════════════════ -->
  <form
    method="POST"
    action="<?= APP_URL ?>/receipt/payment-by-id/store"
    enctype="multipart/form-data"
    id="paymentForm"
  >
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
    <input type="hidden" name="receipt_id"  value="<?= (int)$receipt['id'] ?>">
    <input type="hidden" name="client_id"   value="<?= (int)($receipt['client_id'] ?? 0) ?>">
    <input type="hidden" name="creator_id"  value="<?= (int)($receipt['creator_id'] ?? 0) ?>">


    <!-- ══════════════════════════════════════════════════════════
         § 3 — Client data (editable)
         ══════════════════════════════════════════════════════════ -->
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
                   value="<?= htmlspecialchars($_POST['client_name'] ?? $receipt['client_name'] ?? '') ?>" required>
            <span class="field-hint">يجب إدخال 3 كلمات على الأقل</span>
            <div class="inline-error" id="name_error">❌ يجب أن يحتوي الاسم على 3 كلمات على الأقل.</div>
          </div>

          <div class="form-field">
            <label class="form-label">هاتف العميل <span class="req">*</span></label>
            <div class="phone-row">
              <span class="phone-prefix" id="phone_prefix_badge">
                <?= htmlspecialchars($phonePrefix ?: '—') ?>
              </span>
              <input type="hidden" name="country_code" id="country_code_input"
                     value="<?= htmlspecialchars($phonePrefix) ?>">
              <input type="hidden" name="full_phone"   id="full_phone_input"
                     value="<?= htmlspecialchars($receipt['phone_number'] ?? $receipt['phone'] ?? '') ?>">
              <input type="text" name="phone_local" id="phone_input" class="form-control"
                     placeholder="رقم الهاتف بدون كود الدولة"
                     inputmode="numeric" maxlength="11"
                     value="<?= htmlspecialchars($phoneLocal) ?>" required>
            </div>
            <span class="field-hint">كود الدولة يُحدَّد تلقائياً عند اختيار الفرع</span>
            <div class="inline-error" id="phone_error">
              ❌ <span id="phone_error_msg">رقم الهاتف غير صحيح.</span>
            </div>
          </div>

          <div class="form-field full">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="text" name="client_email" id="client_email_input" class="form-control"
                   placeholder="example@gmail.com"
                   value="<?= htmlspecialchars($receipt['email'] ?? '') ?>">
            <span class="field-hint">اختياري — يجب أن ينتهي بـ @gmail.com</span>
            <div class="inline-error" id="email_error">
              ❌ يجب أن يكون البريد الإلكتروني بصيغة name@gmail.com فقط.
            </div>
          </div>

          <div class="form-field">
            <label class="form-label">العمر</label>
            <input type="number" name="client_age" class="form-control"
                   placeholder="مثال: 25" min="5" max="99"
                   value="<?= htmlspecialchars($_POST['client_age'] ?? $receipt['client_age'] ?? '') ?>">
            <span class="field-hint">اختياري</span>
          </div>

          <div class="form-field">
            <label class="form-label">الجنس</label>
            <select name="client_gender" class="form-control">
              <option value="">— اختر —</option>
              <?php foreach (['ذكر', 'أنثى'] as $g): ?>
                <option value="<?= $g ?>"
                  <?= (($_POST['client_gender'] ?? $receipt['client_gender'] ?? '') === $g) ? 'selected' : '' ?>>
                  <?= $g ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="field-hint">اختياري</span>
          </div>

        </div>
      </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         § 4 — Subscription details (editable)
         ══════════════════════════════════════════════════════════ -->
    <div class="form-section">
      <div class="section-header">
        <div class="section-icon">📋</div>
        <span class="section-title">تفاصيل الاشتراك</span>
      </div>
      <div class="section-body">
        <div class="form-grid">

          <div class="form-field">
            <label class="form-label">الفرع <span class="req">*</span></label>
            <select name="branch_id" id="branch" class="form-control" required>
              <option value="">— اختر الفرع —</option>
              <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"
                  data-country-id="<?= (int)($b['country_id'] ?? 0) ?>"
                  data-country-code="<?= htmlspecialchars($b['country_code'] ?? '') ?>"
                  <?= ((string)$savedBranchId === (string)$b['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['branch_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
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
            <select name="level" class="form-control">
              <?php for ($i = 1; $i <= 6; $i++): ?>
                <option value="<?= $i ?>"
                  <?= (($_POST['level'] ?? $receipt['level'] ?? 1) == $i) ? 'selected' : '' ?>>
                  <?= $i ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>

        </div>
      </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         § 5 — Sessions (editable)
         ══════════════════════════════════════════════════════════ -->
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
                   value="<?= htmlspecialchars($_POST['first_session'] ?? $receipt['first_session'] ?? '') ?>" required>
          </div>

          <div class="form-field">
            <label class="form-label">وقت التمرين</label>
            <input type="time" name="exercise_time" id="exercise_time" class="form-control"
                   value="<?= htmlspecialchars($_POST['exercise_time'] ?? $receipt['exercise_time'] ?? '') ?>">
            <div class="inline-error" id="time_error">
              ❌ <span id="time_error_msg">وقت التمرين خارج ساعات عمل الفرع.</span>
            </div>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">تاريخ جلسة التجديد</label>
            <input type="text" name="renewal_session" id="renewal_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['renewal_session'] ?? $receipt['renewal_session'] ?? '') ?>" readonly>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">تاريخ آخر جلسة</label>
            <input type="text" name="last_session" id="last_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['last_session'] ?? $receipt['last_session'] ?? '') ?>" readonly>
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
            <span id="day_error_hint" style="font-weight:600;margin-right:4px;"></span>
          </div>

        </div>
      </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════
         § 6 — Payment
         ══════════════════════════════════════════════════════════ -->
    <div class="form-section">
      <div class="section-header">
        <div class="section-icon">💳</div>
        <span class="section-title">الدفعة الجديدة</span>
      </div>
      <div class="section-body">
        <div class="form-grid">

          <div class="form-field">
            <label class="form-label">
              المبلغ <span class="req">*</span>
              <span style="font-weight:400;color:var(--text-muted);text-transform:none;">
                (الحد الأقصى: <?= number_format($ns['remaining'], 2) ?>)
              </span>
            </label>
            <input type="text" name="amount" id="paidAmount" class="form-control"
                   placeholder="0"
                   min="<?= $minPaymentAmount ?>"
                   max="<?= $ns['remaining'] ?>"
                   step="0.01"
                   value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" required>
            <div class="pay-warn" id="pay_warn">
              ⚠️ الحد الأدنى للدفع هو
              <strong id="min_pay_display"><?= number_format($minPaymentAmount, 0) ?></strong>
              جنيه. لا يمكن المتابعة بمبلغ أقل.
            </div>
            <div class="inline-error" id="max_pay_error">
              ❌ المبلغ يتجاوز المتبقي على الإيصال.
            </div>
          </div>

          <div class="form-field computed-field">
            <label class="form-label">المتبقي بعد الدفعة</label>
            <input type="text" id="remainingAfter" class="form-control" value="—" readonly>
          </div>

          <div class="form-field">
            <label class="form-label">طريقة الدفع <span class="req">*</span></label>
            <select name="payment_method" id="payment_method" class="form-control" required>
              <option value="">— اختر —</option>
              <?php foreach (['cash' => 'نقداً', 'instapay' => 'InstaPay', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'تحويل بنكي'] as $val => $lbl): ?>
                <option value="<?= $val ?>"
                  <?= (($_POST['payment_method'] ?? '') === $val) ? 'selected' : '' ?>>
                  <?= $lbl ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-field" id="evidence-field">
            <label class="form-label">إثبات الدفع <span class="req">*</span></label>
            <input type="file" name="transaction_evidence" id="transaction_evidence"
                   class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/*">
            <span class="field-hint">صور فقط (JPG، PNG، GIF، WEBP)</span>
            <div class="inline-error" id="evidence_error">
              ❌ يُسمح بالصور فقط. الرجاء اختيار ملف صورة.
            </div>
          </div>

          <div class="form-field full">
            <label class="form-label">ملاحظات</label>
            <input type="text" name="notes" class="form-control"
                   placeholder="أي ملاحظات إضافية..."
                   value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
          </div>

        </div>
      </div>
    </div>


    <!-- ── Form actions ── -->
    <div class="form-actions">
      <a href="<?= APP_URL ?>/receipt/payment-by-id" class="btn btn-secondary">إلغاء</a>
      <a href="<?= APP_URL ?>/receipt/show?id=<?= (int)$receipt['id'] ?>"
         class="btn btn-view" target="_blank">
        👁 عرض الإيصال
      </a>
      <button type="submit" class="btn btn-primary" id="submitBtn">
        💰 تسجيل الدفعة وحفظ التعديلات
      </button>
    </div>

  </form>

  <?php endif; // remaining > 0 ?>

<?php else: ?>
  <!-- Empty state — no search yet or not found -->
  <div class="form-section">
    <div class="section-body">
      <div class="empty-state">
        <div class="es-icon">🔎</div>
        <p>أدخل رقم الإيصال أعلاه للبحث وإضافة دفعة.</p>
      </div>
    </div>
  </div>
<?php endif; ?>

</div><!-- /.receipt-page -->


<script>
// ═══════════════════════════════════════════════════════════════
//  PHP → JS data (mirrors create.php pattern exactly)
// ═══════════════════════════════════════════════════════════════
const BRANCH_META = {};
<?php foreach ($branches as $b):
    $days = [];
    foreach (['working_days1','working_days2','working_days3'] as $slot) {
        if (!empty($b[$slot])) {
            foreach (array_map('trim', explode(',', $b[$slot])) as $d) {
                if ($d !== '') $days[] = $d;
            }
        }
    }
    $days      = array_values(array_unique($days));
    $cid       = isset($b['country_id'])       ? (int)$b['country_id']   : 0;
    $cc        = isset($b['country_code'])      ? $b['country_code']      : '';
    $timeFrom  = isset($b['working_time_from']) ? substr($b['working_time_from'], 0, 5) : '';
    $timeTo    = isset($b['working_time_to'])   ? substr($b['working_time_to'],   0, 5) : '';
?>
BRANCH_META[<?= (int)$b['id'] ?>] = {
    country_id:        <?= $cid ?>,
    country_code:      <?= json_encode($cc) ?>,
    days:              <?= json_encode($days) ?>,
    working_time_from: <?= json_encode($timeFrom) ?>,
    working_time_to:   <?= json_encode($timeTo) ?>
};
<?php endforeach; ?>

const CAPTAINS_BY_BRANCH = <?= json_encode($captainsByBranch ?: new stdClass()) ?>;

const PLANS_BY_COUNTRY_ID = {};
<?php foreach ($plans as $p):
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
const MAX_PAYMENT      = <?= (float)($ns['remaining'] ?? 0) ?>;
const SAVED_PLAN_ID    = <?= json_encode((string)($savedPlanId    ?? '')) ?>;
const SAVED_CAPTAIN_ID = <?= json_encode((string)($savedCaptainId ?? '')) ?>;

// ═══════════════════════════════════════════════════════════════
//  DOM refs
// ═══════════════════════════════════════════════════════════════
const branchSel        = document.getElementById('branch');
const planSel          = document.getElementById('price');
const captainSel       = document.getElementById('captain');
const paidInput        = document.getElementById('paidAmount');
const remainingAfterIn = document.getElementById('remainingAfter');
const startDateIn      = document.getElementById('start_date');
const renewalIn        = document.getElementById('renewal_date');
const lastDateIn       = document.getElementById('last_date');
const doubleChk        = document.getElementById('double');
const dayErrorEl       = document.getElementById('day_error');
const dayErrorHint     = document.getElementById('day_error_hint');
const payMethodSel     = document.getElementById('payment_method');
const evidenceField    = document.getElementById('evidence-field');
const evidenceIn       = document.getElementById('transaction_evidence');
const evidenceErrorEl  = document.getElementById('evidence_error');
const payWarnEl        = document.getElementById('pay_warn');
const maxPayErrorEl    = document.getElementById('max_pay_error');
const noPlansNotice    = document.getElementById('no_plans_notice');
const minPayDisplay    = document.getElementById('min_pay_display');
const submitBtn        = document.getElementById('submitBtn');
const form             = document.getElementById('paymentForm');
const clientNameIn     = document.getElementById('client_name_input');
const clientEmailIn    = document.getElementById('client_email_input');
const phonePrefixBadge = document.getElementById('phone_prefix_badge');
const countryCodeIn    = document.getElementById('country_code_input');
const phoneLocalIn     = document.getElementById('phone_input');
const fullPhoneIn      = document.getElementById('full_phone_input');
const nameErrorEl      = document.getElementById('name_error');
const phoneErrorEl     = document.getElementById('phone_error');
const phoneErrorMsg    = document.getElementById('phone_error_msg');
const emailErrorEl     = document.getElementById('email_error');
const exerciseTimeIn   = document.getElementById('exercise_time');
const timeErrorEl      = document.getElementById('time_error');
const timeErrorMsg     = document.getElementById('time_error_msg');

// Guard: form may not exist if receipt is fully paid or not found
if (!form) {
    // nothing to bind
}

// ═══════════════════════════════════════════════════════════════
//  Helpers (identical to create.php)
// ═══════════════════════════════════════════════════════════════
function branchMeta() {
    return branchSel ? (BRANCH_META[branchSel.value] || null) : null;
}
function selectedSessions() {
    const opt = planSel?.options[planSel.selectedIndex];
    return parseInt(opt?.dataset.sessions) || 0;
}
function selectedPrice() {
    const opt = planSel?.options[planSel.selectedIndex];
    return parseFloat(opt?.dataset.price) || 0;
}
function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// ═══════════════════════════════════════════════════════════════
//  Name validation
// ═══════════════════════════════════════════════════════════════
function validateName() {
    if (!clientNameIn) return true;
    const words   = clientNameIn.value.trim().split(/\s+/).filter(w => w.length > 0);
    const invalid = words.length < 3;
    nameErrorEl.classList.toggle('visible', invalid);
    clientNameIn.classList.toggle('field-invalid', invalid);
    return !invalid;
}
clientNameIn?.addEventListener('input', validateName);
clientNameIn?.addEventListener('blur',  validateName);

// ═══════════════════════════════════════════════════════════════
//  Phone validation
// ═══════════════════════════════════════════════════════════════
const PHONE_RULES = {
    '+966': { regex: /^(05\d{8}|5\d{8})$/,           hint: 'مثال: 0512345678 أو 512345678' },
    '+20':  { regex: /^(01[0-9]\d{8}|1[0-9]\d{8})$/, hint: 'مثال: 01012345678 أو 1012345678' },
};
function validatePhone() {
    if (!phoneLocalIn) return true;
    const raw        = phoneLocalIn.value;
    const digitsOnly = raw.replace(/\D/g, '');
    if (raw !== digitsOnly) phoneLocalIn.value = digitsOnly;
    const prefix = countryCodeIn?.value;
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
phoneLocalIn?.addEventListener('input', validatePhone);
phoneLocalIn?.addEventListener('blur',  validatePhone);

// ═══════════════════════════════════════════════════════════════
//  Email validation
// ═══════════════════════════════════════════════════════════════
function validateEmail() {
    if (!clientEmailIn) return true;
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
clientEmailIn?.addEventListener('input', validateEmail);
clientEmailIn?.addEventListener('blur',  validateEmail);

// ═══════════════════════════════════════════════════════════════
//  Evidence validation
// ═══════════════════════════════════════════════════════════════
function validateEvidence() {
    if (!evidenceIn?.files?.length) {
        evidenceErrorEl?.classList.remove('visible');
        evidenceIn?.classList.remove('field-invalid');
        return true;
    }
    const isImage = evidenceIn.files[0].type.startsWith('image/');
    evidenceErrorEl.classList.toggle('visible', !isImage);
    evidenceIn.classList.toggle('field-invalid', !isImage);
    if (!isImage) evidenceIn.value = '';
    return isImage;
}
evidenceIn?.addEventListener('change', validateEvidence);

// ═══════════════════════════════════════════════════════════════
//  Exercise time validation
// ═══════════════════════════════════════════════════════════════
function validateExerciseTime() {
    if (!exerciseTimeIn) return true;
    timeErrorEl.classList.remove('visible');
    exerciseTimeIn.classList.remove('field-invalid');
    const time = exerciseTimeIn.value;
    if (!time) return true;
    const meta = branchMeta();
    if (!meta?.working_time_from || !meta?.working_time_to) return true;
    if (time < meta.working_time_from || time > meta.working_time_to) {
        timeErrorMsg.textContent =
            `وقت التمرين يجب أن يكون بين ${meta.working_time_from} و ${meta.working_time_to}.`;
        timeErrorEl.classList.add('visible');
        exerciseTimeIn.classList.add('field-invalid');
        return false;
    }
    return true;
}
exerciseTimeIn?.addEventListener('change', validateExerciseTime);
exerciseTimeIn?.addEventListener('blur',   validateExerciseTime);

// ═══════════════════════════════════════════════════════════════
//  Country code badge
// ═══════════════════════════════════════════════════════════════
function updateCountryCode() {
    const meta   = branchMeta();
    const prefix = meta?.country_code || '—';
    if (phonePrefixBadge) phonePrefixBadge.textContent = prefix;
    if (countryCodeIn)    countryCodeIn.value = prefix !== '—' ? prefix : '';
    validatePhone();
    assembleFullPhone();
}
function assembleFullPhone() {
    if (!fullPhoneIn) return;
    const prefix = countryCodeIn?.value || '';
    let local    = phoneLocalIn?.value.trim() || '';
    if (prefix && local.startsWith('0')) local = local.slice(1);
    fullPhoneIn.value = prefix ? (prefix + local) : local;
}

// ═══════════════════════════════════════════════════════════════
//  Plans dropdown
// ═══════════════════════════════════════════════════════════════
function populatePlans() {
    if (!planSel) return;
    const meta      = branchMeta();
    const countryId = meta?.country_id || null;
    const plans     = (countryId && PLANS_BY_COUNTRY_ID[countryId])
                        ? PLANS_BY_COUNTRY_ID[countryId] : [];

    noPlansNotice?.classList.toggle('visible', meta !== null && plans.length === 0);

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

    updatePaymentMax();
    updateSessionDates();
}

// ═══════════════════════════════════════════════════════════════
//  Captains dropdown
// ═══════════════════════════════════════════════════════════════
function populateCaptains() {
    if (!captainSel || !branchSel) return;
    const captains = CAPTAINS_BY_BRANCH[branchSel.value] || [];
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
//  Payment amount validation
// ═══════════════════════════════════════════════════════════════
function updatePaymentMax() {
    if (!paidInput) return;
    const paid = parseFloat(paidInput.value) || 0;

    // Below minimum
    const belowMin = paid > 0 && paid < MIN_PAYMENT;
    payWarnEl?.classList.toggle('visible', belowMin);

    // Above maximum (remaining balance)
    const aboveMax = MAX_PAYMENT > 0 && paid > MAX_PAYMENT;
    maxPayErrorEl?.classList.toggle('visible', aboveMax);

    // Live "remaining after" display
    if (remainingAfterIn) {
        if (paid > 0 && paid <= MAX_PAYMENT) {
            remainingAfterIn.value = Math.max(MAX_PAYMENT - paid, 0).toFixed(2);
        } else {
            remainingAfterIn.value = '—';
        }
    }

    const hasError = belowMin || aboveMax
        || dayErrorEl?.classList.contains('visible');

    if (submitBtn) submitBtn.disabled = hasError;
}
paidInput?.addEventListener('input', updatePaymentMax);

// ═══════════════════════════════════════════════════════════════
//  Session date logic (identical to create.php)
// ═══════════════════════════════════════════════════════════════
function pickActiveDays(startDayName, allowedDays, totalSessions, isDouble) {
    const idx      = allowedDays.indexOf(startDayName);
    if (idx === -1) return [];
    const pairStart = idx % 2 === 0 ? idx : idx - 1;
    const pair1     = allowedDays.slice(pairStart, pairStart + 2);
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
    if (!startDateIn || !branchSel) return;
    const startDate = startDateIn.value;
    if (renewalIn) renewalIn.value = '';
    if (lastDateIn) lastDateIn.value = '';
    dayErrorEl?.classList.remove('visible');
    if (dayErrorHint) dayErrorHint.textContent = '';

    if (!startDate || !branchSel.value) return;

    const meta = branchMeta();
    if (!meta?.days?.length) return;

    const startDayName = new Date(startDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
    if (!meta.days.includes(startDayName)) {
        if (dayErrorHint) dayErrorHint.textContent = meta.days.join('، ');
        dayErrorEl?.classList.add('visible');
        if (submitBtn) submitBtn.disabled = true;
        return;
    }

    if (submitBtn) submitBtn.disabled = false;
    updatePaymentMax();
    const total = selectedSessions();
    if (!total) return;
    const result = buildSessionDates(startDate, meta.days, total, doubleChk?.checked ?? false);
    if (renewalIn) renewalIn.value = result.renewal;
    if (lastDateIn) lastDateIn.value = result.last;
}

// ═══════════════════════════════════════════════════════════════
//  Evidence toggle
// ═══════════════════════════════════════════════════════════════
function toggleEvidence() {
    const m = payMethodSel?.value;
    if (m && m !== 'cash') {
        evidenceField?.classList.add('visible');
        if (evidenceIn) evidenceIn.required = true;
    } else {
        evidenceField?.classList.remove('visible');
        if (evidenceIn) { evidenceIn.required = false; evidenceIn.value = ''; }
        evidenceErrorEl?.classList.remove('visible');
    }
}

// ═══════════════════════════════════════════════════════════════
//  Event listeners
// ═══════════════════════════════════════════════════════════════
branchSel?.addEventListener('change', () => {
    updateCountryCode();
    populatePlans();
    populateCaptains();
    updateSessionDates();
    validateExerciseTime();
});
planSel?.addEventListener('change',     () => { updatePaymentMax(); updateSessionDates(); });
doubleChk?.addEventListener('change',   updateSessionDates);
startDateIn?.addEventListener('change', updateSessionDates);
payMethodSel?.addEventListener('change', toggleEvidence);

// ═══════════════════════════════════════════════════════════════
//  Form submit — full validation gate
// ═══════════════════════════════════════════════════════════════
form?.addEventListener('submit', e => {
    const nameOk     = validateName();
    const phoneOk    = validatePhone();
    const emailOk    = validateEmail();
    const evidenceOk = validateEvidence();
    const timeOk     = validateExerciseTime();

    const paid     = parseFloat(paidInput?.value) || 0;
    const belowMin = paid > 0 && paid < MIN_PAYMENT;
    const aboveMax = MAX_PAYMENT > 0 && paid > MAX_PAYMENT;

    if (!nameOk || !phoneOk || !emailOk || !evidenceOk || !timeOk || belowMin || aboveMax) {
        e.preventDefault();
        const firstErr = form.querySelector('.inline-error.visible, .pay-warn.visible');
        firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    assembleFullPhone();
});

// ═══════════════════════════════════════════════════════════════
//  Init
// ═══════════════════════════════════════════════════════════════
(function init() {
    if (!form) return;
    if (branchSel?.value) {
        updateCountryCode();
        populatePlans();
        populateCaptains();
    }
    toggleEvidence();
    updatePaymentMax();
    updateSessionDates();
    assembleFullPhone();
    validateExerciseTime();
    if (minPayDisplay) minPayDisplay.textContent = MIN_PAYMENT.toLocaleString('ar-EG');
})();
</script>

</body>
</html>
<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
