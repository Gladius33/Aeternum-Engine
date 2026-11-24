<?php
declare(strict_types=1);
// Test harness only. Never deploy tests/ or use this router in production.
if (PHP_SAPI!=='cli-server' || getenv('AETERNUM_BROWSER_TEST')!=='1' || ($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1') {
    http_response_code(403);
    exit;
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
// Explicit allowlist: never serve source files, credentials or arbitrary paths.
if (in_array($path,['/play.html','/play.css','/play.js','/world-scene.js','/world-scene.css','/game-ui.css','/game-ui.js','/game-ui-profiles.js','/game-art.js','/living-art.js','/game-audio.js','/locale.js','/client-dist/aeternum-shell.js'],true)) return false;
// CI-only loopback observer verifies that the TLS proxy strips forged headers.
if ($path==='/__test/proxy-headers') {
    header('Content-Type: application/json');
    echo json_encode([
        'forwarded'=>$_SERVER['HTTP_X_FORWARDED_FOR']??null,
        'realIp'=>$_SERVER['HTTP_X_REAL_IP']??null
    ],JSON_THROW_ON_ERROR);
    return true;
}
if (is_string($path) && str_starts_with($path,'/v1/')) {
    // Emulate HTTPS termination by a trusted web server solely in loopback CI.
    $_SERVER['HTTPS']='on';
    require __DIR__.'/../../public/index.php';
    return true;
}
http_response_code(404);
return true;
