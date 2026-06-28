<?php
// views/admin/branches/_form.php
// Required vars: $branch, $errors, $isEdit, $action, $countries, $isAreaManager

$days = [
    'Sunday'    => 'الأحد',
    'Monday'    => 'الاثنين',
    'Tuesday'   => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday'  => 'الخميس',
    'Friday'    => 'الجمعة',
    'Saturday'  => 'السبت',
];

function dayChecked(mixed $field, string $day): bool {
    if (empty($field)) return false;
    if (is_array($field)) return in_array($day, $field, true);
    return in_array($day, explode(',', $field), true);
}

$isAreaManager = $isAreaManager ?? false;

require ROOT . '/views/includes/layout_top.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? '✏️ تعديل الفرع' : '➕ فرع جديد' ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary">← رجوع</a>
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

            <?php if (!$isAreaManager): ?>
            <!-- ── المعلومات الأساسية ── -->
            <!-- visible to admin only -->
            <p class="section-title">المعلومات الأساسية</p>

            <div class="form-row">
                <!-- اسم الفرع -->
                <div class="field">
                    <label for="branch_name">اسم الفرع <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="text"
                               id="branch_name"
                               name="branch_name"
                               placeholder="مثال: فرع الرياض الرئيسي"
                               value="<?= htmlspecialchars($branch['branch_name'] ?? '') ?>"
                               required>
                        <span class="icon">🏢</span>
                    </div>
                </div>

                <!-- الدولة -->
                <div class="field">
                    <label for="country_id">الدولة <span class="required">*</span></label>
                    <div class="input-wrap">
                        <select id="country_id" name="country_id" required>
                            <option value="">— اختر الدولة —</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= (int) ($branch['country_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="icon">🌍</span>
                    </div>
                </div>
            </div>

            <!-- ── الحالة ── -->
            <div class="field">
                <label>حالة الفرع</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="visible" value="1"
                            <?= (($branch['visible'] ?? 1) == 1) ? 'checked' : '' ?>>
                        ✅ نشط
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="visible" value="0"
                            <?= (($branch['visible'] ?? 1) == 0) ? 'checked' : '' ?>>
                        ❌ معطّل
                    </label>
                </div>
            </div>
            <?php else: ?>
            <!-- area_manager: show branch name as read-only info, no inputs -->
            <p class="section-title">معلومات الفرع</p>
            <div class="field">
                <label>اسم الفرع</label>
                <div class="input-wrap">
                    <input type="text"
                           value="<?= htmlspecialchars($branch['branch_name'] ?? '') ?>"
                           disabled>
                    <span class="icon">🏢</span>
                </div>
                <p style="font-size:.8rem;color:var(--muted);margin-top:.4rem">
                    لا يمكنك تعديل اسم الفرع أو الدولة أو حالته.
                </p>
            </div>
            <?php endif; ?>

            <!-- ── أيام العمل ── -->
            <p class="section-title" style="margin-top:1.6rem">أيام العمل</p>
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:1.2rem">
                حدّد أيام العمل لكل وردية. اترك الوردية فارغة إن لم تكن مستخدمة.
            </p>

            <div class="shifts-grid">
                <?php foreach ([1, 2, 3] as $shift):
                    $field = $branch["working_days{$shift}"] ?? [];
                ?>
                    <div class="shift-block">
                        <span class="shift-name">ايام العمل <?= $shift ?></span>
                        <div class="day-checks">
                            <?php foreach ($days as $en => $ar): ?>
                                <label class="day-check-label">
                                    <input type="checkbox"
                                           name="working_days<?= $shift ?>[]"
                                           value="<?= $en ?>"
                                        <?= dayChecked($field, $en) ? 'checked' : '' ?>>
                                    <?= $ar ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── وقت الدوام ── -->
            <p class="section-title" style="margin-top:1.6rem">وقت الدوام</p>
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:1.2rem">
                حدّد وقت بداية ونهاية الدوام الرسمي للفرع.
            </p>

            <div class="form-row">
                <div class="field">
                    <label for="working_time_from">من الساعة</label>
                    <div class="input-wrap">
                        <input type="time"
                               id="working_time_from"
                               name="working_time_from"
                               value="<?= htmlspecialchars($branch['working_time_from'] ?? '') ?>">
                        <span class="icon">🕐</span>
                    </div>
                </div>

                <div class="field">
                    <label for="working_time_to">حتى الساعة</label>
                    <div class="input-wrap">
                        <input type="time"
                               id="working_time_to"
                               name="working_time_to"
                               value="<?= htmlspecialchars($branch['working_time_to'] ?? '') ?>">
                        <span class="icon">🕔</span>
                    </div>
                </div>
            </div>

            <!-- ── الإجراءات ── -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? '💾 حفظ التعديلات' : '✅ إنشاء الفرع' ?>
                </button>
                <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary">إلغاء</a>
            </div>

        </div>
    </form>
</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>