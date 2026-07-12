<?php // views/admin/captains/_table.php
// Shared by index.php (initial render) and the AJAX search endpoint
$role = $_SESSION['user']['role'] ?? '';
$isAdmin = $role === 'admin';
$canEdit = in_array($role, ['admin', 'area_manager'], true);
?>
<?php if (empty($captains)): ?>
    <div class="empty-state">
        <div class="empty-icon">🧑‍✈️</div>
        <p>لا يوجد كباتن يطابقون البحث.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الكابتن</th>
                    <th>الاسم المختصر</th>
                    <th>رقم الهاتف الأساسي</th>
                    <th>رقم الهاتف الإضافي</th>
                    <th>العمر</th>
                    <th>البريد الإلكتروني</th>
                    <th>المؤهل العلمي</th>
                    <th>الحالة</th>
                    <th>الفروع</th>
                    <th>البطاقة</th>
                    <th>الشهادة</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($captains as $c): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.82rem"><?= $c['id'] ?></td>
                        <td><strong><?= htmlspecialchars($c['captain_name']) ?></strong></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['nickname'] ?? '—') ?></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['phone_number'] ?? '—') ?></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['secondary_phone_number'] ?? '—') ?></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars((string)($c['age'] ?? '—')) ?></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['academic_qualification'] ?? '—') ?></td>
                        <td>
                            <?php if ($c['visible']): ?>
                                <span class="badge badge-success">نشط</span>
                            <?php else: ?>
                                <span class="badge badge-danger">معطّل</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem;color:var(--muted)">
                            <?= $c['branch_names'] ? htmlspecialchars($c['branch_names']) : '—' ?>
                        </td>
                        <td style="font-size:.82rem">
                            <?php if (!empty($c['ssn_card_path'])):
                                $cardUrl = APP_URL . '/' . htmlspecialchars($c['ssn_card_path']);
                                $isPdf   = str_ends_with(strtolower($c['ssn_card_path']), '.pdf');
                            ?>
                                <button type="button" class="btn btn-sm btn-secondary js-view-card"
                                        data-url="<?= $cardUrl ?>" data-pdf="<?= $isPdf ? '1' : '0' ?>">
                                    📎 عرض
                                </button>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem">
                            <?php if (!empty($c['certificate_image_path'])):
                                $certificateUrl = APP_URL . '/' . htmlspecialchars($c['certificate_image_path']);
                                $certificateIsPdf = str_ends_with(strtolower($c['certificate_image_path']), '.pdf');
                            ?>
                                <button type="button" class="btn btn-sm btn-secondary js-view-card"
                                        data-url="<?= $certificateUrl ?>" data-pdf="<?= $certificateIsPdf ? '1' : '0' ?>">
                                    📎 عرض
                                </button>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:.85rem"><?= htmlspecialchars($c['created_at'] ?? '—') ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= APP_URL ?>/admin/captains/show?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                <?php if ($canEdit): ?>
                                    <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">
                                        <?= $role === 'area_manager' ? 'الفروع' : 'تعديل' ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <form method="POST" action="<?= APP_URL ?>/admin/captains/delete?id=<?= $c['id'] ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا الكابتن؟')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:.75rem 1.2rem;font-size:.8rem;color:var(--muted);border-top:1px solid var(--border)">
        عرض <?= count($captains) ?> كابتن
    </div>
<?php endif; ?>
