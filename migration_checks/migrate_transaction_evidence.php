<?php

$old = new PDO(
    "mysql:host=localhost;dbname=swimmingacademy;charset=utf8mb4",
    "root",
    ""
);

$new = new PDO(
    "mysql:host=localhost;dbname=swimming_academy;charset=utf8mb4",
    "root",
    ""
);

$old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$new->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function extractFiles($attachment)
{
    if (empty($attachment)) {
        return [];
    }

    $decoded = json_decode($attachment, true);

    if (!is_array($decoded)) {
        $decoded = [$attachment];
    }

    $files = [];

    foreach ($decoded as $path) {
        $path = str_replace("\\", "/", $path);
        $files[] = basename($path);
    }

    return array_unique($files);
}

$oldReceipts = $old->query("
    SELECT id, attachment
    FROM receipts
");

$getTemplate = $new->prepare("
    SELECT *
    FROM transactions
    WHERE receipt_id = ?
    LIMIT 1
");

$exists = $new->prepare("
    SELECT COUNT(*)
    FROM transactions
    WHERE receipt_id = ?
      AND attachment = ?
");

$insert = $new->prepare("
INSERT INTO transactions
(
    payment_method,
    amount,
    receipt_id,
    created_by,
    created_at,
    attachment,
    notes,
    type
)
VALUES
(
    :payment_method,
    :amount,
    :receipt_id,
    :created_by,
    :created_at,
    :attachment,
    :notes,
    :type
)
");

$total = 0;

while ($receipt = $oldReceipts->fetch(PDO::FETCH_ASSOC)) {

    $files = extractFiles($receipt['attachment']);

    if (empty($files)) {
        continue;
    }

    $getTemplate->execute([$receipt['id']]);
    $template = $getTemplate->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo "Receipt {$receipt['id']} skipped (no transaction found)\n";
        continue;
    }

    foreach ($files as $file) {

        $exists->execute([
            $receipt['id'],
            $file
        ]);

        if ($exists->fetchColumn()) {
            continue;
        }

        $insert->execute([
            ':payment_method' => $template['payment_method'],
            ':amount'         => $template['amount'],
            ':receipt_id'     => $template['receipt_id'],
            ':created_by'     => $template['created_by'],
            ':created_at'     => $template['created_at'],
            ':attachment'     => $file,
            ':notes'          => $template['notes'],
            ':type'           => $template['type'],
        ]);

        echo "Inserted {$file} for receipt {$receipt['id']}\n";

        $total++;
    }
}

echo PHP_EOL;
echo "=====================================\n";
echo "Inserted {$total} missing attachments.\n";
echo "=====================================\n";