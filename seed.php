<?php
declare(strict_types=1);
/**
 * One-time setup: creates indexes, the admin + demo accounts, parking slots
 * and some sample history so the dashboards look alive.
 *
 * Run from a terminal:   php setup/seed.php
 * or open in a browser:  http://localhost/smart-parking/setup/seed.php
 *
 * It is safe to run more than once (existing data is never overwritten).
 */
require __DIR__ . '/../includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

$log = [];

try {
    // ---- indexes ---------------------------------------------------------
    col('users')->createIndex(['email' => 1], ['unique' => true]);
    col('vehicles')->createIndex(['user_id' => 1, 'plate' => 1], ['unique' => true]);
    col('slots')->createIndex(['code' => 1], ['unique' => true]);
    col('slots')->createIndex(['status' => 1, 'type' => 1]);
    col('bookings')->createIndex(['booking_no' => 1], ['unique' => true]);
    col('bookings')->createIndex(['user_id' => 1, 'status' => 1, 'entry_time' => -1]);
    col('bookings')->createIndex(['vehicle_id' => 1, 'status' => 1]);
    $log[] = 'Indexes created.';

    // ---- accounts --------------------------------------------------------
    $accounts = [
        ['Admin User', 'admin@parksmart.com', 'Admin@123', 'admin', '9000000001'],
        ['Demo Driver', 'demo@parksmart.com', 'Demo@1234', 'user', '9000000002'],
    ];
    $ids = [];
    foreach ($accounts as [$name, $email, $pass, $role, $phone]) {
        $existing = col('users')->findOne(['email' => $email]);
        if ($existing) {
            $ids[$email] = $existing['_id'];
            $log[] = "User $email already exists.";
            continue;
        }
        $res = col('users')->insertOne([
            'name' => $name, 'email' => $email, 'phone' => $phone,
            'password' => password_hash($pass, PASSWORD_DEFAULT),
            'role' => $role, 'created_at' => new UTCDateTime(), 'last_login' => null,
        ]);
        $ids[$email] = $res->getInsertedId();
        $log[] = "Created $role account: $email / $pass";
    }
    $demoId = $ids['demo@parksmart.com'];

    // ---- slots -----------------------------------------------------------
    if (col('slots')->countDocuments() === 0) {
        $plan = [['A', 'car', 12], ['B', 'bike', 10], ['C', 'ev', 6]];
        $docs = [];
        foreach ($plan as [$floor, $type, $n]) {
            for ($i = 1; $i <= $n; $i++) {
                $docs[] = [
                    'code' => sprintf('%s-%02d', $floor, $i), 'floor' => $floor, 'type' => $type,
                    'rate' => (float)config('rates')[$type], 'status' => 'available',
                    'current_booking' => null, 'created_at' => new UTCDateTime(),
                ];
            }
        }
        col('slots')->insertMany($docs);
        $log[] = 'Created ' . count($docs) . ' parking slots (floors A, B, C).';
    } else {
        $log[] = 'Slots already exist, skipped.';
    }

    // ---- demo vehicles ---------------------------------------------------
    $demoVehicles = [
        ['TN66AB1234', 'car', 'Hyundai i20', 'White'],
        ['TN66CD5678', 'bike', 'Royal Enfield', 'Black'],
        ['TN66EV0001', 'ev', 'Tata Nexon EV', 'Blue'],
    ];
    $vehByType = [];
    foreach ($demoVehicles as [$plate, $type, $model, $color]) {
        $v = col('vehicles')->findOne(['user_id' => $demoId, 'plate' => $plate]);
        if (!$v) {
            $r = col('vehicles')->insertOne([
                'user_id' => $demoId, 'plate' => $plate, 'type' => $type,
                'model' => $model, 'color' => $color, 'created_at' => new UTCDateTime(),
            ]);
            $v = ['_id' => $r->getInsertedId(), 'plate' => $plate, 'type' => $type];
        }
        $vehByType[$type] = $v;
    }

    // ---- sample booking history -------------------------------------------
    if (col('bookings')->countDocuments() === 0) {
        $slots = get_slots();
        mt_srand(2026);
        $docs = [];
        for ($i = 0; $i < 34; $i++) {
            $slot     = $slots[mt_rand(0, count($slots) - 1)];
            $veh      = $vehByType[$slot['type']];
            $duration = mt_rand(1, 6) * 3600 + mt_rand(0, 50) * 60;
            $entry    = strtotime('today') - mt_rand(0, 6) * 86400 + mt_rand(7, 19) * 3600 + mt_rand(0, 59) * 60;
            $entry    = min($entry, time() - $duration - 120);
            $exit     = $entry + $duration;
            $fee      = calc_fee($entry, $exit, (float)$slot['rate']);
            $docs[] = [
                'booking_no' => gen_booking_no(), 'user_id' => $demoId, 'vehicle_id' => $veh['_id'],
                'plate' => $veh['plate'], 'vehicle_type' => $slot['type'],
                'slot_id' => $slot['_id'], 'slot_code' => $slot['code'], 'floor' => $slot['floor'],
                'rate' => (float)$slot['rate'], 'expected_hours' => max(1, intdiv($duration, 3600)),
                'entry_time' => new UTCDateTime($entry * 1000), 'exit_time' => new UTCDateTime($exit * 1000),
                'status' => 'completed', 'duration_minutes' => $fee['minutes'], 'billable_hours' => $fee['hours'],
                'subtotal' => $fee['subtotal'], 'tax' => $fee['tax'], 'total' => $fee['total'],
                'payment_method' => ['upi', 'card', 'cash', 'wallet'][mt_rand(0, 3)],
                'paid_at' => new UTCDateTime($exit * 1000), 'created_at' => new UTCDateTime($entry * 1000),
            ];
        }
        col('bookings')->insertMany($docs);
        $log[] = 'Added ' . count($docs) . ' sample completed bookings for the demo user.';
    } else {
        $log[] = 'Bookings already exist, skipped sample history.';
    }
    $ok = true;
} catch (Throwable $e) {
    $ok = false;
    $log[] = 'ERROR: ' . $e->getMessage();
}

if (PHP_SAPI === 'cli') {
    echo implode(PHP_EOL, $log), PHP_EOL;
    exit($ok ? 0 : 1);
}

render_header('Setup', 'auth');
?>
<div class="auth-panel" style="min-height:100vh">
    <div class="glass auth-card" style="max-width:640px">
        <h1><?= $ok ? 'Setup complete ✅' : 'Setup failed ❌' ?></h1>
        <ul class="setup-log">
            <?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
        </ul>
        <?php if ($ok): ?>
            <div class="inline-alert info"><i class="bi bi-info-circle-fill"></i>
                For security, delete the <code>setup/</code> folder (or change the demo passwords) before deploying anywhere public.</div>
            <a class="btn btn-neon w-100 mt-3" href="<?= e(url('login.php')) ?>">Go to login <i class="bi bi-arrow-right"></i></a>
        <?php else: ?>
            <a class="btn btn-ghost w-100 mt-3" href="<?= e(url('setup/check.php')) ?>">Run environment check</a>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
