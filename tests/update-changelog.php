<?php
declare(strict_types=1);

// Fixture-only checks: no live account, update installation, or network access.
// php -n -d extension=tokenizer tests/update-changelog.php
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$temporaryDirectory = sys_get_temp_dir() . '/pse-changelog-' . bin2hex(random_bytes(8));
define('PSE_DATA_DIR', $temporaryDirectory);
define('PSE_VERSION', '2.18.1');
define('PSE_COOKIE', 'pse_auth');
define('PSE_UPDATE_REPOSITORY', 'ziobit/PSE-Email-Client');
define('PSE_UPDATE_BRANCH', 'main');
define('PSE_UPDATE_CACHE_SECONDS', 900);

function check(bool $condition, string $description): void
{
  if (!$condition) throw new RuntimeException($description);
}

function pseEnsureStorage(): void
{
  if (!is_dir(PSE_DATA_DIR)) mkdir(PSE_DATA_DIR, 0700, true);
}

$fixtureNotes = "# Release notes\n\n## 2.19.0 (2026-11-01)\n\n- Future feature.\n\n## 2.18.3\n\n- Third patch.\n\n## [v2.18.2] (2026-10-07)\n\n- Second patch.\n\n## 2.18.1 (2026-10-06)\n\n- Current patch.\n\n## 2.18.0\n\n- Previous patch.\n";
function pseBundledChangelogText(): string
{
  global $fixtureNotes;
  return $fixtureNotes;
}

$httpResponse = ['status' => 200, 'body' => ''];
$httpRoutes = null;
$requestedUrls = [];
function pseHttpRequest(string $method, string $url, array $headers = []): array
{
  global $httpResponse, $httpRoutes, $requestedUrls;
  $requestedUrls[] = $url;
  if (is_array($httpRoutes)) {
    if (!isset($httpRoutes[$url])) throw new RuntimeException('Unexpected fixture request: ' . $url);
    return $httpRoutes[$url];
  }
  if ($httpResponse instanceof Throwable) throw $httpResponse;
  return $httpResponse;
}

preg_match('/\/\* PSE_EMBEDDED_CHANGELOG_START \*\/(.*?)^function pseUpdateCacheFile/sm', $source, $changelogBlock);
preg_match_all('/^function (pse\w+)\(/m', $changelogBlock[1] ?? '', $matches);
$functions = array_merge([
  'pseReadJson', 'pseWriteJson', 'pseUpdateCacheFile', 'pseGithubApiGet',
  'pseRemoteVersionFromPhp', 'pseValidateUpdateSourceUrl', 'pseCheckForUpdate'
], array_diff($matches[1], ['pseBundledChangelogText']));
foreach ($functions as $name) {
  check((bool)preg_match('/^function ' . preg_quote($name, '/') . '\b/m', $source, $match, PREG_OFFSET_CAPTURE), 'Missing function: ' . $name);
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
}

register_shutdown_function(function (): void {
  if (is_dir(PSE_DATA_DIR)) {
    foreach (glob(PSE_DATA_DIR . '/*') ?: [] as $file) unlink($file);
    rmdir(PSE_DATA_DIR);
  }
});

$selected = pseChangelogForVersions($fixtureNotes, '2.18.0', '2.18.3');
check(strpos($selected, 'Current patch.') !== false && strpos($selected, 'Second patch.') !== false && strpos($selected, 'Third patch.') !== false, 'Skipped upgrades include every intermediate release.');
check(strpos($selected, 'Previous patch.') === false && strpos($selected, 'Future feature.') === false, 'Already installed and future releases are excluded.');
check(pseChangelogForVersions($fixtureNotes, '', '2.18.1') === "## 2.18.1 (2026-10-06)\n\n- Current patch.", 'First manual installation shows the installed release only.');
check(pseChangelogForVersions(str_replace("\n", "\r\n", $fixtureNotes), '2.18.1', '2.18.2') === "## [v2.18.2] (2026-10-07)\n\n- Second patch.", 'CRLF and bracketed version headings are supported.');
check(strpos(pseChangelogForVersions(str_replace('Current patch.', 'Current patch. <!-- pse-pr:ziobit/PSE-Email-Client#1 -->', $fixtureNotes), '', '2.18.1'), 'pse-pr:') === false, 'Internal merge deduplication markers are omitted from user-facing notes.');
check(pseChangelogForVersions("\xFF", '', '2.18.1') === '', 'Invalid UTF-8 is rejected.');
check(pseChangelogForVersions(str_repeat('x', 262145), '', '2.18.1') === '', 'Oversized release notes are rejected.');

