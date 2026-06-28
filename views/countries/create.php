<?php $pageTitle = 'إضافة دولة'; ?>
<?php require ROOT . '/views/includes/layout_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">➕ إضافة دولة جديدة</h1>
        <p class="breadcrumb">لوحة التحكم · الدول · إضافة دولة</p>
    </div>
    <a href="<?= APP_URL ?>/country" class="btn btn-secondary">← رجوع</a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error">⚠️ <?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:640px; margin: 0 auto;">
    <form method="POST" action="<?= APP_URL ?>/country/create">

        <div class="form-body">

            <p class="section-title">معلومات الدولة</p>

            <div class="field">
                <label for="country">اسم الدولة <span class="required">*</span></label>
                <div class="input-wrap">
                    <input type="text" id="country" name="country"
                        placeholder="مثال: المملكة العربية السعودية"
                        value="<?= htmlspecialchars($country['country'] ?? '') ?>"
                        required>
                    <span class="icon">🌍</span>
                </div>
            </div>

            <div class="field">
                <label for="country_code">رمز الدولة</label>
                <div class="input-wrap">
                    <input type="text" id="country_code" name="country_code"
                        placeholder="مثال: SA"
                        value="<?= htmlspecialchars($country['country_code'] ?? '') ?>"
                        maxlength="5"
                        style="text-transform:uppercase;">
                    <span class="icon">🏷️</span>
                </div>
                <small style="font-size:.78rem;color:var(--muted);margin-top:.3rem;display:block;">
                    رمز مختصر للدولة — حروف إنجليزية كبيرة
                </small>
            </div>

            <div class="field">
                <label for="visible">الحالة</label>
                <select id="visible" name="visible">
                    <option value="1" <?= (($country['visible'] ?? 1) == 1) ? 'selected' : '' ?>>✅ ظاهر</option>
                    <option value="0" <?= (($country['visible'] ?? 1) == 0) ? 'selected' : '' ?>>❌ مخفي</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">✅ حفظ الدولة</button>
                <a href="<?= APP_URL ?>/country" class="btn btn-secondary">إلغاء</a>
            </div>

        </div>
    </form>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>