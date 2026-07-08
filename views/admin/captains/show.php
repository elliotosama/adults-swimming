<?php // views/admin/captains/show.php
require ROOT . '/views/includes/layout_top.php';
?>


<!-- Custom Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--card,#111d2b);border-radius:16px;border:0.5px solid var(--border);padding:2rem 2rem 1.5rem;max-width:400px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.45);animation:modalIn .2s cubic-bezier(.34,1.56,.64,1);font-family:'Cairo',sans-serif;">
        <div style="width:52px;height:52px;border-radius:50%;background:#e05c5c20;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:24px;">⚠️</div>
        <h2 style="text-align:center;font-size:1.15rem;font-weight:600;margin:0 0 .5rem;color:var(--text,#e0eaf4);font-family:'Cairo',sans-serif;">حذف الكابتن</h2>
        <p style="text-align:center;color:var(--muted,#5a7a96);font-size:.9rem;margin:0 0 1.75rem;line-height:1.6;font-family:'Cairo',sans-serif;">هل أنت متأكد من حذف هذا الكابتن؟<br>يمكنك إعادة تفعيله لاحقاً.</p>
        <div style="display:flex;gap:.75rem;">
            <button onclick="closeModal()" style="flex:1;padding:.7rem;border-radius:8px;border:0.5px solid var(--border);background:transparent;cursor:pointer;font-size:.9rem;color:var(--text,#e0eaf4);font-family:'Cairo',sans-serif;transition:background .15s">إلغاء</button>
            <button id="confirmBtn" style="flex:1;padding:.7rem;border-radius:8px;border:none;background:#e24b4a;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;font-family:'Cairo',sans-serif;transition:background .15s">حذف</button>
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


<style>
.show-card { padding: 2rem; }

.detail-grid {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 0;
}
.detail-row {
    display: contents;
}
.detail-row > * {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border);
    font-size: .9rem;
    display: flex;
    align-items: center;
}
.detail-row:last-child > * { border-bottom: none; }
.detail-label {
    color: #fff;
    font-weight: 500;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.branch-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px 4px 8px;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: 500;
    background: color-mix(in srgb, var(--accent, #00b4d8) 12%, transparent);
    color: var(--accent, #00b4d8);
    border: 1px solid color-mix(in srgb, var(--accent, #00b4d8) 30%, transparent);
}
.branch-tags { display: flex; flex-wrap: wrap; gap: 6px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🧑‍✈️ <?= htmlspecialchars($captain['captain_name']) ?></h1>
        <p class="breadcrumb">لوحة التحكم · الكباتن · <?= htmlspecialchars($captain['captain_name']) ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $captain['id'] ?>" class="btn btn-warning">✏️ تعديل</a>
        <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">→ رجوع</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card show-card">

    <div class="detail-grid">

        <div class="detail-row">
            <div class="detail-label">#</div>
            <div style="color:#fff;font-size:.82rem"><?= $captain['id'] ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">اسم الكابتن</div>
            <div><strong><?= htmlspecialchars($captain['captain_name']) ?></strong></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">رقم الهاتف</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['phone_number'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">الحالة</div>
            <div>
                <?php if ($captain['visible']): ?>
                    <span class="badge badge-success">نشط</span>
                <?php else: ?>
                    <span class="badge badge-danger">معطّل</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-label">تاريخ الإنشاء</div>
            <div style="color:#fff;font-size:.85rem"><?= htmlspecialchars($captain['created_at'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">الفروع</div>
            <div>
                <?php if (empty($assignedBranches)): ?>
                    <span style="color:#fff">—</span>
                <?php else: ?>
                    <div class="branch-tags">
                        <?php foreach ($assignedBranches as $b): ?>
                            <span class="branch-tag">
                                🏢 <?= htmlspecialchars($b['branch_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div style="display:flex;gap:8px;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);">
        <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $captain['id'] ?>" class="btn btn-sm btn-warning">✏️ تعديل</a>
<form method="POST"
      action="<?= APP_URL ?>/admin/captains/delete?id=<?= $captain['id'] ?>"
      style="display:inline"
      onsubmit="event.preventDefault(); showDeleteModal(this);">
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
</form>
    </div>

</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>