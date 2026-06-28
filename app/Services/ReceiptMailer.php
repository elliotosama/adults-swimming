<?php
// app/Services/ReceiptMailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ReceiptMailer
{
    // ── Configure these constants or pull from your config file ──────────────
    private const SMTP_HOST       = 'smtp.gmail.com';
    private const SMTP_PORT       = 587;
    private const SMTP_USERNAME   = 'osama.ramadan.esmail@gmail.com';
    private const SMTP_PASSWORD   = 'ctktploi lsoogueu'; // your app password (no spaces)
    private const FROM_EMAIL      = 'osama.ramadan.esmail@gmail.com';
    private const FROM_NAME       = 'Swimming academy';

    /**
     * Send a receipt summary email to the client.
     *
     * @param array  $receipt     Full receipt row (as returned by findById)
     * @param float  $totalPaid
     * @param float  $remaining
     * @param string $type        'new' | 'renewal' | 'payment' | 'refund'
     * @param string $toEmail     Recipient email address
     * @return bool               true on success
     * @throws Exception          on PHPMailer failure
     */
    public static function send(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $type,
        string $toEmail
    ): bool {
        $mail = new PHPMailer(true);

        // ── SMTP setup ───────────────────────────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = self::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = self::SMTP_USERNAME;
        $mail->Password   = self::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = self::SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // ── Recipients ───────────────────────────────────────────────────────
        $mail->setFrom(self::FROM_EMAIL, self::FROM_NAME);
        $mail->addAddress($toEmail, $receipt['client_name'] ?? '');

        // ── Subject & body ───────────────────────────────────────────────────
        [$subject, $html] = self::buildEmail($receipt, $totalPaid, $remaining, $type);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = self::buildPlainText($receipt, $totalPaid, $remaining, $type);

        $mail->send();
        return true;
    }

    // ── Internal builders ─────────────────────────────────────────────────────

    /**
     * Build the PDF URL for a given receipt ID.
     */
    private static function pdfUrl(int $receiptId): string
    {
        return rtrim(APP_URL, '/') . '/receipt/pdf?id=' . $receiptId;
    }

    private static function buildEmail(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $type
    ): array {
        $clientName  = htmlspecialchars($receipt['client_name']    ?? '—');
        $receiptId   = (int) ($receipt['id']                       ?? 0);
        $planName    = htmlspecialchars($receipt['plan_name']       ?? '—');
        $captainName = htmlspecialchars($receipt['captain_name']    ?? '—');
        $branchName  = htmlspecialchars($receipt['branch_name']     ?? '—');
        $firstSess   = htmlspecialchars($receipt['first_session']   ?? '—');
        $lastSess    = htmlspecialchars($receipt['last_session']    ?? '—');
        $renewalSess = htmlspecialchars($receipt['renewal_session'] ?? '—');
        $exTime      = htmlspecialchars($receipt['exercise_time']   ?? '—');
        $level       = htmlspecialchars((string)($receipt['level']  ?? '—'));
        $method      = htmlspecialchars($receipt['payment_method']  ?? '—');

        $paidFmt      = number_format($totalPaid, 0);
        $remainingFmt = number_format($remaining, 0);

        // PDF link
        $pdfUrl = self::pdfUrl($receiptId);

        // Colours & labels per type
        $typeConfig = [
            'renewal' => [
                'icon'    => '🔄',
                'label'   => 'Subscription Renewed / تم تجديد اشتراكك',
                'subject' => "🔄 Subscription Renewed — Receipt #{$receiptId}",
                'accent'  => '#00b4d8',
                'banner'  => 'Your subscription has been successfully renewed.',
            ],
            'payment' => [
                'icon'    => '💳',
                'label'   => 'Payment Received / تم تسجيل دفعتك',
                'subject' => "💳 Payment Received — Receipt #{$receiptId}",
                'accent'  => '#34c789',
                'banner'  => 'Your payment has been recorded successfully.',
            ],
            'refund'  => [
                'icon'    => '↩️',
                'label'   => 'Refund Processed / تم استرداد مبلغك',
                'subject' => "↩️ Refund Processed — Receipt #{$receiptId}",
                'accent'  => '#e05c5c',
                'banner'  => 'Your refund has been processed successfully.',
            ],
            'new'     => [
                'icon'    => '🏋️',
                'label'   => 'Subscription Details / تفاصيل اشتراكك',
                'subject' => "🏋️ Welcome! Your Receipt #{$receiptId}",
                'accent'  => '#00b4d8',
                'banner'  => 'Thank you for subscribing! Here are your details.',
            ],
        ];

        $cfg     = $typeConfig[$type] ?? $typeConfig['new'];
        $subject = $cfg['subject'];
        $accent  = $cfg['accent'];

        // ── Show subscription block only for non-payment/refund types ────────
        $subscriptionBlock = '';
        if (in_array($type, ['new', 'renewal'], true)) {
            $subscriptionBlock = <<<HTML
            <tr>
              <td style="padding:0 32px 24px;">
                <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#5a7a96;
                           text-transform:uppercase;letter-spacing:0.8px;
                           border-bottom:1px solid #1a2e42;padding-bottom:8px;">
                  📋 Subscription Details / تفاصيل الاشتراك
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Branch / الفرع</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$branchName}</span>
                    </td>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Captain / الكابتن</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$captainName}</span>
                    </td>
                  </tr>
                  <tr>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Plan / الخطة</span>
                      <span style="font-size:14px;font-weight:700;color:{$accent};">{$planName}</span>
                    </td>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Level / المستوى</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$level}</span>
                    </td>
                  </tr>
                  <tr>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Training Time / وقت التمرين</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$exTime}</span>
                    </td>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Renewal Session / جلسة التجديد</span>
                      <span style="font-size:14px;font-weight:700;color:{$accent};">{$renewalSess}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:0 32px 24px;">
                <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#5a7a96;
                           text-transform:uppercase;letter-spacing:0.8px;
                           border-bottom:1px solid #1a2e42;padding-bottom:8px;">
                  📅 Sessions / الجلسات
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">First Session / أول جلسة</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$firstSess}</span>
                    </td>
                    <td width="50%" style="padding:6px 0;vertical-align:top;">
                      <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Last Session / آخر جلسة</span>
                      <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$lastSess}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
