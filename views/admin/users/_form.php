<?php
// views/admin/users/_form.php
// Required: $user, $errors, $isEdit, $action, $pageTitle, $breadcrumb, $branches, $assignedIds

require ROOT . '/views/includes/layout_top.php';

$roles = [
    'admin'            => 'مدير النظام',
    'branch_manager'   => 'مدير فرع',
    'area_manager'     => 'مدير منطقة',
    'customer_service' => 'خدمة العملاء',
];
?>
<style>
    .branch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: .5rem;
        margin-top: .5rem;
    }
    .branch-check-label {
        display: flex; align-items: center; gap: .5rem;
        padding: .55rem .9rem; border-radius: 10px;
        border: 1.5px solid var(--border); font-size: .85rem;
        cursor: pointer; transition: border-color .2s, background .2s, color .2s;
        user-select: none;
    }
    .branch-check-label:has(input:checked) {
        border-color: var(--accent); background: #00b4d815; color: var(--accent);
    }
    .branch-check-label input[type="checkbox"] { display: none; }
    .pw-hint { font-size:.76rem; color:var(--muted); margin-top:.3rem; }
    .field-error {
        font-size:.76rem; color:#e54848; margin-top:.3rem;
        display:none; align-items:center; gap:.3rem;
    }
    .field-error.show { display:flex; }
    .input-wrap.has-error input {
        border-color:#e54848 !important;
    }
    .input-wrap.is-valid input {
        border-color:#2bb673 !important;
    }
</style>

<!-- FIX: layout_top.php closes <div class="page"> immediately,
     so we re-open the wrapper here to contain all page content. -->
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? '✏️ تعديل المستخدم' : '➕ مستخدم جديد' ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/users" class="btn btn-secondary">← رجوع</a>
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
    <form method="POST" action="<?= APP_URL . $action ?>" id="userForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-body">

            <!-- ── بيانات الحساب ── -->
            <p class="section-title">بيانات الحساب</p>

            <div class="form-row">
                <div class="field">
                    <label for="username">اسم المستخدم <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username"
                               placeholder="مثال: ahmed_ali"
                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        <span class="icon">👤</span>
                    </div>
                </div>

                <div class="field">
                    <label for="email">البريد الإلكتروني <span class="required">*</span></label>
                    <div class="input-wrap" id="emailWrap">
                        <input type="text" id="email" name="email"
                               placeholder="example@email.com"
                               pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$"
                               title="الرجاء إدخال بريد إلكتروني صحيح ينتهي بـ .com"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        <span class="icon">✉️</span>
                    </div>
                    <p class="field-error" id="emailError">
                        ⚠️ <span>الرجاء إدخال بريد إلكتروني صحيح (مثال: name@domain.com) — يجب أن ينتهي بـ .com</span>
                    </p>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="password">
                        <?= $isEdit ? 'كلمة المرور الجديدة' : 'كلمة المرور' ?>
                        <?= $isEdit ? '' : '<span class="required">*</span>' ?>
                    </label>
                    <div class="input-wrap">
                        <input type="text" id="password" name="password"
                               placeholder="<?= $isEdit ? 'اتركها فارغة إن لم تُرد التغيير' : '••••••••' ?>"
                               <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                        <span class="icon">🔒</span>
                    </div>
                    <?php if ($isEdit): ?>
                        <p class="pw-hint">اترك الحقل فارغاً للإبقاء على كلمة المرور الحالية.</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="phone">رقم الهاتف</label>
                    <div class="input-wrap">
                        <input type="text" id="phone" name="phone"
                               placeholder="+966 5X XXX XXXX"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        <span class="icon">📱</span>
                    </div>
                </div>
            </div>

            <!-- ── الدور والحالة ── -->
            <p class="section-title" style="margin-top:1.4rem">الصلاحيات والحالة</p>

            <div class="form-row">
                <div class="field">
                    <label for="role">الدور <span class="required">*</span></label>
                    <div class="input-wrap">
                        <select id="role" name="role" required>
                            <option value="" disabled <?= empty($user['role']) ? 'selected' : '' ?>>اختر الدور</option>
                            <?php foreach ($roles as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($user['role'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="icon">🏅</span>
                    </div>
                </div>

                <div class="field">
                    <label>حالة الحساب</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="is_active" value="1"
                                <?= (($user['is_active'] ?? 1) == 1) ? 'checked' : '' ?>>
                            ✅ نشط
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="is_active" value="0"
                                <?= (($user['is_active'] ?? 1) == 0) ? 'checked' : '' ?>>
                            ❌ معطّل
                        </label>
                    </div>
                    <input type="hidden" name="visible" value="1">
                </div>
            </div>

            <!-- ── الفروع المرتبطة ── -->
            <p class="section-title" style="margin-top:1.4rem">الفروع المرتبطة</p>
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:.9rem">
                اختر الفروع التي يمكن لهذا المستخدم الوصول إليها.
            </p>

            <?php if (empty($branches)): ?>
                <p style="color:var(--muted);font-size:.85rem">لا توجد فروع متاحة بعد.</p>
            <?php else: ?>
                <div class="branch-grid">
                    <?php foreach ($branches as $b): ?>
                        <label class="branch-check-label">
                            <input type="checkbox"
                                   name="branch_ids[]"
                                   value="<?= $b['id'] ?>"
                                <?= in_array((int)$b['id'], (array)$assignedIds, true) ? 'checked' : '' ?>>
                            🏢 <?= htmlspecialchars($b['branch_name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── الإجراءات ── -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? '💾 حفظ التعديلات' : '✅ إنشاء المستخدم' ?>
                </button>
                <a href="<?= APP_URL ?>/admin/users" class="btn btn-secondary">إلغاء</a>
            </div>

        </div>
    </form>
</div>

</div><!-- /.page -->

<script>
(function () {
    var emailInput = document.getElementById('email');
    var emailWrap  = document.getElementById('emailWrap');
    var emailError = document.getElementById('emailError');
    var form       = document.getElementById('userForm');

    // Same rule as the server should enforce: something@something.com
    var EMAIL_RE = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/;

    function validateEmail(showStateOnEmpty) {
        var val = emailInput.value.trim();

        if (val === '') {
            emailWrap.classList.remove('has-error', 'is-valid');
            emailError.classList.remove('show');
            return !showStateOnEmpty; // empty is invalid on submit (required), but don't flash error while still typing
        }

        var isValid = EMAIL_RE.test(val);

        if (isValid) {
            emailWrap.classList.remove('has-error');
            emailWrap.classList.add('is-valid');
            emailError.classList.remove('show');
        } else {
            emailWrap.classList.remove('is-valid');
            emailWrap.classList.add('has-error');
            emailError.classList.add('show');
        }
        return isValid;
    }

    emailInput.addEventListener('input', function () { validateEmail(false); });
    emailInput.addEventListener('blur', function () { validateEmail(true); });

    form.addEventListener('submit', function (e) {
        var val = emailInput.value.trim();
        var ok = val !== '' && EMAIL_RE.test(val);

        if (!ok) {
            e.preventDefault();
            emailWrap.classList.add('has-error');
            emailWrap.classList.remove('is-valid');
            emailError.classList.add('show');
            emailInput.focus();
        }
    });
})();
</script>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>