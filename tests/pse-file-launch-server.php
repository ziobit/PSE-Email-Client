<?php
declare(strict_types=1);

// Exercise the production login branch and pre-render queue guard with local
// fixtures. Neither test dispatches mailbox requests or touches live storage.
// php -n tests/pse-file-launch-server.php
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$queueRuns = 0;
$loginRuns = 0;
$fixtureAuthenticated = true;

function check(bool $condition, string $description): void
{
  if (!$condition) throw new RuntimeException($description);
}

function pseHandleActionQueue(array $settings): array
{
  global $queueRuns;
  $queueRuns++;
  return ['processed' => 2, 'failed' => 1, 'pending' => 4];
}

function pseActionQueueCount(): int
{
  return 7;
}

function pseLogin(array $settings): void
{
  global $loginRuns;
  $loginRuns++;
}

function pseIsAuthenticated(array $settings): bool
{
  global $fixtureAuthenticated;
  return $fixtureAuthenticated;
}

class FixtureJsonResponse extends RuntimeException
{
  public array $payload;

  public function __construct(array $payload)
  {
    parent::__construct('Fixture JSON response');
    $this->payload = $payload;
  }
}

function pseJson(array $payload, int $status = 200): void
{
  throw new FixtureJsonResponse($payload);
}

check((bool)preg_match("/    case 'login':(.*?)    case 'logout':/s", $source, $match), 'Production login branch is present.');
$loginBranch = $match[1];
$settings = ['initialized' => true, 'password_hash' => password_hash('fixture-password', PASSWORD_DEFAULT)];
foreach ([true, false, 'true', 1, null] as $fileFlag) {
  $queueRuns = 0;
  $loginRuns = 0;
  $data = ['password' => 'fixture-password', 'openPse' => $fileFlag];
  try {
    eval('switch ("login") { case "login":' . $loginBranch . '}');
    throw new RuntimeException('Login did not emit a response.');
  } catch (FixtureJsonResponse $response) {
    check(($response->payload['ok'] ?? false) === true && $loginRuns === 1, 'File launches still authenticate normally.');
    $fileLaunch = $fileFlag === true;
    check($queueRuns === ($fileLaunch ? 0 : 1), 'Only an explicit boolean file launch skips queued mailbox work at login.');
    check($response->payload['queue'] === ($fileLaunch
      ? ['processed' => 0, 'failed' => 0, 'pending' => 7]
      : ['processed' => 2, 'failed' => 1, 'pending' => 4]), 'Login reports pending work accurately without marking deferred actions as processed.');
  }
}

check((bool)preg_match('/\$pseAuthenticated = empty\(\$pseBootError\).*?(?=if \(\$pseAuthenticated && !headers_sent\(\))/s', $source, $match), 'Production pre-render queue guard is present.');
$renderGuard = $match[0];
foreach (['1', 1, true, '', '0', null, ['1']] as $fileFlag) {
  $queueRuns = 0;
  $_GET = $fileFlag === null ? [] : ['open_pse' => $fileFlag];
  $pseBootError = '';
  $pseSettings = [];
  eval($renderGuard);
  $fileLaunch = $fileFlag === '1';
  check($queueRuns === ($fileLaunch ? 0 : 1), 'Only the exact file-launch URL skips queued mailbox work before rendering.');
  check($pseQueueStatus === ($fileLaunch
    ? ['processed' => 0, 'failed' => 0, 'pending' => 7]
    : ['processed' => 2, 'failed' => 1, 'pending' => 4]), 'Pre-render pending count remains accurate when work is deferred.');
}

$fixtureAuthenticated = false;
$queueRuns = 0;
$_GET = [];
eval($renderGuard);
check($queueRuns === 0, 'Unauthenticated renders do not execute queued mailbox work.');
echo "PSE file-launch server fixtures passed.\n";
