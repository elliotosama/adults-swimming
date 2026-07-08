<?php // views/admin/captains/edit.php
$action = '/admin/captains/edit?id=' . (int) ($captain['id'] ?? 0);
require __DIR__ . '/_form.php';