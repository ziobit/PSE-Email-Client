<?php
declare(strict_types=1);

// Offline fixtures use the real list renderer and per-message cache. Gmail and
// storage paths are isolated; no mailbox or network access is required.
// php -n -d extension=tokenizer tests/gmail-message-cache.php
$source = file_get_contents(dirname(__DIR__) . '/index.php');
$functionNames = [
  'pseGmailMessageList', 'pseGmailCachedMessage',
  'pseGmailHeaders', 'pseGmailSearchText', 'pseGmailAttachmentCount',
  'pseMime', 'pseAddressList', 'pseSenderDisplayParts', 'pseRecipientDisplayParts',
  'pseListPreviewText', 'pseSearchContext', 'pseNormalizeAttachmentFilter',
  'pseNormalizeCalendarDate', 'pseSettingsTimezone', 'pseBase64UrlDecode',
  'pseCalendarMonthData', 'pseCachedCalendarMonth', 'pseNormalizeCalendarMonth',
  'pseCalendarAddMessage', 'pseCalendarDayKey', 'pseMailCacheCalendarFile',
  'pseMailCacheEnvelopeRead', 'pseMailCacheEnvelopeWrite', 'pseMailCacheInfo'
];
preg_match_all('/^function (pseGmailHistory[a-zA-Z0-9_]*)\b/m', $source, $historyFunctions);
$functionNames = array_merge($functionNames, $historyFunctions[1]);
foreach ($functionNames as $name) {
  if (!preg_match('/^function ' . preg_quote($name, '/') . '\b/m', $source, $match, PREG_OFFSET_CAPTURE)) {
    throw new RuntimeException('Missing function: ' . $name);
  }
  $tokens = token_get_all('<?php ' . substr($source, $match[0][1]));
  $body = '';
  $depth = 0;
  $started = false;
  foreach (array_slice($tokens, 1) as $token) {
    $text = is_array($token) ? $token[1] : $token;
    $body .= $text;
    if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
      $depth++;
      $started = true;
    } elseif ($token === '}') {
      $depth--;
      if ($started && $depth === 0) {
        break;
      }
    }
  }
  eval($body);
}

$checks = 0;
function check(bool $condition, string $description): void
{
  global $checks;
  if (!$condition) {
    throw new RuntimeException($description);
  }
  $checks++;
}

$fixtureRoot = sys_get_temp_dir() . '/pse-gmail-list-' . bin2hex(random_bytes(8));
mkdir($fixtureRoot, 0700, true);
register_shutdown_function(function () use ($fixtureRoot): void {
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($files as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  }
  rmdir($fixtureRoot);
});

function pseMailCacheAccountDirectory(array $settings): string
{
  global $fixtureRoot;
  $directory = $fixtureRoot . '/' . hash('sha256', (string)$settings['account_id']);
  pseEnsureDir($directory);
  return $directory;
}
function pseEnsureDir(string $directory): void
{
  if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
  }
}
function pseEnsureDirectory(string $directory): void { pseEnsureDir($directory); }
function pseReadJson(string $file, array $default = []): array
{
  $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
  return is_array($data) ? $data : $default;
}
function pseWriteJson(string $file, array $data): void
{
  pseEnsureDir(dirname($file));
  file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
}
function pseFolderIsSent(array $settings, string $folder): bool { return $folder === 'SENT'; }
function pseIsGmailAccount(array $settings): bool { return $settings['account_type'] === 'gmail'; }
function pseFormatDate($timestamp, array $settings): string { return gmdate('Y-m-d H:i:s', (int)$timestamp); }

