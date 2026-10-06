<?php
declare(strict_types=1);

// Offline synchronization fixtures; no Google account or network is used.
$source = file_get_contents(dirname(__DIR__) . '/index.php');
preg_match_all('/^function ([a-zA-Z0-9_]+)\(/m', $source, $matches, PREG_OFFSET_CAPTURE);
$functions = [];
foreach ($matches[1] as $index => $match) {
  $start = $matches[0][$index][1];
  $end = $matches[0][$index + 1][1] ?? strlen($source);
  $functions[$match[0]] = substr($source, $start, $end - $start);
}
foreach ($functions as $name => $body) {
  if (strpos($name, 'pseGmailHistory') === 0 || $name === 'pseGmailCachedMessage') eval($body);
}

$directory = sys_get_temp_dir() . '/pse-gmail-history-test-' . bin2hex(random_bytes(8));
$settings = ['account_id' => 'one', 'google_oauth_email' => 'first@example.com'];
$GLOBALS['calls'] = [];
$GLOBALS['fixtureMessages'] = [];
$GLOBALS['profileCursor'] = '18446744073709551610000';
$GLOBALS['historyPages'] = ['' => ['historyId' => $GLOBALS['profileCursor']]];
$GLOBALS['historyFailure'] = null;
$GLOBALS['messageFailure'] = null;
$assertions = 0;

