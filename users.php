<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();

if (is_post()) {
    csrf_verify();
    $uid  = oid($_POST['user_id'] ?? null);
    $user = $uid ? col('users')->findOne(['_id' => $uid]) : null;

    if ($user && ($_POST['action'] ?? '') === 'role') {
        if ((string)$user['_id'] === (string)$admin['_id']) {
            flash('danger', 'You cannot change your own role.');
        } else {
            $new = $user['role'] === 'admin' ? 'user' : 'admin';
            col('users')->updateOne(['_id' => $uid], ['$set' => ['role' => $new]]);
            flash('success', $user['name'] . ' is now ' . ($new === 'admin' ? 'an administrator' : 'a regular user') . '.');
        }
    }
    redirect('admin/users.php');
}

$users = iterator_to_array(col('users')->find([], ['sort' => ['created_at' => -1]]), false);

// bookings per user
$bookingCounts = [];
foreach (col('bookings')->aggregate([['$group' => ['_id' => '$user_id', 'n' => ['$sum' => 1]]]]) as $row) {
    $bookingCounts[(string)$row['_id']] = (int)$row['n'];
}

render_header('Users', 'app', 'a_users');
?>
<div class="glass panel">
    <div class="table-responsive">
        <table class="table table-glass align-middle">
            <thead><tr><th>User</th><th>Email</th><th>Phone</th><th>Role</th><th>Bookings</th><th>Joined</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><div class="avatar sm"><?= e(initials($u['name'])) ?></div><b><?= e($u['name']) ?></b></div></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?? '') ?: '—' ?></td>
                    <td><span class="badge-status s-<?= $u['role'] === 'admin' ? 'active' : 'completed' ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'User' ?></span></td>
                    <td><?= $bookingCounts[(string)$u['_id']] ?? 0 ?></td>
                    <td><?= e(fmt_dt($u['created_at'], 'd M Y')) ?></td>
                    <td><?= e(fmt_dt($u['last_login'] ?? null)) ?></td>
                    <td class="text-end">
                        <?php if ((string)$u['_id'] !== (string)$admin['_id']): ?>
                        <form method="post" data-confirm="Change role for <?= e($u['name']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= e((string)$u['_id']) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= $u['role'] === 'admin' ? 'Make user' : 'Make admin' ?></button>
                        </form>
                        <?php else: ?><small class="muted">You</small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