$fixtureMessages = [];
$fixtureFolders = ['INBOX' => [], 'SENT' => []];
$detailRequests = [];
$listRequests = 0;
$metadataHeaderSets = [];
$historyResponse = ['historyId' => '100'];
$historyError = null;
$profileHistoryId = '100';
$historyRequests = 0;
$listMutation = null;
function fixtureMessage(string $id): array
{
  $headers = [
    'Subject' => 'Fixture ' . $id,
    'From' => 'Owner <owner@example.com>',
    'To' => '"Doe, Jane" <jane@example.com>, second@example.com',
    'Cc' => 'Copy <copy@example.com>',
    'Bcc' => 'Hidden <hidden@example.com>',
    'Reply-To' => 'Replies <reply@example.com>',
    'Date' => 'Tue, 6 Oct 2026 12:00:00 +0000'
  ];
  return [
    'id' => $id,
    'historyId' => '100',
    'internalDate' => '1791288000000',
    'sizeEstimate' => 2048,
    'labelIds' => ['INBOX', 'UNREAD'],
    'snippet' => 'Snippet for ' . $id,
    'payload' => [
      'mimeType' => 'multipart/mixed',
      'headers' => array_map(function ($name, $value): array {
        return ['name' => $name, 'value' => $value];
      }, array_keys($headers), array_values($headers)),
      'parts' => [
        ['mimeType' => 'text/plain', 'body' => ['data' => rtrim(strtr(base64_encode('Body for ' . $id . ' searchneedle'), '+/', '-_'), '=')]],
        ['mimeType' => 'application/pdf', 'filename' => 'fixture.pdf', 'body' => ['attachmentId' => 'a1']]
      ]
    ]
  ];
}

function pseGoogleApi(array $settings, string $method, string $path, array $query = []): array
{
  global $fixtureMessages, $fixtureFolders, $detailRequests, $listRequests, $metadataHeaderSets;
  global $historyResponse, $historyError, $profileHistoryId, $historyRequests;
  global $listMutation;
  if ($method !== 'GET') {
    throw new RuntimeException('Unexpected Gmail method: ' . $method);
  }
  if ($path === 'profile') {
    return ['historyId' => $profileHistoryId];
  }
  if ($path === 'history') {
    $historyRequests++;
    if ($historyError instanceof RuntimeException) {
      throw $historyError;
    }
    return $historyResponse;
  }
  if ($path === 'messages') {
    $listRequests++;
    if (is_callable($listMutation)) {
      $listMutation($settings);
    }
    $ids = $fixtureFolders[(string)$query['labelIds']] ?? [];
    $offset = (int)($query['pageToken'] ?? 0);
    $limit = (int)$query['maxResults'];
    $result = [
      'messages' => array_map(function (string $id): array { return ['id' => $id]; }, array_slice($ids, $offset, $limit)),
      'resultSizeEstimate' => count($ids)
    ];
    if ($offset + $limit < count($ids)) {
      $result['nextPageToken'] = (string)($offset + $limit);
    }
    return $result;
  }
  if (strpos($path, 'labels/') === 0) {
    $folder = rawurldecode(substr($path, strlen('labels/')));
    return ['messagesTotal' => count($fixtureFolders[$folder] ?? []), 'messagesUnread' => 1];
  }
  if (strpos($path, 'messages/') === 0) {
    $id = rawurldecode(substr($path, strlen('messages/')));
    $format = (string)($query['format'] ?? 'full');
    $detailRequests[] = [$settings['account_id'], $id, $format];
    $message = $fixtureMessages[$id] ?? null;
    if (!is_array($message)) {
      throw new RuntimeException('Unknown fixture message: ' . $id, 404);
    }
    if ($format === 'metadata') {
      $metadataHeaderSets[] = $query['metadataHeaders'] ?? [];
      $requested = array_map('strtolower', (array)($query['metadataHeaders'] ?? []));
      $message['payload']['headers'] = array_values(array_filter($message['payload']['headers'], function (array $header) use ($requested): bool {
        return in_array(strtolower($header['name']), $requested, true);
      }));
      unset($message['payload']['parts']);
    }
    return $message;
  }
  throw new RuntimeException('Unexpected Gmail path: ' . $path);
}

