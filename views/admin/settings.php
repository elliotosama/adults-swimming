<?php
/*
 * views/admin/settings.php
 * ─────────────────────────────────────────────────────────────
 * Admin-only page to manage system settings such as
 * the minimum payment amount enforced on receipt creation.
 *
 * Controller injects:
 *   $settings  – associative array  key => value  from the settings table
 *   $saved     – bool  true when the form was just saved successfully
 */
auth_require(['admin']);   // only admins reach this view
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إعدادات النظام</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
  .settings-page { max-width: 720px; margin: 0 auto; padding: 32px 20px 60px; }

  .page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border);
  }
  .page-header h1 { font-size: 22px; font-weight: 700; }
  .breadcrumb     { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

  .btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--text-muted); font-size: 13px; text-decoration: none;
    transition: all var(--transition);
  }
  .btn-back:hover { background: var(--surface); color: var(--text); border-color: var(--accent); }

  .alert { padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
  .alert-success { background: #0f2a1a; border: 1px solid #1a5c30; color: #86efac; }
  .alert-error   { background: #2a1515; border: 1px solid #5a2020; color: #fca5a5; }

  .settings-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 20px;
    animation: slideUp 0.3s ease both;
  }
  @keyframes slideUp {
    from { opacity:0; transform: translateY(14px); }
    to   { opacity:1; transform: translateY(0); }
  }
  .card-header {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 22px; border-bottom: 1px solid var(--border);
    background: var(--surface-2);
  }
  .card-icon  { width:32px; height:32px; border-radius:8px; background:var(--accent-dim); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
  .card-title { font-size:14px; font-weight:600; }
  .card-body  { padding: 22px; }

  .setting-row {
    display: flex; flex-direction: column; gap: 8px;
    padding: 18px 0;
    border-bottom: 1px solid var(--border);
  }
  .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
  .setting-row:first-child { padding-top: 0; }

  .setting-label {
    font-size: 13px; font-weight: 700; color: var(--text);
  }
  .setting-desc {
    font-size: 12px; color: var(--text-muted); line-height: 1.6;
  }
  .setting-input-row {
    display: flex; gap: 10px; align-items: center; margin-top: 4px;
  }
  .setting-prefix {
    padding: 9px 14px;
    background: var(--accent-dim);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--accent);
    font-weight: 700; font-size: 13px;
    white-space: nowrap;
  }

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

  .form-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 24px; }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px; border-radius: var(--radius);
    font-family: 'Cairo', sans-serif; font-size: 14px; font-weight: 600;
    cursor: pointer; border: none; transition: all var(--transition); text-decoration: none;
  }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 4px 20px rgba(79,124,255,0.35); }
  .btn-primary:hover { background: #3a68e8; transform: translateY(-1px); }
  .btn-secondary { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
  .btn-secondary:hover { color: var(--text); border-color: var(--accent); }

  /* Current value chip */
  .current-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(79,124,255,0.1); border: 1px solid var(--accent-dim);
    color: var(--accent); padding: 3px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
  }
</style>
</head>
<body>
<div class="settings-page">

  <div class="page-header">
    <div>
      <h1>⚙️ إعدادات النظام</h1>
      <p class="breadcrumb">لوحة التحكم · الإعدادات</p>
    </div>
    <a href="<?= APP_URL ?>/dashboard" class="btn-back">← رجوع</a>
  </div>

  <?php if (!empty($saved)): ?>
    <div class="alert alert-success">✅ تم حفظ الإعدادات بنجاح.</div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <form method="POST" action="<?= APP_URL ?>/admin/settings">

    <!-- ── Payment Settings ── -->
    <div class="settings-card">
      <div class="card-header">
        <div class="card-icon">💳</div>
        <span class="card-title">إعدادات الدفع</span>
      </div>
      <div class="card-body">

        <!-- Minimum payment amount -->
        <div class="setting-row">
          <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <span class="setting-label">الحد الأدنى للدفع</span>
            <span class="current-chip">
              الحالي:
              <?= number_format((float)($settings['min_payment_amount'] ?? 400), 0) ?>
              جنيه
            </span>
          </div>
          <p class="setting-desc">
            الحد الأدنى للمبلغ الذي يمكن قبوله عند إنشاء إيصال جديد أو تجديد اشتراك.
            لا يمكن لأي موظف إتمام إيصال بمبلغ أقل من هذه القيمة.
          </p>
          <div class="setting-input-row">
            <span class="setting-prefix">جنيه</span>
            <input type="number"
                   name="settings[min_payment_amount]"
                   class="form-control"
                   value="<?= htmlspecialchars($settings['min_payment_amount'] ?? '400') ?>"
                   min="0" step="1" required
                   placeholder="400">
          </div>
        </div>

        <!--
          Add more payment-related settings here in the future,
          e.g. max_discount_percent, late_fee_amount, etc.
        -->

      </div>
    </div>

    <!-- ── Future setting sections go here ── -->

    <div class="form-actions">
      <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">إلغاء</a>
      <button type="submit" class="btn btn-primary">💾 حفظ الإعدادات</button>
    </div>

  </form>
</div>
</body>
</html>