function check(bool $condition, string $message): void {
  global $assertions;
  $assertions++;
  if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function rejects(callable $callback, int $code): void {
  try { $callback(); } catch (RuntimeException $error) {
    check($error->getCode() === $code, 'expected HTTP failure ' . $code . ', got ' . $error->getCode());
    return;
  }
  check(false, 'expected HTTP failure ' . $code);
}
function pseEnsureDirectory(string $path): void {
  if (!is_dir($path)) mkdir($path, 0700, true);
}
function pseMailCacheAccountDirectory(array $settings): string {
  global $directory;
  $path = $directory . '/' . hash('sha256', (string)$settings['account_id']);
  pseEnsureDirectory($path);
  foreach (['lists', 'messages', 'rendered', 'indexes', 'calendars'] as $child) pseEnsureDirectory($path . '/' . $child);
  return $path;
}
function pseReadJson(string $file, array $fallback = []): array {
  return is_file($file) ? (json_decode(file_get_contents($file), true) ?: $fallback) : $fallback;
}
function pseWriteJson(string $file, array $value): void {
  pseEnsureDirectory(dirname($file));
  if (!empty($GLOBALS['failWrite']) && basename($file) === 'state.json') throw new RuntimeException('State write failure');
  $temp = $file . '.tmp.' . bin2hex(random_bytes(4));
  file_put_contents($temp, json_encode($value));
  rename($temp, $file);
}
function pseMailCacheEnvelopeWrite(string $file, array $data, array $meta = []): array {
  $value = array_merge($meta, ['data' => $data, 'savedAt' => time()]);
  pseWriteJson($file, $value);
  return $value;
}
function pseGoogleApi(array $settings, string $method, string $path, array $query = [], ?array $data = null): array {
  $GLOBALS['calls'][] = [$method, $path, $query];
  if ($path === 'profile') return ['historyId' => $GLOBALS['profileCursor']];
  if ($path === 'history') {
    $token = (string)($query['pageToken'] ?? '');
    if (($GLOBALS['historyFailure']['token'] ?? null) === $token) throw new RuntimeException('Fixture history error', $GLOBALS['historyFailure']['code']);
    if (!isset($GLOBALS['historyPages'][$token])) throw new RuntimeException('Missing fixture page');
    return $GLOBALS['historyPages'][$token];
  }
  $id = substr($path, strlen('messages/'));
  if (($GLOBALS['messageFailure']['id'] ?? null) === $id) throw new RuntimeException('Fixture message error', $GLOBALS['messageFailure']['code']);
  if (!isset($GLOBALS['fixtureMessages'][$id])) throw new RuntimeException('Fixture message not found', 404);
  $message = $GLOBALS['fixtureMessages'][$id];
  if (($query['format'] ?? '') === 'metadata') {
    $required = array_map('strtolower', (array)($query['metadataHeaders'] ?? []));
    $message['payload'] = ['headers' => array_values(array_filter($message['payload']['headers'], function ($header) use ($required): bool {
      return empty($required) || in_array(strtolower($header['name']), $required, true);
    }))];
  }
  return $message;
}
function detailCalls(): array {
  return array_values(array_filter($GLOBALS['calls'], fn($call) => strpos($call[1], 'messages/') === 0));
}
function state(array $settings): array {
  return pseReadJson(pseGmailHistoryDirectory($settings) . '/state.json');
}
function cacheSeed(array $settings, string $layer, string $name, string $folder, string $uid = ''): string {
  $file = pseMailCacheAccountDirectory($settings) . '/' . $layer . '/' . $name . '.json';
  pseWriteJson($file, ['folder' => $folder, 'uid' => $uid, 'data' => []]);
  return $file;
}
function message(string $id, array $labels = ['INBOX', 'UNREAD'], string $body = 'body'): array {
  return [
    'id' => $id, 'threadId' => 'thread-' . $id, 'labelIds' => $labels,
    'internalDate' => '1791300000000', 'sizeEstimate' => 100,
    'payload' => ['mimeType' => 'text/plain', 'body' => ['data' => base64_encode($body)], 'headers' => [
      ['name' => 'Subject', 'value' => 'Subject ' . $id], ['name' => 'From', 'value' => 'sender@example.com'],
      ['name' => 'To', 'value' => 'Recipient <recipient@example.com>'], ['name' => 'Date', 'value' => 'Tue, 6 Oct 2026 12:00:00 +0000']
    ]]
  ];
}
$summaryQuery = ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'To', 'Cc', 'Bcc', 'Date', 'Reply-To']];
try {
  $legacyList = cacheSeed($settings, 'lists', 'legacy', 'INBOX');
  $legacySource = cacheSeed($settings, 'messages', 'legacy', 'INBOX', 'legacy');
  $bootstrap = pseGmailHistorySync($settings);
  check($bootstrap['reset'] && $bootstrap['historyId'] === $GLOBALS['profileCursor'], 'bootstrap saves an exact decimal-string checkpoint');
  check(count($GLOBALS['calls']) === 1 && $GLOBALS['calls'][0][1] === 'profile', 'bootstrap reads profile only, never the entire mailbox');
  check(!is_file($legacyList) && !is_file($legacySource), 'bootstrap invalidates older cache layers');
  check($bootstrap['countDirtyFolders'] === ['*'], 'bootstrap durably requests authoritative folder counts');
  foreach (['gmailHistoryRevision', 'gmailWildcardRevision', 'gmailFolderRevisionTokens'] as $field) {
    check(array_key_exists($field, $bootstrap), 'bootstrap exports locked snapshot field ' . $field);
  }
  $snapshotFolders = [['id' => 'INBOX'], ['id' => 'SENT'], ['id' => 'Label_unrelated']];
  $bootstrapMap = pseGmailHistoryFolderRevisionMap($bootstrap, $snapshotFolders);
  check($bootstrapMap['INBOX'] === pseGmailHistoryRevision($settings, 'INBOX') && $bootstrapMap['SENT'] === pseGmailHistoryRevision($settings, 'SENT'), 'actual integration helper expands bootstrap snapshot into known-folder revisions');
  pseGmailHistoryAcknowledgeFolderCounts($settings, $bootstrap['historyId'], ['*'], pseGmailHistoryRevision($settings));
  check(state($settings)['countDirtyFolders'] === [], 'successful complete count read acknowledges dirty folders');

  for ($index = 1; $index <= 50; $index++) {
    $id = 'm' . $index;
    $GLOBALS['fixtureMessages'][$id] = message($id);
    pseGmailCachedMessage($settings, $id, $summaryQuery);
  }
  check(count(detailCalls()) === 50, 'first requested page fetches 50 message summaries');
  $GLOBALS['calls'] = [];
  $empty = pseGmailHistorySync($settings);
  for ($index = 1; $index <= 50; $index++) pseGmailCachedMessage($settings, 'm' . $index, $summaryQuery);
  check(count($GLOBALS['calls']) === 1 && $GLOBALS['calls'][0][1] === 'history', 'unchanged page needs only one lightweight history request');
  check(!$empty['reset'] && $empty['changedMessageIds'] === [] && $empty['countDirtyFolders'] === [], 'empty history reports no changed messages or counts');
  check($empty['gmailHistoryRevision'] === pseGmailHistoryRevision($settings) && pseGmailHistoryFolderRevisionMap($empty, $snapshotFolders) === $bootstrapMap, 'unchanged sync exports consistent stable snapshot revisions');
  check($GLOBALS['calls'][0][2]['startHistoryId'] === $bootstrap['historyId'], 'history cursor is sent without numeric conversion');
  $unchangedToken = pseGmailHistoryMessageToken($settings, 'm1');
  pseGmailCachedMessage($settings, 'm1', $summaryQuery);
  check(pseGmailHistoryMessageToken($settings, 'm1') === $unchangedToken, 'unchanged raw payload keeps its generation token');

  $inboxList = cacheSeed($settings, 'lists', 'inbox', 'INBOX');
  $unrelatedList = cacheSeed($settings, 'lists', 'unrelated', 'Label_unrelated');
  $calendar = cacheSeed($settings, 'calendars', 'inbox', 'INBOX');
  $sourceFile = cacheSeed($settings, 'messages', 'm1', 'INBOX', 'm1');
  $renderedFile = cacheSeed($settings, 'rendered', 'm1', 'SENT', 'm1');
  $inboxRevision = pseGmailHistoryRevision($settings, 'INBOX');
  $otherRevision = pseGmailHistoryRevision($settings, 'Label_unrelated');
  $nextCursor = '18446744073709551610123';
  $GLOBALS['historyPages'] = ['' => ['historyId' => $nextCursor, 'history' => [
    ['id' => '18446744073709551610001', 'messages' => [['id' => 'm1']], 'labelsRemoved' => [['message' => ['id' => 'm1'], 'labelIds' => ['UNREAD']]]]
  ]]];
  $GLOBALS['calls'] = [];
  $read = pseGmailHistorySync($settings);
  check(count($GLOBALS['calls']) === 1 && empty(detailCalls()), 'read-status delta updates cached labels without downloading the message again');
  check(!in_array('UNREAD', pseGmailCachedMessage($settings, 'm1', $summaryQuery)['labelIds'], true), 'read-status delta updates summary flags');
  check($read['changedMessageIds'] === ['m1'] && in_array('INBOX', $read['changedFolders'], true), 'old labels identify affected Inbox even when history omits labels');
  check(!is_file($inboxList) && !is_file($calendar) && !is_file($sourceFile) && !is_file($renderedFile), 'changed message invalidates list/calendar/source/render across folders');
  check(is_file($unrelatedList), 'known labels preserve unrelated folder page caches');
  check(pseGmailHistoryMessageToken($settings, 'm1') !== $unchangedToken, 'changed raw labels invalidate older source generation');
  check(pseGmailHistoryRevision($settings, 'INBOX') !== $inboxRevision && pseGmailHistoryRevision($settings, 'Label_unrelated') === $otherRevision, 'per-folder revisions change only for affected folders');
  $readMap = pseGmailHistoryFolderRevisionMap($read, $snapshotFolders);
  check($readMap['INBOX'] === pseGmailHistoryRevision($settings, 'INBOX') && $readMap['Label_unrelated'] === $otherRevision, 'normal changed sync exports exact committed folder revisions');
  check(pseGmailHistoryWriteDerived($settings, 'INBOX', $inboxRevision, $inboxList, [], ['folder' => 'INBOX']) === [], 'a raced derived page cannot overwrite current history cache');
  $currentRevision = pseGmailHistoryRevision($settings, 'INBOX');
  check(!empty(pseGmailHistoryWriteDerived($settings, 'INBOX', $currentRevision, $inboxList, [], ['folder' => 'INBOX'])), 'current derived page writes with its history revision');

  $GLOBALS['historyPages'] = ['' => ['historyId' => $nextCursor]];
  $pending = pseGmailHistorySync($settings);
  check($pending['changedFolders'] === [] && in_array('INBOX', $pending['countDirtyFolders'], true), 'another endpoint consuming history cannot lose dirty folder counts');
  pseGmailHistoryAcknowledgeFolderCounts($settings, $bootstrap['historyId'], ['*']);
  check(in_array('INBOX', state($settings)['countDirtyFolders'], true), 'stale count acknowledgement cannot clear new dirty counts');
  pseGmailHistoryAcknowledgeFolderCounts($settings, $nextCursor, $pending['countDirtyFolders'], pseGmailHistoryRevision($settings));
  check(state($settings)['countDirtyFolders'] === [], 'matching count acknowledgement clears only completed work');

  $pageCursor = '18446744073709551610200';
  $GLOBALS['fixtureMessages']['m51'] = message('m51');
  $GLOBALS['historyPages'] = [
    '' => ['historyId' => $pageCursor, 'nextPageToken' => 'page2', 'history' => [
      ['id' => '18446744073709551610124', 'labelsRemoved' => [['message' => ['id' => 'm2'], 'labelIds' => ['INBOX']]]],
      ['id' => '18446744073709551610125', 'labelsAdded' => [['message' => ['id' => 'm2'], 'labelIds' => ['SENT']]]]
    ]],
    'page2' => ['historyId' => $pageCursor, 'history' => [
      ['id' => '18446744073709551610126', 'messagesDeleted' => [['message' => ['id' => 'm3']]]],
      ['id' => '18446744073709551610127', 'messagesAdded' => [['message' => ['id' => 'm51']]], 'messages' => [['id' => 'm51']]]
    ]]
  ];
  $GLOBALS['calls'] = [];
  $paginated = pseGmailHistorySync($settings);
  check(count($GLOBALS['calls']) === 3 && count(detailCalls()) === 1, 'all history pages finish before only the unknown new summary is fetched');
  check($GLOBALS['calls'][1][2]['startHistoryId'] === $nextCursor, 'every history page uses the original checkpoint');
  check($paginated['changedMessageIds'] === ['m2', 'm3', 'm51'] && $paginated['deletedMessageIds'] === ['m3'], 'duplicate typed/general records are deduplicated');
  $moved = pseGmailCachedMessage($settings, 'm2', $summaryQuery);
  check(!in_array('INBOX', $moved['labelIds'], true) && in_array('SENT', $moved['labelIds'], true), 'label deltas merge move changes into shared message payload');
  check(pseGmailHistoryMessageToken($settings, 'm3') === '', 'deleted messages lose their shared payload generation');
  check(!in_array('*', $paginated['changedFolders'], true), 'new-message summary resolves exact labels without forcing all folder counts');
  pseGmailCachedMessage($settings, 'm51', $summaryQuery);
  check(count(detailCalls()) === 1 && detailCalls()[0][1] === 'messages/m51', 'one new message requires exactly one new detail request');

  $GLOBALS['fixtureMessages']['known-label-new'] = message('known-label-new', ['Label_unrelated']);
  $GLOBALS['historyPages'] = ['' => ['historyId' => '18446744073709551610201', 'history' => [
    ['id' => '18446744073709551610201', 'messagesAdded' => [['message' => ['id' => 'known-label-new', 'labelIds' => ['Label_unrelated']]]]]
  ]]];
  $GLOBALS['calls'] = [];
  $knownLabelNew = pseGmailHistorySync($settings);
  check(empty(detailCalls()) && !in_array('*', $knownLabelNew['changedFolders'], true), 'uncached offscreen new mail with known labels needs no payload fetch');

  $beforeFailure = state($settings)['historyId'];
  $tokenBeforeFailure = pseGmailHistoryMessageToken($settings, 'm4');
  $GLOBALS['historyPages'] = [
    '' => ['historyId' => '18446744073709551610300', 'nextPageToken' => 'failure', 'history' => [
      ['id' => '18446744073709551610201', 'labelsRemoved' => [['message' => ['id' => 'm4'], 'labelIds' => ['UNREAD']]]]
    ]]
  ];
  $GLOBALS['historyFailure'] = ['token' => 'failure', 'code' => 429];
  rejects(fn() => pseGmailHistorySync($settings), 429);
  check(state($settings)['historyId'] === $beforeFailure && pseGmailHistoryMessageToken($settings, 'm4') === $tokenBeforeFailure, 'failed second page preserves cursor and payload cache');
  $GLOBALS['historyFailure'] = ['token' => '', 'code' => 503];
  rejects(fn() => pseGmailHistorySync($settings), 503);
  check(state($settings)['historyId'] === $beforeFailure, 'transient history failure never resets the checkpoint');
  $GLOBALS['historyFailure'] = null;

  $GLOBALS['fixtureMessages']['draft'] = message('draft', ['DRAFT'], 'old');
  $draft = pseGmailCachedMessage($settings, 'draft', ['format' => 'full']);
  $GLOBALS['fixtureMessages']['draft'] = message('draft', ['DRAFT'], 'changed draft');
  $GLOBALS['historyPages'] = ['' => ['historyId' => '18446744073709551610400', 'history' => [
    ['id' => '18446744073709551610301', 'messages' => [['id' => 'draft']], 'labelsAdded' => [['message' => ['id' => 'draft'], 'labelIds' => ['DRAFT']]]]
  ]]];
  $GLOBALS['calls'] = [];
  $GLOBALS['messageFailure'] = ['id' => 'draft', 'code' => 429];
  rejects(fn() => pseGmailHistorySync($settings), 429);
  check(state($settings)['historyId'] === $beforeFailure && pseGmailHistoryMessageToken($settings, 'draft') === $draft['_pseGmailCacheToken'], 'failed changed draft fetch preserves the original cursor/payload');
  $GLOBALS['messageFailure'] = null;
  $GLOBALS['calls'] = [];
  pseGmailHistorySync($settings);
  $changedDraft = pseGmailCachedMessage($settings, 'draft', ['format' => 'full']);
  check(count(detailCalls()) === 1 && base64_decode($changedDraft['payload']['body']['data']) === 'changed draft', 'only an already-cached changed draft payload is refreshed');

  $GLOBALS['historyPages'] = ['' => ['historyId' => '18446744073709551610400']];
  $localGlobalRevision = pseGmailHistoryRevision($settings);
  $localHistoryId = state($settings)['historyId'];
  pseGmailHistoryMarkMutation($settings, ['m5'], ['INBOX'], false);
  check(pseGmailHistoryMessageToken($settings, 'm5') === '' && state($settings)['historyId'] === $localHistoryId, 'local mutation invalidates payload while retaining the history checkpoint');
  pseGmailHistoryAcknowledgeFolderCounts($settings, $localHistoryId, ['*'], $localGlobalRevision);
  check(!empty(state($settings)['countDirtyFolders']), 'count fetch begun before a local mutation cannot acknowledge that mutation');
  $GLOBALS['calls'] = [];
  $fullBeforeRead = pseGmailCachedMessage($settings, 'm6', ['format' => 'full']);
  $GLOBALS['calls'] = [];
  pseGmailHistoryMarkMutation($settings, ['m6'], ['INBOX'], false, [], ['UNREAD']);
  $fullAfterRead = pseGmailCachedMessage($settings, 'm6', ['format' => 'full']);
  check(detailCalls() === [] && $fullAfterRead['payload'] === $fullBeforeRead['payload'] && !in_array('UNREAD', $fullAfterRead['labelIds'], true), 'local read-only label change preserves cached body without another message GET');
  check($fullAfterRead['_pseGmailCacheToken'] !== $fullBeforeRead['_pseGmailCacheToken'], 'local label change still fences an older source writer');

  $writeFailureCursor = state($settings)['historyId'];
  $GLOBALS['historyPages'] = ['' => ['historyId' => '18446744073709551610500', 'history' => [
    ['id' => '18446744073709551610401', 'labelsRemoved' => [['message' => ['id' => 'm7'], 'labelIds' => ['UNREAD']]]]
  ]]];
  $GLOBALS['failWrite'] = true;
  rejects(fn() => pseGmailHistorySync($settings), 0);
  $GLOBALS['failWrite'] = false;
  check(state($settings)['historyId'] === $writeFailureCursor, 'failure to persist final state never advances its checkpoint');
  pseGmailHistorySync($settings);
  check(!in_array('UNREAD', pseGmailCachedMessage($settings, 'm7', $summaryQuery)['labelIds'], true) && state($settings)['historyId'] === '18446744073709551610500', 'retry after partially applied cache writes safely replays idempotent label deltas');

  $GLOBALS['historyFailure'] = ['token' => '', 'code' => 404];
  $GLOBALS['profileCursor'] = '18446744073709551620000';
  $GLOBALS['calls'] = [];
  $expired = pseGmailHistorySync($settings);
  check($expired['reset'] && $expired['historyId'] === $GLOBALS['profileCursor'], 'expired HTTP 404 acquires a fresh profile checkpoint');
  check(array_column($GLOBALS['calls'], 1) === ['history', 'profile'], 'expired cursor resets without scanning the mailbox');
  check(pseGmailHistoryMessageToken($settings, 'm1') === '' && $expired['countDirtyFolders'] === ['*'], 'expired cursor invalidates all reusable payload and derived cache layers');
  $expiredMap = pseGmailHistoryFolderRevisionMap($expired, $snapshotFolders);
  check($expired['gmailHistoryRevision'] === pseGmailHistoryRevision($settings) && $expiredMap['INBOX'] === pseGmailHistoryRevision($settings, 'INBOX') && $expiredMap['INBOX'] !== $readMap['INBOX'], 'expired reset exports a new consistent revision snapshot for every known folder');
  $GLOBALS['historyFailure'] = null;
  $GLOBALS['historyPages'] = ['' => ['historyId' => $GLOBALS['profileCursor']]];

  $GLOBALS['fixtureMessages']['shared'] = message('shared', ['INBOX']);
  pseGmailCachedMessage($settings, 'shared', ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Date']]);
  $GLOBALS['calls'] = [];
  $upgraded = pseGmailCachedMessage($settings, 'shared', $summaryQuery);
  check(count(detailCalls()) === 1 && in_array('to', array_map(fn($header) => strtolower($header['name']), $upgraded['payload']['headers']), true), 'calendar-only metadata cannot satisfy recipient headers until upgraded');
  pseGmailCachedMessage($settings, 'shared', ['format' => 'full']);
  $GLOBALS['calls'] = [];
  pseGmailCachedMessage($settings, 'shared', $summaryQuery);
  pseGmailCachedMessage($settings, 'shared', ['format' => 'full']);
  check(detailCalls() === [], 'full payload satisfies metadata and full requests without downgrade');
  $otherAccount = ['account_id' => 'two', 'google_oauth_email' => 'second@example.com'];
  pseGmailCachedMessage($otherAccount, 'shared', $summaryQuery);
  check(count(detailCalls()) === 1, 'different account IDs never share payloads');
  $replacement = ['account_id' => 'one', 'google_oauth_email' => 'replacement@example.com'];
  pseGmailCachedMessage($replacement, 'shared', $summaryQuery);
  check(count(detailCalls()) === 2, 'different OAuth emails on a reused account ID never share payloads');
  $GLOBALS['calls'] = [];
  $replaced = pseGmailHistorySync($replacement);
  check($replaced['reset'] && $GLOBALS['calls'][0][1] === 'profile', 'replacement Gmail identity does not reuse the original user checkpoint');

  $GLOBALS['historyPages'] = ['' => ['historyId' => $GLOBALS['profileCursor'], 'nextPageToken' => 'loop'], 'loop' => ['historyId' => $GLOBALS['profileCursor'], 'nextPageToken' => 'loop']];
  rejects(fn() => pseGmailHistorySync($replacement), 0);
  check(state($replacement)['historyId'] === $GLOBALS['profileCursor'], 'repeated paging token never advances cursor');
  check(pseGmailHistoryCursorCompare('000100', '99') > 0 && pseGmailHistoryCursorCompare('18446744073709551610000', '18446744073709551610001') < 0, 'decimal cursor comparison stays exact above integer precision');
  echo 'Gmail history: ' . $assertions . " checks passed.\n";
} finally {
  $remove = function (string $path) use (&$remove): void {
    if (is_dir($path)) { foreach (glob($path . '/*') ?: [] as $child) $remove($child); rmdir($path); }
    else @unlink($path);
  };
  $remove($directory);
}
