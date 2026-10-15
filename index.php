<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

require_admin();
$types = vehicle_types();

$slots  = get_slots();
$counts = slot_counts($slots);

$totalUsers   = col('users')->countDocuments(['role' => 'user']);
$activeNow    = col('bookings')->countDocuments(['status' => 'active']);
$revenueTotal = sum_total(['status' => 'completed']);
$todayStart   = new UTCDateTime(strtotime('today') * 1000);
$revenueToday = sum_total(['status' => 'completed', 'exit_time' => ['$gte' => $todayStart]]);

// Revenue for the last 7 days
$since = new UTCDateTime(strtotime('today -6 days') * 1000);
$agg   = col('bookings')->aggregate([
    ['$match' => ['status' => 'completed', 'exit_time' => ['$gte' => $since]]],
    ['$group' => [
        '_id'     => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$exit_time', 'timezone' => config('timezone')]],
        'revenue' => ['$sum' => '$total'],
        'count'   => ['$sum' => 1],
    ]],
]);
$byDay = [];
foreach ($agg as $row) {
    $byDay[$row['_id']] = ['revenue' => (float)$row['revenue'], 'count' => (int)$row['count']];
}
$labels = $revenue = $sessions = [];
for ($i = 6; $i >= 0; $i--) {
    $key        = date('Y-m-d', strtotime("-$i day"));
    $labels[]   = date('D d', strtotime("-$i day"));
    $revenue[]  = round($byDay[$key]['revenue'] ?? 0, 2);
    $sessions[] = $byDay[$key]['count'] ?? 0;
}

// Bookings by vehicle type
$mix = ['car' => 0, 'bike' => 0, 'ev' => 0];
foreach (col('bookings')->aggregate([['$group' => ['_id' => '$vehicle_type', 'n' => ['$sum' => 1]]]]) as $row) {
    $mix[$row['_id']] = (int)$row['n'];
}

$recent = iterator_to_array(col('bookings')->find([], ['sort' => ['created_at' => -1], 'limit' => 8]), false);

$GLOBALS['page_scripts'] = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js'];
$GLOBALS['inline_js'] = 'window.PARK_CHARTS = ' . json_encode([
    'labels'    => $labels,
    'revenue'   => $revenue,
    'sessions'  => $sessions,
    'mixLabels' => array_map(fn($k) => $types[$k]['label'] ?? $k, array_keys($mix)),
    'mixData'   => array_values($mix),
    'occ'       => [$counts['available'], $counts['occupied'], $counts['maintenance']],
    'currency'  => config('currency'),
], JSON_HEX_TAG | JSON_HEX_AMP) . ';';

render_header('Admin Overview', 'app', 'a_overview');
?>
<div class="stat-grid">
    <div class="glass stat-card">
        <div class="stat-icon c-green"><i class="bi bi-currency-rupee"></i></div>
        <div><span class="stat-label">Revenue today</span><b class="stat-value" data-count="<?= $revenueToday ?>" data-decimals="2" data-prefix="<?= e(config('currency')) ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-cyan"><i class="bi bi-cash-stack"></i></div>
        <div><span class="stat-label">Total revenue</span><b class="stat-value" data-count="<?= $revenueTotal ?>" data-decimals="2" data-prefix="<?= e(config('currency')) ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-violet"><i class="bi bi-p-circle-fill"></i></div>
        <div><span class="stat-label">Parked right now</span><b class="stat-value" data-count="<?= $activeNow ?>">0</b></div>
    </div>
    <div class="glass stat-card">
        <div class="stat-icon c-pink"><i class="bi bi-people-fill"></i></div>
        <div><span class="stat-label">Registered users</span><b class="stat-value" data-count="<?= $totalUsers ?>">0</b></div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <div class="glass panel">
            <div class="panel-head"><h3><i class="bi bi-graph-up-arrow"></i> Revenue, last 7 days</h3></div>
            <div class="chart-box"><canvas id="revChart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="glass panel h-100">
            <div class="panel-head"><h3><i class="bi bi-pie-chart-fill"></i> Slot status</h3></div>
            <div class="chart-box sm"><canvas id="occChart"></canvas></div>
            <p class="hint text-center mt-2"><?= (int)$counts['total'] ?> slots · <?= (int)$counts['percent'] ?>% occupied</p>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-4">
        <div class="glass panel h-100">
            <div class="panel-head"><h3><i class="bi bi-car-front-fill"></i> Vehicle mix</h3></div>
            <div class="chart-box sm"><canvas id="mixChart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="glass panel h-100">
            <div class="panel-head">
                <h3><i class="bi bi-activity"></i> Latest bookings</h3>
                <a class="link-more" href="<?= e(url('admin/bookings.php')) ?>">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if (!$recent): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p>No bookings yet.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-glass align-middle">
                    <thead><tr><th>Booking</th><th>Vehicle</th><th>Slot</th><th>Entry</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $b): ?>
                        <tr>
                            <td class="mono"><?= e($b['booking_no']) ?></td>
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
    </div>
</div>

<div class="glass panel mt-4">
    <div class="panel-head">
        <h3><i class="bi bi-grid-3x3-gap-fill"></i> Live slot map</h3>
        <div class="legend"><span><i class="dot ok"></i>Available</span><span><i class="dot bad"></i>Occupied</span><span><i class="dot warn"></i>Maintenance</span></div>
    </div>
    <div id="slotMap" data-live="1"><?php render_slot_map($slots, false); ?></div>
</div>
<?php render_footer(); ?>
