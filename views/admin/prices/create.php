<?php
// views/admin/prices/create.php
$pageTitle  = 'سعر جديد';
$breadcrumb = 'لوحة التحكم · الأسعار · سعر جديد';
$action     = '/admin/price/create';
$isEdit     = false;
$price      = $price  ?? [];
$errors     = $errors ?? [];

require __DIR__ . '/_form.php';