<?php
// app/Services/ReceiptPdfGenerator.php

require_once ROOT . '/vendor/autoload.php';

use Mpdf\Mpdf;

class ReceiptPdfGenerator {

    /**
     * Generate and stream a receipt PDF to the browser.
     *
     * @param array  $receipt       All receipt fields from findById() + plan_price
     * @param float  $totalPaid     Net paid amount (gross payments − refunds)
     * @param float  $remaining     Amount still owed
     * @param string $paymentMethod Payment method key (cash / instapay / …)
     * @param string $lang          'ar' (default) or 'en'
     */
    public static function generate(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $paymentMethod,
        string $lang = 'ar'
    ): void {

        $mpdf = self::makeMpdf($lang);
        $mpdf->WriteHTML(self::buildHtml($receipt, $totalPaid, $remaining, $paymentMethod, $lang));

        $filename = 'receipt_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
        $mpdf->Output($filename, 'I');
    }

    /**
     * Save PDF to disk and return the filename.
     * Used by store() to persist pdf_path on the receipt row.
     *
     * @param string $lang 'ar' (default) or 'en'
     */
    public static function save(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $paymentMethod,
        string $saveDir,
        string $lang = 'ar'
    ): string {

        $mpdf = self::makeMpdf($lang);
        $mpdf->WriteHTML(self::buildHtml($receipt, $totalPaid, $remaining, $paymentMethod, $lang));

        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0775, true);
        }

        $filename = 'receipt_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
        $mpdf->Output(rtrim($saveDir, '/') . '/' . $filename, 'F');

        return $filename;
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    private static function makeMpdf(string $lang): Mpdf
    {
        $isRtl = ($lang !== 'en');

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => [148, 110],
            'margin_top'    => 10,
            'margin_bottom' => 0,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'direction'     => $isRtl ? 'rtl' : 'ltr',
            'tempDir'       => sys_get_temp_dir() . '/mpdf',
        ]);

        if ($isRtl) {
            $mpdf->SetDirectionality('rtl');
        }


        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->autoArabic       = $isRtl;
        $mpdf->baseScript       = 1;

        return $mpdf;
    }

    private static function buildHtml(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $paymentMethod,
        string $lang
    ): string {

        $isEn = ($lang === 'en');

        // ── Sanitise fields ──────────────────────────────────────
        $id          = htmlspecialchars($receipt['id']              ?? '—');
        $clientName  = htmlspecialchars($receipt['client_name']     ?? '—');
        $phone       = htmlspecialchars($receipt['phone_number']    ?? '—');
        $branchName  = htmlspecialchars($receipt['branch_name']     ?? '—');
        $captainName = htmlspecialchars($receipt['captain_name']    ?? '—');
        $planName    = htmlspecialchars($receipt['plan_name']       ?? '—');
        $firstSess   = htmlspecialchars($receipt['first_session']   ?? '—');
        $lastSess    = htmlspecialchars($receipt['last_session']    ?? '—');
        $renewalSess = htmlspecialchars($receipt['renewal_session'] ?? '—');
        $rawExTime   = $receipt['exercise_time'] ?? '';
        $exTime      = '';
        if ($rawExTime && $rawExTime !== '—') {
            try {
                $exTime = (new DateTime($rawExTime))->format('g:i A');
            } catch (\Exception $e) {
                $exTime = $rawExTime;
            }
        }
        $exTime      = htmlspecialchars($exTime ?: '—');
        $level       = htmlspecialchars((string)($receipt['level'] ?? '—'));
        $createdAt   = htmlspecialchars($receipt['created_at']      ?? '—');
        $creatorName = htmlspecialchars($receipt['creator_name']    ?? '—');

        $paymentMethodLabels = $isEn
            ? ['cash' => 'Cash', 'instapay' => 'InstaPay', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'Bank Transfer']
            : ['cash' => 'نقداً',  'instapay' => 'InstaPay', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'تحويل بنكي'];
        $payLabel = htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? $paymentMethod);

        $totalPaidFmt = number_format($totalPaid, 0);
        $remainingFmt = number_format($remaining, 0);

        // ── Logo ─────────────────────────────────────────────────
        $logoPath = ROOT . '/assets/images/logo.jpeg';
        $logoImg  = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $logoSrc  = 'data:' . $logoMime . ';base64,' . $logoData;
            $logoImg  = '<img src="' . $logoSrc . '" class="logo">';
        }

        // ── Labels (Arabic vs English) ───────────────────────────
        $L = $isEn ? [
            'dir'            => 'ltr',
            'htmlLang'       => 'en',
            'receiptTitle'   => 'Receipt',
            'receiptNo'      => 'Receipt No.',
            'clientName'     => 'Client Name',
            'mobile'         => 'Mobile',
            'trainingTime'   => 'Training Time',
            'firstSession'   => 'First Session',
            'renewalDate'    => 'Renewal Date',
            'lastSession'    => 'Last Session',
            'level'          => 'Level',
            'planType'       => 'Subscription Plan',
            'branch'         => 'Branch',
            'captain'        => 'Captain',
            'amountPaid'     => 'Amount Paid',
            'paymentMethod'  => 'Payment Method',
            'remaining'      => 'Remaining',
            'receivedBy'     => 'Received By',
            'createdAt'      => 'Date',
            'importantTitle' => 'Important Notes:',
            'refundPolicy'   => 'Refund Policy:',
            'rule1'          => '30% of the subscription fee is deducted after the first session.',
            'rule2'          => '50% of the subscription fee is deducted after the second session.',
            'rule3'          => 'Absences are compensated by a maximum of one session within the remaining subscription period.',
            'academyName'    => 'Adults Swimming Academy',
        ] : [
            'dir'            => 'rtl',
            'htmlLang'       => 'ar',
            'receiptTitle'   => 'إيصال',
            'receiptNo'      => 'رقم الإيصال',
            'clientName'     => 'اسم العميل',
            'mobile'         => 'رقم الموبايل',
            'trainingTime'   => 'ميعاد التمرين',
            'firstSession'   => 'الحصة الأولى',
            'renewalDate'    => 'تاريخ التجديد',
            'lastSession'    => 'الحصة الأخيرة',
            'level'          => 'المستوى',
            'planType'       => 'نوع الاشتراك',
            'branch'         => 'الفرع',
            'captain'        => 'الكابتن',
            'amountPaid'     => 'المبلغ المدفوع',
            'paymentMethod'  => 'طريقة الدفع',
            'remaining'      => 'المتبقي',
            'receivedBy'     => 'المستلم',
            'createdAt'      => 'تاريخ الإنشاء',
            'importantTitle' => 'تعليمات هامة:',
            'refundPolicy'   => 'سياسة الاسترجاع:',
            'rule1'          => 'يتم خصم 30% من قيمة الاشتراك من بعد الحصة الأولى',
            'rule2'          => 'يتم خصم 50% من قيمة الاشتراك من بعد الحصة الثانية',
            'rule3'          => 'يتم التعويض عن الغياب بحد أقصى حصة وذلك فقط للمدة المتبقية في الاشتراك',
            'academyName'    => 'Adults Swimming Academy',
        ];

        $dir      = $L['dir'];
        $htmlLang = $L['htmlLang'];

        return <<<HTML
