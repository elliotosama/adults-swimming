<?php
// views/admin/users/create.php
$pageTitle  = 'مستخدم جديد';
$breadcrumb = 'لوحة التحكم · المستخدمون · مستخدم جديد';
$action     = '/admin/user/create';
$isEdit     = false;
$user       = $user        ?? [];
$errors     = $errors      ?? [];
$branches   = $branches    ?? [];
$assignedIds= $assignedIds ?? [];

require __DIR__ . '/_form.php';