for ($number = 0; $number < 50; $number++) {
  $id = sprintf('m%03d', $number);
  $fixtureMessages[$id] = fixtureMessage($id);
  $fixtureFolders['INBOX'][] = $id;
}
$settings = [
  'account_id' => 'gmail-account-one', 'account_type' => 'gmail',
  'items_per_page' => 50, 'email_preview_rows' => 0,
  'show_attachment_pill' => false, 'timezone' => 'UTC'
];
pseGmailHistorySync($settings);

$first = pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($first['messages']) === 50 && count($detailRequests) === 50, 'Initial 50-message page fetches each metadata payload once.');
check(count(array_filter($detailRequests, function (array $request): bool { return $request[2] !== 'metadata'; })) === 0, 'Metadata-only summaries do not load message bodies.');
foreach (['Subject', 'From', 'To', 'Cc', 'Bcc', 'Date', 'Reply-To'] as $header) {
  check(in_array(strtolower($header), array_map('strtolower', $metadataHeaderSets[0]), true), 'Shared Inbox metadata retains ' . $header . '.');
}
$again = pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 50, 'An unchanged Inbox refresh makes no message-detail requests.');
check($again['messages'] === $first['messages'], 'Cached payloads preserve list presentation.');

$fixtureMessages['newmail'] = fixtureMessage('newmail');
array_unshift($fixtureFolders['INBOX'], 'newmail');
$newPage = pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 51 && $newPage['messages'][0]['uid'] === 'newmail', 'One newly arriving email triggers exactly one detail request.');
check($newPage['total'] === 51, 'A new message updates totals without refetching unchanged payloads.');

$fixtureFolders['SENT'] = ['m000'];
$sent = pseGmailMessageList($settings, 'SENT', 1, '');
check(count($detailRequests) === 51, 'A message already cached from Inbox can be rendered in Sent without another metadata request.');
check($sent['messages'][0]['recipientEmail'] === 'jane@example.com, second@example.com', 'Shared metadata preserves recipient names and addresses after a move to Sent.');

$settings['email_preview_rows'] = 1;
$preview = pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 101, 'Enabling previews upgrades all 50 metadata payloads to full exactly once.');
check(strpos($preview['messages'][0]['previewText'], 'Body for newmail') !== false, 'Full payload upgrades retain readable preview bodies.');
pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 101, 'A second preview refresh reuses full payloads.');
$settings['email_preview_rows'] = 0;
pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 101, 'Full payloads also satisfy metadata-only list requests.');

$settings['show_attachment_pill'] = true;
$attachments = pseGmailMessageList($settings, 'INBOX', 1, '');
check(count($detailRequests) === 101, 'Unknown attachment counts reuse already-cached full structures.');
check($attachments['messages'][0]['attachmentCount'] === 1, 'Cached full payloads preserve attachment counts.');
$search = pseGmailMessageList($settings, 'INBOX', 1, 'searchneedle');
check(count($detailRequests) === 101, 'Searching list summaries reuses full payloads.');
check(strpos(implode(' ', $search['messages'][0]['searchContext']), 'searchneedle') !== false, 'Search context is derived from cached full body text.');

$otherAccount = $settings;
$otherAccount['account_id'] = 'gmail-account-two';
pseGmailMessageList($otherAccount, 'SENT', 1, '');
check(count($detailRequests) === 102 && end($detailRequests)[0] === 'gmail-account-two', 'Message caches are isolated between accounts.');

$calendarBeforeDetails = count($detailRequests);
$calendar = pseCachedCalendarMonth($settings, 'INBOX', '2026-10');
check(count($detailRequests) === $calendarBeforeDetails && $calendar['data']['total'] === 51, 'Calendar population reuses all existing list payloads.');
$calendarBeforeLists = $listRequests;
$calendarBeforeHistory = $historyRequests;
$calendarAgain = pseCachedCalendarMonth($settings, 'INBOX', '2026-10', '', '', false, 'all', true);
check($listRequests === $calendarBeforeLists && count($detailRequests) === $calendarBeforeDetails, 'An unchanged forced calendar refresh makes no list or detail requests.');
check($historyRequests === $calendarBeforeHistory + 1 && !empty($calendarAgain['gmailHistorySynced']), 'Forced calendar refresh validates its saved history checkpoint.');
check($calendarAgain['data'] === $calendar['data'], 'Unchanged calendar history preserves message presentation.');
$sentCalendar = pseCachedCalendarMonth($settings, 'SENT', '2026-10');
check(count($detailRequests) === $calendarBeforeDetails && $sentCalendar['data']['days'][0]['emails'][0]['recipientEmail'] === 'jane@example.com, second@example.com', 'Sent calendar reuses globally cached recipient headers.');

