<?php
// views/admin/captains/_form.php
// Required vars: $captain, $errors, $isEdit, $action, $branches, $assignedIds
// Optional: $ajaxPartial — when true, renders without page chrome (SPA modal)

$ajaxPartial = $ajaxPartial ?? false;
$assignedIds = $assignedIds ?? [];
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

if (!$ajaxPartial) {
    require ROOT . '/views/includes/layout_top.php';
}

$existingCard = $captain['ssn_card_path'] ?? null;
$existingCardIsPdf = $existingCard && str_ends_with(strtolower($existingCard), '.pdf');
$existingCertificate = $captain['certificate_image_path'] ?? null;
$existingCertificateIsPdf = $existingCertificate && str_ends_with(strtolower($existingCertificate), '.pdf');
?>

<style>
.branch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 5px;
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
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }
.section-divider {
    font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--muted); margin: 1.6rem 0 .8rem;
    display: flex; align-items: center; gap: 8px;
}
.section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.card-preview {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px;
    margin-top: 8px; font-size: .85rem;
}
.card-preview img { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; }
.card-preview .file-icon { font-size: 1.8rem; }
.card-preview a { color: var(--accent, #00b4d8); font-weight: 500; }
.remove-card-check { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: .82rem; color: var(--muted); }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? '✏️ تعديل الكابتن' : '➕ كابتن جديد' ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <?php if ($ajaxPartial): ?>
        <button type="button" class="btn btn-secondary js-modal-close">→ رجوع</button>
    <?php else: ?>
        <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">→ رجوع</a>
    <?php endif; ?>
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

<div class="card form-card">
    <form method="POST" action="<?= APP_URL . $action ?>" class="js-captain-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <p class="section-divider">بيانات الكابتن</p>

        <div class="form-group">
            <label class="form-label" for="captain_name">اسم الكابتن <span style="color:var(--danger)">*</span></label>
            <input type="text" id="captain_name" name="captain_name" class="form-control"
                   value="<?= htmlspecialchars($captain['captain_name'] ?? '') ?>"
                   required minlength="2" placeholder="أدخل اسم الكابتن">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="phone_number">رقم الهاتف الأساسي <span style="color:var(--danger)">*</span></label>
                <input type="tel" id="phone_number" name="phone_number" class="form-control"
                       value="<?= htmlspecialchars($captain['phone_number'] ?? '') ?>"
                       required placeholder="مثال: 01274593603">
            </div>

            <div class="form-group">
                <label class="form-label" for="secondary_phone_number">رقم الهاتف الإضافي <span style="color:var(--danger)">*</span></label>
                <input type="tel" id="secondary_phone_number" name="secondary_phone_number" class="form-control"
                       value="<?= htmlspecialchars($captain['secondary_phone_number'] ?? '') ?>"
                       required placeholder="مثال: +201274593603">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="age">العمر</label>
                <input type="number" id="age" name="age" class="form-control" min="18" max="90"
                       value="<?= htmlspecialchars((string)($captain['age'] ?? '')) ?>"
                       placeholder="مثال: 30">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($captain['email'] ?? '') ?>"
                       placeholder="example@email.com">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="academic_qualification">المؤهل العلمي</label>
            <input type="text" id="academic_qualification" name="academic_qualification" class="form-control"
                   value="<?= htmlspecialchars($captain['academic_qualification'] ?? '') ?>"
                   placeholder="مثال: بكالوريوس تربية رياضية">
        </div>

        <?php if ($isAdmin): ?>
            <div class="form-group">
                <label class="form-label" for="visible">الحالة</label>
                <select id="visible" name="visible" class="form-control">
                    <option value="1" <?= ($captain['visible'] ?? 1) == 1 ? 'selected' : '' ?>>✅ نشط</option>
                    <option value="0" <?= ($captain['visible'] ?? 1) == 0 ? 'selected' : '' ?>>❌ معطّل</option>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="visible" value="<?= (int)($captain['visible'] ?? 1) ?>">
        <?php endif; ?>

        <p class="section-divider">صورة البطاقة (الرقم القومي)</p>

        <div class="form-group">
            <label class="form-label" for="ssn_card_path">رفع صورة البطاقة</label>
            <input type="file" id="ssn_card_path" name="ssn_card_path" class="form-control"
                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
            <p style="font-size:.75rem;color:var(--muted);margin-top:4px">JPG, PNG, WEBP أو PDF — بحد أقصى 5 ميجابايت.</p>

            <?php if ($existingCard): ?>
                <div class="card-preview">
                    <?php if ($existingCardIsPdf): ?>
                        <span class="file-icon">📄</span>
                    <?php else: ?>
                        <img src="<?= APP_URL . '/' . htmlspecialchars($existingCard) ?>" alt="صورة البطاقة">
                    <?php endif; ?>
                    <a href="<?= APP_URL . '/' . htmlspecialchars($existingCard) ?>" target="_blank" rel="noopener">عرض الملف الحالي</a>
                </div>
                <label class="remove-card-check">
                    <input type="checkbox" name="remove_ssn_card" value="1">
                    إزالة صورة البطاقة الحالية
                </label>
            <?php endif; ?>
        </div>

        <p class="section-divider">الشهادة</p>

        <div class="form-group">
            <label class="form-label" for="certificate_image_path">رفع صورة الشهادة</label>
            <input type="file" id="certificate_image_path" name="certificate_image_path" class="form-control"
                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
            <p style="font-size:.75rem;color:var(--muted);margin-top:4px">JPG, PNG, WEBP أو PDF — بحد أقصى 5 ميجابايت.</p>

            <?php if ($existingCertificate): ?>
                <div class="card-preview">
                    <?php if ($existingCertificateIsPdf): ?>
                        <span class="file-icon">📄</span>
                    <?php else: ?>
                        <img src="<?= APP_URL . '/' . htmlspecialchars($existingCertificate) ?>" alt="صورة الشهادة">
                    <?php endif; ?>
                    <a href="<?= APP_URL . '/' . htmlspecialchars($existingCertificate) ?>" target="_blank" rel="noopener">عرض الملف الحالي</a>
                </div>
                <label class="remove-card-check">
                    <input type="checkbox" name="remove_certificate_image" value="1">
                    إزالة صورة الشهادة الحالية
                </label>
            <?php endif; ?>
        </div>

        <p class="section-divider">الفروع المُعيَّنة</p>

        <?php if (empty($branches)): ?>
            <p style="color:var(--muted);font-size:.85rem;padding:12px 0;">لا توجد فروع مسجّلة.</p>
        <?php else: ?>
            <div class="branch-grid">
                <?php foreach ($branches as $branch): ?>
                    <label class="branch-card">
                        <input type="checkbox" name="branch_ids[]" value="<?= $branch['id'] ?>"
                               <?= in_array($branch['id'], $assignedIds) ? 'checked' : '' ?>>
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
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? '💾 حفظ التعديلات' : '✅ إضافة الكابتن' ?>
            </button>
            <?php if ($ajaxPartial): ?>
                <button type="button" class="btn btn-secondary js-modal-close">إلغاء</button>
            <?php else: ?>
                <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">إلغاء</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!$ajaxPartial): ?>
    <?php require ROOT . '/views/includes/layout_bottom.php'; ?>
<?php endif; ?>
