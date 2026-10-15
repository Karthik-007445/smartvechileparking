<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

$autoload = ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:system-ui;background:#050816;color:#e9eefb;padding:40px">'
       . '<h2>Dependencies are missing</h2><p>Open a terminal in the project folder and run '
       . '<code style="background:#111a33;padding:3px 8px;border-radius:6px">composer install</code>, '
       . 'then open <code>setup/check.php</code> to verify your environment.</p></body>';
    exit;
}
require $autoload;

$GLOBALS['config'] = require ROOT_DIR . '/config/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_name('parksmart_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/functions.php';
require __DIR__ . '/layout.php';

set_exception_handler(function (Throwable $e): void {
    $isApi  = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
    $dbDown = $e instanceof MongoDB\Driver\Exception\Exception;
    $msg    = $dbDown
        ? 'Cannot reach MongoDB. Make sure the MongoDB service is running and the URI in config/config.php is correct.'
        : 'Something went wrong. Please try again.';

    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $msg]);
        return;
    }
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title>'
       . '<body style="font-family:system-ui;background:#050816;color:#e9eefb;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="max-width:560px;padding:32px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:rgba(255,255,255,.05)">'
       . '<h2 style="margin-top:0">&#9888; ' . ($dbDown ? 'Database unavailable' : 'Unexpected error') . '</h2>'
       . '<p style="color:#9aa6c4">' . htmlspecialchars($msg) . '</p>';
    if (config('debug')) {
        echo '<pre style="white-space:pre-wrap;color:#fb7185;font-size:12px">' . htmlspecialchars((string)$e) . '</pre>';
    }
    echo '<p><a style="color:#22d3ee" href="javascript:history.back()">&larr; Go back</a></p></div></body>';
});
