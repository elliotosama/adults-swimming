<?php
// app/Services/ReceiptPdfGenerator.php

require_once ROOT . '/vendor/autoload.php';

use Mpdf\Mpdf;

class ReceiptPdfGenerator {

    /**
     * Generate and stream a receipt PDF to the browser.
     */
public static function generate(
    array  $receipt,
    float  $totalPaid,
    float  $remaining,
    string $paymentMethod,
    string $lang = 'ar'
): void {
    $mpdf = self::makeMpdf($lang);
    $mpdf->WriteHTML(self::buildHtml($receipt, $totalPaid, 0, $remaining, $paymentMethod, $lang));
    $filename = 'receipt_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
    $mpdf->Output($filename, 'I');
}

    /**
     * Save PDF to disk and return the filename.
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
    $mpdf->WriteHTML(self::buildHtml($receipt, $totalPaid, 0, $remaining, $paymentMethod, $lang));
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0775, true);
    }
    $filename = 'receipt_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
    $mpdf->Output(rtrim($saveDir, '/') . '/' . $filename, 'F');
    return $filename;
}

    /**
     * Generate and stream a REFUND receipt PDF to the browser.
     *
     * Same layout/template as the normal receipt, but adds a
     * "refunded amount" row and shows the post-refund paid /
     * remaining figures instead of the original ones.
     */


    public static function generateRefund(
    array  $receipt,
    float  $grossPaid,
    float  $totalRefunded,
    float  $remaining,
    float  $refundAmount,
    string $paymentMethod,
    string $lang = 'ar'
): void {
    $mpdf = self::makeMpdf($lang);
    $mpdf->WriteHTML(self::buildHtml(
        $receipt, $grossPaid, $totalRefunded, $remaining, $paymentMethod, $lang, $refundAmount
    ));
    $filename = 'refund_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
    $mpdf->Output($filename, 'I');
}

    /**
     * Save a REFUND receipt PDF to disk and return the filename.
     */


