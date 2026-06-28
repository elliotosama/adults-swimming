<?php // views/transactions/index.php
require ROOT . '/views/includes/layout_top.php';

// ── Pagination URL helper (preserves active filters) ──────────────────────
function paginationUrl(int $p): string {
    $params = array_filter([
        'page'         => $p,
        'receipt_id'   => $_GET['receipt_id']   ?? '',
        'client_phone' => $_GET['client_phone'] ?? '',
    ], fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>

<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">تعطيل المعامله</h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا المعامله؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
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

<div class="page-header">
    <div>
        <h1 class="page-title">💳 المعاملات المالية</h1>
        <p class="breadcrumb">لوحة التحكم · المعاملات</p>
    </div>
    <a href="<?= APP_URL ?>/transaction/create" class="btn btn-primary">
        + إضافة معاملة
    </a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- ══ Filter Form ══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:1rem">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end; padding:10px;">

        <div>
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.25rem">
                رقم الإيصال
            </label>
            <input
                type="text"
                name="receipt_id"
                value="<?= htmlspecialchars($_GET['receipt_id'] ?? '') ?>"
                class="form-input"
                placeholder="مثال: 142"
                style="width:140px"
            >
        </div>

        <div>
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.25rem">
                رقم هاتف العميل
            </label>
            <input
                type="text"
                name="client_phone"
                value="<?= htmlspecialchars($_GET['client_phone'] ?? '') ?>"
                class="form-input"
                placeholder="مثال: 0501234567"
                style="width:180px"
            >
        </div>

        <button type="submit" class="btn btn-primary">🔍 بحث</button>

        <?php if (!empty($_GET['receipt_id']) || !empty($_GET['client_phone'])): ?>
            <a href="<?= APP_URL ?>/transactions" class="btn btn-secondary">✕ مسح الفلتر</a>
        <?php endif; ?>

    </form>
</div>
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <p>لا توجد معاملات مالية مسجّلة بعد.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>طريقة الدفع</th>
                        <th>المبلغ</th>
                        <th>الإيصال</th>
                        <th>هاتف العميل</th>
                        <th>اسم العميل</th>
                        <th>المنشئ</th>
                        <th>التاريخ</th>
                        <th>ملاحظات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                        $typeMap = [
                            'payment'  => ['badge-success', 'دفعة'],
                            'refund'   => ['badge-danger',  'استرداد'],
                            'discount' => ['badge-warning', 'خصم'],
                        ];
                        [$tCls, $tLabel] = $typeMap[$t['type']] ?? ['badge-secondary', $t['type']];
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.82rem"><?= $t['id'] ?></td>
                            <td><span class="badge <?= $tCls ?>"><?= $tLabel ?></span></td>
                            <td><?= htmlspecialchars($t['payment_method'] ?? '—') ?></td>
                            <td><strong><?= number_format($t['amount'], 2) ?></strong></td>
                            <td>
                                <?php if ($t['receipt_id']): ?>
                                    <a href="<?= APP_URL ?>/receipt/show?id=<?= $t['receipt_id'] ?>"
                                       style="color:var(--primary);text-decoration:none;font-size:.85rem">
                                        #<?= $t['receipt_id'] ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.85rem">
                                <?php if (!empty($t['client_phone'])): ?>
                                    <a href="<?= APP_URL ?>/transactions?client_phone=<?= urlencode($t['client_phone']) ?>"
                                       style="color:var(--primary);text-decoration:none">
                                        <?= htmlspecialchars($t['client_phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.85rem"><?= htmlspecialchars($t['client_name'] ?? '—') ?></td>
                            <td style="font-size:.85rem"><?= htmlspecialchars($t['creator_name'] ?? '—') ?></td>
                            <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
                            <td style="font-size:.82rem;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($t['notes'] ?? '—') ?>
                            </td>
                            <td>
                                <div class="td-actions">
                                    <a href="<?= APP_URL ?>/transaction/show?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                    <a href="<?= APP_URL ?>/transaction/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                    <!-- FIX: was $c['id'], now correctly $t['id'] -->
                                    <form method="POST"
                                          action="<?= APP_URL ?>/transaction/delete?id=<?= $t['id'] ?>"
                                          style="display:inline"
                                          onsubmit="event.preventDefault(); showDeleteModal(this);">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <span class="pagination-info">
            عرض صفحة <?= $page ?> من <?= $totalPages ?>
            &nbsp;·&nbsp; إجمالي <?= number_format($total) ?> معاملة
        </span>
        <div class="pagination">

            <?php if ($page > 1): ?>
                <a href="<?= paginationUrl($page - 1) ?>" class="btn btn-sm btn-secondary">« السابق</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            ?>

            <?php if ($start > 1): ?>
                <a href="<?= paginationUrl(1) ?>" class="btn btn-sm btn-secondary">1</a>
                <?php if ($start > 2): ?>
                    <span class="pagination-ellipsis">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
                <a href="<?= paginationUrl($p) ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?>
                    <span class="pagination-ellipsis">…</span>
                <?php endif; ?>
                <a href="<?= paginationUrl($totalPages) ?>" class="btn btn-sm btn-secondary"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= paginationUrl($page + 1) ?>" class="btn btn-sm btn-secondary">التالي »</a>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>