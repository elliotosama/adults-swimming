<?php $pageTitle = 'تعديل الدولة'; ?>
<?php require ROOT . '/views/includes/layout_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">✏️ تعديل الدولة</h1>
        <p class="breadcrumb">لوحة التحكم · الدول · تعديل</p>
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
    <form method="POST" action="<?= APP_URL ?>/country/edit?id=<?= (int) $country['id'] ?>">

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
                <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
                <a href="<?= APP_URL ?>/country" class="btn btn-secondary">إلغاء</a>
            </div>

        </div>
    </form>

    <!-- Danger Zone -->
    <div style="margin:0 1.5rem 1.5rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.08);">
        <p style="font-size:.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;">
            منطقة الخطر
        </p>
        <a href="<?= APP_URL ?>/country/delete?id=<?= (int) $country['id'] ?>"
           onclick="return confirm('هل أنت متأكد من إخفاء هذه الدولة؟')"
           class="btn btn-sm btn-danger">🗑️ إخفاء الدولة</a>
    </div>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>