<!DOCTYPE html>
<html dir="{$dir}" lang="{$htmlLang}">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Cairo', 'Arial', sans-serif;
    font-size: 11px;
    color: #1a1a2e;
    direction: {$dir};
    background: #fff;
  }

  .page { width: 100%; padding: 8px 10px 0; }


  .header {
    margin-top: -60px;
  }

  .header-logo {
    width: 100px;
    height: 100px;
    margin-left: 10px;
  }

  .academy-name {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #3e21a8ff;
  }


  .receipt-data {
    font-size: 16px;
  margin-left: 170px;
  margin-bottom: 10px;
  margin-top: 10px;
  }


  /* ── Info table ── */
  .info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
  }
  .info-table td {
    padding: 5px 8px;
    font-size: 11px;
    vertical-align: middle;
    border-bottom: 1px solid #e8ecf0;
  }
  .info-table tr:last-child td { border-bottom: none; }

  .label-cell {
    color: #555;
    font-weight: 600;
    width: 28%;
    white-space: nowrap;
  }
  .value-cell {
    color: #1a1a2e;
    font-weight: 700;
    width: 22%;
  }

  .row-highlight { background: #f0f4ff; }
  .row-amount    { background: #fff8e1; }
  .row-remaining { background: #ffeaea; }

  /* ── Divider ── */
  .divider {
    border: none;
    border-top: 1.5px solid #1a3a6b;
    margin: 10px 0;
  }

  /* ── Footer ── */
  .footer {
    background: #f8f9fc;
    border: 1px solid #dde2ee;
    border-radius: 6px;
    padding: 10px 14px;
    margin-top: 10px;
  }
  .footer-title {
    font-size: 12px;
    font-weight: 800;
    color: #c0392b;
    margin-bottom: 6px;
  }
  .footer-subtitle {
    font-size: 11px;
    font-weight: 700;
    color: #1a3a6b;
    margin-bottom: 4px;
  }
  .footer li {
    font-size: 13.5px;
    color: #444;
    margin-bottom: 3px;
    list-style: none;
    padding-right: 8px;
  }
  .footer li::before { content: "- "; }
  ul { margin-right: -45px; }
</style>
</head>
<body>
<div class="page">


  <div class="header">
    <div class="header-logo">{$logoImg}</div>
    <div class="academy-name">{$L['academyName']}</div>
  </div>
  <div class="receipt-data">
    <div class="receipt-title">{$L['receiptTitle']}</div>
    <div class="receipt-number">{$L['receiptNo']}: {$id}</div>
  </div>

  <!-- Main info grid -->
  <table class="info-table">
    <tr class="row-highlight">
      <td class="label-cell">{$L['clientName']}:</td>
      <td class="value-cell" colspan="3">{$clientName}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['mobile']}:</td>
      <td class="value-cell">{$phone}</td>
      <td class="label-cell">{$L['trainingTime']}:</td>
      <td class="value-cell">{$exTime}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['firstSession']}:</td>
      <td class="value-cell">{$firstSess}</td>
      <td class="label-cell">{$L['renewalDate']}:</td>
      <td class="value-cell">{$renewalSess}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['lastSession']}:</td>
      <td class="value-cell">{$lastSess}</td>
      <td class="label-cell">{$L['level']}:</td>
      <td class="value-cell">{$level}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['planType']}:</td>
      <td class="value-cell" colspan="3">{$planName}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['branch']}:</td>
      <td class="value-cell">{$branchName}</td>
      <td class="label-cell">{$L['captain']}:</td>
      <td class="value-cell">{$captainName}</td>
    </tr>
    <tr class="row-amount">
      <td class="label-cell">{$L['amountPaid']}:</td>
      <td class="value-cell">{$totalPaidFmt}</td>
      <td class="label-cell">{$L['paymentMethod']}:</td>
      <td class="value-cell">{$payLabel}</td>
    </tr>
    <tr class="row-remaining">
      <td class="label-cell">{$L['remaining']}:</td>
      <td class="value-cell">{$remainingFmt}</td>
      <td class="label-cell">{$L['receivedBy']}:</td>
      <td class="value-cell">{$creatorName}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['createdAt']}:</td>
      <td class="value-cell" colspan="3">{$createdAt}</td>
    </tr>
  </table>

  <hr class="divider">

  <!-- Footer notes -->
  <div class="footer">
    <div class="footer-title">{$L['importantTitle']}</div>
    <div class="footer-subtitle">{$L['refundPolicy']}</div>
    <ul>
      <li>{$L['rule1']}</li>
      <li>{$L['rule2']}</li>
      <li>{$L['rule3']}</li>
    </ul>
  </div>

</div>
</body>
</html>
HTML;
    }
}