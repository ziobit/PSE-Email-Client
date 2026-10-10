<?php
declare(strict_types=1);

// Called only against the temporary index.php copy created by javascript-syntax.cjs.
$file = $argv[1];
$mode = $argv[2];
$directory = dirname($file) . '/pse_data';
mkdir($directory, 0700, true);
$token = 'fixture-browser-token';
file_put_contents($directory . '/settings.json', json_encode([
  'initialized' => $mode !== 'setup',
  'auth_tokens' => [hash('sha256', $token)],
  'storage_key' => str_repeat('a', 64),
  'accounts' => [],
  'auto_update' => false
]));
$_SERVER = [
  'REQUEST_METHOD' => 'GET',
  'SCRIPT_NAME' => '/mail/index.php',
  'HTTP_HOST' => 'pse.example',
  'HTTPS' => 'on'
];
// Skip the production queue dispatcher; this fixture has no configured accounts.
$_GET = ['open_pse' => '1'];
$_COOKIE = $mode === 'authenticated' ? ['pse_auth' => $token] : [];
if ($mode === 'service-worker') $_GET = ['pwa' => 'sw'];
require $file;
