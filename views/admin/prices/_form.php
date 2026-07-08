<?php
// views/admin/prices/_form.php
// Required: $price, $errors, $isEdit, $action, $pageTitle, $breadcrumb

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!$isAjax) {
    require ROOT . '/views/includes/layout_top.php';
}
?>
<style>
    .pw-hint { font-size:.76rem; color:var(--muted); margin-top:.3rem; }
</style>

<?php if ($isAjax): ?>
    <!-- Compact header for modal context -->
    <div class="modal-header">
        <h2 class="modal-title"><?= $isEdit ? '✏️ تعديل السعر' : '➕ سعر جديد' ?></h2>
        <button type="button" class="modal-close" onclick="closeAjaxModal()">&times;</button>
    </div>
<?php else: ?>
    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $isEdit ? '✏️ تعديل السعر' : '➕ سعر جديد' ?></h1>
            <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
        </div>
        <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary">← رجوع</a>
    </div>
<?php endif; ?>

<div id="formAlertBox">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            ⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
</div>

<div class="card" style="<?= $isAjax ? 'box-shadow:none;border:none;' : '' ?>">
    <form method="POST" action="<?= APP_URL . $action ?>" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-body">

            <!-- ── بيانات السعر ── -->
            <p class="section-title">بيانات السعر</p>

            <div class="form-row">
                <div class="field" style="grid-column: 1 / -1">
                    <label for="description">الوصف <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="description" name="description"
                               value="<?= htmlspecialchars($price['description'] ?? '') ?>" required>
                        <span class="icon">🏷️</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="price">السعر <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="price" name="price"
                               placeholder="0.00"
                               step="0.01" min="0"
                               value="<?= htmlspecialchars($price['price'] ?? '') ?>" required>
                        <span class="icon">💰</span>
                    </div>
                </div>

                <div class="field">
                    <label for="number_of_sessions">عدد الحصص <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="number_of_sessions" name="number_of_sessions"
                               placeholder="مثال: 10"
                               min="1"
                               value="<?= htmlspecialchars($price['number_of_sessions'] ?? '') ?>" required>
                        <span class="icon">🔢</span>
                    </div>
                </div>
            </div>

            <!-- ── الدولة والحالة ── -->
            <p class="section-title" style="margin-top:1.4rem">الدولة والحالة</p>

            <div class="form-row">
                <div class="field">
                    <label for="country_id">الدولة <span class="required">*</span></label>
                    <div class="input-wrap">
                        <select id="country_id" name="country_id" required>
                            <option value="">— اختر الدولة —</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= ((int)($price['country_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="icon">🌍</span>
                    </div>
                </div>

                <div class="field">
                    <label>حالة السعر</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="visible" value="1"
                                <?= (($price['visible'] ?? 1) == 1) ? 'checked' : '' ?>>
                            ✅ نشط
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="visible" value="0"
                                <?= (($price['visible'] ?? 1) == 0) ? 'checked' : '' ?>>
                            ❌ معطّل
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── الإجراءات ── -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? '💾 حفظ التعديلات' : '✅ إضافة السعر' ?>
                </button>
                <?php if ($isAjax): ?>
                    <a href="javascript:void(0)" class="btn btn-secondary" onclick="closeAjaxModal()">إلغاء</a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary">إلغاء</a>
                <?php endif; ?>
            </div>

        </div>
    </form>
</div>

<?php if (!$isAjax) {
    require ROOT . '/views/includes/layout_bottom.php';
} ?>