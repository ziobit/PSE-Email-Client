<?php
declare(strict_types=1);

// Fixture-only checks for successful Gmail writes; no mailbox or network access.
// php -n -d extension=tokenizer tests/gmail-history-actions.php
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$temporaryDirectory = sys_get_temp_dir() . '/pse-gmail-actions-' . bin2hex(random_bytes(8));
define('PSE_MAIL_CACHE_DIR', $temporaryDirectory);
$assertions = 0;
$settings = ['account_id' => 'gmail-one', 'account_type' => 'gmail', 'google_oauth_email' => 'one@example.com'];
$otherSettings = ['account_id' => 'gmail-two', 'account_type' => 'gmail', 'google_oauth_email' => 'two@example.com'];
$GLOBALS['httpResponse'] = [];
$GLOBALS['httpCalls'] = [];
$GLOBALS['tokenRefreshes'] = [];

function check(bool $condition, string $description): void
{
  global $assertions;
  $assertions++;
  if (!$condition) throw new RuntimeException($description);
}

function pseEnsureDirectory(string $directory): void
{
  if (!is_dir($directory)) mkdir($directory, 0700, true);
}

function pseSafeAccountId(string $id): string
{
  return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? $id : '';
}

function pseReadJson(string $file, array $fallback = []): array
{
  return is_file($file) ? (json_decode(file_get_contents($file), true) ?: $fallback) : $fallback;
}

function pseWriteJson(string $file, array $data): void
{
  pseEnsureDirectory(dirname($file));
  file_put_contents($file, json_encode($data));
}

function pseMailCacheFoldersFile(array $settings): string
{
  return pseMailCacheAccountDirectory($settings) . '/folders.json';
}

function pseQueryString(array $query): string
{
  return http_build_query($query);
}

function pseGoogleAccessToken(array $settings, bool $forceRefresh = false): string
{
  $GLOBALS['tokenRefreshes'][] = [$settings['account_id'], $forceRefresh];
  return 'fixture-token';
}

function pseHttpJson(string $method, string $url, array $headers, ?array $data = null): array
{
  $GLOBALS['httpCalls'][] = [$method, $url, $data];
  if (!empty($GLOBALS['httpResponseQueue'])) {
    $response = array_shift($GLOBALS['httpResponseQueue']);
  } else {
    $response = $GLOBALS['httpResponse'];
  }
  if ($response instanceof Throwable) throw $response;
  return $response;
}

