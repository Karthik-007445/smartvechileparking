<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $error = 'Too many attempts. Please wait ' . ($_SESSION['lock_until'] - time()) . ' seconds.';
    } else {
        $found = $email !== '' ? col('users')->findOne(['email' => $email]) : null;
        if ($found && password_verify($pass, $found['password'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (string)$found['_id'];
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            col('users')->updateOne(['_id' => $found['_id']], ['$set' => ['last_login' => new UTCDateTime()]]);
            flash('success', 'Welcome back, ' . explode(' ', $found['name'])[0] . '!');
            redirect($found['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 60;
            $_SESSION['fails'] = 0;
        }
        $error = 'Invalid email or password.';
    }
}

render_header('Sign in', 'auth');
?>
<div class="auth-wrap">
    <section class="auth-art">
        <a class="brand" href="<?= e(url()) ?>">
            <span class="brand-mark">P</span>
            <span class="brand-text"><?= e(config('app_name')) ?><small>Smart Parking</small></span>
        </a>
        <div class="auth-art-body">
            <h2>Your next parking spot<br>is already <span class="grad-text">waiting.</span></h2>
            <p>Live availability, instant booking and automatic billing in one place.</p>
            <div class="glass lot-card">
                <div class="lot-head"><span><i class="bi bi-broadcast"></i> Live parking lot</span><small><span data-mini-free>0</span> free</small></div>
                <div class="mini-lot" data-mini-lot></div>
            </div>
        </div>
        <div class="float-chip fc-1"><i class="bi bi-check-circle-fill"></i> Slot A-07 booked</div>
        <div class="float-chip fc-4"><i class="bi bi-shield-check"></i> Secure sign-in</div>
    </section>

    <section class="auth-panel">
        <div class="glass auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to manage your parking.</p>

            <?php if ($error): ?>
                <div class="inline-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <label class="field-label" for="email">Email address</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required autofocus>
                </div>

                <label class="field-label" for="password">Password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
                    <button class="eye" type="button" data-toggle-pass="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>

                <button class="btn btn-neon w-100 mt-4" type="submit">Sign in <i class="bi bi-arrow-right"></i></button>
            </form>

            <div class="demo-box">
                <small>Demo accounts</small>
                <div>
                    <button type="button" class="chip-btn" data-fill-email="demo@parksmart.com" data-fill-pass="Demo@1234"><i class="bi bi-person"></i> User</button>
                    <button type="button" class="chip-btn" data-fill-email="admin@parksmart.com" data-fill-pass="Admin@123"><i class="bi bi-shield-lock"></i> Admin</button>
                </div>
            </div>

            <p class="auth-switch">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
        </div>
    </section>
</div>
<?php render_footer(); ?>
