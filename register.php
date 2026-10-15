<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$errors = [];

if (is_post()) {
    csrf_verify();
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $pass    = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $errors[] = 'Please enter your full name (2–60 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $errors[] = 'Phone number looks invalid.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$errors && col('users')->countDocuments(['email' => $email]) > 0) {
        $errors[] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $res = col('users')->insertOne([
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => password_hash($pass, PASSWORD_DEFAULT),
            'role'       => 'user',
            'created_at' => new UTCDateTime(),
            'last_login' => new UTCDateTime(),
        ]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (string)$res->getInsertedId();
        flash('success', 'Account created. Add your vehicle to start parking!');
        redirect('vehicles.php');
    }
}

render_header('Create account', 'auth');
?>
<div class="auth-wrap">
    <section class="auth-art">
        <a class="brand" href="<?= e(url()) ?>">
            <span class="brand-mark">P</span>
            <span class="brand-text"><?= e(config('app_name')) ?><small>Smart Parking</small></span>
        </a>
        <div class="auth-art-body">
            <h2>Join thousands of drivers who<br><span class="grad-text">park without stress.</span></h2>
            <p>Create your free account, add your vehicle and book a slot in under a minute.</p>
            <div class="glass lot-card">
                <div class="lot-head"><span><i class="bi bi-broadcast"></i> Live parking lot</span><small><span data-mini-free>0</span> free</small></div>
                <div class="mini-lot" data-mini-lot></div>
            </div>
        </div>
        <div class="float-chip fc-2"><i class="bi bi-lightning-charge-fill"></i> EV bay ready</div>
        <div class="float-chip fc-4"><i class="bi bi-qr-code"></i> Digital receipts</div>
    </section>

    <section class="auth-panel">
        <div class="glass auth-card">
            <h1>Create your account</h1>
            <p class="muted">It only takes a minute.</p>

            <?php if ($errors): ?>
                <div class="inline-alert danger"><i class="bi bi-exclamation-octagon-fill"></i>
                    <div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <label class="field-label" for="name">Full name</label>
                <div class="input-icon"><i class="bi bi-person"></i>
                    <input class="form-control" id="name" name="name" value="<?= old('name') ?>" placeholder="Karthik Kumar" required autofocus></div>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="field-label" for="email">Email</label>
                        <div class="input-icon"><i class="bi bi-envelope"></i>
                            <input class="form-control" type="email" id="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" required></div>
                    </div>
                    <div class="col-md-5">
                        <label class="field-label" for="phone">Phone <span class="opt">(optional)</span></label>
                        <div class="input-icon"><i class="bi bi-telephone"></i>
                            <input class="form-control" id="phone" name="phone" value="<?= old('phone') ?>" placeholder="98765 43210"></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="password">Password</label>
                        <div class="input-icon"><i class="bi bi-lock"></i>
                            <input class="form-control" type="password" id="password" name="password" placeholder="Min. 8 characters" required>
                            <button class="eye" type="button" data-toggle-pass="#password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                    </div>
                    <div class="col-md-6">
                        <label class="field-label" for="confirm">Confirm password</label>
                        <div class="input-icon"><i class="bi bi-lock-fill"></i>
                            <input class="form-control" type="password" id="confirm" name="confirm" placeholder="Repeat password" required></div>
                    </div>
                </div>
                <div class="strength" data-strength-for="#password"><span></span><small>Password strength</small></div>

                <button class="btn btn-neon w-100 mt-4" type="submit">Create account <i class="bi bi-arrow-right"></i></button>
            </form>

            <p class="auth-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
        </div>
    </section>
</div>
<?php render_footer(); ?>
