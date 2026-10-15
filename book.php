<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

$user  = require_login();
$uid   = $user['_id'];
$types = vehicle_types();

$vehicles = iterator_to_array(col('vehicles')->find(['user_id' => $uid], ['sort' => ['created_at' => -1]]), false);
$errors   = [];

if (is_post()) {
    csrf_verify();
    $vehicleId = oid($_POST['vehicle_id'] ?? null);
    $slotId    = oid($_POST['slot_id'] ?? null);
    $hours     = max(1, min(24, (int)($_POST['hours'] ?? 1)));

    $vehicle = $vehicleId ? col('vehicles')->findOne(['_id' => $vehicleId, 'user_id' => $uid]) : null;
    if (!$vehicle) {
        $errors[] = 'Please choose one of your vehicles.';
    }
    if (!$slotId) {
        $errors[] = 'Please choose a parking slot.';
    }
    if ($vehicle && col('bookings')->countDocuments(['vehicle_id' => $vehicle['_id'], 'status' => 'active']) > 0) {
        $errors[] = $vehicle['plate'] . ' is already parked. Check out first.';
    }

    if (!$errors) {
        // Atomically claim the slot: only succeeds if it is still available and fits the vehicle.
        $slot = col('slots')->findOneAndUpdate(
            ['_id' => $slotId, 'status' => 'available', 'type' => $vehicle['type']],
            ['$set' => ['status' => 'occupied']]
        );
        if (!$slot) {
            $errors[] = 'Sorry, that slot was just taken or does not fit your vehicle. Please pick another one.';
        } else {
            try {
                $res = col('bookings')->insertOne([
                    'booking_no'     => gen_booking_no(),
                    'user_id'        => $uid,
                    'vehicle_id'     => $vehicle['_id'],
                    'plate'          => $vehicle['plate'],
                    'vehicle_type'   => $vehicle['type'],
                    'slot_id'        => $slot['_id'],
                    'slot_code'      => $slot['code'],
                    'floor'          => $slot['floor'],
                    'rate'           => (float)$slot['rate'],
                    'expected_hours' => $hours,
                    'entry_time'     => new UTCDateTime(),
                    'exit_time'      => null,
                    'status'         => 'active',
                    'created_at'     => new UTCDateTime(),
                ]);
            } catch (Throwable $ex) {
                col('slots')->updateOne(['_id' => $slot['_id']], ['$set' => ['status' => 'available']]);
                throw $ex;
            }
            col('slots')->updateOne(['_id' => $slot['_id']], ['$set' => ['current_booking' => $res->getInsertedId()]]);
            flash('success', 'Slot ' . $slot['code'] . ' is yours. Drive in and enjoy!');
            redirect('dashboard.php');
        }
    }
}

$available = get_slots(['status' => 'available']);
$byFloor   = [];
foreach ($available as $s) {
    $byFloor[$s['floor']][] = $s;
}
ksort($byFloor);
$preSlot = oid($_GET['slot'] ?? null);

render_header('Book Parking', 'app', 'book');
?>
<?php if (!$vehicles): ?>
    <div class="glass panel empty-big">
        <i class="bi bi-car-front-fill"></i>
        <h3>Add a vehicle first</h3>
        <p class="muted">You need at least one vehicle in your garage before booking a slot.</p>
        <a class="btn btn-neon" href="<?= e(url('vehicles.php')) ?>"><i class="bi bi-plus-lg"></i> Add vehicle</a>
    </div>
<?php else: ?>
<?php if ($errors): ?>
    <div class="inline-alert danger mb-3"><i class="bi bi-exclamation-octagon-fill"></i>
        <div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
<?php endif; ?>