foreach ([
  'pseMailCacheAccountDirectory', 'pseGmailHistoryIdentity', 'pseGmailHistoryDirectory',
  'pseGmailHistoryWithLock', 'pseGmailHistoryMessageFile', 'pseGmailHistoryRemoveFile',
  'pseGmailHistoryReadMessage', 'pseGmailHistoryInvalidateDerived', 'pseGmailHistoryMarkMutation',
  'pseGmailHistoryMessageToken', 'pseIsGmailAccount', 'pseMailCacheEnvelopeRead', 'pseMailCacheEnvelopeWrite',
  'pseMailCacheMessageSourceFile', 'pseMailCacheMessageSourceLockFile', 'pseWithMessageSourceLock',
  'pseMailCacheMessageRenderedFile', 'pseMailCacheMessageRenderSignature',
  'pseMailCacheReadMessageSource', 'pseWriteMessageSource', 'pseWriteMessageSourceEnvelope',
  'pseMailCachePublicMessage', 'pseGoogleApi'
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

register_shutdown_function(fn() => deleteFixtureDirectory(PSE_MAIL_CACHE_DIR));

function seedMessage(array $settings, string $id, array $labels = ['INBOX', 'UNREAD']): void
{
  $directory = pseMailCacheAccountDirectory($settings);
  pseWriteJson(pseGmailHistoryMessageFile($settings, $id), [
    'schema' => 1, 'identity' => pseGmailHistoryIdentity($settings), 'cacheToken' => bin2hex(random_bytes(16)),
    'format' => 'full', 'headers' => ['*'],
    'message' => ['id' => $id, 'labelIds' => $labels, 'payload' => ['headers' => [], 'body' => ['data' => 'fixture-body']]]
  ]);
  foreach (['messages', 'rendered'] as $layer) {
    pseWriteJson($directory . '/' . $layer . '/' . $id . '.json', [
      'folder' => $labels[0] ?? 'INBOX', 'uid' => $id, 'data' => ['uid' => $id, 'seen' => false]
    ]);
  }
}

function seedFixtures(array $settings): string
{
  seedMessage($settings, 'mail-a');
  seedMessage($settings, 'mail-b');
  seedMessage($settings, 'old-draft', ['DRAFT']);
  seedMessage($settings, 'new-draft', ['DRAFT']);
  $directory = pseMailCacheAccountDirectory($settings);
  foreach (['INBOX', 'DRAFT', 'SENT'] as $folder) {
    pseWriteJson($directory . '/lists/' . $folder . '.json', ['folder' => $folder, 'data' => ['messages' => []]]);
  }
  pseWriteJson($directory . '/indexes/attachment-counts-INBOX.json', ['mail-a' => 2, 'mail-b' => 3]);
  pseWriteJson(pseMailCacheFoldersFile($settings), ['data' => [['id' => 'INBOX', 'messages' => 2, 'unseen' => 2]]]);
  $stateFile = pseGmailHistoryDirectory($settings) . '/state.json';
  pseWriteJson($stateFile, [
    'schema' => 1, 'identity' => pseGmailHistoryIdentity($settings),
    'historyId' => '18446744073709551617001', 'countDirtyFolders' => ['INBOX'], 'syncedAt' => 1
  ]);
  return file_get_contents($stateFile);
}

$otherState = seedFixtures($otherSettings);
$state = seedFixtures($settings);
$beforeReadToken = pseGmailHistoryMessageToken($settings, 'mail-a');
$GLOBALS['httpResponse'] = ['id' => 'mail-a', 'labelIds' => ['INBOX']];
$result = pseGoogleApi($settings, 'POST', 'messages/mail-a/modify', [], ['removeLabelIds' => ['UNREAD']]);
check($result['id'] === 'mail-a', 'A successful mutation returns its original server response.');
check(is_file(pseGmailHistoryMessageFile($settings, 'mail-a')) && !in_array('UNREAD', pseGmailHistoryReadMessage($settings, 'mail-a')['message']['labelIds'], true), 'Marking read patches the known labels without retrieving the unchanged body again.');
check(pseGmailHistoryMessageToken($settings, 'mail-a') !== $beforeReadToken && pseGmailHistoryReadMessage($settings, 'mail-a')['message']['payload']['body']['data'] === 'fixture-body', 'A label-only mutation preserves the full cached body and changes its generation token.');
check(!is_file(pseMailCacheAccountDirectory($settings) . '/messages/mail-a.json'), 'Marking read invalidates the old source cache.');
check(!is_file(pseMailCacheAccountDirectory($settings) . '/rendered/mail-a.json'), 'Marking read invalidates the old rendered cache.');
check(is_file(pseGmailHistoryMessageFile($settings, 'mail-b')), 'An unrelated message remains cached.');
check(!is_file(pseMailCacheFoldersFile($settings)), 'A local mutation makes the next folder-count read authoritative.');
check(pseReadJson(pseGmailHistoryDirectory($settings) . '/state.json')['historyId'] === json_decode($state, true)['historyId'], 'A local mutation never advances or rounds the saved history checkpoint.');
check(in_array('INBOX', pseReadJson(pseGmailHistoryDirectory($settings) . '/state.json')['countDirtyFolders'], true), 'Count changes remain pending until a successful count refresh acknowledges them.');
check(!empty(pseReadJson(pseGmailHistoryDirectory($settings) . '/state.json')['mutationRevision']), 'Local writes change the durable cache revision before the next history read.');
check(file_get_contents(pseGmailHistoryDirectory($otherSettings) . '/state.json') === $otherState && is_file(pseGmailHistoryMessageFile($otherSettings, 'mail-a')), 'Mutation invalidation is isolated to the current account.');

foreach (['trash', 'untrash'] as $operation) {
  seedFixtures($settings);
  $GLOBALS['httpResponse'] = [];
  pseGoogleApi($settings, 'POST', 'messages/mail-a/' . $operation, [], []);
  check(!is_file(pseGmailHistoryMessageFile($settings, 'mail-a')), $operation . ' invalidates the path message even with an empty response.');
}

foreach (['batchModify', 'batchDelete'] as $operation) {
  seedFixtures($settings);
  $GLOBALS['httpResponse'] = [];
  $request = ['ids' => ['mail-a', 'mail-b', 'mail-a', '../invalid']];
  if ($operation === 'batchModify') $request['removeLabelIds'] = ['UNREAD'];
  pseGoogleApi($settings, 'POST', 'messages/' . $operation, [], $request);
  if ($operation === 'batchModify') {
    check(!in_array('UNREAD', pseGmailHistoryReadMessage($settings, 'mail-a')['message']['labelIds'], true) && !in_array('UNREAD', pseGmailHistoryReadMessage($settings, 'mail-b')['message']['labelIds'], true), 'Batch read updates patch every cached message while preserving their bodies.');
  } else {
    check(!is_file(pseGmailHistoryMessageFile($settings, 'mail-a')) && !is_file(pseGmailHistoryMessageFile($settings, 'mail-b')), $operation . ' invalidates every request message ID.');
  }
  check(pseReadJson(pseMailCacheAccountDirectory($settings) . '/indexes/attachment-counts-INBOX.json') === ($operation === 'batchDelete' ? [] : ['mail-a' => 2, 'mail-b' => 3]), $operation . ' preserves immutable attachment counts unless the message is deleted.');
}

seedFixtures($settings);
pseGoogleApi($settings, 'DELETE', 'messages/mail-a');
check(!is_file(pseGmailHistoryMessageFile($settings, 'mail-a')), 'Single permanent deletion invalidates its message ID.');

foreach (['send', 'insert'] as $operation) {
  seedFixtures($settings);
  seedMessage($settings, 'sent-message', ['SENT']);
  $GLOBALS['httpResponse'] = ['id' => 'sent-message'];
  pseGoogleApi($settings, 'POST', 'messages/' . $operation, [], ['raw' => 'fixture']);
  check(!is_file(pseGmailHistoryMessageFile($settings, 'sent-message')) && is_file(pseGmailHistoryMessageFile($settings, 'mail-b')), $operation . ' invalidates the returned Sent ID without touching unrelated messages.');
}

foreach ([['POST', 'drafts'], ['PUT', 'drafts/draft-id'], ['POST', 'drafts/send'], ['DELETE', 'drafts/draft-id']] as [$method, $path]) {
  seedFixtures($settings);
  if ($path === 'drafts/send') seedMessage($settings, 'sent-draft', ['SENT']);
  $GLOBALS['httpResponse'] = $path === 'drafts/send'
    ? ['id' => 'sent-draft', 'labelIds' => ['SENT']]
    : ['id' => 'draft-id', 'message' => ['id' => 'new-draft']];
  pseGoogleApi($settings, $method, $path, [], ['id' => 'draft-id']);
  check(!is_file(pseGmailHistoryMessageFile($settings, 'old-draft')) && !is_file(pseGmailHistoryMessageFile($settings, 'new-draft')), $path . ' invalidates both replaced and returned native draft message IDs.');
  check(!is_file(pseMailCacheAccountDirectory($settings) . '/lists/DRAFT.json') && is_file(pseMailCacheAccountDirectory($settings) . '/lists/INBOX.json'), $path . ' invalidates the draft page without dropping the unrelated Inbox page.');
  check(is_file(pseMailCacheAccountDirectory($settings) . '/lists/SENT.json') === ($path !== 'drafts/send'), $path . ' changes the Sent page only when the draft is sent.');
  if ($path === 'drafts/send') check(!is_file(pseGmailHistoryMessageFile($settings, 'sent-draft')), 'Sending a draft invalidates the flat message ID returned by Gmail.');
}

seedFixtures($settings);
$GLOBALS['httpResponse'] = ['id' => 'mail-a'];
pseGoogleApi($settings, 'GET', 'messages/mail-a', ['format' => 'full']);
check(is_file(pseGmailHistoryMessageFile($settings, 'mail-a')) && is_file(pseMailCacheFoldersFile($settings)), 'A successful read does not invalidate any cache.');

seedFixtures($settings);
$GLOBALS['httpResponse'] = new RuntimeException('Google request failed (HTTP 403): denied', 403);
try {
  pseGoogleApi($settings, 'POST', 'messages/mail-a/trash', [], []);
  check(false, 'A failed operation must throw.');
} catch (RuntimeException $error) {
  check($error->getCode() === 403 && is_file(pseGmailHistoryMessageFile($settings, 'mail-a')), 'A failed server operation preserves the existing payload and status.');
}

seedFixtures($settings);
$GLOBALS['httpResponseQueue'] = [new RuntimeException('Google request failed (HTTP 401): expired', 401), ['id' => 'mail-a']];
$GLOBALS['tokenRefreshes'] = [];
pseGoogleApi($settings, 'POST', 'messages/mail-a/modify', [], ['addLabelIds' => ['UNREAD']]);
check($GLOBALS['tokenRefreshes'] === [['gmail-one', false], ['gmail-one', true]], 'Authentication refresh happens once before the successful mutation.');
check(in_array('UNREAD', pseGmailHistoryReadMessage($settings, 'mail-a')['message']['labelIds'], true), 'A successful authenticated retry patches the unread label in the retained payload.');

// Reproduce a decoder completing after another request has changed the payload.
seedMessage($settings, 'race-message');
$oldToken = pseGmailHistoryMessageToken($settings, 'race-message');
$oldSource = ['uid' => 'race-message', 'html' => 'Old body', 'plain' => 'Old body', 'seen' => false, '_cacheGmailToken' => $oldToken];
$sourceFile = pseMailCacheMessageSourceFile($settings, 'INBOX', 'race-message');
pseWithMessageSourceLock($settings, 'INBOX', 'race-message', function () use ($settings, $oldSource): void {
  pseWriteMessageSource($settings, 'INBOX', 'race-message', $oldSource, false);
});
check(pseMailCacheReadMessageSource($settings, 'INBOX', 'race-message')['data']['plain'] === 'Old body', 'A matching raw token permits the source to be persisted while its source lock is held.');

$entry = pseGmailHistoryReadMessage($settings, 'race-message');
$entry['cacheToken'] = bin2hex(random_bytes(16));
pseWriteJson(pseGmailHistoryMessageFile($settings, 'race-message'), $entry);
check(pseMailCacheReadMessageSource($settings, 'INBOX', 'race-message') === [], 'A source from an older raw-cache generation is rejected even if it still exists on disk.');

$newSource = ['uid' => 'race-message', 'html' => 'New body', 'plain' => 'New body', 'seen' => true, '_cacheGmailToken' => $entry['cacheToken']];
pseWriteMessageSource($settings, 'INBOX', 'race-message', $newSource, false);
$freshBytes = file_get_contents($sourceFile);
pseWriteMessageSource($settings, 'INBOX', 'race-message', $oldSource, false);
check(file_get_contents($sourceFile) === $freshBytes, 'An old decoder cannot overwrite a source persisted for the newer generation.');
check(pseMailCacheReadMessageSource($settings, 'INBOX', 'race-message')['data']['seen'] === true, 'Reading after the stale write returns the current flags and body.');

pseWithMessageSourceLock($settings, 'INBOX', 'race-message', function () use ($settings, $newSource): void {
  pseGmailHistoryMarkMutation($settings, ['race-message'], ['INBOX'], false);
  pseWriteMessageSource($settings, 'INBOX', 'race-message', $newSource, false);
});
check(!is_file($sourceFile) && pseMailCacheReadMessageSource($settings, 'INBOX', 'race-message') === [], 'Mutation invalidation and a late source writer complete under the source lock without restoring a deleted generation.');

seedMessage($otherSettings, 'race-message');
pseWriteMessageSource($otherSettings, 'INBOX', 'race-message', $newSource, false);
check(!is_file(pseMailCacheMessageSourceFile($otherSettings, 'INBOX', 'race-message')), 'A source token from another account cannot populate its cache.');
check(!array_key_exists('_cacheGmailToken', pseMailCachePublicMessage($newSource)), 'Raw-cache generation tokens stay out of browser responses.');

echo 'Gmail history actions: ' . $assertions . " checks passed.\n";
