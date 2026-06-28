<?php // views/admin/branches/_table.php
// Shared by index.php (initial render) and the AJAX search endpoint
?>
<?php if (empty($branches)): ?>
    <div class="empty-state">
        <div class="empty-icon">🏢</div>
        <p>لا توجد فروع تطابق البحث.</p>
        <?php if (!empty($filters['search']) || !empty($filters['country_id']) || !empty($filters['visibility'])): ?>
            <a href="<?= APP_URL ?>/admin/branches" class="btn btn-secondary" style="margin-top:1rem">إعادة ضبط الفلاتر</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الفرع</th>
                    <th>الدولة</th>
                    <th>الوردية 1</th>
                    <th>الوردية 2</th>
                    <th>الوردية 3</th>
                    <th>الحالة</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.82rem"><?= $b['id'] ?></td>
                        <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                        <td><?= htmlspecialchars($b['country'] ?? '—') ?></td>
                        <td style="font-size:.8rem;color:var(--muted)">
                            <?php if (!empty($b['working_days1'])): ?>
                                <?php foreach (explode(',', $b['working_days1']) as $d): ?>
                                    <span class="badge" style="background:#00b4d815;color:var(--accent);border:1px solid #00b4d830;margin:1px">
                                        <?= htmlspecialchars(trim($d)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:.8rem;color:var(--muted)">
                            <?php if (!empty($b['working_days2'])): ?>
                                <?php foreach (explode(',', $b['working_days2']) as $d): ?>
                                    <span class="badge" style="background:#f4a62315;color:var(--gold);border:1px solid #f4a62330;margin:1px">
                                        <?= htmlspecialchars(trim($d)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:.8rem;color:var(--muted)">
                            <?php if (!empty($b['working_days3'])): ?>
                                <?php foreach (explode(',', $b['working_days3']) as $d): ?>
                                    <span class="badge" style="background:#34c78915;color:var(--success);border:1px solid #34c78930;margin:1px">
                                        <?= htmlspecialchars(trim($d)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['visible']): ?>
                                <span class="badge badge-success">نشط</span>
                            <?php else: ?>
                                <span class="badge badge-danger">معطّل</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:.85rem">
                            <?= htmlspecialchars($b['created_at'] ?? '—') ?>
                        </td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= APP_URL ?>/admin/branch/show?id=<?= $b['id'] ?>"
                                   class="btn btn-sm btn-secondary">عرض</a>
                                <a href="<?= APP_URL ?>/admin/branch/edit?id=<?= $b['id'] ?>"
                                   class="btn btn-sm btn-warning">تعديل</a>
                                <form method="POST"
                                      action="<?= APP_URL ?>/admin/branch/delete?id=<?= $b['id'] ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('هل أنت متأكد من تعطيل هذا الفرع؟')">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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
        عرض <?= count($branches) ?> فرع
    </div>
<?php endif; ?>