<?php
declare(strict_types=1);

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/* ------------------------------------------------------------------ */
/*  Config / database                                                  */
/* ------------------------------------------------------------------ */

function config(?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['config'] ?? [];
    return $key === null ? $cfg : ($cfg[$key] ?? $default);
}

function db(): MongoDB\Database
{
    static $db = null;
    if ($db === null) {
        $client = new MongoDB\Client(
            (string)config('mongo_uri'),
            ['serverSelectionTimeoutMS' => 5000, 'connectTimeoutMS' => 5000],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $db = $client->selectDatabase((string)config('mongo_db'));
    }
    return $db;
}

function col(string $name): MongoDB\Collection
{
    return db()->selectCollection($name);
}

/* ------------------------------------------------------------------ */
/*  Output / URL helpers                                               */
/* ------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = config('base_path');
    if (is_string($cfg)) {
        return $base = rtrim($cfg, '/');
    }
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $root    = realpath(ROOT_DIR);
    $base    = '';
    if ($docRoot && $root) {
        $d = rtrim(str_replace('\\', '/', $docRoot), '/');
        $r = rtrim(str_replace('\\', '/', $root), '/');
        if (stripos($r, $d) === 0) {
            $base = substr($r, strlen($d));
        }
    }
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/* ------------------------------------------------------------------ */
/*  Flash messages / CSRF                                              */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------ */
/*  Authentication                                                     */
/* ------------------------------------------------------------------ */

function oid(?string $id): ?ObjectId
{
    if ($id === null || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
        return null;
    }
    try {
        return new ObjectId($id);
    } catch (Throwable) {
        return null;
    }
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = oid($_SESSION['uid'] ?? null);
    if (!$id) {
        return $user = null;
    }
    $found = col('users')->findOne(['_id' => $id]);
    return $user = ($found ?: null);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (($u['role'] ?? 'user') !== 'admin') {
        flash('danger', 'Administrator access required.');
        redirect('dashboard.php');
    }
    return $u;
}

/* ------------------------------------------------------------------ */
/*  Formatting                                                         */
/* ------------------------------------------------------------------ */

function ts(mixed $d): int
{
    if ($d instanceof UTCDateTime) {
        return $d->toDateTime()->getTimestamp();
    }
    return (int)$d;
}

function fmt_dt(mixed $d, string $format = 'd M Y, h:i A'): string
{
    if (!$d) {
        return '—';
    }
    return date($format, ts($d));
}

function money(float|int $n): string
{
    return config('currency', '₹') . number_format((float)$n, 2);
}

function fmt_duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'h' . ($m ? ' ' . $m . 'm' : '');
}

function vehicle_types(): array
{
    return [
        'car'  => ['label' => 'Car',  'icon' => 'bi-car-front-fill'],
        'bike' => ['label' => 'Bike', 'icon' => 'bi-scooter'],
        'ev'   => ['label' => 'EV',   'icon' => 'bi-ev-front-fill'],
    ];
}

function status_badge(string $status): string
{
    $labels = [
        'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
        'available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance',
    ];
    return '<span class="badge-status s-' . e($status) . '">' . e($labels[$status] ?? ucfirst($status)) . '</span>';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: 'U';
}

function clean_plate(string $plate): string|false
{
    $p = strtoupper(preg_replace('/[\s\-]+/', '', $plate) ?? '');
    return preg_match('/^[A-Z0-9]{4,12}$/', $p) ? $p : false;
}

/* ------------------------------------------------------------------ */
/*  Slots / billing                                                    */
/* ------------------------------------------------------------------ */

function get_slots(array $filter = []): array
{
    $cursor = col('slots')->find($filter, ['sort' => ['floor' => 1, 'code' => 1]]);
    return iterator_to_array($cursor, false);
}

function slot_counts(array $slots): array
{
    $c = ['total' => count($slots), 'available' => 0, 'occupied' => 0, 'maintenance' => 0];
    foreach ($slots as $s) {
        $c[$s['status']] = ($c[$s['status']] ?? 0) + 1;
    }
    $usable = max(1, $c['total'] - $c['maintenance']);
    $c['percent'] = (int)round($c['occupied'] / $usable * 100);
    return $c;
}

