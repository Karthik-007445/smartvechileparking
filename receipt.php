<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id   = oid($_GET['id'] ?? null);
$b    = $id ? col('bookings')->findOne(['_id' => $id]) : null;

if (!$b || ($user['role'] !== 'admin' && (string)$b['user_id'] !== (string)$user['_id'])) {
    flash('danger', 'Receipt not found.');
    redirect('bookings.php');
}

$owner = col('users')->findOne(['_id' => $b['user_id']]);
$types = vehicle_types();
$t     = $types[$b['vehicle_type']] ?? $types['car'];
$done  = $b['status'] === 'completed';

$GLOBALS['page_scripts'] = ['https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'];

render_header('Receipt', 'app', 'bookings');
?>
<div class="receipt-actions no-print">
    <a class="btn btn-ghost" href="<?= e(url($user['role'] === 'admin' ? 'admin/bookings.php' : 'bookings.php')) ?>"><i class="bi bi-arrow-left"></i> Back</a>
    <button class="btn btn-neon" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print receipt</button>
</div>

<div class="receipt glass">
    <div class="receipt-head">
        <div class="brand">
            <span class="brand-mark">P</span>
            <span class="brand-text"><?= e(config('app_name')) ?><small>Parking receipt</small></span>
        </div>
        <div class="receipt-no">
            <small>Booking no.</small>
            <b class="mono"><?= e($b['booking_no']) ?></b>
        </div>
    </div>

    <div class="receipt-status <?= $done ? 'paid' : 'open' ?>">
        <i class="bi <?= $done ? 'bi-patch-check-fill' : 'bi-hourglass-split' ?>"></i>
        <?= $done ? 'PAID' : 'IN PROGRESS' ?>
    </div>

    <div class="receipt-grid">
        <div><small>Customer</small><b><?= e($owner['name'] ?? '—') ?></b></div>
        <div><small>Vehicle</small><b><i class="bi <?= $t['icon'] ?>"></i> <?= e($b['plate']) ?> (<?= e($t['label']) ?>)</b></div>
        <div><small>Slot</small><b><?= e($b['slot_code']) ?> · Floor <?= e($b['floor']) ?></b></div>
        <div><small>Rate</small><b><?= e(money((float)$b['rate'])) ?> / hour</b></div>
        <div><small>Entry</small><b><?= e(fmt_dt($b['entry_time'])) ?></b></div>
        <div><small>Exit</small><b><?= e(fmt_dt($b['exit_time'] ?? null)) ?></b></div>
    </div>

    <?php if ($done): ?>
    <div class="receipt-lines">
        <div><span>Parked for</span><b><?= e(fmt_duration((int)$b['duration_minutes'])) ?></b></div>
        <div><span>Billable hours</span><b><?= (int)$b['billable_hours'] ?></b></div>
        <div><span>Subtotal</span><b><?= e(money((float)$b['subtotal'])) ?></b></div>
        <?php if ((float)$b['tax'] > 0): ?>
        <div><span><?= e(config('tax_label')) ?> (<?= (int)config('tax_percent') ?>%)</span><b><?= e(money((float)$b['tax'])) ?></b></div>
        <?php endif; ?>
        <div class="grand"><span>Total paid</span><b><?= e(money((float)$b['total'])) ?></b></div>
        <div><span>Payment method</span><b><?= e(payment_methods()[$b['payment_method']] ?? ucfirst((string)$b['payment_method'])) ?></b></div>
    </div>
    <?php else: ?>
    <div class="inline-alert info"><i class="bi bi-info-circle-fill"></i>This vehicle is still parked. The final amount is calculated at checkout.</div>
    <?php endif; ?>

    <div class="receipt-foot">
        <div id="qrcode" data-text="<?= e($b['booking_no'] . '|' . $b['plate'] . '|' . $b['slot_code']) ?>"></div>
        <p>Thank you for parking with <?= e(config('app_name')) ?>.<br>Scan the code at the exit gate for verification.</p>
    </div>
</div>
<?php render_footer(); ?>
