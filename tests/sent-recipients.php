<?php
declare(strict_types=1);

// Run without extensions so both Gmail's address-parser fallback and mocked
// IMAP transport can be exercised:
// php -n -d extension=tokenizer tests/sent-recipients.php
if (extension_loaded('imap')) {
  throw new RuntimeException('Run this fixture test with php -n.');
}

$source = file_get_contents(dirname(__DIR__) . '/index.php');
foreach ([
  'pseIsGmailAccount', 'pseGmailHeaders', 'pseMime', 'pseAddressList',
  'pseSenderDisplayParts', 'pseRecipientDisplayParts', 'pseImapRecipientDisplayParts',
  'pseFolderLabel', 'pseFolderIsSent', 'pseGmailMessageList', 'pseMessageList',
  'pseMailCacheListFile', 'pseMailCacheCalendarFile', 'pseCalendarAddMessage',
  'pseNormalizeAttachmentFilter', 'pseNormalizeCalendarDate'
] as $name) {
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

function check(bool $condition, string $description): void
{
  if (!$condition) {
    throw new RuntimeException($description);
  }
}

function pseMailCacheFolderSpecial(array $settings, string $folder): string
{
  return $folder === 'LocalizedOutbox' ? 'sent' : 'folder';
}

function pseMailCacheAccountDirectory(array $settings): string
{
  return '/fixture-cache';
}

function pseFormatDate($value, array $settings): string
{
  return (string)$value;
}

$fixtureHeaders = [
  'Subject' => 'Recipients fixture',
  'From' => 'Account Owner <owner@example.com>',
  'To' => '"Doe, Jane" <jane@example.com>, bob@example.com',
  'Cc' => 'Copies <copy@example.com>',
  'Bcc' => 'Hidden <hidden@example.com>',
  'Date' => 'Tue, 6 Oct 2026 12:00:00 +0000'
];
$gmailMetadataHeaders = [];

function pseGoogleApi(array $settings, string $method, string $path, array $query = []): array
{
  global $fixtureHeaders, $gmailMetadataHeaders;
  if ($path === 'messages') {
    return ['messages' => [['id' => 'abc123']], 'resultSizeEstimate' => 1];
  }
  if (strpos($path, 'labels/') === 0) {
    return ['messagesTotal' => 1, 'messagesUnread' => 0];
  }
  $gmailMetadataHeaders = $query['metadataHeaders'] ?? array_keys($fixtureHeaders);
  $headers = [];
  foreach ($fixtureHeaders as $name => $value) {
    if (in_array($name, $gmailMetadataHeaders, true)) {
      $headers[] = ['name' => $name, 'value' => $value];
    }
  }
  return [
    'payload' => ['headers' => $headers], 'labelIds' => ['SENT'],
    'internalDate' => 1791288000000, 'sizeEstimate' => 1234
  ];
}

// This fixture isolates recipient presentation; persistent cache behavior has
// its own Gmail message-cache fixtures.
function pseGmailCachedMessage(array $settings, string $id, array $query): array
{
  return pseGoogleApi($settings, 'GET', 'messages/' . rawurlencode($id), $query);
}

foreach (['FT_UID', 'SORTDATE', 'SE_UID', 'SA_MESSAGES', 'SA_UNSEEN'] as $index => $name) {
  define($name, $index + 1);
}
$imapHeaderRequests = 0;

function pseOpenImap(array $settings, string $folder, bool $readOnly) { return 'fixture-imap'; }
function pseImapBase(array $settings): string { return '{fixture}'; }
function imap_sort($imap, $sort, $reverse, $flags, $criteria = '', $charset = '') { return [1]; }
function imap_close($imap): bool { return true; }
function imap_status($imap, $mailbox, $flags) { return (object)['messages' => 1, 'unseen' => 0]; }
function imap_fetch_overview($imap, $sequence, $flags): array
{
  global $fixtureHeaders;
  return [(object)[
    'uid' => 1, 'from' => $fixtureHeaders['From'], 'to' => 'jane@example.com',
    'subject' => $fixtureHeaders['Subject'], 'date' => $fixtureHeaders['Date'],
    'udate' => 1791288000, 'size' => 1234, 'seen' => true
  ]];
}
function imap_fetchheader($imap, $uid, $flags): string
{
  global $imapHeaderRequests;
  $imapHeaderRequests++;
  return 'fixture headers';
}
function imap_rfc822_parse_headers($raw)
{
  global $fixtureHeaders;
  return (object)[
    'toaddress' => $fixtureHeaders['To'], 'ccaddress' => $fixtureHeaders['Cc'],
    'bccaddress' => $fixtureHeaders['Bcc']
  ];
}

$settings = [
  'account_type' => 'gmail', 'items_per_page' => 50, 'email_preview_rows' => 0,
  'show_attachment_pill' => false, 'timezone' => 'UTC'
];
$parts = pseRecipientDisplayParts($fixtureHeaders['To'], $fixtureHeaders['Cc'], $fixtureHeaders['Bcc']);
check($parts['recipientName'] === 'Doe, Jane, bob@example.com', 'Quoted comma names and all To recipients must survive parsing.');
check($parts['recipientEmail'] === 'jane@example.com, bob@example.com', 'Recipient addresses must be preserved.');
check($parts['recipientLabel'] === 'Doe, Jane <jane@example.com>, bob@example.com', 'Recipient tooltip must include names and full addresses.');
check(pseRecipientDisplayParts('', $fixtureHeaders['Cc'])['recipientEmail'] === 'copy@example.com', 'Cc-only message must show its recipient.');
check(pseRecipientDisplayParts('undisclosed-recipients:;', '', $fixtureHeaders['Bcc'])['recipientEmail'] === 'hidden@example.com', 'Bcc-only message must show its recipient.');
check(pseRecipientDisplayParts('')['recipientName'] === '(Unknown recipient)', 'Missing recipients must never fall back to the sender.');

$sent = pseMessageList($settings, 'SENT', 1, '');
check($sent['messages'][0]['fromEmail'] === 'owner@example.com', 'Actual sender must remain intact for reply and sender filtering.');
check($sent['messages'][0]['recipientEmail'] === 'jane@example.com, bob@example.com', 'Gmail Sent summary must include all recipients.');
check(in_array('Bcc', $gmailMetadataHeaders, true), 'Gmail metadata must request recipient headers.');
check($sent['recipientSchema'] === 1, 'Saved Sent searches must identify upgraded recipient summaries.');
$inbox = pseMessageList($settings, 'INBOX', 1, '');
check(!isset($inbox['messages'][0]['recipientName']), 'Inbox summary must retain sender display behavior.');

$settings['account_type'] = 'imap';
$sent = pseMessageList($settings, 'LocalizedOutbox', 1, '');
check($sent['messages'][0]['recipientEmail'] === 'jane@example.com, bob@example.com', 'IMAP Sent must use full headers rather than the first overview recipient.');
check($sent['messages'][0]['fromEmail'] === 'owner@example.com', 'IMAP sender must remain available.');
$headerRequests = $imapHeaderRequests;
pseMessageList($settings, 'INBOX', 1, '');
check($imapHeaderRequests === $headerRequests, 'Inbox listing must not add recipient-header requests.');

$legacyInboxIdentity = implode("\0", ['INBOX', '1', '', '', '0', 'desc', 'all', '', '50', '']);
$legacySentIdentity = implode("\0", ['LocalizedOutbox', '1', '', '', '0', 'desc', 'all', '', '50', '']);
check(pseMailCacheListFile($settings, 'INBOX', 1, '', '', false) === '/fixture-cache/lists/' . hash('sha256', $legacyInboxIdentity) . '.json', 'Existing Inbox caches must keep their identity.');
check(pseMailCacheListFile($settings, 'LocalizedOutbox', 1, '', '', false) !== '/fixture-cache/lists/' . hash('sha256', $legacySentIdentity) . '.json', 'Legacy Sent summaries must be refreshed after upgrading.');
$legacyCalendarIdentity = implode("\0", ['LocalizedOutbox', '2026-10', '', '', '0', 'all', 'UTC']);
check(pseMailCacheCalendarFile($settings, 'LocalizedOutbox', '2026-10', '', '', false, 'all') !== '/fixture-cache/calendars/' . hash('sha256', $legacyCalendarIdentity) . '.json', 'Legacy Sent calendar summaries must be refreshed.');

$days = [];
pseCalendarAddMessage($days, '2026-10-06', '1', 'Account Owner', 'owner@example.com', 'Fixture', 1791288000, $parts);
check($days['2026-10-06']['emails'][0]['recipientName'] === $parts['recipientName'], 'Sent calendar must include recipient presentation fields.');
check(isset($days['2026-10-06']['_senders']['owner@example.com']), 'Calendar sender counts must keep their original meaning.');
echo "Sent recipient fixtures passed.\n";