    public static function saveRefund(
    array  $receipt,
    float  $grossPaid,
    float  $totalRefunded,
    float  $remaining,
    float  $refundAmount,
    string $paymentMethod,
    string $saveDir,
    string $lang = 'ar'
): string {
    $mpdf = self::makeMpdf($lang);
    $mpdf->WriteHTML(self::buildHtml(
        $receipt, $grossPaid, $totalRefunded, $remaining, $paymentMethod, $lang, $refundAmount
    ));
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0775, true);
    }
    $filename = 'refund_' . $receipt['id'] . '_' . date('Ymd') . ($lang === 'en' ? '_en' : '') . '.pdf';
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
            'format'        => [148, 175],   // tight height — just enough for content
            'margin_top'    => 8,
            'margin_bottom' => 3,
            'margin_left'   => 10,
            'margin_right'  => 10,
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

    /**
     * @param float|null $refundAmount  When provided, this renders the
     *                                  document as a REFUND receipt:
     *                                  the title, the "payment method"
     *                                  label, and an extra "amount
     *                                  refunded" row all switch to the
     *                                  refund wording, while
     *                                  $totalPaid / $remaining are
     *                                  expected to already be the
     *                                  POST-refund figures.
     */

    private static function buildHtml(
    array   $receipt,
    float   $totalPaid,       // normal receipt: netPaid | refund receipt: grossPaid
    float   $totalRefunded,   // 0 for normal; total refunded for refund receipt
    float   $remaining,
    string  $paymentMethod,
    string  $lang,
    ?float  $refundAmount = null
): string {
    $isEn     = ($lang === 'en');
    $isRefund = ($refundAmount !== null);

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
    $age         = htmlspecialchars((string)($receipt['age']    ?? '—'));

    $rawExTime = $receipt['exercise_time'] ?? '';
    $exTime    = '';
    if ($rawExTime && $rawExTime !== '—') {
        try {
            $exTime = (new DateTime($rawExTime))->format('g:i A');
        } catch (\Exception $e) {
            $exTime = $rawExTime;
        }
    }
    $exTime      = htmlspecialchars($exTime ?: '—');
    $createdAt   = htmlspecialchars($receipt['created_at']   ?? '—');
    $creatorName = htmlspecialchars($receipt['creator_name'] ?? '—');

    $paymentMethodLabels = $isEn
        ? ['cash' => 'Cash', 'instapay' => 'InstaPay', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'Bank Transfer']
        : ['cash' => 'نقداً', 'instapay' => 'instapay', 'vodafone_cash' => 'Vodafone Cash', 'bank_transfer' => 'تحويل بنكي'];
    $payLabel = htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? $paymentMethod);

    // For refund receipts: show gross paid; for normal: show net paid
    $totalPaidFmt    = number_format($totalPaid, 0);
    $remainingFmt    = number_format($remaining, 0);
    // "Returned to Client" always reflects the cumulative total refunded on
    // this receipt (not just the amount of the single refund transaction
    // that triggered this PDF) — matches the modal and the percentage math.
    $refundedFmt     = number_format($totalRefunded, 0);
    $totalRefundFmt  = number_format($totalRefunded, 0);

    // ── Refund breakdown ──────────────────────────────────────
    // Percentage returned to the client: totalRefunded ÷ grossPaid × 100
    // Percentage kept by the academy:    netKept       ÷ grossPaid × 100
    // (netKept = what the client actually paid, net of everything refunded)
    $refundPct = 0;
    $netKeptPct = 0;
    $netKept    = 0.0;
    if ($isRefund) {
        $netKept = max(0, $totalPaid - $totalRefunded);
        if ($totalPaid > 0) {
            $refundPct  = round(($totalRefunded / $totalPaid) * 100);
            $netKeptPct = round(($netKept / $totalPaid) * 100);
        }
    }
    $refundPctFmt  = $refundPct . '%';
    $netKeptFmt    = number_format($netKept, 0);
    $netKeptPctFmt = $netKeptPct . '%';

    // ── Logo ─────────────────────────────────────────────────
    $logoPath = ROOT . '/assets/images/logo.jpeg';
    $logoImg  = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = mime_content_type($logoPath);
        $logoSrc  = 'data:' . $logoMime . ';base64,' . $logoData;
        $logoImg  = '<img src="' . $logoSrc . '" style="width:55px;height:55px;object-fit:contain;">';
    }

    // ── Labels ───────────────────────────────────────────────
    $L = $isEn ? [
        'dir'            => 'ltr',
        'htmlLang'       => 'en',
        'receiptTitle'   => $isRefund ? 'Refund Receipt' : 'Cash Receipt',
        'receiptNo'      => 'Receipt No.',
        'memberNo'       => 'Member No.',
        'clientName'     => 'Client Name',
        'age'            => 'Age',
        'mobile'         => 'Mobile',
        'trainingTime'   => 'Training Time',
        'firstSession'   => 'First Session',
        'renewalDate'    => 'Renewal Date',
        'lastSession'    => 'Last Session',
        'planType'       => 'Subscription',
        'branch'         => 'Branch',
        'amountPaid'     => 'Amount Paid',
        'amountRefunded' => 'Returned to Client',
        'refundPct'      => 'Returned to Client %',
        'netKept'        => 'Net Kept by Academy',
        'netKeptPct'     => 'Academy %',
        'captain'        => 'Captain',
        'paymentMethod'  => $isRefund ? 'Refund Method' : 'Payment Method',
        'receivedBy'     => 'Received By',
        'remaining'      => 'Remaining',
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
        'receiptTitle'   => $isRefund ? 'إيصال استرداد' : 'إيصال استلام نقدية',
        'receiptNo'      => 'رقم الايصال',
        'memberNo'       => 'رقم العضوية',
        'clientName'     => 'اسم العميل',
        'age'            => 'السن',
        'mobile'         => 'رقم الموبايل',
        'trainingTime'   => 'ميعاد التمرين',
        'firstSession'   => 'الحصة الأولى',
        'renewalDate'    => 'تاريخ التجديد',
        'lastSession'    => 'الحصة الأخيرة',
        'planType'       => 'نوع الاشتراك',
        'branch'         => 'الفرع',
        'amountPaid'     => 'المبلغ المدفوع',
        'amountRefunded' => 'المسترد للعميل',
        'refundPct'      => 'نسبة الاسترداد للعميل',
        'netKept'        => 'صافي الايراد',
        'netKeptPct'     => 'نسبة الأكاديمية',
        'captain'        => 'الكابتن',
        'paymentMethod'  => $isRefund ? 'طريقة الاسترداد' : 'طريقة الدفع',
        'receivedBy'     => 'المستلم',
        'remaining'      => 'المتبقي',
        'createdAt'      => 'تاريخ الإنشاء',
        'importantTitle' => 'تعليمات هامة:',
        'refundPolicy'   => 'سياسة الإسترجاع:',
        'rule1'          => 'يتم خصم 30% من قيمة الاشتراك من بعد الحصة الأولى',
        'rule2'          => 'يتم خصم 50% من قيمة الاشتراك من بعد الحصة الثانية',
        'rule3'          => 'يتم التعويض عن الغياب بحد أقصى حصة وذلك فقط للمدة المتبقية في الاشتراك',
        'academyName'    => 'Adults Swimming Academy',
    ];

    $dir      = $L['dir'];
    $htmlLang = $L['htmlLang'];
    $memberNo = htmlspecialchars($receipt['member_number'] ?? $receipt['client_id'] ?? '—');

    // Extra rows shown only on refund receipts:
    //   Row 1 — amount returned to the client + % returned to the client
    //   Row 2 — net amount kept by the academy + % kept by the academy
    $refundRowHtml = '';
    if ($isRefund) {
        $refundRowHtml = <<<HTML
    <tr class="row-refunded">
      <td class="label-cell">{$L['amountRefunded']}:</td>
      <td class="value-cell">{$refundedFmt}</td>
      <td class="label-cell">{$L['refundPct']}:</td>
      <td class="value-cell">{$refundPctFmt}</td>
    </tr>
    <tr class="row-net-kept">
      <td class="label-cell">{$L['netKept']}:</td>
      <td class="value-cell">{$netKeptFmt}</td>
      <td class="label-cell">{$L['netKeptPct']}:</td>
      <td class="value-cell">{$netKeptPctFmt}</td>
    </tr>
HTML;
    }

    // For refund PDF: "Amount Paid" row shows gross paid (what client paid in total)
    // For normal PDF: shows net paid
    $amountPaidLabel = $isRefund ? ($isEn ? 'Gross Paid' : 'إجمالي المدفوع') : $L['amountPaid'];

    return <<<HTML