HTML;
        }

        // Payment row colour
        $remainingColor = $remaining > 0 ? '#e05c5c' : '#34c789';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#0a1520;font-family:'Segoe UI',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0"
         style="background:#0a1520;min-height:100vh;">
    <tr>
      <td align="center" style="padding:40px 16px;">

        <!-- Card -->
        <table width="600" cellpadding="0" cellspacing="0" border="0"
               style="max-width:600px;width:100%;background:#111d2b;
                      border:1px solid #1a2e42;border-radius:16px;overflow:hidden;">

          <!-- Header -->
          <tr>
            <td style="background:#0d1821;border-bottom:1px solid #1a2e42;padding:24px 32px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td>
                    <span style="font-size:28px;">{$cfg['icon']}</span>
                  </td>
                  <td style="padding-right:12px;">
                    <p style="margin:0;font-size:18px;font-weight:700;color:#e0eaf4;">
                      {$cfg['label']}
                    </p>
                    <p style="margin:4px 0 0;font-size:12px;color:#5a7a96;">
                      {$clientName}
                    </p>
                  </td>
                  <td align="left">
                    <span style="font-size:24px;font-weight:800;color:{$accent};
                                 letter-spacing:-0.5px;">#{$receiptId}</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Banner -->
          <tr>
            <td style="padding:20px 32px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background:#0f2a1a;border:1px solid #1a5c30;
                              border-radius:10px;padding:14px 18px;">
                    <span style="font-size:14px;font-weight:600;color:#86efac;">
                      ✅ {$cfg['banner']}
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Spacer -->
          <tr><td style="height:24px;"></td></tr>

          <!-- Subscription block (new/renewal only) -->
          {$subscriptionBlock}

          <!-- Payment section -->
          <tr>
            <td style="padding:0 32px 24px;">
              <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#5a7a96;
                         text-transform:uppercase;letter-spacing:0.8px;
                         border-bottom:1px solid #1a2e42;padding-bottom:8px;">
                💳 Payment / الدفع
              </p>
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="33%" style="padding:6px 0;vertical-align:top;">
                    <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Paid / المدفوع</span>
                    <span style="font-size:20px;font-weight:800;color:#34c789;">{$paidFmt}</span>
                  </td>
                  <td width="33%" style="padding:6px 0;vertical-align:top;">
                    <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Remaining / المتبقي</span>
                    <span style="font-size:20px;font-weight:800;color:{$remainingColor};">{$remainingFmt}</span>
                  </td>
                  <td width="33%" style="padding:6px 0;vertical-align:top;">
                    <span style="display:block;font-size:11px;color:#5a7a96;font-weight:600;text-transform:uppercase;">Method / الطريقة</span>
                    <span style="font-size:14px;font-weight:600;color:#e0eaf4;">{$method}</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- PDF Download Button -->
          <tr>
            <td style="padding:0 32px 28px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background:#0d1f30;border:1px solid #1a3a5c;
                              border-radius:10px;padding:16px 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td>
                          <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#e0eaf4;">
                            📄 إيصالك بصيغة PDF / Your PDF Receipt
                          </p>
                          <p style="margin:0;font-size:12px;color:#5a7a96;">
                            اضغط على الزر لعرض أو تحميل الإيصال / Click the button to view or download
                          </p>
                        </td>
                        <td align="left" style="white-space:nowrap;padding-right:12px;">
                          <a href="{$pdfUrl}"
                             target="_blank"
                             style="display:inline-block;padding:10px 20px;
                                    background:{$accent};color:#fff;
                                    border-radius:8px;font-size:13px;
                                    font-weight:700;text-decoration:none;
                                    letter-spacing:0.3px;">
                            ⬇ فتح PDF
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#0d1821;border-top:1px solid #1a2e42;
                        padding:18px 32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#5a7a96;">
                شكراً لك 🙏 Thank you for choosing us
              </p>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

        return [$subject, $html];
    }

    private static function buildPlainText(
        array  $receipt,
        float  $totalPaid,
        float  $remaining,
        string $type
    ): string {
        $id      = $receipt['id'] ?? '';
        $name    = $receipt['client_name'] ?? '—';
        $paid    = number_format($totalPaid, 0);
        $rem     = number_format($remaining, 0);
        $pdfUrl  = self::pdfUrl((int) $id);

        return implode("\n", [
            "Receipt #{$id} — {$name}",
            str_repeat('─', 40),
            "Plan:      " . ($receipt['plan_name']       ?? '—'),
            "Branch:    " . ($receipt['branch_name']     ?? '—'),
            "Captain:   " . ($receipt['captain_name']    ?? '—'),
            "First:     " . ($receipt['first_session']   ?? '—'),
            "Last:      " . ($receipt['last_session']    ?? '—'),
            "Renewal:   " . ($receipt['renewal_session'] ?? '—'),
            str_repeat('─', 40),
            "Paid:      {$paid}",
            "Remaining: {$rem}",
            str_repeat('─', 40),
            "PDF Receipt: {$pdfUrl}",
            str_repeat('─', 40),
            "Thank you 🙏",
        ]);
    }
}