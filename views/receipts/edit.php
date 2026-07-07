<?php
// views/receipts/edit.php
require ROOT . '/views/includes/layout_top.php';

/*
 * Role gate
 * ─────────
 * Admin:            full edit access to everything.
 * customer_service: can ALSO edit level, first_session (start date), and
 *                    captain_id — in addition to whatever non-admins can
 *                    already touch (branch_id, plan_id, payment_method,
 *                    transaction_evidence, notes).
 * Everyone else (branch_manager, area_manager, ...):
 *                    level / first_session / captain_id are LOCKED,
 *                    same as the always-locked client fields.
 *
 * Fields LOCKED for everyone except admin:
 *   client_name, phone, client_email, client_age, client_gender          (§1)
 *   exercise_time, double                                               (§3 partials)
 *   remaining (always readonly / computed)
 *
 * Fields LOCKED for everyone except admin + customer_service:
 *   level, first_session, captain_id                                    (§2 + §3)
 *
 * Fields editable by ALL roles in edit mode:
 *   branch_id, plan_id                                                  (§2)
 *   payment_method, transaction_evidence, notes                         (§4)
 */
$isAdmin = $isAdmin ?? false;
$isEdit  = $isEdit  ?? true;
$action  = APP_URL . '/receipt/edit?id=' . $receipt['id'];

$role              = $_SESSION['user']['role'] ?? '';
$isCustomerService = ($role === 'customer_service');

// $csAllowed = true for fields that customer_service is also allowed to edit
// (level, first_session, captain_id). Defaults to false (admin-only lock).
$lock = function(bool $adminOnly, bool $csAllowed = false) use ($isAdmin, $isEdit, $isCustomerService): string {
    if ($isAdmin)                        return '';
    if ($csAllowed && $isCustomerService) return '';
    if ($isEdit && $adminOnly)           return 'disabled';
    return '';
};

// Convenience flag used for label/lock-icon rendering on the three fields
// customer_service is now allowed to touch.
$canEditCS = $isAdmin || $isCustomerService;

/*
 * total_paid  = real sum of all payment transactions (injected by controller)
 * total_refunded = real sum of all refund transactions
 * remaining   = max(0, plan_price - total_paid + total_refunded)
 *
 * Both are fetched fresh from the DB in edit() — never trust the receipt row's
 * own `amount` or `remaining` columns here.
 */
$planPrice      = (float) ($receipt['plan_price']      ?? 0);
$totalPaid      = (float) ($receipt['total_paid']      ?? 0);
$totalRefunded  = (float) ($receipt['total_refunded']  ?? 0);
$remaining      = max(0, $planPrice - $totalPaid + $totalRefunded);
?>
<style>
/* ── Shared dark theme (same palette as index.php / manage.php) ── */
:root {
    --bg:          #1E1E2D;
    --surface:     #252736;
    --surface-2:   #2C2F38;
    --border:      #3C3F58;
    --border-focus:#007ACC;
    --accent:      #007ACC;
    --accent-dim:  #0A3A5C;
    --success:     #98C379;
    --danger:      #E06C75;
    --warning:     #D19A66;
    --text:        #FFFFFF;
    --text-muted:  #ffffffb3;
    --text-label:  #ffffffb3;
    --radius:      10px;
    --transition:  0.2s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Cairo', sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 16px;
    min-height: 100vh;
    direction: rtl;
}

.receipt-page {
    max-width: 980px;
    margin: 0 auto;
    padding: 32px 20px 60px;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    min-width: 0;
}
.page-header h1 { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }
.breadcrumb     { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
}
.role-badge.admin { background: rgba(0,122,204,0.15); color: var(--accent); border: 1px solid var(--accent-dim); }
.role-badge.user  { background: rgba(255,255,255,0.08); color: var(--text-muted); border: 1px solid var(--border); }

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text-muted);
    font-family: 'Cairo', sans-serif;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition);
    flex-shrink: 0;
}
.btn-back:hover { background: var(--surface); color: var(--text); border-color: var(--accent); }