<!DOCTYPE html>
<html dir="{$dir}" lang="{$htmlLang}">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Cairo', 'Arial', sans-serif; font-size: 11px; color: #1a1a2e; direction: {$dir}; background: #fff; }
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  .academy-name { font-size: 13px; font-weight: 900; color: #1a3a8f; margin-top: 4px; }
  .title-block { text-align: center; margin: 6px 0 2px; }
  .receipt-title { font-size: 18px; font-weight: 900; color: #1a1a2e; }
  .receipt-number { font-size: 12px; font-weight: 700; color: #c0392b; margin-top: 2px; }
  .divider { border: none; border-top: 1.5px solid #1a3a6b; margin: 7px 0; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 4px 6px; font-size: 11px; vertical-align: middle; border-bottom: 1px solid #e8ecf0; }
  .info-table tr:last-child td { border-bottom: none; }
  .label-cell { color: #333; font-weight: 700; width: 22%; white-space: nowrap; }
  .value-cell { color: #1a1a2e; font-weight: 900; width: 28%; }
  .value-cell-full { color: #1a1a2e; font-weight: 900; }
  .row-amount td { background: #fff8e1; }
  .row-remaining td { background: #ffeaea; }
  .row-refunded td { background: #fff0e0; color: #c0392b; font-weight: 900; }
  .row-net-kept td { background: #eaf7ec; color: #1a6b3a; font-weight: 900; }
  .footer { background: #f8f9fc; border: 1px solid #dde2ee; border-radius: 5px; padding: 8px 12px; margin-top: 8px; }
  .footer-title { font-size: 12px; font-weight: 900; color: #c0392b; margin-bottom: 4px; }
  .footer-subtitle { font-size: 11px; font-weight: 700; color: #1a3a6b; margin-bottom: 3px; }
  .footer ul { margin: 0; padding: 0; list-style: none; }
  .footer ul li { font-size: 10.5px; color: #444; margin-bottom: 2px; padding-right: 6px; }
  .footer ul li::before { content: "- "; }
</style>
</head>
<body>
  <table class="header-table" dir="ltr">
    <tr>
      <td style="text-align:left;vertical-align:top;">
        {$logoImg}
        <div class="academy-name">{$L['academyName']}</div>
      </td>
    </tr>
  </table>
  <div class="title-block">
    <div class="receipt-title">{$L['receiptTitle']}</div>
    <div class="receipt-number">{$L['receiptNo']}: {$id}</div>
  </div>
  <hr class="divider">
  <table class="info-table">
    <tr>
      <td class="label-cell">{$L['memberNo']}:</td>
      <td class="value-cell">{$memberNo}</td>
      <td class="label-cell">{$L['trainingTime']}:</td>
      <td class="value-cell">{$exTime}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['clientName']}:</td>
      <td class="value-cell">{$clientName}</td>
      <td class="label-cell">{$L['firstSession']}:</td>
      <td class="value-cell">{$firstSess}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['age']}:</td>
      <td class="value-cell">{$age}</td>
      <td class="label-cell">{$L['renewalDate']}:</td>
      <td class="value-cell">{$renewalSess}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['mobile']}:</td>
      <td class="value-cell">{$phone}</td>
      <td class="label-cell">{$L['lastSession']}:</td>
      <td class="value-cell">{$lastSess}</td>
    </tr>
    <tr>
      <td class="label-cell">{$L['planType']}:</td>
      <td class="value-cell-full" colspan="3">{$planName}</td>
    </tr>
    <tr class="row-amount">
      <td class="label-cell">{$amountPaidLabel}:</td>
      <td class="value-cell">{$totalPaidFmt}</td>
      <td class="label-cell">{$L['branch']}:</td>
      <td class="value-cell">{$branchName}</td>
    </tr>
    {$refundRowHtml}
    <tr>
      <td class="label-cell">{$L['paymentMethod']}:</td>
      <td class="value-cell">{$payLabel}</td>
      <td class="label-cell">{$L['captain']}:</td>
      <td class="value-cell">{$captainName}</td>
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
  <div class="footer">
    <div class="footer-title">{$L['importantTitle']}</div>
    <div class="footer-subtitle">{$L['refundPolicy']}</div>
    <ul>
      <li>{$L['rule1']}</li>
      <li>{$L['rule2']}</li>
      <li>{$L['rule3']}</li>
    </ul>
  </div>
</body>
</html>
HTML;
}
}