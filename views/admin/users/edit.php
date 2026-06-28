<?php
// views/admin/users/edit.php
$pageTitle  = 'تعديل المستخدم';
$breadcrumb = 'لوحة التحكم · المستخدمون · تعديل';
$action     = '/admin/user/edit?id=' . (int)($user['id'] ?? 0);
$isEdit     = true;
$errors     = $errors      ?? [];
$branches   = $branches    ?? [];
$assignedIds= $assignedIds ?? [];

require __DIR__ . '/_form.php';