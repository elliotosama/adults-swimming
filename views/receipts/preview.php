<?php // views/receipts/preview.php
require ROOT . '/views/includes/layout_top.php';

function formatAmPm(string $time): string {
    if (empty($time)) return '—';
    try {
        $dt = new DateTime($time);
        return $dt->format('g:i A'); // e.g. "9:30 AM", "2:00 PM"
    } catch (\Exception $e) {
        return $time;
    }
}

// Build WhatsApp message (Arabic + English)
$clientPhone = preg_replace('/\s+/', '', ($receipt['country_code'] ?? '') . ($receipt['phone_number'] ?? ''));

$planName    = htmlspecialchars($receipt['plan_name']    ?? '—');
$captainName = htmlspecialchars($receipt['captain_name'] ?? '—');
$branchName  = htmlspecialchars($receipt['branch_name']  ?? '—');
$firstSess   = $receipt['first_session']   ?? '—';
$lastSess    = $receipt['last_session']    ?? '—';
$renewalSess = $receipt['renewal_session'] ?? '—';
$rawExTime = $receipt['exercise_time'] ?? '';
$exTime = $rawExTime ? (function($t) {
    try { return (new DateTime($t))->format('g:i A'); }
    catch (\Exception $e) { return $t; }
})($rawExTime) : '—';
$level       = $receipt['level']           ?? '—';

// Get plan price from receipt
$planPrice = (float)($receipt['plan_price'] ?? 0);

// Sum transactions dynamically
$db = get_db();
$txRow = $db->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN type = 'refund'  THEN amount ELSE 0 END), 0) AS total_refunded
    FROM transactions
    WHERE receipt_id = ?
");
$txRow->execute([$receipt['id']]);
$txData = $txRow->fetch(PDO::FETCH_ASSOC);


$grossPaidCalc = (float) $txData['total_paid'];   // gross paid (before refunds)
$totalRefunded = (float) $txData['total_refunded'];
$netPaid       = $grossPaidCalc - $totalRefunded;
$remainingCalc = max(0, $planPrice - $netPaid);

// % of what the client actually paid that has been refunded so far
$refundPctCalc = $grossPaidCalc > 0 ? round(($totalRefunded / $grossPaidCalc) * 100) : 0;

$totalPaidCalc = $netPaid;  // Total Paid shown to the user is refund-adjusted (net)

$remaining = number_format($remainingCalc, 0);
$type = $_GET['type'] ?? 'new';

// Fetch client email
$emailStmt = $db->prepare("SELECT email as client_email FROM clients WHERE id = ? LIMIT 1");
$emailStmt->execute([$receipt['client_id']]);
$clientEmail = $emailStmt->fetchColumn() ?: null;

// PDF link to include in WhatsApp messages
$pdfUrl = ($type === 'refund')
    ? APP_URL . '/receipt/refund-pdf?id=' . $receipt['id']
    : APP_URL . '/receipt/pdf?id=' . $receipt['id'];

$pdfUrlEn = ($type === 'refund')
    ? APP_URL . '/receipt/refund-pdf?id=' . $receipt['id'] . '&lang=en'
    : APP_URL . '/receipt/pdf?id=' . $receipt['id'] . '&lang=en';

$pdfLink = $pdfUrl;

$waMessage = match($type) {
    'renewal' => rawurlencode(
        "Thanks for renewing your subscription.\n" .
        "تم تجديد اشتراكك، يمكنك تحميل الإيصال من خلال هذا الرابط:\n"
    ) . $pdfLink,

    'payment' => rawurlencode(
        "Thanks for completing your payment.\n" .
        "تم دفع متبقي الاشتراك، يمكنك تحميل الإيصال من خلال هذا الرابط:\n"
    ) . $pdfLink,

    'refund' => rawurlencode(
        "Your refund has been processed.\n" .
        "تم استرداد مبلغك، يمكنك تحميل الإيصال من خلال هذا الرابط:\n"
    ) . $pdfLink,

    default => rawurlencode(
        "Thanks for subscribing.\n" .
        "شكراً لاشتراكك، يمكنك تحميل الإيصال من خلال هذا الرابط:\n"
    ) . $pdfLink,
};

$waLink = "https://wa.me/{$clientPhone}?text={$waMessage}";
?>

