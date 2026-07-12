<?php // views/admin/captains/edit.php
$action = '/admin/captains/edit?id=' . urlencode((string) ($captain['id'] ?? ''));
require __DIR__ . '/_form.php';
