<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل الفرع</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا الفرع؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--color-border-secondary);background:transparent;cursor:pointer;font-size:.9rem;color:black;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;transition:background .15s">تعطيل</button>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
#confirmModal.open { display:flex; }
</style>

<script>
let _pendingForm = null;

function showDeleteModal(form) {
    _pendingForm = form;
    const modal = document.getElementById('confirmModal');
    modal.classList.add('open');
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('open');
    modal.style.display = 'none';
    _pendingForm = null;
}

document.getElementById('confirmBtn').addEventListener('click', function () {
    if (_pendingForm) _pendingForm.submit();
    closeModal();
});

document.getElementById('confirmModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});
</script>

// your content



<form method="POST"
      action="<?= APP_URL ?>/admin/branch/delete?id=<?= $b['id'] ?>"
      style="display:inline"
      onsubmit="event.preventDefault(); showDeleteModal(this);">
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
</form>





function to add in controller {
    public function operations() {
    $tab = $_GET['tab'] ?? 'payment';

    // Shared across all tabs
    $data = ['activeTab' => $tab];

    if ($tab === 'payment') {
        $paySearch          = trim($_GET['search'] ?? '');
        $payClient          = $paySearch ? $this->clients->findByNameOrPhone($paySearch) : null;
        $data['paySearch']  = $paySearch;
        $data['payClient']  = $payClient;
        $data['payReceipts'] = $payClient
            ? $this->receipts->findByClientWithTotals($payClient['id'])
            : [];

    } elseif ($tab === 'refund') {
        $refSearch          = trim($_GET['search'] ?? '');
        $refClient          = $refSearch ? $this->clients->findByNameOrPhone($refSearch) : null;
        $data['refSearch']  = $refSearch;
        $data['refClient']  = $refClient;
        $data['refReceipts'] = $refClient
            ? $this->receipts->findByClientId($refClient['id'])
            : [];

    } elseif ($tab === 'renew') {
        // Everything create.php needs
        $data['isRenewal']       = true;
        $data['isEdit']          = false;
        $data['receipt']         = [];
        $data['branches']        = $this->branches->getAllVisible();
        $data['plans']           = $this->prices->getAll();
        $data['captainsByBranch'] = $this->captains->groupedByBranch();
        $data['search']          = trim($_GET['search'] ?? '');
        $data['client']          = $data['search']
            ? $this->clients->findByNameOrPhone($data['search'])
            : null;
        $data['breadcrumb']      = 'العمليات · تجديد عميل';
        $data['pageTitle']       = 'تجديد عميل';
    }

    $this->render('receipts/operations', $data);
}
}


$router->get('/receipt/payment', fn() => header('Location: /receipt/operations?tab=payment'));
$router->get('/receipt/renew',   fn() => header('Location: /receipt/operations?tab=renew'));
$router->get('/receipt/refund',  fn() => header('Location: /receipt/operations?tab=refund'));













operations.php => {
    <?php // views/receipts/operations.php
require ROOT . '/views/includes/layout_top.php';

// Which tab is active? Default to 'payment'
$activeTab = $_GET['tab'] ?? 'payment';
if (!in_array($activeTab, ['payment', 'renew', 'refund'])) {
    $activeTab = 'payment';
}
?>
<style>
/* ── Tab bar ── */
.ops-tabs {
    display: flex;
    gap: 4px;
    background: var(--surface-2, #0d1821);
    border: 1px solid var(--border, #1a2e42);
    border-radius: 14px;
    padding: 5px;
    margin-bottom: 28px;
    max-width: 860px;
    margin-left: auto;
    margin-right: auto;
}

.ops-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: var(--text-muted, #5a7a96);
    font-family: 'Tajawal', 'Cairo', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background .18s, color .18s, box-shadow .18s;
    white-space: nowrap;
}

.ops-tab:hover {
    background: var(--surface, #111d2b);
    color: var(--text, #e0eaf4);
}

.ops-tab.active-payment {
    background: #0a2a3a;
    color: #00b4d8;
    box-shadow: 0 0 0 1px #00b4d840;
}

.ops-tab.active-renew {
    background: #0f2a1a;
    color: #34c789;
    box-shadow: 0 0 0 1px #34c78940;
}

.ops-tab.active-refund {
    background: #2a1515;
    color: #e05c5c;
    box-shadow: 0 0 0 1px #e05c5c40;
}

.ops-tab .tab-icon {
    font-size: 16px;
    line-height: 1;
}

/* ── Panels ── */
.ops-panel { display: none; }
.ops-panel.active { display: block; }

/* ── Page header inside panel ── */
.ops-page-header {
    max-width: 860px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ops-page-header h1 { font-size: 20px; font-weight: 700; }
.ops-page-header .breadcrumb {
    font-size: 12px;
    color: var(--text-muted, #5a7a96);
    margin-top: 3px;
}

/* ── Outer wrapper ── */
.ops-wrap {
    padding: 28px 20px 60px;
}
.ops-inner {
    max-width: 860px;
    margin: 0 auto;
}
</style>

<div class="ops-wrap">

    <!-- ── Tab bar ── -->
    <div class="ops-tabs">
        <a href="?tab=payment"
           class="ops-tab <?= $activeTab === 'payment' ? 'active-payment' : '' ?>">
            <span class="tab-icon">💳</span> دفع باقي
        </a>
        <a href="?tab=renew"
           class="ops-tab <?= $activeTab === 'renew' ? 'active-renew' : '' ?>">
            <span class="tab-icon">🔄</span> تجديد عميل
        </a>
        <a href="?tab=refund"
           class="ops-tab <?= $activeTab === 'refund' ? 'active-refund' : '' ?>">
            <span class="tab-icon">↩️</span> استرداد
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════
         TAB 1 — Payment (دفع باقي)
    ════════════════════════════════════════════════ -->
    <div class="ops-panel <?= $activeTab === 'payment' ? 'active' : '' ?>">
        <?php if ($activeTab === 'payment'): ?>
        <?php
            // The payment controller sets: $client, $receipts, $search, $breadcrumb
            // Those are already available since the controller loaded them before
            // including this view.
        ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="ops-inner">
                <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Client search -->
        <div class="ops-inner">
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">🔍</div>
                    <span class="section-title">البحث عن العميل</span>
                </div>
                <div class="section-body">
                    <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;">
                        <input type="hidden" name="tab" value="payment">
                        <div class="form-field" style="flex:1;">
                            <label class="form-label">ابحث بالاسم أو رقم الهاتف</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="مثال: أحمد محمد أو 01012345678"
                                   value="<?= htmlspecialchars($paySearch ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
                    </form>

                    <?php if (!empty($paySearch) && empty($payClient)): ?>
                        <div class="alert alert-error" style="margin-top:12px;">⚠️ لم يتم العثور على عميل.</div>
                    <?php endif; ?>

                    <?php if (!empty($payClient)): ?>
                        <div style="margin-top:16px;padding:14px;background:var(--surface-2);
                                    border:1px solid var(--border);border-radius:var(--radius);
                                    display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                            <div>
                                <div style="font-size:11px;color:var(--text-muted);">الاسم</div>
                                <div style="font-weight:700;"><?= htmlspecialchars($payClient['client_name']) ?></div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--text-muted);">الهاتف</div>
                                <div style="font-weight:700;"><?= htmlspecialchars($payClient['phone'] ?? '—') ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($payReceipts)): ?>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
                اختر الإيصال الذي تريد إضافة دفعة عليه:
            </div>
            <div class="receipt-pick" id="payReceiptPick">
                <?php foreach ($payReceipts as $r):
                    $planPrice = (float)($r['plan_price'] ?? 0);
                    $totalPaid = (float)($r['total_paid'] ?? 0);
                    $remaining = max(0, $planPrice - $totalPaid);
                ?>
                <div class="receipt-card"
                     data-id="<?= $r['id'] ?>"
                     onclick="paySelectReceipt(<?= $r['id'] ?>, <?= $remaining ?>, <?= $planPrice ?>)">
                    <div class="receipt-card-header">
                        <span style="font-weight:700;">#<?= $r['id'] ?> — <?= htmlspecialchars($r['plan_name'] ?? '—') ?></span>
                        <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></span>
                        <?php
                            $st = $r['receipt_status'] ?? '';
                            $stColors = ['completed'=>'#22c55e','not_completed'=>'#fbbf24','pending'=>'#818cf8'];
                            $stLabels = ['completed'=>'مكتمل','not_completed'=>'غير مكتمل','pending'=>'معلّق'];
                        ?>
                        <span style="font-size:11px;font-weight:700;color:<?= $stColors[$st] ?? 'var(--text-muted)' ?>;">
                            <?= $stLabels[$st] ?? $st ?>
                        </span>
                    </div>
                    <div class="receipt-card-body">
                        <div class="rc-item"><label>أول جلسة</label><span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span></div>
                        <div class="rc-item"><label>آخر جلسة</label><span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span></div>
                        <div class="rc-item"><label>سعر الخطة</label><span><?= number_format($planPrice, 0) ?></span></div>
                        <div class="rc-item"><label>المدفوع</label><span style="color:var(--success);"><?= number_format($totalPaid, 0) ?></span></div>
                        <div class="rc-item"><label>المتبقي</label>
                            <span style="color:<?= $remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= number_format($remaining, 0) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="<?= APP_URL ?>/receipt/payment"
                  id="paymentForm" style="display:none;margin-top:24px;" enctype="multipart/form-data">
                <input type="hidden" name="receipt_id" id="paySelectedReceiptId">
                <input type="hidden" name="search" value="<?= htmlspecialchars($paySearch ?? '') ?>">
                <input type="hidden" name="_tab" value="payment">

                <div class="payment-form">
                    <div class="payment-form-header">💳 تفاصيل الدفعة</div>
                    <div class="payment-form-body">
                        <div class="form-grid-3">
                            <div class="form-field">
                                <label class="form-label">المبلغ <span style="color:var(--danger);">*</span></label>
                                <input type="number" name="amount" id="payAmount"
                                       class="form-control" placeholder="0" min="1" step="0.01" required>
                                <span class="field-hint">المتبقي: <strong id="payCurrentRemaining">—</strong></span>
                            </div>
                            <div class="form-field">
                                <label class="form-label">طريقة الدفع <span style="color:var(--danger);">*</span></label>
                                <select name="payment_method" id="payMethodSelect"
                                        class="form-control" required
                                        onchange="togglePayEvidence(this.value)">
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
                            <div class="form-field" id="pay-evidence-field" style="display:none;flex-direction:column;gap:6px;">
                                <label class="form-label">إثبات الدفع <span style="color:var(--danger);">*</span></label>
                                <input type="file" name="transaction_evidence" id="payEvidence"
                                       class="form-control" accept="image/*,application/pdf">
                                <span class="field-hint">صورة أو PDF (مطلوب للدفع الإلكتروني)</span>
                            </div>
                        </div>
                        <div style="margin-top:18px;display:flex;gap:10px;">
                            <button type="submit" class="btn btn-primary">💾 تسجيل الدفعة</button>
                            <button type="button" class="btn btn-secondary"
                                    onclick="document.getElementById('paymentForm').style.display='none'">إلغاء</button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         TAB 2 — Renew (تجديد عميل)
    ════════════════════════════════════════════════ -->
    <div class="ops-panel <?= $activeTab === 'renew' ? 'active' : '' ?>">
        <?php if ($activeTab === 'renew'):
            // The controller sets $isRenewal = true and loads the same vars as create.
            // We just include the create view logic inline.
            // Pass $isRenewal so create.php renders in renewal mode.
            require ROOT . '/views/receipts/create.php';
        endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         TAB 3 — Refund (استرداد)
    ════════════════════════════════════════════════ -->
    <div class="ops-panel <?= $activeTab === 'refund' ? 'active' : '' ?>">
        <?php if ($activeTab === 'refund'): ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="ops-inner">
                <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <div class="ops-inner">
            <!-- Client search -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">🔍</div>
                    <span class="section-title">البحث عن العميل</span>
                </div>
                <div class="section-body">
                    <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;">
                        <input type="hidden" name="tab" value="refund">
                        <div class="form-field" style="flex:1;">
                            <label class="form-label">ابحث بالاسم أو رقم الهاتف</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="مثال: أحمد محمد أو 01012345678"
                                   value="<?= htmlspecialchars($refSearch ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:42px;">🔍 بحث</button>
                    </form>

                    <?php if (!empty($refSearch) && empty($refClient)): ?>
                        <div class="alert alert-error" style="margin-top:12px;">⚠️ لم يتم العثور على عميل.</div>
                    <?php elseif (!empty($refClient) && empty($refReceipts)): ?>
                        <div class="alert alert-error" style="margin-top:12px;">⚠️ لا توجد إيصالات لهذا العميل.</div>
                    <?php endif; ?>

                    <?php if (!empty($refClient)): ?>
                        <div style="margin-top:16px;padding:14px;background:var(--surface-2);
                                    border:1px solid var(--border);border-radius:var(--radius);
                                    display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                            <div>
                                <div style="font-size:11px;color:var(--text-muted);">الاسم</div>
                                <div style="font-weight:700;"><?= htmlspecialchars($refClient['client_name']) ?></div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--text-muted);">الهاتف</div>
                                <div style="font-weight:700;"><?= htmlspecialchars($refClient['phone'] ?? '—') ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($refReceipts)): ?>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
                اختر الإيصال الذي تريد استرداد مبلغ منه:
            </div>
            <div class="receipt-pick" id="refReceiptPick">
                <?php foreach ($refReceipts as $r):
                    $planPrice = (float)($r['plan_price'] ?? 0);
                    $totalPaid = (float)($r['total_paid'] ?? 0);
                    $rem = $planPrice > 0 ? max(0, $planPrice - $totalPaid) : (float)($r['remaining'] ?? 0);
                ?>
                <div class="receipt-card receipt-card--refund"
                     data-id="<?= $r['id'] ?>"
                     onclick="refSelectReceipt(<?= $r['id'] ?>, <?= $totalPaid ?>)">
                    <div class="receipt-card-header">
                        <span style="font-weight:700;">#<?= $r['id'] ?> — <?= htmlspecialchars($r['client_name'] ?? '—') ?></span>
                        <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['branch_name'] ?? '—') ?></span>
                        <?php
                            $st = $r['receipt_status'] ?? '';
                            $stColors = ['completed'=>'#22c55e','not_completed'=>'#fbbf24','pending'=>'#818cf8'];
                            $stLabels = ['completed'=>'مكتمل','not_completed'=>'غير مكتمل','pending'=>'معلّق'];
                        ?>
                        <span style="font-size:11px;font-weight:700;color:<?= $stColors[$st] ?? 'var(--text-muted)' ?>;">
                            <?= $stLabels[$st] ?? $st ?>
                        </span>
                    </div>
                    <div class="receipt-card-body">
                        <div class="rc-item"><label>الخطة</label><span><?= htmlspecialchars($r['plan_name'] ?? '—') ?></span></div>
                        <div class="rc-item"><label>أول جلسة</label><span><?= htmlspecialchars($r['first_session'] ?? '—') ?></span></div>
                        <div class="rc-item"><label>آخر جلسة</label><span><?= htmlspecialchars($r['last_session'] ?? '—') ?></span></div>
                        <div class="rc-item"><label>المدفوع</label><span style="color:var(--success);"><?= number_format($totalPaid, 0) ?></span></div>
                        <div class="rc-item"><label>المتبقي</label>
                            <span style="color:<?= $rem > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= number_format($rem, 0) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="refundFormOuter" style="display:none;margin-top:24px;">
                <form method="POST" action="<?= APP_URL ?>/receipt/refund"
                      id="refundForm" enctype="multipart/form-data">
                    <input type="hidden" name="receipt_id" id="refSelectedReceiptId">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($refSearch ?? '') ?>">
                    <input type="hidden" name="_tab" value="refund">

                    <div class="refund-form-inner">
                        <div class="refund-form-header">↩️ تفاصيل الاسترداد</div>
                        <div class="refund-form-body">
                            <div class="form-grid-3">
                                <div class="form-field">
                                    <label class="form-label">المبلغ المُسترَد <span style="color:var(--danger);">*</span></label>
                                    <input type="number" name="amount" id="refAmount"
                                           class="form-control" placeholder="0" min="1" step="0.01" required>
                                    <span class="field-hint">إجمالي المدفوع: <strong id="refCurrentTotalPaid">—</strong></span>
                                </div>
                                <div class="form-field">
                                    <label class="form-label">طريقة الاسترداد <span style="color:var(--danger);">*</span></label>
                                    <select name="payment_method" id="refMethodSelect"
                                            class="form-control" required
                                            onchange="toggleRefundEvidence(this.value)">
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
                            <div style="margin-top:16px;">
                                <div class="form-field" id="refund-evidence-field" style="display:none;flex-direction:column;gap:6px;">
                                    <label class="form-label">إثبات الاسترداد <span style="color:var(--danger);">*</span></label>
                                    <input type="file" name="transaction_evidence" id="refEvidence"
                                           class="form-control" accept="image/*,application/pdf">
                                    <span class="field-hint">صورة أو PDF (مطلوب للاسترداد الإلكتروني)</span>
                                </div>
                            </div>
                            <div style="margin-top:18px;display:flex;gap:10px;">
                                <button type="submit" class="btn btn-primary"
                                        style="background:var(--danger);box-shadow:0 4px 20px rgba(224,92,92,.3);">
                                    ↩️ تأكيد الاسترداد
                                </button>
                                <button type="button" class="btn btn-secondary"
                                        onclick="document.getElementById('refundFormOuter').style.display='none'">إلغاء</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Refund card hover color override -->
        <style>
        .receipt-card--refund:hover,
        .receipt-card--refund.selected { border-color: var(--danger) !important; }
        </style>

        <?php endif; ?>
    </div>

</div><!-- /.ops-wrap -->

<script>
/* ── Payment tab ── */
function paySelectReceipt(id, remaining, planPrice) {
    document.querySelectorAll('#payReceiptPick .receipt-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector('#payReceiptPick .receipt-card[data-id="' + id + '"]');
    if (card) card.classList.add('selected');
    document.getElementById('paySelectedReceiptId').value = id;
    document.getElementById('payCurrentRemaining').textContent = parseFloat(remaining).toLocaleString('ar-EG');
    const amt = parseFloat(remaining);
    document.getElementById('payAmount').value = amt > 0 ? amt : '';
    const form = document.getElementById('paymentForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth' });
}

function togglePayEvidence(method) {
    const field = document.getElementById('pay-evidence-field');
    const input = document.getElementById('payEvidence');
    if (method && method !== 'cash') {
        field.style.display = 'flex';
        input.required = true;
    } else {
        field.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

/* ── Refund tab ── */
function refSelectReceipt(id, totalPaid) {
    document.querySelectorAll('#refReceiptPick .receipt-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector('#refReceiptPick .receipt-card[data-id="' + id + '"]');
    if (card) card.classList.add('selected');
    document.getElementById('refSelectedReceiptId').value = id;
    document.getElementById('refCurrentTotalPaid').textContent = parseFloat(totalPaid).toLocaleString('ar-EG');
    document.getElementById('refAmount').value = '';
    document.getElementById('refMethodSelect').value = '';
    toggleRefundEvidence('');
    const outer = document.getElementById('refundFormOuter');
    outer.style.display = 'block';
    outer.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleRefundEvidence(method) {
    const field = document.getElementById('refund-evidence-field');
    const input = document.getElementById('refEvidence');
    if (!field) return;
    if (method && method !== 'cash') {
        field.style.display = 'flex';
        input.required = true;
    } else {
        field.style.display = 'none';
        input.required = false;
        if (input) input.value = '';
    }
}
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
}