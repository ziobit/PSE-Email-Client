<?php
declare(strict_types=1);

// Fixture-only manifest rendering: no live server, mailbox, or network access.
// php -n -d extension=tokenizer tests/pwa-launch-manifest.php
define('PSE_SETTINGS_FILE', '/fixture/settings.json');

function pseDefaults(): array
{
  return ['app_title' => 'PSE Email'];
}

function pseReadJson(string $path): array
{
  return ['app_title' => 'Personal Mail'];
}

function psePwaScriptPath(): string
{
  return '/mail/pse.php';
}

function psePwaScopePath(): string
{
  return '/mail/';
}

function psePwaOrigin(): string
{
  return 'https://mail.example';
}

function psePwaLogoFile(): string
{
  return '/fixture/missing-logo.png';
}

function check(bool $condition, string $description): void
{
  if (!$condition) throw new RuntimeException($description);
}

$source = file_get_contents(dirname(__DIR__) . '/index.php');
check((bool)preg_match('/^function pseServePwaManifest\b/m', $source, $match, PREG_OFFSET_CAPTURE), 'Manifest renderer is present.');
$tokens = token_get_all('<?php ' . substr($source, $match[0][1]));
$body = '';
$depth = 0;
$started = false;
foreach (array_slice($tokens, 1) as $token) {
  $body .= is_array($token) ? $token[1] : $token;
  if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
    $depth++;
    $started = true;
  } elseif ($token === '}') {
    $depth--;
    if ($started && $depth === 0) break;
  }
}
eval($body);

// The production renderer exits after writing its response. Inspect that real
// response in a shutdown callback rather than changing its implementation.
ob_start();
register_shutdown_function(function (): void {
  $manifest = json_decode((string)ob_get_clean(), true);
  check(is_array($manifest), 'Renderer emits a valid JSON manifest.');
  check(($manifest['launch_handler']['client_mode'] ?? '') === 'focus-existing', 'Installed app launches reuse a window without navigation.');
  check(($manifest['id'] ?? '') === '/mail/pse.php' && ($manifest['start_url'] ?? '') === '/mail/pse.php', 'App identity and ordinary startup URL remain stable.');
  check(count($manifest['file_handlers'] ?? []) === 1, 'PSE has one file handler.');
  $handler = $manifest['file_handlers'][0];
  check(($handler['launch_type'] ?? '') === 'single-client', 'Opening several files uses one launch.');
  check(($handler['action'] ?? '') === '/mail/pse.php?open_pse=1', 'File launches retain the explicit fast-start URL.');
  check(($handler['accept']['application/vnd.pse.email+json'] ?? []) === ['.pse'], 'The portable-email association remains registered.');
  check(($handler['icons'][0]['src'] ?? '') === '/mail/pse.php?pwa=cat-icon&size=192', 'Document launches retain the black-cat icon.');
  check(($manifest['name'] ?? '') === 'Personal Mail', 'The configured app name remains intact.');
  echo "PWA launch manifest fixtures passed.\n";
});
pseServePwaManifest();
