<?php // views/admin/captains/_table.php
// Shared by index.php (initial render) and the AJAX search endpoint
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
                    <th>رقم الهاتف</th>
                    <th>الحالة</th>
                    <th>الفروع</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($captains as $c): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.82rem"><?= $c['id'] ?></td>
                        <td><strong><?= htmlspecialchars($c['captain_name']) ?></strong></td>
                        <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($c['phone_number'] ?? '—') ?></td>
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
                        <td style="color:var(--muted);font-size:.85rem"><?= htmlspecialchars($c['created_at'] ?? '—') ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= APP_URL ?>/admin/captains/show?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">عرض</a>
                                <a href="<?= APP_URL ?>/admin/captains/edit?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                <form method="POST" action="<?= APP_URL ?>/admin/captains/delete?id=<?= $c['id'] ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('هل أنت متأكد من تعطيل هذا الكابتن؟')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">تعطيل</button>
                                </form>
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