<?php
// views/admin/prices/_form.php
// Required: $price, $errors, $isEdit, $action, $pageTitle, $breadcrumb

require ROOT . '/views/includes/layout_top.php';
?>
<style>
    .pw-hint { font-size:.76rem; color:var(--muted); margin-top:.3rem; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? '✏️ تعديل السعر' : '➕ سعر جديد' ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary">← رجوع</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        ⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= APP_URL . $action ?>">
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
                <a href="<?= APP_URL ?>/admin/prices" class="btn btn-secondary">إلغاء</a>
            </div>

        </div>
    </form>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>