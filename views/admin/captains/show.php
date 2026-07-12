<?php
// views/admin/captains/show.php
// Optional: $ajaxPartial — when true, renders without page chrome (SPA modal)

$ajaxPartial = $ajaxPartial ?? false;
$role = $_SESSION['user']['role'] ?? '';
$isAdmin = $role === 'admin';
$canEdit = in_array($role, ['admin', 'area_manager'], true);
$isBranchManager = $role === 'branch_manager';
$managerBranchIds = array_map('intval', $managerBranchIds ?? []);
$captainBranchIds = array_values(array_filter(array_map('intval', $captain['branch_ids'] ?? [])));
$isInMyBranch = $isBranchManager && !empty(array_intersect($managerBranchIds, $captainBranchIds));
$captainWhatsappMessage = is_file(ROOT . '/message.txt') ? trim((string) file_get_contents(ROOT . '/message.txt')) : '';
$whatsappUrl = '';
if (!empty($captain['phone_number']) && $captainWhatsappMessage !== '') {
    $normalizedPhone = PhoneHelper::normalize((string)$captain['phone_number'], '+20');
    $whatsappDigits = $normalizedPhone ? preg_replace('/\D/', '', $normalizedPhone) : '';
    if ($whatsappDigits) {
        $whatsappUrl = 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode($captainWhatsappMessage);
    }
}

if (!$ajaxPartial) {
    require ROOT . '/views/includes/layout_top.php';
}
?>

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

.id-card-preview {
    display: flex;
    align-items: center;
    gap: 12px;
}
.id-card-preview img {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.id-card-preview .file-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">🧑‍✈️ <?= htmlspecialchars($captain['captain_name']) ?></h1>
        <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $captain['id'] ?>" class="btn btn-warning">✏️ تعديل</a>
        <?php endif; ?>
        <?php if ($whatsappUrl): ?>
            <a href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-success">واتساب</a>
        <?php endif; ?>
        <?php if ($isBranchManager): ?>
            <button type="button"
                    class="btn <?= $isInMyBranch ? 'btn-danger js-remove-from-branch' : 'btn-primary js-add-to-branch' ?>"
                    data-id="<?= htmlspecialchars($captain['id']) ?>">
                <?= $isInMyBranch ? 'إزالة من فرعي' : 'إضافة لفرعي' ?>
            </button>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
            <?php if ($ajaxPartial): ?>
                <button type="button"
                        class="btn btn-danger js-delete-captain"
                        data-id="<?= htmlspecialchars($captain['id']) ?>"
                        data-name="<?= htmlspecialchars($captain['captain_name']) ?>">حذف</button>
            <?php else: ?>
                <form method="POST"
                      action="<?= APP_URL ?>/admin/captains/delete?id=<?= $captain['id'] ?>"
                      style="display:inline"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذا الكابتن؟')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($ajaxPartial): ?>
            <button type="button" class="btn btn-secondary js-modal-close">→ رجوع</button>
        <?php else: ?>
            <a href="<?= APP_URL ?>/admin/captains" class="btn btn-secondary">→ رجوع</a>
        <?php endif; ?>
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
            <div class="detail-label">الاسم المختصر</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['nickname'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">رقم الهاتف الأساسي</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['phone_number'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">رقم الهاتف الإضافي</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['secondary_phone_number'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">العمر</div>
            <div style="color:#fff"><?= htmlspecialchars((string)($captain['age'] ?? '—')) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">البريد الإلكتروني</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['email'] ?? '—') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">المؤهل العلمي</div>
            <div style="color:#fff"><?= htmlspecialchars($captain['academic_qualification'] ?? '—') ?></div>
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
            <div class="detail-label">صورة البطاقة</div>
            <div>
                <?php if (!empty($captain['ssn_card_path'])):
                    $cardUrl = APP_URL . '/' . htmlspecialchars($captain['ssn_card_path']);
                    $isPdf   = str_ends_with(strtolower($captain['ssn_card_path']), '.pdf');
                ?>
                    <div class="id-card-preview">
                        <?php if ($isPdf): ?>
                            <span class="file-icon">📄</span>
                        <?php else: ?>
                            <img src="<?= $cardUrl ?>" alt="صورة البطاقة">
                        <?php endif; ?>
                        <a href="<?= $cardUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">عرض الملف</a>
                    </div>
                <?php else: ?>
                    <span style="color:#fff">—</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-label">الشهادة</div>
            <div>
                <?php if (!empty($captain['certificate_image_path'])):
                    $certificateUrl = APP_URL . '/' . htmlspecialchars($captain['certificate_image_path']);
                    $certificateIsPdf = str_ends_with(strtolower($captain['certificate_image_path']), '.pdf');
                ?>
                    <div class="id-card-preview">
                        <?php if ($certificateIsPdf): ?>
                            <span class="file-icon">📄</span>
                        <?php else: ?>
                            <img src="<?= $certificateUrl ?>" alt="صورة الشهادة">
                        <?php endif; ?>
                        <a href="<?= $certificateUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">عرض الملف</a>
                    </div>
                <?php else: ?>
                    <span style="color:#fff">—</span>
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
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $captain['id'] ?>" class="btn btn-sm btn-warning">✏️ تعديل</a>
        <?php endif; ?>
        <?php if ($whatsappUrl): ?>
            <a href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success">واتساب</a>
        <?php endif; ?>
        <?php if ($isBranchManager): ?>
            <button type="button"
                    class="btn btn-sm <?= $isInMyBranch ? 'btn-danger js-remove-from-branch' : 'btn-primary js-add-to-branch' ?>"
                    data-id="<?= htmlspecialchars($captain['id']) ?>">
                <?= $isInMyBranch ? 'إزالة من فرعي' : 'إضافة لفرعي' ?>
            </button>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
            <?php if ($ajaxPartial): ?>
                <button type="button"
                        class="btn btn-sm btn-danger js-delete-captain"
                        data-id="<?= htmlspecialchars($captain['id']) ?>"
                        data-name="<?= htmlspecialchars($captain['captain_name']) ?>">حذف</button>
            <?php else: ?>
                <form method="POST"
                      action="<?= APP_URL ?>/admin/captains/delete?id=<?= $captain['id'] ?>"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذا الكابتن؟')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php if (!$ajaxPartial): ?>
    <?php if ($isBranchManager): ?>
        <script>
        (function () {
            function updateMyBranch(captainId, action) {
                const endpoint = action === 'add' ? 'add-to-my-branch' : 'remove-from-my-branch';
                const body = 'csrf_token=' + encodeURIComponent(<?= json_encode($_SESSION['csrf_token'] ?? '') ?>);

                fetch(<?= json_encode(APP_URL . '/admin/captains/') ?> + endpoint + '?id=' + encodeURIComponent(captainId), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'حدث خطأ أثناء الحفظ.');
                    }
                })
                .catch(() => alert('حدث خطأ أثناء الحفظ.'));
            }

            document.addEventListener('click', function (event) {
                const addButton = event.target.closest('.js-add-to-branch');
                if (addButton) {
                    event.preventDefault();
                    updateMyBranch(addButton.dataset.id, 'add');
                    return;
                }

                const removeButton = event.target.closest('.js-remove-from-branch');
                if (removeButton) {
                    event.preventDefault();
                    updateMyBranch(removeButton.dataset.id, 'remove');
                }
            });
        })();
        </script>
    <?php endif; ?>
    <?php require ROOT . '/views/includes/layout_bottom.php'; ?>
<?php endif; ?>