.alert {
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    line-height: 1.6;
}
.alert-error   { background: #2D1E20; border: 1px solid #5C3C40; color: #F0A8AD; }
.alert-success { background: #1E2D1E; border: 1px solid #3C5C3F; color: #C0DBA0; }
.alert-info    { background: rgba(0,122,204,0.08); border: 1px solid var(--accent-dim); color: var(--text-muted); }

/* Guard against <strong> visually merging with adjacent Arabic words —
   RTL bidi + line-wrapped source whitespace can otherwise collapse the
   gap between a word and a following/preceding bold span. */
.alert strong {
    margin: 0 3px;
    unicode-bidi: isolate;
}

.form-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 20px;
    overflow: hidden;
    animation: slideUp 0.35s ease both;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-2);
    min-width: 0;
}
.section-icon  {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: var(--accent-dim);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.section-title { font-size: 14px; font-weight: 600; color: var(--text); flex-shrink: 0; }

.section-lock {
    margin-right: auto;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--text-muted);
    background: var(--border);
    padding: 3px 10px;
    border-radius: 999px;
    white-space: nowrap;
}

.section-body  { padding: 22px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 22px; }
.form-grid .full { grid-column: 1 / -1; }

.form-field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }

.form-label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-label);
    letter-spacing: 0.3px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 6px;
}
.form-label .req  { color: var(--danger); margin-right: 3px; }
.form-label .lock { font-size: 11px; opacity: 0.7; }

.form-control {
    width: 100%;
    padding: 10px 14px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition);
    appearance: none;
}
.form-control:focus {
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(0,122,204,0.2);
}
.form-control::placeholder { color: var(--text-muted); }

select.form-control {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23A3A3A3' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: left 12px center;
    padding-left: 34px;
}

.form-control:disabled,
.form-control[disabled] {
    background: rgba(37,39,54,0.6);
    border-color: rgba(60,63,88,0.5);
    color: var(--text-muted);
    cursor: not-allowed;
    opacity: 0.75;
}
select.form-control:disabled {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666a80' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
}

.field-hint { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* Computed / read-only fields */
.computed-field .form-control {
    background: rgba(0,122,204,0.06);
    border-color: var(--accent-dim);
    color: var(--accent);
    font-weight: 600;
}

/* Green tint when fully paid */
.computed-field.paid .form-control {
    background: rgba(152,195,121,0.1);
    border-color: #3C5C3F;
    color: var(--success);
}

/* Evidence toggle */
#evidence-field         { display: none; }
#evidence-field.visible { display: flex; }

/* Toggle switch */
.toggle-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: border-color var(--transition);
}
.toggle-row:not(.locked) { cursor: pointer; user-select: none; }
.toggle-row:not(.locked):hover { border-color: var(--accent); }
.toggle-row.locked { opacity: 0.6; cursor: not-allowed; }
.toggle-row input[type="checkbox"] { display: none; }
.toggle-thumb {
    width: 38px; height: 20px;
    background: var(--border);
    border-radius: 999px;
    position: relative;
    flex-shrink: 0;
    transition: background var(--transition);
}
.toggle-thumb::after {
    content: '';
    position: absolute;
    top: 3px; right: 3px;
    width: 14px; height: 14px;
    border-radius: 50%;
    background: #fff;
    transition: transform var(--transition);
}
.toggle-row input:checked + .toggle-thumb             { background: var(--accent); }
.toggle-row input:checked + .toggle-thumb::after      { transform: translateX(-18px); }
.toggle-label { font-size: 13px; color: var(--text-muted); }

/* Payment summary banner */
.pay-summary {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    padding: 14px 18px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 20px;
}
.pay-summary-item { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.pay-summary-item .label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px; }
.pay-summary-item .value { font-size: 16px; font-weight: 700; }
.pay-summary-item .value.green  { color: var(--success); }
.pay-summary-item .value.blue   { color: var(--accent); }
.pay-summary-item .value.yellow { color: var(--warning); }