<style>
:root {
    --surface-2:  #0d1821;
    --text-muted: #fff;
    --text:       #fff;
    --border:     #1a2e42;
    --surface:    #252736;;
    --accent:     #00b4d8;
    --success:    #34c789;
    --danger:     #e05c5c;
}

.preview-wrap {
    max-width: 640px;
    margin: 0 auto;
    padding: 32px 16px 60px;
    position: relative;
    z-index: 10;
}

.preview-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    position: relative;
    z-index: 10;
}

.preview-card-header {
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 10;
}

.preview-card-header .icon { font-size: 26px; }

.preview-card-header h2 {
    font-size: 17px;
    font-weight: 700;
    margin: 0;
    color: var(--text);
}

.preview-card-header p {
    font-size: 12px;
    color: var(--text-muted);
    margin: 2px 0 0;
}

.preview-receipt-id {
    margin-right: auto;
    font-size: 22px;
    font-weight: 800;
    color: var(--accent);
    letter-spacing: -0.5px;
}

.preview-body {
    padding: 24px;
    position: relative;
    z-index: 10;
}

.preview-section-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

.preview-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 32px;
    margin-bottom: 24px;
}

.preview-item {
    display: flex;
    align-items: baseline;
    flex: 0 1 auto;
    gap: 6px;
    white-space: nowrap;
}

