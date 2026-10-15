<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

$user  = require_login();
$uid   = $user['_id'];
$types = vehicle_types();
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $plate = clean_plate((string)($_POST['plate'] ?? ''));
        $type  = (string)($_POST['type'] ?? '');
        $model = trim((string)($_POST['model'] ?? ''));
        $color = trim((string)($_POST['color'] ?? ''));

        if ($plate === false) {
            $errors[] = 'Enter a valid registration number (4–12 letters/digits), e.g. TN66AB1234.';
        }
        if (!isset($types[$type])) {
            $errors[] = 'Choose a vehicle type.';
        }
        if (mb_strlen($model) > 40 || mb_strlen($color) > 20) {
            $errors[] = 'Model or colour is too long.';
        }
        if (!$errors && col('vehicles')->countDocuments(['user_id' => $uid, 'plate' => $plate]) > 0) {
            $errors[] = 'You have already added ' . $plate . '.';
        }
        if (!$errors) {
            col('vehicles')->insertOne([
                'user_id'    => $uid,
                'plate'      => $plate,
                'type'       => $type,
                'model'      => $model,
                'color'      => $color,
                'created_at' => new UTCDateTime(),
            ]);
            flash('success', $plate . ' added to your garage.');
            redirect('vehicles.php');
        }
    }

    if ($action === 'delete') {
        $vid = oid($_POST['vehicle_id'] ?? null);
        if ($vid) {
            if (col('bookings')->countDocuments(['vehicle_id' => $vid, 'status' => 'active']) > 0) {
                flash('danger', 'This vehicle is currently parked. Check out first.');
            } else {
                col('vehicles')->deleteOne(['_id' => $vid, 'user_id' => $uid]);
                flash('success', 'Vehicle removed.');
            }
        }
        redirect('vehicles.php');
    }
}

$vehicles = iterator_to_array(col('vehicles')->find(['user_id' => $uid], ['sort' => ['created_at' => -1]]), false);

render_header('My Vehicles', 'app', 'vehicles');
?>
<div class="row g-4">
    <div class="col-xl-8">
        <?php if (!$vehicles): ?>
            <div class="glass panel empty-big">
                <i class="bi bi-car-front-fill"></i>
                <h3>Your garage is empty</h3>
                <p class="muted">Add your first vehicle using the form to start booking slots.</p>
            </div>
        <?php else: ?>
        <div class="vehicle-grid">
            <?php foreach ($vehicles as $v):
                $t = $types[$v['type']] ?? $types['car']; ?>
            <div class="glass vehicle-card">
                <div class="vc-icon"><i class="bi <?= $t['icon'] ?>"></i></div>
                <div class="vc-info">
                    <div class="plate"><?= e($v['plate']) ?></div>
                    <small><?= e($t['label']) ?><?= !empty($v['model']) ? ' · ' . e($v['model']) : '' ?><?= !empty($v['color']) ? ' · ' . e($v['color']) : '' ?></small>
                </div>
                <form method="post" data-confirm="Remove <?= e($v['plate']) ?> from your garage?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="vehicle_id" value="<?= e((string)$v['_id']) ?>">
                    <button class="icon-btn danger" type="submit" title="Remove"><i class="bi bi-trash3"></i></button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="glass panel">
            <div class="panel-head"><h3><i class="bi bi-plus-circle-fill"></i> Add a vehicle</h3></div>
            <?php if ($errors): ?>
                <div class="inline-alert danger"><i class="bi bi-exclamation-octagon-fill"></i>
                    <div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <label class="field-label" for="plate">Registration number</label>
                <input class="form-control plate-input" id="plate" name="plate" value="<?= old('plate') ?>" placeholder="TN66AB1234" maxlength="14" required>

                <label class="field-label">Vehicle type</label>
                <div class="type-picker">
                    <?php foreach ($types as $k => $t): ?>
                    <label>
                        <input type="radio" name="type" value="<?= e($k) ?>" <?= (($_POST['type'] ?? 'car') === $k) ? 'checked' : '' ?>>
                        <span><i class="bi <?= $t['icon'] ?>"></i><?= e($t['label']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3">
                    <div class="col-7">
                        <label class="field-label" for="model">Model <span class="opt">(optional)</span></label>
                        <input class="form-control" id="model" name="model" value="<?= old('model') ?>" placeholder="Hyundai i20">
                    </div>
                    <div class="col-5">
                        <label class="field-label" for="color">Colour</label>
                        <input class="form-control" id="color" name="color" value="<?= old('color') ?>" placeholder="White">
                    </div>
                </div>
                <button class="btn btn-neon w-100 mt-4" type="submit"><i class="bi bi-plus-lg"></i> Add to garage</button>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
