<?php $pageTitle = 'الدول'; ?>
<?php require ROOT . '/views/includes/layout_top.php'; ?>



<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--color-background-primary,#fff);border-radius:16px;border:0.5px solid var(--color-border-tertiary);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);">
        <div style="width:52px;height:52px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:black">اخفاء الدوله </h2>
        <p style="text-align:center;color:black;font-size:.9rem;margin:0 0 1.75rem;line-height:1.6">هل أنت متأكد من تعطيل هذا الدوله؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
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
        <h1 class="page-title">🌍 الدول</h1>
        <p class="breadcrumb">لوحة التحكم · الدول</p>
    </div>
    <a href="<?= APP_URL ?>/country/create" class="btn btn-primary">+ إضافة دولة</a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <?php if (empty($countries)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌍</div>
            <p>لا توجد دول مضافة حتى الآن.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الدولة</th>
                        <th>رمز الدولة</th>
                        <th>تاريخ الإضافة</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($countries as $i => $c): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.82rem"><?= $i + 1 ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.75rem;">
                                    <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--gold),var(--accent));display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">
                                        🌍
                                    </div>
                                    <div style="display:flex;flex-direction:column;">
                                        <strong style="font-size:.9rem"><?= htmlspecialchars($c['country']) ?></strong>
                                        <span style="font-size:.78rem;color:var(--muted)">أضيف: <?= htmlspecialchars($c['created_at'] ?? '—') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($c['country_code'])): ?>
                                    <span style="background:#00b4d820;color:var(--accent);border:1px solid #00b4d840;border-radius:6px;padding:2px 10px;font-size:.8rem;font-weight:600;">
                                        <?= htmlspecialchars($c['country_code']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($c['created_at'] ?? '—') ?></td>
                            <td>
                                <?php if ($c['visible']): ?>
                                    <span class="badge badge-success">ظاهر</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">مخفي</span>
                                <?php endif; ?>
                            </td>
<td>
    <div class="td-actions">
        <a href="<?= APP_URL ?>/country/edit?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
<form method="GET"
      action="<?= APP_URL ?>/country/delete"
      style="display:inline"
      onsubmit="event.preventDefault(); showDeleteModal(this);">
    <input type="hidden" name="id" value="<?= $c['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-danger">إخفاء</button>
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

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>