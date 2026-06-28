<?php
// views/admin/prices/edit.php
$pageTitle  = 'تعديل السعر';
$breadcrumb = 'لوحة التحكم · الأسعار · تعديل';
$action     = '/admin/price/edit?id=' . (int)($price['id'] ?? 0);
$isEdit     = true;
$price      = $price  ?? [];
$errors     = $errors ?? [];
require __DIR__ . '/_form.php';