function render_slot_map(array $slots, bool $clickable = true): void
{
    if (!$slots) {
        echo '<div class="empty-state"><i class="bi bi-p-square"></i><p>No parking slots configured yet.</p></div>';
        return;
    }
    $types  = vehicle_types();
    $floors = [];
    foreach ($slots as $s) {
        $floors[$s['floor']][] = $s;
    }
    ksort($floors);
    foreach ($floors as $floor => $list) {
        $free = count(array_filter($list, fn($s) => $s['status'] === 'available'));
        echo '<div class="floor"><div class="floor-head"><span><i class="bi bi-layers-fill"></i> Floor ' . e($floor)
           . '</span><small>' . $free . ' / ' . count($list) . ' free</small></div><div class="slot-grid">';
        foreach ($list as $s) {
            $t     = $types[$s['type']] ?? $types['car'];
            $title = $s['code'] . ' · ' . $t['label'] . ' · ' . money((float)$s['rate']) . '/hr · ' . ucfirst($s['status']);
            $icon  = $s['status'] === 'maintenance' ? 'bi-cone-striped' : $t['icon'];
            $inner = '<i class="bi ' . $icon . '"></i><span>' . e($s['code']) . '</span>';
            if ($clickable && $s['status'] === 'available') {
                echo '<a class="slot available" href="' . e(url('book.php?slot=' . $s['_id'])) . '" title="' . e($title) . '">' . $inner . '</a>';
            } else {
                echo '<div class="slot ' . e($s['status']) . '" title="' . e($title) . '">' . $inner . '</div>';
            }
        }
        echo '</div></div>';
    }
}

/** Parking fee: first $grace minutes free, then billed per started hour. */
function calc_fee(int $entry, int $exit, float $rate): array
{
    $seconds = max(0, $exit - $entry);
    $minutes = (int)ceil($seconds / 60);
    $grace   = (int)config('grace_minutes', 0);
    $hours   = $minutes <= $grace ? 0 : max(1, (int)ceil($minutes / 60));
    $sub     = round($hours * $rate, 2);
    $tax     = round($sub * ((float)config('tax_percent', 0)) / 100, 2);
    return [
        'minutes'  => $minutes,
        'hours'    => $hours,
        'subtotal' => $sub,
        'tax'      => $tax,
        'total'    => round($sub + $tax, 2),
    ];
}

function gen_booking_no(): string
{
    return 'PS' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
}

/**
 * Close an active booking, bill it and free the slot.
 * Pass $userId to restrict to the owner (null = admin override).
 */
function complete_booking(ObjectId $bookingId, ?ObjectId $userId, string $method): ?array
{
    $filter = ['_id' => $bookingId, 'status' => 'active'];
    if ($userId !== null) {
        $filter['user_id'] = $userId;
    }
    $b = col('bookings')->findOne($filter);
    if (!$b) {
        return null;
    }
    $now = time();
    $fee = calc_fee(ts($b['entry_time']), $now, (float)$b['rate']);
    $res = col('bookings')->updateOne($filter, ['$set' => [
        'status'           => 'completed',
        'exit_time'        => new UTCDateTime($now * 1000),
        'duration_minutes' => $fee['minutes'],
        'billable_hours'   => $fee['hours'],
        'subtotal'         => $fee['subtotal'],
        'tax'              => $fee['tax'],
        'total'            => $fee['total'],
        'payment_method'   => $method,
        'paid_at'          => new UTCDateTime($now * 1000),
    ]]);
    if ($res->getModifiedCount() !== 1) {
        return null;
    }
    col('slots')->updateOne(['_id' => $b['slot_id']], ['$set' => ['status' => 'available', 'current_booking' => null]]);
    return col('bookings')->findOne(['_id' => $bookingId]);
}

function sum_total(array $match): float
{
    $cursor = col('bookings')->aggregate([
        ['$match' => $match],
        ['$group' => ['_id' => null, 'sum' => ['$sum' => '$total']]],
    ]);
    foreach ($cursor as $row) {
        return (float)$row['sum'];
    }
    return 0.0;
}

function payment_methods(): array
{
    return ['upi' => 'UPI', 'card' => 'Card', 'cash' => 'Cash', 'wallet' => 'Wallet'];
}
