<?php
declare(strict_types=1);

// Offline fixtures: real queue filtering and optimistic persistent list updates.
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$temporaryDirectory = sys_get_temp_dir() . '/pse-queued-delete-' . bin2hex(random_bytes(8));
define('PSE_MAIL_CACHE_DIR', $temporaryDirectory . '/mail-cache');
define('PSE_ACTION_QUEUE_FILE', $temporaryDirectory . '/action-queue.json');
$checks = 0;
$sideEffects = [];

function check(bool $condition, string $description): void
{
  global $checks;
  if (!$condition) throw new RuntimeException($description);
  $checks++;
}

function pseEnsureStorage(): void
{
  if (!is_dir(dirname(PSE_ACTION_QUEUE_FILE))) mkdir(dirname(PSE_ACTION_QUEUE_FILE), 0700, true);
}

function pseSafeAccountId(string $id): string
{
  return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? $id : '';
}

function pseMailCacheUpdateFoldersCounts(array $settings, string $folder, int $messages, int $unseen): void
{
  $GLOBALS['sideEffects'][] = ['counts', $settings['account_id'], $folder, $messages, $unseen];
}

function pseMailCacheFolderSpecial(array $settings, string $folder): string
{
  return $folder === 'TRASH' ? 'trash' : 'folder';
}

function pseMailCacheAdjustSpecialFolder(array $settings, string $special, int $messages, int $unseen = 0): void
{
  $GLOBALS['sideEffects'][] = ['special', $settings['account_id'], $special, $messages, $unseen];
}

function pseMailCacheDeleteMessageFiles(array $settings, string $folder, string $uid): void
{
  $GLOBALS['sideEffects'][] = ['details', $settings['account_id'], $folder, $uid];
}

function pseMailCacheRemoveAttachmentCounts(array $settings, string $folder, array $uids): void {}
function pseMailCacheInvalidateFolderCalendars(array $settings, string $folder): void {}

class FixtureJsonResponse extends RuntimeException
{
  public array $response;
  public function __construct(array $response) { $this->response = $response; }
}
function pseIsAuthenticated(array $settings): bool { return true; }
function pseRequireCsrf(array $settings): void {}
function pseCleanupExpiredAttachmentUploads(): void {}
function pseBody(): array { return $GLOBALS['fixtureBody']; }
function pseApplyClientAppearanceSettings(array $settings, $appearance): array { return $settings; }
function pseJson(array $data, int $status = 200): void { throw new FixtureJsonResponse($data); }
function pseCachedMessageList(
  array $settings, string $folder, int $page, string $search, string $sender,
  bool $unread, string $sort, string $attachments, string $date, bool $force, bool $cacheOnly
): array {
  $GLOBALS['fixtureListArguments'] = func_get_args();
  return $GLOBALS['fixtureListResult'];
}

