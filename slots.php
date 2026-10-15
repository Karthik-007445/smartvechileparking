<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$types = vehicle_types();
$slots = get_slots();
$out   = [];
foreach ($slots as $s) {
    $t = $types[$s['type']] ?? $types['car'];
    $out[] = [
        'id'     => (string)$s['_id'],
        'code'   => $s['code'],
        'floor'  => $s['floor'],
        'type'   => $s['type'],
        'label'  => $t['label'],
        'icon'   => $s['status'] === 'maintenance' ? 'bi-cone-striped' : $t['icon'],
        'rate'   => (float)$s['rate'],
        'status' => $s['status'],
    ];
}

echo json_encode([
    'ok'       => true,
    'currency' => config('currency'),
    'counts'   => slot_counts($slots),
    'slots'    => $out,
    'time'     => date('c'),
]);
