<?php
declare(strict_types=1);
/**
 * Environment checker. Open this page first if something does not work:
 *   http://localhost/smart-parking/setup/check.php
 * It deliberately does not depend on the rest of the app.
 */
$root = dirname(__DIR__);
$cfg  = is_file($root . '/config/config.php') ? require $root . '/config/config.php' : [];
$checks = [];

$checks[] = ['PHP version ≥ 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), 'Running PHP ' . PHP_VERSION];
$checks[] = ['mongodb PHP extension', extension_loaded('mongodb'),
    extension_loaded('mongodb') ? 'Loaded (v' . phpversion('mongodb') . ')' : 'Not loaded. Install ext-mongodb and enable it in php.ini (extension=mongodb), then restart Apache.'];
$checks[] = ['mbstring extension', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Loaded' : 'Enable extension=mbstring in php.ini'];

$hasVendor = is_file($root . '/vendor/autoload.php');
$checks[] = ['Composer dependencies', $hasVendor, $hasVendor ? 'vendor/ found' : 'Run "composer install" in the project folder'];

$connOk = false; $connMsg = 'Skipped (fix the items above first)';
if (extension_loaded('mongodb') && $hasVendor) {
    try {
        require_once $root . '/vendor/autoload.php';
        $client = new MongoDB\Client($cfg['mongo_uri'] ?? 'mongodb://127.0.0.1:27017', ['serverSelectionTimeoutMS' => 3000]);
        $client->selectDatabase('admin')->command(['ping' => 1]);
        $connOk = true;
        $connMsg = 'Connected to ' . ($cfg['mongo_uri'] ?? '');
    } catch (Throwable $e) {
        $connMsg = 'Could not connect: ' . $e->getMessage();
    }
}
$checks[] = ['MongoDB connection', $connOk, $connMsg];
$allOk = !in_array(false, array_column($checks, 1), true);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ParkSmart · Environment check</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at 20% 10%,#14234d,#050816 60%);font-family:system-ui,Segoe UI,Arial,sans-serif;color:#e9eefb}
.card{width:min(680px,92vw);padding:32px;border-radius:22px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(14px)}
h1{margin:0 0 4px;font-size:26px}p.sub{margin:0 0 22px;color:#9aa6c4}
.row{display:flex;gap:14px;align-items:flex-start;padding:14px 0;border-top:1px solid rgba(255,255,255,.08)}
.ic{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-weight:700;flex:none;font-size:14px}
.ok{background:rgba(52,211,153,.18);color:#34d399}.bad{background:rgba(251,113,133,.18);color:#fb7185}
b{display:block}small{color:#9aa6c4;word-break:break-word}
a.btn{display:inline-block;margin-top:22px;padding:12px 22px;border-radius:12px;background:linear-gradient(135deg,#22d3ee,#8b5cf6);color:#fff;text-decoration:none;font-weight:600}
</style></head>
<body><div class="card">
<h1>Environment check</h1><p class="sub">Verifies everything ParkSmart needs.</p>
<?php foreach ($checks as [$label, $pass, $msg]): ?>
<div class="row"><span class="ic <?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✓' : '✕' ?></span><div><b><?= htmlspecialchars($label) ?></b><small><?= htmlspecialchars($msg) ?></small></div></div>
<?php endforeach; ?>
<?php if ($allOk): ?><a class="btn" href="seed.php">Next: create demo data →</a>
<?php else: ?><small style="display:block;margin-top:18px">Fix the red items, then refresh this page.</small><?php endif; ?>
</div></body></html>
