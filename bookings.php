<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_admin();

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'force_checkout') {
        $bid  = oid($_POST['booking_id'] ?? null);
        $done = $bid ? complete_booking($bid, null, 'cash') : null;
        if ($done) {
            flash('success', 'Checked out ' . $done['plate'] . ' · ' . money((float)$done['total']) . ' collected (cash).');
        } else {
            flash('danger', 'Could not check out that booking.');
        }
    }
    redirect('admin/bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : ''));
}

$filter = (string)($_GET['status'] ?? 'all');
$query  = in_array($filter, ['active', 'completed'], true) ? ['status' => $filter] : [];
if (!$query) {
    $filter = 'all';
}
$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $query['$or'] = [
        ['plate' => ['$regex' => preg_quote(strtoupper($search)), '$options' => 'i']],
        ['booking_no' => ['$regex' => preg_quote($search), '$options' => 'i']],
    ];
}

$rows  = iterator_to_array(col('bookings')->find($query, ['sort' => ['entry_time' => -1], 'limit' => 300]), false);
$users = [];
foreach ($rows as $r) {
    $users[(string)$r['user_id']] = true;
}
$userDocs = [];
if ($users) {
    $ids = array_map(fn($id) => oid($id), array_keys($users));
    foreach (col('users')->find(['_id' => ['$in' => array_filter($ids)]], ['projection' => ['name' => 1]]) as $u) {
        $userDocs[(string)$u['_id']] = $u['name'];
    }
}

render_header('All Bookings', 'app', 'a_bookings');
?>
<div class="toolbar">
    <div class="filter-tabs mb-0">
        <?php foreach (['all' => 'All', 'active' => 'Active', 'completed' => 'Completed'] as $k => $label): ?>
            <a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= e(url('admin/bookings.php?status=' . $k)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="search-box" method="get">
        <input type="hidden" name="status" value="<?= e($filter) ?>">
        <i class="bi bi-search"></i>
        <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search plate or booking no.">
    </form>
</div>

<div class="glass panel">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i><p>No bookings match.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-glass align-middle">
            <thead><tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Slot</th><th>Entry</th><th>Exit</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $b): ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('receipt.php?id=' . $b['_id'])) ?>"><?= e($b['booking_no']) ?></a></td>
                    <td><?= e($userDocs[(string)$b['user_id']] ?? '—') ?></td>
                    <td><?= e($b['plate']) ?></td>
                    <td><?= e($b['slot_code']) ?></td>
                    <td><?= e(fmt_dt($b['entry_time'])) ?></td>
                    <td><?= e(fmt_dt($b['exit_time'] ?? null)) ?></td>
                    <td><?= $b['status'] === 'completed' ? e(money((float)$b['total'])) : '—' ?></td>
                    <td><?= status_badge($b['status']) ?></td>
                    <td class="text-end">
                        <?php if ($b['status'] === 'active'): ?>
                        <form method="post" data-confirm="Force check-out <?= e($b['plate']) ?> and record a cash payment?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="force_checkout">
                            <input type="hidden" name="booking_id" value="<?= e((string)$b['_id']) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-box-arrow-right"></i> Check out</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
