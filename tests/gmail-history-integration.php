<?php
declare(strict_types=1);

// Offline integration fixtures: real cache handlers, controlled Gmail checkpoints.
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$directory = sys_get_temp_dir() . '/pse-gmail-integration-' . bin2hex(random_bytes(8));
define('PSE_MAIL_CACHE_DIR', $directory);
$checks = 0;
function check(bool $condition, string $description): void
{
  global $checks;
  if (!$condition) throw new RuntimeException($description);
  $checks++;
}
function pseEnsureStorage(): void {}
function pseSafeAccountId(string $id): string { return $id; }
function pseIsGmailAccount(array $settings): bool { return ($settings['account_type'] ?? '') === 'gmail'; }
function pseFolderIsSent(array $settings, string $folder): bool { return $folder === 'SENT'; }
function pseFolderLabel(string $name): string { return $name; }
class PseGoogleReconnectRequiredException extends RuntimeException {}

$historyCalls = 0;
$listCalls = 0;
$imapFolderCalls = 0;
$requests = [];
$acknowledgments = [];
$historyError = null;
$apiError = '';
$apiErrorCode = 0;
$raceOnList = false;
$sync = ['historyId' => '100', 'changedFolders' => [], 'countDirtyFolders' => [], 'reset' => false, 'syncedAt' => time(), 'gmailFolderRevisions' => []];
$counts = ['INBOX' => [10, 2], 'SENT' => [3, 0], 'TRASH' => [1, 0]];
function pseGmailHistoryWithLock(array $settings, callable $callback) { return $callback(); }
function pseGmailHistoryDirectory(array $settings): string
{
  $path = pseMailCacheAccountDirectory($settings) . '/gmail-history';
  pseEnsureDirectory($path);
  return $path;
}
function pseGmailHistorySync(array $settings): array
{
  global $sync, $historyCalls, $historyError;
  $historyCalls++;
  if ($historyError) throw $historyError;
  $path = pseGmailHistoryDirectory($settings) . '/state.json';
  $state = pseReadJson($path, []);
  $state = array_merge(['schema' => 1, 'identity' => pseGmailHistoryIdentity($settings), 'wildcardRevision' => 'initial', 'folderRevisions' => []], $state);
  $state['historyId'] = $sync['historyId'];
  foreach ($sync['changedFolders'] as $folder) {
    if ($folder === '*') {
      $state['wildcardRevision'] = 'reset-' . $sync['historyId'];
      foreach (glob(pseMailCacheAccountDirectory($settings) . '/lists/*.json') ?: [] as $file) unlink($file);
    } else {
      $state['folderRevisions'][$folder] = $sync['historyId'];
      pseMailCacheInvalidateFolderLists($settings, $folder);
    }
  }
  pseWriteJson($path, $state);
  return array_merge($sync, [
    'gmailHistoryRevision' => pseGmailHistoryRevision($settings),
    'gmailWildcardRevision' => $state['wildcardRevision'],
    'gmailFolderRevisionTokens' => $state['folderRevisions']
  ]);
}
function pseGmailHistoryAcknowledgeFolderCounts(array $settings, string $historyId, array $folders, string $revision = ''): void
{
  global $acknowledgments;
  $acknowledgments[] = [$historyId, $folders, $revision];
}
function pseGoogleApi(array $settings, string $method, string $path, array $query = []): array
{
  global $requests, $counts, $apiError, $apiErrorCode;
  $requests[] = $path;
  if ($apiError === $path) throw new RuntimeException('Fixture API failure', $apiErrorCode);
  if ($path === 'labels') return ['labels' => array_map(function ($id): array { return ['id' => $id, 'name' => $id]; }, array_keys($counts))];
  if (preg_match('~^labels/(.+)$~', $path, $match) && isset($counts[$match[1]])) {
    return ['name' => $match[1], 'messagesTotal' => $counts[$match[1]][0], 'messagesUnread' => $counts[$match[1]][1]];
  }
  throw new RuntimeException('Unexpected fixture request ' . $path);
}
function pseFolders(array $settings): array
{
  global $imapFolderCalls;
  $imapFolderCalls++;
  return [['id' => 'INBOX', 'messages' => 5, 'unseen' => 1]];
}
function pseMessageList(array $settings, string $folder, int $page, string $search, string $sender, bool $unread, string $sort, array $attachments, string $filter, string $date): array
{
  global $listCalls, $counts, $raceOnList;
  $listCalls++;
  if ($raceOnList) {
    $file = pseGmailHistoryDirectory($settings) . '/state.json';
    $state = pseReadJson($file);
    $state['folderRevisions'][$folder] = 'raced';
    pseWriteJson($file, $state);
  }
  return ['messages' => [['uid' => 'fixture-' . $listCalls, 'seen' => $counts[$folder][1] === 0]], 'total' => $counts[$folder][0], 'folderTotal' => $counts[$folder][0], 'folderUnseen' => $counts[$folder][1]];
}

