<?php // views/clients/_form.php ?>
<div class="form-body">

    <div class="form-row">
        <div class="field">
            <label>اسم العميل <span class="required">*</span></label>
            <div class="input-wrap">
                <input type="text" name="client_name"
                       value="<?= htmlspecialchars($client['client_name'] ?? '') ?>"
                       placeholder="الاسم الكامل" required>
                <span class="icon">👤</span>
            </div>
        </div>

        <div class="field">
            <label>رقم الهاتف <span class="required">*</span></label>
            <div class="input-wrap">
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($client['phone'] ?? '') ?>"
                       placeholder="مثال: 0501234567" required>
                <span class="icon">📞</span>
            </div>
        </div>
    </div>

    <div class="form-row">
        <div class="field">
            <label>البريد الإلكتروني</label>
            <div class="input-wrap">
                <input type="text" name="email"
                       value="<?= htmlspecialchars($client['email'] ?? '') ?>"
                       placeholder="example@email.com">
                <span class="icon">📧</span>
            </div>
        </div>

        <div class="field">
            <label>العمر</label>
            <div class="input-wrap">
                <input type="text" name="age"
                       value="<?= htmlspecialchars($client['age'] ?? '') ?>"
                       placeholder="مثال: 25">
                <span class="icon">🎂</span>
            </div>
        </div>
    </div>

    <div class="field">
        <label>الجنس</label>
        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="gender" value="male"
                       <?= ($client['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                ذكر
            </label>
            <label class="radio-label">
                <input type="radio" name="gender" value="female"
                       <?= ($client['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                أنثى
            </label>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? '💾 حفظ التعديلات' : '➕ إضافة العميل' ?>
        </button>
        <a href="<?= APP_URL ?>/clients" class="btn btn-secondary">إلغاء</a>
    </div>

</div>