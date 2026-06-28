<?php // views/admin/captains/edit.php
require ROOT . '/views/includes/layout_top.php';
?>

<style>
.branch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-top: 6px;
}
.branch-card { position: relative; cursor: pointer; }
.branch-card input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
.branch-card-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 14px 10px;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    transition: all 0.18s ease;
    text-align: center;
    min-height: 72px;
    user-select: none;
}
.branch-card-inner .branch-icon { font-size: 1.4rem; line-height: 1; }
.branch-card-inner .branch-label { font-size: .82rem; font-weight: 500; color: var(--text, #333); line-height: 1.3; }
.branch-card-inner .branch-disabled-tag { font-size: .68rem; color: var(--muted); background: var(--border); border-radius: 4px; padding: 1px 5px; }
.branch-card input:checked + .branch-card-inner {
    border-color: var(--accent, #00b4d8);
    background: color-mix(in srgb, var(--accent, #00b4d8) 10%, transparent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent, #00b4d8) 20%, transparent);
}
.branch-card-inner:hover {
    border-color: var(--accent, #00b4d8);
    background: color-mix(in srgb, var(--accent, #00b4d8) 6%, transparent);
}
.form-card { padding: 2rem 2rem 1.5rem; }
.section-divider {
    font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--muted); margin: 1.6rem 0 .8rem;
    display: flex; align-items: center; gap: 8px;
}
.section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">✏️ تعديل الكابتن</h1>
        <p class="breadcrumb">لوحة التحكم · الكباتن · تعديل</p>
    </div>
    <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">→ رجوع</a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card form-card">
    <form method="POST" action="<?= APP_URL ?>/admin/captains/edit?id=<?= (int) $captain['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <p class="section-divider">بيانات الكابتن</p>

        <div class="form-group">
            <label class="form-label" for="captain_name">اسم الكابتن <span style="color:var(--danger)">*</span></label>
            <input type="text" id="captain_name" name="captain_name" class="form-control"
                   value="<?= htmlspecialchars($captain['captain_name'] ?? '') ?>"
                   required minlength="2" placeholder="أدخل اسم الكابتن">
        </div>

        <div class="form-group">
            <label class="form-label" for="phone_number">رقم الهاتف</label>
            <input type="tel" id="phone_number" name="phone_number" class="form-control"
                   value="<?= htmlspecialchars($captain['phone_number'] ?? '') ?>"
                   placeholder="مثال: 0501234567">
        </div>

        <div class="form-group">
            <label class="form-label" for="visible">الحالة</label>
            <select id="visible" name="visible" class="form-control">
                <option value="1" <?= ($captain['visible'] ?? 1) == 1 ? 'selected' : '' ?>>✅ نشط</option>
                <option value="0" <?= ($captain['visible'] ?? 1) == 0 ? 'selected' : '' ?>>❌ معطّل</option>
            </select>
        </div>

        <p class="section-divider">الفروع المُعيَّنة</p>

        <?php if (empty($branches)): ?>
            <p style="color:var(--muted);font-size:.85rem;padding:12px 0;">لا توجد فروع مسجّلة.</p>
        <?php else: ?>
            <div class="branch-grid">
                <?php foreach ($branches as $branch): ?>
                    <label class="branch-card">
                        <input type="checkbox" name="branch_ids[]" value="<?= $branch['id'] ?>"
                               <?= in_array($branch['id'], $assignedIds ?? []) ? 'checked' : '' ?>>
                        <div class="branch-card-inner">
                            <span class="branch-icon">🏢</span>
                            <span class="branch-label"><?= htmlspecialchars($branch['branch_name']) ?></span>
                            <?php if (!$branch['visible']): ?>
                                <span class="branch-disabled-tag">معطّل</span>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;margin-top:2rem;">
            <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
            <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>