.preview-item label {
    flex-shrink: 0;
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.preview-item span {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}

.preview-item span.muted   { color: var(--text-muted); font-weight: 400; }
.preview-item span.accent  { color: var(--accent); }
.preview-item span.success { color: var(--success); }
.preview-item span.danger  { color: var(--danger); }

.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.badge-status.not_completed { background: #2a1f0a; color: #fbbf24; border: 1px solid #7a5010; }
.badge-status.completed     { background: #0f2a1a; color: #22c55e; border: 1px solid #1a5c30; }
.badge-status.pending       { background: #1a1a2a; color: #818cf8; border: 1px solid #3730a3; }

.divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 20px 0;
}

.actions-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 10;
}

/* WhatsApp */
.btn-wa {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 13px 28px;
    background: #25d366;
    color: #fff;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    font-family: inherit;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.2s, transform 0.15s;
    box-shadow: 0 4px 18px rgba(37,211,102,0.30);
}
.btn-wa:hover { background: #1ebe5d; transform: translateY(-1px); }
.btn-wa svg  { width: 22px; height: 22px; flex-shrink: 0; }

/* Generic secondary link */
.btn-secondary-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 22px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-secondary-link:hover { border-color: var(--accent); color: var(--text); }

/* English PDF button — distinct teal/slate style */
.btn-pdf-en {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 22px;
    background: #0f2a38;
    border: 1px solid #00b4d844;
    border-radius: 10px;
    color: #00b4d8;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s, transform 0.15s;
    box-shadow: 0 4px 14px rgba(0,180,216,0.15);
}
.btn-pdf-en:hover {
    background: #133444;
    border-color: #00b4d8;
    transform: translateY(-1px);
}

/* Email */
.btn-email {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 13px 24px;
    background: #1a3a5c;
    color: #60a5fa;
    border: 1px solid #2563eb44;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s, transform 0.15s;
    box-shadow: 0 4px 18px rgba(37,99,235,0.18);
}
.btn-email:hover { background: #1e4a80; border-color: #3b82f6; transform: translateY(-1px); }
.btn-email svg  { width: 20px; height: 20px; flex-shrink: 0; }
.btn-email .email-address {
    font-size: 11px;
    font-weight: 400;
    opacity: 0.75;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-email-disabled {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 13px 24px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-email-disabled svg { width: 20px; height: 20px; flex-shrink: 0; }

/* Banners */
.success-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    background: #0f2a1a;
    border: 1px solid #1a5c30;
    border-radius: 10px;
    color: #86efac;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 24px;
    position: relative;
    z-index: 10;
}

.error-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    background: #2a0f0f;
    border: 1px solid #5c1a1a;
    border-radius: 10px;
    color: #fca5a5;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 24px;
    position: relative;
    z-index: 10;
}

.preview-wrap .page-header {
    position: relative;
    z-index: 10;
}
</style>

<div class="preview-wrap">

    <!-- Page header -->
    <div class="page-header" style="margin-bottom:20px; flex-direction: row;">
        <div>
            <h1 class="page-title">🧾 تفاصيل الايصال</h1>
            <p class="breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
        </div>
        <a href="<?= APP_URL ?>/receipts" class="btn btn-secondary" style="position: absolute; left: -30px;">→ الإيصالات</a>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="success-banner">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="error-banner">⚠️ <?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

<?php if (!empty($refundData)): ?>
<div style="
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 24px;
">

    <div style="
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom:16px;
        padding-bottom:12px;
        border-bottom:1px solid var(--border);
    ">
        <span style="
            color:#fff;
            font-size:18px;
            font-weight:700;
        ">
            ↩️ تفاصيل الاسترداد
        </span>

        <span style="
            color:var(--text-muted);
            font-size:13px;
        ">
            Refund Summary
        </span>
    </div>

    <div style="
        display:grid;
        grid-template-columns:repeat(4,1fr);
        gap:20px;
    ">

        <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                إجمالي المدفوع
            </div>
            <div style="font-size:24px;font-weight:700;color:#fff;">
                <?= number_format($refundData['gross_paid'],0) ?>
            </div>
        </div>

        <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                المبلغ المسترد
            </div>
            <div style="font-size:24px;font-weight:700;color:var(--danger);">
                <?= number_format($refundData['total_refunded'],0) ?>
            </div>
        </div>

        <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                المتبقي
            </div>
            <div style="
                font-size:24px;
                font-weight:700;
                color:<?= $refundData['remaining'] <= 0 ? 'var(--success)' : '#fbbf24' ?>;
            ">
                <?= number_format($refundData['remaining'],0) ?>
            </div>
        </div>

        <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                نسبة الاسترداد
            </div>
            <div style="font-size:24px;font-weight:700;color:#fff;">
                <?= $refundData['refund_pct'] ?>%
            </div>
        </div>

    </div>

</div>
<?php endif; ?>

    <!-- Receipt card -->
    <div class="preview-card">

        <div class="preview-card-header">
            <div class="icon">🧾</div>
            <div>
                <h2><?= htmlspecialchars($receipt['client_name']) ?></h2>
                <p><?= htmlspecialchars($receipt['phone_number'] ?? '—') ?></p>
            </div>
            <div class="preview-receipt-id">#<?= $receipt['id'] ?></div>
        </div>

        <div class="preview-body">

            <!-- § Client -->
            <div class="preview-section-title">👤 بيانات العميل</div>
            <div class="preview-grid">
                <div class="preview-item">
                    <label>الاسم</label>
                    <span><?= htmlspecialchars($receipt['client_name']) ?></span>
                </div>
                <div class="preview-item">
                    <label>الهاتف</label>
                    <span><?= htmlspecialchars(($receipt['country_code'] ?? '') . ' ' . ($receipt['phone_number'] ?? '—')) ?></span>
                </div>
                <?php if ($clientEmail): ?>
                <div class="preview-item">
                    <label>البريد الإلكتروني</label>
                    <span class="accent"><?= htmlspecialchars($clientEmail) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <hr class="divider">

            <!-- § Subscription -->
            <div class="preview-section-title">📋 تفاصيل الاشتراك</div>
            <div class="preview-grid">
                <div class="preview-item">
                    <label>الفرع</label>
                    <span><?= $branchName ?></span>
                </div>
                <div class="preview-item">
                    <label>الكابتن</label>
                    <span><?= $captainName ?></span>
                </div>
                <div class="preview-item">
                    <label>الخطة</label>
                    <span class="accent"><?= $planName ?></span>
                </div>
                <div class="preview-item">
                    <label>المستوي</label>
                    <span><?= htmlspecialchars((string)$level) ?></span>
                </div>
                <div class="preview-item">
                    <label>الحالة</label>
                    <?php
                        $statusLabels = [
                            'completed'     => 'مكتمل',
                            'not_completed' => 'غير مكتمل',
                        ];
                        $st = $receipt['receipt_status'] ?? 'not_completed';
                    ?>
                    <span>
                        <span class="badge-status <?= htmlspecialchars($st) ?>">
                            <?= htmlspecialchars($statusLabels[$st] ?? $st) ?>
                        </span>
                    </span>
                </div>
                <div class="preview-item">
                    <label>وقت التمرين</label>
                    <span><?= htmlspecialchars($exTime) ?></span>
                </div>
            </div>

            <hr class="divider">

            <!-- § Sessions -->
            <div class="preview-section-title">📅 مواعيد التمرين</div>
            <div class="preview-grid">
                <div class="preview-item">
                    <label>أول تمرين</label>
                    <span><?= htmlspecialchars($firstSess) ?></span>
                </div>
                <div class="preview-item">
                    <label>آخر تمرين</label>
                    <span><?= htmlspecialchars($lastSess) ?></span>
                </div>
                <div class="preview-item">
                    <label>جلسة التجديد</label>
                    <span class="accent"><?= htmlspecialchars($renewalSess) ?></span>
                </div>
            </div>

            <hr class="divider">

            <!-- § Payment -->
            <div class="preview-section-title">💳 الدفع</div>
            <div class="preview-grid">
                <div class="preview-item">
                    <label>قيمة الاشتراك</label>
                    <span class="accent"><?= number_format($planPrice, 0) ?></span>
                </div>
                <div class="preview-item">
                    <label>إجمالي المدفوع</label>
                    <span class="success"><?= number_format($grossPaidCalc, 0) ?></span>
                </div>
<?php if (!empty($receipt['is_refunded']) && $grossPaidCalc > 0): ?>
                <div class="preview-item">
                    <label>المسترد</label>
                    <span class="danger"><?= number_format($totalRefunded, 0) ?></span>
                </div>
                <div class="preview-item">
                    <label>نسبة الاسترداد</label>
                    <span class="danger"><?= $refundPctCalc ?>%</span>
                </div>
<?php endif; ?>
<div class="preview-item">
    <label>المتبقي</label>
    <span class="<?= $remainingCalc > 0 ? 'danger' : 'success' ?>"><?= number_format($remainingCalc, 0) ?></span>
</div>
                <div class="preview-item">
                    <label>طريقة الدفع</label>
                    <span><?= htmlspecialchars($receipt['payment_method'] ?? '—') ?></span>
                </div>
                <?php if (!empty($receipt['notes'])): ?>
                <div class="preview-item" style="flex-basis:100%; white-space:normal;">
                    <label>ملاحظات</label>
                    <span class="muted"><?= htmlspecialchars($receipt['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /preview-body -->
    </div><!-- /preview-card -->

    <!-- Action buttons -->
    <div class="actions-row">

        <!-- WhatsApp -->
        <a href="<?= $waLink ?>" target="_blank" class="btn-wa">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            إرسال واتساب / Send WhatsApp
        </a>

        <!-- Email -->
        <?php if ($clientEmail): ?>
        <form id="send-email-form" method="POST" action="<?= APP_URL ?>/receipt/send-email" style="display:inline;margin:0;">
            <input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <button type="submit" class="btn-email" id="send-email-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
                <span id="email-btn-text">إرسال بريد إلكتروني / Send Email</span>
                <span class="email-address">(<?= htmlspecialchars($clientEmail) ?>)</span>
            </button>
        </form>
        <div id="email-message" style="margin-top:15px;"></div>
        <?php else: ?>
        <span class="btn-email-disabled" title="لا يوجد بريد إلكتروني مسجّل لهذا العميل / No email on file">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
            لا يوجد بريد إلكتروني
        </span>
        <?php endif; ?>

        <!-- Arabic PDF (download) -->
	<div style="display: flex; gap:10px;">
	<a href="<?= $pdfUrl ?>&download=1"
           download="receipt-<?= (int)$receipt['id'] ?>-ar.pdf"
           class="btn-secondary-link">
            ⬇️ تحميل (عربي)
        </a>

        <!-- English PDF (download) -->
        <a href="<?= $pdfUrlEn ?>&download=1"
           download="receipt-<?= (int)$receipt['id'] ?>-en.pdf"
           class="btn-pdf-en">
            ⬇️ Download (English)
        </a>
            <?php if($_SESSION['user']['role'] === 'admin') { ?>
	</div>
        <a href="<?= APP_URL ?>/receipt/show?id=<?= $receipt['id'] ?>" class="btn-secondary-link">
            👁 عرض الإيصال الكامل
        </a>
        <?php }?>
        <?php if (!empty($receipt['is_refunded'])): ?>
    <a href="<?= APP_URL ?>/receipt/refund-pdf?id=<?= $receipt['id'] ?>" target="_blank" class="btn btn-secondary">
      ↩️ إيصال الاسترداد
    </a>
  <?php endif; ?>
    </div>

</div>

<?php require ROOT . '/views/includes/layout_bottom.php'; ?>
