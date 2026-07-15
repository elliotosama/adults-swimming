<?php

$old = new mysqli("localhost", "root", "", "swimmingacademy");
$new = new mysqli("localhost", "root", "", "swimming_academy");

if ($old->connect_error || $new->connect_error) {
    die("Database connection failed.");
}

$result = $old->query("
    SELECT id, type, renew_type
    FROM receipts
");

$update = $new->prepare("
    UPDATE receipts
    SET renewal_type = ?
    WHERE id = ?
");

while ($row = $result->fetch_assoc()) {

    if ($row['type'] === 'fresh') {
        $newRenewalType = 'new';
    } else {
        if ($row['renew_type'] === 'old') {
            $newRenewalType = 'previous_renewal';
        } elseif ($row['renew_type'] === 'current') {
            $newRenewalType = 'current_renewal';
        } else {
            $newRenewalType = null;
        }
    }

    $update->bind_param(
        "si",
        $newRenewalType,
        $row['id']
    );

    $update->execute();
}

echo "Migration completed successfully.";

$update->close();
$old->close();
$new->close();