/* Submit area */
.form-actions { display: flex; gap: 12px; justify-content: flex-end; padding: 24px 0 0; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 26px;
    border-radius: var(--radius);
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all var(--transition);
    text-decoration: none;
}
.btn-primary   { background: var(--accent); color: #fff; box-shadow: 0 4px 20px rgba(0,122,204,0.35); }
.btn-primary:hover { background: #3399FF; transform: translateY(-1px); }
.btn-secondary { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
.btn-secondary:hover { color: var(--text); border-color: var(--accent); }

/* ══════════════════════════════════════════
   Responsive fixes (mobile)
   ══════════════════════════════════════════ */
@media (max-width: 640px) {
    .form-grid       { grid-template-columns: 1fr; }
    .form-grid .full { grid-column: 1; }

    /* Header stacks instead of overlapping/clipping on narrow screens */
    .page-header {
        flex-wrap: wrap;
        gap: 12px;
    }
    .btn-back { align-self: flex-start; }

    /* Section header: let the lock badge wrap onto its own line
       instead of pushing the row wider than the viewport */
    .section-header {
        flex-wrap: wrap;
        row-gap: 8px;
    }
    .section-lock {
        margin-right: 0;   /* auto-margin was what forced horizontal overflow */
        width: 100%;       /* push it onto its own line */
        white-space: normal;
        line-height: 1.5;
    }

    /* Form actions stack full-width on mobile instead of cramping */
    .form-actions {
        flex-direction: column-reverse;
    }
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }

    /* Payment summary items wrap two-per-row instead of overflowing */
    .pay-summary {
        gap: 12px;
    }
    .pay-summary-item {
        flex: 1 1 calc(50% - 12px);
    }
}
</style>

<div class="receipt-page">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>تعديل الإيصال #<?= (int)$receipt['id'] ?></h1>
            <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
            <span class="role-badge <?= $isAdmin ? 'admin' : 'user' ?>">
                <?php if ($isAdmin): ?>
                    🔓 مدير — تعديل كامل
                <?php elseif ($isCustomerService): ?>
                    🔓 خدمة العملاء — تعديل الفرع، الخطة، المستوى، الكابتن، تاريخ أول جلسة
                <?php else: ?>
                    🔒 مستخدم — تعديل محدود
                <?php endif; ?>
            </span>
        </div>
        <a href="<?= APP_URL ?>/receipts" class="btn-back">← رجوع</a>
    </div>

    <!-- Info banner for non-admins -->
    <?php if (!$isAdmin): ?>
    <div class="alert alert-info">
        <?php if ($isCustomerService): ?>
            <span>ℹ️ يمكنك تعديل <strong>الفرع</strong> و<strong>الخطة</strong>، بالإضافة إلى <strong>المستوى</strong> و<strong>تاريخ أول جلسة</strong> و<strong>الكابتن</strong>، وبيانات <strong>الدفع</strong>. بيانات العميل وباقي الحقول للقراءة فقط.</span>
        <?php else: ?>
            <span>ℹ️ يمكنك تعديل <strong>الفرع</strong> و<strong>الخطة</strong> وبيانات <strong>الدفع</strong> فقط. بيانات العميل، المستوى، تاريخ أول جلسة، والكابتن للقراءة فقط.</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Flash / Validation alerts -->
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

    <!-- ══════════════════════════════════════════
         Payment summary banner — always visible at the top
         Shows real figures fetched from transactions table
    ══════════════════════════════════════════ -->
    <div class="pay-summary">
        <div class="pay-summary-item">
            <span class="label">💰 سعر الخطة</span>
            <span class="value blue" id="summaryPlanPrice"><?= $planPrice ?></span>
        </div>
        <div class="pay-summary-item">
            <span class="label">✅ إجمالي المدفوع</span>
            <span class="value green"><?= $totalPaid ?></span>
        </div>
        <?php if ($totalRefunded > 0): ?>
        <div class="pay-summary-item">
            <span class="label">↩️ المسترد</span>
            <span class="value yellow"><?= $totalRefunded ?></span>
        </div>
        <?php endif; ?>
        <div class="pay-summary-item">
            <span class="label">⏳ المتبقي</span>
            <span class="value <?= $remaining <= 0 ? 'green' : 'yellow' ?>" id="summaryRemaining">
                <?= number_format($remaining, 2) ?>
            </span>
        </div>
        <?php if ($remaining <= 0): ?>
        <div class="pay-summary-item" style="align-self:center; margin-right:auto;">
            <span style="background:#1E2D1E;border:1px solid #3C5C3F;color:#C0DBA0;
                         padding:4px 14px;border-radius:999px;font-size:12px;font-weight:600;">
                ✅ مدفوع بالكامل
            </span>
        </div>
        <?php endif; ?>
    </div>

    <form method="POST" action="<?= $action ?>" enctype="multipart/form-data" id="receiptForm"
          data-plan-price="<?= $planPrice ?>"
          data-total-paid="<?= $totalPaid ?>"
          data-total-refunded="<?= $totalRefunded ?>">

        <!-- ══════════════════════════════════════════
             § 1 — بيانات العميل  (LOCKED for non-admin)
        ══════════════════════════════════════════ -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">👤</div>
                <span class="section-title">بيانات العميل</span>
                <?php if (!$isAdmin): ?><span class="section-lock">🔒 للقراءة فقط</span><?php endif; ?>
            </div>
            <div class="section-body">
                <div class="form-grid">

                    <div class="form-field">
                        <label class="form-label">
                            اسم العميل
                            <?= $isAdmin ? '<span class="req">*</span>' : '<span class="lock">🔒</span>' ?>
                        </label>
                        <input type="text" name="client_name" class="form-control"
                               placeholder="الاسم الكامل"
                               value="<?= htmlspecialchars($receipt['client_name'] ?? '') ?>"
                               <?= $lock(true) ?>
                               <?= $isAdmin ? 'required' : '' ?>>
                    </div>
                    <input type="hidden" name="client_id" value="<?= (int)$receipt['client_id'] ?>">
                    <?php if ($isAdmin): ?>
                    <div class="form-field">
                        <label class="form-label">هاتف العميل <span class="req">*</span></label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="رقم الهاتف"
                               value="<?= htmlspecialchars($receipt['phone_number'] ?? $receipt['phone'] ?? '') ?>"
                               required>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="phone"
                               value="<?= htmlspecialchars($receipt['phone_number'] ?? $receipt['phone'] ?? '') ?>">
                    <?php endif; ?>

                    <!-- Email — admin editable, non-admin read-only display -->
                    <div class="form-field">
                        <label class="form-label">
                            البريد الإلكتروني
                            <?= !$isAdmin ? '<span class="lock">🔒</span>' : '' ?>
                        </label>
                        <input type="text" name="client_email" class="form-control"
                               placeholder="example@gmail.com"
                               value="<?= htmlspecialchars($receipt['client_email'] ?? '') ?>"
                               <?= $lock(true) ?>>
                    </div>

                    <!-- Age -->
                    <div class="form-field">
                        <label class="form-label">
                            العمر
                            <?= !$isAdmin ? '<span class="lock">🔒</span>' : '' ?>
                        </label>
                        <input type="number" name="client_age" class="form-control"
                               placeholder="مثال: 25" min="5" max="99"
                               value="<?= htmlspecialchars($receipt['age'] ?? '') ?>"
                               <?= $lock(true) ?>>
                    </div>

                    <!-- Gender -->
                    <div class="form-field">
                        <label class="form-label">
                            الجنس
                            <?= !$isAdmin ? '<span class="lock">🔒</span>' : '' ?>
                        </label>
                        <select name="client_gender" class="form-control" <?= $lock(true) ?>>
                            <option value="">— اختر —</option>
                            <option value="ذكر"   <?= ($receipt['gender'] ?? '') === 'ذكر'   ? 'selected' : '' ?>>ذكر</option>
                            <option value="أنثى" <?= ($receipt['gender'] ?? '') === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             § 2 — تفاصيل الاشتراك
             branch_id / plan_id: editable by everyone.
             level / captain_id : admin + customer_service only.
        ══════════════════════════════════════════ -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">📋</div>
                <span class="section-title">تفاصيل الاشتراك</span>
                <?php if (!$canEditCS): ?>
                    <span class="section-lock">🔒 المستوى والكابتن لخدمة العملاء والمدير فقط</span>
                <?php endif; ?>
            </div>
            <div class="section-body">
                <div class="form-grid">


                <div class="form-field">
    <label class="form-label">الفرع <span class="req">*</span></label>
    <select name="branch_id" id="branch" class="form-control" required>
        <option value="">— اختر الفرع —</option>
        <?php foreach (($branches ?? []) as $b): ?>
            <option value="<?= $b['id'] ?>"
                <?= (string)($receipt['branch_id'] ?? $receipt['branch'] ?? '') === (string)$b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['branch_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <div class="form-field">
                        <label class="form-label">الخطة / العرض <span class="req">*</span></label>
                        <select name="plan_id" id="planSelect" class="form-control" required>
                            <option value="">— اختر الخطة —</option>
                            <?php foreach (($plans ?? []) as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    data-price="<?= (float) $p['price'] ?>"
                                    data-sessions="<?= (int) $p['number_of_sessions'] ?>"
                                    <?= (string)($receipt['plan_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            الكابتن
                            <?= !$canEditCS ? '<span class="lock">🔒</span>' : '' ?>
                        </label>
                        <select name="captain_id" id="captain" class="form-control" <?= $lock(true, true) ?>>
                            <option value="">— اختر الكابتن —</option>
                            <?php foreach (($captains ?? []) as $ca): ?>
                                <option value="<?= $ca['id'] ?>"
                                    <?= (string)($receipt['captain_id'] ?? '') === (string)$ca['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ca['captain_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            المستوى
                            <?= $canEditCS ? '<span class="req">*</span>' : '<span class="lock">🔒</span>' ?>
                        </label>
                        <select name="level" class="form-control" <?= $lock(true, true) ?> <?= $canEditCS ? 'required' : '' ?>>
                            <option value="">— اختر المستوى —</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>"
                                    <?= (string)($receipt['level'] ?? '') === (string)$i ? 'selected' : '' ?>>
                                    Level <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             § 3 — الجلسات
             first_session : admin + customer_service only.
             exercise_time / double : admin only.
        ══════════════════════════════════════════ -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">📅</div>
                <span class="section-title">الجلسات</span>
                <?php if (!$isAdmin): ?>
                    <span class="section-lock">
                        <?= $isCustomerService
                            ? '🔒 وقت التمرين والجلسة المزدوجة للمدير فقط'
                            : '🔒 تاريخ أول جلسة، وقت التمرين، والجلسة المزدوجة للمدير وخدمة العملاء فقط' ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="section-body">
                <div class="form-grid">

                    <div class="form-field">
                        <label class="form-label">
                            تاريخ أول جلسة
                            <?= $canEditCS ? '<span class="req">*</span>' : '<span class="lock">🔒</span>' ?>
                        </label>
                        <input type="date" name="first_session" id="start_date" class="form-control"
                               value="<?= htmlspecialchars($receipt['first_session'] ?? '') ?>"
                               <?= $lock(true, true) ?>
                               <?= $canEditCS ? 'required' : '' ?>>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            وقت التمرين
                            <?= !$isAdmin ? '<span class="lock">🔒</span>' : '' ?>
                        </label>
                        <input type="time" name="exercise_time" class="form-control"
                               value="<?= htmlspecialchars($receipt['exercise_time'] ?? '') ?>"
                               <?= $lock(true) ?>>
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
                        <?php if ($isAdmin): ?>
                            <label class="toggle-row" for="double">
                                <input type="checkbox" name="double" id="double"
                                       <?= !empty($receipt['double']) ? 'checked' : '' ?>>
                                <span class="toggle-thumb"></span>
                                <span class="toggle-label">جلستان في اليوم (Double Session)</span>
                            </label>
                        <?php else: ?>
                            <label class="toggle-row locked">
                                <input type="checkbox" name="double" id="double"
                                       <?= !empty($receipt['double']) ? 'checked' : '' ?> disabled>
                                <span class="toggle-thumb"></span>
                                <span class="toggle-label">جلستان في اليوم (Double Session) 🔒</span>
                            </label>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             § 4 — الدفع
             NOTE: "amount" here is NOT editable — real totals come from
             the transactions table and are shown read-only above.
             This section only lets the user update payment_method / evidence / notes.
        ══════════════════════════════════════════ -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon">💳</div>
                <span class="section-title">الدفع</span>
            </div>
            <div class="section-body">
                <div class="form-grid">

                    <!-- سعر الخطة — updates when plan changes -->
                    <div class="form-field computed-field">
                        <label class="form-label">سعر الخطة</label>
                        <input type="number" id="planPriceDisplay" class="form-control"
                               value="<?= $planPrice ?>" readonly>
                        <span class="field-hint">يُحدَّث تلقائياً عند تغيير الخطة</span>
                    </div>

                    <!-- إجمالي المدفوع — fixed, from transactions -->
                    <div class="form-field computed-field <?= $totalPaid >= $planPrice && $planPrice > 0 ? 'paid' : '' ?>">
                        <label class="form-label">إجمالي المدفوع</label>
                        <input type="number" id="totalPaidDisplay" class="form-control"
                               value="<?= $totalPaid ?>" readonly>
                        <span class="field-hint">مجموع جميع الدفعات المسجّلة</span>
                    </div>

                    <!-- المتبقي — recomputes when plan changes -->
                    <div class="form-field computed-field <?= $remaining <= 0 ? 'paid' : '' ?>">
                        <label class="form-label">المتبقي</label>
                        <input type="number" name="remaining" id="remainingAmount"
                               class="form-control" value="<?= $remaining ?>" min="0" readonly>
                        <span class="field-hint">= سعر الخطة − إجمالي المدفوع</span>
                    </div>

                    <!-- طريقة الدفع -->
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

                    <!-- إثبات الدفع -->
                    <div class="form-field" id="evidence-field">
                        <label class="form-label">إثبات الدفع</label>
                        <input type="file" name="transaction_evidence" id="transaction_evidence"
                               class="form-control" accept="image/*,application/pdf">
                        <span class="field-hint">صورة أو ملف PDF</span>
                        <?php if (!empty($receipt['transaction_evidence'])): ?>
                            <span class="field-hint">
                                الملف الحالي:
                                <a href="<?= htmlspecialchars($receipt['transaction_evidence']) ?>"
                                   target="_blank" style="color:var(--accent)">عرض الملف</a>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- ملاحظات -->
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
            <a href="<?= APP_URL ?>/receipts" class="btn btn-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
        </div>

    </form>
</div>

<script>
(function () {
    // ── Payment evidence toggle ──────────────────────────────────────────────
    const pmSelect  = document.getElementById('payment_method');
    const evidField = document.getElementById('evidence-field');

    function toggleEvidence() {
        const val = pmSelect ? pmSelect.value : '';
        evidField.classList.toggle('visible', val !== '' && val !== 'cash');
    }

    if (pmSelect) {
        pmSelect.addEventListener('change', toggleEvidence);
        toggleEvidence();
    }

    // ── Remaining auto-compute when plan changes ─────────────────────────────
    // remaining = plan_price - total_paid + total_refunded
    // total_paid and total_refunded are FIXED for this session (from server).

    const form          = document.getElementById('receiptForm');
    const planSelect    = document.getElementById('planSelect');
    const planPriceDisp = document.getElementById('planPriceDisplay');
    const remainInput   = document.getElementById('remainingAmount');
    const summaryPrice  = document.getElementById('summaryPlanPrice');
    const summaryRem    = document.getElementById('summaryRemaining');

    const totalPaid      = parseFloat(form.dataset.totalPaid)      || 0;
    const totalRefunded  = parseFloat(form.dataset.totalRefunded)  || 0;

    function updateRemaining() {
        const opt   = planSelect ? planSelect.options[planSelect.selectedIndex] : null;
        const price = opt ? (parseFloat(opt.dataset.price) || 0) : 0;
        const rem   = Math.max(0, price - totalPaid + totalRefunded);

        if (planPriceDisp) planPriceDisp.value   = price.toFixed(2);
        if (remainInput)   remainInput.value      = rem.toFixed(2);
        if (summaryPrice)  summaryPrice.textContent = price.toLocaleString('en-EG', {minimumFractionDigits: 2});
        if (summaryRem)    summaryRem.textContent  = rem.toLocaleString('en-EG', {minimumFractionDigits: 2});

        // Colour the remaining badge
        if (summaryRem) {
            summaryRem.className = 'value ' + (rem <= 0 ? 'green' : 'yellow');
        }
    }

    if (planSelect) {
        planSelect.addEventListener('change', updateRemaining);
        updateRemaining(); // run on load
    }
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>