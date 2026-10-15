<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$uid  = $user['_id'];
$errors = [];
$passErrors = [];

if (is_post()) {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors[] = 'Please enter your full name (2–60 characters).';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
            $errors[] = 'Phone number looks invalid.';
        }
        if (!$errors) {
            col('users')->updateOne(['_id' => $uid], ['$set' => ['name' => $name, 'phone' => $phone]]);
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    }

    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $cnf = (string)($_POST['confirm'] ?? '');
        if (!password_verify($cur, $user['password'])) {
            $passErrors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $passErrors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $cnf) {
            $passErrors[] = 'New passwords do not match.';
        }
        if (!$passErrors) {
            col('users')->updateOne(['_id' => $uid], ['$set' => ['password' => password_hash($new, PASSWORD_DEFAULT)]]);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
}

$bookingCount = col('bookings')->countDocuments(['user_id' => $uid]);
$spent        = sum_total(['user_id' => $uid, 'status' => 'completed']);

render_header('Profile', 'app', 'profile');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="glass panel profile-card">
            <div class="avatar xl"><?= e(initials($user['name'])) ?></div>
            <h3><?= e($user['name']) ?></h3>
            <p class="muted"><?= e($user['email']) ?></p>
            <span class="badge-status s-<?= $user['role'] === 'admin' ? 'active' : 'completed' ?>"><?= $user['role'] === 'admin' ? 'Administrator' : 'Member' ?></span>
            <div class="profile-stats">
                <div><b><?= $bookingCount ?></b><small>Bookings</small></div>
                <div><b><?= e(money($spent)) ?></b><small>Spent</small></div>
            </div>
            <small class="muted">Member since <?= e(fmt_dt($user['created_at'], 'd M Y')) ?></small>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="glass panel mb-4">
            <div class="panel-head"><h3><i class="bi bi-person-lines-fill"></i> Personal details</h3></div>
            <?php if ($errors): ?>
                <div class="inline-alert danger"><i class="bi bi-exclamation-octagon-fill"></i>
                    <div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div class="row g-3">
                    <div class="col-md-6"><label class="field-label">Full name</label>
                        <input class="form-control" name="name" value="<?= e($_POST['name'] ?? $user['name']) ?>" required></div>
                    <div class="col-md-6"><label class="field-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>"></div>
                    <div class="col-12"><label class="field-label">Email</label>
                        <input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                </div>
                <button class="btn btn-neon mt-4" type="submit"><i class="bi bi-check2"></i> Save changes</button>
            </form>
        </div>

        <div class="glass panel">
            <div class="panel-head"><h3><i class="bi bi-shield-lock-fill"></i> Change password</h3></div>
            <?php if ($passErrors): ?>
                <div class="inline-alert danger"><i class="bi bi-exclamation-octagon-fill"></i>
                    <div><?php foreach ($passErrors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div class="row g-3">
                    <div class="col-12"><label class="field-label">Current password</label>
                        <input class="form-control" type="password" name="current" required></div>
                    <div class="col-md-6"><label class="field-label">New password</label>
                        <input class="form-control" type="password" name="new" required></div>
                    <div class="col-md-6"><label class="field-label">Confirm new password</label>
                        <input class="form-control" type="password" name="confirm" required></div>
                </div>
                <button class="btn btn-ghost mt-4" type="submit"><i class="bi bi-key"></i> Update password</button>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
