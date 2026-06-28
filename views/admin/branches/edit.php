<?php
// views/admin/branches/edit.php
$pageTitle  = 'تعديل الفرع';
$breadcrumb = 'لوحة التحكم · الفروع · تعديل';
$action     = '/admin/branch/edit?id=' . (int)($branch['id'] ?? 0);
$isEdit     = true;
$errors     = $errors ?? [];

require __DIR__ . '/_form.php';