foreach ([
  'pseReadJson', 'pseWriteJson', 'pseEnsureDirectory', 'pseIsGmailAccount',
  'pseMailCacheAccountDirectory', 'pseMailCacheEnvelopeRead', 'pseMailCacheEnvelopeWrite',
  'pseActionQueueTransaction', 'pseFilterQueuedDeletedMessages', 'pseMailCacheDeleteMessages',
  'pseNormalizeAttachmentFilter', 'pseNormalizeCalendarDate', 'pseHandleAjax'
] as $name) {
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

function deleteFixtureDirectory(string $directory): void
{
  if (!is_dir($directory)) return;
  foreach (scandir($directory) ?: [] as $name) {
    if ($name === '.' || $name === '..') continue;
    $file = $directory . '/' . $name;
    if (is_dir($file)) deleteFixtureDirectory($file); else unlink($file);
  }
  rmdir($directory);
}
register_shutdown_function(fn() => deleteFixtureDirectory($temporaryDirectory));

$gmail = ['account_id' => 'one', 'account_type' => 'gmail'];
$other = ['account_id' => 'two', 'account_type' => 'gmail'];
$imap = ['account_id' => 'one', 'account_type' => 'imap'];
$page = [
  'messages' => [['uid' => 'mail-a', 'seen' => false], ['uid' => 'mail-b', 'seen' => true]],
  'page' => 2, 'pages' => 10, 'perPage' => 50, 'total' => 500, 'folderTotal' => 800,
  'folderUnseen' => 20, 'uidValidity' => '123456789'
];

function seedQueue(array $items): void
{
  pseActionQueueTransaction(fn(array $queue): array => ['queue' => $items, 'result' => null]);
}

seedQueue([
  ['id' => 'one-delete', 'action' => 'delete', 'account_id' => 'one', 'folder' => 'INBOX', 'uid' => 'mail-a'],
  ['id' => 'two-delete', 'action' => 'delete', 'account_id' => 'two', 'folder' => 'INBOX', 'uid' => 'mail-b'],
  ['id' => 'read-only', 'action' => 'read', 'account_id' => 'one', 'folder' => 'INBOX', 'uid' => 'mail-b'],
  ['id' => 'malformed', 'action' => 'delete', 'account_id' => 'one', 'folder' => 'INBOX'],
  'invalid queue item'
]);
$filtered = pseFilterQueuedDeletedMessages($gmail, 'INBOX', $page);
check(array_column($filtered['messages'], 'uid') === ['mail-b'], 'A pending Gmail delete is hidden from the source folder.');
check(array_column(pseFilterQueuedDeletedMessages($gmail, 'Label_42', $page)['messages'], 'uid') === ['mail-b'], 'A pending Gmail delete is hidden across labels that share its global message ID.');
check(array_column(pseFilterQueuedDeletedMessages($other, 'INBOX', $page)['messages'], 'uid') === ['mail-a'], 'Pending deletion IDs are scoped to the current account.');
check(array_column(pseFilterQueuedDeletedMessages($imap, 'INBOX', $page)['messages'], 'uid') === ['mail-b'], 'Pending IMAP deletes are hidden in their source folder.');
check(pseFilterQueuedDeletedMessages($imap, 'Archive', $page) === $page, 'An identical IMAP UID in a different folder is preserved.');
foreach (array_diff(array_keys($page), ['messages']) as $field) {
  check($filtered[$field] === $page[$field], 'Filtering preserves provider pagination/count field: ' . $field);
}
check(pseFilterQueuedDeletedMessages($gmail, 'INBOX', $filtered) === $filtered, 'Filtering an already optimistic page is idempotent without subtracting counts twice.');
check($page['messages'][0]['uid'] === 'mail-a' && count($page['messages']) === 2, 'Response filtering does not mutate its input data.');

seedQueue([['id' => 'failed', 'action' => 'delete', 'account_id' => 'one', 'folder' => 'INBOX', 'uid' => 'mail-a', 'attempts' => 3, 'last_error' => 'Temporary server error']]);
check(array_column(pseFilterQueuedDeletedMessages($gmail, 'INBOX', $page)['messages'], 'uid') === ['mail-b'], 'A failed queued delete remains hidden while awaiting retry.');
seedQueue([]);
check(pseFilterQueuedDeletedMessages($gmail, 'INBOX', $page) === $page, 'Undoing or completing the queue releases hidden message IDs.');
check(pseFilterQueuedDeletedMessages($gmail, 'INBOX', ['messages' => []]) === ['messages' => []], 'An empty page stays empty.');

// Exercise the real AJAX route with controlled cache reads to cover both cache
// hits and cache misses rebuilt while the underlying mailbox still has the UID.
function requestMessages(array $settings, array $body, array $result): array
{
  $GLOBALS['fixtureBody'] = $body;
  $GLOBALS['fixtureListResult'] = $result;
  try {
    pseHandleAjax('messages', $settings);
    throw new RuntimeException('The messages route returned without JSON.');
  } catch (FixtureJsonResponse $response) {
    return $response->response;
  }
}
seedQueue([['id' => 'route-pending', 'action' => 'delete', 'account_id' => 'one', 'folder' => 'INBOX', 'uid' => 'mail-a']]);
foreach ([false, true] as $forceRefresh) {
  $result = requestMessages($gmail, ['folder' => 'INBOX', 'forceRefresh' => $forceRefresh], [
    'data' => $page, 'cache' => ['cached' => !$forceRefresh, 'savedAt' => 123], 'cacheMiss' => false
  ]);
  check($result['ok'] && array_column($result['data']['messages'], 'uid') === ['mail-b'],
    'The real messages route hides queued rows on ' . ($forceRefresh ? 'forced refresh' : 'cache hit') . '.');
  check($result['cache']['savedAt'] === 123 && !$result['cacheMiss'], 'Response filtering preserves the route cache status.');
  check($GLOBALS['fixtureListArguments'][9] === $forceRefresh, 'The route passes the requested refresh mode to the cache handler.');
}
$miss = requestMessages($gmail, ['folder' => 'INBOX', 'cacheOnly' => true], [
  'data' => null, 'cache' => ['cached' => true, 'savedAt' => 0], 'cacheMiss' => true
]);
check($miss['data'] === null && $miss['cacheMiss'], 'The real route preserves a cache-only miss without trying to filter null data.');
seedQueue([]);
$restored = requestMessages($gmail, ['folder' => 'INBOX', 'forceRefresh' => true], [
  'data' => $page, 'cache' => ['cached' => false, 'savedAt' => 124], 'cacheMiss' => false
]);
check(array_column($restored['data']['messages'], 'uid') === ['mail-a', 'mail-b'],
  'After Undo clears the queue, the real messages route allows the restored row.');

// A cached filtered page must survive unchanged Gmail history after queueing.
$directory = pseMailCacheAccountDirectory($gmail);
$file = $directory . '/lists/inbox-filtered.json';
$metadata = [
  'folder' => 'INBOX', 'page' => 2, 'search' => 'receipt',
  'senderFilter' => 'sender@example.com', 'unreadOnly' => false,
  'sortOrder' => 'asc', 'attachmentFilter' => 'with', 'startDate' => '2026-10-01',
  'gmailHistoryRevision' => 'revision-unchanged', 'serverSyncedAt' => 1234567890
];
pseMailCacheEnvelopeWrite($file, $page, $metadata);
$otherFile = pseMailCacheAccountDirectory($other) . '/lists/inbox.json';
pseMailCacheEnvelopeWrite($otherFile, $page, $metadata);
$otherBytes = file_get_contents($otherFile);
pseMailCacheDeleteMessages($gmail, 'INBOX', ['mail-a']);
$updated = pseMailCacheEnvelopeRead($file);
check(array_column($updated['data']['messages'], 'uid') === ['mail-b'], 'Queueing removes the UID from the real persistent page cache.');
foreach ($metadata as $field => $value) {
  check(($updated[$field] ?? null) === $value, 'Optimistic page updates preserve metadata: ' . $field);
}
check($updated['data']['folderTotal'] === 799 && $updated['data']['folderUnseen'] === 19, 'Optimistic persistent counts change once when queueing.');
check(!array_key_exists('_removedHere', $updated), 'Temporary removal bookkeeping stays out of the saved envelope.');
check(file_get_contents($otherFile) === $otherBytes, 'Optimistic queue updates leave another account cache unchanged.');

echo 'Queued delete cache: ' . $checks . " checks passed.\n";