$encodedPhp = '<?php /* PSE_EMBEDDED_CHANGELOG_START */ function pseBundledChangelogText(): string { return base64_decode(\'' . base64_encode($fixtureNotes) . '\', true) ?: \'\'; } /* PSE_EMBEDDED_CHANGELOG_END */';
check(pseChangelogFromPhp($encodedPhp) === $fixtureNotes, 'Embedded notes decode without executing the downloaded PHP.');
check(pseChangelogFromPhp('<?php throw new RuntimeException("must not execute");') === '', 'Missing bundled notes fail safely without executing source.');
check(pseChangelogFromPhp(str_replace(base64_encode($fixtureNotes), '////', $encodedPhp)) === '', 'Invalid UTF-8 in an embedded payload is rejected.');

$commit = str_repeat('a', 40);
$httpResponse = ['status' => 200, 'body' => $fixtureNotes];
$update = pseUpdateChangelog($commit, $encodedPhp, '2.18.3');
check(end($requestedUrls) === 'https://raw.githubusercontent.com/ziobit/PSE-Email-Client/' . $commit . '/CHANGELOG.md', 'Changelog retrieval uses the exact immutable PHP source commit.');
check($update['changelogAvailable'] && strpos($update['changelog'], 'Second patch.') !== false && strpos($update['changelog'], 'Current patch.') === false, 'Update notes select versions newer than the running application.');
$httpResponse = new RuntimeException('fixture network offline');
$offline = pseUpdateChangelog($commit, $encodedPhp, '2.18.2');
check($offline['changelogAvailable'] && strpos($offline['changelog'], 'Second patch.') !== false, 'Offline retrieval falls back to the embedded copy.');
$missing = pseUpdateChangelog($commit, '', '2.18.2');
check(!$missing['changelogAvailable'] && strpos($missing['changelog'], 'unavailable') !== false, 'Missing notes produce an explicit readable fallback.');

$officialUrl = 'https://raw.githubusercontent.com/ziobit/PSE-Email-Client/' . $commit . '/index.php';
check(pseValidateUpdateSourceUrl($officialUrl), 'Official HTTPS update URL is trusted.');
foreach ([
  str_replace('https:', 'http:', $officialUrl),
  str_replace('https://', 'https://username@', $officialUrl),
  str_replace('https://', 'https://username:password@', $officialUrl),
  str_replace('raw.githubusercontent.com', 'raw.githubusercontent.com.attacker.example', $officialUrl),
  str_replace('raw.githubusercontent.com', 'raw.githubusercontent.com:8080', $officialUrl),
  str_replace('/ziobit/PSE-Email-Client/', '/attacker/PSE-Email-Client/', $officialUrl),
  str_replace('/ziobit/PSE-Email-Client/', '/ziobit/PSE-Email-Client-evil/', $officialUrl)
] as $untrustedUrl) {
  check(!pseValidateUpdateSourceUrl($untrustedUrl), 'Untrusted source URL rejected: ' . $untrustedUrl);
}

