<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user  = require_login();
$uid   = $user['_id'];
$types = vehicle_types();

$slots  = get_slots();
$counts = slot_counts($slots);

$active = iterator_to_array(col('bookings')->find(
    ['user_id' => $uid, 'status' => 'active'],
    ['sort' => ['entry_time' => -1]]
), false);

$recent = iterator_to_array(col('bookings')->find(
    ['user_id' => $uid],
    ['sort' => ['entry_time' => -1], 'limit' => 5]
), false);

$totalBookings = col('bookings')->countDocuments(['user_id' => $uid]);
$totalSpent    = sum_total(['user_id' => $uid, 'status' => 'completed']);
$vehicleCount  = col('vehicles')->countDocuments(['user_id' => $uid]);

render_header('Dashboard', 'app', 'dashboard');
?>
<div class="greeting">
    <div>
        <h2>Hello, <?= e(explode(' ', $user['name'])[0]) ?> <span class="wave">👋</span></h2>
        <p class="muted">Here is what is happening in the parking lot right now.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="glass stat-card">
        <div class="stat-icon c-cyan"><i class="bi bi-p-circle-fill"></i></div>
        <div><span class="stat-label">Active parkings</span><b class="stat-value" data-count="<?= count($active) ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-violet"><i class="bi bi-clock-history"></i></div>
        <div><span class="stat-label">Total bookings</span><b class="stat-value" data-count="<?= $totalBookings ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-pink"><i class="bi bi-wallet2"></i></div>
        <div><span class="stat-label">Total spent</span><b class="stat-value" data-count="<?= $totalSpent ?>" data-decimals="2" data-prefix="<?= e(config('currency')) ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-green"><i class="bi bi-check2-circle"></i></div>
        <div><span class="stat-label">Free slots now</span><b class="stat-value" data-stat="available" data-count="<?= $counts['available'] ?>">0</b></div>
    </div>
</div>

<?php if ($active): ?>
<h3 class="section-title"><i class="bi bi-broadcast"></i> Currently parked</h3>
<div class="booking-grid">
    <?php foreach ($active as $b):
        $t = $types[$b['vehicle_type']] ?? $types['car']; ?>
    <div class="glass booking-card" data-entry="<?= ts($b['entry_time']) ?>" data-rate="<?= (float)$b['rate'] ?>">
        <div class="bc-top">
            <div class="bc-slot"><i class="bi <?= $t['icon'] ?>"></i><div><b><?= e($b['slot_code']) ?></b><small>Floor <?= e($b['floor']) ?></small></div></div>
            <?= status_badge('active') ?>
        </div>
        <div class="bc-plate"><?= e($b['plate']) ?></div>
        <div class="bc-timer" data-timer>00:00:00</div>
        <div class="bc-meta">Since <?= e(fmt_dt($b['entry_time'], 'h:i A, d M')) ?> · <?= e(money((float)$b['rate'])) ?>/hr</div>
        <div class="bc-fee"><span>Fee so far (incl. tax)</span><b data-fee>—</b></div>
        <form method="post" action="<?= e(url('checkout.php')) ?>" class="bc-form" data-confirm="Check out and pay now?">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= e((string)$b['_id']) ?>">
            <select class="form-select form-select-sm" name="method" aria-label="Payment method">
                <?php foreach (payment_methods() as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-neon btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i> Pay &amp; exit</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <div class="glass panel">
            <div class="panel-head">
                <h3><i class="bi bi-grid-3x3-gap-fill"></i> Live slot map</h3>
                <div class="legend">
                    <span><i class="dot ok"></i>Available</span>
                    <span><i class="dot bad"></i>Occupied</span>
                    <span><i class="dot warn"></i>Maintenance</span>
                </div>
            </div>
            <div id="slotMap" data-live="1" data-clickable="1"><?php render_slot_map($slots, true); ?></div>
            <p class="hint"><i class="bi bi-info-circle"></i> Tap any green slot to book it. The map refreshes every 10 seconds.</p>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="glass panel mb-4">
            <div class="panel-head"><h3><i class="bi bi-pie-chart-fill"></i> Occupancy</h3></div>
            <div class="occupancy">
                <div class="ring" style="--p: <?= (int)$counts['percent'] ?>" data-ring><span><b data-stat="percent"><?= (int)$counts['percent'] ?></b>%</span></div>
                <ul>
                    <li><i class="dot ok"></i> Available <b data-stat="available"><?= $counts['available'] ?></b></li>
                    <li><i class="dot bad"></i> Occupied <b data-stat="occupied"><?= $counts['occupied'] ?></b></li>
                    <li><i class="dot warn"></i> Maintenance <b data-stat="maintenance"><?= $counts['maintenance'] ?></b></li>
                </ul>
            </div>
        </div>

        <div class="glass panel">
            <div class="panel-head"><h3><i class="bi bi-lightning-fill"></i> Quick actions</h3></div>
            <div class="quick-actions">
                <a class="btn btn-neon" href="<?= e(url('book.php')) ?>"><i class="bi bi-p-square-fill"></i> Book parking</a>
                <a class="btn btn-ghost" href="<?= e(url('vehicles.php')) ?>"><i class="bi bi-car-front-fill"></i> My vehicles (<?= $vehicleCount ?>)</a>
                <a class="btn btn-ghost" href="<?= e(url('bookings.php')) ?>"><i class="bi bi-receipt-cutoff"></i> Booking history</a>
            </div>
        </div>
    </div>
</div>

<div class="glass panel mt-4">
    <div class="panel-head">
        <h3><i class="bi bi-clock-history"></i> Recent activity</h3>
        <a class="link-more" href="<?= e(url('bookings.php')) ?>">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i><p>No bookings yet. Book your first slot!</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-glass align-middle">
            <thead><tr><th>Booking</th><th>Vehicle</th><th>Slot</th><th>Entry</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $b): ?>
                <tr>
                    <td><a href="<?= e(url('receipt.php?id=' . $b['_id'])) ?>" class="mono"><?= e($b['booking_no']) ?></a></td>
                    <td><?= e($b['plate']) ?></td>
                    <td><?= e($b['slot_code']) ?></td>
                    <td><?= e(fmt_dt($b['entry_time'])) ?></td>
                    <td><?= $b['status'] === 'completed' ? e(money((float)$b['total'])) : '—' ?></td>
                    <td><?= status_badge($b['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