<form method="post" id="bookForm" class="row g-4" data-pre-slot="<?= $preSlot ? e((string)$preSlot) : '' ?>"
      data-tax="<?= e((string)config('tax_percent')) ?>" data-grace="<?= (int)config('grace_minutes') ?>">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="glass panel mb-4">
            <div class="panel-head"><h3><span class="step-dot">1</span> Choose your vehicle</h3></div>
            <select class="form-select form-select-lg" name="vehicle_id" id="vehicle" required>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= e((string)$v['_id']) ?>" data-type="<?= e($v['type']) ?>"
                        <?= (($_POST['vehicle_id'] ?? '') === (string)$v['_id']) ? 'selected' : '' ?>>
                        <?= e($v['plate']) ?> — <?= e($types[$v['type']]['label'] ?? $v['type']) ?><?= !empty($v['model']) ? ' · ' . e($v['model']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="glass panel mb-4">
            <div class="panel-head">
                <h3><span class="step-dot">2</span> Pick a slot</h3>
                <div class="legend"><span><i class="dot ok"></i>Free</span><span><i class="dot sel"></i>Selected</span></div>
            </div>
            <?php if (!$available): ?>
                <div class="empty-state"><i class="bi bi-emoji-frown"></i><p>The lot is full right now. Please check back soon.</p></div>
            <?php endif; ?>
            <div id="pickerFloors">
            <?php foreach ($byFloor as $floor => $list): ?>
                <div class="floor" data-floor>
                    <div class="floor-head"><span><i class="bi bi-layers-fill"></i> Floor <?= e($floor) ?></span></div>
                    <div class="slot-grid">
                    <?php foreach ($list as $s):
                        $t = $types[$s['type']] ?? $types['car']; ?>
                        <label class="slot-pick" data-type="<?= e($s['type']) ?>">
                            <input type="radio" name="slot_id" value="<?= e((string)$s['_id']) ?>"
                                   data-rate="<?= (float)$s['rate'] ?>" data-code="<?= e($s['code']) ?>" data-floor="<?= e($s['floor']) ?>" required>
                            <span class="slot available" title="<?= e($s['code'] . ' · ' . money((float)$s['rate']) . '/hr') ?>">
                                <i class="bi <?= $t['icon'] ?>"></i><span><?= e($s['code']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="empty-state d-none" id="noMatch"><i class="bi bi-search"></i><p>No free slots for this vehicle type right now.</p></div>
        </div>

        <div class="glass panel">
            <div class="panel-head"><h3><span class="step-dot">3</span> Expected duration</h3></div>
            <div class="hours-row">
                <button type="button" class="icon-btn" data-hours="-1" aria-label="Less"><i class="bi bi-dash-lg"></i></button>
                <input class="form-control hours-input" type="number" name="hours" id="hours" min="1" max="24" value="<?= (int)($_POST['hours'] ?? 2) ?>">
                <button type="button" class="icon-btn" data-hours="1" aria-label="More"><i class="bi bi-plus-lg"></i></button>
                <span class="muted">hours (an estimate, you are billed for actual time)</span>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="glass panel summary sticky-top">
            <div class="panel-head"><h3><i class="bi bi-receipt"></i> Booking summary</h3></div>
            <dl>
                <div><dt>Vehicle</dt><dd id="sumVehicle">—</dd></div>
                <div><dt>Slot</dt><dd id="sumSlot">Not selected</dd></div>
                <div><dt>Rate</dt><dd id="sumRate">—</dd></div>
                <div><dt>Estimated hours</dt><dd id="sumHours">—</dd></div>
            </dl>
            <div class="sum-total"><span>Estimated cost<?= config('tax_percent') ? ' (incl. ' . e(config('tax_label')) . ')' : '' ?></span><b id="sumTotal">—</b></div>
            <button class="btn btn-neon w-100 mt-3" type="submit" id="bookBtn" disabled><i class="bi bi-lightning-charge-fill"></i> Confirm &amp; park</button>
            <p class="hint mt-3"><i class="bi bi-gift"></i> First <?= (int)config('grace_minutes') ?> minutes are free. Final fee is calculated at checkout.</p>
        </div>
    </div>
</form>
<?php endif; ?>
<?php render_footer(); ?>