$branchUrl = 'https://raw.githubusercontent.com/ziobit/PSE-Email-Client/main/index.php';
$phpRelease = str_replace('<?php ', '<?php const PSE_VERSION = \'2.18.3\'; ', $encodedPhp);
$httpRoutes = [
  'https://api.github.com/repos/ziobit/PSE-Email-Client/commits/main' => ['status' => 200, 'body' => json_encode(['sha' => $commit])],
  'https://api.github.com/repos/ziobit/PSE-Email-Client/contents?ref=' . $commit => ['status' => 200, 'body' => json_encode([
    ['type' => 'file', 'name' => 'index.php', 'download_url' => $branchUrl]
  ])],
  $officialUrl => ['status' => 200, 'body' => $phpRelease],
  'https://raw.githubusercontent.com/ziobit/PSE-Email-Client/' . $commit . '/CHANGELOG.md' => ['status' => 200, 'body' => $fixtureNotes]
];
pseWriteJson(pseUpdateCacheFile(), [
  'checkedAt' => time(), 'latestVersion' => '9.99.0',
  'sourceUrl' => $branchUrl, 'updateAvailable' => true
]);
$requestedUrls = [];
$pinned = pseCheckForUpdate(false);
check($pinned['status'] === 'update' && $pinned['latestVersion'] === '2.18.3', 'A legacy fresh cache is discarded and rebuilt with changelog metadata.');
check($pinned['sourceCommit'] === $commit && $pinned['sourceUrl'] === $officialUrl, 'Updater replaces mutable metadata URLs with the resolved source commit.');
check(!in_array($branchUrl, $requestedUrls, true), 'Updater never reads mutable branch PHP once the revision is resolved.');
check($pinned['changelogVersion'] === '2.18.3' && strpos($pinned['changelog'], 'Third patch.') !== false, 'Version and release notes correspond to the same pinned revision.');
$requestedUrls = [];
$cached = pseCheckForUpdate(false);
check($requestedUrls === [] && $cached['sourceCommit'] === $commit && $cached['changelog'] === $pinned['changelog'], 'A valid changelog cache reuses the immutable revision and its notes.');
$previousInstallCache = $pinned;
$previousInstallCache['currentVersion'] = '2.18.0';
$previousInstallCache['changelog'] = 'Current patch. Second patch. Third patch.';
pseWriteJson(pseUpdateCacheFile(), $previousInstallCache);
$requestedUrls = [];
$afterManualInstall = pseCheckForUpdate(false);
check($requestedUrls !== [] && strpos($afterManualInstall['changelog'], 'Current patch.') === false, 'Manual deployment invalidates notes selected for an older running version.');
$httpRoutes = null;

$settings = ['storage_key' => 'fixture-storage-secret'];
$_COOKIE[PSE_COOKIE] = 'browser-one';
$manual = pseInstalledChangelog($settings);
check(is_array($manual) && $manual['toVersion'] === PSE_VERSION && strpos($manual['text'], 'Current patch.') !== false, 'Manual deployment initializes an installed release notice.');
check(pseInstalledChangelog($settings)['id'] === $manual['id'], 'Reloads preserve the notice identifier until acknowledgement.');
$id = $manual['id'];
pseAcknowledgeInstalledChangelog($settings, $id);
check(pseInstalledChangelog($settings) === null, 'Acknowledgement suppresses the notice for this browser.');
$_COOKIE[PSE_COOKIE] = 'browser-two';
check(pseInstalledChangelog($settings)['id'] === $id, 'Another browser still sees the same update.');
pseAcknowledgeInstalledChangelog($settings, $id);
check(pseInstalledChangelog($settings) === null, 'Second browser acknowledgement is saved independently.');
$_COOKIE[PSE_COOKIE] = 'browser-one';
check(pseInstalledChangelog($settings) === null, 'Acknowledging from another browser does not erase existing acknowledgements.');

$fresh = pseRecordInstalledChangelog('2.18.0', PSE_VERSION, 'Fresh release notes', 'https://github.com/ziobit/PSE-Email-Client/blob/' . $commit . '/CHANGELOG.md');
check(pseInstalledChangelog($settings)['id'] === $fresh['id'], 'Recording a new update resets acknowledgement state.');
$staleWasRejected = false;
try {
  pseAcknowledgeInstalledChangelog($settings, $id);
} catch (RuntimeException $error) {
  $staleWasRejected = true;
}
check($staleWasRejected && pseInstalledChangelog($settings)['id'] === $fresh['id'], 'A stale popup cannot acknowledge a newer notice.');

pseWriteJson(pseInstalledChangelogFile(), [
  'id' => 'earlier-release', 'fromVersion' => '2.17.30', 'toVersion' => '2.18.0',
  'text' => 'Previous release notes', 'acknowledged' => ['older-browser' => true]
]);
$manualUpgrade = pseInstalledChangelog($settings);
check($manualUpgrade['fromVersion'] === '2.18.0' && $manualUpgrade['toVersion'] === PSE_VERSION, 'A manual version upgrade preserves the previously installed version.');
check($manualUpgrade['id'] !== 'earlier-release' && strpos($manualUpgrade['text'], 'Current patch.') !== false, 'A manual upgrade creates a fresh notice containing the new release notes.');
check(strpos($manualUpgrade['text'], 'Previous patch.') === false, 'Manual upgrade notes omit the old installed release.');

echo "Update changelog fixture checks passed.\n";
