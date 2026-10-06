<?php
declare(strict_types=1);

// Server regression checks run without IMAP/network or a real mailbox:
// php -n tests/folder-cleanup.php
$source = file_get_contents(dirname(__DIR__) . '/index.php');
preg_match_all('/^function ([a-zA-Z0-9_]+)\(/m', $source, $matches, PREG_OFFSET_CAPTURE);
$functions = [];
foreach ($matches[1] as $index => $match) {
  $start = $matches[0][$index][1];
  $end = $matches[0][$index + 1][1] ?? strlen($source);
  $functions[$match[0]] = substr($source, $start, $end - $start);
}
foreach (['pseNormalizeCalendarDate', 'pseSettingsTimezone', 'pseIsGmailAccount'] as $name) eval($functions[$name]);
foreach ($functions as $name => $body) {
  if (strpos($name, 'pseFolderCleanup') === 0) eval($body);
}

define('PSE_DATA_DIR', sys_get_temp_dir() . '/pse-cleanup-test-' . bin2hex(random_bytes(8)));
define('PSE_COOKIE', 'pse_auth');
define('SA_UIDVALIDITY', 1);
define('SE_UID', 1);
define('FT_UID', 1);
define('CP_UID', 1);
$_COOKIE[PSE_COOKIE] = 'browser-login-token';
$settings = ['storage_key' => 'test-storage-secret', 'account_id' => 'account-one', 'account_type' => 'gmail', 'google_oauth_email' => 'test@example.com', 'timezone' => 'Asia/Bangkok', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'test@example.com', 'imap_encryption' => 'ssl'];
$GLOBALS['folderFixture'] = [
  ['id' => 'INBOX', 'name' => 'Inbox', 'special' => 'inbox'],
  ['id' => 'TRASH', 'name' => 'Trash', 'special' => 'trash']
];
$GLOBALS['gmailFixture'] = [];
$GLOBALS['googleCalls'] = [];
$GLOBALS['bulkCalls'] = [];
$GLOBALS['imapFixture'] = ['uids' => [], 'validity' => 42, 'deleted' => [], 'moves' => [], 'deletes' => [], 'expunges' => 0, 'failExpunge' => false];
$assertions = 0;
function check(bool $condition, string $message): void {
  global $assertions;
  $assertions++;
  if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function rejects(callable $call, string $expected): void {
  try { $call(); } catch (RuntimeException $error) {
    check(strpos($error->getMessage(), $expected) !== false, 'expected rejection containing: ' . $expected . '; got: ' . $error->getMessage());
    return;
  }
  check(false, 'expected rejection: ' . $expected);
}
function pseEnsureStorage(): void { if (!is_dir(PSE_DATA_DIR)) mkdir(PSE_DATA_DIR, 0700, true); }
function pseEnsureDirectory(string $directory): void { if (!is_dir($directory)) mkdir($directory, 0700, true); }
function pseReadJson(string $file, array $fallback = []): array { return is_file($file) ? (json_decode(file_get_contents($file), true) ?: $fallback) : $fallback; }
function pseWriteJson(string $file, array $data): void { pseEnsureStorage(); file_put_contents($file . '.tmp', json_encode($data)); rename($file . '.tmp', $file); }
function pseFolders(array $settings): array { return $GLOBALS['folderFixture']; }
function pseMailCacheAfterDirectMessageOperation(array $settings, string $folder, array $uids, string $operation, string $destination = ''): void { $GLOBALS['cacheInvalidations'][] = [$folder, $uids, $operation, $destination]; }
function pseMailCacheClearLists(array $settings): void { $GLOBALS['cacheCleared'] = true; }
function pseGoogleApi(array $settings, string $method, string $path, array $query = [], ?array $body = null): array {
  $GLOBALS['googleCalls'][] = [$method, $path, $query];
  if ($path === 'labels') return ['labels' => array_map(fn($folder) => ['id' => $folder['id'], 'name' => $folder['name']], $GLOBALS['folderFixture'])];
  if ($path === 'messages') {
    if (!empty($GLOBALS['repeatPage'])) return ['messages' => [['id' => 'repeat-message']], 'nextPageToken' => 'repeated-token'];
    $offset = (int)substr((string)($query['pageToken'] ?? 'p0'), 1);
    if (isset($GLOBALS['largeCount'])) {
      $size = min(500, $GLOBALS['largeCount'] - $offset);
      $page = ['messages' => []];
      for ($index = 0; $index < $size; $index++) $page['messages'][] = ['id' => 'large-' . ($offset + $index)];
      if ($offset + $size < $GLOBALS['largeCount']) $page['nextPageToken'] = 'p' . ($offset + $size);
      return $page;
    }
    $found = [];
    foreach ($GLOBALS['gmailFixture'] as $id => $message) {
      if (!in_array($query['labelIds'], $message['labelIds'], true)) continue;
      if (empty($query['includeSpamTrash']) && (in_array('TRASH', $message['labelIds'], true) || in_array('SPAM', $message['labelIds'], true))) continue;
      if (isset($query['q']) && (int)($message['internalDate'] / 1000) >= (int)substr($query['q'], 7)) continue;
      $found[] = ['id' => $id];
    }
    $size = (int)($query['maxResults'] ?? 500);
    $result = ['messages' => array_slice($found, $offset, $size)];
    if ($offset + $size < count($found)) $result['nextPageToken'] = 'p' . ($offset + $size);
    return $result;
  }
  $id = substr($path, strlen('messages/'));
  if (!isset($GLOBALS['gmailFixture'][$id])) throw new RuntimeException('Google request failed (HTTP 404): not found');
  return $GLOBALS['gmailFixture'][$id];
}
function pseBulkMessages(array $settings, string $folder, array $uids, string $operation, string $confirmation): int {
  $GLOBALS['bulkCalls'][] = [$folder, $uids, $operation, $confirmation];
  foreach ($uids as $id) {
    if ($operation === 'delete_forever') unset($GLOBALS['gmailFixture'][$id]);
    else $GLOBALS['gmailFixture'][$id]['labelIds'] = ['TRASH'];
  }
  return count($uids);
}
function pseGmailBulkMessages(array $settings, array $uids, string $operation, string $confirmation): int {
  return pseBulkMessages($settings, $operation === 'delete_forever' ? 'TRASH' : 'INBOX', $uids, $operation, $confirmation);
}
function pseOpenImap(array $settings, string $folder, bool $readOnly = false) { return $folder; }
function pseImapBase(array $settings): string { return '{mock-server}'; }
function imap_status($imap, string $folder, int $options) { return (object)['uidvalidity' => $GLOBALS['imapFixture']['validity']]; }
function imap_errors() { return false; }
function imap_last_error() { return 'stub server failure'; }
function imap_search($imap, string $criteria, int $options) { return $criteria === 'DELETED' ? ($GLOBALS['imapFixture']['deleted'] ?: false) : (array_keys($GLOBALS['imapFixture']['uids']) ?: false); }
function imap_fetch_overview($imap, string $sequence, int $options) {
  $result = [];
  foreach (explode(',', $sequence) as $uid) if (isset($GLOBALS['imapFixture']['uids'][(int)$uid])) $result[] = (object)['uid' => (int)$uid, 'udate' => $GLOBALS['imapFixture']['uids'][(int)$uid]];
  return $result;
}
function imap_msgno($imap, int $uid): int { return isset($GLOBALS['imapFixture']['uids'][$uid]) ? $uid : 0; }
function imap_mail_move($imap, string $sequence, string $folder, int $options): bool {
  $GLOBALS['imapFixture']['moves'][] = [$sequence, $folder];
  $GLOBALS['imapFixture']['deleted'] = array_unique(array_merge($GLOBALS['imapFixture']['deleted'], array_map('intval', explode(',', $sequence))));
  return true;
}
function imap_delete($imap, string $sequence, int $options): bool {
  $GLOBALS['imapFixture']['deletes'][] = $sequence;
  $GLOBALS['imapFixture']['deleted'] = array_unique(array_merge($GLOBALS['imapFixture']['deleted'], array_map('intval', explode(',', $sequence))));
  return true;
}
function imap_expunge($imap): bool {
  if ($GLOBALS['imapFixture']['failExpunge']) return false;
  $GLOBALS['imapFixture']['expunges']++;
  foreach ($GLOBALS['imapFixture']['deleted'] as $uid) unset($GLOBALS['imapFixture']['uids'][$uid]);
  $GLOBALS['imapFixture']['deleted'] = [];
  return true;
}
function imap_close($imap): bool { return true; }

try {
  $now = new DateTimeImmutable('2024-03-30T18:00:00+00:00');
  $cutoff = pseFolderCleanupCutoff($settings, 'month', '', $now);
  check($cutoff['today'] === '2024-03-31' && $cutoff['until'] === '2024-02-29', 'account timezone and leap-month clamp');
  check($cutoff['presets']['two_months'] === '2024-01-31', 'two calendar months');
  check($cutoff['presets']['week'] === '2024-03-24', 'week preset');
  $dst = pseFolderCleanupCutoff($settings + [], 'custom', '2024-03-10', $now);
  check($dst['before'] === (new DateTimeImmutable('2024-03-11 00:00:00', new DateTimeZone('Asia/Bangkok')))->getTimestamp(), 'custom whole day included');
  $newYork = $settings; $newYork['timezone'] = 'America/New_York';
  $dst = pseFolderCleanupCutoff($newYork, 'custom', '2024-03-10', $now);
  check($dst['before'] - (new DateTimeImmutable('2024-03-10 00:00:00', new DateTimeZone('America/New_York')))->getTimestamp() === 23 * 3600, 'DST day uses next midnight, not 86400 seconds');
  rejects(fn() => pseFolderCleanupCutoff($settings, 'custom', '2024-02-30', $now), 'valid cutoff');
  rejects(fn() => pseFolderCleanupCutoff($settings, 'custom', '2024-04-01', $now), 'and today');
  rejects(fn() => pseFolderCleanupCutoff($settings, 'custom', '1969-12-31', $now), '1970-01-01');
  rejects(fn() => pseFolderCleanupCutoff($settings, 'unknown', '', $now), 'Invalid');
  rejects(fn() => pseFolderCleanupFolders($settings, 'MISSING'), 'no longer exists');
  check(pseFolderCleanupOptions($settings, 'TRASH')['permanent'], 'Trash cleanup is permanent');
  check(!pseFolderCleanupOptions($settings, 'INBOX')['permanent'], 'ordinary folder moves to Trash');
  check(count($GLOBALS['googleCalls']) === 3 && count(array_filter($GLOBALS['googleCalls'], fn($call) => $call[1] === 'labels')) === 3, 'Gmail folder validation uses one lightweight labels request per check');

  $GLOBALS['googleCalls'] = [];
  for ($index = 1; $index <= 1003; $index++) $GLOBALS['gmailFixture']['mail-' . $index] = ['labelIds' => ['INBOX'], 'internalDate' => 1700000000000];
  $snapshot = pseFolderCleanupSnapshot($settings, 'INBOX', 1800000000);
  check(count($snapshot['uids']) === 1003, 'Gmail scans all three pages');
  check(count($GLOBALS['googleCalls']) === 3 && $GLOBALS['googleCalls'][0][2]['includeSpamTrash'] === false, 'ordinary Gmail folder excludes Trash/spam and uses epoch cutoff');
  $GLOBALS['gmailFixture']['hidden-trash'] = ['labelIds' => ['INBOX', 'TRASH'], 'internalDate' => 1700000000000];
  $GLOBALS['gmailFixture']['hidden-spam'] = ['labelIds' => ['INBOX', 'SPAM'], 'internalDate' => 1700000000000];
  check(count(pseFolderCleanupSnapshot($settings, 'INBOX', 0)['uids']) === 1003, 'ordinary folder preview excludes already-trashed/spam messages');
  check(pseFolderCleanupSnapshot($settings, 'TRASH', 0)['uids'] === ['hidden-trash'], 'Trash preview includes Trash messages explicitly');
  $GLOBALS['repeatPage'] = true;
  rejects(fn() => pseFolderCleanupSnapshot($settings, 'INBOX', 0), 'repeated');
  unset($GLOBALS['repeatPage']);
  $GLOBALS['largeCount'] = 250001;
  rejects(fn() => pseFolderCleanupSnapshot($settings, 'INBOX', 0), '250,000');
  unset($GLOBALS['largeCount']);
  $GLOBALS['gmailFixture'] = array_slice($GLOBALS['gmailFixture'], 0, 203, true);
  $preview = pseFolderCleanupPreview($settings, 'INBOX', 'all');
  check($preview['count'] === 203 && $preview['remaining'] === 203, 'preview count is a full snapshot');
  $token = $preview['token'];
  rejects(fn() => pseFolderCleanupExecute($settings, $token, 'YES I AM SURE'), 'YES DELETE ALL exactly');
  check(!$GLOBALS['bulkCalls'], 'wrong confirmation cannot mutate');
  $otherAccount = $settings; $otherAccount['account_id'] = 'other-account';
  rejects(fn() => pseFolderCleanupExecute($otherAccount, $token, 'YES DELETE ALL'), 'account changed');
  $_COOKIE[PSE_COOKIE] = 'another-browser';
  rejects(fn() => pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL'), 'login or email account changed');
  $_COOKIE[PSE_COOKIE] = 'browser-login-token';
  $GLOBALS['gmailFixture']['new-arrival'] = ['labelIds' => ['INBOX'], 'internalDate' => 1700000000000];
  $result = pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL');
  check($result['processed'] === 25 && $result['remaining'] === 178, 'bounded first Gmail batch');
  $GLOBALS['gmailFixture']['mail-26']['labelIds'] = ['INBOX', 'TRASH'];
  unset($GLOBALS['gmailFixture']['mail-27']);
  $result = pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL');
  check($result['processed'] === 50 && $result['skipped'] === 2, 'moved/missing IDs skipped without new search');
  while (!$result['complete']) $result = pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL');
  check($result['complete'] && $result['affected'] === 201 && $result['remaining'] === 0, 'resumable final partial batch');
  check($GLOBALS['gmailFixture']['new-arrival']['labelIds'] === ['INBOX'], 'new arrival never selected');
  check(max(array_map(fn($call) => count($call[1]), $GLOBALS['bulkCalls'])) <= 25, 'each Gmail delete request bounded to25');
  $callCount = count($GLOBALS['bulkCalls']);
  pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL');
  check(count($GLOBALS['bulkCalls']) === $callCount, 'completed token replay idempotent');
  $trash = pseFolderCleanupPreview($settings, 'TRASH', 'all');
  pseFolderCleanupExecute($settings, $trash['token'], 'YES DELETE ALL');
  check(end($GLOBALS['bulkCalls'])[2] === 'delete_forever', 'Trash uses permanent API deletion');
  $file = pseFolderCleanupDirectory() . '/' . $token . '.json';
  $saved = pseReadJson($file); $saved['expires'] = time() - 1; pseWriteJson($file, $saved);
  rejects(fn() => pseFolderCleanupExecute($settings, $token, 'YES DELETE ALL'), 'expired');
  $GLOBALS['gmailFixture'] = [
    'dated-unknown' => ['labelIds' => ['INBOX'], 'internalDate' => 1700000000000],
    'dated-newer' => ['labelIds' => ['INBOX'], 'internalDate' => 1700000000000]
  ];
  $datedPreview = pseFolderCleanupPreview($settings, 'INBOX', 'custom', '2024-04-01');
  $GLOBALS['gmailFixture']['dated-unknown']['internalDate'] = 0;
  $GLOBALS['gmailFixture']['dated-newer']['internalDate'] = (new DateTimeImmutable('2024-04-02 00:00:00', new DateTimeZone('Asia/Bangkok')))->getTimestamp() * 1000;
  $callCount = count($GLOBALS['bulkCalls']);
  $datedResult = pseFolderCleanupExecute($settings, $datedPreview['token'], 'YES DELETE ALL');
  check($datedResult['skipped'] === 2 && count($GLOBALS['bulkCalls']) === $callCount, 'dated cleanup fails safe for unknown or changed newer timestamps');

  $imapSettings = $settings; $imapSettings['account_type'] = 'imap';
  $endOfDay = (new DateTimeImmutable('2024-04-02 00:00:00', new DateTimeZone('Asia/Bangkok')))->getTimestamp();
  $GLOBALS['imapFixture']['uids'] = [1 => $endOfDay - 1, 2 => $endOfDay, 3 => 0, 4 => $endOfDay - 86400];
  $dated = pseFolderCleanupSnapshot($imapSettings, 'INBOX', $endOfDay);
  check($dated['uids'] === ['1', '4'] && $dated['uidValidity'] === 42, 'IMAP inclusive day boundary and unknown date excluded');
  $preview = pseFolderCleanupPreview($imapSettings, 'INBOX', 'all');
  $GLOBALS['imapFixture']['validity'] = 43;
  rejects(fn() => pseFolderCleanupExecute($imapSettings, $preview['token'], 'YES DELETE ALL'), 'identifiers changed');
  check(!$GLOBALS['imapFixture']['moves'] && !$GLOBALS['imapFixture']['deletes'], 'UIDVALIDITY mismatch has no side effects');
  $GLOBALS['imapFixture']['validity'] = 42;
  $GLOBALS['imapFixture']['uids'][999] = $endOfDay;
  $GLOBALS['imapFixture']['deleted'] = [999];
  rejects(fn() => pseFolderCleanupExecute($imapSettings, $preview['token'], 'YES DELETE ALL'), 'other messages pending deletion');
  check(!$GLOBALS['imapFixture']['moves'] && $GLOBALS['imapFixture']['expunges'] === 0, 'unrelated deleted UID refused before move or expunge');
  $GLOBALS['imapFixture']['deleted'] = [];
  $GLOBALS['imapFixture']['deleted'] = [1];
  $result = pseFolderCleanupExecute($imapSettings, $preview['token'], 'YES DELETE ALL');
  check($result['complete'] && count($GLOBALS['imapFixture']['uids']) === 1 && isset($GLOBALS['imapFixture']['uids'][999]), 'only previewed IMAP IDs moved; new arrival preserved');
  check($GLOBALS['imapFixture']['moves'][0][1] === 'TRASH', 'IMAP move targets Trash');
  check(in_array('1', explode(',', $GLOBALS['imapFixture']['moves'][0][0]), true), 'pre-marked selected UID still copied to Trash before expunge');
  $preview = pseFolderCleanupPreview($imapSettings, 'TRASH', 'all');
  pseFolderCleanupExecute($imapSettings, $preview['token'], 'YES DELETE ALL');
  check(end($GLOBALS['imapFixture']['deletes']) === '999', 'IMAP Trash deletes permanently');
  $GLOBALS['imapFixture'] = ['uids' => [1 => $endOfDay - 1, 2 => $endOfDay - 10], 'validity' => 42, 'deleted' => [], 'moves' => [], 'deletes' => [], 'expunges' => 0, 'failExpunge' => true];
  $retryPreview = pseFolderCleanupPreview($imapSettings, 'INBOX', 'all');
  $GLOBALS['imapFixture']['uids'][999] = $endOfDay;
  rejects(fn() => pseFolderCleanupExecute($imapSettings, $retryPreview['token'], 'YES DELETE ALL'), 'could not finish deleting');
  $retrySaved = pseReadJson(pseFolderCleanupDirectory() . '/' . $retryPreview['token'] . '.json');
  check($retrySaved['processed'] === 0 && isset($GLOBALS['imapFixture']['uids'][1]), 'failed expunge keeps source and resumable preview');
  $GLOBALS['imapFixture']['failExpunge'] = false;
  $retryResult = pseFolderCleanupExecute($imapSettings, $retryPreview['token'], 'YES DELETE ALL');
  check($retryResult['complete'] && $retryResult['affected'] === 2 && array_keys($GLOBALS['imapFixture']['uids']) === [999], 'same token retry completes only original snapshot IDs');
  check(count($GLOBALS['imapFixture']['moves']) === 2, 'recoverable retry copies again to preserve Trash recovery');
  echo 'PASS: ' . $assertions . " folder cleanup regression checks\n";
} finally {
  foreach (glob(PSE_DATA_DIR . '/folder-cleanup/*') ?: [] as $file) unlink($file);
  @rmdir(PSE_DATA_DIR . '/folder-cleanup');
  @rmdir(PSE_DATA_DIR);
}
