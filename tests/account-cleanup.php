<?php
declare(strict_types=1);

// Offline provider fixtures: never connect to an account or delete real mail.
$source = file_get_contents(dirname(__DIR__) . '/index.php');
preg_match_all('/^function ([a-zA-Z0-9_]+)\(/m', $source, $matches, PREG_OFFSET_CAPTURE);
$functions = [];
foreach ($matches[1] as $index => $match) {
  $functions[$match[0]] = substr($source, $matches[0][$index][1], ($matches[0][$index + 1][1] ?? strlen($source)) - $matches[0][$index][1]);
}
foreach (['pseNormalizeCalendarDate', 'pseSettingsTimezone', 'pseIsGmailAccount', 'pseGmailHeaders'] as $name) eval($functions[$name]);
foreach ($functions as $name => $body) {
  if (strpos($name, 'pseAccountCleanup') === 0 || strpos($name, 'pseFolderCleanup') === 0) eval($body);
}
define('PSE_DATA_DIR', sys_get_temp_dir() . '/pse-account-cleanup-' . bin2hex(random_bytes(8)));
define('PSE_COOKIE', 'pse_auth');
define('SA_UIDVALIDITY', 1);
define('SE_UID', 1);
define('FT_UID', 1);
define('CP_UID', 1);
$_COOKIE[PSE_COOKIE] = 'cleanup-browser';
$settings = ['storage_key' => 'cleanup-test-secret', 'account_id' => 'account-one', 'account_type' => 'gmail', 'google_oauth_email' => 'one@example.com', 'timezone' => 'Asia/Bangkok'];
$assertions = 0;
function check(bool $condition, string $message): void {
  global $assertions;
  $assertions++;
  if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function rejects(callable $call, string $message): void {
  try { $call(); } catch (RuntimeException $error) { check(strpos($error->getMessage(), $message) !== false, $error->getMessage()); return; }
  check(false, 'Expected rejection: ' . $message);
}
function pseEnsureStorage(): void { pseEnsureDirectory(PSE_DATA_DIR); }
function pseEnsureDirectory(string $path): void { if (!is_dir($path)) mkdir($path, 0700, true); }
function pseWriteJson(string $file, array $value): void { file_put_contents($file . '.tmp', json_encode($value)); rename($file . '.tmp', $file); }
function pseReadJson(string $file, array $fallback = []): array { return is_file($file) ? (json_decode(file_get_contents($file), true) ?: $fallback) : $fallback; }
function pseMime(string $text): string { return $text; }
function pseFormatDate(int $timestamp, array $settings): string { return date('d-m-Y', $timestamp); }
function pseFolders(array $settings): array { return $GLOBALS['folders']; }
function pseMailCacheAfterDirectMessageOperation(array $settings, string $folder, array $uids, string $operation, string $destination): void { $GLOBALS['invalidations'][] = [$settings['account_id'], $folder, $uids, $operation, $destination]; }
function pseMailCacheClearLists(array $settings): void {}
function pseGoogleApi(array $settings, string $method, string $path, array $query = [], ?array $body = null): array {
  $GLOBALS['requests'][] = [$path, $query];
  if ($path === 'messages') {
    if (!empty($GLOBALS['repeat'])) return ['messages' => [], 'nextPageToken' => 'repeat'];
    check(!isset($query['labelIds']) && $query['includeSpamTrash'] === true, 'Account search covers all labels, spam and trash');
    $offset = (int)($query['pageToken'] ?? 0);
    $ids = array_keys($GLOBALS['gmail']);
    $page = ['messages' => array_map(function ($id) { return ['id' => $id]; }, array_slice($ids, $offset, 25))];
    if ($offset + 25 < count($ids)) $page['nextPageToken'] = (string)($offset + 25);
    return $page;
  }
  $id = substr($path, strlen('messages/'));
  if (!isset($GLOBALS['gmail'][$id])) throw new RuntimeException('(HTTP 404) Message removed');
  return $GLOBALS['gmail'][$id];
}
function pseGmailBulkMessages(array $settings, array $uids, string $operation, string $confirmation): int {
  $GLOBALS['mutations'][] = [$uids, $operation];
  foreach ($uids as $id) {
    if ($operation === 'delete_forever') unset($GLOBALS['gmail'][$id]);
    else $GLOBALS['gmail'][$id]['labelIds'] = ['TRASH'];
  }
  if (!empty($GLOBALS['failAfterMutation'])) { $GLOBALS['failAfterMutation'] = false; throw new RuntimeException('Response lost'); }
  return count($uids);
}
function pseOpenImap(array $settings, string $folder, bool $readOnly = false) { return $folder; }
function pseImapBase(array $settings): string { return ''; }
function imap_status($imap, string $folder, int $flags) { return (object)['uidvalidity' => $GLOBALS['imap'][$imap]['validity']]; }
function imap_errors() { return false; }
function imap_last_error() { return 'fixture error'; }
function imap_search($imap, string $query, int $flags) {
  if ($query === 'DELETED') return $GLOBALS['imap'][$imap]['deleted'] ?: false;
  $minimum = (int)substr($query, 7);
  return array_keys(array_filter($GLOBALS['imap'][$imap]['messages'], function ($row) use ($minimum) { return $row['size'] > $minimum; })) ?: false;
}
function imap_fetch_overview($imap, string $sequence, int $flags) {
  $rows = [];
  foreach (explode(',', $sequence) as $uid) if (isset($GLOBALS['imap'][$imap]['messages'][$uid])) $rows[] = (object)(['uid' => (int)$uid] + $GLOBALS['imap'][$imap]['messages'][$uid]);
  return $rows;
}
function imap_msgno($imap, int $uid): int { return isset($GLOBALS['imap'][$imap]['messages'][$uid]) ? $uid : 0; }
function imap_mail_move($imap, string $sequence, string $trash, int $flags): bool {
  $GLOBALS['moves'][] = [$imap, $sequence, $trash];
  $GLOBALS['imap'][$imap]['deleted'] = array_map('intval', explode(',', $sequence));
  return true;
}
function imap_delete($imap, string $sequence, int $flags): bool {
  $GLOBALS['deletes'][] = [$imap, $sequence];
  $GLOBALS['imap'][$imap]['deleted'] = array_map('intval', explode(',', $sequence));
  return true;
}
function imap_expunge($imap): bool {
  foreach ($GLOBALS['imap'][$imap]['deleted'] as $uid) unset($GLOBALS['imap'][$imap]['messages'][$uid]);
  $GLOBALS['imap'][$imap]['deleted'] = [];
  return true;
}
function imap_close($imap): bool { return true; }
function scanAll(array $settings, array $data = []): array {
  $job = pseAccountCleanupStart($settings, $data);
  do { $job = pseAccountCleanupScan($settings, $job['token']); } while (!$job['scanComplete']);
  return $job;
}
function execute(array $settings, array $job, array $selection = []): array {
  return pseAccountCleanupExecute($settings, $job['token'], ['confirmation' => 'YES DELETE SELECTED'] + $selection);
}

try {
  $now = new DateTimeImmutable('2024-03-30T18:00:00Z');
  foreach (['month' => '2024-02-29', 'three_months' => '2023-12-31', 'six_months' => '2023-09-30', 'year' => '2023-03-31', 'two_years' => '2022-03-31', 'three_years' => '2021-03-31', 'five_years' => '2019-03-31'] as $mode => $date) {
    $filters = pseAccountCleanupFilters($settings, ['mode' => $mode], $now);
    check($filters['until'] === $date, $mode . ': calendar months clamp in account timezone');
    check(!pseAccountCleanupMatches($filters, 6000000, $filters['before']) && pseAccountCleanupMatches($filters, 6000000, $filters['before'] - 1), 'Older than excludes the cutoff day');
  }
  $filters = pseAccountCleanupFilters($settings, ['minMB' => 1.5]);
  check($filters['mode'] === 'any' && $filters['before'] === 0 && $filters['after'] === 0, 'Any date default');
  check(!pseAccountCleanupMatches($filters, 1572864, 0) && pseAccountCleanupMatches($filters, 1572865, 0), 'Strict larger-than with fractional MB');
  $filters = pseAccountCleanupFilters($settings, ['mode' => 'custom', 'from' => '2024-02-01', 'until' => '2024-02-29'], $now);
  check(pseAccountCleanupMatches($filters, 6000000, $filters['after']) && pseAccountCleanupMatches($filters, 6000000, $filters['before'] - 1), 'Custom range includes both boundary dates');
  check(!pseAccountCleanupMatches($filters, 6000000, $filters['after'] - 1) && !pseAccountCleanupMatches($filters, 6000000, $filters['before']), 'Custom range excludes outside dates');
  check(pseAccountCleanupFilters($settings, ['mode' => 'custom', 'until' => '2024-02-01'], $now)['after'] === 0, 'Open-ended custom range');
  foreach (['0', '-1', 'abc', INF, 102401] as $size) rejects(function () use ($settings, $size) { pseAccountCleanupFilters($settings, ['minMB' => $size]); }, 'Enter a size');
  foreach ([['mode' => 'custom'], ['mode' => 'bad'], ['mode' => 'custom', 'from' => '2024-02-30'], ['mode' => 'custom', 'from' => '2024-03-02', 'until' => '2024-03-01']] as $data) rejects(function () use ($settings, $data, $now) { pseAccountCleanupFilters($settings, $data, $now); }, isset($data['from']) && $data['from'] === '2024-02-30' ? 'Choose dates' : 'range');

  $GLOBALS['gmail'] = [];
  for ($i = 0; $i < 55; $i++) $GLOBALS['gmail']['mail' . $i] = ['labelIds' => ['INBOX', 'custom-label'], 'sizeEstimate' => 6000000, 'internalDate' => 1700000000000, 'payload' => ['headers' => [['name' => 'Subject', 'value' => 'Message ' . $i], ['name' => 'From', 'value' => 'sender@example.com']]]];
  $GLOBALS['gmail']['mail54']['labelIds'] = ['TRASH'];
  $GLOBALS['gmail']['small'] = ['labelIds' => ['INBOX'], 'sizeEstimate' => 5242880, 'internalDate' => 1700000000000];
  $job = pseAccountCleanupStart($settings, []);
  rejects(function () use ($settings, $job) { execute($settings, $job, ['all' => true]); }, 'Finish the search');
  $job = pseAccountCleanupScan($settings, $job['token']);
  check(!$job['scanComplete'] && $job['count'] === 25, 'Search is progressive and cancellable between batches');
  $job = pseAccountCleanupScan($settings, $job['token']);
  $job = pseAccountCleanupScan($settings, $job['token']);
  check($job['scanComplete'] && $job['count'] === 55 && $job['pages'] === 2 && $job['permanentCount'] === 1, 'All pages included once; strict size boundary; trash action flagged');
  $second = pseAccountCleanupTransaction($settings, $job['token'], function (&$snapshot) { return ['page' => 2]; });
  check(count($second['rows']) === 5 && $second['rows'][4]['index'] === 54 && $second['rows'][4]['permanent'], 'Preview pagination preserves stable selection indices');
  rejects(function () use ($settings, $job) { pseAccountCleanupExecute($settings, $job['token'], ['confirmation' => 'YES I AM SURE', 'all' => true]); }, 'YES DELETE SELECTED');
  rejects(function () use ($settings, $job) { execute($settings, $job, ['indices' => [999]]); }, 'Invalid cleanup selection');
  rejects(function () use ($settings, $job) { execute($settings, $job, ['indices' => ['0']]); }, 'Invalid cleanup selection');
  $other = $settings; $other['account_id'] = 'account-two';
  rejects(function () use ($other, $job) { pseAccountCleanupScan($other, $job['token']); }, 'account changed');
  $_COOKIE[PSE_COOKIE] = 'other-browser';
  rejects(function () use ($settings, $job) { execute($settings, $job, ['all' => true]); }, 'account changed');
  $_COOKIE[PSE_COOKIE] = 'cleanup-browser';
  $GLOBALS['gmail']['mail1']['labelIds'] = ['TRASH'];
  $GLOBALS['gmail']['mail2']['sizeEstimate'] = 100;
  $GLOBALS['mutations'] = [];
  $done = execute($settings, $job, ['indices' => [0, 1, 2, 54]]);
  check(!$done['complete'] && $done['processed'] === 3 && $done['affected'] === 1 && $done['skipped'] === 2, 'Changed size and newly trashed messages are skipped safely');
  $done = execute($settings, $job, ['all' => true]);
  check($done['complete'] && $done['affected'] === 2 && $done['selectedCount'] === 4, 'Retry cannot replace the frozen selection with all messages');
  check($GLOBALS['mutations'] === [[['mail0'], 'delete'], [['mail54'], 'delete_forever']], 'Live mail moves to trash; only previewed trash mail is permanently deleted');
  check(isset($GLOBALS['gmail']['mail3']), 'Unselected message remains untouched');
  execute($settings, $job, ['all' => true]);
  check(count($GLOBALS['mutations']) === 2, 'Completed retries are idempotent');
  $job = scanAll($settings);
  $GLOBALS['failAfterMutation'] = true;
  rejects(function () use ($settings, $job) { execute($settings, $job, ['indices' => [3]]); }, 'Response lost');
  $done = execute($settings, $job, ['all' => true]);
  check($done['complete'] && $done['selectedCount'] === 1, 'Lost response cannot expand a persisted selection');
  $GLOBALS['repeat'] = true;
  $repeat = pseAccountCleanupStart($settings, []);
  pseAccountCleanupScan($settings, $repeat['token']);
  rejects(function () use ($settings, $repeat) { pseAccountCleanupScan($settings, $repeat['token']); }, 'repeated');
  $GLOBALS['repeat'] = false;
  rejects(function () use ($settings) { pseAccountCleanupScan($settings, '../bad'); }, 'Invalid');

  $imapSettings = $settings; $imapSettings['account_type'] = 'imap';
  $GLOBALS['folders'] = [['id' => 'INBOX', 'name' => 'Inbox', 'special' => 'inbox'], ['id' => 'Sent', 'name' => 'Sent', 'special' => 'sent'], ['id' => 'Trash', 'name' => 'Trash', 'special' => 'trash']];
  $row = ['size' => 6000000, 'udate' => 1700000000, 'subject' => 'Large message', 'from' => 'sender@example.com'];
  $GLOBALS['imap'] = [];
  foreach ($GLOBALS['folders'] as $folder) $GLOBALS['imap'][$folder['id']] = ['validity' => 42, 'messages' => [1 => $row], 'deleted' => []];
  $job = scanAll($imapSettings);
  check($job['count'] === 3 && $job['permanentCount'] === 1, 'IMAP UIDs are isolated per folder');
  $GLOBALS['imap']['INBOX']['validity'] = 43;
  rejects(function () use ($imapSettings, $job) { execute($imapSettings, $job, ['all' => true]); }, 'identifiers changed');
  $GLOBALS['imap']['INBOX']['validity'] = 42;
  $GLOBALS['imap']['INBOX']['deleted'] = [999];
  rejects(function () use ($imapSettings, $job) { execute($imapSettings, $job, ['all' => true]); }, 'other messages pending deletion');
  $GLOBALS['imap']['INBOX']['deleted'] = [];
  $GLOBALS['imap']['Sent']['messages'][1]['size'] = 100;
  do { $done = execute($imapSettings, $job, ['all' => true]); } while (!$done['complete']);
  check($done['affected'] === 2 && $done['skipped'] === 1, 'IMAP batches revalidate sizes before mutation');
  check($GLOBALS['moves'] === [['INBOX', '1', 'Trash']] && $GLOBALS['deletes'] === [['Trash', '1']], 'IMAP folder destinations match the preview');
  $GLOBALS['folders'] = [['id' => 'Sent', 'name' => 'Sent', 'special' => 'sent']];
  $GLOBALS['imap']['Sent']['messages'][1]['size'] = 6000000;
  $job = scanAll($imapSettings);
  check($job['permanentCount'] === 1, 'Missing IMAP trash is explicitly marked permanent');
  $file = pseFolderCleanupDirectory() . '/' . $job['token'] . '.json';
  $snapshot = pseReadJson($file); $snapshot['expires'] = time() - 1; pseWriteJson($file, $snapshot);
  rejects(function () use ($imapSettings, $job) { execute($imapSettings, $job, ['all' => true]); }, 'expired');
  echo 'PASS: ' . $assertions . " account cleanup backend checks.\n";
} finally {
  foreach (glob(PSE_DATA_DIR . '/folder-cleanup/*') ?: [] as $file) unlink($file);
  @rmdir(PSE_DATA_DIR . '/folder-cleanup'); @rmdir(PSE_DATA_DIR);
}