$fixtureMessages['calendarnew'] = fixtureMessage('calendarnew');
array_unshift($fixtureFolders['INBOX'], 'calendarnew');
$historyResponse = [
  'historyId' => '101',
  'history' => [[
    'id' => '101',
    'messagesAdded' => [['message' => ['id' => 'calendarnew', 'labelIds' => ['INBOX', 'UNREAD']]]]
  ]]
];
$calendarNew = pseCachedCalendarMonth($settings, 'INBOX', '2026-10', '', '', false, 'all', true);
$historyResponse = ['historyId' => '101'];
check(count($detailRequests) === $calendarBeforeDetails + 1 && $calendarNew['data']['total'] === 52, 'A history-added calendar message fetches only its own metadata.');
check($listRequests === $calendarBeforeLists + 2, 'Only initial Sent calendar and changed Inbox calendar rebuild their lightweight ID lists.');

$historyError = new RuntimeException('Fixture rate limit', 429);
$calendarFailure = pseCachedCalendarMonth($settings, 'INBOX', '2026-10', '', '', false, 'all', true);
check($calendarFailure['data'] === $calendarNew['data'] && $calendarFailure['cache']['refreshError'] === 'Fixture rate limit', 'A failed history request retains the previous calendar with a refresh error.');
check(count($detailRequests) === $calendarBeforeDetails + 1, 'A transient calendar history failure does not refetch or clear message payloads.');

$profileHistoryId = '200';
$historyError = new RuntimeException('Fixture expired checkpoint', 404);
$calendarReset = pseCachedCalendarMonth($settings, 'INBOX', '2026-10', '', '', false, 'all', true);
$historyError = null;
$historyResponse = ['historyId' => '200'];
check($calendarReset['data']['total'] === 52 && count($detailRequests) === $calendarBeforeDetails + 53, 'An expired Gmail checkpoint safely rebuilds the requested calendar and its 52 payloads.');

$raceBeforeLists = $listRequests;
$listMutation = function (array $account): void {
  global $listMutation;
  $listMutation = null;
  pseGmailHistoryMarkMutation($account, [], ['INBOX'], false);
};
$calendarRace = pseCachedCalendarMonth($settings, 'INBOX', '2026-10', 'one-race');
$calendarRaceFile = pseMailCacheCalendarFile($settings, 'INBOX', '2026-10', 'one-race', '', false, 'all');
check($calendarRace['data']['total'] === 52 && $listRequests === $raceBeforeLists + 2, 'A concurrent folder change retries the calendar once before publishing.');
check(pseReadJson($calendarRaceFile)['gmailHistoryRevision'] === pseGmailHistoryRevision($settings, 'INBOX'), 'A retried calendar is written with the current folder generation.');

$listMutation = function (array $account): void {
  pseGmailHistoryMarkMutation($account, [], ['INBOX'], false);
};
$repeatedRaceError = '';
try {
  pseCachedCalendarMonth($settings, 'INBOX', '2026-10', 'repeated-race');
} catch (RuntimeException $error) {
  $repeatedRaceError = $error->getMessage();
}
$listMutation = null;
check(strpos($repeatedRaceError, 'Mailbox changed') !== false, 'Repeated concurrent calendar changes stop with a retry message.');
check(!is_file(pseMailCacheCalendarFile($settings, 'INBOX', '2026-10', 'repeated-race', '', false, 'all')), 'A repeatedly stale calendar is never published to the persistent cache.');

echo 'Gmail message-list cache fixtures passed (' . $checks . " checks).\n";