$functions = [
  'pseReadJson', 'pseWriteJson', 'pseEnsureDirectory', 'pseMailCacheAccountDirectory',
  'pseMailCacheEnvelopeRead', 'pseMailCacheEnvelopeWrite', 'pseMailCacheInfo',
  'pseMailCacheFoldersFile', 'pseMailCacheListFile', 'pseMailCacheCalendarFile',
  'pseMailCacheFolderCounts', 'pseMailCacheChangedFolders', 'pseCachedFolders',
  'pseGmailHistoryFolderChanges', 'pseGmailHistoryFolderRevisionMap', 'pseCachedGmailFolderStatus', 'pseCachedFolderStatus',
  'pseCachedMessageList', 'pseMailCacheSetFolderCounts', 'pseMailCacheInvalidateFolderLists',
  'pseGmailFolders', 'pseGmailIgnoredLabels', 'pseNormalizeAttachmentFilter', 'pseNormalizeCalendarDate',
  'pseGmailHistoryIdentity', 'pseGmailHistoryRevision', 'pseGmailHistoryWriteDerived'
];
foreach ($functions as $name) {
  check((bool)preg_match('/^function ' . preg_quote($name, '/') . '\b/m', $source, $match, PREG_OFFSET_CAPTURE), 'Missing function ' . $name);
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
register_shutdown_function(function () use ($directory): void {
  if (!is_dir($directory)) return;
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  rmdir($directory);
});

$settings = ['account_id' => 'gmail', 'account_type' => 'gmail', 'google_oauth_email' => 'me@example.com', 'items_per_page' => 50];
$initial = pseCachedFolders($settings, true);
check($requests === ['labels', 'labels/INBOX', 'labels/SENT', 'labels/TRASH'], 'Initial folder population fetches counts once.');
check($initial['gmailHistorySynced'] && count($initial['folders']) === 3, 'Initial response advertises successful Gmail history synchronization.');
$requests = [];
$acknowledgments = [];
$unchanged = pseCachedFolderStatus($settings, ['INBOX', 'SENT']);
check($requests === [] && $unchanged['changedFolders'] === [], 'Unchanged status reads history without label count requests.');
check($unchanged['checkedFolders'] === ['INBOX', 'SENT'], 'Unchanged status validates the requested folders.');
check(isset($unchanged['gmailFolderRevisions']['INBOX']) && $unchanged['gmailFolderRevisions']['INBOX'] === pseGmailHistoryRevision($settings, 'INBOX'), 'Browser revisions match the locked history snapshot.');
check($acknowledgments[0][2] !== '', 'Folder count acknowledgment includes its generation token.');
$requests = [];
pseCachedFolders($settings, true);
check($requests === ['labels'], 'Explicit folder refresh updates names without fetching unchanged counts.');

$page = pseCachedMessageList($settings, 'INBOX', 1, '', '', false);
$file = pseMailCacheListFile($settings, 'INBOX', 1, '', '', false);
check($listCalls === 1 && !empty(pseReadJson($file)['gmailHistoryRevision']), 'Cold pages synchronize and save a generation token.');
$historyCallsBefore = $historyCalls;
$page2 = pseCachedMessageList($settings, 'INBOX', 1, '', '', false, 'desc', 'all', '', true);
check($page2['cache']['cached'] && $listCalls === 1 && $historyCalls === $historyCallsBefore + 1, 'Forced unchanged page refresh reuses cached messages after one history check.');
check($page2['data'] === $page['data'], 'Unchanged refresh preserves message data.');

$oldStamp = pseReadJson($file)['serverSyncedAt'];
$historyError = new RuntimeException('History temporarily unavailable');
$fallback = pseCachedMessageList($settings, 'INBOX', 1, '', '', false, 'desc', 'all', '', true);
check($fallback['cache']['refreshError'] === 'History temporarily unavailable' && $fallback['cache']['savedAt'] === $oldStamp, 'History failure returns cached data without claiming a new sync.');
check(is_file($file), 'History failure preserves the cached page.');
$historyError = null;

$sync['historyId'] = '101';
$sync['changedFolders'] = ['INBOX'];
$counts['INBOX'] = [11, 3];
$new = pseCachedMessageList($settings, 'INBOX', 1, '', '', false, 'desc', 'all', '', true);
check($listCalls === 2 && !$new['cache']['cached'] && $new['data']['folderTotal'] === 11, 'New mail rebuilds the affected page.');
$sync['changedFolders'] = [];
$sync['countDirtyFolders'] = ['INBOX'];
$requests = [];
$status = pseCachedFolderStatus($settings, ['INBOX', 'SENT']);
check($requests === ['labels/INBOX'] && $status['changedFolders'] === ['INBOX'], 'A delta consumed by a page request still refreshes durable dirty counts.');

$sync['countDirtyFolders'] = [];
$sync['historyId'] = '102';
$sync['changedFolders'] = ['INBOX', 'TRASH'];
$counts['INBOX'] = [11, 2];
$requests = [];
$status = pseCachedFolderStatus($settings, ['INBOX']);
check($requests === ['labels/INBOX', 'labels/TRASH'], 'Moves update both affected folder counts.');
check($status['changedFolders'] === ['INBOX', 'TRASH'], 'Equal-total flag changes remain visible as changed folders.');

$sync['changedFolders'] = ['INBOX', 'UNREAD', 'STARRED'];
$requests = [];
$status = pseCachedFolderStatus($settings, ['INBOX']);
check($requests === ['labels/INBOX'], 'Ignored Gmail system labels do not trigger a full folder count refresh.');
check(in_array('UNREAD', end($acknowledgments)[1], true), 'Ignored label changes are acknowledged to prevent repeated work.');

$sync['changedFolders'] = [];
$sync['countDirtyFolders'] = ['INBOX'];
$apiError = 'labels/INBOX';
$ackCount = count($acknowledgments);
$oldFolders = pseMailCacheEnvelopeRead(pseMailCacheFoldersFile($settings));
$failed = pseCachedFolderStatus($settings, ['INBOX']);
check(isset($failed['cache']['refreshError']) && count($acknowledgments) === $ackCount, 'Failed counts are not acknowledged.');
check($failed['cache']['savedAt'] === (int)$oldFolders['serverSyncedAt'], 'Failed count refresh keeps the previous sync timestamp.');
$apiError = '';
$requests = [];
pseCachedFolderStatus($settings, ['INBOX']);
check($requests === ['labels/INBOX'], 'Pending count refresh is retried even without new history.');

$sync['countDirtyFolders'] = ['TRASH'];
$apiError = 'labels/TRASH';
$apiErrorCode = 404;
unset($counts['TRASH']);
$requests = [];
$deletedLabel = pseCachedFolderStatus($settings, ['TRASH']);
check($requests === ['labels/TRASH', 'labels'] && count($deletedLabel['folders']) === 2, 'Deleted label definitions are rediscovered without repeated failing count requests.');
$apiError = '';
$apiErrorCode = 0;
$counts['TRASH'] = [1, 0];

$sync['countDirtyFolders'] = [];
$sync['changedFolders'] = ['*'];
$sync['reset'] = true;
$sync['historyId'] = '103';
$requests = [];
$reset = pseCachedFolderStatus($settings, ['INBOX']);
check(count($requests) === 4 && count($reset['changedFolders']) === 3, 'Expired-history reset refreshes all labels and expands the wildcard for clients.');
check(end($acknowledgments)[1] === ['*'], 'A complete count reset acknowledges the wildcard.');
$sync['reset'] = false;
$sync['changedFolders'] = [];

$callsBefore = $historyCalls;
$only = pseCachedMessageList($settings, 'INBOX', 4, '', '', false, 'desc', 'all', '', false, true);
check($only['cacheMiss'] && $historyCalls === $callsBefore, 'Cache-only prefetch does not contact Gmail.');
$otherSettings = array_merge($settings, ['google_oauth_email' => 'other@example.com']);
check(pseMailCacheListFile($otherSettings, 'INBOX', 1, '', '', false) !== $file, 'Gmail page cache identity changes with the OAuth mailbox.');
check(pseMailCacheCalendarFile($otherSettings, 'INBOX', '2026-10', '', '', false, 'all') !== pseMailCacheCalendarFile($settings, 'INBOX', '2026-10', '', '', false, 'all'), 'Calendar cache identity changes with the OAuth mailbox.');
check(pseMailCacheFoldersFile($otherSettings) !== pseMailCacheFoldersFile($settings), 'Folder counts are isolated by OAuth mailbox identity.');

$raceOnList = true;
try {
  pseCachedMessageList($settings, 'INBOX', 9, '', '', false);
  throw new RuntimeException('Expected generation rejection');
} catch (RuntimeException $error) {
  check(strpos($error->getMessage(), 'mailbox changed') !== false, 'Concurrent history changes reject stale derived-page writes.');
}
check(!is_file(pseMailCacheListFile($settings, 'INBOX', 9, '', '', false)), 'Rejected old generations cannot restore invalidated cache pages.');
$raceOnList = false;

$imap = ['account_id' => 'imap', 'account_type' => 'imap'];
$callsBefore = $historyCalls;
pseCachedFolders($imap, true);
pseCachedMessageList($imap, 'INBOX', 1, '', '', false);
pseCachedMessageList($imap, 'INBOX', 1, '', '', false, 'desc', 'all', '', true);
check($historyCalls === $callsBefore && $imapFolderCalls === 1, 'IMAP keeps its existing synchronization path without Gmail history calls.');
echo "Gmail history integration: {$checks} checks passed.\n";
