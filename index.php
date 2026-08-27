<?php
/*
 * PSE Email (PSE), release v2.17.22
 * Single-file PHP email client with IMAP/SMTP and Google OAuth2/Gmail API accounts.
 * Includes EML/TXT/Word/PDF/image exports, read-time contact suggestions and lazy attachments.
 *
 * Requirements:
 * - PHP 7.4+
 * - PHP extensions: openssl, json; imap for regular IMAP accounts; zip for Download all
 * - cURL or HTTPS URL access for Gmail OAuth2/API accounts
 * - HTTPS is strongly recommended
 *
 * The application stores protected settings, caches and drafts in "pse_data" next to this file.
 */

declare(strict_types=1);

const PSE_NAME = 'PSE Email';
const PSE_VERSION = '2.17.22';
const PSE_DATA_DIR = __DIR__ . '/pse_data';
const PSE_SETTINGS_FILE = PSE_DATA_DIR . '/settings.json';
const PSE_CONTACTS_FILE = PSE_DATA_DIR . '/contacts.json';
const PSE_ACTION_QUEUE_FILE = PSE_DATA_DIR . '/action_queue.json';
const PSE_SAVED_DIR = PSE_DATA_DIR . '/saved';
const PSE_CACHE_DIR = PSE_DATA_DIR . '/cache';
const PSE_MAIL_CACHE_DIR = PSE_DATA_DIR . '/mail_cache';
const PSE_UPLOAD_DIR = PSE_DATA_DIR . '/uploads';
const PSE_REMOTE_IMAGE_MAX_BYTES = 10485760;
const PSE_COOKIE = 'pse_auth';
const PSE_COOKIE_YEARS = 10;
const PSE_MAX_ATTACHMENT_BYTES = 15728640;
const PSE_MAX_INLINE_IMAGE_BYTES = 5242880;
const PSE_LARGE_MESSAGE_BYTES = 2097152;
const PSE_PREFETCH_MAX_MESSAGE_BYTES = 8388608;
const PSE_ATTACHMENT_CHUNK_BYTES = 524288;
const PSE_ATTACHMENT_UPLOAD_EXPIRE_SECONDS = 86400;
const PSE_UPDATE_REPOSITORY = 'ziobit/PSE-Email-Client';
const PSE_UPDATE_BRANCH = 'main';
const PSE_UPDATE_CACHE_SECONDS = 900;

class PseGoogleReconnectRequiredException extends RuntimeException
{
}

function pseDefaults(): array
{
  return [
    'initialized' => false,
    'password_hash' => '',
    'auth_tokens' => [],
    'storage_key' => '',
    'active_account_id' => '',
    'accounts' => [],
    'account_id' => '',
    'account_name' => 'Account 1',
    'account_type' => 'imap',
    'imap_host' => 'imap.gmail.com',
    'imap_port' => 993,
    'imap_encryption' => 'ssl',
    'imap_validate_cert' => true,
    'imap_username' => '',
    'imap_password_enc' => '',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_validate_cert' => true,
    'smtp_username' => '',
    'smtp_password_enc' => '',
    'save_sent_via_imap' => true,
    'from_email' => '',
    'from_name' => '',
    'reply_to' => '',
    'signature' => '',
    'google_client_id' => '',
    'google_client_secret_enc' => '',
    'google_refresh_token_enc' => '',
    'google_access_token_enc' => '',
    'google_access_token_expires_at' => 0,
    'google_oauth_email' => '',
    'google_oauth_pending_hash' => '',
    'google_oauth_pending_expires_at' => 0,
    'google_oauth_reconnect_required' => false,
    'date_format' => 'd-m-Y',
    'time_format' => 'H:i',
    'smart_datetime' => false,
    'group_messages_by_day' => false,
    'email_preview_rows' => 0,
    'show_attachment_pill' => false,
    'show_list_trash' => false,
    'show_list_size' => false,
    'show_calendar' => false,
    'hide_useless_gmail_folders' => true,
    'timezone' => 'Asia/Bangkok',
    'density' => 'medium',
    'theme' => 'custom',
    'primary_color' => '#1769aa',
    'accent_color' => '#0d6efd',
    'background_color' => '#f3f5f8',
    'panel_color' => '#ffffff',
    'items_per_page' => 50,
    'search_delay_seconds' => 2,
    'block_remote_images' => true,
    'always_load_remote_images' => false,
    'show_image_attachments_inline' => true,
    'suggest_unknown_read_contacts' => true,
    'confirm_delete_messages' => true,
    'compose_save_drafts' => false,
    'mobile_single_pane' => true,
    'mobile_swipe_hint_seconds' => 2,
    'auto_update' => false,
    'app_title' => 'PSE Email'
  ];
}

function pseThemes(): array
{
  return [
    'alpine' => [
      'name' => 'Alpine Air',
      'mode' => 'light',
      'primary_color' => '#1769aa',
      'accent_color' => '#0d6efd',
      'background_color' => '#eef4f8',
      'panel_color' => '#ffffff'
    ],
    'sandstone' => [
      'name' => 'Sandstone',
      'mode' => 'light',
      'primary_color' => '#9a5b24',
      'accent_color' => '#d1843e',
      'background_color' => '#f7f0e6',
      'panel_color' => '#fffaf2'
    ],
    'lavender' => [
      'name' => 'Lavender Mist',
      'mode' => 'light',
      'primary_color' => '#6552a3',
      'accent_color' => '#8b73d6',
      'background_color' => '#f2effa',
      'panel_color' => '#ffffff'
    ],
    'mint' => [
      'name' => 'Mint Desk',
      'mode' => 'light',
      'primary_color' => '#18745c',
      'accent_color' => '#20a47a',
      'background_color' => '#edf8f3',
      'panel_color' => '#ffffff'
    ],
    'rosepaper' => [
      'name' => 'Rose Paper',
      'mode' => 'light',
      'primary_color' => '#a24162',
      'accent_color' => '#d15d82',
      'background_color' => '#faf0f3',
      'panel_color' => '#fffafb'
    ],
    'midnight' => [
      'name' => 'Midnight Blue',
      'mode' => 'dark',
      'primary_color' => '#315f9b',
      'accent_color' => '#5c9ded',
      'background_color' => '#0d1523',
      'panel_color' => '#162236'
    ],
    'graphite' => [
      'name' => 'Graphite',
      'mode' => 'dark',
      'primary_color' => '#3f4752',
      'accent_color' => '#9aa7b6',
      'background_color' => '#15181d',
      'panel_color' => '#22262d'
    ],
    'oceanic' => [
      'name' => 'Oceanic',
      'mode' => 'dark',
      'primary_color' => '#075985',
      'accent_color' => '#22b8cf',
      'background_color' => '#071b24',
      'panel_color' => '#0e2a35'
    ],
    'aubergine' => [
      'name' => 'Aubergine',
      'mode' => 'dark',
      'primary_color' => '#69416f',
      'accent_color' => '#c084cf',
      'background_color' => '#1c1320',
      'panel_color' => '#2c1e31'
    ],
    'forestnight' => [
      'name' => 'Forest Night',
      'mode' => 'dark',
      'primary_color' => '#28604c',
      'accent_color' => '#54b689',
      'background_color' => '#0d1b16',
      'panel_color' => '#172a22'
    ]
  ];
}

function pseThemeIsDark(array $settings): bool
{
  $theme = (string)($settings['theme'] ?? 'custom');
  $themes = pseThemes();
  if (isset($themes[$theme])) {
    return $themes[$theme]['mode'] === 'dark';
  }
  $hex = ltrim((string)($settings['background_color'] ?? '#ffffff'), '#');
  if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
    return false;
  }
  $red = hexdec(substr($hex, 0, 2));
  $green = hexdec(substr($hex, 2, 2));
  $blue = hexdec(substr($hex, 4, 2));
  return (($red * 299 + $green * 587 + $blue * 114) / 1000) < 128;
}

function pseAccountSettingKeys(): array
{
  return [
    'account_type',
    'imap_host', 'imap_port', 'imap_encryption', 'imap_validate_cert', 'imap_username',
    'imap_password_enc', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_validate_cert',
    'smtp_username', 'smtp_password_enc', 'save_sent_via_imap', 'from_email', 'from_name',
    'reply_to', 'signature', 'google_client_id', 'google_client_secret_enc',
    'google_refresh_token_enc', 'google_access_token_enc', 'google_access_token_expires_at',
    'google_oauth_email', 'google_oauth_pending_hash', 'google_oauth_pending_expires_at',
    'google_oauth_reconnect_required',
    'date_format', 'time_format', 'smart_datetime', 'group_messages_by_day',
    'email_preview_rows', 'show_attachment_pill', 'show_list_trash', 'show_list_size', 'show_calendar',
    'hide_useless_gmail_folders', 'timezone',
    'density', 'theme', 'primary_color', 'accent_color', 'background_color', 'panel_color',
    'items_per_page', 'search_delay_seconds', 'block_remote_images',
    'always_load_remote_images', 'show_image_attachments_inline',
    'suggest_unknown_read_contacts', 'confirm_delete_messages', 'compose_save_drafts', 'mobile_single_pane', 'mobile_swipe_hint_seconds', 'app_title'
  ];
}

function pseAccountDefaults(): array
{
  $defaults = pseDefaults();
  $account = [
    'id' => '',
    'name' => 'Account 1'
  ];
  foreach (pseAccountSettingKeys() as $key) {
    $account[$key] = $defaults[$key];
  }
  return $account;
}

function pseSafeAccountId(string $id): string
{
  return preg_match('/^[a-zA-Z0-9_-]{4,80}$/', $id) ? $id : '';
}

function pseNewAccountId(): string
{
  return 'account_' . bin2hex(random_bytes(8));
}

function pseNormalizeLegacyAppTitle(string $title): string
{
  $title = trim($title);
  $legacyHashes = [
    '50e2ea2673329f080bba1b23624fc4cd180c5a67d069953cf12eea3a43d859c0',
    'd3cd74924767a5f0b1df4f6791289f0c30f33b2210f38e747bc4271c8d8e8b31'
  ];
  if ($title === '' || in_array(hash('sha256', $title), $legacyHashes, true)) {
    return 'PSE Email';
  }
  return $title;
}

function pseNormalizeSettings(array $settings): array
{
  $defaults = pseDefaults();
  $settings = array_replace($defaults, $settings);
  $settings['app_title'] = pseNormalizeLegacyAppTitle((string)($settings['app_title'] ?? ''));
  $incomingAccounts = isset($settings['accounts']) && is_array($settings['accounts'])
    ? $settings['accounts']
    : [];
  $accounts = [];

  foreach ($incomingAccounts as $key => $incoming) {
    if (!is_array($incoming)) {
      continue;
    }
    $id = pseSafeAccountId((string)($incoming['id'] ?? $key));
    if ($id === '' || isset($accounts[$id])) {
      $id = pseNewAccountId();
    }
    $account = array_replace(pseAccountDefaults(), $incoming);
    $account['app_title'] = pseNormalizeLegacyAppTitle((string)($account['app_title'] ?? ''));
    $account['id'] = $id;
    $account['name'] = trim((string)($account['name'] ?? ''));
    if ($account['name'] === '') {
      $account['name'] = 'Account ' . (count($accounts) + 1);
    }
    $accounts[$id] = $account;
  }

  if (empty($accounts)) {
    $idSeed = (string)($settings['imap_username'] ?? '') . '|' . (string)($settings['storage_key'] ?? '');
    $id = 'account_' . substr(hash('sha256', $idSeed), 0, 16);
    $account = pseAccountDefaults();
    foreach (pseAccountSettingKeys() as $key) {
      if (array_key_exists($key, $settings)) {
        $account[$key] = $settings[$key];
      }
    }
    $legacyName = trim((string)($settings['account_name'] ?? ''));
    if ($legacyName === '' || $legacyName === 'Account 1') {
      $legacyName = trim((string)($settings['imap_username'] ?? ''));
    }
    $account['app_title'] = pseNormalizeLegacyAppTitle((string)($account['app_title'] ?? ''));
    $account['id'] = $id;
    $account['name'] = $legacyName !== '' ? $legacyName : 'Account 1';
    $accounts[$id] = $account;
  }

  $activeId = pseSafeAccountId((string)($settings['active_account_id'] ?? ''));
  if ($activeId === '' || !isset($accounts[$activeId])) {
    $activeId = (string)array_key_first($accounts);
  }
  $active = $accounts[$activeId];
  foreach (pseAccountSettingKeys() as $key) {
    $settings[$key] = $active[$key];
  }
  $settings['accounts'] = $accounts;
  $settings['active_account_id'] = $activeId;
  $settings['account_id'] = $activeId;
  $settings['account_name'] = (string)$active['name'];
  return $settings;
}

function pseWriteSettings(array $settings): array
{
  $incoming = $settings;
  $settings = pseNormalizeSettings($settings);
  foreach (pseAccountSettingKeys() as $key) {
    if (array_key_exists($key, $incoming)) {
      $settings[$key] = $incoming[$key];
    }
  }
  if (array_key_exists('account_name', $incoming)) {
    $settings['account_name'] = (string)$incoming['account_name'];
  }
  $activeId = (string)$settings['active_account_id'];
  $account = $settings['accounts'][$activeId];
  foreach (pseAccountSettingKeys() as $key) {
    $account[$key] = $settings[$key];
  }
  $account['id'] = $activeId;
  $account['name'] = trim((string)$settings['account_name']) ?: 'Account 1';
  $settings['accounts'][$activeId] = $account;
  pseWriteJson(PSE_SETTINGS_FILE, $settings);
  return $settings;
}

function psePatchGoogleOAuthAccount(string $accountId, callable $callback): array
{
  $accountId = pseSafeAccountId($accountId);
  if ($accountId === '') {
    throw new RuntimeException('Invalid Google account identifier.');
  }
  pseEnsureStorage();
  $handle = @fopen(PSE_SETTINGS_FILE . '.lock', 'c+');
  if (!$handle) {
    throw new RuntimeException('Unable to lock Google account settings.');
  }
  try {
    if (!@flock($handle, LOCK_EX)) {
      throw new RuntimeException('Unable to lock Google account settings.');
    }
    $settings = pseNormalizeSettings(pseReadJson(PSE_SETTINGS_FILE));
    if (!isset($settings['accounts'][$accountId])) {
      throw new RuntimeException('Google email account not found.');
    }
    $account = $callback($settings['accounts'][$accountId], $settings);
    if (!is_array($account)) {
      throw new RuntimeException('Invalid Google account settings update.');
    }
    $account = array_replace($settings['accounts'][$accountId], $account);
    $account['id'] = $accountId;
    $account['name'] = trim((string)($account['name'] ?? '')) ?: 'Account 1';
    $settings['accounts'][$accountId] = $account;
    if ((string)$settings['active_account_id'] === $accountId) {
      foreach (pseAccountSettingKeys() as $key) {
        $settings[$key] = $account[$key];
      }
      $settings['account_id'] = $accountId;
      $settings['account_name'] = (string)$account['name'];
    }
    pseWriteJson(PSE_SETTINGS_FILE, $settings);

    foreach (pseAccountSettingKeys() as $key) {
      $settings[$key] = $account[$key];
    }
    $settings['active_account_id'] = $accountId;
    $settings['account_id'] = $accountId;
    $settings['account_name'] = (string)$account['name'];
    return $settings;
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

function pseEnsureStorage(): void
{
  if (!is_dir(PSE_DATA_DIR) && !@mkdir(PSE_DATA_DIR, 0750, true) && !is_dir(PSE_DATA_DIR)) {
    throw new RuntimeException('Cannot create the pse_data directory. Check web-server write permissions.');
  }
  if (!is_dir(PSE_SAVED_DIR) && !@mkdir(PSE_SAVED_DIR, 0750, true) && !is_dir(PSE_SAVED_DIR)) {
    throw new RuntimeException('Cannot create the saved-email directory.');
  }
  if (!is_dir(PSE_CACHE_DIR) && !@mkdir(PSE_CACHE_DIR, 0750, true) && !is_dir(PSE_CACHE_DIR)) {
    throw new RuntimeException('Cannot create the attachment-cache directory.');
  }
  if (!is_dir(PSE_MAIL_CACHE_DIR) && !@mkdir(PSE_MAIL_CACHE_DIR, 0750, true) && !is_dir(PSE_MAIL_CACHE_DIR)) {
    throw new RuntimeException('Cannot create the persistent mailbox-cache directory.');
  }
  if (!is_dir(PSE_UPLOAD_DIR) && !@mkdir(PSE_UPLOAD_DIR, 0750, true) && !is_dir(PSE_UPLOAD_DIR)) {
    throw new RuntimeException('Cannot create the temporary attachment-upload directory.');
  }

  $htaccess = PSE_DATA_DIR . '/.htaccess';
  if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
  }
  $webConfig = PSE_DATA_DIR . '/web.config';
  if (!file_exists($webConfig)) {
    @file_put_contents(
      $webConfig,
      "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>"
    );
  }
  $index = PSE_DATA_DIR . '/index.html';
  if (!file_exists($index)) {
    @file_put_contents($index, '');
  }
}

function pseReadJson(string $file, array $fallback = []): array
{
  if (!is_file($file)) {
    return $fallback;
  }
  $raw = @file_get_contents($file);
  if ($raw === false || trim($raw) === '') {
    return $fallback;
  }
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : $fallback;
}

function pseWriteJson(string $file, array $data): void
{
  pseEnsureStorage();
  $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
  if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
  }
  $json = json_encode($data, $flags);
  if ($json === false) {
    throw new RuntimeException('Unable to encode application data: ' . json_last_error_msg());
  }
  $tmp = $file . '.tmp.' . bin2hex(random_bytes(5));
  if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write ' . basename($file) . '.');
  }
  @chmod($tmp, 0640);
  if (!@rename($tmp, $file)) {
    @unlink($tmp);
    throw new RuntimeException('Unable to finalize ' . basename($file) . '.');
  }
}


function pseAttachmentUploadSafeId(string $uploadId): string
{
  return preg_match('/^[a-f0-9]{32}$/', $uploadId) ? $uploadId : '';
}

function pseAttachmentUploadDirectory(string $uploadId): string
{
  $uploadId = pseAttachmentUploadSafeId($uploadId);
  if ($uploadId === '') {
    throw new RuntimeException('Invalid temporary attachment identifier.');
  }
  return PSE_UPLOAD_DIR . '/' . $uploadId;
}

function pseAttachmentUploadManifestFile(string $uploadId): string
{
  return pseAttachmentUploadDirectory($uploadId) . '/manifest.json';
}

function pseDeleteDirectoryTree(string $directory): void
{
  if (!is_dir($directory)) {
    return;
  }
  $items = @scandir($directory);
  if (is_array($items)) {
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $path = $directory . '/' . $item;
      if (is_dir($path)) {
        pseDeleteDirectoryTree($path);
      } else {
        @unlink($path);
      }
    }
  }
  @rmdir($directory);
}

function pseCleanupExpiredAttachmentUploads(): void
{
  if (!is_dir(PSE_UPLOAD_DIR)) {
    return;
  }
  $cutoff = time() - PSE_ATTACHMENT_UPLOAD_EXPIRE_SECONDS;
  foreach (glob(PSE_UPLOAD_DIR . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
    $manifestFile = $directory . '/manifest.json';
    $updatedAt = 0;
    if (is_file($manifestFile)) {
      $manifest = pseReadJson($manifestFile, []);
      $updatedAt = (int)($manifest['updatedAt'] ?? $manifest['createdAt'] ?? 0);
    }
    if ($updatedAt <= 0) {
      $modified = @filemtime($directory);
      $updatedAt = $modified === false ? 0 : (int)$modified;
    }
    if ($updatedAt > 0 && $updatedAt < $cutoff) {
      pseDeleteDirectoryTree($directory);
    }
  }
}

function pseAttachmentUploadExpectedChunks(int $size): int
{
  return max(1, (int)ceil(max(0, $size) / PSE_ATTACHMENT_CHUNK_BYTES));
}

function pseAttachmentUploadExpectedChunkSize(int $size, int $index): int
{
  $offset = $index * PSE_ATTACHMENT_CHUNK_BYTES;
  return max(0, min(PSE_ATTACHMENT_CHUNK_BYTES, $size - $offset));
}

function pseAttachmentUploadReceivedChunks(array $manifest): array
{
  $uploadId = pseAttachmentUploadSafeId((string)($manifest['uploadId'] ?? ''));
  if ($uploadId === '') {
    return [];
  }
  $directory = pseAttachmentUploadDirectory($uploadId);
  $size = max(0, (int)($manifest['size'] ?? 0));
  $totalChunks = max(1, (int)($manifest['totalChunks'] ?? 1));
  $received = [];
  for ($index = 0; $index < $totalChunks; $index++) {
    $part = $directory . '/' . sprintf('%06d.part', $index);
    if (!is_file($part)) {
      continue;
    }
    $partSize = @filesize($part);
    if ($partSize !== false && (int)$partSize === pseAttachmentUploadExpectedChunkSize($size, $index)) {
      $received[] = $index;
    }
  }
  return $received;
}

function pseAttachmentUploadManifest(array $settings, string $uploadId, bool $requireComplete = false): array
{
  $uploadId = pseAttachmentUploadSafeId($uploadId);
  if ($uploadId === '') {
    throw new RuntimeException('Invalid temporary attachment identifier.');
  }
  $manifest = pseReadJson(pseAttachmentUploadManifestFile($uploadId), []);
  if (empty($manifest) || (string)($manifest['uploadId'] ?? '') !== $uploadId) {
    throw new RuntimeException('Temporary attachment upload was not found or has expired.');
  }
  if ((string)($manifest['accountId'] ?? '') !== (string)($settings['account_id'] ?? '')) {
    throw new RuntimeException('Temporary attachment upload belongs to another account.');
  }
  if ($requireComplete && empty($manifest['complete'])) {
    throw new RuntimeException('An attachment upload is incomplete. Finish uploading it before sending.');
  }
  return $manifest;
}

function pseInitAttachmentUpload(array $settings, array $data): array
{
  pseEnsureStorage();
  $name = basename(trim((string)($data['name'] ?? 'attachment.bin')));
  if ($name === '' || $name === '.' || $name === '..') {
    $name = 'attachment.bin';
  }
  $name = substr($name, 0, 240);
  $mime = preg_replace('/[^a-z0-9.+\-\/]/i', '', (string)($data['type'] ?? 'application/octet-stream'));
  $mime = $mime !== '' ? $mime : 'application/octet-stream';
  $size = (int)($data['size'] ?? -1);
  if ($size < 0 || $size > PSE_MAX_ATTACHMENT_BYTES) {
    throw new RuntimeException('Attachment size is invalid or exceeds the 15 MB application limit.');
  }
  $sha256 = strtolower(trim((string)($data['sha256'] ?? '')));
  if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
    throw new RuntimeException('A valid SHA-256 checksum is required for attachment uploads.');
  }
  $totalChunks = (int)($data['totalChunks'] ?? 0);
  $expectedChunks = pseAttachmentUploadExpectedChunks($size);
  if ($totalChunks !== $expectedChunks) {
    throw new RuntimeException('Attachment chunk count does not match its size.');
  }

  $resumeId = pseAttachmentUploadSafeId((string)($data['uploadId'] ?? ''));
  if ($resumeId !== '' && is_file(pseAttachmentUploadManifestFile($resumeId))) {
    $existing = pseAttachmentUploadManifest($settings, $resumeId, false);
    if (
      (string)($existing['name'] ?? '') === $name &&
      (string)($existing['type'] ?? '') === $mime &&
      (int)($existing['size'] ?? -1) === $size &&
      (int)($existing['totalChunks'] ?? 0) === $totalChunks &&
      hash_equals((string)($existing['sha256'] ?? ''), $sha256)
    ) {
      return [
        'uploadId' => $resumeId,
        'received' => pseAttachmentUploadReceivedChunks($existing),
        'complete' => !empty($existing['complete'])
      ];
    }
  }

  $uploadId = bin2hex(random_bytes(16));
  $directory = pseAttachmentUploadDirectory($uploadId);
  if (!@mkdir($directory, 0750, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create a temporary attachment upload.');
  }
  $now = time();
  $manifest = [
    'format' => 'PSE-UPLOAD/1',
    'uploadId' => $uploadId,
    'accountId' => (string)($settings['account_id'] ?? ''),
    'name' => $name,
    'type' => $mime,
    'size' => $size,
    'sha256' => $sha256,
    'totalChunks' => $totalChunks,
    'complete' => false,
    'createdAt' => $now,
    'updatedAt' => $now
  ];
  pseWriteJson($directory . '/manifest.json', $manifest);
  return ['uploadId' => $uploadId, 'received' => [], 'complete' => false];
}

function pseStoreAttachmentChunk(array $settings, array $data): array
{
  $uploadId = pseAttachmentUploadSafeId((string)($data['uploadId'] ?? ''));
  $manifest = pseAttachmentUploadManifest($settings, $uploadId, false);
  if (!empty($manifest['complete'])) {
    return [
      'uploadId' => $uploadId,
      'received' => pseAttachmentUploadReceivedChunks($manifest),
      'complete' => true
    ];
  }
  $index = (int)($data['index'] ?? -1);
  $totalChunks = (int)($manifest['totalChunks'] ?? 0);
  if ($index < 0 || $index >= $totalChunks) {
    throw new RuntimeException('Invalid attachment chunk number.');
  }
  if (!isset($_FILES['chunk']) || !is_array($_FILES['chunk'])) {
    throw new RuntimeException('Attachment chunk data is missing.');
  }
  $uploadError = (int)($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($uploadError !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Attachment chunk upload failed with code ' . $uploadError . '.');
  }
  $tmp = (string)($_FILES['chunk']['tmp_name'] ?? '');
  if ($tmp === '' || !is_file($tmp)) {
    throw new RuntimeException('Temporary attachment chunk is unavailable.');
  }
  $expectedSize = pseAttachmentUploadExpectedChunkSize((int)$manifest['size'], $index);
  $actualSize = @filesize($tmp);
  if ($actualSize === false || (int)$actualSize !== $expectedSize) {
    throw new RuntimeException('Attachment chunk size is incorrect.');
  }
  $content = @file_get_contents($tmp);
  if (!is_string($content) || strlen($content) !== $expectedSize) {
    throw new RuntimeException('Unable to read the uploaded attachment chunk.');
  }
  $part = pseAttachmentUploadDirectory($uploadId) . '/' . sprintf('%06d.part', $index);
  if (@file_put_contents($part, $content, LOCK_EX) === false) {
    throw new RuntimeException('Unable to store the uploaded attachment chunk.');
  }
  @chmod($part, 0640);
  $manifest['updatedAt'] = time();
  pseWriteJson(pseAttachmentUploadManifestFile($uploadId), $manifest);
  return [
    'uploadId' => $uploadId,
    'received' => pseAttachmentUploadReceivedChunks($manifest),
    'complete' => false
  ];
}

function pseFinalizeAttachmentUpload(array $settings, string $uploadId): array
{
  $manifest = pseAttachmentUploadManifest($settings, $uploadId, false);
  if (!empty($manifest['complete'])) {
    return $manifest;
  }
  $received = pseAttachmentUploadReceivedChunks($manifest);
  $totalChunks = (int)$manifest['totalChunks'];
  if (count($received) !== $totalChunks) {
    throw new RuntimeException(
      'Attachment upload is incomplete: received ' . count($received) . ' of ' . $totalChunks . ' chunks.'
    );
  }

  $hash = hash_init('sha256');
  $totalBytes = 0;
  $directory = pseAttachmentUploadDirectory($uploadId);
  for ($index = 0; $index < $totalChunks; $index++) {
    $part = $directory . '/' . sprintf('%06d.part', $index);
    $content = @file_get_contents($part);
    if (!is_string($content)) {
      throw new RuntimeException('Unable to verify an uploaded attachment chunk.');
    }
    $expectedSize = pseAttachmentUploadExpectedChunkSize((int)$manifest['size'], $index);
    if (strlen($content) !== $expectedSize) {
      throw new RuntimeException('An uploaded attachment chunk changed size during verification.');
    }
    $totalBytes += strlen($content);
    hash_update($hash, $content);
  }
  if ($totalBytes !== (int)$manifest['size']) {
    throw new RuntimeException('Uploaded attachment size verification failed.');
  }
  $actualHash = hash_final($hash);
  if (!hash_equals((string)$manifest['sha256'], $actualHash)) {
    pseDeleteDirectoryTree($directory);
    throw new RuntimeException('Uploaded attachment SHA-256 verification failed. Please attach the file again.');
  }
  $manifest['complete'] = true;
  $manifest['completedAt'] = time();
  $manifest['updatedAt'] = time();
  pseWriteJson(pseAttachmentUploadManifestFile($uploadId), $manifest);
  return $manifest;
}

function pseUploadedAttachmentBase64(array $settings, string $uploadId): string
{
  $manifest = pseAttachmentUploadManifest($settings, $uploadId, true);
  $directory = pseAttachmentUploadDirectory($uploadId);
  $totalChunks = (int)$manifest['totalChunks'];
  $encoded = '';
  $carry = '';
  for ($index = 0; $index < $totalChunks; $index++) {
    $part = $directory . '/' . sprintf('%06d.part', $index);
    $content = @file_get_contents($part);
    if (!is_string($content)) {
      throw new RuntimeException('A completed temporary attachment is missing data.');
    }
    $binary = $carry . $content;
    $usable = strlen($binary) - (strlen($binary) % 3);
    if ($usable > 0) {
      $encoded .= chunk_split(base64_encode(substr($binary, 0, $usable)));
    }
    $carry = $usable < strlen($binary) ? substr($binary, $usable) : '';
  }
  if ($carry !== '') {
    $encoded .= chunk_split(base64_encode($carry));
  }
  return $encoded;
}

function pseCleanupMessageAttachmentUploads(array $settings, array $data): void
{
  foreach ((array)($data['attachments'] ?? []) as $attachment) {
    if (!is_array($attachment)) {
      continue;
    }
    $uploadId = pseAttachmentUploadSafeId((string)($attachment['uploadId'] ?? ''));
    if ($uploadId === '') {
      continue;
    }
    try {
      pseAttachmentUploadManifest($settings, $uploadId, false);
      pseDeleteDirectoryTree(pseAttachmentUploadDirectory($uploadId));
    } catch (Throwable $error) {
      // Cleanup is best-effort after a successful send.
    }
  }
}

function pseEnsureDirectory(string $directory): void
{
  if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create cache directory ' . basename($directory) . '.');
  }
}

function pseMailCacheAccountDirectory(array $settings): string
{
  $accountId = pseSafeAccountId((string)($settings['account_id'] ?? ''));
  if ($accountId === '') {
    $accountId = 'account_' . substr(hash('sha256', (string)($settings['imap_username'] ?? '')), 0, 16);
  }
  $directory = PSE_MAIL_CACHE_DIR . '/' . $accountId;
  pseEnsureDirectory($directory);
  foreach (['lists', 'messages', 'rendered', 'indexes', 'calendars'] as $child) {
    pseEnsureDirectory($directory . '/' . $child);
  }
  return $directory;
}

function pseMailCacheEnvelopeRead(string $file): array
{
  $envelope = pseReadJson($file, []);
  if (!isset($envelope['data']) || !is_array($envelope['data'])) {
    return [];
  }
  return $envelope;
}

function pseMailCacheEnvelopeWrite(string $file, array $data, array $meta = []): array
{
  $savedAt = time();
  $existing = pseReadJson($file, []);
  $freshFromServer = !empty($meta['freshFromServer']);
  $inheritedServerSyncedAt = (int)($meta['serverSyncedAt'] ?? 0);
  unset($meta['freshFromServer'], $meta['serverSyncedAt']);
  $serverSyncedAt = $freshFromServer
    ? $savedAt
    : ($inheritedServerSyncedAt > 0
      ? $inheritedServerSyncedAt
      : (int)($existing['serverSyncedAt'] ?? $existing['savedAt'] ?? $savedAt));
  $envelope = array_merge($meta, [
    'savedAt' => $savedAt,
    'savedAtIso' => gmdate('c', $savedAt),
    'serverSyncedAt' => $serverSyncedAt,
    'serverSyncedAtIso' => gmdate('c', $serverSyncedAt),
    'data' => $data
  ]);
  pseWriteJson($file, $envelope);
  return $envelope;
}

function pseMailCacheInfo(array $envelope, bool $cached): array
{
  $serverSyncedAt = (int)($envelope['serverSyncedAt'] ?? $envelope['savedAt'] ?? 0);
  return [
    'cached' => $cached,
    'savedAt' => $serverSyncedAt,
    'savedAtIso' => $serverSyncedAt > 0 ? gmdate('c', $serverSyncedAt) : ''
  ];
}

function pseMailCacheFoldersFile(array $settings): string
{
  $filename = pseIsGmailAccount($settings) && !empty($settings['hide_useless_gmail_folders'])
    ? 'folders-gmail-filtered.json'
    : 'folders.json';
  return pseMailCacheAccountDirectory($settings) . '/' . $filename;
}

function pseMailCacheListFile(
  array $settings,
  string $folder,
  int $page,
  string $search,
  string $senderFilter,
  bool $unreadOnly,
  string $sortOrder = 'desc',
  string $attachmentFilter = 'all',
  string $startDate = ''
): string {
  $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $startDate = pseNormalizeCalendarDate($startDate);
  $identity = implode("\0", [
    $folder,
    (string)$page,
    $search,
    strtolower(trim($senderFilter)),
    $unreadOnly ? '1' : '0',
    $sortOrder,
    $attachmentFilter,
    $startDate,
    (string)($settings['items_per_page'] ?? 50)
  ]);
  return pseMailCacheAccountDirectory($settings) . '/lists/' . hash('sha256', $identity) . '.json';
}

function pseMailCacheCalendarFile(
  array $settings,
  string $folder,
  string $month,
  string $search,
  string $senderFilter,
  bool $unreadOnly,
  string $attachmentFilter
): string {
  $identity = implode("\0", [
    $folder,
    $month,
    $search,
    strtolower(trim($senderFilter)),
    $unreadOnly ? '1' : '0',
    pseNormalizeAttachmentFilter($attachmentFilter),
    (string)($settings['timezone'] ?? 'UTC')
  ]);
  return pseMailCacheAccountDirectory($settings) . '/calendars/' . hash('sha256', $identity) . '.json';
}

function pseMailCacheMessageSourceFile(
  array $settings,
  string $folder,
  string $uid
): string {
  $identity = implode("\0", [$folder, $uid, 'source-v1']);
  return pseMailCacheAccountDirectory($settings) . '/messages/' . hash('sha256', $identity) . '.json';
}

function pseMailCacheMessageSourceLockFile(
  array $settings,
  string $folder,
  string $uid
): string {
  return pseMailCacheMessageSourceFile($settings, $folder, $uid) . '.lock';
}

function pseWithMessageSourceLock(
  array $settings,
  string $folder,
  string $uid,
  callable $callback
) {
  $handle = @fopen(pseMailCacheMessageSourceLockFile($settings, $folder, $uid), 'c+');
  if (!$handle) {
    throw new RuntimeException('Unable to lock the message cache.');
  }
  try {
    if (!@flock($handle, LOCK_EX)) {
      throw new RuntimeException('Unable to lock the message cache.');
    }
    return $callback();
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

function pseMailCacheMessageRenderSignature(array $settings): string
{
  $identity = json_encode([
    'renderer' => 3,
    'dateFormat' => (string)($settings['date_format'] ?? 'd-m-Y'),
    'timeFormat' => (string)($settings['time_format'] ?? 'H:i'),
    'timezone' => (string)($settings['timezone'] ?? 'UTC'),
    'blockRemoteImages' => !empty($settings['block_remote_images']),
    'alwaysLoadRemoteImages' => !empty($settings['always_load_remote_images'])
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  return hash('sha256', is_string($identity) ? $identity : 'renderer-v2');
}

function pseMailCacheMessageRenderedFile(
  array $settings,
  string $folder,
  string $uid,
  bool $loadRemote
): string {
  $identity = implode("\0", [
    $folder,
    $uid,
    $loadRemote ? 'remote' : 'blocked',
    pseMailCacheMessageRenderSignature($settings)
  ]);
  return pseMailCacheAccountDirectory($settings) . '/rendered/' . hash('sha256', $identity) . '.json';
}

// Kept as an internal compatibility alias for export code and older call sites.
function pseMailCacheMessageFile(
  array $settings,
  string $folder,
  string $uid,
  bool $loadRemote
): string {
  return pseMailCacheMessageRenderedFile($settings, $folder, $uid, $loadRemote);
}

function pseMailCacheMessageIndexFile(array $settings, string $folder, string $uid): string
{
  return pseMailCacheAccountDirectory($settings) . '/indexes/' . hash('sha256', $folder . "\0" . $uid) . '.json';
}

function pseMailCacheRegisterAsset(array $settings, string $folder, string $uid, string $key): void
{
  $file = pseMailCacheMessageIndexFile($settings, $folder, $uid);
  $index = pseReadJson($file, [
    'folder' => $folder,
    'uid' => $uid,
    'keys' => []
  ]);
  $keys = is_array($index['keys'] ?? null) ? $index['keys'] : [];
  $keys[$key] = $key;
  $index['folder'] = $folder;
  $index['uid'] = $uid;
  $index['keys'] = array_values($keys);
  pseWriteJson($file, $index);
}

function pseMailCacheDeleteMessageAssets(array $settings, string $folder, string $uid): void
{
  $indexFile = pseMailCacheMessageIndexFile($settings, $folder, $uid);
  $index = pseReadJson($indexFile, []);
  foreach ((array)($index['keys'] ?? []) as $key) {
    $key = (string)$key;
    if (!preg_match('/^[a-f0-9]{64}$/', $key)) {
      continue;
    }
    @unlink(PSE_CACHE_DIR . '/' . $key . '.bin');
    @unlink(PSE_CACHE_DIR . '/' . $key . '.json');
  }
  @unlink($indexFile);
}

function pseMailCacheDeleteMessageFiles(array $settings, string $folder, string $uid): void
{
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  $gmail = pseIsGmailAccount($settings);
  $assetFolders = [$folder => true];
  foreach (['messages', 'rendered'] as $cacheLayer) {
    foreach (glob($accountDirectory . '/' . $cacheLayer . '/*.json') ?: [] as $file) {
      $envelope = pseReadJson($file, []);
      $cachedFolder = (string)($envelope['folder'] ?? '');
      if (
        (string)($envelope['uid'] ?? '') === $uid &&
        ($gmail || $cachedFolder === $folder)
      ) {
        if ($cachedFolder !== '') {
          $assetFolders[$cachedFolder] = true;
        }
        @unlink($file);
      }
    }
  }
  foreach (array_keys($assetFolders) as $assetFolder) {
    pseMailCacheDeleteMessageAssets($settings, (string)$assetFolder, $uid);
  }
}

function pseMailCacheDeleteDirectory(string $directory): void
{
  if (!is_dir($directory)) {
    return;
  }
  $items = scandir($directory);
  if (!is_array($items)) {
    return;
  }
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }
    $path = $directory . '/' . $item;
    if (is_dir($path)) {
      pseMailCacheDeleteDirectory($path);
    } else {
      @unlink($path);
    }
  }
  @rmdir($directory);
}

function psePathStats(string $path): array
{
  $stats = ['bytes' => 0, 'files' => 0];
  if (is_link($path)) {
    return $stats;
  }
  if (is_file($path)) {
    $size = @filesize($path);
    $stats['bytes'] = $size === false ? 0 : max(0, (int)$size);
    $stats['files'] = 1;
    return $stats;
  }
  if (!is_dir($path)) {
    return $stats;
  }
  $items = @scandir($path);
  if (!is_array($items)) {
    return $stats;
  }
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }
    $child = psePathStats($path . '/' . $item);
    $stats['bytes'] += (int)$child['bytes'];
    $stats['files'] += (int)$child['files'];
  }
  return $stats;
}

function pseOfflineCacheUsage(array $settings): array
{
  $settings = pseNormalizeSettings($settings);
  $mailTotal = psePathStats(PSE_MAIL_CACHE_DIR);
  $assetTotal = psePathStats(PSE_CACHE_DIR);
  $accountUsage = [];

  foreach ((array)$settings['accounts'] as $key => $account) {
    if (!is_array($account)) {
      continue;
    }
    $accountId = pseSafeAccountId((string)($account['id'] ?? $key));
    if ($accountId === '') {
      continue;
    }
    $mail = psePathStats(PSE_MAIL_CACHE_DIR . '/' . $accountId);
    $accountUsage[$accountId] = [
      'id' => $accountId,
      'name' => trim((string)($account['name'] ?? '')) ?: $accountId,
      'type' => (string)($account['account_type'] ?? 'imap'),
      'mailBytes' => (int)$mail['bytes'],
      'assetBytes' => 0,
      'bytes' => (int)$mail['bytes'],
      'files' => (int)$mail['files']
    ];
  }

  foreach (glob(PSE_CACHE_DIR . '/*.json') ?: [] as $metaFile) {
    $meta = pseReadJson($metaFile, []);
    $accountId = pseSafeAccountId((string)($meta['accountId'] ?? ''));
    if ($accountId === '' || !isset($accountUsage[$accountId])) {
      continue;
    }
    $key = basename($metaFile, '.json');
    $metaStats = psePathStats($metaFile);
    $dataStats = preg_match('/^[a-f0-9]{64}$/', $key)
      ? psePathStats(PSE_CACHE_DIR . '/' . $key . '.bin')
      : ['bytes' => 0, 'files' => 0];
    $assetBytes = (int)$metaStats['bytes'] + (int)$dataStats['bytes'];
    $assetFiles = (int)$metaStats['files'] + (int)$dataStats['files'];
    $accountUsage[$accountId]['assetBytes'] += $assetBytes;
    $accountUsage[$accountId]['bytes'] += $assetBytes;
    $accountUsage[$accountId]['files'] += $assetFiles;
  }

  $knownBytes = 0;
  $knownFiles = 0;
  foreach ($accountUsage as $usage) {
    $knownBytes += (int)$usage['bytes'];
    $knownFiles += (int)$usage['files'];
  }
  $totalBytes = (int)$mailTotal['bytes'] + (int)$assetTotal['bytes'];
  $totalFiles = (int)$mailTotal['files'] + (int)$assetTotal['files'];

  return [
    'total' => [
      'bytes' => $totalBytes,
      'files' => $totalFiles,
      'mailBytes' => (int)$mailTotal['bytes'],
      'assetBytes' => (int)$assetTotal['bytes']
    ],
    'accounts' => array_values($accountUsage),
    'other' => [
      'bytes' => max(0, $totalBytes - $knownBytes),
      'files' => max(0, $totalFiles - $knownFiles)
    ],
    'calculatedAt' => time()
  ];
}

function pseImapQuotaUsage(array $settings, string $folder = 'INBOX'): array
{
  $result = [
    'visible' => !pseIsGmailAccount($settings),
    'supported' => false,
    'usedBytes' => 0,
    'limitBytes' => 0,
    'availableBytes' => 0,
    'resource' => 'STORAGE',
    'message' => ''
  ];
  if (pseIsGmailAccount($settings)) {
    $result['visible'] = false;
    return $result;
  }
  if (!function_exists('imap_get_quotaroot')) {
    $result['message'] = 'The PHP IMAP extension does not expose quota information.';
    return $result;
  }
  $folder = trim($folder) !== '' ? $folder : 'INBOX';
  $imap = null;
  try {
    $imap = pseOpenImap($settings, $folder, true);
    $quota = @imap_get_quotaroot($imap, $folder);
    if (!is_array($quota) || empty($quota)) {
      $quota = @imap_get_quotaroot($imap, 'INBOX');
    }
    if (!is_array($quota) || empty($quota)) {
      $result['message'] = 'This IMAP server did not report a mailbox quota.';
      return $result;
    }
    $storage = $quota['STORAGE'] ?? $quota['storage'] ?? null;
    if (!is_array($storage) && isset($quota['usage'], $quota['limit'])) {
      $storage = $quota;
    }
    if (!is_array($storage)) {
      foreach ($quota as $resource => $record) {
        if (is_array($record) && isset($record['usage'], $record['limit'])) {
          $storage = $record;
          $result['resource'] = strtoupper((string)$resource);
          break;
        }
      }
    }
    if (!is_array($storage)) {
      $result['message'] = 'The IMAP quota response did not contain a storage resource.';
      return $result;
    }
    $usageKb = max(0, (int)($storage['usage'] ?? 0));
    $limitKb = max(0, (int)($storage['limit'] ?? 0));
    $result['usedBytes'] = $usageKb * 1024;
    if ($limitKb <= 0) {
      $result['message'] = 'The IMAP server reported usage but no storage limit.';
      return $result;
    }
    $result['supported'] = true;
    $result['limitBytes'] = $limitKb * 1024;
    $result['availableBytes'] = max(0, ($limitKb - $usageKb) * 1024);
    return $result;
  } catch (Throwable $error) {
    $result['message'] = $error->getMessage();
    return $result;
  } finally {
    if ($imap !== null) {
      @imap_close($imap);
    }
  }
}

function pseMailCacheClearAccount(array $settings): void
{
  $accountId = pseSafeAccountId((string)($settings['account_id'] ?? ''));
  if ($accountId !== '') {
    pseMailCacheDeleteDirectory(PSE_MAIL_CACHE_DIR . '/' . $accountId);
  }
}

function pseMailCacheClearRenderedData(array $settings): void
{
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  foreach (['lists', 'rendered', 'calendars'] as $subdirectory) {
    foreach (glob($accountDirectory . '/' . $subdirectory . '/*.json') ?: [] as $file) {
      @unlink($file);
    }
  }
}

function pseMailCacheClearAccountAssets(array $settings): void
{
  $accountId = (string)($settings['account_id'] ?? '');
  foreach (glob(PSE_CACHE_DIR . '/*.json') ?: [] as $metaFile) {
    $meta = pseReadJson($metaFile, []);
    if ((string)($meta['accountId'] ?? '') !== $accountId) {
      continue;
    }
    $key = basename($metaFile, '.json');
    @unlink(PSE_CACHE_DIR . '/' . $key . '.bin');
    @unlink($metaFile);
  }
}

function pseMailCacheClearFolderDetails(array $settings, string $folder): void
{
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  $uids = [];
  foreach (['messages', 'rendered'] as $cacheLayer) {
    foreach (glob($accountDirectory . '/' . $cacheLayer . '/*.json') ?: [] as $file) {
      $envelope = pseReadJson($file, []);
      if ((string)($envelope['folder'] ?? '') !== $folder) {
        continue;
      }
      $uid = (string)($envelope['uid'] ?? '');
      if ($uid !== '') {
        $uids[$uid] = true;
      }
      @unlink($file);
    }
  }
  foreach (array_keys($uids) as $uid) {
    pseMailCacheDeleteMessageAssets($settings, $folder, (string)$uid);
  }
}

function pseMailCacheFolderCounts(array $folders): array
{
  $counts = [];
  foreach ($folders as $folder) {
    if (!is_array($folder)) {
      continue;
    }
    $id = (string)($folder['id'] ?? '');
    if ($id === '') {
      continue;
    }
    $counts[$id] = [
      'messages' => max(0, (int)($folder['messages'] ?? 0)),
      'unseen' => max(0, (int)($folder['unseen'] ?? 0))
    ];
  }
  return $counts;
}

function pseMailCacheChangedFolders(array $previousFolders, array $currentFolders): array
{
  $previous = pseMailCacheFolderCounts($previousFolders);
  $current = pseMailCacheFolderCounts($currentFolders);
  $changed = [];
  foreach ($current as $id => $counts) {
    if (!isset($previous[$id]) || $previous[$id] !== $counts) {
      $changed[$id] = true;
    }
  }
  foreach ($previous as $id => $counts) {
    if (!isset($current[$id])) {
      $changed[$id] = true;
    }
  }
  return array_keys($changed);
}

function pseCachedFolders(array $settings, bool $forceRefresh = false): array
{
  $file = pseMailCacheFoldersFile($settings);
  $previous = pseMailCacheEnvelopeRead($file);
  if (!$forceRefresh && !empty($previous)) {
    return [
      'folders' => $previous['data'],
      'changedFolders' => [],
      'cache' => pseMailCacheInfo($previous, true)
    ];
  }
  try {
    $folders = pseFolders($settings);
  } catch (Throwable $error) {
    if (!empty($previous)) {
      $cache = pseMailCacheInfo($previous, true);
      $cache['refreshError'] = $error->getMessage();
      $cache['googleReconnectRequired'] = $error instanceof PseGoogleReconnectRequiredException;
      return [
        'folders' => $previous['data'],
        'changedFolders' => [],
        'cache' => $cache
      ];
    }
    throw $error;
  }
  $changedFolders = empty($previous)
    ? []
    : pseMailCacheChangedFolders((array)$previous['data'], $folders);
  // Keep cached folder lists even when server counts change. They remain the fast,
  // cache-first view until the user explicitly refreshes the current folder.
  $envelope = pseMailCacheEnvelopeWrite($file, $folders, ['freshFromServer' => true]);
  return [
    'folders' => $folders,
    'changedFolders' => $changedFolders,
    'cache' => pseMailCacheInfo($envelope, false)
  ];
}

function pseCachedFolderStatus(array $settings, array $requestedFolderIds): array
{
  $file = pseMailCacheFoldersFile($settings);
  $previous = pseMailCacheEnvelopeRead($file);
  if (empty($previous)) {
    return pseCachedFolders($settings, true);
  }

  $folders = array_values((array)$previous['data']);
  $knownFolders = [];
  foreach ($folders as $index => $folder) {
    if (!is_array($folder)) {
      continue;
    }
    $id = (string)($folder['id'] ?? '');
    if ($id !== '') {
      $knownFolders[$id] = $index;
    }
  }

  $requested = [];
  foreach ($requestedFolderIds as $folderId) {
    $folderId = (string)$folderId;
    if ($folderId !== '' && isset($knownFolders[$folderId])) {
      $requested[$folderId] = true;
    }
  }
  if (empty($requested)) {
    return [
      'folders' => $folders,
      'changedFolders' => [],
      'checkedFolders' => [],
      'cache' => pseMailCacheInfo($previous, true)
    ];
  }

  $serverCounts = [];
  try {
    if (pseIsGmailAccount($settings)) {
      foreach (array_keys($requested) as $folderId) {
        $detail = pseGoogleApi(
          $settings,
          'GET',
          'labels/' . rawurlencode((string)$folderId)
        );
        $serverCounts[$folderId] = [
          'messages' => max(0, (int)($detail['messagesTotal'] ?? 0)),
          'unseen' => max(0, (int)($detail['messagesUnread'] ?? 0))
        ];
      }
    } else {
      $imap = pseOpenImap($settings, 'INBOX', true);
      $base = pseImapBase($settings);
      foreach (array_keys($requested) as $folderId) {
        $status = @imap_status($imap, $base . $folderId, SA_MESSAGES | SA_UNSEEN);
        if ($status !== false) {
          $serverCounts[$folderId] = [
            'messages' => max(0, (int)$status->messages),
            'unseen' => max(0, (int)$status->unseen)
          ];
        }
      }
      imap_close($imap);
    }
  } catch (Throwable $error) {
    $cache = pseMailCacheInfo($previous, true);
    $cache['refreshError'] = $error->getMessage();
    $cache['googleReconnectRequired'] = $error instanceof PseGoogleReconnectRequiredException;
    return [
      'folders' => $folders,
      'changedFolders' => [],
      'checkedFolders' => array_keys($requested),
      'cache' => $cache
    ];
  }

  $changedFolders = [];
  foreach ($serverCounts as $folderId => $counts) {
    $index = $knownFolders[$folderId];
    $oldMessages = max(0, (int)($folders[$index]['messages'] ?? 0));
    $oldUnseen = max(0, (int)($folders[$index]['unseen'] ?? 0));
    if ($oldMessages !== $counts['messages'] || $oldUnseen !== $counts['unseen']) {
      $folders[$index]['messages'] = $counts['messages'];
      $folders[$index]['unseen'] = $counts['unseen'];
      $changedFolders[] = $folderId;
      // Do not invalidate cached lists here. The client decides whether a count increase
      // is new mail (automatic page-1 refresh) or another change that stays cache-first.
    }
  }

  $envelope = pseMailCacheEnvelopeWrite($file, $folders, ['freshFromServer' => true]);
  return [
    'folders' => $folders,
    'changedFolders' => array_values(array_unique($changedFolders)),
    'checkedFolders' => array_keys($requested),
    'cache' => pseMailCacheInfo($envelope, false)
  ];
}

function pseCachedMessageList(
  array $settings,
  string $folder,
  int $page,
  string $search,
  string $senderFilter,
  bool $unreadOnly,
  string $sortOrder = 'desc',
  string $attachmentFilter = 'all',
  string $startDate = '',
  bool $forceRefresh = false,
  bool $cacheOnly = false
): array {
  $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $startDate = pseNormalizeCalendarDate($startDate);
  $senderFilter = strtolower(trim($senderFilter));
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }
  $file = pseMailCacheListFile(
    $settings,
    $folder,
    $page,
    $search,
    $senderFilter,
    $unreadOnly,
    $sortOrder,
    $attachmentFilter,
    $startDate
  );
  $previous = pseMailCacheEnvelopeRead($file);
  if (!$forceRefresh && !empty($previous)) {
    return [
      'data' => $previous['data'],
      'cache' => pseMailCacheInfo($previous, true),
      'cacheMiss' => false
    ];
  }
  if ($cacheOnly && !$forceRefresh) {
    return [
      'data' => null,
      'cache' => [
        'cached' => true,
        'savedAt' => 0,
        'savedAtIso' => ''
      ],
      'cacheMiss' => true
    ];
  }
  $needAttachmentCounts = !empty($settings['show_attachment_pill']) || $attachmentFilter !== 'all';
  $cachedAttachmentCounts = $needAttachmentCounts
    ? pseMailCacheAttachmentCounts($settings, $folder)
    : [];
  try {
    $data = pseMessageList(
      $settings,
      $folder,
      $page,
      $search,
      $senderFilter,
      $unreadOnly,
      $sortOrder,
      $cachedAttachmentCounts,
      $attachmentFilter,
      $startDate
    );
  } catch (Throwable $error) {
    if (!empty($previous)) {
      $cache = pseMailCacheInfo($previous, true);
      $cache['refreshError'] = $error->getMessage();
      $cache['googleReconnectRequired'] = $error instanceof PseGoogleReconnectRequiredException;
      return ['data' => $previous['data'], 'cache' => $cache];
    }
    throw $error;
  }
  if ($needAttachmentCounts) {
    pseMailCacheStoreAttachmentCounts(
      $settings,
      $folder,
      is_array($data['messages'] ?? null) ? $data['messages'] : []
    );
  }
  $oldValidity = (string)($previous['data']['uidValidity'] ?? '');
  $newValidity = (string)($data['uidValidity'] ?? '');
  if (
    !pseIsGmailAccount($settings) &&
    $oldValidity !== '' &&
    $newValidity !== '' &&
    $oldValidity !== $newValidity
  ) {
    pseMailCacheClearFolderDetails($settings, $folder);
    pseMailCacheClearAttachmentCounts($settings, $folder);
  }
  if (
    $forceRefresh &&
    !pseIsGmailAccount($settings) &&
    $page === 1 &&
    $startDate === '' &&
    $search === '' &&
    $senderFilter === '' &&
    !$unreadOnly &&
    isset($data['_cacheAllFolderUids']) &&
    is_array($data['_cacheAllFolderUids'])
  ) {
    pseMailCachePruneMissingImapMessages(
      $settings,
      $folder,
      $data['_cacheAllFolderUids']
    );
  }
  unset($data['_cacheAllFolderUids']);
  if ($forceRefresh) {
    pseMailCacheInvalidateFolderLists($settings, $folder);
  }
  $envelope = pseMailCacheEnvelopeWrite($file, $data, [
    'folder' => $folder,
    'page' => $page,
    'search' => $search,
    'senderFilter' => $senderFilter,
    'unreadOnly' => $unreadOnly,
    'sortOrder' => $sortOrder,
    'attachmentFilter' => $attachmentFilter,
    'startDate' => $startDate,
    'freshFromServer' => true
  ]);
  pseMailCacheSetFolderCounts(
    $settings,
    $folder,
    (int)($data['folderTotal'] ?? $data['total'] ?? 0),
    (int)($data['folderUnseen'] ?? 0)
  );
  return [
    'data' => $data,
    'cache' => pseMailCacheInfo($envelope, false),
    'cacheMiss' => false
  ];
}

function pseMailCachePublicMessage(array $message): array
{
  unset($message['_cacheSourceHtml'], $message['_cachePrefetched']);
  return $message;
}

function pseMailCacheReadMessageSource(
  array $settings,
  string $folder,
  string $uid,
  bool $migrateLegacy = true
): array {
  $sourceFile = pseMailCacheMessageSourceFile($settings, $folder, $uid);
  $source = pseMailCacheEnvelopeRead($sourceFile);
  if (!empty($source)) {
    return $source;
  }
  if (!$migrateLegacy) {
    return [];
  }

  // Migrate v2.13 and earlier detail caches without contacting the provider.
  $best = [];
  $bestFile = '';
  foreach (glob(pseMailCacheAccountDirectory($settings) . '/messages/*.json') ?: [] as $file) {
    if ($file === $sourceFile) {
      continue;
    }
    $candidate = pseMailCacheEnvelopeRead($file);
    if (
      empty($candidate) ||
      (string)($candidate['folder'] ?? '') !== $folder ||
      (string)($candidate['uid'] ?? '') !== $uid
    ) {
      continue;
    }
    if (empty($best) || (!empty($best['loadRemote']) && empty($candidate['loadRemote']))) {
      $best = $candidate;
      $bestFile = $file;
    }
  }
  if (empty($best)) {
    return [];
  }

  $data = (array)$best['data'];
  if ((string)($data['_cacheSourceHtml'] ?? '') === '') {
    $data['_cacheSourceHtml'] = (string)($data['html'] ?? '');
  }
  $source = pseMailCacheEnvelopeWrite($sourceFile, $data, [
    'folder' => $folder,
    'uid' => $uid,
    'cacheLayer' => 'source',
    'serverSyncedAt' => (int)($best['serverSyncedAt'] ?? $best['savedAt'] ?? time())
  ]);

  foreach (glob(pseMailCacheAccountDirectory($settings) . '/messages/*.json') ?: [] as $file) {
    if ($file === $sourceFile) {
      continue;
    }
    $candidate = pseMailCacheEnvelopeRead($file);
    if (
      !empty($candidate) &&
      (string)($candidate['folder'] ?? '') === $folder &&
      (string)($candidate['uid'] ?? '') === $uid
    ) {
      @unlink($file);
    }
  }
  return $source;
}

function pseMailCacheRenderMessage(
  array $settings,
  string $folder,
  string $uid,
  array $source,
  bool $loadRemote
): array {
  $message = $source;
  $sourceHtml = (string)($source['_cacheSourceHtml'] ?? $source['html'] ?? '');
  // CID references are converted to signed attachment URLs only. The attachment
  // bytes are fetched later by the browser, after the email body can render.
  $sourceHtml = pseReplaceCidUrls(
    $sourceHtml,
    pseLazyCidUrlsFromAttachments((array)($source['attachments'] ?? []))
  );
  $forceImageBlocking = (int)($source['size'] ?? 0) > PSE_LARGE_MESSAGE_BYTES;
  $remotePrepared = psePrepareRemoteImageHtml(
    $settings,
    $folder,
    $uid,
    $sourceHtml,
    $loadRemote,
    $forceImageBlocking
  );
  $html = (string)$remotePrepared['html'];
  $plain = (string)($source['plain'] ?? '');
  if ($html === '' && $plain !== '') {
    $html = '<pre style="white-space:pre-wrap;font:14px/1.55 Arial,sans-serif;margin:0">' .
      htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
      '</pre>';
  }
  $timestamp = (int)($source['timestamp'] ?? 0);
  if ($timestamp > 0) {
    $message['date'] = pseFormatDate($timestamp, $settings);
  }
  $message['html'] = $html;
  $message['remoteImages'] = !empty($remotePrepared['remoteImages']);
  $message['remoteImagesBlocked'] = !empty($remotePrepared['remoteImagesBlocked']);
  $message['seen'] = !empty($source['seen']);
  return $message;
}

function pseMailCachePruneMissingImapMessages(
  array $settings,
  string $folder,
  array $knownUids
): void {
  if (pseIsGmailAccount($settings)) {
    return;
  }
  $known = array_fill_keys(array_map('strval', $knownUids), true);
  $sourceDirectory = pseMailCacheAccountDirectory($settings) . '/messages';
  foreach (glob($sourceDirectory . '/*.json') ?: [] as $file) {
    $envelope = pseMailCacheEnvelopeRead($file);
    if (empty($envelope) || (string)($envelope['folder'] ?? '') !== $folder) {
      continue;
    }
    $uid = (string)($envelope['uid'] ?? '');
    if ($uid !== '' && !isset($known[$uid])) {
      pseMailCacheDeleteMessageFiles($settings, $folder, $uid);
      pseMailCacheRemoveAttachmentCounts($settings, $folder, [$uid]);
    }
  }
}

function pseValidMessageUid(array $settings, string $uid): bool
{
  if (pseIsGmailAccount($settings)) {
    return (bool)preg_match('/^[a-zA-Z0-9_-]+$/', $uid);
  }
  return ctype_digit($uid) && (int)$uid > 0;
}

function pseNormalizePrefetchUids(array $settings, array $uids): array
{
  $normalized = [];
  foreach (array_slice($uids, 0, 200) as $uid) {
    $uid = trim((string)$uid);
    if ($uid !== '' && pseValidMessageUid($settings, $uid)) {
      $normalized[$uid] = $uid;
    }
  }
  return array_values($normalized);
}

function pseMessageSourceNeedsMimeBodyRepair(array $settings, array $source): bool
{
  if (!pseIsGmailAccount($settings)) {
    return false;
  }
  $html = trim((string)($source['_cacheSourceHtml'] ?? $source['html'] ?? ''));
  $plain = trim((string)($source['plain'] ?? ''));
  if ($html !== '' || $plain !== '') {
    return false;
  }
  foreach ((array)($source['attachments'] ?? []) as $attachment) {
    if (!is_array($attachment)) {
      continue;
    }
    $mime = strtolower(trim((string)($attachment['mime'] ?? '')));
    $cid = trim((string)($attachment['cid'] ?? ''), '<>');
    if (
      in_array($mime, ['text/plain', 'text/html'], true) &&
      !empty($attachment['inline']) &&
      $cid !== ''
    ) {
      return true;
    }
  }
  return false;
}

function pseMessageSourceCacheStatus(
  array $settings,
  string $folder,
  array $uids
): array {
  $cached = [];
  $missing = [];
  foreach (pseNormalizePrefetchUids($settings, $uids) as $uid) {
    $sourceEnvelope = pseMailCacheReadMessageSource($settings, $folder, $uid, false);
    if (
      !empty($sourceEnvelope) &&
      !pseMessageSourceNeedsMimeBodyRepair($settings, (array)$sourceEnvelope['data'])
    ) {
      $cached[] = $uid;
    } else {
      $missing[] = $uid;
    }
  }
  return ['cached' => $cached, 'missing' => $missing];
}

function psePrefetchedSourceNeedsForegroundHydration(array $source): bool
{
  // CID images no longer require foreground hydration: their signed attachment
  // URLs are inserted while rendering and the browser fetches the bytes later.
  return false;
}

function pseWriteMessageSource(
  array $settings,
  string $folder,
  string $uid,
  array $sourceMessage,
  bool $prefetched
): array {
  if ((string)($sourceMessage['_cacheSourceHtml'] ?? '') === '') {
    $sourceMessage['_cacheSourceHtml'] = (string)($sourceMessage['html'] ?? '');
  }
  $sourceMessage['_cachePrefetched'] = $prefetched;
  $sourceEnvelope = pseMailCacheEnvelopeWrite(
    pseMailCacheMessageSourceFile($settings, $folder, $uid),
    $sourceMessage,
    [
      'folder' => $folder,
      'uid' => $uid,
      'cacheLayer' => 'source',
      'freshFromServer' => true,
      'prefetched' => $prefetched
    ]
  );
  // The render filename already includes folder, UID, remote-image mode and
  // the current render signature, so invalidation stays O(1) per message.
  @unlink(pseMailCacheMessageRenderedFile($settings, $folder, $uid, false));
  @unlink(pseMailCacheMessageRenderedFile($settings, $folder, $uid, true));
  return $sourceEnvelope;
}

function psePrefetchMessageSource(
  array $settings,
  string $folder,
  string $uid,
  int $expectedSize = 0
): array {
  if (!pseValidMessageUid($settings, $uid)) {
    throw new RuntimeException('Invalid message identifier.');
  }
  if ($expectedSize > PSE_PREFETCH_MAX_MESSAGE_BYTES) {
    return [
      'cached' => false,
      'prefetched' => false,
      'skipped' => true,
      'reason' => 'large'
    ];
  }
  return pseWithMessageSourceLock(
    $settings,
    $folder,
    $uid,
    function () use ($settings, $folder, $uid): array {
      $existing = pseMailCacheReadMessageSource($settings, $folder, $uid, false);
      if (
        !empty($existing) &&
        !pseMessageSourceNeedsMimeBodyRepair($settings, (array)$existing['data'])
      ) {
        return [
          'cached' => true,
          'prefetched' => false,
          'skipped' => false
        ];
      }
      // Prefetch the message body and metadata without changing \Seen, loading
      // remote images, or downloading ordinary/inline attachment content.
      $sourceMessage = pseMessageDetails($settings, $folder, $uid, false, false, true);
      pseWriteMessageSource($settings, $folder, $uid, $sourceMessage, true);
      return [
        'cached' => true,
        'prefetched' => true,
        'skipped' => false
      ];
    }
  );
}

function pseCachedMessageDetails(
  array $settings,
  string $folder,
  string $uid,
  bool $loadRemote,
  bool $forceRefresh = false
): array {
  $freshFromServer = false;
  $sourceEnvelope = pseWithMessageSourceLock(
    $settings,
    $folder,
    $uid,
    function () use (
      $settings,
      $folder,
      $uid,
      $forceRefresh,
      &$freshFromServer
    ): array {
      $existingSource = pseMailCacheReadMessageSource($settings, $folder, $uid);
      $sourceEnvelope = $forceRefresh ? [] : $existingSource;
      $needsHydration = !empty($sourceEnvelope) &&
        pseMessageSourceNeedsMimeBodyRepair($settings, (array)$sourceEnvelope['data']);
      if (!empty($sourceEnvelope) && !$needsHydration) {
        return $sourceEnvelope;
      }
      try {
        // Fetch only the message body/metadata here. Marking the message read is
        // deliberately deferred to a separate client request after the preview renders.
        $sourceMessage = pseMessageDetails($settings, $folder, $uid, false, false, false);
        $freshFromServer = true;
        return pseWriteMessageSource($settings, $folder, $uid, $sourceMessage, false);
      } catch (Throwable $error) {
        if (empty($existingSource)) {
          throw $error;
        }
        return $existingSource;
      }
    }
  );

  $source = (array)$sourceEnvelope['data'];

  $renderedFile = pseMailCacheMessageRenderedFile($settings, $folder, $uid, $loadRemote);
  $renderedEnvelope = !$forceRefresh ? pseMailCacheEnvelopeRead($renderedFile) : [];
  if (empty($renderedEnvelope)) {
    $renderedMessage = pseMailCacheRenderMessage($settings, $folder, $uid, $source, $loadRemote);
    $renderedEnvelope = pseMailCacheEnvelopeWrite($renderedFile, $renderedMessage, [
      'folder' => $folder,
      'uid' => $uid,
      'loadRemote' => $loadRemote,
      'cacheLayer' => 'rendered',
      'renderSignature' => pseMailCacheMessageRenderSignature($settings),
      'serverSyncedAt' => (int)($sourceEnvelope['serverSyncedAt'] ?? $sourceEnvelope['savedAt'] ?? time())
    ]);
  }

  return [
    'message' => pseMailCachePublicMessage((array)$renderedEnvelope['data']),
    'cache' => pseMailCacheInfo($sourceEnvelope, !$freshFromServer)
  ];
}

function pseMailCacheUpdateFoldersCounts(
  array $settings,
  string $folder,
  int $messageDelta,
  int $unseenDelta
): void {
  $file = pseMailCacheFoldersFile($settings);
  $envelope = pseMailCacheEnvelopeRead($file);
  if (empty($envelope)) {
    return;
  }
  foreach ($envelope['data'] as &$item) {
    if ((string)($item['id'] ?? '') !== $folder) {
      continue;
    }
    $item['messages'] = max(0, (int)($item['messages'] ?? 0) + $messageDelta);
    $item['unseen'] = max(0, (int)($item['unseen'] ?? 0) + $unseenDelta);
  }
  unset($item);
  pseMailCacheEnvelopeWrite($file, $envelope['data']);
}

function pseMailCacheSetFolderCounts(
  array $settings,
  string $folder,
  int $messages,
  int $unseen
): void {
  $file = pseMailCacheFoldersFile($settings);
  $envelope = pseMailCacheEnvelopeRead($file);
  if (empty($envelope)) {
    return;
  }
  $changed = false;
  foreach ($envelope['data'] as &$item) {
    if ((string)($item['id'] ?? '') !== $folder) {
      continue;
    }
    $item['messages'] = max(0, $messages);
    $item['unseen'] = max(0, $unseen);
    $changed = true;
    break;
  }
  unset($item);
  if ($changed) {
    pseMailCacheEnvelopeWrite($file, $envelope['data'], [
      'serverSyncedAt' => (int)($envelope['serverSyncedAt'] ?? $envelope['savedAt'] ?? time())
    ]);
  }
}

function pseMailCacheFolderSpecial(array $settings, string $folder): string
{
  $envelope = pseMailCacheEnvelopeRead(pseMailCacheFoldersFile($settings));
  foreach ((array)($envelope['data'] ?? []) as $item) {
    if ((string)($item['id'] ?? '') === $folder) {
      return (string)($item['special'] ?? 'folder');
    }
  }
  return 'folder';
}

function pseMailCacheAdjustSpecialFolder(
  array $settings,
  string $special,
  int $messageDelta,
  int $unseenDelta = 0
): void {
  $file = pseMailCacheFoldersFile($settings);
  $envelope = pseMailCacheEnvelopeRead($file);
  if (empty($envelope)) {
    return;
  }
  foreach ($envelope['data'] as &$item) {
    if ((string)($item['special'] ?? '') !== $special) {
      continue;
    }
    $item['messages'] = max(0, (int)($item['messages'] ?? 0) + $messageDelta);
    $item['unseen'] = max(0, (int)($item['unseen'] ?? 0) + $unseenDelta);
  }
  unset($item);
  pseMailCacheEnvelopeWrite($file, $envelope['data']);
}

function pseMailCacheUpdateFlags(
  array $settings,
  string $folder,
  array $uids,
  bool $seen
): void {
  $uidMap = array_fill_keys(array_map('strval', $uids), true);
  if (empty($uidMap)) {
    return;
  }
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  $unseenDelta = 0;
  $counted = [];
  $listEnvelopes = [];
  foreach (glob($accountDirectory . '/lists/*.json') ?: [] as $file) {
    $envelope = pseMailCacheEnvelopeRead($file);
    if (empty($envelope) || (string)($envelope['folder'] ?? '') !== $folder) {
      continue;
    }
    $changed = false;
    $removedFromUnreadView = 0;
    $messages = [];
    foreach ((array)($envelope['data']['messages'] ?? []) as $message) {
      $uid = (string)($message['uid'] ?? '');
      if (!isset($uidMap[$uid])) {
        $messages[] = $message;
        continue;
      }
      $oldSeen = !empty($message['seen']);
      if ($oldSeen !== $seen && !isset($counted[$uid])) {
        $unseenDelta += $seen ? -1 : 1;
        $counted[$uid] = true;
      }
      if ($seen && !empty($envelope['unreadOnly'])) {
        $removedFromUnreadView++;
        $changed = true;
        continue;
      }
      $message['seen'] = $seen;
      $messages[] = $message;
      $changed = true;
    }
    $envelope['data']['messages'] = $messages;
    if ($removedFromUnreadView > 0) {
      $envelope['data']['total'] = max(
        0,
        (int)($envelope['data']['total'] ?? 0) - $removedFromUnreadView
      );
      $perPage = max(1, (int)($envelope['data']['perPage'] ?? 1));
      $envelope['data']['pages'] = max(
        1,
        (int)ceil((int)$envelope['data']['total'] / $perPage)
      );
    }
    $envelope['_changed'] = $changed;
    $listEnvelopes[$file] = $envelope;
  }
  foreach ($listEnvelopes as $file => $envelope) {
    if ($unseenDelta !== 0) {
      $envelope['data']['folderUnseen'] = max(
        0,
        (int)($envelope['data']['folderUnseen'] ?? 0) + $unseenDelta
      );
    }
    if (empty($envelope['_changed']) && $unseenDelta === 0) {
      continue;
    }
    pseMailCacheEnvelopeWrite($file, $envelope['data'], [
      'folder' => (string)$envelope['folder'],
      'page' => (int)($envelope['page'] ?? 1),
      'search' => (string)($envelope['search'] ?? ''),
      'unreadOnly' => !empty($envelope['unreadOnly'])
    ]);
  }
  foreach (['messages', 'rendered'] as $cacheLayer) {
    foreach (glob($accountDirectory . '/' . $cacheLayer . '/*.json') ?: [] as $file) {
      $envelope = pseMailCacheEnvelopeRead($file);
      if (
        empty($envelope) ||
        (string)($envelope['folder'] ?? '') !== $folder ||
        !isset($uidMap[(string)($envelope['uid'] ?? '')])
      ) {
        continue;
      }
      $envelope['data']['seen'] = $seen;
      pseMailCacheEnvelopeWrite($file, $envelope['data'], [
        'folder' => (string)$envelope['folder'],
        'uid' => (string)$envelope['uid'],
        'loadRemote' => !empty($envelope['loadRemote']),
        'cacheLayer' => (string)($envelope['cacheLayer'] ?? $cacheLayer),
        'renderSignature' => (string)($envelope['renderSignature'] ?? ''),
        'serverSyncedAt' => (int)($envelope['serverSyncedAt'] ?? $envelope['savedAt'] ?? time())
      ]);
    }
  }
  if ($unseenDelta !== 0) {
    pseMailCacheUpdateFoldersCounts($settings, $folder, 0, $unseenDelta);
  }
  pseMailCacheInvalidateFolderCalendars($settings, $folder);
}

function pseMailCacheDeleteMessages(array $settings, string $folder, array $uids): void
{
  $uidMap = array_fill_keys(array_map('strval', $uids), true);
  if (empty($uidMap)) {
    return;
  }
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  $gmail = pseIsGmailAccount($settings);
  $listEnvelopes = [];
  $removedByFolder = [];
  $removedUnseenByFolder = [];

  foreach (glob($accountDirectory . '/lists/*.json') ?: [] as $file) {
    $envelope = pseMailCacheEnvelopeRead($file);
    if (empty($envelope)) {
      continue;
    }
    $cacheFolder = (string)($envelope['folder'] ?? '');
    if (!$gmail && $cacheFolder !== $folder) {
      continue;
    }
    $remaining = [];
    $removedHere = 0;
    foreach ((array)($envelope['data']['messages'] ?? []) as $message) {
      $uid = (string)($message['uid'] ?? '');
      if (!isset($uidMap[$uid])) {
        $remaining[] = $message;
        continue;
      }
      $removedHere++;
      $removedByFolder[$cacheFolder][$uid] = true;
      if (empty($message['seen'])) {
        $removedUnseenByFolder[$cacheFolder][$uid] = true;
      }
    }
    $envelope['data']['messages'] = $remaining;
    $envelope['_removedHere'] = $removedHere;
    $listEnvelopes[$file] = $envelope;
  }

  foreach (array_keys($uidMap) as $uid) {
    $removedByFolder[$folder][(string)$uid] = true;
  }

  foreach ($listEnvelopes as $file => $envelope) {
    $cacheFolder = (string)($envelope['folder'] ?? '');
    $removedHere = (int)($envelope['_removedHere'] ?? 0);
    $folderAffected = count($removedByFolder[$cacheFolder] ?? []);
    $folderUnseenAffected = count($removedUnseenByFolder[$cacheFolder] ?? []);
    if ($removedHere > 0) {
      $envelope['data']['total'] = max(
        0,
        (int)($envelope['data']['total'] ?? 0) - $removedHere
      );
      $perPage = max(1, (int)($envelope['data']['perPage'] ?? 1));
      $envelope['data']['pages'] = max(
        1,
        (int)ceil((int)$envelope['data']['total'] / $perPage)
      );
    }
    if ($folderAffected > 0) {
      $envelope['data']['folderTotal'] = max(
        0,
        (int)($envelope['data']['folderTotal'] ?? 0) - $folderAffected
      );
      $envelope['data']['folderUnseen'] = max(
        0,
        (int)($envelope['data']['folderUnseen'] ?? 0) - $folderUnseenAffected
      );
    }
    if ($removedHere === 0 && $folderAffected === 0) {
      continue;
    }
    pseMailCacheEnvelopeWrite($file, $envelope['data'], [
      'folder' => $cacheFolder,
      'page' => (int)($envelope['page'] ?? 1),
      'search' => (string)($envelope['search'] ?? ''),
      'unreadOnly' => !empty($envelope['unreadOnly'])
    ]);
  }

  foreach ($removedByFolder as $affectedFolder => $folderUids) {
    pseMailCacheUpdateFoldersCounts(
      $settings,
      (string)$affectedFolder,
      -count($folderUids),
      -count($removedUnseenByFolder[$affectedFolder] ?? [])
    );
  }

  $affected = count($uidMap);
  $sourceUnseenAffected = count($removedUnseenByFolder[$folder] ?? []);
  if (pseMailCacheFolderSpecial($settings, $folder) !== 'trash') {
    pseMailCacheAdjustSpecialFolder($settings, 'trash', $affected, $sourceUnseenAffected);
  }
  foreach (array_keys($uidMap) as $uid) {
    pseMailCacheDeleteMessageFiles($settings, $folder, (string)$uid);
  }
  pseMailCacheRemoveAttachmentCounts($settings, $folder, array_keys($uidMap));
  pseMailCacheInvalidateFolderCalendars($settings, $folder);
  foreach (array_keys($removedByFolder) as $affectedFolder) {
    pseMailCacheInvalidateFolderCalendars($settings, (string)$affectedFolder);
  }
}

function pseMailCacheAfterDirectMessageOperation(
  array $settings,
  string $sourceFolder,
  array $uids,
  string $operation,
  string $destinationFolder = ''
): void {
  $uids = array_values(array_unique(array_filter(array_map('strval', $uids), function (string $uid): bool {
    return trim($uid) !== '';
  })));
  if (empty($uids)) {
    return;
  }

  if ($operation === 'delete_forever' && pseIsGmailAccount($settings)) {
    // A permanently deleted Gmail message disappears from every label. Drop all
    // cached lists because the same immutable Gmail ID may be present in several labels.
    pseMailCacheClearLists($settings);
    $indexDirectory = pseMailCacheAccountDirectory($settings) . '/indexes';
    foreach (glob($indexDirectory . '/attachment-counts-*.json') ?: [] as $file) {
      $counts = pseReadJson($file, []);
      $changed = false;
      foreach ($uids as $uid) {
        if (array_key_exists($uid, $counts)) {
          unset($counts[$uid]);
          $changed = true;
        }
      }
      if ($changed) {
        pseWriteJson($file, $counts);
      }
    }
  } else {
    pseMailCacheInvalidateFolderLists($settings, $sourceFolder);
    pseMailCacheRemoveAttachmentCounts($settings, $sourceFolder, $uids);
    if ($destinationFolder !== '') {
      pseMailCacheInvalidateFolderLists($settings, $destinationFolder);
    }
  }

  foreach ($uids as $uid) {
    pseMailCacheDeleteMessageFiles($settings, $sourceFolder, $uid);
  }

  // Force authoritative folder counts on the next folder read. The browser updates
  // its visible counts immediately, while this prevents stale persistent counts.
  @unlink(pseMailCacheFoldersFile($settings));
}

function pseMailCacheClearLists(array $settings): void
{
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  foreach (['lists', 'calendars'] as $subdirectory) {
    foreach (glob($accountDirectory . '/' . $subdirectory . '/*.json') ?: [] as $file) {
      @unlink($file);
    }
  }
}

function pseMailCacheInvalidateFolderCalendars(array $settings, string $folder): void
{
  $directory = pseMailCacheAccountDirectory($settings) . '/calendars';
  foreach (glob($directory . '/*.json') ?: [] as $file) {
    $envelope = pseReadJson($file, []);
    if ((string)($envelope['folder'] ?? '') === $folder) {
      @unlink($file);
    }
  }
}

function pseMailCacheInvalidateFolderLists(array $settings, string $folder): void
{
  $accountDirectory = pseMailCacheAccountDirectory($settings);
  foreach (['lists', 'calendars'] as $subdirectory) {
    foreach (glob($accountDirectory . '/' . $subdirectory . '/*.json') ?: [] as $file) {
      $envelope = pseReadJson($file, []);
      if ((string)($envelope['folder'] ?? '') === $folder) {
        @unlink($file);
      }
    }
  }
}

function pseAttachmentCacheKey(
  array $settings,
  string $folder,
  string $uid,
  string $part
): string {
  $identity = implode('|', [
    (string)($settings['account_id'] ?? ''),
    (string)$settings['imap_username'],
    $folder,
    $uid,
    $part
  ]);
  return hash_hmac('sha256', $identity, (string)$settings['storage_key']);
}

function pseCachedAttachmentRecord(
  array $settings,
  string $folder,
  string $uid,
  string $part
): array {
  $key = pseAttachmentCacheKey($settings, $folder, $uid, $part);
  $dataFile = PSE_CACHE_DIR . '/' . $key . '.bin';
  $metaFile = PSE_CACHE_DIR . '/' . $key . '.json';
  $meta = pseReadJson($metaFile, []);
  if (!is_file($dataFile) || empty($meta)) {
    return [];
  }
  return [
    'key' => $key,
    'dataFile' => $dataFile,
    'meta' => $meta
  ];
}

function pseCachedAttachmentUrlFromRecord(array $settings, array $record): string
{
  $key = (string)($record['key'] ?? '');
  if (!preg_match('/^[a-f0-9]{64}$/', $key)) {
    return '';
  }
  $signature = substr(
    hash_hmac('sha256', 'attachment|' . $key, (string)$settings['storage_key']),
    0,
    40
  );
  return '?cached_attachment=' . rawurlencode($key . '.' . $signature);
}

function pseOutputCachedAttachmentRecord(array $record, bool $download): void
{
  $meta = (array)$record['meta'];
  $dataFile = (string)$record['dataFile'];
  $filename = str_replace(["\r", "\n", '"'], '', basename((string)($meta['filename'] ?? 'attachment.bin')));
  $mime = preg_replace('/[^a-z0-9.+-\/]/i', '', (string)($meta['mime'] ?? 'application/octet-stream'));
  $inline = !$download && pseIsReadableAttachment($mime);
  header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') .
    '; filename="' . ($filename ?: 'attachment.bin') . '"; filename*=UTF-8\'\'' . rawurlencode($filename ?: 'attachment.bin'));
  header('Content-Length: ' . (string)filesize($dataFile));
  header('Cache-Control: private, max-age=31536000, immutable');
  header('X-Content-Type-Options: nosniff');
  readfile($dataFile);
  exit;
}

function pseReplaceCidUrls(string $html, array $cidUrls): string
{
  if ($html === '' || empty($cidUrls)) {
    return $html;
  }
  foreach ($cidUrls as $cid => $url) {
    $quoted = preg_quote((string)$cid, '/');
    $html = preg_replace(
      '/cid:' . $quoted . '(?=["\'\s)>])/i',
      $url,
      $html
    ) ?? $html;
  }
  return $html;
}


function pseLazyCidUrlsFromAttachments(array $attachments): array
{
  $cidUrls = [];
  foreach ($attachments as $attachment) {
    if (!is_array($attachment) || empty($attachment['inline'])) {
      continue;
    }
    $cid = trim((string)($attachment['cid'] ?? ''), '<>');
    $url = trim((string)($attachment['url'] ?? ''));
    if ($cid !== '' && $url !== '') {
      $cidUrls[$cid] = $url;
    }
  }
  return $cidUrls;
}


function pseReferencedCidSet(string $html): array
{
  $result = [];
  if ($html === '') {
    return $result;
  }
  $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if (!preg_match_all('/cid:([^"\'\s)>]+)/i', $decoded, $matches)) {
    return $result;
  }
  foreach ((array)($matches[1] ?? []) as $cid) {
    $cid = strtolower(trim(rawurldecode((string)$cid), '<>'));
    if ($cid !== '') {
      $result[$cid] = true;
    }
  }
  return $result;
}

function pseRemoteImageHostIsPublic(string $host): bool
{
  if ($host === '' || strcasecmp($host, 'localhost') === 0) {
    return false;
  }
  $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
  if (empty($ips)) {
    return false;
  }
  foreach ($ips as $ip) {
    if (!filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    )) {
      return false;
    }
  }
  return true;
}

function pseDownloadRemoteImage(string $url): array
{
  $parts = parse_url($url);
  $scheme = strtolower((string)($parts['scheme'] ?? ''));
  $host = (string)($parts['host'] ?? '');
  if (!in_array($scheme, ['http', 'https'], true) || !pseRemoteImageHostIsPublic($host)) {
    return [];
  }
  if (!function_exists('curl_init')) {
    return [];
  }
  $body = '';
  $tooLarge = false;
  $curl = curl_init($url);
  if ($curl === false) {
    return [];
  }
  curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => PSE_NAME . '/' . PSE_VERSION,
    CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
    CURLOPT_WRITEFUNCTION => function ($curlHandle, string $chunk) use (&$body, &$tooLarge): int {
      if (strlen($body) + strlen($chunk) > PSE_REMOTE_IMAGE_MAX_BYTES) {
        $tooLarge = true;
        return 0;
      }
      $body .= $chunk;
      return strlen($chunk);
    }
  ]);
  $ok = curl_exec($curl);
  $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  $mime = strtolower(trim(explode(';', (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
  curl_close($curl);
  if ($ok === false || $tooLarge || $status < 200 || $status >= 300 || strpos($mime, 'image/') !== 0 || $body === '') {
    return [];
  }
  return ['mime' => $mime, 'content' => $body];
}

function pseRemoteImageToken(
  array $settings,
  string $folder,
  string $uid,
  string $url
): string {
  $payload = pseBase64UrlEncode((string)json_encode([
    'account' => (string)($settings['account_id'] ?? ''),
    'folder' => $folder,
    'uid' => $uid,
    'url' => $url
  ], JSON_UNESCAPED_SLASHES));
  $signature = substr(
    hash_hmac('sha256', 'remote-image|' . $payload, (string)$settings['storage_key']),
    0,
    40
  );
  return $payload . '.' . $signature;
}

function pseRemoteImageUrl(
  array $settings,
  string $folder,
  string $uid,
  string $url
): string {
  return '?remote_image=' . rawurlencode(
    pseRemoteImageToken($settings, $folder, $uid, $url)
  );
}

function pseReadRemoteImageToken(array $settings, string $token): array
{
  if (!pseIsAuthenticated($settings)) {
    throw new RuntimeException('Authentication required.');
  }
  $parts = explode('.', $token, 2);
  $payload = (string)($parts[0] ?? '');
  $signature = (string)($parts[1] ?? '');
  if (
    $payload === '' ||
    !preg_match('/^[a-zA-Z0-9_-]+$/', $payload) ||
    !preg_match('/^[a-f0-9]{40}$/', $signature)
  ) {
    throw new RuntimeException('Invalid remote image link.');
  }
  $expected = substr(
    hash_hmac('sha256', 'remote-image|' . $payload, (string)$settings['storage_key']),
    0,
    40
  );
  if (!hash_equals($expected, $signature)) {
    throw new RuntimeException('Invalid remote image link.');
  }
  $data = json_decode(pseBase64UrlDecode($payload), true);
  $url = is_array($data) ? trim((string)($data['url'] ?? '')) : '';
  if (
    !is_array($data) ||
    (string)($data['account'] ?? '') !== (string)($settings['account_id'] ?? '') ||
    trim((string)($data['folder'] ?? '')) === '' ||
    trim((string)($data['uid'] ?? '')) === '' ||
    $url === '' ||
    strlen($url) > 16384 ||
    !preg_match('#^https?://#i', $url)
  ) {
    throw new RuntimeException('Remote image link has expired.');
  }
  return [
    'folder' => (string)$data['folder'],
    'uid' => (string)$data['uid'],
    'url' => $url
  ];
}

function pseRemoteImageCacheRecord(
  array $settings,
  string $folder,
  string $uid,
  string $url,
  bool $downloadIfMissing = true
): array {
  $part = 'remote-' . hash('sha256', $url);
  $existing = pseCachedAttachmentRecord($settings, $folder, $uid, $part);
  if (!empty($existing) || !$downloadIfMissing) {
    return $existing;
  }
  $downloaded = pseDownloadRemoteImage($url);
  if (empty($downloaded)) {
    return [];
  }
  $path = parse_url($url, PHP_URL_PATH);
  $filename = basename(is_string($path) ? $path : '') ?: 'remote-image';
  pseCacheAttachment(
    $settings,
    $folder,
    $uid,
    $part,
    $filename,
    (string)$downloaded['mime'],
    (string)$downloaded['content']
  );
  return pseCachedAttachmentRecord($settings, $folder, $uid, $part);
}

function pseOutputRemoteImageRecord(array $record): void
{
  $meta = (array)($record['meta'] ?? []);
  $dataFile = (string)($record['dataFile'] ?? '');
  $mime = strtolower(preg_replace('/[^a-z0-9.+-\/]/i', '', (string)($meta['mime'] ?? '')));
  if ($dataFile === '' || !is_file($dataFile) || strpos($mime, 'image/') !== 0) {
    http_response_code(404);
    exit('Remote image unavailable.');
  }
  $filename = str_replace(["\r", "\n", '"'], '', basename((string)($meta['filename'] ?? 'remote-image')));
  header('Content-Type: ' . $mime);
  header('Content-Disposition: inline; filename="' . ($filename ?: 'remote-image') . '"; filename*=UTF-8\'\'' . rawurlencode($filename ?: 'remote-image'));
  header('Content-Length: ' . (string)filesize($dataFile));
  header('Cache-Control: private, max-age=31536000, immutable');
  header('X-Content-Type-Options: nosniff');
  readfile($dataFile);
  exit;
}

function pseServeRemoteImage(array $settings, string $token): void
{
  $request = pseReadRemoteImageToken($settings, $token);
  $record = pseRemoteImageCacheRecord(
    $settings,
    $request['folder'],
    $request['uid'],
    $request['url'],
    true
  );
  if (empty($record)) {
    http_response_code(404);
    header('Cache-Control: private, no-store, max-age=0');
    exit('Remote image unavailable.');
  }
  pseOutputRemoteImageRecord($record);
}

function pseProxyRemoteImages(
  array $settings,
  string $folder,
  string $uid,
  string $html
): string {
  if ($html === '') {
    return $html;
  }
  $proxyUrl = function (string $url) use ($settings, $folder, $uid): string {
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('#^https?://#i', $url)) {
      return $url;
    }
    return pseRemoteImageUrl($settings, $folder, $uid, $url);
  };
  $html = preg_replace_callback(
    '/(<(?:img|source|image)\b[^>]*?\b(?:src|href|xlink:href)\s*=\s*)(["\'])(https?:\/\/.*?)\2/is',
    function (array $match) use ($proxyUrl): string {
      return $match[1] . $match[2] . htmlspecialchars($proxyUrl($match[3]), ENT_QUOTES, 'UTF-8') . $match[2];
    },
    $html
  ) ?? $html;
  $html = preg_replace_callback(
    '/(\bsrcset\s*=\s*)(["\'])(.*?)\2/is',
    function (array $match) use ($proxyUrl): string {
      $items = [];
      foreach (explode(',', $match[3]) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
          continue;
        }
        $parts = preg_split('/\s+/', $candidate, 2);
        $url = (string)($parts[0] ?? '');
        $descriptor = (string)($parts[1] ?? '');
        if (preg_match('#^https?://#i', $url)) {
          $url = $proxyUrl($url);
        }
        $items[] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . ($descriptor !== '' ? ' ' . $descriptor : '');
      }
      return $match[1] . $match[2] . implode(', ', $items) . $match[2];
    },
    $html
  ) ?? $html;
  $html = preg_replace_callback(
    '/url\s*\(\s*(["\']?)(https?:\/\/[^)"\']+)\1\s*\)/i',
    function (array $match) use ($proxyUrl): string {
      return 'url("' . htmlspecialchars($proxyUrl($match[2]), ENT_QUOTES, 'UTF-8') . '")';
    },
    $html
  ) ?? $html;
  $html = preg_replace_callback(
    '/<img\b([^>]*)>/is',
    function (array $match): string {
      if (preg_match('/\bloading\s*=/i', $match[1])) {
        return $match[0];
      }
      return '<img' . $match[1] . ' loading="lazy">';
    },
    $html
  ) ?? $html;
  return $html;
}


function pseSettings(): array
{
  return pseNormalizeSettings(pseReadJson(PSE_SETTINGS_FILE));
}

function pseJson(array $data, int $status = 200): void
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
  if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
  }
  $json = json_encode($data, $flags);
  echo $json !== false
    ? $json
    : '{"ok":false,"error":"Unable to encode the server response as JSON."}';
  exit;
}

function pseBody(): array
{
  $type = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ((string)$raw === '' && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
      throw new RuntimeException(
        'The request body was rejected before PHP could read it. Check the server request-size and post_max_size limits.'
      );
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
      throw new RuntimeException('The request contains invalid JSON.');
    }
    return $data;
  }
  return $_POST;
}

function pseSetAuthCookie(string $token): void
{
  $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
  setcookie(PSE_COOKIE, $token, [
    'expires' => time() + (PSE_COOKIE_YEARS * 365 * 86400),
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}

function pseClearAuthCookie(): void
{
  $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
  setcookie(PSE_COOKIE, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}

function pseIsAuthenticated(array $settings): bool
{
  $token = (string)($_COOKIE[PSE_COOKIE] ?? '');
  if ($token === '' || empty($settings['auth_tokens']) || !is_array($settings['auth_tokens'])) {
    return false;
  }
  $hash = hash('sha256', $token);
  foreach ($settings['auth_tokens'] as $savedHash) {
    if (is_string($savedHash) && hash_equals($savedHash, $hash)) {
      return true;
    }
  }
  return false;
}

function pseLogin(array &$settings): string
{
  $token = bin2hex(random_bytes(32));
  $tokens = isset($settings['auth_tokens']) && is_array($settings['auth_tokens'])
    ? $settings['auth_tokens']
    : [];
  $tokens[] = hash('sha256', $token);
  $settings['auth_tokens'] = array_slice(array_values(array_unique($tokens)), -20);
  $settings = pseWriteSettings($settings);
  pseSetAuthCookie($token);
  return $token;
}

function pseCsrf(array $settings): string
{
  $token = (string)($_COOKIE[PSE_COOKIE] ?? '');
  return hash_hmac('sha256', 'csrf|' . $token, (string)$settings['storage_key']);
}

function pseRequireCsrf(array $settings): void
{
  $received = (string)($_SERVER['HTTP_X_PSE_CSRF'] ?? '');
  $expected = pseCsrf($settings);
  if ($received === '' || !hash_equals($expected, $received)) {
    pseJson(['ok' => false, 'error' => 'Security token expired. Reload the page.'], 419);
  }
}

function pseEncrypt(string $plain, string $key): string
{
  if ($plain === '') {
    return '';
  }
  if (!function_exists('openssl_encrypt')) {
    throw new RuntimeException('The PHP OpenSSL extension is required.');
  }
  $iv = random_bytes(12);
  $tag = '';
  $cipher = openssl_encrypt(
    $plain,
    'aes-256-gcm',
    hash('sha256', $key, true),
    OPENSSL_RAW_DATA,
    $iv,
    $tag
  );
  if ($cipher === false) {
    throw new RuntimeException('Unable to encrypt the saved password.');
  }
  return base64_encode($iv . $tag . $cipher);
}

function pseDecrypt(string $encoded, string $key): string
{
  if ($encoded === '') {
    return '';
  }
  $raw = base64_decode($encoded, true);
  if ($raw === false || strlen($raw) < 29 || !function_exists('openssl_decrypt')) {
    return '';
  }
  $iv = substr($raw, 0, 12);
  $tag = substr($raw, 12, 16);
  $cipher = substr($raw, 28);
  $plain = openssl_decrypt(
    $cipher,
    'aes-256-gcm',
    hash('sha256', $key, true),
    OPENSSL_RAW_DATA,
    $iv,
    $tag
  );
  return $plain === false ? '' : $plain;
}

function pseBase64UrlEncode(string $value): string
{
  return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pseBase64UrlDecode(string $value): string
{
  $padding = strlen($value) % 4;
  if ($padding > 0) {
    $value .= str_repeat('=', 4 - $padding);
  }
  $decoded = base64_decode(strtr($value, '-_', '+/'), true);
  return $decoded === false ? '' : $decoded;
}

function pseQueryString(array $query): string
{
  $pairs = [];
  foreach ($query as $name => $value) {
    foreach (is_array($value) ? $value : [$value] as $item) {
      $pairs[] = rawurlencode((string)$name) . '=' . rawurlencode((string)$item);
    }
  }
  return implode('&', $pairs);
}

function pseCurrentBaseUrl(): string
{
  $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
  $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
    $forwarded === 'https';
  $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
  if (!preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $host)) {
    $host = 'localhost';
  }
  $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
  if (!is_string($path) || $path === '') {
    $path = '/';
  }
  return ($https ? 'https' : 'http') . '://' . $host . $path;
}

function pseHttpRequest(
  string $method,
  string $url,
  array $headers = [],
  ?string $body = null
): array {
  $method = strtoupper($method);
  if (function_exists('curl_init')) {
    $curl = curl_init($url);
    if ($curl === false) {
      throw new RuntimeException('Unable to initialize the HTTP client.');
    }
    curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_USERAGENT => PSE_NAME . '/' . PSE_VERSION
    ]);
    if ($body !== null) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($curl);
    if ($response === false) {
      $error = curl_error($curl);
      curl_close($curl);
      throw new RuntimeException('HTTP request failed: ' . ($error ?: 'unknown error'));
    }
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $responseBody = substr((string)$response, $headerSize);
    curl_close($curl);
    return ['status' => $status, 'body' => $responseBody];
  }

  $options = [
    'http' => [
      'method' => $method,
      'header' => implode("\r\n", $headers),
      'ignore_errors' => true,
      'timeout' => 60
    ]
  ];
  if ($body !== null) {
    $options['http']['content'] = $body;
  }
  $context = stream_context_create($options);
  $responseBody = @file_get_contents($url, false, $context);
  if ($responseBody === false) {
    throw new RuntimeException('The server cannot contact Google. Enable cURL or HTTPS URL access.');
  }
  $status = 0;
  foreach ((array)($http_response_header ?? []) as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $match)) {
      $status = (int)$match[1];
    }
  }
  return ['status' => $status, 'body' => (string)$responseBody];
}

function pseHttpJson(
  string $method,
  string $url,
  array $headers = [],
  ?array $data = null,
  bool $form = false
): array {
  $body = null;
  if ($data !== null) {
    if ($form) {
      $body = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
      $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } else {
      $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if (!is_string($body)) {
        throw new RuntimeException('Unable to encode the HTTP request.');
      }
      $headers[] = 'Content-Type: application/json';
    }
  }
  $headers[] = 'Accept: application/json';
  $response = pseHttpRequest($method, $url, $headers, $body);
  $decoded = [];
  if (trim((string)$response['body']) !== '') {
    $decoded = json_decode((string)$response['body'], true);
    if (!is_array($decoded)) {
      throw new RuntimeException('Google returned an invalid response.');
    }
  }
  if ((int)$response['status'] < 200 || (int)$response['status'] >= 300) {
    $message = (string)(
      $decoded['error']['message'] ??
      $decoded['error_description'] ??
      $decoded['error'] ??
      ('HTTP ' . (int)$response['status'])
    );
    throw new RuntimeException(
      'Google request failed (HTTP ' . (int)$response['status'] . '): ' . $message
    );
  }
  return $decoded;
}

function pseIsGmailAccount(array $settings): bool
{
  return (string)($settings['account_type'] ?? 'imap') === 'gmail';
}

function pseGoogleOAuthState(array $settings): string
{
  $payload = json_encode([
    'account' => (string)$settings['account_id'],
    'expires' => time() + 600,
    'nonce' => bin2hex(random_bytes(16))
  ], JSON_UNESCAPED_SLASHES);
  if (!is_string($payload)) {
    throw new RuntimeException('Unable to create the Google authorization state.');
  }
  $encoded = pseBase64UrlEncode($payload);
  $signature = hash_hmac('sha256', $encoded, (string)$settings['storage_key']);
  return $encoded . '.' . $signature;
}

function pseVerifyGoogleOAuthState(array $settings, string $state): string
{
  $parts = explode('.', $state, 2);
  if (count($parts) !== 2) {
    throw new RuntimeException('Invalid Google authorization state.');
  }
  $expected = hash_hmac('sha256', $parts[0], (string)$settings['storage_key']);
  if (!hash_equals($expected, $parts[1])) {
    throw new RuntimeException('Google authorization state verification failed.');
  }
  $payload = json_decode(pseBase64UrlDecode($parts[0]), true);
  if (!is_array($payload) || (int)($payload['expires'] ?? 0) < time()) {
    throw new RuntimeException('Google authorization has expired. Start it again.');
  }
  $accountId = pseSafeAccountId((string)($payload['account'] ?? ''));
  if ($accountId === '' || !isset($settings['accounts'][$accountId])) {
    throw new RuntimeException('The Google authorization account no longer exists.');
  }
  return $accountId;
}

function pseGoogleAuthorizationUrl(array &$settings): string
{
  if (!pseIsGmailAccount($settings)) {
    throw new RuntimeException('Select a Gmail account first.');
  }
  $clientId = trim((string)$settings['google_client_id']);
  $clientSecret = pseDecrypt(
    (string)$settings['google_client_secret_enc'],
    (string)$settings['storage_key']
  );
  if ($clientId === '' || $clientSecret === '') {
    throw new RuntimeException('Save the Google OAuth Client ID and Client Secret first.');
  }
  $state = pseGoogleOAuthState($settings);
  $pendingHash = hash('sha256', $state);
  $pendingExpiresAt = time() + 600;
  $settings = psePatchGoogleOAuthAccount(
    (string)$settings['account_id'],
    function (array $account) use ($pendingHash, $pendingExpiresAt): array {
      $account['google_oauth_pending_hash'] = $pendingHash;
      $account['google_oauth_pending_expires_at'] = $pendingExpiresAt;
      return $account;
    }
  );
  $query = [
    'client_id' => $clientId,
    'redirect_uri' => pseCurrentBaseUrl() . '?google_oauth=callback',
    'response_type' => 'code',
    'scope' => 'https://mail.google.com/ https://www.googleapis.com/auth/gmail.modify https://www.googleapis.com/auth/gmail.send',
    'access_type' => 'offline',
    'prompt' => 'consent select_account',
    'include_granted_scopes' => 'true',
    'state' => $state
  ];
  $hint = trim((string)($settings['google_oauth_email'] ?: $settings['from_email']));
  if ($hint !== '') {
    $query['login_hint'] = $hint;
  }
  return 'https://accounts.google.com/o/oauth2/v2/auth?' .
    http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function pseGoogleReconnectMessage(): string
{
  return 'Google authorization has expired or been revoked. Reconnect this Gmail account in Settings. ' .
    'If the OAuth consent screen is still in Testing, change its publishing status to In production ' .
    'before reconnecting; otherwise Google may expire the refresh token after 7 days.';
}

function pseGoogleRefreshTokenWasRejected(Throwable $error): bool
{
  $message = strtolower($error->getMessage());
  return strpos($message, 'invalid_grant') !== false ||
    strpos($message, 'expired or revoked') !== false ||
    strpos($message, 'token has been revoked') !== false;
}

function pseLatestGoogleAccountSettings(array $settings): array
{
  $accountId = pseSafeAccountId((string)($settings['account_id'] ?? ''));
  if ($accountId === '') {
    return $settings;
  }
  $latest = pseSettingsForAccount(pseSettings(), $accountId);
  return $latest !== null ? $latest : $settings;
}

function pseStoreGoogleAccessToken(
  array $settings,
  string $expectedRefreshToken,
  string $accessToken,
  int $expiresAt
): void {
  $accountId = (string)$settings['account_id'];
  psePatchGoogleOAuthAccount(
    $accountId,
    function (array $account, array $current) use (
      $expectedRefreshToken,
      $accessToken,
      $expiresAt
    ): array {
      $currentRefreshToken = pseDecrypt(
        (string)$account['google_refresh_token_enc'],
        (string)$current['storage_key']
      );
      if (
        $currentRefreshToken === '' ||
        !hash_equals($currentRefreshToken, $expectedRefreshToken)
      ) {
        // A newer authorization won the race. Never overwrite its tokens.
        return $account;
      }
      $account['google_access_token_enc'] = pseEncrypt(
        $accessToken,
        (string)$current['storage_key']
      );
      $account['google_access_token_expires_at'] = $expiresAt;
      $account['google_oauth_reconnect_required'] = false;
      return $account;
    }
  );
}

function pseMarkGoogleReconnectRequired(array $settings, string $expectedRefreshToken): void
{
  $accountId = (string)$settings['account_id'];
  psePatchGoogleOAuthAccount(
    $accountId,
    function (array $account, array $current) use ($expectedRefreshToken): array {
      $currentRefreshToken = pseDecrypt(
        (string)$account['google_refresh_token_enc'],
        (string)$current['storage_key']
      );
      if (
        $currentRefreshToken === '' ||
        !hash_equals($currentRefreshToken, $expectedRefreshToken)
      ) {
        // The user has already reconnected in another request/tab.
        return $account;
      }
      $account['google_access_token_enc'] = '';
      $account['google_access_token_expires_at'] = 0;
      $account['google_oauth_reconnect_required'] = true;
      return $account;
    }
  );
}

function pseGoogleAccessToken(array $settings, bool $forceRefresh = false): string
{
  static $tokens = [];
  $settings = pseLatestGoogleAccountSettings($settings);
  $accountId = (string)$settings['account_id'];
  if (!empty($settings['google_oauth_reconnect_required'])) {
    throw new PseGoogleReconnectRequiredException(pseGoogleReconnectMessage());
  }
  if (!$forceRefresh && isset($tokens[$accountId])) {
    return $tokens[$accountId];
  }
  $savedToken = pseDecrypt(
    (string)$settings['google_access_token_enc'],
    (string)$settings['storage_key']
  );
  if (
    !$forceRefresh &&
    $savedToken !== '' &&
    (int)$settings['google_access_token_expires_at'] > time() + 90
  ) {
    $tokens[$accountId] = $savedToken;
    return $savedToken;
  }

  for ($credentialAttempt = 0; $credentialAttempt < 2; $credentialAttempt++) {
    $refreshToken = pseDecrypt(
      (string)$settings['google_refresh_token_enc'],
      (string)$settings['storage_key']
    );
    $clientSecret = pseDecrypt(
      (string)$settings['google_client_secret_enc'],
      (string)$settings['storage_key']
    );
    if ($refreshToken === '' || trim((string)$settings['google_client_id']) === '' || $clientSecret === '') {
      throw new PseGoogleReconnectRequiredException(
        'Connect this Gmail account with Google OAuth2 in Settings.'
      );
    }
    try {
      $token = pseHttpJson(
        'POST',
        'https://oauth2.googleapis.com/token',
        [],
        [
          'client_id' => (string)$settings['google_client_id'],
          'client_secret' => $clientSecret,
          'refresh_token' => $refreshToken,
          'grant_type' => 'refresh_token'
        ],
        true
      );
    } catch (Throwable $error) {
      if (!pseGoogleRefreshTokenWasRejected($error)) {
        throw $error;
      }
      $latestSettings = pseLatestGoogleAccountSettings($settings);
      $latestRefreshToken = pseDecrypt(
        (string)$latestSettings['google_refresh_token_enc'],
        (string)$latestSettings['storage_key']
      );
      if (
        $credentialAttempt === 0 &&
        $latestRefreshToken !== '' &&
        !hash_equals($refreshToken, $latestRefreshToken) &&
        empty($latestSettings['google_oauth_reconnect_required'])
      ) {
        // Another request completed a fresh Google authorization. Retry with it once.
        $settings = $latestSettings;
        continue;
      }
      pseMarkGoogleReconnectRequired($settings, $refreshToken);
      throw new PseGoogleReconnectRequiredException(pseGoogleReconnectMessage());
    }
    $accessToken = (string)($token['access_token'] ?? '');
    if ($accessToken === '') {
      throw new RuntimeException('Google did not return an access token.');
    }
    $expiresAt = time() + max(60, (int)($token['expires_in'] ?? 3600));
    pseStoreGoogleAccessToken($settings, $refreshToken, $accessToken, $expiresAt);
    $tokens[$accountId] = $accessToken;
    return $accessToken;
  }
  throw new PseGoogleReconnectRequiredException(pseGoogleReconnectMessage());
}

function pseGoogleApi(
  array $settings,
  string $method,
  string $path,
  array $query = [],
  ?array $data = null
): array {
  $url = 'https://gmail.googleapis.com/gmail/v1/users/me/' . ltrim($path, '/');
  if (!empty($query)) {
    $url .= '?' . pseQueryString($query);
  }
  for ($attempt = 0; $attempt < 2; $attempt++) {
    $token = pseGoogleAccessToken($settings, $attempt === 1);
    try {
      return pseHttpJson($method, $url, ['Authorization: Bearer ' . $token], $data);
    } catch (RuntimeException $error) {
      if ($attempt === 0 && strpos($error->getMessage(), '401') !== false) {
        continue;
      }
      throw $error;
    }
  }
  throw new RuntimeException('Google authentication failed.');
}

function pseHandleGoogleOAuthCallback(array $settings): void
{
  $restorePseLogin = !pseIsAuthenticated($settings);
  if (!empty($_GET['error'])) {
    throw new RuntimeException('Google authorization was cancelled: ' . (string)$_GET['error']);
  }
  $returnedState = (string)($_GET['state'] ?? '');
  $accountId = pseVerifyGoogleOAuthState($settings, $returnedState);
  $settings = pseSwitchAccount($settings, $accountId);
  $previousGoogleEmail = strtolower(trim((string)(
    $settings['google_oauth_email'] ?: $settings['imap_username']
  )));
  if (!pseIsGmailAccount($settings)) {
    throw new RuntimeException('The selected account is not a Gmail account.');
  }
  $pendingHash = (string)($settings['google_oauth_pending_hash'] ?? '');
  $pendingExpires = (int)($settings['google_oauth_pending_expires_at'] ?? 0);
  if (
    $pendingHash === '' ||
    $pendingExpires < time() ||
    !hash_equals($pendingHash, hash('sha256', $returnedState))
  ) {
    throw new RuntimeException('This Google authorization request is no longer valid. Start it again from Settings.');
  }
  // Consume the authorization request before exchanging the code so the callback cannot be replayed.
  $settings = psePatchGoogleOAuthAccount(
    $accountId,
    function (array $account): array {
      $account['google_oauth_pending_hash'] = '';
      $account['google_oauth_pending_expires_at'] = 0;
      return $account;
    }
  );
  $clientSecret = pseDecrypt(
    (string)$settings['google_client_secret_enc'],
    (string)$settings['storage_key']
  );
  $token = pseHttpJson(
    'POST',
    'https://oauth2.googleapis.com/token',
    [],
    [
      'code' => (string)($_GET['code'] ?? ''),
      'client_id' => (string)$settings['google_client_id'],
      'client_secret' => $clientSecret,
      'redirect_uri' => pseCurrentBaseUrl() . '?google_oauth=callback',
      'grant_type' => 'authorization_code'
    ],
    true
  );
  $accessToken = (string)($token['access_token'] ?? '');
  if ($accessToken === '') {
    throw new RuntimeException('Google did not return an access token.');
  }
  $refreshToken = (string)($token['refresh_token'] ?? '');
  if ($refreshToken === '' && (string)$settings['google_refresh_token_enc'] === '') {
    throw new RuntimeException('Google did not return a refresh token. Revoke the app grant and connect again.');
  }
  $profile = pseHttpJson(
    'GET',
    'https://gmail.googleapis.com/gmail/v1/users/me/profile',
    ['Authorization: Bearer ' . $accessToken]
  );
  $email = trim((string)($profile['emailAddress'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Google did not return a valid Gmail address.');
  }
  if ($previousGoogleEmail !== '' && $previousGoogleEmail !== strtolower($email)) {
    pseMailCacheClearAccountAssets($settings);
    pseMailCacheClearAccount($settings);
  }
  $accessTokenExpiresAt = time() + max(60, (int)($token['expires_in'] ?? 3600));
  $settings = psePatchGoogleOAuthAccount(
    $accountId,
    function (array $account, array $current) use (
      $refreshToken,
      $accessToken,
      $accessTokenExpiresAt,
      $email
    ): array {
      if ($refreshToken !== '') {
        $account['google_refresh_token_enc'] = pseEncrypt(
          $refreshToken,
          (string)$current['storage_key']
        );
      }
      $account['google_access_token_enc'] = pseEncrypt(
        $accessToken,
        (string)$current['storage_key']
      );
      $account['google_access_token_expires_at'] = $accessTokenExpiresAt;
      $account['google_oauth_reconnect_required'] = false;
      $account['google_oauth_email'] = $email;
      $account['imap_username'] = $email;
      $account['smtp_username'] = $email;
      $account['from_email'] = $email;
      return $account;
    }
  );
  if ($restorePseLogin) {
    pseLogin($settings);
  } elseif (!empty($_COOKIE[PSE_COOKIE])) {
    pseSetAuthCookie((string)$_COOKIE[PSE_COOKIE]);
  }
  header('Location: ' . pseCurrentBaseUrl() . '?google_oauth=success');
  exit;
}

function pseIsReadableAttachment(string $mime): bool
{
  return in_array(strtolower($mime), [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/x-png',
    'image/gif',
    'image/webp',
    'image/avif',
    'image/bmp'
  ], true);
}

function pseCacheAttachment(
  array $settings,
  string $folder,
  string $uid,
  string $part,
  string $filename,
  string $mime,
  string $content
): array {
  pseEnsureStorage();
  $key = pseAttachmentCacheKey($settings, $folder, (string)$uid, $part);
  $dataFile = PSE_CACHE_DIR . '/' . $key . '.bin';
  $metaFile = PSE_CACHE_DIR . '/' . $key . '.json';
  if (!is_file($dataFile) || (int)filesize($dataFile) !== strlen($content)) {
    $tmp = $dataFile . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $dataFile)) {
      @unlink($tmp);
      throw new RuntimeException('Unable to cache attachment ' . $filename . '.');
    }
    @chmod($dataFile, 0640);
  }
  pseWriteJson($metaFile, [
    'filename' => $filename,
    'mime' => $mime,
    'size' => strlen($content),
    'cachedAt' => gmdate('c'),
    'accountId' => (string)($settings['account_id'] ?? ''),
    'folder' => $folder,
    'uid' => (string)$uid,
    'part' => $part
  ]);
  pseMailCacheRegisterAsset($settings, $folder, (string)$uid, $key);
  $signature = substr(
    hash_hmac('sha256', 'attachment|' . $key, (string)$settings['storage_key']),
    0,
    40
  );
  return [
    'url' => '?cached_attachment=' . rawurlencode($key . '.' . $signature),
    'previewable' => pseIsReadableAttachment($mime)
  ];
}

function pseServeCachedAttachment(array $settings, string $token, bool $download): void
{
  if (!pseIsAuthenticated($settings)) {
    http_response_code(401);
    exit('Authentication required.');
  }
  $parts = explode('.', $token, 2);
  $key = $parts[0] ?? '';
  $signature = $parts[1] ?? '';
  if (!preg_match('/^[a-f0-9]{64}$/', $key) || !preg_match('/^[a-f0-9]{40}$/', $signature)) {
    http_response_code(404);
    exit('Attachment not found.');
  }
  $expected = substr(
    hash_hmac('sha256', 'attachment|' . $key, (string)$settings['storage_key']),
    0,
    40
  );
  if (!hash_equals($expected, $signature)) {
    http_response_code(404);
    exit('Attachment not found.');
  }
  $dataFile = PSE_CACHE_DIR . '/' . $key . '.bin';
  $meta = pseReadJson(PSE_CACHE_DIR . '/' . $key . '.json');
  if (!is_file($dataFile) || empty($meta)) {
    http_response_code(404);
    exit('Attachment cache expired. Open the email again.');
  }
  $filename = str_replace(["\r", "\n", '"'], '', basename((string)($meta['filename'] ?? 'attachment.bin')));
  $mime = preg_replace('/[^a-z0-9.+-\/]/i', '', (string)($meta['mime'] ?? 'application/octet-stream'));
  $inline = !$download && pseIsReadableAttachment($mime);
  header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') .
    '; filename="' . ($filename ?: 'attachment.bin') . '"; filename*=UTF-8\'\'' . rawurlencode($filename ?: 'attachment.bin'));
  header('Content-Length: ' . (string)filesize($dataFile));
  header('Cache-Control: private, max-age=86400');
  header('X-Content-Type-Options: nosniff');
  readfile($dataFile);
  exit;
}

function pseAttachmentToken(
  array $settings,
  string $purpose,
  string $folder,
  string $uid,
  string $part = ''
): string {
  $payload = pseBase64UrlEncode((string)json_encode([
    'account' => (string)($settings['account_id'] ?? ''),
    'folder' => $folder,
    'uid' => $uid,
    'part' => $part
  ], JSON_UNESCAPED_SLASHES));
  $signature = substr(
    hash_hmac('sha256', $purpose . '|' . $payload, (string)$settings['storage_key']),
    0,
    40
  );
  return $payload . '.' . $signature;
}

function pseAttachmentUrl(
  array $settings,
  string $folder,
  string $uid,
  string $part
): string {
  return '?attachment_download=' . rawurlencode(
    pseAttachmentToken($settings, 'attachment', $folder, $uid, $part)
  );
}

function pseAttachmentDownloadAllUrl(
  array $settings,
  string $folder,
  string $uid
): string {
  return '?attachment_download_all=' . rawurlencode(
    pseAttachmentToken($settings, 'attachment-all', $folder, $uid)
  );
}

function pseReadAttachmentToken(array $settings, string $purpose, string $token): array
{
  if (!pseIsAuthenticated($settings)) {
    throw new RuntimeException('Authentication required.');
  }
  $parts = explode('.', $token, 2);
  $payload = (string)($parts[0] ?? '');
  $signature = (string)($parts[1] ?? '');
  if (
    $payload === '' ||
    !preg_match('/^[a-zA-Z0-9_-]+$/', $payload) ||
    !preg_match('/^[a-f0-9]{40}$/', $signature)
  ) {
    throw new RuntimeException('Invalid attachment link.');
  }
  $expected = substr(
    hash_hmac('sha256', $purpose . '|' . $payload, (string)$settings['storage_key']),
    0,
    40
  );
  if (!hash_equals($expected, $signature)) {
    throw new RuntimeException('Invalid attachment link.');
  }
  $data = json_decode(pseBase64UrlDecode($payload), true);
  if (
    !is_array($data) ||
    (string)($data['account'] ?? '') !== (string)($settings['account_id'] ?? '') ||
    trim((string)($data['folder'] ?? '')) === '' ||
    trim((string)($data['uid'] ?? '')) === ''
  ) {
    throw new RuntimeException('Attachment link has expired.');
  }
  return [
    'folder' => (string)$data['folder'],
    'uid' => (string)$data['uid'],
    'part' => (string)($data['part'] ?? '')
  ];
}

function pseGmailHeaders(array $payload): array
{
  $headers = [];
  foreach ((array)($payload['headers'] ?? []) as $header) {
    if (!is_array($header)) {
      continue;
    }
    $name = strtolower(trim((string)($header['name'] ?? '')));
    if ($name !== '') {
      $headers[$name] = (string)($header['value'] ?? '');
    }
  }
  return $headers;
}

function pseSearchContext(string $text, string $needle): array
{
  $needle = trim($needle);
  if ($needle === '') {
    return [];
  }
  $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text;
  $text = preg_replace('/<\/(?:p|div|li|tr|h[1-6])\s*>/i', "\n", $text) ?? $text;
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
  $rawLines = preg_split('/\n+/', $text) ?: [];
  $lines = [];
  foreach ($rawLines as $line) {
    $line = trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line);
    if ($line !== '') {
      $lines[] = $line;
    }
  }
  if (empty($lines)) {
    return ['', '', ''];
  }
  $matchIndex = null;
  foreach ($lines as $index => $line) {
    $position = function_exists('mb_stripos')
      ? mb_stripos($line, $needle, 0, 'UTF-8')
      : stripos($line, $needle);
    if ($position !== false) {
      $matchIndex = $index;
      break;
    }
  }
  if ($matchIndex === null) {
    $combined = implode(' ', $lines);
    $lines = [$combined];
    $matchIndex = 0;
  }
  $crop = function (string $line, bool $matched) use ($needle): string {
    $max = 240;
    $length = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
    if ($length <= $max) {
      return $line;
    }
    $position = $matched
      ? (function_exists('mb_stripos') ? mb_stripos($line, $needle, 0, 'UTF-8') : stripos($line, $needle))
      : false;
    $start = $position === false ? 0 : max(0, (int)$position - 85);
    $excerpt = function_exists('mb_substr')
      ? mb_substr($line, $start, $max, 'UTF-8')
      : substr($line, $start, $max);
    return ($start > 0 ? '…' : '') . $excerpt . ($start + $max < $length ? '…' : '');
  };
  return [
    $matchIndex > 0 ? $crop($lines[$matchIndex - 1], false) : '',
    $crop($lines[$matchIndex], true),
    isset($lines[$matchIndex + 1]) ? $crop($lines[$matchIndex + 1], false) : ''
  ];
}

function pseGmailSearchText(array $part): string
{
  $chunks = [];
  foreach ((array)($part['parts'] ?? []) as $child) {
    if (is_array($child)) {
      $childText = pseGmailSearchText($child);
      if ($childText !== '') {
        $chunks[] = $childText;
      }
    }
  }
  $mime = strtolower((string)($part['mimeType'] ?? ''));
  $data = (string)($part['body']['data'] ?? '');
  if (($mime === 'text/plain' || $mime === 'text/html') && $data !== '') {
    $decoded = pseBase64UrlDecode($data);
    if ($mime === 'text/html') {
      $decoded = preg_replace('/<\s*br\s*\/?>/i', "\n", $decoded) ?? $decoded;
      $decoded = strip_tags($decoded);
    }
    if (trim($decoded) !== '') {
      $chunks[] = $decoded;
    }
  }
  return implode("\n", $chunks);
}

function pseGmailPartContent(array $settings, string $messageId, array $part): string
{
  $data = (string)($part['body']['data'] ?? '');
  if ($data !== '') {
    return pseBase64UrlDecode($data);
  }
  $attachmentId = (string)($part['body']['attachmentId'] ?? '');
  if ($attachmentId === '') {
    return '';
  }
  $attachment = pseGoogleApi(
    $settings,
    'GET',
    'messages/' . rawurlencode($messageId) . '/attachments/' . rawurlencode($attachmentId)
  );
  return pseBase64UrlDecode((string)($attachment['data'] ?? ''));
}

function pseGmailCollectParts(
  array $settings,
  string $messageId,
  array $part,
  array &$out,
  bool $loadText = true
): void {
  foreach ((array)($part['parts'] ?? []) as $child) {
    if (is_array($child)) {
      pseGmailCollectParts($settings, $messageId, $child, $out, $loadText);
    }
  }
  $mime = strtolower((string)($part['mimeType'] ?? 'application/octet-stream'));
  if (strpos($mime, 'multipart/') === 0) {
    return;
  }
  $headers = pseGmailHeaders($part);
  $filename = pseMime((string)($part['filename'] ?? ''));
  $disposition = strtolower((string)($headers['content-disposition'] ?? ''));
  $cid = trim((string)($headers['content-id'] ?? ''), '<>');
  $isInline = strpos($disposition, 'inline') !== false || $cid !== '';
  $isAttachment = $filename !== '' || strpos($disposition, 'attachment') !== false;

  // Some legitimate multipart/alternative messages assign a Content-ID to
  // their text bodies. Content-ID alone does not make a text part an attachment.
  if (!$isAttachment && ($mime === 'text/plain' || $mime === 'text/html')) {
    if (!$loadText) {
      return;
    }
    $content = pseGmailPartContent($settings, $messageId, $part);
    $charset = 'UTF-8';
    if (preg_match('/charset\s*=\s*["\']?([^;"\']+)/i', (string)($headers['content-type'] ?? ''), $match)) {
      $charset = trim($match[1]);
    }
    if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('iconv')) {
      $converted = @iconv($charset, 'UTF-8//IGNORE', $content);
      if ($converted !== false) {
        $content = $converted;
      }
    }
    if ($mime === 'text/html' && $out['html'] === '') {
      $out['html'] = $content;
    } elseif ($mime === 'text/plain' && $out['plain'] === '') {
      $out['plain'] = $content;
    }
    return;
  }

  if ($isAttachment || $isInline) {
    $partId = (string)($part['partId'] ?? '');
    if ($partId === '') {
      $partId = '0';
    }
    if ($filename === '') {
      $filename = 'attachment-' . str_replace('.', '-', $partId);
    }
    $attachment = [
      'part' => $partId,
      'filename' => $filename,
      'mime' => $mime,
      'size' => (int)($part['body']['size'] ?? 0),
      'inline' => $isInline,
      'cid' => $cid,
      'previewable' => pseIsReadableAttachment($mime)
    ];
    $out['attachments'][] = $attachment;
  }
}

function pseGmailFindPart(array $part, string $partNo): ?array
{
  $currentPart = (string)($part['partId'] ?? '');
  if ($currentPart === $partNo || ($partNo === '0' && $currentPart === '')) {
    return $part;
  }
  foreach ((array)($part['parts'] ?? []) as $child) {
    if (!is_array($child)) {
      continue;
    }
    $found = pseGmailFindPart($child, $partNo);
    if ($found !== null) {
      return $found;
    }
  }
  return null;
}

function pseGmailFolders(array $settings): array
{
  $response = pseGoogleApi($settings, 'GET', 'labels');
  $folders = [];
  $ignored = [
    'CHAT', 'IMPORTANT', 'STARRED', 'UNREAD',
    'CATEGORY_PERSONAL', 'CATEGORY_SOCIAL', 'CATEGORY_PROMOTIONS',
    'CATEGORY_UPDATES', 'CATEGORY_FORUMS'
  ];
  foreach ((array)($response['labels'] ?? []) as $label) {
    if (!is_array($label)) {
      continue;
    }
    $id = (string)($label['id'] ?? '');
    if ($id === '' || in_array($id, $ignored, true)) {
      continue;
    }
    if (!empty($settings['hide_useless_gmail_folders'])) {
      $uselessFolders = ['yellow_star', 'scheduled', 'notes'];
      $labelId = strtolower(trim($id));
      $labelName = strtolower(trim((string)($label['name'] ?? '')));
      if (in_array($labelId, $uselessFolders, true) || in_array($labelName, $uselessFolders, true)) {
        continue;
      }
    }
    $detail = pseGoogleApi($settings, 'GET', 'labels/' . rawurlencode($id));
    $special = 'folder';
    if ($id === 'INBOX') {
      $special = 'inbox';
    } elseif ($id === 'SENT') {
      $special = 'sent';
    } elseif ($id === 'DRAFT') {
      $special = 'drafts';
    } elseif ($id === 'TRASH') {
      $special = 'trash';
    } elseif ($id === 'SPAM') {
      $special = 'spam';
    } elseif ($id === 'ALL') {
      $special = 'archive';
    }
    $name = (string)($detail['name'] ?? $label['name'] ?? $id);
    $folders[] = [
      'id' => $id,
      'name' => pseFolderLabel($name),
      'delimiter' => '/',
      'messages' => (int)($detail['messagesTotal'] ?? 0),
      'unseen' => (int)($detail['messagesUnread'] ?? 0),
      'special' => $special
    ];
  }
  usort($folders, function (array $a, array $b): int {
    $order = ['inbox' => 0, 'sent' => 1, 'drafts' => 2, 'archive' => 3, 'spam' => 8, 'trash' => 9, 'folder' => 5];
    $oa = $order[$a['special']] ?? 5;
    $ob = $order[$b['special']] ?? 5;
    return $oa === $ob ? strcasecmp($a['name'], $b['name']) : ($oa <=> $ob);
  });
  return $folders;
}

function pseListPreviewText(string $text, int $maxChars = 1400): string
{
  if ($text === '') {
    return '';
  }
  $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text;
  $text = preg_replace('/<\/(?:p|div|li|tr|h[1-6])\s*>/i', "\n", $text) ?? $text;
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
  $lines = [];
  foreach (preg_split('/\n+/', $text) ?: [] as $line) {
    $line = trim(preg_replace('/[ \t]+/', ' ', (string)$line) ?? (string)$line);
    if ($line !== '') {
      $lines[] = $line;
    }
  }
  $text = trim(implode("\n", $lines));
  if ($text === '') {
    return '';
  }
  $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
  if ($length <= $maxChars) {
    return $text;
  }
  $cropped = function_exists('mb_substr')
    ? mb_substr($text, 0, $maxChars, 'UTF-8')
    : substr($text, 0, $maxChars);
  return rtrim((string)$cropped) . '…';
}

function pseNormalizeAttachmentFilter(string $filter): string
{
  $filter = strtolower(trim($filter));
  return $filter === 'with' ? 'with' : 'all';
}

function pseNormalizeCalendarDate(string $date): string
{
  $date = trim($date);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return '';
  }
  $parts = array_map('intval', explode('-', $date));
  return checkdate($parts[1], $parts[2], $parts[0]) ? $date : '';
}

function pseNormalizeCalendarMonth(string $month): string
{
  $month = trim($month);
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    return '';
  }
  $parts = array_map('intval', explode('-', $month));
  return $parts[0] >= 1970 && $parts[0] <= 9999 && $parts[1] >= 1 && $parts[1] <= 12
    ? sprintf('%04d-%02d', $parts[0], $parts[1])
    : '';
}

function pseSettingsTimezone(array $settings): DateTimeZone
{
  try {
    return new DateTimeZone((string)($settings['timezone'] ?? 'UTC'));
  } catch (Throwable $error) {
    return new DateTimeZone('UTC');
  }
}

function pseCalendarDayKey(int $timestamp, DateTimeZone $timezone): string
{
  if ($timestamp <= 0) {
    return '';
  }
  return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('Y-m-d');
}

function pseGmailAttachmentCount(array $part): int
{
  $count = 0;
  foreach ((array)($part['parts'] ?? []) as $child) {
    if (is_array($child)) {
      $count += pseGmailAttachmentCount($child);
    }
  }

  $mime = strtolower((string)($part['mimeType'] ?? ''));
  if (strpos($mime, 'multipart/') === 0) {
    return $count;
  }
  $headers = pseGmailHeaders($part);
  $filename = trim(pseMime((string)($part['filename'] ?? '')));
  $disposition = strtolower((string)($headers['content-disposition'] ?? ''));
  $cid = trim((string)($headers['content-id'] ?? ''), '<>');
  $isInline = strpos($disposition, 'inline') !== false || $cid !== '';
  $isAttachment = $filename !== '' || strpos($disposition, 'attachment') !== false;
  return $count + (($isAttachment || $isInline) ? 1 : 0);
}

function pseImapAttachmentCount($part): int
{
  if (!$part) {
    return 0;
  }
  $count = 0;
  if (!empty($part->parts) && is_array($part->parts)) {
    foreach ($part->parts as $child) {
      $count += pseImapAttachmentCount($child);
    }
  }
  $params = psePartParameters($part);
  $disposition = strtolower((string)($part->disposition ?? ''));
  $cid = trim((string)($part->id ?? ''), '<>');
  $hasFilename = trim((string)($params['filename'] ?? $params['name'] ?? '')) !== '';
  $isAttachment = $hasFilename || $cid !== '' || $disposition === 'attachment' || $disposition === 'inline';
  return $count + ($isAttachment ? 1 : 0);
}

function pseMailCacheAttachmentCountsFile(array $settings, string $folder): string
{
  return pseMailCacheAccountDirectory($settings) . '/indexes/attachment-counts-' .
    hash('sha256', $folder) . '.json';
}

function pseMailCacheAttachmentCounts(array $settings, string $folder): array
{
  $counts = [];
  foreach (pseReadJson(pseMailCacheAttachmentCountsFile($settings, $folder), []) as $uid => $count) {
    $uid = trim((string)$uid);
    if ($uid !== '') {
      $counts[$uid] = max(0, (int)$count);
    }
  }

  // Import counts from older list caches once, so upgrading does not require
  // re-reading attachment structures that are already known.
  $directory = pseMailCacheAccountDirectory($settings) . '/lists';
  foreach (glob($directory . '/*.json') ?: [] as $file) {
    $envelope = pseReadJson($file, []);
    if ((string)($envelope['folder'] ?? '') !== $folder) {
      continue;
    }
    foreach ((array)($envelope['data']['messages'] ?? []) as $message) {
      if (!is_array($message) || !array_key_exists('attachmentCount', $message)) {
        continue;
      }
      $uid = trim((string)($message['uid'] ?? ''));
      if ($uid !== '' && !array_key_exists($uid, $counts)) {
        $counts[$uid] = max(0, (int)$message['attachmentCount']);
      }
    }
  }
  return $counts;
}

function pseMailCacheStoreAttachmentCounts(array $settings, string $folder, array $messages): void
{
  $counts = pseMailCacheAttachmentCounts($settings, $folder);
  $changed = false;
  foreach ($messages as $message) {
    if (!is_array($message) || !array_key_exists('attachmentCount', $message)) {
      continue;
    }
    $uid = trim((string)($message['uid'] ?? ''));
    if ($uid === '') {
      continue;
    }
    $count = max(0, (int)$message['attachmentCount']);
    if (!array_key_exists($uid, $counts) || (int)$counts[$uid] !== $count) {
      $counts[$uid] = $count;
      $changed = true;
    }
  }
  if ($changed) {
    pseWriteJson(pseMailCacheAttachmentCountsFile($settings, $folder), $counts);
  }
}

function pseMailCacheRemoveAttachmentCounts(array $settings, string $folder, array $uids): void
{
  $file = pseMailCacheAttachmentCountsFile($settings, $folder);
  $counts = pseReadJson($file, []);
  if (!$counts) {
    return;
  }
  $changed = false;
  foreach ($uids as $uid) {
    $uid = trim((string)$uid);
    if ($uid !== '' && array_key_exists($uid, $counts)) {
      unset($counts[$uid]);
      $changed = true;
    }
  }
  if ($changed) {
    pseWriteJson($file, $counts);
  }
}

function pseMailCacheClearAttachmentCounts(array $settings, string $folder): void
{
  @unlink(pseMailCacheAttachmentCountsFile($settings, $folder));
}

function pseGmailMessageList(
  array $settings,
  string $folder,
  int $page,
  string $search,
  string $senderFilter = '',
  bool $unreadOnly = false,
  string $sortOrder = 'desc',
  array $cachedAttachmentCounts = [],
  string $attachmentFilter = 'all',
  string $startDate = ''
): array {
  $page = max(1, $page);
  $perPage = max(10, min(200, (int)$settings['items_per_page']));
  $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $startDate = pseNormalizeCalendarDate($startDate);
  $senderFilter = strtolower(trim($senderFilter));
  $previewRows = max(0, min(5, (int)($settings['email_preview_rows'] ?? 0)));
  $showAttachmentPill = !empty($settings['show_attachment_pill']);
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }
  $gmailQueryParts = [];
  if ($search !== '') {
    $gmailQueryParts[] = $search;
  }
  if ($senderFilter !== '') {
    $gmailQueryParts[] = 'from:' . $senderFilter;
  }
  if ($unreadOnly) {
    $gmailQueryParts[] = 'is:unread';
  }
  if ($attachmentFilter === 'with') {
    $gmailQueryParts[] = 'has:attachment';
  }
  if ($startDate !== '') {
    $timezone = pseSettingsTimezone($settings);
    $anchorStart = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
    if ($sortOrder === 'asc') {
      $gmailQueryParts[] = 'after:' . max(0, $anchorStart->getTimestamp() - 1);
    } else {
      $gmailQueryParts[] = 'before:' . $anchorStart->modify('+1 day')->getTimestamp();
    }
  }
  $gmailQuery = implode(' ', $gmailQueryParts);
  $list = [];
  $totalFromIds = null;

  if ($sortOrder === 'asc') {
    // Gmail has no oldest-first parameter. Read the lightweight ID pages, reverse them,
    // and only fetch metadata for the requested oldest-first page.
    $allItems = [];
    $token = '';
    do {
      $query = [
        'labelIds' => $folder,
        'maxResults' => 500
      ];
      if ($gmailQuery !== '') {
        $query['q'] = $gmailQuery;
      }
      if ($token !== '') {
        $query['pageToken'] = $token;
      }
      $batch = pseGoogleApi($settings, 'GET', 'messages', $query);
      foreach ((array)($batch['messages'] ?? []) as $item) {
        if (is_array($item) && trim((string)($item['id'] ?? '')) !== '') {
          $allItems[] = $item;
        }
      }
      $token = (string)($batch['nextPageToken'] ?? '');
    } while ($token !== '');

    $totalFromIds = count($allItems);
    $allItems = array_reverse($allItems);
    $list = [
      'messages' => array_slice($allItems, ($page - 1) * $perPage, $perPage),
      'resultSizeEstimate' => $totalFromIds
    ];
  } else {
    $token = '';
    for ($currentPage = 1; $currentPage <= $page; $currentPage++) {
      $query = [
        'labelIds' => $folder,
        'maxResults' => $perPage
      ];
      if ($gmailQuery !== '') {
        $query['q'] = $gmailQuery;
      }
      if ($token !== '') {
        $query['pageToken'] = $token;
      }
      $list = pseGoogleApi($settings, 'GET', 'messages', $query);
      if ($currentPage < $page) {
        $token = (string)($list['nextPageToken'] ?? '');
        if ($token === '') {
          $list = ['messages' => [], 'resultSizeEstimate' => 0];
          break;
        }
      }
    }
  }

  $messages = [];
  foreach ((array)($list['messages'] ?? []) as $item) {
    $id = (string)($item['id'] ?? '');
    if ($id === '') {
      continue;
    }
    $cachedAttachmentCount = array_key_exists($id, $cachedAttachmentCounts)
      ? max(0, (int)$cachedAttachmentCounts[$id])
      : null;
    $needAttachmentStructure = $showAttachmentPill && $cachedAttachmentCount === null;
    $messageQuery = ($search !== '' || $previewRows > 0 || $needAttachmentStructure)
      ? ['format' => 'full']
      : ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Date']];
    $metadata = pseGoogleApi(
      $settings,
      'GET',
      'messages/' . rawurlencode($id),
      $messageQuery
    );
    $headers = pseGmailHeaders((array)($metadata['payload'] ?? []));
    $fromFirst = pseSenderDisplayParts((string)($headers['from'] ?? ''));
    $labels = array_map('strval', (array)($metadata['labelIds'] ?? []));
    $timestamp = (int)floor(((int)($metadata['internalDate'] ?? 0)) / 1000);
    $messageItem = [
      'uid' => $id,
      'subject' => pseMime((string)($headers['subject'] ?? '(No subject)')),
      'fromName' => $fromFirst['name'],
      'fromEmail' => $fromFirst['email'],
      'date' => pseFormatDate($timestamp, $settings),
      'timestamp' => $timestamp,
      'size' => (int)($metadata['sizeEstimate'] ?? 0),
      'seen' => !in_array('UNREAD', $labels, true),
      'answered' => false
    ];
    if ($showAttachmentPill) {
      $messageItem['attachmentCount'] = $cachedAttachmentCount !== null && $messageQuery['format'] !== 'full'
        ? $cachedAttachmentCount
        : pseGmailAttachmentCount((array)($metadata['payload'] ?? []));
    }
    $bodyText = '';
    if ($search !== '' || $previewRows > 0) {
      $bodyText = pseGmailSearchText((array)($metadata['payload'] ?? []));
    }
    if ($previewRows > 0) {
      $messageItem['previewText'] = pseListPreviewText(
        $bodyText !== '' ? $bodyText : (string)($metadata['snippet'] ?? '')
      );
    }
    if ($search !== '') {
      $searchText = implode("\n", [
        (string)($headers['from'] ?? ''),
        (string)($headers['subject'] ?? ''),
        (string)($metadata['snippet'] ?? ''),
        $bodyText
      ]);
      $messageItem['searchContext'] = pseSearchContext($searchText, $search);
    }
    $messages[] = $messageItem;
  }
  $label = pseGoogleApi($settings, 'GET', 'labels/' . rawurlencode($folder));
  $total = $totalFromIds !== null
    ? $totalFromIds
    : ($search === '' && $senderFilter === '' && !$unreadOnly && $attachmentFilter === 'all' && $startDate === ''
      ? (int)($label['messagesTotal'] ?? count($messages))
      : (int)($list['resultSizeEstimate'] ?? count($messages)));
  return [
    'messages' => $messages,
    'page' => $page,
    'perPage' => $perPage,
    'total' => $total,
    'pages' => max(1, (int)ceil($total / $perPage)),
    'folderTotal' => (int)($label['messagesTotal'] ?? $total),
    'folderUnseen' => (int)($label['messagesUnread'] ?? 0),
    'uidValidity' => 'gmail'
  ];
}

function pseGmailMessageDetails(
  array $settings,
  string $folder,
  string $messageId,
  bool $loadRemote,
  bool $markSeen = true,
  bool $prefetchOnly = false
): array {
  if (!preg_match('/^[a-zA-Z0-9_-]+$/', $messageId)) {
    throw new RuntimeException('Invalid Gmail message identifier.');
  }
  $message = pseGoogleApi(
    $settings,
    'GET',
    'messages/' . rawurlencode($messageId),
    ['format' => 'full']
  );
  $payload = (array)($message['payload'] ?? []);
  $headers = pseGmailHeaders($payload);
  $content = ['plain' => '', 'html' => '', 'attachments' => [], 'inline' => []];
  pseGmailCollectParts($settings, $messageId, $payload, $content);
  $cidUrls = [];
  $embeddedImagesBlocked = 0;
  $referencedCids = pseReferencedCidSet($content['html']);
  foreach ($content['attachments'] as &$attachment) {
    $partNo = (string)$attachment['part'];
    $cid = trim((string)($attachment['cid'] ?? ''), '<>');
    $cidKey = strtolower(rawurldecode($cid));
    $referencedInline = !empty($attachment['inline']) &&
      $cidKey !== '' &&
      isset($referencedCids[$cidKey]);
    $attachment['url'] = pseAttachmentUrl($settings, $folder, $messageId, $partNo);

    // A previously cached image is free to reuse; no Gmail request is made.
    $existing = pseCachedAttachmentRecord($settings, $folder, $messageId, $partNo);
    if (!empty($existing)) {
      $attachment['url'] = pseCachedAttachmentUrlFromRecord($settings, $existing);
      if ($referencedInline) {
        $cidUrls[$cid] = (string)$attachment['url'];
      }
      continue;
    }

    // CID images are represented by signed attachment URLs immediately. Their
    // binary data is not part of the message-open response; the browser requests
    // each image afterward, so the HTML can paint first.
    if ($referencedInline) {
      if (!empty($attachment['previewable'])) {
        $cidUrls[$cid] = (string)$attachment['url'];
      } elseif (!$prefetchOnly) {
        $embeddedImagesBlocked++;
      }
    }
  }
  unset($attachment);
  $content['html'] = pseReplaceCidUrls($content['html'], $cidUrls);
  $cacheSourceHtml = $content['html'];
  $messageSize = (int)($message['sizeEstimate'] ?? 0);
  $forceImageBlocking = $messageSize > PSE_LARGE_MESSAGE_BYTES;
  $remotePrepared = psePrepareRemoteImageHtml(
    $settings,
    $folder,
    $messageId,
    $content['html'],
    $loadRemote,
    $forceImageBlocking
  );
  $content['html'] = (string)$remotePrepared['html'];
  if ($content['html'] === '' && $content['plain'] !== '') {
    $content['html'] = '<pre style="white-space:pre-wrap;font:14px/1.55 Arial,sans-serif;margin:0">' .
      htmlspecialchars($content['plain'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
      '</pre>';
  }
  $labels = array_map('strval', (array)($message['labelIds'] ?? []));
  $wasSeen = !in_array('UNREAD', $labels, true);
  if ($markSeen && !$wasSeen) {
    pseGoogleApi(
      $settings,
      'POST',
      'messages/' . rawurlencode($messageId) . '/modify',
      [],
      ['removeLabelIds' => ['UNREAD']]
    );
  }
  $from = pseAddressList((string)($headers['from'] ?? ''));
  $timestamp = (int)floor(((int)($message['internalDate'] ?? 0)) / 1000);
  return [
    'uid' => $messageId,
    'subject' => pseMime((string)($headers['subject'] ?? '(No subject)')),
    'from' => $from,
    'to' => pseAddressList((string)($headers['to'] ?? '')),
    'cc' => pseAddressList((string)($headers['cc'] ?? '')),
    'replyTo' => pseAddressList((string)($headers['reply-to'] ?? '')),
    'date' => pseFormatDate($timestamp, $settings),
    'timestamp' => $timestamp,
    'messageId' => trim((string)($headers['message-id'] ?? '')),
    'impersonationWarning' => pseBrandImpersonationWarning($from),
    'html' => $content['html'],
    'plain' => $content['plain'] !== '' ? $content['plain'] : trim(strip_tags($content['html'])),
    'remoteImages' => !empty($remotePrepared['remoteImages']),
    'remoteImagesBlocked' => !empty($remotePrepared['remoteImagesBlocked']),
    'attachments' => $content['attachments'],
    'downloadAllUrl' => $content['attachments']
      ? pseAttachmentDownloadAllUrl($settings, $folder, $messageId)
      : '',
    'embeddedImagesBlocked' => $embeddedImagesBlocked,
    'largeMessage' => $messageSize > PSE_LARGE_MESSAGE_BYTES,
    'seen' => $markSeen ? true : $wasSeen,
    'answered' => false,
    'size' => $messageSize,
    '_cacheSourceHtml' => $cacheSourceHtml,
    '_cachePrefetched' => $prefetchOnly
  ];
}

function pseGmailSetFlag(
  array $settings,
  string $messageId,
  string $flag,
  bool $enabled
): void {
  $label = '';
  $reverse = false;
  if ($flag === '\\Seen') {
    $label = 'UNREAD';
    $reverse = true;
  } elseif ($flag === '\\Answered') {
    return;
  } else {
    throw new RuntimeException('Unsupported message flag.');
  }
  $add = ($enabled xor $reverse) ? [$label] : [];
  $remove = ($enabled xor $reverse) ? [] : [$label];
  pseGoogleApi(
    $settings,
    'POST',
    'messages/' . rawurlencode($messageId) . '/modify',
    [],
    ['addLabelIds' => $add, 'removeLabelIds' => $remove]
  );
}

function pseGmailMoveMessage(array $settings, string $messageId): void
{
  pseGoogleApi($settings, 'POST', 'messages/' . rawurlencode($messageId) . '/trash', [], []);
}

function pseGmailBulkMessages(
  array $settings,
  array $messageIds,
  string $operation,
  string $confirmation
): int {
  $messageIds = array_values(array_unique(array_filter(array_map(function ($id): string {
    $id = trim((string)$id);
    return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? $id : '';
  }, $messageIds))));
  if (empty($messageIds)) {
    throw new RuntimeException('Select at least one message.');
  }
  if (count($messageIds) > 500) {
    throw new RuntimeException('Bulk operations are limited to 500 messages at a time.');
  }
  if (!in_array($operation, ['delete', 'delete_forever', 'restore', 'read', 'unread'], true)) {
    throw new RuntimeException('Unsupported bulk operation.');
  }
  if (
    $operation === 'delete' &&
    !empty($settings['confirm_delete_messages']) &&
    count($messageIds) > 10 &&
    $confirmation !== 'YES I AM SURE'
  ) {
    throw new RuntimeException('Type YES I AM SURE to delete more than 10 messages.');
  }

  if ($operation === 'delete_forever') {
    try {
      pseGoogleApi(
        $settings,
        'POST',
        'messages/batchDelete',
        [],
        ['ids' => $messageIds]
      );
    } catch (RuntimeException $error) {
      $errorText = strtolower($error->getMessage());
      if (
        strpos($errorText, '403') !== false &&
        (strpos($errorText, 'insufficient') !== false ||
          strpos($errorText, 'scope') !== false ||
          strpos($errorText, 'permission') !== false)
      ) {
        throw new RuntimeException(
          'Gmail permanent delete needs the full mail permission. Reconnect this Google account once from Settings, then try again.'
        );
      }
      throw $error;
    }
    return count($messageIds);
  }

  $add = [];
  $remove = [];
  if ($operation === 'delete') {
    $add = ['TRASH'];
    $remove = ['INBOX', 'SPAM'];
  } elseif ($operation === 'restore') {
    $add = ['INBOX'];
    $remove = ['TRASH', 'SPAM'];
  } elseif ($operation === 'read') {
    $remove = ['UNREAD'];
  } else {
    $add = ['UNREAD'];
  }
  pseGoogleApi(
    $settings,
    'POST',
    'messages/batchModify',
    [],
    ['ids' => $messageIds, 'addLabelIds' => $add, 'removeLabelIds' => $remove]
  );
  return count($messageIds);
}

function pseHasImap(): void
{
  if (!function_exists('imap_open')) {
    throw new RuntimeException('The PHP IMAP extension is not installed or enabled.');
  }
}

function pseImapBase(array $settings): string
{
  $flags = '/imap';
  $encryption = (string)$settings['imap_encryption'];
  if ($encryption === 'ssl') {
    $flags .= '/ssl';
  } elseif ($encryption === 'tls') {
    $flags .= '/tls';
  } else {
    $flags .= '/notls';
  }
  if (empty($settings['imap_validate_cert'])) {
    $flags .= '/novalidate-cert';
  }
  return '{' . $settings['imap_host'] . ':' . (int)$settings['imap_port'] . $flags . '}';
}

function pseOpenImap(array $settings, string $folder = 'INBOX', bool $readOnly = false)
{
  pseHasImap();
  if (trim((string)$settings['imap_username']) === '') {
    throw new RuntimeException('Configure the IMAP account in Settings first.');
  }
  $password = pseDecrypt((string)$settings['imap_password_enc'], (string)$settings['storage_key']);
  if ($password === '') {
    throw new RuntimeException('The IMAP password is not configured.');
  }
  $options = $readOnly && defined('OP_READONLY') ? OP_READONLY : 0;
  $imap = @imap_open(
    pseImapBase($settings) . $folder,
    (string)$settings['imap_username'],
    $password,
    $options,
    1
  );
  if ($imap === false) {
    $error = imap_last_error();
    throw new RuntimeException('IMAP connection failed: ' . ($error ?: 'unknown error'));
  }
  return $imap;
}

function pseMime(string $value): string
{
  if ($value === '') {
    return '';
  }
  if (function_exists('iconv_mime_decode')) {
    $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    if (is_string($decoded) && $decoded !== '') {
      return $decoded;
    }
  }
  if (!function_exists('imap_mime_header_decode')) {
    return $value;
  }
  $parts = @imap_mime_header_decode($value);
  if (!is_array($parts)) {
    return $value;
  }
  $out = '';
  foreach ($parts as $part) {
    $text = (string)($part->text ?? '');
    $charset = strtoupper((string)($part->charset ?? 'DEFAULT'));
    if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
      $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
      $text = $converted === false ? $text : $converted;
    }
    $out .= $text;
  }
  return $out;
}

function pseAddressList(string $raw): array
{
  $result = [];
  if ($raw === '') {
    return $result;
  }
  $parsed = function_exists('imap_rfc822_parse_adrlist')
    ? @imap_rfc822_parse_adrlist($raw, '')
    : false;
  if (is_array($parsed)) {
    foreach ($parsed as $item) {
      $mailbox = (string)($item->mailbox ?? '');
      $host = (string)($item->host ?? '');
      if ($mailbox === '' || $host === '' || $host === '.SYNTAX-ERROR.') {
        continue;
      }
      $email = $mailbox . '@' . $host;
      $name = pseMime((string)($item->personal ?? ''));
      $result[] = ['name' => $name, 'email' => $email];
    }
    return $result;
  }
  foreach (preg_split('/\s*,\s*/', $raw) as $address) {
    $address = trim((string)$address);
    $name = '';
    $email = $address;
    if (preg_match('/^(.*?)<([^>]+)>$/', $address, $match)) {
      $name = trim(pseMime($match[1]), " \t\n\r\0\x0B\"'");
      $email = trim($match[2]);
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $result[] = ['name' => $name, 'email' => $email];
    }
  }
  return $result;
}

function pseSenderDisplayParts(string $raw): array
{
  $raw = trim($raw);
  $parsed = pseAddressList($raw);
  $first = $parsed[0] ?? ['name' => '', 'email' => ''];
  $name = trim((string)($first['name'] ?? ''));
  $email = trim((string)($first['email'] ?? ''));

  if ($email === '' && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $match)) {
    $email = trim((string)$match[0]);
  }
  if ($name === '' && $email !== '') {
    $name = $email;
  }
  if ($name === '' && $raw !== '') {
    $name = trim(pseMime($raw), " \t\n\r\0\x0B\"'");
  }
  if ($name === '') {
    $name = '(Unknown sender)';
  }

  return ['name' => $name, 'email' => $email];
}

function pseFolderLabel(string $raw): string
{
  $label = $raw;
  if (function_exists('imap_utf7_decode')) {
    $decoded = @imap_utf7_decode($raw);
    if (is_string($decoded) && $decoded !== '') {
      $label = $decoded;
    }
  }
  $label = preg_replace('/^INBOX[.\/\\\\]+/i', '', $label) ?? $label;
  $label = preg_replace('/^\[Gmail\][.\/\\\\]+/i', '', $label) ?? $label;
  $parts = preg_split('/[.\/\\\\]+/', $label);
  $leaf = is_array($parts) ? end($parts) : $label;
  $leaf = is_string($leaf) && $leaf !== '' ? $leaf : $label;
  return strcasecmp($leaf, 'INBOX') === 0 ? 'Inbox' : $leaf;
}

function pseFolders(array $settings): array
{
  if (pseIsGmailAccount($settings)) {
    return pseGmailFolders($settings);
  }
  $imap = pseOpenImap($settings, 'INBOX', true);
  $base = pseImapBase($settings);
  $boxes = @imap_getmailboxes($imap, $base, '*');
  $folders = [];
  if (is_array($boxes)) {
    foreach ($boxes as $box) {
      $full = (string)$box->name;
      $raw = strpos($full, $base) === 0 ? substr($full, strlen($base)) : $full;
      $attributes = (int)($box->attributes ?? 0);
      if (defined('LATT_NOSELECT') && ($attributes & LATT_NOSELECT)) {
        continue;
      }
      $status = @imap_status($imap, $base . $raw, SA_MESSAGES | SA_UNSEEN);
      $lower = strtolower(pseFolderLabel($raw));
      $special = 'folder';
      if (strcasecmp($raw, 'INBOX') === 0) {
        $special = 'inbox';
      } elseif (strpos($lower, 'sent') !== false) {
        $special = 'sent';
      } elseif (strpos($lower, 'draft') !== false) {
        $special = 'drafts';
      } elseif (strpos($lower, 'trash') !== false || strpos($lower, 'deleted') !== false) {
        $special = 'trash';
      } elseif (strpos($lower, 'spam') !== false || strpos($lower, 'junk') !== false) {
        $special = 'spam';
      } elseif (strpos($lower, 'all mail') !== false || strpos($lower, 'archive') !== false) {
        $special = 'archive';
      }
      $folders[] = [
        'id' => $raw,
        'name' => pseFolderLabel($raw),
        'delimiter' => (string)($box->delimiter ?? '/'),
        'messages' => $status ? (int)$status->messages : 0,
        'unseen' => $status ? (int)$status->unseen : 0,
        'special' => $special
      ];
    }
  }
  imap_close($imap);
  usort($folders, function (array $a, array $b): int {
    $order = ['inbox' => 0, 'sent' => 1, 'drafts' => 2, 'archive' => 3, 'spam' => 8, 'trash' => 9, 'folder' => 5];
    $oa = $order[$a['special']] ?? 5;
    $ob = $order[$b['special']] ?? 5;
    return $oa === $ob ? strcasecmp($a['name'], $b['name']) : ($oa <=> $ob);
  });
  return $folders;
}

function pseFormatDate($value, array $settings): string
{
  $timestamp = 0;
  if (is_numeric($value)) {
    $timestamp = (int)$value;
  } elseif (is_string($value)) {
    $timestamp = strtotime($value) ?: 0;
  }
  if ($timestamp <= 0) {
    return '';
  }
  if (!empty($settings['smart_datetime'])) {
    $now = time();
    $today = strtotime('today', $now);
    $yesterday = strtotime('yesterday', $now);
    $difference = max(0, $now - $timestamp);
    $time = date((string)$settings['time_format'], $timestamp);

    if ($timestamp >= $today && $timestamp <= ($now + 60)) {
      if ($difference < 60) {
        return 'Just now';
      }
      if ($difference < 3600) {
        $minutes = max(1, (int)floor($difference / 60));
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
      }
      $hours = max(1, (int)floor($difference / 3600));
      return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($timestamp >= $yesterday && $timestamp < $today) {
      return 'Yesterday at ' . $time;
    }
    if ($timestamp >= strtotime('-7 days', $today) && $timestamp < $yesterday) {
      return date('l', $timestamp) . ' at ' . $time;
    }
  }
  return date((string)$settings['date_format'] . ' ' . (string)$settings['time_format'], $timestamp);
}

function pseCollectImapSearchText($imap, int $uid, $part, string $partNo, array &$chunks): void
{
  if (!empty($part->parts) && is_array($part->parts)) {
    foreach ($part->parts as $index => $child) {
      $childNo = $partNo === '' ? (string)($index + 1) : $partNo . '.' . ($index + 1);
      pseCollectImapSearchText($imap, $uid, $child, $childNo, $chunks);
    }
    return;
  }
  $type = (int)($part->type ?? 7);
  $subtype = strtolower((string)($part->subtype ?? ''));
  if ($type !== 0 || !in_array($subtype, ['plain', 'html'], true)) {
    return;
  }
  $params = psePartParameters($part);
  $disposition = strtolower((string)($part->disposition ?? ''));
  if (
    isset($params['filename']) ||
    isset($params['name']) ||
    $disposition === 'attachment'
  ) {
    return;
  }
  $raw = $partNo === ''
    ? @imap_body($imap, $uid, FT_UID | FT_PEEK)
    : @imap_fetchbody($imap, $uid, $partNo, FT_UID | FT_PEEK);
  $content = pseDecodePart(is_string($raw) ? $raw : '', (int)($part->encoding ?? 0));
  $charset = (string)($params['charset'] ?? 'UTF-8');
  if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('iconv')) {
    $converted = @iconv($charset, 'UTF-8//IGNORE', $content);
    if ($converted !== false) {
      $content = $converted;
    }
  }
  if ($subtype === 'html') {
    $content = preg_replace('/<\s*br\s*\/?>/i', "\n", $content) ?? $content;
    $content = strip_tags($content);
  }
  if (trim($content) !== '') {
    $chunks[] = $content;
  }
}

function pseImapSearchText($imap, int $uid): string
{
  $structure = @imap_fetchstructure($imap, $uid, FT_UID);
  if (!$structure) {
    $raw = @imap_body($imap, $uid, FT_UID | FT_PEEK);
    return is_string($raw) ? $raw : '';
  }
  $chunks = [];
  pseCollectImapSearchText($imap, $uid, $structure, '', $chunks);
  return implode("\n", $chunks);
}


function pseCalendarAddMessage(
  array &$days,
  string $dayKey,
  string $uid,
  string $fromName,
  string $fromEmail,
  string $subject,
  int $timestamp
): void {
  if ($dayKey === '') {
    return;
  }
  if (!isset($days[$dayKey])) {
    $days[$dayKey] = [
      'date' => $dayKey,
      'count' => 0,
      'distinctSenders' => 0,
      'emails' => [],
      '_senders' => []
    ];
  }
  $senderName = trim($fromName);
  $senderEmail = trim($fromEmail);
  $senderKey = strtolower($senderEmail !== '' ? $senderEmail : $senderName);
  if ($senderKey === '') {
    $senderKey = '(unknown sender)';
  }
  $days[$dayKey]['count']++;
  $days[$dayKey]['_senders'][$senderKey] = true;
  $days[$dayKey]['emails'][] = [
    'uid' => $uid,
    'sender' => $senderName !== '' ? $senderName : ($senderEmail !== '' ? $senderEmail : '(Unknown sender)'),
    'senderEmail' => $senderEmail,
    'subject' => $subject !== '' ? $subject : '(No subject)',
    'timestamp' => $timestamp
  ];
}

function pseCalendarMonthData(
  array $settings,
  string $folder,
  string $month,
  string $search = '',
  string $senderFilter = '',
  bool $unreadOnly = false,
  string $attachmentFilter = 'all'
): array {
  $folder = trim($folder);
  $month = pseNormalizeCalendarMonth($month);
  $search = trim($search);
  $senderFilter = strtolower(trim($senderFilter));
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  if ($folder === '') {
    throw new RuntimeException('Select an email folder first.');
  }
  if ($month === '') {
    throw new RuntimeException('Invalid calendar month.');
  }
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }

  $timezone = pseSettingsTimezone($settings);
  $start = new DateTimeImmutable($month . '-01 00:00:00', $timezone);
  $end = $start->modify('+1 month');
  $startTimestamp = $start->getTimestamp();
  $endTimestamp = $end->getTimestamp();
  $days = [];

  if (pseIsGmailAccount($settings)) {
    $queryParts = [];
    if ($search !== '') {
      $queryParts[] = $search;
    }
    if ($senderFilter !== '') {
      $queryParts[] = 'from:' . $senderFilter;
    }
    if ($unreadOnly) {
      $queryParts[] = 'is:unread';
    }
    if ($attachmentFilter === 'with') {
      $queryParts[] = 'has:attachment';
    }
    $queryParts[] = 'after:' . max(0, $startTimestamp - 1);
    $queryParts[] = 'before:' . $endTimestamp;
    $gmailQuery = implode(' ', $queryParts);

    $ids = [];
    $token = '';
    do {
      $query = [
        'labelIds' => $folder,
        'maxResults' => 500,
        'q' => $gmailQuery
      ];
      if ($token !== '') {
        $query['pageToken'] = $token;
      }
      $batch = pseGoogleApi($settings, 'GET', 'messages', $query);
      foreach ((array)($batch['messages'] ?? []) as $item) {
        $id = trim((string)($item['id'] ?? ''));
        if ($id !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
          $ids[$id] = $id;
        }
      }
      $token = (string)($batch['nextPageToken'] ?? '');
    } while ($token !== '');

    foreach (array_values($ids) as $id) {
      $metadata = pseGoogleApi(
        $settings,
        'GET',
        'messages/' . rawurlencode($id),
        ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Date']]
      );
      $timestamp = (int)floor(((int)($metadata['internalDate'] ?? 0)) / 1000);
      if ($timestamp < $startTimestamp || $timestamp >= $endTimestamp) {
        continue;
      }
      $headers = pseGmailHeaders((array)($metadata['payload'] ?? []));
      $from = pseSenderDisplayParts((string)($headers['from'] ?? ''));
      pseCalendarAddMessage(
        $days,
        pseCalendarDayKey($timestamp, $timezone),
        $id,
        (string)$from['name'],
        (string)$from['email'],
        pseMime((string)($headers['subject'] ?? '(No subject)')),
        $timestamp
      );
    }
  } else {
    $imap = pseOpenImap($settings, $folder, true);
    try {
      $criteria = [
        'SINCE ' . $start->format('d-M-Y'),
        'BEFORE ' . $end->format('d-M-Y')
      ];
      if ($unreadOnly) {
        $criteria[] = 'UNSEEN';
      }
      if ($search !== '') {
        $criteria[] = 'TEXT "' . addcslashes($search, "\\\"") . '"';
      }
      if ($senderFilter !== '') {
        $criteria[] = 'FROM "' . addcslashes($senderFilter, "\\\"") . '"';
      }
      $criteriaText = implode(' ', $criteria);
      $uids = @imap_search($imap, $criteriaText, SE_UID, 'UTF-8');
      if ($uids === false) {
        $uids = @imap_search($imap, $criteriaText, SE_UID);
      }
      $uids = is_array($uids) ? array_values($uids) : [];

      if ($attachmentFilter === 'with' && $uids) {
        $cachedCounts = pseMailCacheAttachmentCounts($settings, $folder);
        $filtered = [];
        $countsChanged = false;
        foreach ($uids as $uid) {
          $uidKey = (string)$uid;
          if (array_key_exists($uidKey, $cachedCounts)) {
            $count = max(0, (int)$cachedCounts[$uidKey]);
          } else {
            $structure = @imap_fetchstructure($imap, (int)$uid, FT_UID);
            $count = pseImapAttachmentCount($structure);
            $cachedCounts[$uidKey] = $count;
            $countsChanged = true;
          }
          if ($count > 0) {
            $filtered[] = $uid;
          }
        }
        if ($countsChanged) {
          pseWriteJson(pseMailCacheAttachmentCountsFile($settings, $folder), $cachedCounts);
        }
        $uids = $filtered;
      }

      foreach (array_chunk($uids, 200) as $chunk) {
        $sequence = implode(',', array_map('intval', $chunk));
        if ($sequence === '') {
          continue;
        }
        $overview = @imap_fetch_overview($imap, $sequence, FT_UID);
        if (!is_array($overview)) {
          continue;
        }
        foreach ($overview as $item) {
          $uid = (string)($item->uid ?? '');
          if ($uid === '') {
            continue;
          }
          $timestamp = isset($item->udate)
            ? (int)$item->udate
            : (int)(strtotime((string)($item->date ?? '')) ?: 0);
          if ($timestamp < $startTimestamp || $timestamp >= $endTimestamp) {
            continue;
          }
          $from = pseSenderDisplayParts((string)($item->from ?? ''));
          pseCalendarAddMessage(
            $days,
            pseCalendarDayKey($timestamp, $timezone),
            $uid,
            (string)$from['name'],
            (string)$from['email'],
            pseMime((string)($item->subject ?? '(No subject)')),
            $timestamp
          );
        }
      }
    } finally {
      imap_close($imap);
    }
  }

  ksort($days);
  $total = 0;
  foreach ($days as &$day) {
    $day['distinctSenders'] = count((array)$day['_senders']);
    unset($day['_senders']);
    usort($day['emails'], function (array $left, array $right): int {
      return (int)$right['timestamp'] <=> (int)$left['timestamp'];
    });
    $total += (int)$day['count'];
  }
  unset($day);

  return [
    'month' => $month,
    'startDate' => $start->format('Y-m-d'),
    'endDate' => $end->format('Y-m-d'),
    'total' => $total,
    'days' => array_values($days)
  ];
}

function pseCachedCalendarMonth(
  array $settings,
  string $folder,
  string $month,
  string $search = '',
  string $senderFilter = '',
  bool $unreadOnly = false,
  string $attachmentFilter = 'all',
  bool $forceRefresh = false
): array {
  $month = pseNormalizeCalendarMonth($month);
  if ($month === '') {
    throw new RuntimeException('Invalid calendar month.');
  }
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $senderFilter = strtolower(trim($senderFilter));
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }
  $file = pseMailCacheCalendarFile(
    $settings,
    $folder,
    $month,
    $search,
    $senderFilter,
    $unreadOnly,
    $attachmentFilter
  );
  $previous = pseMailCacheEnvelopeRead($file);
  if (!$forceRefresh && !empty($previous)) {
    return [
      'data' => $previous['data'],
      'cache' => pseMailCacheInfo($previous, true)
    ];
  }

  try {
    $data = pseCalendarMonthData(
      $settings,
      $folder,
      $month,
      $search,
      $senderFilter,
      $unreadOnly,
      $attachmentFilter
    );
  } catch (Throwable $error) {
    if (!empty($previous)) {
      $cache = pseMailCacheInfo($previous, true);
      $cache['refreshError'] = $error->getMessage();
      $cache['googleReconnectRequired'] = $error instanceof PseGoogleReconnectRequiredException;
      return ['data' => $previous['data'], 'cache' => $cache];
    }
    throw $error;
  }

  $envelope = pseMailCacheEnvelopeWrite($file, $data, [
    'folder' => $folder,
    'month' => $month,
    'search' => $search,
    'senderFilter' => $senderFilter,
    'unreadOnly' => $unreadOnly,
    'attachmentFilter' => $attachmentFilter,
    'freshFromServer' => true
  ]);
  return [
    'data' => $data,
    'cache' => pseMailCacheInfo($envelope, false)
  ];
}

function pseMessageList(
  array $settings,
  string $folder,
  int $page,
  string $search,
  string $senderFilter = '',
  bool $unreadOnly = false,
  string $sortOrder = 'desc',
  array $cachedAttachmentCounts = [],
  string $attachmentFilter = 'all',
  string $startDate = ''
): array
{
  $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $startDate = pseNormalizeCalendarDate($startDate);
  $senderFilter = strtolower(trim($senderFilter));
  $previewRows = max(0, min(5, (int)($settings['email_preview_rows'] ?? 0)));
  $showAttachmentPill = !empty($settings['show_attachment_pill']);
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }
  if (pseIsGmailAccount($settings)) {
    return pseGmailMessageList(
      $settings,
      $folder,
      $page,
      $search,
      $senderFilter,
      $unreadOnly,
      $sortOrder,
      $cachedAttachmentCounts,
      $attachmentFilter,
      $startDate
    );
  }
  $imap = pseOpenImap($settings, $folder, true);
  $page = max(1, $page);
  $perPage = max(10, min(200, (int)$settings['items_per_page']));
  $reverse = $sortOrder === 'desc';
  // PHP 7.x declares imap_sort() parameter 3 as int, while PHP 8+ declares it as bool.
  // With strict_types enabled, pass the exact type expected by the running PHP version.
  $imapSortReverse = PHP_VERSION_ID < 80000 ? ($reverse ? 1 : 0) : $reverse;
  $uids = [];
  if ($search !== '' || $senderFilter !== '' || $unreadOnly || $startDate !== '') {
    $criteria = [];
    if ($unreadOnly) {
      $criteria[] = 'UNSEEN';
    }
    if ($search !== '') {
      $escaped = addcslashes($search, "\\\"");
      $criteria[] = 'TEXT "' . $escaped . '"';
    }
    if ($senderFilter !== '') {
      $escapedSender = addcslashes($senderFilter, "\\\"");
      $criteria[] = 'FROM "' . $escapedSender . '"';
    }
    if ($startDate !== '') {
      $timezone = pseSettingsTimezone($settings);
      $anchorStart = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
      if ($sortOrder === 'asc') {
        $criteria[] = 'SINCE ' . $anchorStart->format('d-M-Y');
      } else {
        $criteria[] = 'BEFORE ' . $anchorStart->modify('+1 day')->format('d-M-Y');
      }
    }
    $criteriaText = implode(' ', $criteria);
    $uids = @imap_sort($imap, SORTDATE, $imapSortReverse, SE_UID, $criteriaText, 'UTF-8');
    if ($uids === false) {
      $uids = @imap_sort($imap, SORTDATE, $imapSortReverse, SE_UID, $criteriaText);
    }
    if ($uids === false) {
      $uids = @imap_search($imap, $criteriaText, SE_UID, 'UTF-8');
      if ($uids === false) {
        $uids = @imap_search($imap, $criteriaText, SE_UID);
      }
      $uids = is_array($uids) ? $uids : [];
      if ($sortOrder === 'asc') {
        sort($uids, SORT_NUMERIC);
      } else {
        rsort($uids, SORT_NUMERIC);
      }
    } else {
      $uids = is_array($uids) ? $uids : [];
    }
  } else {
    $sorted = @imap_sort($imap, SORTDATE, $imapSortReverse, SE_UID);
    $uids = is_array($sorted) ? $sorted : [];
  }
  $allFolderUids = ($search === '' && $senderFilter === '' && !$unreadOnly && $startDate === '')
    ? array_values($uids)
    : [];
  if ($attachmentFilter !== 'all') {
    $filteredUids = [];
    $countsChanged = false;
    foreach ($uids as $uid) {
      $uidKey = (string)$uid;
      if (array_key_exists($uidKey, $cachedAttachmentCounts)) {
        $count = max(0, (int)$cachedAttachmentCounts[$uidKey]);
      } else {
        $structure = @imap_fetchstructure($imap, (int)$uid, FT_UID);
        $count = pseImapAttachmentCount($structure);
        $cachedAttachmentCounts[$uidKey] = $count;
        $countsChanged = true;
      }
      if ($count > 0) {
        $filteredUids[] = $uid;
      }
    }
    if ($countsChanged) {
      pseWriteJson(pseMailCacheAttachmentCountsFile($settings, $folder), $cachedAttachmentCounts);
    }
    $uids = $filteredUids;
  }
  $total = count($uids);
  $slice = array_slice($uids, ($page - 1) * $perPage, $perPage);
  $messages = [];
  foreach ($slice as $uid) {
    $overview = @imap_fetch_overview($imap, (string)$uid, FT_UID);
    if (!is_array($overview) || empty($overview[0])) {
      continue;
    }
    $o = $overview[0];
    $fromFirst = pseSenderDisplayParts((string)($o->from ?? ''));
    $messageItem = [
      'uid' => (int)$uid,
      'subject' => pseMime((string)($o->subject ?? '(No subject)')),
      'fromName' => $fromFirst['name'],
      'fromEmail' => $fromFirst['email'],
      'date' => pseFormatDate((string)($o->date ?? ''), $settings),
      'timestamp' => isset($o->udate) ? (int)$o->udate : 0,
      'size' => (int)($o->size ?? 0),
      'seen' => !empty($o->seen),
      'answered' => !empty($o->answered)
    ];
    if ($showAttachmentPill) {
      $uidKey = (string)$uid;
      if (array_key_exists($uidKey, $cachedAttachmentCounts)) {
        $messageItem['attachmentCount'] = max(0, (int)$cachedAttachmentCounts[$uidKey]);
      } else {
        $structure = @imap_fetchstructure($imap, (int)$uid, FT_UID);
        $messageItem['attachmentCount'] = pseImapAttachmentCount($structure);
      }
    }
    $bodyText = '';
    if ($search !== '' || $previewRows > 0) {
      $bodyText = pseImapSearchText($imap, (int)$uid);
    }
    if ($previewRows > 0) {
      $messageItem['previewText'] = pseListPreviewText($bodyText);
    }
    if ($search !== '') {
      $searchText = implode("\n", [
        (string)($o->from ?? ''),
        (string)($o->subject ?? ''),
        $bodyText
      ]);
      $messageItem['searchContext'] = pseSearchContext($searchText, $search);
    }
    $messages[] = $messageItem;
  }
  $statusFlags = SA_MESSAGES | SA_UNSEEN;
  if (defined('SA_UIDVALIDITY')) {
    $statusFlags |= SA_UIDVALIDITY;
  }
  $status = @imap_status($imap, pseImapBase($settings) . $folder, $statusFlags);
  imap_close($imap);
  return [
    'messages' => $messages,
    'page' => $page,
    'perPage' => $perPage,
    'total' => $total,
    'pages' => max(1, (int)ceil($total / $perPage)),
    'folderTotal' => $status ? (int)$status->messages : $total,
    'folderUnseen' => $status ? (int)$status->unseen : 0,
    'uidValidity' => $status && isset($status->uidvalidity) ? (string)$status->uidvalidity : '',
    '_cacheAllFolderUids' => $allFolderUids
  ];
}

function pseDecodePart(string $data, int $encoding): string
{
  if ($encoding === 3) {
    $decoded = base64_decode($data, true);
    return $decoded === false ? '' : $decoded;
  }
  if ($encoding === 4) {
    return quoted_printable_decode($data);
  }
  return $data;
}

function psePartParameters($part): array
{
  $params = [];
  $lists = [];
  if (!empty($part->parameters) && is_array($part->parameters)) {
    $lists[] = $part->parameters;
  }
  if (!empty($part->dparameters) && is_array($part->dparameters)) {
    $lists[] = $part->dparameters;
  }
  foreach ($lists as $list) {
    foreach ($list as $param) {
      $params[strtolower((string)$param->attribute)] = pseMime((string)$param->value);
    }
  }
  return $params;
}

function pseCollectParts(
  $imap,
  int $uid,
  $part,
  string $partNo,
  array &$out,
  bool $loadText = true
): void
{
  $typeNames = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
  $type = $typeNames[(int)($part->type ?? 7)] ?? 'application';
  $subtype = strtolower((string)($part->subtype ?? 'octet-stream'));
  $mime = $type . '/' . $subtype;
  $params = psePartParameters($part);
  $filename = $params['filename'] ?? ($params['name'] ?? '');
  $disposition = strtolower((string)($part->disposition ?? ''));
  $isAttachment = $filename !== '' || $disposition === 'attachment';
  $isInline = $disposition === 'inline' || !empty($part->id);

  if (!empty($part->parts) && is_array($part->parts)) {
    foreach ($part->parts as $index => $child) {
      $childNo = $partNo === '' ? (string)($index + 1) : $partNo . '.' . ($index + 1);
      pseCollectParts($imap, $uid, $child, $childNo, $out, $loadText);
    }
    return;
  }

  if (!$isAttachment && $type === 'text' && ($subtype === 'plain' || $subtype === 'html')) {
    if (!$loadText) {
      return;
    }
    $raw = $partNo === ''
      ? @imap_body($imap, $uid, FT_UID | FT_PEEK)
      : @imap_fetchbody($imap, $uid, $partNo, FT_UID | FT_PEEK);
    $content = pseDecodePart(is_string($raw) ? $raw : '', (int)($part->encoding ?? 0));
    $charset = $params['charset'] ?? 'UTF-8';
    if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('iconv')) {
      $converted = @iconv($charset, 'UTF-8//IGNORE', $content);
      if ($converted !== false) {
        $content = $converted;
      }
    }
    if ($subtype === 'html' && $out['html'] === '') {
      $out['html'] = $content;
    } elseif ($subtype === 'plain' && $out['plain'] === '') {
      $out['plain'] = $content;
    }
    return;
  }

  if ($isAttachment || $isInline) {
    $cid = trim((string)($part->id ?? ''), '<>');
    $attachment = [
      'part' => $partNo === '' ? '0' : $partNo,
      'filename' => $filename !== '' ? $filename : ('attachment-' . ($partNo === '' ? '0' : str_replace('.', '-', $partNo))),
      'mime' => $mime,
      'size' => (int)($part->bytes ?? 0),
      'inline' => $isInline,
      'cid' => $cid,
      'previewable' => pseIsReadableAttachment($mime)
    ];
    $out['attachments'][] = $attachment;
  }
}

function pseRemoteImageLooksLikeTracker(string $tag, string $url): bool
{
  $tag = strtolower($tag);
  $url = strtolower(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  $width = null;
  $height = null;
  if (preg_match('/\bwidth\s*=\s*["\']?\s*(\d{1,4})/i', $tag, $match)) {
    $width = (int)$match[1];
  }
  if (preg_match('/\bheight\s*=\s*["\']?\s*(\d{1,4})/i', $tag, $match)) {
    $height = (int)$match[1];
  }
  if ($width !== null && $height !== null && $width <= 2 && $height <= 2) {
    return true;
  }
  if (preg_match('/style\s*=\s*["\'][^"\']*(?:display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?:\.0+)?\s*(?:[;!]|$)|width\s*:\s*[012]px|height\s*:\s*[012]px)/i', $tag)) {
    return true;
  }
  return (bool)preg_match(
    '#(?:^|[/?&_.=-])(?:tracking?|track(?:ing)?pixel|pixel|beacon|email[_-]?open|open[_-]?pixel|read[_-]?receipt|mailtrack)(?:[/?&_.=-]|$)#i',
    $url
  );
}

function pseBlockLikelyTrackingImages(string $html): array
{
  $blocked = false;
  $html = preg_replace_callback(
    '/<(?:img|image)\b[^>]*\b(?:src|href|xlink:href)\s*=\s*(["\'])(https?:\/\/.*?)\1[^>]*>/is',
    function (array $match) use (&$blocked): string {
      if (!pseRemoteImageLooksLikeTracker($match[0], $match[2])) {
        return $match[0];
      }
      $blocked = true;
      return '<span data-pse-tracking-image="blocked" style="display:none"></span>';
    },
    $html
  ) ?? $html;
  $html = preg_replace_callback(
    '/url\(\s*(["\']?)(https?:\/\/[^)"\']+)\1\s*\)/i',
    function (array $match) use (&$blocked): string {
      if (!pseRemoteImageLooksLikeTracker('', $match[2])) {
        return $match[0];
      }
      $blocked = true;
      return 'none';
    },
    $html
  ) ?? $html;
  return ['html' => $html, 'blocked' => $blocked];
}

function psePrepareRemoteImageHtml(
  array $settings,
  string $folder,
  string $uid,
  string $html,
  bool $loadRemote,
  bool $forceImageBlocking
): array {
  $alwaysLoad = !empty($settings['always_load_remote_images']);
  $blockTracking = !empty($settings['block_remote_images']);
  $autoLoad = $alwaysLoad || (!$forceImageBlocking && !$blockTracking);
  $shouldLoad = $loadRemote || $autoLoad;
  $trackingBlocked = false;
  $hadRemoteImages = (bool)preg_match(
    '/(?:<(?:img|source|image)\b[^>]*(?:src|srcset|href|xlink:href)\s*=\s*["\']https?:\/\/|url\s*\(\s*["\']?https?:\/\/)/i',
    $html
  );

  if ($shouldLoad) {
    if (!$loadRemote && $alwaysLoad && $blockTracking) {
      $tracking = pseBlockLikelyTrackingImages($html);
      $html = (string)$tracking['html'];
      $trackingBlocked = !empty($tracking['blocked']);
    }
    // The message-open API only rewrites URLs. The browser requests each
    // remote image later through the signed same-origin proxy/cache endpoint.
    $html = pseProxyRemoteImages($settings, $folder, $uid, $html);
  }

  $blockRemaining = !$shouldLoad && ($forceImageBlocking || $blockTracking);
  $sanitized = pseSanitizeEmailHtml($html, $blockRemaining);
  return [
    'html' => $sanitized['html'],
    'remoteImages' => $hadRemoteImages || $sanitized['remoteImages'] || $trackingBlocked,
    'remoteImagesBlocked' => $trackingBlocked || ($blockRemaining && $sanitized['remoteImages'])
  ];
}


function pseSanitizeEmailHtml(string $html, bool $blockRemote): array
{
  if (trim($html) === '') {
    return ['html' => '', 'remoteImages' => false];
  }
  $remoteImages = (bool)preg_match(
    '/(?:<(?:img|source|image)\b[^>]*(?:src|srcset)\s*=\s*["\']https?:\/\/|url\s*\(\s*["\']?https?:\/\/)/i',
    $html
  );
  $html = preg_replace('#<(script|object|embed|iframe|frame|frameset|form|input|button|textarea|select|option|meta|base|link)[^>]*>.*?</\1>#is', '', $html) ?? $html;
  $html = preg_replace('#<(script|object|embed|iframe|frame|frameset|form|input|button|textarea|select|option|meta|base|link)[^>]*/?>#is', '', $html) ?? $html;
  $html = preg_replace('/\s(on[a-z]+)\s*=\s*(["\']).*?\2/is', '', $html) ?? $html;
  $html = preg_replace('/\s(on[a-z]+)\s*=\s*[^\s>]+/is', '', $html) ?? $html;
  $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
  $html = preg_replace('/url\s*\(\s*["\']?\s*javascript:[^)]+\)/i', 'none', $html) ?? $html;
  $html = preg_replace_callback(
    '/<img\b[^>]*\bsrc\s*=\s*(["\'])cid:[^"\']+\1[^>]*>/is',
    function (): string {
      return '<span style="display:inline-block;padding:6px 10px;border:1px solid #ddd;color:#777;font:12px Arial">Embedded image available below</span>';
    },
    $html
  ) ?? $html;
  $html = preg_replace(
    '/<img\b[^>]*\bsrc\s*=\s*cid:[^\s>]+[^>]*>/is',
    '<span style="display:inline-block;padding:6px 10px;border:1px solid #ddd;color:#777;font:12px Arial">Embedded image available below</span>',
    $html
  ) ?? $html;
  $html = preg_replace('/url\s*\(\s*["\']?cid:[^)]+\)/i', 'none', $html) ?? $html;
  $html = preg_replace_callback(
    '/<a\b([^>]*)>/is',
    function (array $match): string {
      $attributes = (string)$match[1];
      if (!preg_match('/\bhref\s*=/i', $attributes)) {
        return $match[0];
      }
      $attributes = preg_replace(
        '/\s+target\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        '',
        $attributes
      ) ?? $attributes;
      $rel = [];
      if (preg_match('/\s+rel\s*=\s*(["\'])(.*?)\1/is', $attributes, $relMatch)) {
        foreach (preg_split('/\s+/', trim((string)$relMatch[2])) ?: [] as $token) {
          if ($token !== '') {
            $rel[strtolower($token)] = $token;
          }
        }
      }
      $attributes = preg_replace(
        '/\s+rel\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        '',
        $attributes
      ) ?? $attributes;
      $rel['noopener'] = 'noopener';
      $rel['noreferrer'] = 'noreferrer';
      return '<a' . $attributes . ' target="_blank" rel="' .
        htmlspecialchars(implode(' ', array_values($rel)), ENT_QUOTES, 'UTF-8') . '">';
    },
    $html
  ) ?? $html;
  $html = preg_replace_callback(
    '/<img\b([^>]*)>/is',
    function (array $match): string {
      $attributes = (string)$match[1];
      $attributes = preg_replace('/\s+loading\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes) ?? $attributes;
      $attributes = preg_replace('/\s+decoding\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes) ?? $attributes;
      return '<img' . $attributes . ' loading="lazy" decoding="async">';
    },
    $html
  ) ?? $html;
  if ($blockRemote) {
    $html = preg_replace_callback(
      '/<img\b([^>]*?)\bsrc\s*=\s*(["\'])(https?:\/\/.*?)\2([^>]*)>/is',
      function (array $m): string {
        $url = htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8');
        return '<span style="display:inline-block;padding:6px 10px;border:1px solid #ddd;color:#777;font:12px Arial" data-pse-image="' . $url . '">Remote image blocked</span>';
      },
      $html
    ) ?? $html;
    $html = preg_replace(
      '/(<(?:source|image)\b[^>]*?)\s+(?:src|href|xlink:href)\s*=\s*(["\'])https?:\/\/.*?\2/is',
      '$1',
      $html
    ) ?? $html;
    $html = preg_replace(
      '/\s+srcset\s*=\s*(["\'])[^"\']*https?:\/\/.*?\1/is',
      '',
      $html
    ) ?? $html;
    $html = preg_replace('/url\s*\(\s*["\']?https?:\/\/[^)]+\)/i', 'none', $html) ?? $html;
  }
  return ['html' => $html, 'remoteImages' => $remoteImages];
}

function pseSenderDomain(string $email): string
{
  $position = strrpos($email, '@');
  if ($position === false) {
    return '';
  }
  return strtolower(trim(substr($email, $position + 1), " \t\n\r\0\x0B."));
}

function pseDomainIsAllowed(string $senderDomain, array $allowedDomains): bool
{
  foreach ($allowedDomains as $allowedDomain) {
    $allowedDomain = strtolower(trim((string)$allowedDomain, " \t\n\r\0\x0B."));
    if (
      $allowedDomain !== '' &&
      (
        $senderDomain === $allowedDomain ||
        substr($senderDomain, -(strlen($allowedDomain) + 1)) === '.' . $allowedDomain
      )
    ) {
      return true;
    }
  }
  return false;
}

function pseBrandImpersonationWarning(array $from): ?array
{
  $sender = $from[0] ?? ['name' => '', 'email' => ''];
  $senderName = trim((string)($sender['name'] ?? ''));
  $senderEmail = trim((string)($sender['email'] ?? ''));
  $senderDomain = pseSenderDomain($senderEmail);
  if ($senderName === '' || $senderDomain === '') {
    return null;
  }

  // Official domains for 50 well-known brands that are frequently impersonated.
  // This is deliberately only a warning heuristic, never a definitive spam verdict.
  $brands = [
    ['brand' => 'SiteGround', 'names' => ['siteground'], 'domains' => ['siteground.com']],
    ['brand' => 'Namecheap', 'names' => ['namecheap'], 'domains' => ['namecheap.com']],
    ['brand' => 'Dropbox', 'names' => ['dropbox'], 'domains' => ['dropbox.com', 'dropboxmail.com']],
    ['brand' => 'PayPal', 'names' => ['paypal'], 'domains' => ['paypal.com']],
    ['brand' => 'Amazon', 'names' => ['amazon'], 'domains' => ['amazon.com', 'amazon.co.uk', 'amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.co.jp', 'amazon.ca', 'amazon.com.au']],
    ['brand' => 'Microsoft', 'names' => ['microsoft', 'office 365', 'microsoft 365'], 'domains' => ['microsoft.com', 'microsoftonline.com', 'office.com', 'outlook.com', 'live.com']],
    ['brand' => 'Apple', 'names' => ['apple', 'icloud'], 'domains' => ['apple.com', 'icloud.com']],
    ['brand' => 'Google', 'names' => ['google', 'gmail'], 'domains' => ['google.com', 'googlemail.com', 'gmail.com']],
    ['brand' => 'Facebook / Meta', 'names' => ['facebook', 'facebookmail', 'meta support'], 'domains' => ['facebook.com', 'facebookmail.com', 'meta.com']],
    ['brand' => 'Instagram', 'names' => ['instagram'], 'domains' => ['instagram.com']],
    ['brand' => 'LinkedIn', 'names' => ['linkedin'], 'domains' => ['linkedin.com']],
    ['brand' => 'Netflix', 'names' => ['netflix'], 'domains' => ['netflix.com', 'netflix.net']],
    ['brand' => 'Adobe', 'names' => ['adobe'], 'domains' => ['adobe.com', 'adobemail.com']],
    ['brand' => 'DocuSign', 'names' => ['docusign'], 'domains' => ['docusign.com', 'docusign.net']],
    ['brand' => 'DHL', 'names' => ['dhl'], 'domains' => ['dhl.com']],
    ['brand' => 'FedEx', 'names' => ['fedex'], 'domains' => ['fedex.com']],
    ['brand' => 'UPS', 'names' => ['ups'], 'domains' => ['ups.com']],
    ['brand' => 'USPS', 'names' => ['usps', 'united states postal service'], 'domains' => ['usps.com']],
    ['brand' => 'Royal Mail', 'names' => ['royal mail'], 'domains' => ['royalmail.com']],
    ['brand' => 'American Express', 'names' => ['american express', 'amex'], 'domains' => ['americanexpress.com', 'amex.com']],
    ['brand' => 'Visa', 'names' => ['visa'], 'domains' => ['visa.com']],
    ['brand' => 'Mastercard', 'names' => ['mastercard', 'master card'], 'domains' => ['mastercard.com']],
    ['brand' => 'Chase', 'names' => ['chase bank', 'jpmorgan chase'], 'domains' => ['chase.com', 'jpmorgan.com', 'jpmorganchase.com']],
    ['brand' => 'Bank of America', 'names' => ['bank of america'], 'domains' => ['bankofamerica.com']],
    ['brand' => 'Wells Fargo', 'names' => ['wells fargo'], 'domains' => ['wellsfargo.com']],
    ['brand' => 'Citibank', 'names' => ['citibank', 'citi bank'], 'domains' => ['citi.com', 'citibank.com']],
    ['brand' => 'HSBC', 'names' => ['hsbc'], 'domains' => ['hsbc.com']],
    ['brand' => 'Barclays', 'names' => ['barclays'], 'domains' => ['barclays.com']],
    ['brand' => 'Santander', 'names' => ['santander'], 'domains' => ['santander.com']],
    ['brand' => 'Wise', 'names' => ['wise', 'transferwise'], 'domains' => ['wise.com', 'transferwise.com']],
    ['brand' => 'Revolut', 'names' => ['revolut'], 'domains' => ['revolut.com']],
    ['brand' => 'Coinbase', 'names' => ['coinbase'], 'domains' => ['coinbase.com']],
    ['brand' => 'Binance', 'names' => ['binance'], 'domains' => ['binance.com']],
    ['brand' => 'Kraken', 'names' => ['kraken'], 'domains' => ['kraken.com']],
    ['brand' => 'X / Twitter', 'names' => ['twitter', 'x support', 'x.com'], 'domains' => ['x.com', 'twitter.com']],
    ['brand' => 'WhatsApp', 'names' => ['whatsapp', 'whats app'], 'domains' => ['whatsapp.com']],
    ['brand' => 'Telegram', 'names' => ['telegram'], 'domains' => ['telegram.org']],
    ['brand' => 'Slack', 'names' => ['slack'], 'domains' => ['slack.com']],
    ['brand' => 'Zoom', 'names' => ['zoom'], 'domains' => ['zoom.us', 'zoom.com']],
    ['brand' => 'GitHub', 'names' => ['github'], 'domains' => ['github.com']],
    ['brand' => 'GitLab', 'names' => ['gitlab'], 'domains' => ['gitlab.com']],
    ['brand' => 'Atlassian', 'names' => ['atlassian', 'jira', 'confluence'], 'domains' => ['atlassian.com']],
    ['brand' => 'Cloudflare', 'names' => ['cloudflare'], 'domains' => ['cloudflare.com']],
    ['brand' => 'GoDaddy', 'names' => ['godaddy', 'go daddy'], 'domains' => ['godaddy.com']],
    ['brand' => 'Hostinger', 'names' => ['hostinger'], 'domains' => ['hostinger.com']],
    ['brand' => 'Shopify', 'names' => ['shopify'], 'domains' => ['shopify.com']],
    ['brand' => 'eBay', 'names' => ['ebay'], 'domains' => ['ebay.com', 'ebay.co.uk', 'ebay.de', 'ebay.fr', 'ebay.it', 'ebay.es', 'ebay.ca', 'ebay.com.au']],
    ['brand' => 'Airbnb', 'names' => ['airbnb'], 'domains' => ['airbnb.com']],
    ['brand' => 'Booking.com', 'names' => ['booking.com', 'booking support'], 'domains' => ['booking.com']],
    ['brand' => 'Spotify', 'names' => ['spotify'], 'domains' => ['spotify.com']]
  ];

  $normalizedName = strtolower($senderName);
  foreach ($brands as $brand) {
    $nameMatches = false;
    foreach ($brand['names'] as $knownName) {
      $pattern = '/(^|[^a-z0-9])' . preg_quote(strtolower($knownName), '/') . '([^a-z0-9]|$)/i';
      if (preg_match($pattern, $normalizedName)) {
        $nameMatches = true;
        break;
      }
    }
    if ($nameMatches && !pseDomainIsAllowed($senderDomain, $brand['domains'])) {
      return [
        'brand' => (string)$brand['brand'],
        'senderName' => $senderName,
        'senderEmail' => $senderEmail,
        'senderDomain' => $senderDomain
      ];
    }
  }
  return null;
}

function pseMessageDetails(
  array $settings,
  string $folder,
  string $uid,
  bool $loadRemote,
  bool $markSeen = true,
  bool $prefetchOnly = false
): array
{
  if (pseIsGmailAccount($settings)) {
    return pseGmailMessageDetails(
      $settings,
      $folder,
      $uid,
      $loadRemote,
      $markSeen,
      $prefetchOnly
    );
  }
  $imapUid = (int)$uid;
  if ($imapUid <= 0) {
    throw new RuntimeException('Invalid IMAP message identifier.');
  }
  $imap = pseOpenImap($settings, $folder, !$markSeen);
  $overview = @imap_fetch_overview($imap, (string)$imapUid, FT_UID);
  if (!is_array($overview) || empty($overview[0])) {
    imap_close($imap);
    throw new RuntimeException('Message not found.');
  }
  $o = $overview[0];
  $headerRaw = @imap_fetchheader($imap, $imapUid, FT_UID);
  $header = @imap_rfc822_parse_headers(is_string($headerRaw) ? $headerRaw : '');
  $structure = @imap_fetchstructure($imap, $imapUid, FT_UID);
  $content = ['plain' => '', 'html' => '', 'attachments' => [], 'inline' => []];
  if ($structure) {
    pseCollectParts($imap, $imapUid, $structure, '', $content);
  }
  $cidUrls = [];
  $embeddedImagesBlocked = 0;
  $referencedCids = pseReferencedCidSet($content['html']);
  foreach ($content['attachments'] as &$attachment) {
    $partNo = (string)$attachment['part'];
    $cid = trim((string)($attachment['cid'] ?? ''), '<>');
    $cidKey = strtolower(rawurldecode($cid));
    $referencedInline = !empty($attachment['inline']) &&
      $cidKey !== '' &&
      isset($referencedCids[$cidKey]);
    $attachment['url'] = pseAttachmentUrl($settings, $folder, (string)$imapUid, $partNo);

    // A previously cached image is free to reuse; no IMAP fetch is made.
    $existing = pseCachedAttachmentRecord($settings, $folder, (string)$imapUid, $partNo);
    if (!empty($existing)) {
      $attachment['url'] = pseCachedAttachmentUrlFromRecord($settings, $existing);
      if ($referencedInline) {
        $cidUrls[$cid] = (string)$attachment['url'];
      }
      continue;
    }

    // CID images are represented by signed attachment URLs immediately. Their
    // binary data is not part of the message-open response; the browser requests
    // each image afterward, so the HTML can paint first.
    if ($referencedInline) {
      if (!empty($attachment['previewable'])) {
        $cidUrls[$cid] = (string)$attachment['url'];
      } elseif (!$prefetchOnly) {
        $embeddedImagesBlocked++;
      }
    }
  }
  unset($attachment);
  $content['html'] = pseReplaceCidUrls($content['html'], $cidUrls);
  $cacheSourceHtml = $content['html'];
  $messageSize = (int)($o->size ?? 0);
  $forceImageBlocking = $messageSize > PSE_LARGE_MESSAGE_BYTES;
  $remotePrepared = psePrepareRemoteImageHtml(
    $settings,
    $folder,
    (string)$imapUid,
    $content['html'],
    $loadRemote,
    $forceImageBlocking
  );
  $content['html'] = (string)$remotePrepared['html'];
  if ($content['html'] === '' && $content['plain'] !== '') {
    $content['html'] = '<pre style="white-space:pre-wrap;font:14px/1.55 Arial,sans-serif;margin:0">' .
      htmlspecialchars($content['plain'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
      '</pre>';
  }
  $wasSeen = !empty($o->seen);
  if ($markSeen) {
    @imap_setflag_full($imap, (string)$imapUid, '\\Seen', ST_UID);
  }
  $fromRaw = is_object($header) ? (string)($header->fromaddress ?? '') : (string)($o->from ?? '');
  $toRaw = is_object($header) ? (string)($header->toaddress ?? '') : (string)($o->to ?? '');
  $ccRaw = is_object($header) ? (string)($header->ccaddress ?? '') : '';
  $messageId = is_object($header) ? trim((string)($header->message_id ?? '')) : '';
  $from = pseAddressList($fromRaw);
  $timestamp = isset($o->udate)
    ? (int)$o->udate
    : (strtotime((string)($o->date ?? '')) ?: 0);
  imap_close($imap);
  return [
    'uid' => (string)$imapUid,
    'subject' => pseMime((string)($o->subject ?? '(No subject)')),
    'from' => $from,
    'to' => pseAddressList($toRaw),
    'cc' => pseAddressList($ccRaw),
    'replyTo' => is_object($header) ? pseAddressList((string)($header->reply_toaddress ?? '')) : [],
    'date' => pseFormatDate($timestamp, $settings),
    'timestamp' => $timestamp,
    'messageId' => $messageId,
    'impersonationWarning' => pseBrandImpersonationWarning($from),
    'html' => $content['html'],
    'plain' => $content['plain'] !== '' ? $content['plain'] : trim(strip_tags($content['html'])),
    'remoteImages' => !empty($remotePrepared['remoteImages']),
    'remoteImagesBlocked' => !empty($remotePrepared['remoteImagesBlocked']),
    'attachments' => $content['attachments'],
    'downloadAllUrl' => $content['attachments']
      ? pseAttachmentDownloadAllUrl($settings, $folder, (string)$imapUid)
      : '',
    'embeddedImagesBlocked' => $embeddedImagesBlocked,
    'largeMessage' => $messageSize > PSE_LARGE_MESSAGE_BYTES,
    'seen' => $markSeen ? true : $wasSeen,
    'answered' => !empty($o->answered),
    'size' => $messageSize,
    '_cacheSourceHtml' => $cacheSourceHtml,
    '_cachePrefetched' => $prefetchOnly
  ];
}

function pseSpecialFolder(array $settings, string $special): string
{
  foreach (pseFolders($settings) as $folder) {
    if ($folder['special'] === $special) {
      return (string)$folder['id'];
    }
  }
  return '';
}

function pseMoveMessage(array $settings, string $folder, string $uid, string $target): void
{
  if (pseIsGmailAccount($settings)) {
    if ($target === '__archive__') {
      throw new RuntimeException('Archive action is disabled.');
    }
    pseGmailMoveMessage($settings, $uid);
    return;
  }
  $imapUid = (int)$uid;
  if ($imapUid <= 0) {
    throw new RuntimeException('Invalid IMAP message identifier.');
  }
  $imap = pseOpenImap($settings, $folder, false);
  $destination = $target;
  if ($target === '__archive__') {
    imap_close($imap);
    throw new RuntimeException('Archive action is disabled.');
  } elseif ($target === '__trash__') {
    imap_close($imap);
    $destination = pseSpecialFolder($settings, 'trash');
    $imap = pseOpenImap($settings, $folder, false);
  }
  if ($destination !== '') {
    if (!@imap_mail_move($imap, (string)$imapUid, $destination, CP_UID)) {
      $error = imap_last_error();
      imap_close($imap);
      throw new RuntimeException('Unable to move message: ' . ($error ?: 'unknown error'));
    }
  } else {
    if (!@imap_delete($imap, (string)$imapUid, FT_UID)) {
      $error = imap_last_error();
      imap_close($imap);
      throw new RuntimeException('Unable to delete message: ' . ($error ?: 'unknown error'));
    }
  }
  imap_close($imap, CL_EXPUNGE);
}

function pseSetFlag(array $settings, string $folder, string $uid, string $flag, bool $enabled): void
{
  if (pseIsGmailAccount($settings)) {
    pseGmailSetFlag($settings, $uid, $flag, $enabled);
    return;
  }
  $allowed = ['\\Seen', '\\Answered'];
  if (!in_array($flag, $allowed, true)) {
    throw new RuntimeException('Unsupported message flag.');
  }
  $imapUid = (int)$uid;
  if ($imapUid <= 0) {
    throw new RuntimeException('Invalid IMAP message identifier.');
  }
  $imap = pseOpenImap($settings, $folder, false);
  $ok = $enabled
    ? @imap_setflag_full($imap, (string)$imapUid, $flag, ST_UID)
    : @imap_clearflag_full($imap, (string)$imapUid, $flag, ST_UID);
  if (!$ok) {
    $error = imap_last_error();
    imap_close($imap);
    throw new RuntimeException('Unable to update message: ' . ($error ?: 'unknown error'));
  }
  imap_close($imap);
}

function pseBulkMessages(
  array $settings,
  string $folder,
  array $uids,
  string $operation,
  string $confirmation = ''
): int
{
  if (!in_array($operation, ['delete', 'delete_forever', 'restore', 'read', 'unread'], true)) {
    throw new RuntimeException('Unsupported bulk operation.');
  }

  $folder = trim($folder);
  $trash = '';
  $inbox = '';
  if ($operation === 'restore' || $operation === 'delete_forever') {
    $trash = pseSpecialFolder($settings, 'trash');
    if ($trash === '' || strcasecmp($trash, $folder) !== 0) {
      throw new RuntimeException('Restore and permanent delete are allowed only from the Trash folder.');
    }
    if ($operation === 'restore') {
      $inbox = pseSpecialFolder($settings, 'inbox');
      if ($inbox === '') {
        $inbox = 'INBOX';
      }
    }
  }

  if (pseIsGmailAccount($settings)) {
    return pseGmailBulkMessages($settings, $uids, $operation, $confirmation);
  }
  $uids = array_values(array_unique(array_filter(array_map('intval', $uids), function (int $uid): bool {
    return $uid > 0;
  })));
  if (empty($uids)) {
    throw new RuntimeException('Select at least one message.');
  }
  if (count($uids) > 500) {
    throw new RuntimeException('Bulk operations are limited to 500 messages at a time.');
  }
  if (
    $operation === 'delete' &&
    !empty($settings['confirm_delete_messages']) &&
    count($uids) > 10 &&
    $confirmation !== 'YES I AM SURE'
  ) {
    throw new RuntimeException('Type YES I AM SURE to delete more than 10 messages.');
  }
  $sequence = implode(',', $uids);
  if ($operation === 'delete' && $trash === '') {
    $trash = pseSpecialFolder($settings, 'trash');
  }
  $imap = pseOpenImap($settings, $folder, false);
  if ($operation === 'read') {
    $ok = @imap_setflag_full($imap, $sequence, '\\Seen', ST_UID);
  } elseif ($operation === 'unread') {
    $ok = @imap_clearflag_full($imap, $sequence, '\\Seen', ST_UID);
  } elseif ($operation === 'restore') {
    $ok = @imap_mail_move($imap, $sequence, $inbox, CP_UID);
  } elseif ($operation === 'delete_forever') {
    $ok = @imap_delete($imap, $sequence, FT_UID);
  } elseif ($trash !== '' && strcasecmp($trash, $folder) !== 0) {
    $ok = @imap_mail_move($imap, $sequence, $trash, CP_UID);
  } else {
    $ok = @imap_delete($imap, $sequence, FT_UID);
  }
  if (!$ok) {
    $error = imap_last_error();
    imap_close($imap);
    throw new RuntimeException('Bulk operation failed: ' . ($error ?: 'unknown error'));
  }
  $expunge = in_array($operation, ['delete', 'delete_forever', 'restore'], true);
  imap_close($imap, $expunge ? CL_EXPUNGE : 0);
  return count($uids);
}


function pseActionQueueTransaction(callable $callback)
{
  pseEnsureStorage();
  $handle = @fopen(PSE_ACTION_QUEUE_FILE . '.lock', 'c+');
  if (!$handle) {
    throw new RuntimeException('Unable to open the action queue.');
  }
  try {
    if (!@flock($handle, LOCK_EX)) {
      throw new RuntimeException('Unable to lock the action queue.');
    }
    $queue = pseReadJson(PSE_ACTION_QUEUE_FILE, []);
    $outcome = $callback(array_values($queue));
    if (
      !is_array($outcome) ||
      !isset($outcome['queue']) ||
      !is_array($outcome['queue'])
    ) {
      throw new RuntimeException('Invalid action queue transaction.');
    }
    pseWriteJson(PSE_ACTION_QUEUE_FILE, array_values($outcome['queue']));
    @chmod(PSE_ACTION_QUEUE_FILE . '.lock', 0640);
    return $outcome['result'] ?? null;
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

function pseActionQueueCount(): int
{
  pseEnsureStorage();
  $handle = @fopen(PSE_ACTION_QUEUE_FILE . '.lock', 'c+');
  if (!$handle) {
    return 0;
  }
  try {
    if (!@flock($handle, LOCK_SH)) {
      return 0;
    }
    return count(pseReadJson(PSE_ACTION_QUEUE_FILE, []));
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

function pseNormalizeQueuedUids(array $settings, array $uids): array
{
  $gmail = pseIsGmailAccount($settings);
  $normalized = [];
  foreach ($uids as $uid) {
    $uid = trim((string)$uid);
    if ($gmail) {
      if (!preg_match('/^[a-zA-Z0-9_-]+$/', $uid)) {
        continue;
      }
    } elseif (!ctype_digit($uid) || (int)$uid <= 0) {
      continue;
    }
    $normalized[$uid] = $uid;
  }
  return array_values($normalized);
}

function pseQueueDeleteMessages(
  array $settings,
  string $folder,
  array $uids,
  string $confirmation = ''
): array {
  $folder = trim($folder);
  if ($folder === '' || strlen($folder) > 1000) {
    throw new RuntimeException('Invalid email folder.');
  }
  $uids = pseNormalizeQueuedUids($settings, $uids);
  if (empty($uids)) {
    throw new RuntimeException('Select at least one message.');
  }
  if (count($uids) > 500) {
    throw new RuntimeException('Queued deletions are limited to 500 messages at a time.');
  }
  if (
    !empty($settings['confirm_delete_messages']) &&
    count($uids) > 10 &&
    $confirmation !== 'YES I AM SURE'
  ) {
    throw new RuntimeException('Type YES I AM SURE to delete more than 10 messages.');
  }
  $accountId = (string)$settings['account_id'];
  $result = pseActionQueueTransaction(function (array $queue) use ($accountId, $folder, $uids): array {
    $existing = [];
    foreach ($queue as $item) {
      if (!is_array($item)) {
        continue;
      }
      $key = implode("\0", [
        (string)($item['action'] ?? ''),
        (string)($item['account_id'] ?? ''),
        (string)($item['folder'] ?? ''),
        (string)($item['uid'] ?? '')
      ]);
      $existing[$key] = true;
    }
    $queued = 0;
    $queuedUids = [];
    foreach ($uids as $uid) {
      $key = implode("\0", ['delete', $accountId, $folder, $uid]);
      if (isset($existing[$key])) {
        continue;
      }
      $queue[] = [
        'id' => bin2hex(random_bytes(12)),
        'action' => 'delete',
        'account_id' => $accountId,
        'folder' => $folder,
        'uid' => $uid,
        'created_at' => date('c'),
        'attempts' => 0,
        'last_error' => ''
      ];
      $existing[$key] = true;
      $queued++;
      $queuedUids[] = $uid;
    }
    return [
      'queue' => $queue,
      'result' => [
        'queued' => $queued,
        'pending' => count($queue),
        'queued_uids' => $queuedUids
      ]
    ];
  });
  pseMailCacheDeleteMessages(
    $settings,
    $folder,
    is_array($result['queued_uids'] ?? null) ? $result['queued_uids'] : []
  );
  return $result;
}

function pseActionQueueExistingIds(array $ids): array
{
  $wanted = array_fill_keys(array_map('strval', $ids), true);
  if (empty($wanted)) {
    return [];
  }
  pseEnsureStorage();
  $handle = @fopen(PSE_ACTION_QUEUE_FILE . '.lock', 'c+');
  if (!$handle) {
    return [];
  }
  try {
    if (!@flock($handle, LOCK_SH)) {
      return [];
    }
    $existing = [];
    foreach (pseReadJson(PSE_ACTION_QUEUE_FILE, []) as $action) {
      if (!is_array($action)) {
        continue;
      }
      $id = (string)($action['id'] ?? '');
      if ($id !== '' && isset($wanted[$id])) {
        $existing[$id] = true;
      }
    }
    return $existing;
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

function pseUndoQueuedDeleteOperations(array $settings): array
{
  $result = pseActionQueueTransaction(function (array $queue): array {
    $remaining = [];
    $removed = [];
    foreach ($queue as $action) {
      if (is_array($action) && (string)($action['action'] ?? '') === 'delete') {
        $removed[] = $action;
        continue;
      }
      $remaining[] = $action;
    }
    return [
      'queue' => $remaining,
      'result' => [
        'removed' => $removed,
        'pending' => count($remaining)
      ]
    ];
  });

  $affectedAccounts = [];
  foreach ((array)($result['removed'] ?? []) as $action) {
    if (!is_array($action)) {
      continue;
    }
    $accountId = pseSafeAccountId((string)($action['account_id'] ?? ''));
    if ($accountId !== '') {
      $affectedAccounts[$accountId] = true;
    }
  }
  foreach (array_keys($affectedAccounts) as $accountId) {
    $accountSettings = pseSettingsForAccount($settings, (string)$accountId);
    if ($accountSettings === null) {
      continue;
    }
    pseMailCacheClearLists($accountSettings);
    @unlink(pseMailCacheFoldersFile($accountSettings));
  }

  return [
    'removed' => count((array)($result['removed'] ?? [])),
    'pending' => (int)($result['pending'] ?? 0),
    'accounts' => count($affectedAccounts)
  ];
}

function pseReserveActionQueue(int $limit = 5000): array
{
  return pseActionQueueTransaction(function (array $queue) use ($limit): array {
    $selected = [];
    $reservationId = bin2hex(random_bytes(12));
    $now = time();
    foreach ($queue as $index => $action) {
      if (!is_array($action) || count($selected) >= $limit) {
        continue;
      }
      $reservedAt = strtotime((string)($action['reserved_at'] ?? '')) ?: 0;
      if ($reservedAt > $now - 300) {
        continue;
      }
      $action['reservation_id'] = $reservationId;
      $action['reserved_at'] = date('c', $now);
      $queue[$index] = $action;
      $selected[] = $action;
    }
    return ['queue' => $queue, 'result' => $selected];
  });
}

function pseFinishActionQueue(array $completedIds, array $failedActions): int
{
  $completed = array_fill_keys(array_map('strval', $completedIds), true);
  $failed = [];
  foreach ($failedActions as $action) {
    if (is_array($action) && isset($action['id'])) {
      $failed[(string)$action['id']] = $action;
    }
  }
  return pseActionQueueTransaction(function (array $queue) use ($completed, $failed): array {
    $remaining = [];
    foreach ($queue as $action) {
      if (!is_array($action)) {
        continue;
      }
      $id = (string)($action['id'] ?? '');
      if ($id !== '' && isset($completed[$id])) {
        continue;
      }
      if ($id !== '' && isset($failed[$id])) {
        $action = $failed[$id];
        unset($action['reservation_id'], $action['reserved_at']);
      }
      $remaining[] = $action;
    }
    return ['queue' => $remaining, 'result' => count($remaining)];
  });
}

function pseSettingsForAccount(array $settings, string $accountId): ?array
{
  $settings = pseNormalizeSettings($settings);
  $accountId = pseSafeAccountId($accountId);
  if ($accountId === '' || !isset($settings['accounts'][$accountId])) {
    return null;
  }
  $account = $settings['accounts'][$accountId];
  foreach (pseAccountSettingKeys() as $key) {
    $settings[$key] = $account[$key];
  }
  $settings['active_account_id'] = $accountId;
  $settings['account_id'] = $accountId;
  $settings['account_name'] = (string)$account['name'];
  return $settings;
}

function pseExistingImapUids(array $settings, string $folder, array $uids): array
{
  $imap = pseOpenImap($settings, $folder, true);
  $existing = [];
  try {
    foreach ($uids as $uid) {
      $uid = (int)$uid;
      if ($uid > 0 && @imap_msgno($imap, $uid) > 0) {
        $existing[] = (string)$uid;
      }
    }
  } finally {
    imap_close($imap);
  }
  return $existing;
}

function pseHandleActionQueue(array $settings): array
{
  try {
    $actions = pseReserveActionQueue();
  } catch (Throwable $error) {
    return [
      'processed' => 0,
      'failed' => 1,
      'pending' => pseActionQueueCount(),
      'error' => $error->getMessage()
    ];
  }
  if (empty($actions)) {
    return [
      'processed' => 0,
      'failed' => 0,
      'pending' => pseActionQueueCount()
    ];
  }
  $groups = [];
  $completedIds = [];
  $failedActions = [];
  $processed = 0;
  $failed = 0;
  foreach ($actions as $action) {
    if (!is_array($action) || (string)($action['action'] ?? '') !== 'delete') {
      if (is_array($action)) {
        $action['attempts'] = (int)($action['attempts'] ?? 0) + 1;
        $action['last_error'] = 'Unsupported queued action.';
        $action['last_attempt_at'] = date('c');
        $failedActions[] = $action;
      }
      $failed++;
      continue;
    }
    $accountId = (string)($action['account_id'] ?? '');
    $folder = (string)($action['folder'] ?? '');
    $uid = (string)($action['uid'] ?? '');
    $key = hash('sha256', $accountId . "\0" . $folder);
    if (!isset($groups[$key])) {
      $groups[$key] = [
        'account_id' => $accountId,
        'folder' => $folder,
        'actions' => []
      ];
    }
    $groups[$key]['actions'][] = $action;
  }
  foreach ($groups as $group) {
    $accountSettings = pseSettingsForAccount($settings, (string)$group['account_id']);
    if ($accountSettings === null) {
      $failed += count($group['actions']);
      foreach ($group['actions'] as $action) {
        $completedIds[] = (string)$action['id'];
      }
      continue;
    }
    foreach (array_chunk($group['actions'], 500) as $chunk) {
      $stillPending = pseActionQueueExistingIds(array_map(function (array $action): string {
        return (string)($action['id'] ?? '');
      }, $chunk));
      $chunk = array_values(array_filter($chunk, function (array $action) use ($stillPending): bool {
        return isset($stillPending[(string)($action['id'] ?? '')]);
      }));
      if (empty($chunk)) {
        continue;
      }
      $uids = array_map(function (array $action): string {
        return (string)$action['uid'];
      }, $chunk);
      try {
        $processed += pseBulkMessages(
          $accountSettings,
          (string)$group['folder'],
          $uids,
          'delete',
          'YES I AM SURE'
        );
        foreach ($chunk as $action) {
          $completedIds[] = (string)$action['id'];
        }
      } catch (Throwable $error) {
        $retryChunk = $chunk;
        $retryError = $error;
        if (!pseIsGmailAccount($accountSettings)) {
          try {
            $existingUids = pseExistingImapUids(
              $accountSettings,
              (string)$group['folder'],
              $uids
            );
            $existingMap = array_fill_keys($existingUids, true);
            $retryChunk = [];
            foreach ($chunk as $action) {
              if (isset($existingMap[(string)$action['uid']])) {
                $retryChunk[] = $action;
              } else {
                $completedIds[] = (string)$action['id'];
                $processed++;
              }
            }
            if (!empty($retryChunk)) {
              $retryUids = array_map(function (array $action): string {
                return (string)$action['uid'];
              }, $retryChunk);
              try {
                $processed += pseBulkMessages(
                  $accountSettings,
                  (string)$group['folder'],
                  $retryUids,
                  'delete',
                  'YES I AM SURE'
                );
                foreach ($retryChunk as $action) {
                  $completedIds[] = (string)$action['id'];
                }
                $retryChunk = [];
              } catch (Throwable $retryFailure) {
                $retryError = $retryFailure;
              }
            }
          } catch (Throwable $existenceFailure) {
            $retryChunk = $chunk;
          }
        }
        $failed += count($retryChunk);
        foreach ($retryChunk as $action) {
          $action['attempts'] = (int)($action['attempts'] ?? 0) + 1;
          $action['last_error'] = $retryError->getMessage();
          $action['last_attempt_at'] = date('c');
          $failedActions[] = $action;
        }
      }
    }
  }
  try {
    $pending = pseFinishActionQueue($completedIds, $failedActions);
  } catch (Throwable $error) {
    return [
      'processed' => $processed,
      'failed' => $failed + 1,
      'pending' => pseActionQueueCount(),
      'error' => $error->getMessage()
    ];
  }
  return [
    'processed' => $processed,
    'failed' => $failed,
    'pending' => $pending
  ];
}

function pseEncodeHeader(string $text): string
{
  if ($text === '') {
    return '';
  }
  if (function_exists('mb_encode_mimeheader')) {
    return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
  }
  return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function pseNormalizeRecipients($value): array
{
  $items = is_array($value) ? $value : preg_split('/\s*[,;]\s*/', (string)$value);
  $result = [];
  foreach ($items as $item) {
    if (is_array($item)) {
      $email = trim((string)($item['email'] ?? ''));
      $name = trim((string)($item['name'] ?? ''));
    } else {
      $email = trim((string)$item);
      $name = '';
      if (preg_match('/^(.*?)<([^>]+)>$/', $email, $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"'");
        $email = trim($m[2]);
      }
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $result[strtolower($email)] = ['name' => $name, 'email' => $email];
    }
  }
  return array_values($result);
}

function pseFormatRecipient(array $recipient): string
{
  return $recipient['name'] !== ''
    ? pseEncodeHeader($recipient['name']) . ' <' . $recipient['email'] . '>'
    : $recipient['email'];
}

class PseSmtp
{
  private $socket = null;
  private array $settings;

  public function __construct(array $settings)
  {
    $this->settings = $settings;
  }

  private function readResponse(): array
  {
    if (!is_resource($this->socket)) {
      throw new RuntimeException('SMTP socket is not open.');
    }
    $lines = [];
    while (($line = fgets($this->socket, 8192)) !== false) {
      $lines[] = rtrim($line, "\r\n");
      if (strlen($line) >= 4 && $line[3] === ' ') {
        break;
      }
    }
    $last = end($lines);
    $code = is_string($last) ? (int)substr($last, 0, 3) : 0;
    return [$code, implode("\n", $lines)];
  }

  private function expect(array $codes): string
  {
    [$code, $text] = $this->readResponse();
    if (!in_array($code, $codes, true)) {
      throw new RuntimeException('SMTP error ' . $code . ': ' . $text);
    }
    return $text;
  }

  private function command(string $command, array $codes): string
  {
    if (!is_resource($this->socket)) {
      throw new RuntimeException('SMTP socket is not open.');
    }
    fwrite($this->socket, $command . "\r\n");
    return $this->expect($codes);
  }

  public function connect(): void
  {
    $host = (string)$this->settings['smtp_host'];
    $port = (int)$this->settings['smtp_port'];
    $encryption = (string)$this->settings['smtp_encryption'];
    $prefix = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create([
      'ssl' => [
        'verify_peer' => !empty($this->settings['smtp_validate_cert']),
        'verify_peer_name' => !empty($this->settings['smtp_validate_cert']),
        'allow_self_signed' => empty($this->settings['smtp_validate_cert']),
        'peer_name' => $host,
        'SNI_enabled' => true
      ]
    ]);
    $errno = 0;
    $errstr = '';
    $this->socket = @stream_socket_client(
      $prefix . $host . ':' . $port,
      $errno,
      $errstr,
      20,
      STREAM_CLIENT_CONNECT,
      $context
    );
    if (!is_resource($this->socket)) {
      throw new RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
    }
    stream_set_timeout($this->socket, 25);
    $this->expect([220]);
    $hostName = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $this->command('EHLO ' . ($hostName ?: 'localhost'), [250]);

    if ($encryption === 'tls') {
      $this->command('STARTTLS', [220]);
      $method = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
        ? STREAM_CRYPTO_METHOD_TLS_CLIENT
        : STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
      if (!@stream_socket_enable_crypto($this->socket, true, $method)) {
        throw new RuntimeException('Unable to start SMTP TLS encryption.');
      }
      $this->command('EHLO ' . ($hostName ?: 'localhost'), [250]);
    }

    $username = (string)$this->settings['smtp_username'];
    $password = pseDecrypt(
      (string)$this->settings['smtp_password_enc'],
      (string)$this->settings['storage_key']
    );
    if ($username === '' || $password === '') {
      throw new RuntimeException('SMTP username/password is not configured.');
    }
    $this->command('AUTH LOGIN', [334]);
    $this->command(base64_encode($username), [334]);
    $this->command(base64_encode($password), [235]);
  }

  public function send(string $from, array $recipients, string $rawMessage): void
  {
    $this->command('MAIL FROM:<' . $from . '>', [250]);
    foreach ($recipients as $email) {
      $this->command('RCPT TO:<' . $email . '>', [250, 251]);
    }
    $this->command('DATA', [354]);
    $rawMessage = preg_replace('/(?m)^\./', '..', $rawMessage) ?? $rawMessage;
    fwrite($this->socket, $rawMessage . "\r\n.\r\n");
    $this->expect([250]);
  }

  public function quit(): void
  {
    if (is_resource($this->socket)) {
      try {
        $this->command('QUIT', [221]);
      } catch (Throwable $e) {
      }
      fclose($this->socket);
      $this->socket = null;
    }
  }

  public function __destruct()
  {
    $this->quit();
  }
}

function pseAppendRawHtmlSignature(string $html, string $signature): string
{
  $signature = trim($signature);
  if ($signature === '') {
    return $html;
  }
  $addition = '<br><br>' . $signature;
  if (preg_match('/<\/body\s*>/i', $html)) {
    return preg_replace_callback(
      '/<\/body\s*>/i',
      function (array $match) use ($addition): string {
        return $addition . $match[0];
      },
      $html,
      1
    ) ?? ($html . $addition);
  }
  if (preg_match('/<\/html\s*>/i', $html)) {
    return preg_replace_callback(
      '/<\/html\s*>/i',
      function (array $match) use ($addition): string {
        return $addition . $match[0];
      },
      $html,
      1
    ) ?? ($html . $addition);
  }
  return $html . $addition;
}

function pseBuildMail(array $settings, array $data): array
{
  $to = pseNormalizeRecipients($data['to'] ?? []);
  $cc = pseNormalizeRecipients($data['cc'] ?? []);
  $bcc = pseNormalizeRecipients($data['bcc'] ?? []);
  if (empty($to) && empty($cc) && empty($bcc)) {
    throw new RuntimeException('Add at least one recipient.');
  }
  $subject = trim((string)($data['subject'] ?? ''));
  $html = (string)($data['bodyHtml'] ?? '');
  $text = trim((string)($data['bodyText'] ?? ''));
  if ($html === '' && $text !== '') {
    $html = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
  }
  if ($text === '') {
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }
  $signature = trim((string)$settings['signature']);
  $signatureHandled = !empty($data['signatureHandled']);
  $signatureApplied = !empty($data['signaturePresent']);
  if ($signature !== '' && !$signatureHandled) {
    $html = pseAppendRawHtmlSignature($html, $signature);
    $signatureText = trim(
      html_entity_decode(strip_tags($signature), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );
    if ($signatureText !== '') {
      $text .= "\n\n" . $signatureText;
    }
    $signatureApplied = true;
  }
  $inlineImages = [];
  $html = preg_replace_callback(
    '/<img\b([^>]*?)\bsrc\s*=\s*(["\'])data:(image\/[a-z0-9.+-]+);base64,([^"\']+)\2([^>]*)>/is',
    function (array $match) use (&$inlineImages): string {
      $encoded = preg_replace('/\s+/', '', $match[4]) ?? $match[4];
      $binary = base64_decode($encoded, true);
      if ($binary === false) {
        throw new RuntimeException('An inline image could not be decoded.');
      }
      if (strlen($binary) > PSE_MAX_INLINE_IMAGE_BYTES) {
        throw new RuntimeException('Inline images are limited to 5 MB each.');
      }
      $mime = strtolower($match[3]);
      $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
      ];
      $number = count($inlineImages) + 1;
      $cid = 'pse-inline-' . $number . '-' . bin2hex(random_bytes(8)) . '@local';
      $inlineImages[] = [
        'cid' => $cid,
        'mime' => $mime,
        'name' => 'inline-image-' . $number . '.' . ($extensions[$mime] ?? 'bin'),
        'data' => $binary
      ];
      return '<img' . $match[1] . 'src="cid:' . $cid . '"' . $match[5] . '>';
    },
    $html
  ) ?? $html;
  $attachments = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];
  $totalBytes = array_sum(array_map(function (array $image): int {
    return strlen($image['data']);
  }, $inlineImages));
  foreach ($attachments as $attachment) {
    if (!is_array($attachment)) {
      continue;
    }
    $uploadId = pseAttachmentUploadSafeId((string)($attachment['uploadId'] ?? ''));
    if ($uploadId !== '') {
      $manifest = pseAttachmentUploadManifest($settings, $uploadId, true);
      $totalBytes += (int)$manifest['size'];
      continue;
    }
    $encoded = (string)($attachment['data'] ?? '');
    $totalBytes += (int)(strlen($encoded) * 0.75);
  }
  if ($totalBytes > PSE_MAX_ATTACHMENT_BYTES) {
    throw new RuntimeException('Attachments exceed the 15 MB application limit.');
  }

  $fromEmail = trim((string)$settings['from_email']);
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Configure a valid From email address.');
  }
  $fromName = trim((string)$settings['from_name']);
  $boundaryMixed = 'pse_mix_' . bin2hex(random_bytes(12));
  $boundaryRelated = 'pse_rel_' . bin2hex(random_bytes(12));
  $boundaryAlt = 'pse_alt_' . bin2hex(random_bytes(12));
  $messageId = '<' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($fromEmail, '@') ?: '@localhost', 1) . '>';

  $headers = [
    'Date: ' . date(DATE_RFC2822),
    'Message-ID: ' . $messageId,
    'From: ' . ($fromName !== '' ? pseEncodeHeader($fromName) . ' <' . $fromEmail . '>' : $fromEmail),
    'To: ' . implode(', ', array_map('pseFormatRecipient', $to)),
    'Subject: ' . pseEncodeHeader($subject !== '' ? $subject : '(No subject)'),
    'MIME-Version: 1.0',
    'X-Mailer: ' . PSE_NAME . '/' . PSE_VERSION
  ];
  if (!empty($cc)) {
    $headers[] = 'Cc: ' . implode(', ', array_map('pseFormatRecipient', $cc));
  }
  if ((string)$settings['reply_to'] !== '' && filter_var($settings['reply_to'], FILTER_VALIDATE_EMAIL)) {
    $headers[] = 'Reply-To: ' . $settings['reply_to'];
  }
  $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"';

  $body = '--' . $boundaryMixed . "\r\n";
  if (!empty($inlineImages)) {
    $body .= 'Content-Type: multipart/related; boundary="' . $boundaryRelated . "\"\r\n\r\n";
    $body .= '--' . $boundaryRelated . "\r\n";
  }
  $body .= 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . "\"\r\n\r\n";
  $body .= '--' . $boundaryAlt . "\r\n";
  $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
  $body .= chunk_split(base64_encode($text)) . "\r\n";
  $body .= '--' . $boundaryAlt . "\r\n";
  $body .= "Content-Type: text/html; charset=UTF-8\r\n";
  $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
  $body .= chunk_split(base64_encode($html)) . "\r\n";
  $body .= '--' . $boundaryAlt . "--\r\n";

  foreach ($inlineImages as $inlineImage) {
    $body .= '--' . $boundaryRelated . "\r\n";
    $body .= 'Content-Type: ' . $inlineImage['mime'] . '; name="' . $inlineImage['name'] . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= 'Content-ID: <' . $inlineImage['cid'] . ">\r\n";
    $body .= 'Content-Disposition: inline; filename="' . $inlineImage['name'] . "\"\r\n\r\n";
    $body .= chunk_split(base64_encode($inlineImage['data'])) . "\r\n";
  }
  if (!empty($inlineImages)) {
    $body .= '--' . $boundaryRelated . "--\r\n";
  }

  foreach ($attachments as $attachment) {
    if (!is_array($attachment)) {
      continue;
    }
    $uploadId = pseAttachmentUploadSafeId((string)($attachment['uploadId'] ?? ''));
    if ($uploadId !== '') {
      $manifest = pseAttachmentUploadManifest($settings, $uploadId, true);
      $name = basename((string)$manifest['name']);
      $mime = preg_replace('/[^a-z0-9.+-\/]/i', '', (string)$manifest['type']);
      $encodedBody = pseUploadedAttachmentBase64($settings, $uploadId);
    } else {
      $name = basename((string)($attachment['name'] ?? 'attachment.bin'));
      $mime = preg_replace('/[^a-z0-9.+-\/]/i', '', (string)($attachment['type'] ?? 'application/octet-stream'));
      $encoded = preg_replace('/^data:[^,]+,/', '', (string)($attachment['data'] ?? ''));
      $encoded = preg_replace('/\s+/', '', $encoded) ?? $encoded;
      if ($encoded === '') {
        continue;
      }
      $encodedBody = chunk_split($encoded);
    }
    $safeName = addcslashes($name, "\"\\");
    $body .= '--' . $boundaryMixed . "\r\n";
    $body .= 'Content-Type: ' . ($mime ?: 'application/octet-stream') . '; name="' . $safeName . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= 'Content-Disposition: attachment; filename="' . $safeName . "\"\r\n\r\n";
    $body .= $encodedBody . "\r\n";
  }
  $body .= '--' . $boundaryMixed . '--';
  $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;
  $all = array_merge($to, $cc, $bcc);
  return [
    'from' => $fromEmail,
    'recipients' => array_values(array_unique(array_column($all, 'email'))),
    'raw' => $raw,
    'messageId' => $messageId,
    'signatureApplied' => $signatureApplied
  ];
}

function pseImapSentMessageExists($imap, string $messageId): bool
{
  $escapedId = addcslashes($messageId, "\\\"");
  $existing = @imap_search($imap, 'HEADER Message-ID "' . $escapedId . '"', SE_UID);
  return is_array($existing) && count($existing) > 0;
}

function pseWaitForImapSentMessage($imap, string $messageId, int $attempts = 6, int $delayUs = 500000): bool
{
  $attempts = max(1, $attempts);
  for ($attempt = 0; $attempt < $attempts; $attempt++) {
    if ($attempt > 0) {
      usleep(max(0, $delayUs));
      @imap_ping($imap);
    }
    if (pseImapSentMessageExists($imap, $messageId)) {
      return true;
    }
  }
  return false;
}

function pseEnsureSentCopy(array $settings, string $rawMessage, string $messageId): string
{
  if (empty($settings['save_sent_via_imap'])) {
    return '';
  }

  $imap = null;
  try {
    $sentFolder = pseSpecialFolder($settings, 'sent');
    if ($sentFolder === '') {
      return 'Message sent, but no Sent folder was found through IMAP.';
    }

    $imap = pseOpenImap($settings, $sentFolder, false);

    // Gmail and some other SMTP servers create their own Sent copy. Wait long enough
    // for that copy to become visible before falling back to IMAP APPEND.
    if (pseWaitForImapSentMessage($imap, $messageId, 8, 500000)) {
      return '';
    }

    $mailbox = pseImapBase($settings) . $sentFolder;
    if (!@imap_append($imap, $mailbox, $rawMessage . "\r\n", '\\Seen')) {
      $error = imap_last_error();
      if (pseWaitForImapSentMessage($imap, $messageId, 4, 500000)) {
        return '';
      }
      return 'Message sent, but its Sent copy could not be stored: ' . ($error ?: 'unknown IMAP error');
    }

    // Verify the copy is really visible in Sent instead of trusting APPEND alone.
    if (!pseWaitForImapSentMessage($imap, $messageId, 6, 350000)) {
      return 'Message sent, but its Sent copy could not be verified after IMAP APPEND.';
    }
    return '';
  } catch (Throwable $e) {
    return 'Message sent, but its Sent copy could not be stored: ' . $e->getMessage();
  } finally {
    if ($imap !== null) {
      @imap_close($imap);
    }
  }
}

function pseSendMessage(array $settings, array $data): array
{
  $mail = pseBuildMail($settings, $data);
  if (pseIsGmailAccount($settings)) {
    $raw = $mail['raw'];
    $bcc = pseNormalizeRecipients($data['bcc'] ?? []);
    if (!empty($bcc)) {
      $bccHeader = 'Bcc: ' . implode(', ', array_map('pseFormatRecipient', $bcc)) . "\r\n";
      $position = strpos($raw, "\r\n\r\n");
      $raw = $position === false
        ? ($raw . "\r\n" . $bccHeader)
        : (substr($raw, 0, $position + 2) . $bccHeader . substr($raw, $position + 2));
    }
    $sentMessage = pseGoogleApi(
      $settings,
      'POST',
      'messages/send',
      [],
      ['raw' => pseBase64UrlEncode($raw)]
    );
    $gmailMessageId = (string)($sentMessage['id'] ?? '');
    $sentVerified = in_array(
      'SENT',
      array_map('strval', (array)($sentMessage['labelIds'] ?? [])),
      true
    );
    if (!$sentVerified && $gmailMessageId !== '') {
      for ($attempt = 0; $attempt < 6 && !$sentVerified; $attempt++) {
        if ($attempt > 0) {
          usleep(350000);
        }
        try {
          $verified = pseGoogleApi(
            $settings,
            'GET',
            'messages/' . rawurlencode($gmailMessageId),
            ['format' => 'minimal']
          );
          $sentVerified = in_array(
            'SENT',
            array_map('strval', (array)($verified['labelIds'] ?? [])),
            true
          );
        } catch (Throwable $verifyError) {
          // The send itself succeeded; keep retrying verification briefly.
        }
      }
    }
    if (!$sentVerified) {
      try {
        // Gmail documents that messages.insert automatically receives the SENT system
        // label when the From address belongs to the authenticated user. Use it only
        // as a last-resort storage fallback after messages.send has been verified missing
        // from Sent for several retries.
        $inserted = pseGoogleApi(
          $settings,
          'POST',
          'messages',
          ['internalDateSource' => 'dateHeader'],
          ['raw' => pseBase64UrlEncode($raw)]
        );
        $insertedId = (string)($inserted['id'] ?? '');
        $sentVerified = in_array(
          'SENT',
          array_map('strval', (array)($inserted['labelIds'] ?? [])),
          true
        );
        if (!$sentVerified && $insertedId !== '') {
          $verifiedInsert = pseGoogleApi(
            $settings,
            'GET',
            'messages/' . rawurlencode($insertedId),
            ['format' => 'minimal']
          );
          $sentVerified = in_array(
            'SENT',
            array_map('strval', (array)($verifiedInsert['labelIds'] ?? [])),
            true
          );
        }
      } catch (Throwable $insertError) {
        // Keep the original send successful and surface a Sent-copy warning below.
      }
    }
    return [
      'messageId' => $mail['messageId'],
      'sentCopyWarning' => $sentVerified
        ? ''
        : 'Message sent, but Gmail did not confirm that it was stored in Sent.',
      'signatureApplied' => $mail['signatureApplied']
    ];
  }
  $smtp = new PseSmtp($settings);
  try {
    $smtp->connect();
    $smtp->send($mail['from'], $mail['recipients'], $mail['raw']);
    $smtp->quit();
  } catch (Throwable $e) {
    $smtp->quit();
    throw $e;
  }
  return [
    'messageId' => $mail['messageId'],
    'sentCopyWarning' => pseEnsureSentCopy($settings, $mail['raw'], $mail['messageId']),
    'signatureApplied' => $mail['signatureApplied']
  ];
}


function pseForwardQuotedHtml(array $message): string
{
  $html = (string)($message['html'] ?? '');

  // Match the normal Compose -> Forward behavior: data-URI images are not copied
  // into the quoted body. They are represented by a small text placeholder.
  $html = preg_replace_callback(
    '/<img\b([^>]*)\bsrc\s*=\s*(["\'])data:image\/[^"\']+\2([^>]*)>/is',
    function (array $match): string {
      $attributes = (string)$match[1] . ' ' . (string)$match[3];
      $alt = 'image';
      if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $attributes, $altMatch)) {
        $candidate = trim(html_entity_decode((string)$altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate !== '') {
          $alt = $candidate;
        }
      }
      return '<span style="font-style:italic">[inline image omitted: ' .
        htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ']</span>';
    },
    $html
  ) ?? $html;

  // The browser-side forward helper also strips data-URI background images.
  $html = preg_replace(
    '/background-image\s*:\s*url\(\s*(["\']?)data:[^)]+\1\s*\)\s*;?/is',
    '',
    $html
  ) ?? $html;

  return $html;
}

function pseForwardSourceMessage(array $settings, string $folder, string $uid): array
{
  $sourceEnvelope = pseMailCacheReadMessageSource($settings, $folder, $uid);
  if (!empty($sourceEnvelope)) {
    $source = (array)$sourceEnvelope['data'];
  } else {
    // Read without changing \Seen and without downloading attachment bodies.
    $source = pseMessageDetails($settings, $folder, $uid, false, false, true);
    pseWriteMessageSource($settings, $folder, $uid, $source, true);
  }

  // Use the same blocked-remote-image rendering mode that a normal Forward uses.
  return pseMailCacheRenderMessage($settings, $folder, $uid, $source, false);
}

function pseForwardMailData(array $settings, string $folder, string $uid, array $data): array
{
  $message = pseForwardSourceMessage($settings, $folder, $uid);
  $subject = trim((string)($message['subject'] ?? ''));
  if ($subject === '') {
    $subject = '(No subject)';
  }
  if (!preg_match('/^fwd:/i', $subject)) {
    $subject = 'Fwd: ' . $subject;
  }

  $date = trim((string)($message['date'] ?? ''));
  $from = pseAddressText((array)($message['from'] ?? []));
  if ($from === '') {
    $from = '(Unknown sender)';
  }

  $quotedHtml = pseForwardQuotedHtml($message);
  $headerHtml = '<p><b>On ' .
    htmlspecialchars($date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ', ' .
    htmlspecialchars($from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
    ' wrote:</b></p>';
  $bodyHtml = '<div><br></div><br><div data-pse-quote="1" ' .
    'style="border-left:3px solid #ccd3dd;padding-left:12px;color:#606b7c">' .
    $headerHtml . $quotedHtml . '</div>';

  $plain = trim((string)($message['plain'] ?? ''));
  $bodyText = 'On ' . $date . ', ' . $from . " wrote:

" . $plain;

  return [
    'to' => $data['to'] ?? [],
    'cc' => $data['cc'] ?? [],
    'bcc' => $data['bcc'] ?? [],
    'subject' => $subject,
    'bodyHtml' => $bodyHtml,
    'bodyText' => $bodyText,
    // Normal manual Forward does not insert the signature in the editor, so
    // pseBuildMail applies the configured signature during send.
    'signatureHandled' => false,
    'signaturePresent' => false,
    // Match the current manual Forward behavior: original attachment bodies are
    // not copied into the new compose message automatically.
    'attachments' => []
  ];
}

function pseBulkForwardMessages(
  array $settings,
  string $folder,
  array $uids,
  array $data
): array {
  $folder = trim($folder);
  if ($folder === '') {
    throw new RuntimeException('Select an email folder first.');
  }

  $normalized = [];
  foreach ($uids as $uid) {
    $uid = trim((string)$uid);
    if ($uid !== '' && pseValidMessageUid($settings, $uid)) {
      $normalized[$uid] = $uid;
    }
  }
  $uids = array_values($normalized);
  if (empty($uids)) {
    throw new RuntimeException('Select at least one email to forward.');
  }
  if (count($uids) > 200) {
    throw new RuntimeException('Forward at most 200 selected emails at once.');
  }

  // Validate the recipient set once before sending the first message, so an
  // obvious recipient error cannot result in a partially forwarded selection.
  $to = pseNormalizeRecipients($data['to'] ?? []);
  $cc = pseNormalizeRecipients($data['cc'] ?? []);
  $bcc = pseNormalizeRecipients($data['bcc'] ?? []);
  if (empty($to) && empty($cc) && empty($bcc)) {
    throw new RuntimeException('Add at least one recipient.');
  }

  $sent = [];
  $failures = [];
  $warnings = [];

  foreach ($uids as $uid) {
    try {
      $mailData = pseForwardMailData($settings, $folder, $uid, $data);
      $result = pseSendMessage($settings, $mailData);
      $sent[] = [
        'uid' => $uid,
        'messageId' => (string)($result['messageId'] ?? '')
      ];
      $warning = trim((string)($result['sentCopyWarning'] ?? ''));
      if ($warning !== '') {
        $warnings[] = ['uid' => $uid, 'warning' => $warning];
      }
    } catch (Throwable $error) {
      $failures[] = [
        'uid' => $uid,
        'error' => $error->getMessage()
      ];
    }
  }

  return [
    'sentCount' => count($sent),
    'failedCount' => count($failures),
    'sent' => $sent,
    'failures' => $failures,
    'warnings' => $warnings
  ];
}

function pseContacts(): array
{
  $contacts = pseReadJson(PSE_CONTACTS_FILE, []);
  if (!is_array($contacts)) {
    return [];
  }
  usort($contacts, function (array $a, array $b): int {
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
  });
  return $contacts;
}

function pseCsvSafeValue(string $value): string
{
  return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

function pseExportContacts(): void
{
  $contacts = pseContacts();
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="pse_contacts_' . date('Y-m-d') . '.csv"');
  header('Cache-Control: private, no-store');
  header('X-Content-Type-Options: nosniff');
  $output = fopen('php://output', 'wb');
  if ($output === false) {
    throw new RuntimeException('Unable to create the contacts CSV.');
  }
  fwrite($output, "\xEF\xBB\xBF");
  fputcsv($output, ['Displayed name', 'email']);
  foreach ($contacts as $contact) {
    fputcsv($output, [
      pseCsvSafeValue((string)($contact['name'] ?? '')),
      pseCsvSafeValue((string)($contact['email'] ?? ''))
    ]);
  }
  fclose($output);
  exit;
}

function pseSaveContacts(array $contacts): void
{
  $unique = [];
  foreach ($contacts as $contact) {
    $email = trim((string)($contact['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      continue;
    }
    $key = strtolower($email);
    $unique[$key] = [
      'id' => (string)($contact['id'] ?? bin2hex(random_bytes(8))),
      'name' => trim((string)($contact['name'] ?? '')),
      'email' => $email
    ];
  }
  pseWriteJson(PSE_CONTACTS_FILE, array_values($unique));
}

function pseDetectDelimiter(string $line): string
{
  $candidates = [',', ';', "\t", '|'];
  $best = ',';
  $bestCount = 0;
  foreach ($candidates as $candidate) {
    $count = count(str_getcsv($line, $candidate));
    if ($count > $bestCount) {
      $best = $candidate;
      $bestCount = $count;
    }
  }
  return $best;
}

function pseImportContacts(string $tmpFile): array
{
  $handle = @fopen($tmpFile, 'rb');
  if (!$handle) {
    throw new RuntimeException('Unable to read the uploaded CSV.');
  }
  $firstLine = fgets($handle);
  if ($firstLine === false) {
    fclose($handle);
    throw new RuntimeException('The CSV file is empty.');
  }
  $delimiter = pseDetectDelimiter($firstLine);
  rewind($handle);
  $headers = fgetcsv($handle, 0, $delimiter);
  if (!is_array($headers)) {
    fclose($handle);
    throw new RuntimeException('Unable to read the CSV header.');
  }
  $headers = array_map(function ($value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value) ?? (string)$value;
    return strtolower(trim($value));
  }, $headers);
  $nameIndex = array_search('displayed name', $headers, true);
  if ($nameIndex === false) {
    $nameIndex = array_search('display name', $headers, true);
  }
  $emailIndex = array_search('email', $headers, true);
  if ($emailIndex === false) {
    foreach ($headers as $index => $header) {
      if (strpos($header, 'email') !== false || strpos($header, 'e-mail') !== false) {
        $emailIndex = $index;
        break;
      }
    }
  }
  if ($nameIndex === false || $emailIndex === false) {
    fclose($handle);
    throw new RuntimeException('CSV must contain "Displayed name" and "email" columns.');
  }

  $existing = pseContacts();
  $byEmail = [];
  foreach ($existing as $contact) {
    $byEmail[strtolower((string)$contact['email'])] = $contact;
  }
  $imported = 0;
  $updated = 0;
  $skipped = 0;
  while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $name = trim((string)($row[$nameIndex] ?? ''));
    $emailCell = trim((string)($row[$emailIndex] ?? ''));
    $emails = preg_split('/\s*[;,]\s*/', $emailCell);
    $valid = '';
    foreach ($emails as $candidate) {
      if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        $valid = $candidate;
        break;
      }
    }
    if ($valid === '') {
      $skipped++;
      continue;
    }
    $key = strtolower($valid);
    if (isset($byEmail[$key])) {
      if ($name !== '' && $byEmail[$key]['name'] !== $name) {
        $byEmail[$key]['name'] = $name;
        $updated++;
      } else {
        $skipped++;
      }
    } else {
      $byEmail[$key] = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'email' => $valid];
      $imported++;
    }
  }
  fclose($handle);
  pseSaveContacts(array_values($byEmail));
  return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'total' => count($byEmail)];
}

function pseSafeSavedId(string $id): string
{
  return preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';
}

function pseSavedList(): array
{
  pseEnsureStorage();
  $result = [];
  $files = glob(PSE_SAVED_DIR . '/*.pse') ?: [];
  foreach ($files as $file) {
    $data = pseReadJson($file);
    if (($data['format'] ?? '') !== 'PSE/1') {
      continue;
    }
    $result[] = [
      'id' => basename($file, '.pse'),
      'subject' => (string)($data['message']['subject'] ?? '(No subject)'),
      'to' => $data['message']['to'] ?? [],
      'updatedAt' => (string)($data['updatedAt'] ?? ''),
      'size' => filesize($file) ?: 0
    ];
  }
  usort($result, function (array $a, array $b): int {
    return strcmp($b['updatedAt'], $a['updatedAt']);
  });
  return $result;
}

function pseSavePse(array $data): array
{
  $id = pseSafeSavedId((string)($data['id'] ?? ''));
  if ($id === '') {
    $id = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
  }
  $message = is_array($data['message'] ?? null) ? $data['message'] : [];
  $record = [
    'format' => 'PSE/1',
    'application' => PSE_NAME,
    'version' => PSE_VERSION,
    'id' => $id,
    'createdAt' => (string)($data['createdAt'] ?? gmdate('c')),
    'updatedAt' => gmdate('c'),
    'message' => [
      'to' => pseNormalizeRecipients($message['to'] ?? []),
      'cc' => pseNormalizeRecipients($message['cc'] ?? []),
      'bcc' => pseNormalizeRecipients($message['bcc'] ?? []),
      'subject' => (string)($message['subject'] ?? ''),
      'bodyHtml' => (string)($message['bodyHtml'] ?? ''),
      'bodyText' => (string)($message['bodyText'] ?? ''),
      'signatureHandled' => !empty($message['signatureHandled']),
      'signaturePresent' => !empty($message['signaturePresent']),
      'attachments' => is_array($message['attachments'] ?? null) ? $message['attachments'] : []
    ]
  ];
  $encoded = json_encode($record);
  if ($encoded !== false && strlen($encoded) > (PSE_MAX_ATTACHMENT_BYTES + 2097152)) {
    throw new RuntimeException('The saved PSE file is too large.');
  }
  pseWriteJson(PSE_SAVED_DIR . '/' . $id . '.pse', $record);
  return ['id' => $id, 'updatedAt' => $record['updatedAt']];
}

function pseSimplePdf(array $lines): string
{
  $pageLines = array_chunk($lines, 49);
  if (empty($pageLines)) {
    $pageLines = [[]];
  }
  $pageCount = count($pageLines);
  $fontId = 3 + ($pageCount * 2);
  $objects = [];
  $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  $kids = [];
  for ($i = 0; $i < $pageCount; $i++) {
    $kids[] = (3 + $i * 2) . ' 0 R';
  }
  $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
  foreach ($pageLines as $pageIndex => $page) {
    $pageId = 3 + ($pageIndex * 2);
    $contentId = $pageId + 1;
    $stream = "BT\n/F1 10 Tf\n";
    $y = 790;
    foreach ($page as $line) {
      $text = (string)$line;
      if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
          $text = $converted;
        }
      }
      $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $text);
      $stream .= "1 0 0 1 44 $y Tm\n(" . $text . ") Tj\n";
      $y -= 15;
    }
    $stream .= "ET";
    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' .
      $fontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
  }
  $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
  ksort($objects);
  $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
  $offsets = [0];
  foreach ($objects as $id => $object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
  }
  $xref = strlen($pdf);
  $maxId = max(array_keys($objects));
  $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= $maxId; $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
  }
  $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
  return $pdf;
}

function pseWrapText(string $text, int $width = 92): array
{
  $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
  $result = [];
  foreach (explode("\n", $text) as $line) {
    if ($line === '') {
      $result[] = '';
      continue;
    }
    $wrapped = wordwrap($line, $width, "\n", true);
    foreach (explode("\n", $wrapped) as $wrappedLine) {
      $result[] = $wrappedLine;
    }
  }
  return $result;
}

function pseAddressText(array $addresses): string
{
  return implode(', ', array_map(function (array $item): string {
    return $item['name'] !== '' ? $item['name'] . ' <' . $item['email'] . '>' : $item['email'];
  }, $addresses));
}

function pseOriginalMessageRaw(array $settings, string $folder, string $uid): string
{
  if (pseIsGmailAccount($settings)) {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $uid)) {
      throw new RuntimeException('Invalid Gmail message identifier.');
    }
    $message = pseGoogleApi(
      $settings,
      'GET',
      'messages/' . rawurlencode($uid),
      ['format' => 'raw']
    );
    $raw = pseBase64UrlDecode((string)($message['raw'] ?? ''));
    if ($raw === '') {
      throw new RuntimeException('Gmail did not return the original message.');
    }
    return $raw;
  }

  $imapUid = (int)$uid;
  if ($imapUid <= 0) {
    throw new RuntimeException('Invalid IMAP message identifier.');
  }
  $imap = pseOpenImap($settings, $folder, true);
  $header = @imap_fetchheader($imap, $imapUid, FT_UID);
  $body = @imap_body($imap, $imapUid, FT_UID | FT_PEEK);
  imap_close($imap);
  if (!is_string($header) || !is_string($body)) {
    throw new RuntimeException('The original IMAP message could not be downloaded.');
  }
  return rtrim($header, "\r\n") . "\r\n\r\n" . $body;
}

function pseReadableEmailText(string $plain, string $html): string
{
  $text = trim($plain);
  if ($text === '' && trim($html) !== '') {
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
    $text = preg_replace('/<\/(?:p|div|li|tr|h[1-6]|blockquote|pre)\s*>/i', "\n", $text) ?? $text;
    $text = preg_replace('/<li\b[^>]*>/i', '- ', $text) ?? $text;
    $text = strip_tags($text);
  }
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
  $lines = preg_split('/\n/', $text) ?: [];
  $out = [];
  $blank = false;
  foreach ($lines as $line) {
    $line = rtrim(preg_replace('/[ \t]+/', ' ', (string)$line) ?? (string)$line);
    if (trim($line) === '') {
      if (!$blank && !empty($out)) {
        $out[] = '';
      }
      $blank = true;
      continue;
    }
    $out[] = $line;
    $blank = false;
  }
  return trim(implode("\n", $out));
}

function pseExportText(array $settings, string $folder, string $uid, bool $raw): void
{
  if ($raw) {
    $content = pseOriginalMessageRaw($settings, $folder, $uid);
    $safeUid = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $uid) ?: 'message';
    $filename = 'PSE-email-' . substr($safeUid, 0, 80) . '-raw.txt';
  } else {
    $message = pseExportMessageDetails($settings, $folder, $uid);
    $body = pseReadableEmailText((string)($message['plain'] ?? ''), (string)($message['html'] ?? ''));
    $content = implode("\r\n", [
      'Subject: ' . (string)$message['subject'],
      'From: ' . pseAddressText((array)$message['from']),
      'To: ' . pseAddressText((array)$message['to']),
      'Cc: ' . pseAddressText((array)$message['cc']),
      'Date: ' . (string)$message['date'],
      str_repeat('-', 72),
      '',
      str_replace("\n", "\r\n", $body)
    ]);
    $content = "\xEF\xBB\xBF" . $content;
    $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$message['subject']) ?: 'email';
    $filename = substr($name, 0, 80) . '.txt';
  }
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($content));
  header('Cache-Control: private, no-store, max-age=0');
  header('X-Content-Type-Options: nosniff');
  echo $content;
  exit;
}

function pseExportEml(array $settings, string $folder, string $uid): void
{
  $raw = pseOriginalMessageRaw($settings, $folder, $uid);

  $safeUid = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $uid) ?: 'message';
  header('Content-Type: message/rfc822');
  header('Content-Disposition: attachment; filename="PSE-email-' . substr($safeUid, 0, 80) . '.eml"');
  header('Content-Length: ' . strlen($raw));
  header('Cache-Control: private, no-store, max-age=0');
  header('X-Content-Type-Options: nosniff');
  echo $raw;
  exit;
}

function pseExportMessageDetails(array $settings, string $folder, string $uid): array
{
  $remoteEnvelope = pseMailCacheEnvelopeRead(
    pseMailCacheMessageRenderedFile($settings, $folder, $uid, true)
  );
  if (!empty($remoteEnvelope['data']) && is_array($remoteEnvelope['data'])) {
    return pseMailCachePublicMessage($remoteEnvelope['data']);
  }
  return pseCachedMessageDetails($settings, $folder, $uid, false)['message'];
}

function pseExportCachedAssetFromUrl(array $settings, string $url): array
{
  $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if ($url === '') {
    return [];
  }
  $query = parse_url($url, PHP_URL_QUERY);
  if (!is_string($query) && strpos($url, '?') === 0) {
    $query = substr($url, 1);
  }
  $params = [];
  parse_str((string)$query, $params);
  $remoteToken = (string)($params['remote_image'] ?? '');
  if ($remoteToken !== '') {
    try {
      $request = pseReadRemoteImageToken($settings, $remoteToken);
      $record = pseRemoteImageCacheRecord(
        $settings,
        $request['folder'],
        $request['uid'],
        $request['url'],
        true
      );
      if (!empty($record)) {
        $meta = (array)($record['meta'] ?? []);
        $content = @file_get_contents((string)($record['dataFile'] ?? ''));
        $mime = strtolower((string)($meta['mime'] ?? 'application/octet-stream'));
        if (is_string($content) && $content !== '' && strpos($mime, 'image/') === 0) {
          return [
            'key' => (string)($record['key'] ?? ('remote-' . hash('sha256', $request['url']))),
            'filename' => basename((string)($meta['filename'] ?? 'remote-image')),
            'mime' => $mime,
            'content' => $content
          ];
        }
      }
    } catch (Throwable $error) {
      // Keep exporting even if a remote image cannot be fetched.
    }
  }
  $token = (string)($params['cached_attachment'] ?? '');
  if ($token !== '') {
    $parts = explode('.', $token, 2);
    $key = (string)($parts[0] ?? '');
    $signature = (string)($parts[1] ?? '');
    if (
      preg_match('/^[a-f0-9]{64}$/', $key) &&
      preg_match('/^[a-f0-9]{40}$/', $signature)
    ) {
      $expected = substr(
        hash_hmac('sha256', 'attachment|' . $key, (string)$settings['storage_key']),
        0,
        40
      );
      $dataFile = PSE_CACHE_DIR . '/' . $key . '.bin';
      $meta = pseReadJson(PSE_CACHE_DIR . '/' . $key . '.json', []);
      if (hash_equals($expected, $signature) && is_file($dataFile) && !empty($meta)) {
        $content = @file_get_contents($dataFile);
        $mime = strtolower((string)($meta['mime'] ?? 'application/octet-stream'));
        if (is_string($content) && $content !== '' && strpos($mime, 'image/') === 0) {
          return [
            'key' => $key,
            'filename' => basename((string)($meta['filename'] ?? 'image')),
            'mime' => $mime,
            'content' => $content
          ];
        }
      }
    }
  }
  if (preg_match('#^https?://#i', $url)) {
    $downloaded = pseDownloadRemoteImage($url);
    if (!empty($downloaded['content']) && !empty($downloaded['mime'])) {
      $path = parse_url($url, PHP_URL_PATH);
      return [
        'key' => 'remote-' . hash('sha256', $url),
        'filename' => basename(is_string($path) ? $path : '') ?: 'remote-image',
        'mime' => strtolower((string)$downloaded['mime']),
        'content' => (string)$downloaded['content']
      ];
    }
  }
  if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,([a-z0-9+/=\r\n]+)$#i', $url, $match)) {
    $content = base64_decode(preg_replace('/\s+/', '', $match[2]), true);
    if (is_string($content) && $content !== '') {
      return [
        'key' => 'data-' . hash('sha256', $url),
        'filename' => 'embedded-image',
        'mime' => strtolower($match[1]),
        'content' => $content
      ];
    }
  }
  return [];
}

function pseExportRegisterMhtmlAsset(array &$assets, array $asset): string
{
  $key = (string)($asset['key'] ?? '');
  if ($key === '') {
    $key = hash('sha256', (string)($asset['mime'] ?? '') . "\0" . (string)($asset['content'] ?? ''));
  }
  if (!isset($assets[$key])) {
    $number = count($assets) + 1;
    $extension = [
      'image/jpeg' => 'jpg',
      'image/jpg' => 'jpg',
      'image/png' => 'png',
      'image/gif' => 'gif',
      'image/webp' => 'webp',
      'image/bmp' => 'bmp',
      'image/avif' => 'avif',
      'image/svg+xml' => 'svg'
    ][strtolower((string)($asset['mime'] ?? ''))] ?? 'bin';
    $assets[$key] = [
      'cid' => 'pse-image-' . $number . '@local',
      'location' => 'pse-image-' . $number . '.' . $extension,
      'filename' => (string)($asset['filename'] ?? ('image-' . $number . '.' . $extension)),
      'mime' => (string)($asset['mime'] ?? 'application/octet-stream'),
      'content' => (string)($asset['content'] ?? '')
    ];
  }
  return 'cid:' . $assets[$key]['cid'];
}

function pseExportRewriteImageUrls(array $settings, string $html, array &$assets): string
{
  $rewrite = function (string $url) use ($settings, &$assets): string {
    $asset = pseExportCachedAssetFromUrl($settings, $url);
    return empty($asset) ? $url : pseExportRegisterMhtmlAsset($assets, $asset);
  };

  $html = preg_replace_callback(
    '/(<(?:img|source|image)\b[^>]*?\b(?:src|href|xlink:href)\s*=\s*)(["\'])(.*?)(\2)([^>]*>)/is',
    function (array $match) use ($rewrite): string {
      $url = $match[3];
      if (!preg_match('#^(?:https?://|data:image/|\?cached_attachment=)#i', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
        return $match[0];
      }
      return $match[1] . $match[2] . htmlspecialchars($rewrite($url), ENT_QUOTES, 'UTF-8') . $match[4] . $match[5];
    },
    $html
  ) ?? $html;

  $html = preg_replace_callback(
    '/(srcset\s*=\s*)(["\'])(.*?)(\2)/is',
    function (array $match) use ($rewrite): string {
      $items = [];
      foreach (explode(',', $match[3]) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
          continue;
        }
        $parts = preg_split('/\s+/', $candidate, 2);
        $url = (string)($parts[0] ?? '');
        $descriptor = (string)($parts[1] ?? '');
        $items[] = $rewrite($url) . ($descriptor !== '' ? ' ' . $descriptor : '');
      }
      return $match[1] . $match[2] . htmlspecialchars(implode(', ', $items), ENT_QUOTES, 'UTF-8') . $match[4];
    },
    $html
  ) ?? $html;

  $html = preg_replace_callback(
    '/url\(\s*(["\']?)(.*?)\1\s*\)/is',
    function (array $match) use ($rewrite): string {
      $url = trim($match[2]);
      if (!preg_match('#^(?:https?://|data:image/|\?cached_attachment=)#i', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
        return $match[0];
      }
      return 'url("' . htmlspecialchars($rewrite($url), ENT_QUOTES, 'UTF-8') . '")';
    },
    $html
  ) ?? $html;
  return $html;
}

function pseExportWordMhtml(array $settings, array $message): string
{
  $assets = [];
  $body = pseExportRewriteImageUrls($settings, (string)$message['html'], $assets);
  $attachmentHtml = '';
  foreach ((array)($message['attachments'] ?? []) as $attachment) {
    if (
      empty($attachment['previewable']) ||
      strpos(strtolower((string)($attachment['mime'] ?? '')), 'image/') !== 0
    ) {
      continue;
    }
    $asset = pseExportCachedAssetFromUrl($settings, (string)($attachment['url'] ?? ''));
    if (empty($asset)) {
      continue;
    }
    $cid = pseExportRegisterMhtmlAsset($assets, $asset);
    $attachmentHtml .= '<div style="margin-top:20px;padding-top:14px;border-top:1px solid #ddd">' .
      '<div style="margin-bottom:8px;color:#666;font-size:9pt">' .
      htmlspecialchars((string)($attachment['filename'] ?? 'Image attachment'), ENT_QUOTES, 'UTF-8') .
      '</div><img src="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') .
      '" style="max-width:100%;height:auto"></div>';
  }
  $meta = '<table style="border-collapse:collapse;width:100%;font:10pt Arial">' .
    '<tr><td style="width:70px"><b>From:</b></td><td>' . htmlspecialchars(pseAddressText($message['from']), ENT_QUOTES, 'UTF-8') . '</td></tr>' .
    '<tr><td><b>To:</b></td><td>' . htmlspecialchars(pseAddressText($message['to']), ENT_QUOTES, 'UTF-8') . '</td></tr>' .
    '<tr><td><b>Cc:</b></td><td>' . htmlspecialchars(pseAddressText($message['cc']), ENT_QUOTES, 'UTF-8') . '</td></tr>' .
    '<tr><td><b>Date:</b></td><td>' . htmlspecialchars((string)$message['date'], ENT_QUOTES, 'UTF-8') . '</td></tr>' .
    '</table>';
  $html = '<!doctype html><html><head><meta charset="UTF-8"><title>' .
    htmlspecialchars((string)$message['subject'], ENT_QUOTES, 'UTF-8') .
    '</title><style>body{font-family:Arial,sans-serif;font-size:11pt;color:#222;overflow-wrap:anywhere}h1{font-size:18pt;border-bottom:1px solid #bbb;padding-bottom:8px}img{max-width:100%;height:auto}table{max-width:100%}a{word-break:break-all}</style></head><body><h1>' .
    htmlspecialchars((string)$message['subject'], ENT_QUOTES, 'UTF-8') .
    '</h1>' . $meta . '<hr>' . $body . $attachmentHtml . '</body></html>';

  $boundary = '----=_PSE_' . bin2hex(random_bytes(12));
  $documentLocation = 'file:///C:/PSE/email.htm';
  $mhtml = "MIME-Version: 1.0\r\n" .
    "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n" .
    "X-MimeOLE: Produced By " . PSE_NAME . "\r\n\r\n" .
    "--$boundary\r\n" .
    "Content-Type: text/html; charset=\"utf-8\"\r\n" .
    "Content-Transfer-Encoding: quoted-printable\r\n" .
    "Content-Location: $documentLocation\r\n\r\n" .
    quoted_printable_encode($html) . "\r\n";
  foreach ($assets as $asset) {
    $mhtml .= "--$boundary\r\n" .
      'Content-Type: ' . $asset['mime'] . "\r\n" .
      "Content-Transfer-Encoding: base64\r\n" .
      'Content-Location: ' . $asset['location'] . "\r\n" .
      'Content-ID: <' . $asset['cid'] . ">\r\n\r\n" .
      chunk_split(base64_encode($asset['content']), 76, "\r\n");
  }
  return $mhtml . "--$boundary--\r\n";
}

function pseExportPdf(array $settings, string $folder, string $uid): void
{
  $message = pseExportMessageDetails($settings, $folder, $uid);
  $lines = [
    PSE_NAME,
    '',
    'Subject: ' . $message['subject'],
    'From: ' . pseAddressText($message['from']),
    'To: ' . pseAddressText($message['to']),
    'Cc: ' . pseAddressText($message['cc']),
    'Date: ' . $message['date'],
    str_repeat('-', 92),
    ''
  ];
  $lines = array_merge($lines, pseWrapText($message['plain']));
  $pdf = pseSimplePdf($lines);
  $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $message['subject']) ?: 'email';
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . substr($name, 0, 80) . '.pdf"');
  header('Content-Length: ' . strlen($pdf));
  header('Cache-Control: no-store');
  echo $pdf;
  exit;
}

function pseExportWord(array $settings, string $folder, string $uid): void
{
  $message = pseExportMessageDetails($settings, $folder, $uid);
  $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $message['subject']) ?: 'email';
  $document = pseExportWordMhtml($settings, $message);
  header('Content-Type: application/msword');
  header('Content-Disposition: attachment; filename="' . substr($name, 0, 80) . '.doc"');
  header('Content-Length: ' . strlen($document));
  header('Cache-Control: no-store');
  echo $document;
  exit;
}

function pseGmailAttachmentDataFromPayload(
  array $settings,
  string $uid,
  array $payload,
  string $partNo
): array {
  $part = pseGmailFindPart($payload, $partNo);
  if ($part === null) {
    throw new RuntimeException('Attachment not found.');
  }
  $filename = pseMime((string)($part['filename'] ?? ''));
  if ($filename === '') {
    $filename = 'attachment-' . str_replace('.', '-', $partNo);
  }
  $mime = strtolower((string)($part['mimeType'] ?? 'application/octet-stream'));
  return [
    'filename' => $filename,
    'mime' => $mime,
    'content' => pseGmailPartContent($settings, $uid, $part)
  ];
}

function pseImapPartFromStructure($structure, string $partNo)
{
  if ($partNo === '0') {
    return $structure;
  }
  if (!preg_match('/^\d+(?:\.\d+)*$/', $partNo)) {
    return null;
  }
  $part = $structure;
  foreach (explode('.', $partNo) as $index) {
    if (empty($part->parts) || !isset($part->parts[(int)$index - 1])) {
      return null;
    }
    $part = $part->parts[(int)$index - 1];
  }
  return $part;
}

function pseImapAttachmentDataFromConnection(
  $imap,
  int $imapUid,
  $structure,
  string $partNo
): array {
  $part = pseImapPartFromStructure($structure, $partNo);
  if (!$part) {
    throw new RuntimeException('Attachment not found.');
  }
  $params = psePartParameters($part);
  $filename = $params['filename'] ?? ($params['name'] ?? ('attachment-' . str_replace('.', '-', $partNo)));
  $typeNames = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
  $type = $typeNames[(int)($part->type ?? 7)] ?? 'application';
  $mime = $type . '/' . strtolower((string)($part->subtype ?? 'octet-stream'));
  $raw = $partNo === '0'
    ? @imap_body($imap, $imapUid, FT_UID | FT_PEEK)
    : @imap_fetchbody($imap, $imapUid, $partNo, FT_UID | FT_PEEK);
  return [
    'filename' => $filename,
    'mime' => $mime,
    'content' => pseDecodePart(is_string($raw) ? $raw : '', (int)($part->encoding ?? 0))
  ];
}

function pseDownloadAttachment(
  array $settings,
  string $folder,
  string $uid,
  string $partNo,
  bool $download = true
): void {
  $cachedRecord = pseCachedAttachmentRecord($settings, $folder, $uid, $partNo);
  if (!empty($cachedRecord)) {
    pseOutputCachedAttachmentRecord($cachedRecord, $download);
  }
  if (pseIsGmailAccount($settings)) {
    $message = pseGoogleApi(
      $settings,
      'GET',
      'messages/' . rawurlencode($uid),
      ['format' => 'full']
    );
    $data = pseGmailAttachmentDataFromPayload(
      $settings,
      $uid,
      (array)($message['payload'] ?? []),
      $partNo
    );
  } else {
    $imapUid = (int)$uid;
    if ($imapUid <= 0) {
      throw new RuntimeException('Invalid IMAP message identifier.');
    }
    $imap = pseOpenImap($settings, $folder, true);
    $structure = @imap_fetchstructure($imap, $imapUid, FT_UID);
    if (!$structure) {
      imap_close($imap);
      throw new RuntimeException('Unable to read message structure.');
    }
    $data = pseImapAttachmentDataFromConnection($imap, $imapUid, $structure, $partNo);
    imap_close($imap);
  }
  $filename = str_replace(["\r", "\n", '"'], '', basename((string)$data['filename']));
  $mime = preg_replace('/[^a-z0-9.+-\/]/i', '', (string)$data['mime']);
  $binary = (string)$data['content'];
  pseCacheAttachment(
    $settings,
    $folder,
    $uid,
    $partNo,
    $filename ?: 'attachment.bin',
    $mime ?: 'application/octet-stream',
    $binary
  );
  $inline = !$download && pseIsReadableAttachment($mime);
  header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') .
    '; filename="' . ($filename ?: 'attachment.bin') . '"; filename*=UTF-8\'\'' . rawurlencode($filename ?: 'attachment.bin'));
  header('Content-Length: ' . strlen($binary));
  header('Cache-Control: private, max-age=31536000, immutable');
  header('X-Content-Type-Options: nosniff');
  echo $binary;
  exit;
}

function pseDownloadAllAttachments(
  array $settings,
  string $folder,
  string $uid
): void {
  if (!class_exists('ZipArchive')) {
    throw new RuntimeException('The PHP Zip extension is required for Download all.');
  }
  $content = ['plain' => '', 'html' => '', 'attachments' => [], 'inline' => []];
  $gmailPayload = null;
  $imap = null;
  $imapUid = (int)$uid;
  $imapStructure = null;
  if (pseIsGmailAccount($settings)) {
    $message = pseGoogleApi(
      $settings,
      'GET',
      'messages/' . rawurlencode($uid),
      ['format' => 'full']
    );
    $gmailPayload = (array)($message['payload'] ?? []);
    pseGmailCollectParts($settings, $uid, $gmailPayload, $content, false);
  } else {
    if ($imapUid <= 0) {
      throw new RuntimeException('Invalid IMAP message identifier.');
    }
    $imap = pseOpenImap($settings, $folder, true);
    $imapStructure = @imap_fetchstructure($imap, $imapUid, FT_UID);
    if (!$imapStructure) {
      imap_close($imap);
      throw new RuntimeException('Unable to read message structure.');
    }
    pseCollectParts($imap, $imapUid, $imapStructure, '', $content, false);
  }
  $attachments = $content['attachments'];
  if (!$attachments) {
    if ($imap !== null) {
      imap_close($imap);
    }
    throw new RuntimeException('This message has no attachments.');
  }
  $temporary = tempnam(sys_get_temp_dir(), 'pse-mail-');
  if ($temporary === false) {
    if ($imap !== null) {
      imap_close($imap);
    }
    throw new RuntimeException('Unable to create the attachment archive.');
  }
  @unlink($temporary);
  $zip = new ZipArchive();
  if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    if ($imap !== null) {
      imap_close($imap);
    }
    @unlink($temporary);
    throw new RuntimeException('Unable to create the attachment archive.');
  }
  $usedNames = [];
  try {
    foreach ($attachments as $attachment) {
      $partNo = (string)$attachment['part'];
      $data = $gmailPayload !== null
        ? pseGmailAttachmentDataFromPayload($settings, $uid, $gmailPayload, $partNo)
        : pseImapAttachmentDataFromConnection($imap, $imapUid, $imapStructure, $partNo);
      $name = basename((string)$data['filename']);
      $name = $name !== '' ? $name : ('attachment-' . str_replace('.', '-', $partNo));
      $candidate = $name;
      $counter = 2;
      while (isset($usedNames[strtolower($candidate)])) {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $extension !== '' ? substr($name, 0, -(strlen($extension) + 1)) : $name;
        $candidate = $stem . ' (' . $counter . ')' . ($extension !== '' ? '.' . $extension : '');
        $counter++;
      }
      $usedNames[strtolower($candidate)] = true;
      if (!$zip->addFromString($candidate, (string)$data['content'])) {
        throw new RuntimeException('Unable to add ' . $candidate . ' to the attachment archive.');
      }
    }
  } catch (Throwable $e) {
    $zip->close();
    if ($imap !== null) {
      imap_close($imap);
    }
    @unlink($temporary);
    throw $e;
  }
  $zip->close();
  if ($imap !== null) {
    imap_close($imap);
  }
  $archiveName = 'PSE-attachments-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $uid) . '.zip';
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="' . $archiveName . '"');
  header('Content-Length: ' . (string)filesize($temporary));
  header('Cache-Control: private, no-store');
  readfile($temporary);
  @unlink($temporary);
  exit;
}

function pseMessageIds(
  array $settings,
  string $folder,
  string $search = '',
  string $senderFilter = '',
  bool $unreadOnly = false,
  string $attachmentFilter = 'all',
  string $startDate = '',
  string $sortOrder = 'desc'
): array {
  $folder = trim($folder);
  $search = trim($search);
  $senderFilter = strtolower(trim($senderFilter));
  $attachmentFilter = pseNormalizeAttachmentFilter($attachmentFilter);
  $startDate = pseNormalizeCalendarDate($startDate);
  $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
  if ($folder === '') {
    throw new RuntimeException('Select an email folder first.');
  }
  if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
    $senderFilter = '';
  }

  if (pseIsGmailAccount($settings)) {
    $queryParts = [];
    if ($search !== '') {
      $queryParts[] = $search;
    }
    if ($senderFilter !== '') {
      $queryParts[] = 'from:' . $senderFilter;
    }
    if ($unreadOnly) {
      $queryParts[] = 'is:unread';
    }
    if ($attachmentFilter === 'with') {
      $queryParts[] = 'has:attachment';
    }
    if ($startDate !== '') {
      $timezone = pseSettingsTimezone($settings);
      $anchorStart = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
      if ($sortOrder === 'asc') {
        $queryParts[] = 'after:' . max(0, $anchorStart->getTimestamp() - 1);
      } else {
        $queryParts[] = 'before:' . $anchorStart->modify('+1 day')->getTimestamp();
      }
    }
    $gmailQuery = implode(' ', $queryParts);
    $uids = [];
    $token = '';
    do {
      $query = ['labelIds' => $folder, 'maxResults' => 500];
      if ($gmailQuery !== '') {
        $query['q'] = $gmailQuery;
      }
      if ($token !== '') {
        $query['pageToken'] = $token;
      }
      $batch = pseGoogleApi($settings, 'GET', 'messages', $query);
      foreach ((array)($batch['messages'] ?? []) as $item) {
        $id = trim((string)($item['id'] ?? ''));
        if ($id !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
          $uids[$id] = $id;
        }
      }
      $token = (string)($batch['nextPageToken'] ?? '');
    } while ($token !== '');
    return array_values($uids);
  }

  $imap = pseOpenImap($settings, $folder, true);
  try {
    $criteria = [];
    if ($unreadOnly) {
      $criteria[] = 'UNSEEN';
    }
    if ($search !== '') {
      $criteria[] = 'TEXT "' . addcslashes($search, "\\\"") . '"';
    }
    if ($senderFilter !== '') {
      $criteria[] = 'FROM "' . addcslashes($senderFilter, "\\\"") . '"';
    }
    if ($startDate !== '') {
      $timezone = pseSettingsTimezone($settings);
      $anchorStart = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
      if ($sortOrder === 'asc') {
        $criteria[] = 'SINCE ' . $anchorStart->format('d-M-Y');
      } else {
        $criteria[] = 'BEFORE ' . $anchorStart->modify('+1 day')->format('d-M-Y');
      }
    }
    $criteriaText = $criteria ? implode(' ', $criteria) : 'ALL';
    $uids = @imap_search($imap, $criteriaText, SE_UID, 'UTF-8');
    if ($uids === false) {
      $uids = @imap_search($imap, $criteriaText, SE_UID);
    }
    $uids = is_array($uids) ? $uids : [];
    if ($attachmentFilter !== 'all') {
      $cachedCounts = pseMailCacheAttachmentCounts($settings, $folder);
      $filtered = [];
      $countsChanged = false;
      foreach ($uids as $uid) {
        $uidKey = (string)$uid;
        if (array_key_exists($uidKey, $cachedCounts)) {
          $count = max(0, (int)$cachedCounts[$uidKey]);
        } else {
          $structure = @imap_fetchstructure($imap, (int)$uid, FT_UID);
          $count = pseImapAttachmentCount($structure);
          $cachedCounts[$uidKey] = $count;
          $countsChanged = true;
        }
        if ($count > 0) {
          $filtered[] = $uid;
        }
      }
      if ($countsChanged) {
        pseWriteJson(pseMailCacheAttachmentCountsFile($settings, $folder), $cachedCounts);
      }
      $uids = $filtered;
    }
    $result = [];
    foreach ($uids as $uid) {
      $uid = (int)$uid;
      if ($uid > 0) {
        $result[(string)$uid] = (string)$uid;
      }
    }
    return array_values($result);
  } finally {
    imap_close($imap);
  }
}

function psePublicSettings(array $settings): array
{
  $keys = [
    'account_type',
    'imap_host', 'imap_port', 'imap_encryption', 'imap_validate_cert', 'imap_username',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_validate_cert', 'smtp_username',
    'save_sent_via_imap',
    'from_email', 'from_name', 'reply_to', 'signature', 'google_client_id',
    'google_oauth_email', 'date_format', 'time_format',
    'smart_datetime', 'group_messages_by_day', 'email_preview_rows', 'show_attachment_pill', 'show_list_trash', 'show_list_size', 'show_calendar',
    'hide_useless_gmail_folders', 'timezone', 'density', 'theme', 'primary_color', 'accent_color', 'background_color',
    'panel_color', 'items_per_page', 'search_delay_seconds', 'block_remote_images',
    'always_load_remote_images', 'show_image_attachments_inline',
    'suggest_unknown_read_contacts', 'confirm_delete_messages', 'compose_save_drafts', 'mobile_single_pane', 'mobile_swipe_hint_seconds', 'auto_update', 'app_title',
    'account_id', 'account_name'
  ];
  $public = [];
  foreach ($keys as $key) {
    $public[$key] = $settings[$key];
  }
  $public['accounts'] = [];
  foreach ((array)$settings['accounts'] as $account) {
    if (!is_array($account)) {
      continue;
    }
    $public['accounts'][] = [
      'id' => (string)($account['id'] ?? ''),
      'name' => (string)($account['name'] ?? ''),
      'type' => (string)($account['account_type'] ?? 'imap'),
      'username' => (string)($account['imap_username'] ?? ''),
      'from_email' => (string)($account['from_email'] ?? ''),
      'google_email' => (string)($account['google_oauth_email'] ?? '')
    ];
  }
  $public['imap_password_set'] = $settings['imap_password_enc'] !== '';
  $public['smtp_password_set'] = $settings['smtp_password_enc'] !== '';
  $public['google_client_secret_set'] = $settings['google_client_secret_enc'] !== '';
  $public['google_reconnect_required'] = !empty($settings['google_oauth_reconnect_required']);
  $public['google_connected'] = $settings['google_refresh_token_enc'] !== '' &&
    !$public['google_reconnect_required'];
  $public['google_redirect_uri'] = pseCurrentBaseUrl() . '?google_oauth=callback';
  return $public;
}

function pseSaveSettings(array $settings, array $input): array
{
  $settings = pseNormalizeSettings($settings);
  $requestedAccountId = pseSafeAccountId((string)($input['account_id'] ?? ''));
  if ($requestedAccountId !== '' && isset($settings['accounts'][$requestedAccountId])) {
    $settings['active_account_id'] = $requestedAccountId;
    $settings['account_id'] = $requestedAccountId;
    $settings['account_name'] = (string)$settings['accounts'][$requestedAccountId]['name'];
    foreach (pseAccountSettingKeys() as $key) {
      $settings[$key] = $settings['accounts'][$requestedAccountId][$key];
    }
  }
  $hideUselessGmailFoldersBefore = !empty($settings['hide_useless_gmail_folders']);
  $cacheIdentityBefore = json_encode([
    (string)$settings['account_type'],
    (string)$settings['imap_host'],
    (int)$settings['imap_port'],
    (string)$settings['imap_encryption'],
    (string)$settings['imap_username'],
    (string)$settings['google_oauth_email']
  ]);
  $renderIdentityBefore = json_encode([
    (string)$settings['date_format'],
    (string)$settings['time_format'],
    !empty($settings['smart_datetime']),
    !empty($settings['group_messages_by_day']),
    (int)($settings['email_preview_rows'] ?? 0),
    !empty($settings['show_attachment_pill']),
    (string)$settings['timezone'],
    (int)$settings['items_per_page'],
    !empty($settings['block_remote_images']),
    !empty($settings['always_load_remote_images']),
    !empty($settings['show_image_attachments_inline'])
  ]);
  $textFields = [
    'account_type', 'imap_host', 'imap_encryption', 'imap_username', 'smtp_host', 'smtp_encryption',
    'smtp_username', 'from_email', 'from_name', 'reply_to', 'signature', 'date_format',
    'time_format', 'timezone', 'density', 'theme', 'google_client_id', 'primary_color', 'accent_color',
    'background_color', 'panel_color', 'app_title', 'account_name'
  ];
  foreach ($textFields as $field) {
    if (array_key_exists($field, $input)) {
      $settings[$field] = trim((string)$input[$field]);
    }
  }
  $settings['imap_port'] = max(1, min(65535, (int)($input['imap_port'] ?? $settings['imap_port'])));
  $settings['smtp_port'] = max(1, min(65535, (int)($input['smtp_port'] ?? $settings['smtp_port'])));
  $settings['items_per_page'] = max(10, min(200, (int)($input['items_per_page'] ?? $settings['items_per_page'])));
  $settings['search_delay_seconds'] = max(
    0,
    min(60, round((float)($input['search_delay_seconds'] ?? $settings['search_delay_seconds']), 2))
  );
  $settings['imap_validate_cert'] = !empty($input['imap_validate_cert']);
  $settings['smtp_validate_cert'] = !empty($input['smtp_validate_cert']);
  $settings['save_sent_via_imap'] = !empty($input['save_sent_via_imap']);
  $settings['smart_datetime'] = !empty($input['smart_datetime']);
  $settings['group_messages_by_day'] = !empty($input['group_messages_by_day']);
  $settings['email_preview_rows'] = max(0, min(5, (int)($input['email_preview_rows'] ?? $settings['email_preview_rows'])));
  $settings['show_attachment_pill'] = !empty($input['show_attachment_pill']);
  $settings['show_list_trash'] = !empty($input['show_list_trash']);
  $settings['show_list_size'] = !empty($input['show_list_size']);
  $settings['show_calendar'] = !empty($input['show_calendar']);
  if (array_key_exists('hide_useless_gmail_folders', $input)) {
    $settings['hide_useless_gmail_folders'] = !empty($input['hide_useless_gmail_folders']);
  }
  $settings['block_remote_images'] = !empty($input['block_remote_images']);
  $settings['always_load_remote_images'] = !empty($input['always_load_remote_images']);
  $settings['show_image_attachments_inline'] = !array_key_exists('show_image_attachments_inline', $input) ||
    !empty($input['show_image_attachments_inline']);
  $settings['suggest_unknown_read_contacts'] = !empty($input['suggest_unknown_read_contacts']);
  $settings['confirm_delete_messages'] = !array_key_exists('confirm_delete_messages', $input) ||
    !empty($input['confirm_delete_messages']);
  $settings['compose_save_drafts'] = !empty($input['compose_save_drafts']);
  $settings['mobile_single_pane'] = !array_key_exists('mobile_single_pane', $input) ||
    !empty($input['mobile_single_pane']);
  $settings['mobile_swipe_hint_seconds'] = max(
    0,
    min(5, round((float)($input['mobile_swipe_hint_seconds'] ?? $settings['mobile_swipe_hint_seconds']), 1))
  );
  if (array_key_exists('auto_update', $input)) {
    $settings['auto_update'] = !empty($input['auto_update']);
  }
  if (!in_array($settings['account_type'], ['imap', 'gmail'], true)) {
    $settings['account_type'] = 'imap';
  }
  if (!in_array($settings['density'], ['ultra_compact', 'compact', 'medium', 'large'], true)) {
    $settings['density'] = 'medium';
  }
  $themes = pseThemes();
  if ($settings['theme'] !== 'custom' && !isset($themes[$settings['theme']])) {
    $settings['theme'] = 'custom';
  }
  if (isset($themes[$settings['theme']])) {
    foreach (['primary_color', 'accent_color', 'background_color', 'panel_color'] as $color) {
      $settings[$color] = $themes[$settings['theme']][$color];
    }
  }
  if (!in_array($settings['imap_encryption'], ['ssl', 'tls', 'none'], true)) {
    $settings['imap_encryption'] = 'ssl';
  }
  if (!in_array($settings['smtp_encryption'], ['ssl', 'tls', 'none'], true)) {
    $settings['smtp_encryption'] = 'tls';
  }
  foreach (['primary_color', 'accent_color', 'background_color', 'panel_color'] as $color) {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$settings[$color])) {
      throw new RuntimeException('Invalid color value for ' . $color . '.');
    }
  }
  if (!empty($input['imap_password'])) {
    $settings['imap_password_enc'] = pseEncrypt((string)$input['imap_password'], (string)$settings['storage_key']);
  }
  if (!empty($input['smtp_password'])) {
    $settings['smtp_password_enc'] = pseEncrypt((string)$input['smtp_password'], (string)$settings['storage_key']);
  }
  if (!empty($input['google_client_secret'])) {
    $settings['google_client_secret_enc'] = pseEncrypt(
      (string)$input['google_client_secret'],
      (string)$settings['storage_key']
    );
  }
  if (!empty($input['new_app_password'])) {
    $newPassword = (string)$input['new_app_password'];
    if (strlen($newPassword) < 4) {
      throw new RuntimeException('Application password must be at least 4 characters.');
    }
    $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
  }
  if ($settings['account_name'] === '') {
    throw new RuntimeException('Give this email account a name.');
  }
  if (strlen($settings['account_name']) > 80) {
    throw new RuntimeException('The account name cannot exceed 80 characters.');
  }
  $settings = pseWriteSettings($settings);
  $cacheIdentityAfter = json_encode([
    (string)$settings['account_type'],
    (string)$settings['imap_host'],
    (int)$settings['imap_port'],
    (string)$settings['imap_encryption'],
    (string)$settings['imap_username'],
    (string)$settings['google_oauth_email']
  ]);
  $renderIdentityAfter = json_encode([
    (string)$settings['date_format'],
    (string)$settings['time_format'],
    !empty($settings['smart_datetime']),
    !empty($settings['group_messages_by_day']),
    (int)($settings['email_preview_rows'] ?? 0),
    !empty($settings['show_attachment_pill']),
    (string)$settings['timezone'],
    (int)$settings['items_per_page'],
    !empty($settings['block_remote_images']),
    !empty($settings['always_load_remote_images']),
    !empty($settings['show_image_attachments_inline'])
  ]);
  if ($cacheIdentityBefore !== $cacheIdentityAfter) {
    pseMailCacheClearAccountAssets($settings);
    pseMailCacheClearAccount($settings);
  } elseif ($renderIdentityBefore !== $renderIdentityAfter) {
    pseMailCacheClearRenderedData($settings);
  }
  if ($hideUselessGmailFoldersBefore !== !empty($settings['hide_useless_gmail_folders'])) {
    @unlink(pseMailCacheFoldersFile($settings));
  }
  return $settings;
}

function pseCreateAccount(array $settings, string $name, string $type = 'imap'): array
{
  $settings = pseNormalizeSettings($settings);
  $name = trim($name);
  $type = $type === 'gmail' ? 'gmail' : 'imap';
  if ($name === '') {
    throw new RuntimeException('Enter a name for the new email account.');
  }
  if (strlen($name) > 80) {
    throw new RuntimeException('The account name cannot exceed 80 characters.');
  }
  $id = pseNewAccountId();
  $account = pseAccountDefaults();
  $account['id'] = $id;
  $account['name'] = $name;
  $account['account_type'] = $type;
  if ($type === 'gmail') {
    $account['imap_host'] = 'imap.gmail.com';
    $account['imap_port'] = 993;
    $account['imap_encryption'] = 'ssl';
    $account['smtp_host'] = 'smtp.gmail.com';
    $account['smtp_port'] = 587;
    $account['smtp_encryption'] = 'tls';
  }
  $settings['accounts'][$id] = $account;
  $settings['active_account_id'] = $id;
  $settings['account_id'] = $id;
  $settings['account_name'] = $name;
  foreach (pseAccountSettingKeys() as $key) {
    $settings[$key] = $account[$key];
  }
  return pseWriteSettings($settings);
}

function pseSwitchAccount(array $settings, string $id): array
{
  $settings = pseNormalizeSettings($settings);
  $id = pseSafeAccountId($id);
  if ($id === '' || !isset($settings['accounts'][$id])) {
    throw new RuntimeException('Email account not found.');
  }
  $settings['active_account_id'] = $id;
  $settings['account_id'] = $id;
  $settings['account_name'] = (string)$settings['accounts'][$id]['name'];
  foreach (pseAccountSettingKeys() as $key) {
    $settings[$key] = $settings['accounts'][$id][$key];
  }
  return pseWriteSettings($settings);
}

function pseDeleteAccount(array $settings, string $id, string $confirmation): array
{
  $settings = pseNormalizeSettings($settings);
  if ($confirmation !== 'YES I AM SURE') {
    throw new RuntimeException('Type YES I AM SURE to delete the email account.');
  }
  $id = pseSafeAccountId($id);
  if ($id === '' || !isset($settings['accounts'][$id])) {
    throw new RuntimeException('Email account not found.');
  }
  $deletedAccountSettings = pseSettingsForAccount($settings, $id);
  if ($deletedAccountSettings !== null) {
    pseMailCacheClearAccountAssets($deletedAccountSettings);
    pseMailCacheClearAccount($deletedAccountSettings);
  }
  unset($settings['accounts'][$id]);
  if (empty($settings['accounts'])) {
    $replacementId = pseNewAccountId();
    $replacement = pseAccountDefaults();
    $replacement['id'] = $replacementId;
    $replacement['name'] = 'Account 1';
    $settings['accounts'][$replacementId] = $replacement;
  }
  if ($settings['active_account_id'] === $id || !isset($settings['accounts'][$settings['active_account_id']])) {
    $settings['active_account_id'] = (string)array_key_first($settings['accounts']);
  }
  $activeId = (string)$settings['active_account_id'];
  $settings['account_id'] = $activeId;
  $settings['account_name'] = (string)$settings['accounts'][$activeId]['name'];
  foreach (pseAccountSettingKeys() as $key) {
    $settings[$key] = $settings['accounts'][$activeId][$key];
  }
  return pseWriteSettings($settings);
}

function pseApplyClientAppearanceSettings(array $settings, $raw): array
{
  if (is_string($raw)) {
    $decoded = json_decode($raw, true);
    $raw = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($raw) || empty($raw)) {
    return $settings;
  }

  foreach (['app_title', 'date_format', 'time_format', 'timezone'] as $key) {
    if (array_key_exists($key, $raw)) {
      $settings[$key] = trim((string)$raw[$key]);
    }
  }

  $density = (string)($raw['density'] ?? $settings['density']);
  if (in_array($density, ['ultra_compact', 'compact', 'medium', 'large'], true)) {
    $settings['density'] = $density;
  }

  $themes = pseThemes();
  $theme = (string)($raw['theme'] ?? $settings['theme']);
  if ($theme === 'custom' || isset($themes[$theme])) {
    $settings['theme'] = $theme;
  }

  foreach (['primary_color', 'accent_color', 'background_color', 'panel_color'] as $key) {
    $value = (string)($raw[$key] ?? $settings[$key]);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
      $settings[$key] = $value;
    }
  }

  if (isset($themes[$settings['theme']])) {
    foreach (['primary_color', 'accent_color', 'background_color', 'panel_color'] as $key) {
      $settings[$key] = $themes[$settings['theme']][$key];
    }
  }

  $settings['items_per_page'] = max(10, min(200, (int)($raw['items_per_page'] ?? $settings['items_per_page'])));
  $settings['search_delay_seconds'] = max(
    0,
    min(60, round((float)($raw['search_delay_seconds'] ?? $settings['search_delay_seconds']), 2))
  );
  $settings['email_preview_rows'] = max(0, min(5, (int)($raw['email_preview_rows'] ?? $settings['email_preview_rows'])));
  $settings['mobile_swipe_hint_seconds'] = max(
    0,
    min(5, round((float)($raw['mobile_swipe_hint_seconds'] ?? $settings['mobile_swipe_hint_seconds']), 1))
  );

  foreach ([
    'smart_datetime', 'group_messages_by_day', 'show_attachment_pill', 'show_list_trash',
    'show_list_size', 'show_calendar', 'block_remote_images', 'always_load_remote_images',
    'show_image_attachments_inline', 'suggest_unknown_read_contacts', 'confirm_delete_messages',
    'compose_save_drafts', 'mobile_single_pane'
  ] as $key) {
    if (array_key_exists($key, $raw)) {
      $settings[$key] = !empty($raw[$key]);
    }
  }

  return $settings;
}


function pseUpdateCacheFile(): string
{
  return PSE_DATA_DIR . '/update_check.json';
}

function pseGithubApiGet(string $url): array
{
  $response = pseHttpRequest('GET', $url, [
    'Accept: application/vnd.github+json',
    'X-GitHub-Api-Version: 2022-11-28'
  ]);
  $status = (int)($response['status'] ?? 0);
  $body = (string)($response['body'] ?? '');
  if ($status === 404 || $status === 409) {
    return ['status' => $status, 'data' => [], 'body' => $body];
  }
  if ($status < 200 || $status >= 300) {
    throw new RuntimeException('GitHub update check failed with HTTP ' . $status . '.');
  }
  $data = json_decode($body, true);
  if (!is_array($data)) {
    throw new RuntimeException('GitHub returned an invalid update response.');
  }
  return ['status' => $status, 'data' => $data, 'body' => $body];
}

function pseRemoteVersionFromPhp(string $php): string
{
  if (preg_match("/const\\s+PSE_VERSION\\s*=\\s*['\\\"]([0-9]+(?:\\.[0-9]+){1,3})['\\\"]/", $php, $match)) {
    return (string)$match[1];
  }
  return '';
}

function pseValidateUpdateSourceUrl(string $url): bool
{
  $parts = parse_url($url);
  if (!is_array($parts)) {
    return false;
  }
  $host = strtolower((string)($parts['host'] ?? ''));
  $path = (string)($parts['path'] ?? '');
  return $host === 'raw.githubusercontent.com' &&
    strpos($path, '/ziobit/PSE-Email-Client/') === 0;
}

function pseCheckForUpdate(bool $force = false): array
{
  pseEnsureStorage();
  $cacheFile = pseUpdateCacheFile();
  if (!$force && is_file($cacheFile)) {
    $cached = pseReadJson($cacheFile, []);
    $checkedAt = (int)($cached['checkedAt'] ?? 0);
    if ($checkedAt > 0 && (time() - $checkedAt) < PSE_UPDATE_CACHE_SECONDS) {
      $cached['currentVersion'] = PSE_VERSION;
      $latest = (string)($cached['latestVersion'] ?? '');
      $cached['updateAvailable'] = $latest !== '' && version_compare($latest, PSE_VERSION, '>');
      return $cached;
    }
  }

  $result = [
    'checkedAt' => time(),
    'currentVersion' => PSE_VERSION,
    'latestVersion' => '',
    'updateAvailable' => false,
    'sourceUrl' => '',
    'sourceName' => '',
    'status' => 'unavailable',
    'message' => ''
  ];

  try {
    $apiUrl = 'https://api.github.com/repos/' . rawurlencode('ziobit') . '/' . rawurlencode('PSE-Email-Client') .
      '/contents?ref=' . rawurlencode(PSE_UPDATE_BRANCH);
    $github = pseGithubApiGet($apiUrl);
    $entries = $github['data'];
    if (empty($entries) || array_values($entries) !== $entries) {
      $result['status'] = 'empty';
      $result['message'] = 'The GitHub repository does not contain a published PHP version yet.';
      pseWriteJson($cacheFile, $result);
      return $result;
    }

    $candidates = [];
    foreach ($entries as $entry) {
      if (!is_array($entry) || (string)($entry['type'] ?? '') !== 'file') {
        continue;
      }
      $name = (string)($entry['name'] ?? '');
      $downloadUrl = (string)($entry['download_url'] ?? '');
      if ($downloadUrl === '' || !preg_match('/\\.php$/i', $name) || !pseValidateUpdateSourceUrl($downloadUrl)) {
        continue;
      }
      $score = 0;
      if (preg_match('/^pse(?:[-_.].*)?\\.php$/i', $name)) $score += 100;
      if (stripos($name, 'PSE') !== false) $score += 20;
      if (preg_match('/[0-9]+\\.[0-9]+(?:\\.[0-9]+)+/', $name)) $score += 10;
      $candidates[] = ['name' => $name, 'url' => $downloadUrl, 'score' => $score];
    }

    usort($candidates, function (array $a, array $b): int {
      return ((int)$b['score']) <=> ((int)$a['score']);
    });
    $candidates = array_slice($candidates, 0, 12);

    $bestVersion = '';
    $bestUrl = '';
    $bestName = '';
    foreach ($candidates as $candidate) {
      try {
        $response = pseHttpRequest('GET', (string)$candidate['url'], ['Accept: text/plain']);
        if ((int)($response['status'] ?? 0) < 200 || (int)($response['status'] ?? 0) >= 300) {
          continue;
        }
        $php = (string)($response['body'] ?? '');
        $version = pseRemoteVersionFromPhp($php);
        if ($version === '') {
          continue;
        }
        if ($bestVersion === '' || version_compare($version, $bestVersion, '>')) {
          $bestVersion = $version;
          $bestUrl = (string)$candidate['url'];
          $bestName = (string)$candidate['name'];
        }
      } catch (Throwable $ignore) {
        // Ignore an individual candidate and continue looking for a valid PSE PHP file.
      }
    }

    if ($bestVersion === '') {
      $result['status'] = 'no-version';
      $result['message'] = 'No PHP file containing PSE_VERSION was found in the repository root.';
    } else {
      $result['latestVersion'] = $bestVersion;
      $result['sourceUrl'] = $bestUrl;
      $result['sourceName'] = $bestName;
      $result['updateAvailable'] = version_compare($bestVersion, PSE_VERSION, '>');
      $result['status'] = $result['updateAvailable'] ? 'update' : 'current';
      $result['message'] = $result['updateAvailable']
        ? 'A newer version is available.'
        : 'PSE is up to date.';
    }
  } catch (Throwable $error) {
    $result['status'] = 'error';
    $result['message'] = $error->getMessage();
  }

  pseWriteJson($cacheFile, $result);
  return $result;
}

function pseLintPhpFile(string $file): array
{
  $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
  if (!function_exists('exec') || in_array('exec', $disabled, true)) {
    return ['checked' => false, 'ok' => true, 'output' => ''];
  }
  $output = [];
  $status = 0;
  @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
  return ['checked' => true, 'ok' => $status === 0, 'output' => implode("\n", $output)];
}

function pseInstallUpdate(array $update): array
{
  $latest = (string)($update['latestVersion'] ?? '');
  $sourceUrl = (string)($update['sourceUrl'] ?? '');
  if ($latest === '' || !version_compare($latest, PSE_VERSION, '>')) {
    throw new RuntimeException('There is no newer PSE version to install.');
  }
  if (!pseValidateUpdateSourceUrl($sourceUrl)) {
    throw new RuntimeException('The update source URL is not trusted.');
  }

  $response = pseHttpRequest('GET', $sourceUrl, ['Accept: text/plain']);
  $status = (int)($response['status'] ?? 0);
  $php = (string)($response['body'] ?? '');
  if ($status < 200 || $status >= 300 || strlen($php) < 50000 || strlen($php) > 25000000) {
    throw new RuntimeException('The downloaded update is missing or has an unexpected size.');
  }
  if (strpos(ltrim($php), '<?php') !== 0 || strpos($php, 'PSE_NAME') === false) {
    throw new RuntimeException('The downloaded file does not look like a PSE PHP application.');
  }
  $downloadedVersion = pseRemoteVersionFromPhp($php);
  if ($downloadedVersion !== $latest || !version_compare($downloadedVersion, PSE_VERSION, '>')) {
    throw new RuntimeException('The downloaded update version does not match the GitHub version check.');
  }

  $target = __FILE__;
  $directory = dirname($target);
  if (!is_file($target) || !is_writable($target) || !is_writable($directory)) {
    throw new RuntimeException('PSE cannot auto-update because the PHP file or its folder is not writable.');
  }

  $temp = $target . '.update.' . bin2hex(random_bytes(5));
  if (@file_put_contents($temp, $php, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write the temporary update file.');
  }
  @chmod($temp, (int)(@fileperms($target) & 0777) ?: 0644);
  $lint = pseLintPhpFile($temp);
  if (!empty($lint['checked']) && empty($lint['ok'])) {
    @unlink($temp);
    throw new RuntimeException('The downloaded PHP update failed syntax validation: ' . trim((string)$lint['output']));
  }

  $backupDir = PSE_DATA_DIR . '/updates';
  pseEnsureDirectory($backupDir);
  $backup = $backupDir . '/PSE-' . preg_replace('/[^0-9A-Za-z._-]/', '_', PSE_VERSION) . '-' . date('Ymd-His') . '.php';
  if (!@copy($target, $backup)) {
    @unlink($temp);
    throw new RuntimeException('Unable to create a backup of the current PSE PHP file.');
  }
  @chmod($backup, 0640);

  if (!@rename($temp, $target)) {
    // Windows cannot always rename over an existing file. The backup above makes this fallback recoverable.
    if (!@unlink($target) || !@rename($temp, $target)) {
      @unlink($temp);
      @copy($backup, $target);
      throw new RuntimeException('Unable to replace the current PSE PHP file with the update.');
    }
  }
  clearstatcache(true, $target);
  @unlink(pseUpdateCacheFile());

  return [
    'oldVersion' => PSE_VERSION,
    'newVersion' => $downloadedVersion,
    'backup' => basename($backup),
    'sourceName' => (string)($update['sourceName'] ?? '')
  ];
}

function pseHandleAjax(string $action, array $settings): void
{
  $publicActions = ['setup', 'login'];
  if (!in_array($action, $publicActions, true)) {
    if (!pseIsAuthenticated($settings)) {
      pseJson(['ok' => false, 'error' => 'Authentication required.', 'authRequired' => true], 401);
    }
    pseRequireCsrf($settings);
    pseCleanupExpiredAttachmentUploads();
  }

  $data = pseBody();
  if (in_array($action, [
    'messages', 'calendar_month', 'message',
    'export_pdf', 'export_eml', 'export_txt', 'export_raw_txt', 'export_word'
  ], true)) {
    $settings = pseApplyClientAppearanceSettings($settings, $data['_appearance'] ?? null);
  }
  unset($data['_appearance']);
  switch ($action) {
    case 'setup':
      if (!empty($settings['initialized'])) {
        pseJson(['ok' => false, 'error' => 'Application is already initialized.'], 409);
      }
      $password = (string)($data['password'] ?? 'password');
      if (strlen($password) < 4) {
        pseJson(['ok' => false, 'error' => 'Password must be at least 4 characters.'], 422);
      }
      $settings = pseDefaults();
      $settings['initialized'] = true;
      $settings['storage_key'] = bin2hex(random_bytes(32));
      $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
      $settings = pseWriteSettings($settings);
      pseLogin($settings);
      pseJson(['ok' => true]);
      break;

    case 'login':
      if (empty($settings['initialized'])) {
        pseJson(['ok' => false, 'error' => 'Complete first-time setup first.'], 409);
      }
      if (!password_verify((string)($data['password'] ?? ''), (string)$settings['password_hash'])) {
        usleep(350000);
        pseJson(['ok' => false, 'error' => 'Incorrect password.'], 401);
      }
      pseLogin($settings);
      $queueStatus = pseHandleActionQueue($settings);
      pseJson(['ok' => true, 'queue' => $queueStatus]);
      break;

    case 'logout':
      $queueStatus = pseHandleActionQueue($settings);
      $currentHash = hash('sha256', (string)($_COOKIE[PSE_COOKIE] ?? ''));
      $settings['auth_tokens'] = array_values(array_filter(
        (array)$settings['auth_tokens'],
        function ($hash) use ($currentHash): bool {
          return !is_string($hash) || !hash_equals($hash, $currentHash);
        }
      ));
      $settings = pseWriteSettings($settings);
      pseClearAuthCookie();
      pseJson(['ok' => true, 'queue' => $queueStatus]);
      break;

    case 'folders':
      $foldersResult = pseCachedFolders($settings, !empty($data['forceRefresh']));
      pseJson([
        'ok' => true,
        'folders' => $foldersResult['folders'],
        'changedFolders' => $foldersResult['changedFolders'] ?? [],
        'cache' => $foldersResult['cache']
      ]);
      break;

    case 'folder_status':
      $requestedFolders = is_array($data['folders'] ?? null)
        ? array_slice(array_values($data['folders']), 0, 4)
        : [];
      $folderStatus = pseCachedFolderStatus($settings, $requestedFolders);
      pseJson([
        'ok' => true,
        'folders' => $folderStatus['folders'],
        'changedFolders' => $folderStatus['changedFolders'] ?? [],
        'checkedFolders' => $folderStatus['checkedFolders'] ?? [],
        'cache' => $folderStatus['cache']
      ]);
      break;

    case 'prefetch_status':
      if (
        (string)($data['accountId'] ?? '') !==
        (string)($settings['account_id'] ?? '')
      ) {
        pseJson(['ok' => false, 'error' => 'Email account changed.'], 409);
      }
      $prefetchFolder = (string)($data['folder'] ?? 'INBOX');
      $prefetchUids = is_array($data['uids'] ?? null) ? $data['uids'] : [];
      $prefetchStatus = pseMessageSourceCacheStatus(
        $settings,
        $prefetchFolder,
        $prefetchUids
      );
      pseJson([
        'ok' => true,
        'cached' => $prefetchStatus['cached'],
        'missing' => $prefetchStatus['missing'],
        'maxMessageBytes' => PSE_PREFETCH_MAX_MESSAGE_BYTES
      ]);
      break;

    case 'prefetch_message':
      if (
        (string)($data['accountId'] ?? '') !==
        (string)($settings['account_id'] ?? '')
      ) {
        pseJson(['ok' => false, 'error' => 'Email account changed.'], 409);
      }
      $prefetchResult = psePrefetchMessageSource(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        trim((string)($data['uid'] ?? '')),
        max(0, (int)($data['expectedSize'] ?? 0))
      );
      pseJson(['ok' => true] + $prefetchResult);
      break;

    case 'messages':
      $folder = (string)($data['folder'] ?? 'INBOX');
      $page = (int)($data['page'] ?? 1);
      $search = trim((string)($data['search'] ?? ''));
      $senderFilter = strtolower(trim((string)($data['senderFilter'] ?? '')));
      if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
        $senderFilter = '';
      }
      $unreadOnly = !empty($data['unreadOnly']);
      $sortOrder = strtolower((string)($data['sortOrder'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
      $attachmentFilter = pseNormalizeAttachmentFilter((string)($data['attachmentFilter'] ?? 'all'));
      $startDate = pseNormalizeCalendarDate((string)($data['startDate'] ?? ''));
      $messagesResult = pseCachedMessageList(
        $settings,
        $folder,
        $page,
        $search,
        $senderFilter,
        $unreadOnly,
        $sortOrder,
        $attachmentFilter,
        $startDate,
        !empty($data['forceRefresh']),
        !empty($data['cacheOnly'])
      );
      pseJson([
        'ok' => true,
        'data' => $messagesResult['data'],
        'cache' => $messagesResult['cache'],
        'cacheMiss' => !empty($messagesResult['cacheMiss'])
      ]);
      break;

    case 'calendar_month':
      if (empty($settings['show_calendar'])) {
        throw new RuntimeException('Calendar view is disabled in Settings.');
      }
      $folder = (string)($data['folder'] ?? 'INBOX');
      $month = pseNormalizeCalendarMonth((string)($data['month'] ?? ''));
      $search = trim((string)($data['search'] ?? ''));
      $senderFilter = strtolower(trim((string)($data['senderFilter'] ?? '')));
      if ($senderFilter !== '' && !filter_var($senderFilter, FILTER_VALIDATE_EMAIL)) {
        $senderFilter = '';
      }
      $unreadOnly = !empty($data['unreadOnly']);
      $attachmentFilter = pseNormalizeAttachmentFilter((string)($data['attachmentFilter'] ?? 'all'));
      $calendarResult = pseCachedCalendarMonth(
        $settings,
        $folder,
        $month,
        $search,
        $senderFilter,
        $unreadOnly,
        $attachmentFilter,
        !empty($data['forceRefresh'])
      );
      pseJson([
        'ok' => true,
        'data' => $calendarResult['data'],
        'cache' => $calendarResult['cache']
      ]);
      break;

    case 'message':
      $messageResult = pseCachedMessageDetails(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        (string)($data['uid'] ?? ''),
        !empty($data['loadRemote']),
        !empty($data['forceRefresh'])
      );
      pseJson([
        'ok' => true,
        'message' => $messageResult['message'],
        'cache' => $messageResult['cache']
      ]);
      break;

    case 'set_flag':
      $flagFolder = (string)($data['folder'] ?? 'INBOX');
      $flagUid = (string)($data['uid'] ?? '');
      $flagName = (string)($data['flag'] ?? '');
      $flagEnabled = !empty($data['enabled']);
      pseSetFlag($settings, $flagFolder, $flagUid, $flagName, $flagEnabled);
      if ($flagName === '\\Seen') {
        pseMailCacheUpdateFlags($settings, $flagFolder, [$flagUid], $flagEnabled);
      }
      pseJson(['ok' => true]);
      break;

    case 'message_ids':
      $allUids = pseMessageIds(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        trim((string)($data['search'] ?? '')),
        strtolower(trim((string)($data['senderFilter'] ?? ''))),
        !empty($data['unreadOnly']),
        pseNormalizeAttachmentFilter((string)($data['attachmentFilter'] ?? 'all')),
        pseNormalizeCalendarDate((string)($data['startDate'] ?? '')),
        strtolower((string)($data['sortOrder'] ?? 'desc')) === 'asc' ? 'asc' : 'desc'
      );
      pseJson([
        'ok' => true,
        'uids' => $allUids,
        'total' => count($allUids)
      ]);
      break;

    case 'bulk_messages':
      $operation = (string)($data['operation'] ?? '');
      if ($operation === 'delete') {
        $queued = pseQueueDeleteMessages(
          $settings,
          (string)($data['folder'] ?? 'INBOX'),
          is_array($data['uids'] ?? null) ? $data['uids'] : [],
          (string)($data['confirmation'] ?? '')
        );
        pseJson([
          'ok' => true,
          'affected' => count(is_array($data['uids'] ?? null) ? $data['uids'] : []),
          'queued' => $queued['queued'],
          'pending' => $queued['pending']
        ]);
      }
      $bulkFolder = (string)($data['folder'] ?? 'INBOX');
      $bulkUids = is_array($data['uids'] ?? null) ? $data['uids'] : [];
      $affected = pseBulkMessages(
        $settings,
        $bulkFolder,
        $bulkUids,
        $operation,
        (string)($data['confirmation'] ?? '')
      );
      if ($operation === 'read' || $operation === 'unread') {
        pseMailCacheUpdateFlags($settings, $bulkFolder, $bulkUids, $operation === 'read');
      } elseif ($operation === 'restore' || $operation === 'delete_forever') {
        $destinationFolder = '';
        if ($operation === 'restore') {
          $destinationFolder = pseSpecialFolder($settings, 'inbox');
          if ($destinationFolder === '') {
            $destinationFolder = 'INBOX';
          }
        }
        pseMailCacheAfterDirectMessageOperation(
          $settings,
          $bulkFolder,
          $bulkUids,
          $operation,
          $destinationFolder
        );
      }
      pseJson(['ok' => true, 'affected' => $affected]);
      break;

    case 'move_message':
      $queued = pseQueueDeleteMessages(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        [(string)($data['uid'] ?? '')]
      );
      pseJson([
        'ok' => true,
        'queued' => $queued['queued'],
        'pending' => $queued['pending']
      ]);
      break;

    case 'queue_delete':
      $queued = pseQueueDeleteMessages(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        is_array($data['uids'] ?? null) ? $data['uids'] : [],
        (string)($data['confirmation'] ?? '')
      );
      pseJson([
        'ok' => true,
        'queued' => $queued['queued'],
        'pending' => $queued['pending']
      ]);
      break;

    case 'handle_queue':
      pseJson(['ok' => true, 'queue' => pseHandleActionQueue($settings)]);
      break;

    case 'undo_queue':
      if (empty($data['confirmed'])) {
        throw new RuntimeException('Confirmation is required to undo queued deletions.');
      }
      pseJson(['ok' => true, 'queue' => pseUndoQueuedDeleteOperations($settings)]);
      break;

    case 'cache_usage':
      $quota = null;
      if (!empty($data['includeQuota'])) {
        $quota = pseImapQuotaUsage($settings, (string)($data['folder'] ?? 'INBOX'));
      }
      pseJson([
        'ok' => true,
        'usage' => pseOfflineCacheUsage($settings),
        'quota' => $quota
      ]);
      break;

    case 'upload_attachment_init':
      pseJson(['ok' => true, 'upload' => pseInitAttachmentUpload($settings, $data)]);
      break;

    case 'upload_attachment_chunk':
      pseJson(['ok' => true, 'upload' => pseStoreAttachmentChunk($settings, $data)]);
      break;

    case 'upload_attachment_finalize':
      $manifest = pseFinalizeAttachmentUpload($settings, (string)($data['uploadId'] ?? ''));
      pseJson([
        'ok' => true,
        'upload' => [
          'uploadId' => (string)$manifest['uploadId'],
          'name' => (string)$manifest['name'],
          'type' => (string)$manifest['type'],
          'size' => (int)$manifest['size'],
          'sha256' => (string)$manifest['sha256'],
          'complete' => true
        ]
      ]);
      break;

    case 'send_message':
      $sent = pseSendMessage($settings, $data);
      pseCleanupMessageAttachmentUploads($settings, $data);
      pseMailCacheAdjustSpecialFolder($settings, 'sent', 1, 0);
      pseJson([
        'ok' => true,
        'messageId' => $sent['messageId'],
        'sentCopyWarning' => $sent['sentCopyWarning'],
        'signatureApplied' => $sent['signatureApplied']
      ]);
      break;

    case 'bulk_forward':
      $forwardResult = pseBulkForwardMessages(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        is_array($data['uids'] ?? null) ? $data['uids'] : [],
        $data
      );
      $sentCount = max(0, (int)($forwardResult['sentCount'] ?? 0));
      if ($sentCount > 0) {
        pseMailCacheAdjustSpecialFolder($settings, 'sent', $sentCount, 0);
      }
      pseJson([
        'ok' => true,
        'sentCount' => $sentCount,
        'failedCount' => max(0, (int)($forwardResult['failedCount'] ?? 0)),
        'sent' => $forwardResult['sent'] ?? [],
        'failures' => $forwardResult['failures'] ?? [],
        'warnings' => $forwardResult['warnings'] ?? []
      ]);
      break;

    case 'contacts':
      $contacts = pseContacts();
      pseJson(['ok' => true, 'contacts' => $contacts, 'total' => count($contacts)]);
      break;

    case 'set_unknown_read_contact_suggestions':
      $settings['suggest_unknown_read_contacts'] = !empty($data['enabled']);
      $settings = pseWriteSettings($settings);
      pseJson([
        'ok' => true,
        'enabled' => !empty($settings['suggest_unknown_read_contacts'])
      ]);
      break;

    case 'export_contacts':
      pseExportContacts();
      break;

    case 'save_contact':
      $contacts = pseContacts();
      $email = trim((string)($data['email'] ?? ''));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        pseJson(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
      }
      $name = trim((string)($data['name'] ?? ''));
      $requestedId = trim((string)($data['id'] ?? ''));
      $id = pseSafeSavedId($requestedId);
      if ($requestedId !== '' && $id === '') {
        pseJson(['ok' => false, 'error' => 'Invalid contact identifier.'], 422);
      }
      foreach ($contacts as $contact) {
        if (
          strcasecmp((string)$contact['email'], $email) === 0 &&
          ($id === '' || (string)$contact['id'] !== $id)
        ) {
          pseJson([
            'ok' => false,
            'error' => 'A contact with this email address already exists.'
          ], 409);
        }
      }
      $savedContact = null;
      if ($id !== '') {
        foreach ($contacts as &$contact) {
          if ((string)$contact['id'] === $id) {
            $contact['name'] = $name;
            $contact['email'] = $email;
            $savedContact = $contact;
            break;
          }
        }
        unset($contact);
        if ($savedContact === null) {
          pseJson(['ok' => false, 'error' => 'Contact not found.'], 404);
        }
      } else {
        $savedContact = [
          'id' => bin2hex(random_bytes(8)),
          'name' => $name,
          'email' => $email
        ];
        $contacts[] = $savedContact;
      }
      pseSaveContacts($contacts);
      pseJson(['ok' => true, 'contact' => $savedContact]);
      break;

    case 'save_contacts_batch':
      $incoming = is_array($data['contacts'] ?? null) ? $data['contacts'] : [];
      $contacts = pseContacts();
      $byEmail = [];
      foreach ($contacts as $contact) {
        $byEmail[strtolower((string)$contact['email'])] = $contact;
      }
      $saved = 0;
      foreach ($incoming as $contact) {
        if (!is_array($contact)) {
          continue;
        }
        $email = trim((string)($contact['email'] ?? ''));
        $name = trim((string)($contact['name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          continue;
        }
        $key = strtolower($email);
        if (isset($byEmail[$key])) {
          if ($name !== '') {
            $byEmail[$key]['name'] = $name;
          }
        } else {
          $byEmail[$key] = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email
          ];
        }
        $saved++;
      }
      pseSaveContacts(array_values($byEmail));
      pseJson(['ok' => true, 'saved' => $saved, 'total' => count($byEmail)]);
      break;

    case 'delete_contact':
      $id = pseSafeSavedId((string)($data['id'] ?? ''));
      $contacts = array_values(array_filter(pseContacts(), function (array $contact) use ($id): bool {
        return (string)$contact['id'] !== $id;
      }));
      pseSaveContacts($contacts);
      pseJson(['ok' => true]);
      break;

    case 'import_contacts':
      if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        pseJson(['ok' => false, 'error' => 'Choose a CSV file first.'], 422);
      }
      if ((int)($_FILES['csv']['size'] ?? 0) > 10485760) {
        pseJson(['ok' => false, 'error' => 'CSV file exceeds 10 MB.'], 422);
      }
      pseJson(['ok' => true, 'result' => pseImportContacts($_FILES['csv']['tmp_name'])]);
      break;

    case 'saved_list':
      pseJson(['ok' => true, 'items' => pseSavedList()]);
      break;

    case 'save_pse':
      pseJson(['ok' => true, 'saved' => pseSavePse($data)]);
      break;

    case 'load_pse':
      $id = pseSafeSavedId((string)($data['id'] ?? ''));
      $file = PSE_SAVED_DIR . '/' . $id . '.pse';
      if ($id === '' || !is_file($file)) {
        pseJson(['ok' => false, 'error' => 'Saved email not found.'], 404);
      }
      pseJson(['ok' => true, 'data' => pseReadJson($file)]);
      break;

    case 'delete_pse':
      $id = pseSafeSavedId((string)($data['id'] ?? ''));
      $file = PSE_SAVED_DIR . '/' . $id . '.pse';
      if ($id !== '' && is_file($file) && !@unlink($file)) {
        throw new RuntimeException('Unable to delete the saved email.');
      }
      pseJson(['ok' => true]);
      break;

    case 'delete_all_pse':
      if ((string)($data['confirmation'] ?? '') !== 'I AM SURE') {
        throw new RuntimeException('Type I AM SURE to delete all saved drafts.');
      }
      $deleted = 0;
      foreach (glob(PSE_SAVED_DIR . '/*.pse') ?: [] as $file) {
        if (is_file($file)) {
          if (!@unlink($file)) {
            throw new RuntimeException('Unable to delete all saved drafts.');
          }
          $deleted++;
        }
      }
      pseJson(['ok' => true, 'deleted' => $deleted]);
      break;

    case 'settings':
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'save_settings':
      $settings = pseSaveSettings($settings, $data);
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'upload_app_icon':
      if (!isset($_FILES['icon']) || !is_array($_FILES['icon'])) {
        pseJson(['ok' => false, 'error' => 'Choose an image first.'], 422);
      }
      pseJson(['ok' => true, 'icon' => pseSaveUploadedAppIcon($_FILES['icon'])]);
      break;

    case 'update_check':
      pseJson(['ok' => true, 'update' => pseCheckForUpdate(!empty($data['force']))]);
      break;

    case 'apply_update':
      $automatic = !empty($data['automatic']);
      if ($automatic && empty($settings['auto_update'])) {
        throw new RuntimeException('Automatic updates are disabled in Settings.');
      }
      if (!$automatic && empty($data['confirmed'])) {
        throw new RuntimeException('Update confirmation is required.');
      }
      $update = pseCheckForUpdate(true);
      if (empty($update['updateAvailable'])) {
        pseJson(['ok' => true, 'updated' => false, 'update' => $update]);
      }
      pseJson(['ok' => true, 'updated' => true, 'result' => pseInstallUpdate($update)]);
      break;

    case 'create_account':
      $settings = pseCreateAccount(
        $settings,
        (string)($data['name'] ?? ''),
        (string)($data['type'] ?? 'imap')
      );
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'switch_account':
      $settings = pseSwitchAccount($settings, (string)($data['account_id'] ?? ''));
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'delete_account':
      $settings = pseDeleteAccount(
        $settings,
        (string)($data['account_id'] ?? ''),
        (string)($data['confirmation'] ?? '')
      );
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'google_oauth_start':
      pseJson(['ok' => true, 'authorizationUrl' => pseGoogleAuthorizationUrl($settings)]);
      break;

    case 'google_oauth_disconnect':
      $settings = psePatchGoogleOAuthAccount(
        (string)$settings['account_id'],
        function (array $account): array {
          $account['google_refresh_token_enc'] = '';
          $account['google_access_token_enc'] = '';
          $account['google_access_token_expires_at'] = 0;
          $account['google_oauth_email'] = '';
          $account['google_oauth_reconnect_required'] = false;
          return $account;
        }
      );
      pseJson(['ok' => true, 'settings' => psePublicSettings($settings)]);
      break;

    case 'test_imap':
      if (pseIsGmailAccount($settings)) {
        $profile = pseGoogleApi($settings, 'GET', 'profile');
        pseJson([
          'ok' => true,
          'message' => 'Google OAuth2 connected for ' . (string)($profile['emailAddress'] ?? 'Gmail') . '.'
        ]);
      }
      $imap = pseOpenImap($settings, 'INBOX', true);
      $check = @imap_check($imap);
      imap_close($imap);
      pseJson(['ok' => true, 'message' => 'IMAP connected. ' . (int)($check->Nmsgs ?? 0) . ' messages in Inbox.']);
      break;

    case 'test_smtp':
      if (pseIsGmailAccount($settings)) {
        $profile = pseGoogleApi($settings, 'GET', 'profile');
        pseJson([
          'ok' => true,
          'message' => 'Gmail API sending is authorized for ' . (string)($profile['emailAddress'] ?? 'Gmail') . '.'
        ]);
      }
      $smtp = new PseSmtp($settings);
      $smtp->connect();
      $smtp->quit();
      pseJson(['ok' => true, 'message' => 'SMTP authentication successful.']);
      break;

    case 'export_pdf':
      pseExportPdf($settings, (string)($data['folder'] ?? 'INBOX'), (string)($data['uid'] ?? ''));
      break;

    case 'export_eml':
      pseExportEml($settings, (string)($data['folder'] ?? 'INBOX'), (string)($data['uid'] ?? ''));
      break;

    case 'export_txt':
      pseExportText($settings, (string)($data['folder'] ?? 'INBOX'), (string)($data['uid'] ?? ''), false);
      break;

    case 'export_raw_txt':
      pseExportText($settings, (string)($data['folder'] ?? 'INBOX'), (string)($data['uid'] ?? ''), true);
      break;

    case 'export_word':
      pseExportWord($settings, (string)($data['folder'] ?? 'INBOX'), (string)($data['uid'] ?? ''));
      break;

    case 'attachment':
      pseDownloadAttachment(
        $settings,
        (string)($data['folder'] ?? 'INBOX'),
        (string)($data['uid'] ?? ''),
        (string)($data['part'] ?? '')
      );
      break;

    default:
      pseJson(['ok' => false, 'error' => 'Unknown action.'], 404);
  }
}

function psePwaScriptPath(): string
{
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  if ($script === '') {
    $script = '/' . basename(__FILE__);
  }
  if ($script[0] !== '/') {
    $script = '/' . ltrim($script, '/');
  }
  return $script;
}

function psePwaScopePath(): string
{
  $directory = str_replace('\\', '/', dirname(psePwaScriptPath()));
  if ($directory === '/' || $directory === '.') {
    return '/';
  }
  return rtrim($directory, '/') . '/';
}

function psePwaOrigin(): string
{
  $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
  $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || $forwardedProto === 'https';
  $scheme = $https ? 'https' : 'http';
  $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
  if (!preg_match('/^[a-z0-9.\\-:\[\]]+$/i', $host)) {
    $host = 'localhost';
  }
  return $scheme . '://' . $host;
}

function psePwaLogoFile(): string
{
  return __DIR__ . '/icon.png';
}

function pseDefaultIconPng(): string
{
  if (
    function_exists('imagecreatetruecolor') &&
    function_exists('imagecolorallocate') &&
    function_exists('imagefilledrectangle') &&
    function_exists('imageline') &&
    function_exists('imagepng')
  ) {
    $image = imagecreatetruecolor(128, 128);
    if ($image !== false) {
      $blue = imagecolorallocate($image, 23, 105, 170);
      $white = imagecolorallocate($image, 255, 255, 255);
      imagefill($image, 0, 0, $blue);
      imagefilledrectangle($image, 23, 35, 104, 93, $white);
      if (function_exists('imagesetthickness')) {
        imagesetthickness($image, 6);
      }
      imageline($image, 28, 40, 64, 68, $blue);
      imageline($image, 64, 68, 99, 40, $blue);
      if (function_exists('imagesetthickness')) {
        imagesetthickness($image, 4);
      }
      imageline($image, 29, 89, 53, 67, $blue);
      imageline($image, 99, 89, 75, 67, $blue);
      ob_start();
      imagepng($image);
      $png = (string)ob_get_clean();
      imagedestroy($image);
      if ($png !== '') {
        return $png;
      }
    }
  }

  // Built-in 128x128 PNG fallback so icon creation does not depend on GD.
  $png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAC0ElEQVR42u3d2VEbQRRGYXPLOZjECMIERBAmMbKwX1CVHxgtSDN9l++8gaCG7v/0csWM+unX7z9/f2AsoQsIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAHLzc+XFP95eJPDJ8+v7kus+rfiIGMHnESGEn4uj+yeEP1uCWNWoVWtelan/KAliZfgkON8fR0gQq01/fn0fK8JXbW+3Cfzf4nONmybBtX2x9ywQGRo8TYJMfbFsEzhVgmvbd9Qm8NB3Ak+NutQJp9c7lY3Zgl+2CZw4G2QNf5kAkyTIHP5SAW6VoJoIt/zNK5e65f8O/nh7aTcb3BL86n1OmvsBukhQYdSnFKCDBNXCP7wM7FoqVgw+5QxQcTaoHH5qASpIUD389AJkLRWrlHgtBMhWKlYq8doIkGVJ6DLqywqwUoKO4acsA7OVil2DLzsDHDkbdA+/vAB7SjAh/BYCPLpU7FTijRHgUaVitxJvlAD3LgmTRn1rAb4jwdTwy5aBe5SK04JvOwM8+vGq7s80RsfwHyXBhGcao+vIv1eCSyO/iwTRMfytwK8p37Z+5qvvdZAgpoR/7vXT17f+XgcJdv+MoD130N8Jv+J195Qsqo76VeGfmwkqzgZRMfwMtfrW9apJENXDX/m+/Na1K0kQ1cPPQGUJQvizJQjhz5YghD9bghD+bAlC+LMlCOHPliCEP1uCyBJ8x/AvSZBBhMg46juFf6k9qyWIbOF3uuX62ratlCCyhT+BTBKE8GdLEEeHLfzbJNhbiuWHRk0/R2h1fxwiwNYdug6ROt8fRywJh54bKPD7y+PSS4BDovL1U3RunPCTLQGWhHwDY6kAWI/j4wkAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAACAACAACgAAgAAgAAoAAIAAIAAKAAMjPP7uDq0Vvnpu5AAAAAElFTkSuQmCC',
    true
  );
  return is_string($png) ? $png : '';
}

function pseWriteAppIconPng(string $png): int
{
  if ($png === '' || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    throw new RuntimeException('The generated application icon is not a valid PNG.');
  }
  $target = psePwaLogoFile();
  $directory = dirname($target);
  if (!is_dir($directory) || !is_writable($directory)) {
    throw new RuntimeException('The application folder is not writable, so icon.png cannot be created or replaced.');
  }
  $temp = $target . '.tmp.' . bin2hex(random_bytes(5));
  if (@file_put_contents($temp, $png, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write the new icon.png file.');
  }
  @chmod($temp, 0644);
  if (!@rename($temp, $target)) {
    if (is_file($target)) {
      @unlink($target);
    }
    if (!@rename($temp, $target)) {
      @unlink($temp);
      throw new RuntimeException('Unable to replace icon.png. Check application-folder permissions.');
    }
  }
  clearstatcache(true, $target);
  $modified = @filemtime($target);
  return $modified === false ? time() : (int)$modified;
}

function pseEnsureDefaultIcon(): bool
{
  $target = psePwaLogoFile();
  if (is_file($target) && (int)@filesize($target) > 0) {
    return true;
  }
  try {
    pseWriteAppIconPng(pseDefaultIconPng());
    return is_file($target);
  } catch (Throwable $error) {
    // Do not prevent the mail client from loading merely because its folder is read-only.
    error_log('PSE: unable to create default icon.png: ' . $error->getMessage());
    return false;
  }
}

function pseSaveUploadedAppIcon(array $file): array
{
  $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($error !== UPLOAD_ERR_OK) {
    throw new RuntimeException($error === UPLOAD_ERR_NO_FILE
      ? 'Choose an image first.'
      : 'The application icon upload failed with code ' . $error . '.');
  }
  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    throw new RuntimeException('The uploaded application icon is unavailable.');
  }
  $size = (int)($file['size'] ?? 0);
  if ($size <= 0 || $size > 5242880) {
    throw new RuntimeException('The application icon must be between 1 byte and 5 MB.');
  }
  $info = @getimagesize($tmp);
  if (!is_array($info) || empty($info[0]) || empty($info[1])) {
    throw new RuntimeException('The uploaded file is not a valid image.');
  }
  $width = (int)$info[0];
  $height = (int)$info[1];
  if ($width > 8192 || $height > 8192) {
    throw new RuntimeException('The application icon is too large. Maximum dimensions are 8192x8192 pixels.');
  }
  $mime = strtolower((string)($info['mime'] ?? ''));
  $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
  if (!in_array($mime, $allowed, true)) {
    throw new RuntimeException('Use a PNG, JPEG, WebP, or GIF image.');
  }

  $raw = @file_get_contents($tmp);
  if (!is_string($raw) || $raw === '') {
    throw new RuntimeException('Unable to read the uploaded application icon.');
  }

  $png = '';
  $finalSize = min(256, max(1, min($width, $height)));
  if (
    function_exists('imagecreatefromstring') &&
    function_exists('imagecreatetruecolor') &&
    function_exists('imagecopyresampled') &&
    function_exists('imagepng')
  ) {
    $source = @imagecreatefromstring($raw);
    if ($source === false) {
      throw new RuntimeException('PHP could not decode the uploaded image.');
    }
    $sourceSide = min($width, $height);
    $sourceX = (int)floor(($width - $sourceSide) / 2);
    $sourceY = (int)floor(($height - $sourceSide) / 2);
    $target = imagecreatetruecolor($finalSize, $finalSize);
    if ($target === false) {
      imagedestroy($source);
      throw new RuntimeException('PHP could not prepare the resized application icon.');
    }
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $finalSize, $finalSize, $transparent);
    imagecopyresampled(
      $target,
      $source,
      0,
      0,
      $sourceX,
      $sourceY,
      $finalSize,
      $finalSize,
      $sourceSide,
      $sourceSide
    );
    ob_start();
    imagepng($target);
    $png = (string)ob_get_clean();
    imagedestroy($target);
    imagedestroy($source);
  } elseif (
    $mime === 'image/png' &&
    substr($raw, 0, 8) === "\x89PNG\r\n\x1a\n" &&
    $width === $height &&
    $width <= 256
  ) {
    $png = $raw;
    $finalSize = $width;
  } else {
    throw new RuntimeException('The browser must upload a square PNG no larger than 256x256 when PHP GD is unavailable.');
  }

  $version = pseWriteAppIconPng($png);
  return [
    'version' => $version,
    'width' => $finalSize,
    'height' => $finalSize,
    'filename' => 'icon.png'
  ];
}

function pseServePwaIcon(int $size): void
{
  $sourceFile = psePwaLogoFile();
  if (!is_file($sourceFile)) {
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, must-revalidate');
    echo pseDefaultIconPng();
    exit;
  }

  $size = $size >= 384 ? 512 : 192;
  header('Content-Type: image/png');
  header('Cache-Control: public, max-age=86400');

  if (
    function_exists('imagecreatefrompng') &&
    function_exists('imagecreatetruecolor') &&
    function_exists('imagecopyresampled') &&
    function_exists('imagepng')
  ) {
    $source = @imagecreatefrompng($sourceFile);
    if ($source !== false) {
      $sourceWidth = imagesx($source);
      $sourceHeight = imagesy($source);
      if ($sourceWidth > 0 && $sourceHeight > 0) {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        $scale = min($size / $sourceWidth, $size / $sourceHeight);
        $targetWidth = max(1, (int)round($sourceWidth * $scale));
        $targetHeight = max(1, (int)round($sourceHeight * $scale));
        $targetX = (int)floor(($size - $targetWidth) / 2);
        $targetY = (int)floor(($size - $targetHeight) / 2);
        imagecopyresampled(
          $canvas,
          $source,
          $targetX,
          $targetY,
          0,
          0,
          $targetWidth,
          $targetHeight,
          $sourceWidth,
          $sourceHeight
        );
        imagepng($canvas);
        imagedestroy($canvas);
        imagedestroy($source);
        exit;
      }
      imagedestroy($source);
    }
  }

  // Graceful fallback when GD is unavailable: serve the existing logo unchanged.
  readfile($sourceFile);
  exit;
}

function pseServePwaManifest(): void
{
  $settings = array_replace(pseDefaults(), pseReadJson(PSE_SETTINGS_FILE));
  $name = trim((string)($settings['app_title'] ?? 'PSE Email'));
  if ($name === '') {
    $name = 'PSE Email';
  }
  $scriptPath = psePwaScriptPath();
  $scopePath = psePwaScopePath();
  $origin = psePwaOrigin();
  $iconVersion = is_file(psePwaLogoFile()) ? (int)(@filemtime(psePwaLogoFile()) ?: time()) : time();
  $manifestPath = $scriptPath . '?pwa=manifest';
  $manifest = [
    'id' => $scriptPath,
    'name' => $name,
    'short_name' => 'PSE',
    'description' => 'PSE Email — private IMAP, SMTP and Gmail email client.',
    'start_url' => $scriptPath,
    'scope' => $scopePath,
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'background_color' => (string)($settings['background_color'] ?? '#f3f5f8'),
    'theme_color' => (string)($settings['primary_color'] ?? '#1769aa'),
    'categories' => ['productivity', 'utilities'],
    'icons' => [
      [
        'src' => $scriptPath . '?pwa=icon&size=192&v=' . $iconVersion,
        'sizes' => '192x192',
        'type' => 'image/png',
        'purpose' => 'any'
      ],
      [
        'src' => $scriptPath . '?pwa=icon&size=512&v=' . $iconVersion,
        'sizes' => '512x512',
        'type' => 'image/png',
        'purpose' => 'any maskable'
      ]
    ],
    'related_applications' => [[
      'platform' => 'webapp',
      'url' => $origin . $manifestPath,
      'id' => $origin . $scriptPath
    ]],
    'prefer_related_applications' => false
  ];

  header('Content-Type: application/manifest+json; charset=UTF-8');
  header('Cache-Control: no-cache, must-revalidate');
  echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function pseServePwaServiceWorker(): void
{
  header('Content-Type: application/javascript; charset=UTF-8');
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Service-Worker-Allowed: ' . psePwaScopePath());
  echo "'use strict';\n";
  echo 'const PSE_PWA_VERSION = ' . json_encode(PSE_VERSION) . ";\n";
  echo "self.addEventListener('install', event => { self.skipWaiting(); });\n";
  echo "self.addEventListener('activate', event => { event.waitUntil(self.clients.claim()); });\n";
  echo "self.addEventListener('message', event => { if (event.data === 'PSE_SKIP_WAITING') self.skipWaiting(); });\n";
  echo "// Mailbox and authenticated responses intentionally remain network-managed and are not cached here.\n";
  exit;
}

function pseServePwaEndpoint(string $mode): void
{
  if ($mode === 'manifest') {
    pseServePwaManifest();
  }
  if ($mode === 'sw') {
    pseServePwaServiceWorker();
  }
  if ($mode === 'icon') {
    pseServePwaIcon((int)($_GET['size'] ?? 192));
  }
  http_response_code(404);
  header('Content-Type: text/plain; charset=UTF-8');
  exit('Unknown PWA resource.');
}

pseEnsureDefaultIcon();

$psePwaMode = (string)($_GET['pwa'] ?? '');
if ($psePwaMode !== '') {
  pseServePwaEndpoint($psePwaMode);
}

try {
  pseEnsureStorage();
  $pseSettings = pseSettings();
  if (!empty($pseSettings['timezone']) && in_array($pseSettings['timezone'], timezone_identifiers_list(), true)) {
    date_default_timezone_set((string)$pseSettings['timezone']);
  }
  if ((string)($_GET['google_oauth'] ?? '') === 'callback') {
    pseHandleGoogleOAuthCallback($pseSettings);
  }
  $pseCachedAttachment = (string)($_GET['cached_attachment'] ?? '');
  if ($pseCachedAttachment !== '') {
    pseServeCachedAttachment($pseSettings, $pseCachedAttachment, !empty($_GET['download']));
  }
  $pseRemoteImage = (string)($_GET['remote_image'] ?? '');
  if ($pseRemoteImage !== '') {
    try {
      pseServeRemoteImage($pseSettings, $pseRemoteImage);
    } catch (Throwable $e) {
      http_response_code(404);
      header('Cache-Control: private, no-store, max-age=0');
      exit('Unable to load remote image: ' . $e->getMessage());
    }
  }
  $pseAttachmentDownload = (string)($_GET['attachment_download'] ?? '');
  if ($pseAttachmentDownload !== '') {
    try {
      $attachmentRequest = pseReadAttachmentToken(
        $pseSettings,
        'attachment',
        $pseAttachmentDownload
      );
      pseDownloadAttachment(
        $pseSettings,
        $attachmentRequest['folder'],
        $attachmentRequest['uid'],
        $attachmentRequest['part'],
        !empty($_GET['download'])
      );
    } catch (Throwable $e) {
      http_response_code(404);
      exit('Unable to open attachment: ' . $e->getMessage());
    }
  }
  $pseAttachmentDownloadAll = (string)($_GET['attachment_download_all'] ?? '');
  if ($pseAttachmentDownloadAll !== '') {
    try {
      $attachmentRequest = pseReadAttachmentToken(
        $pseSettings,
        'attachment-all',
        $pseAttachmentDownloadAll
      );
      pseDownloadAllAttachments(
        $pseSettings,
        $attachmentRequest['folder'],
        $attachmentRequest['uid']
      );
    } catch (Throwable $e) {
      http_response_code(500);
      exit('Unable to download attachments: ' . $e->getMessage());
    }
  }
  $pseAction = (string)($_GET['ajax'] ?? '');
  if ($pseAction !== '') {
    pseHandleAjax($pseAction, $pseSettings);
  }
} catch (PseGoogleReconnectRequiredException $e) {
  if (!empty($_GET['ajax'])) {
    pseJson([
      'ok' => false,
      'error' => $e->getMessage(),
      'googleReconnectRequired' => true
    ], 409);
  }
  $pseBootError = $e->getMessage();
  $pseSettings = pseDefaults();
} catch (Throwable $e) {
  if (!empty($_GET['ajax'])) {
    pseJson(['ok' => false, 'error' => $e->getMessage()], 500);
  }
  $pseBootError = $e->getMessage();
  $pseSettings = pseDefaults();
}

$pseAuthenticated = empty($pseBootError) && pseIsAuthenticated($pseSettings);
$pseQueueStatus = [
  'processed' => 0,
  'failed' => 0,
  'pending' => empty($pseBootError) ? pseActionQueueCount() : 0
];
if ($pseAuthenticated) {
  $pseQueueStatus = pseHandleActionQueue($pseSettings);
}
if ($pseAuthenticated && !headers_sent() && !empty($_COOKIE[PSE_COOKIE])) {
  // Refresh older Strict cookies as Lax before a cross-site Google OAuth return.
  pseSetAuthCookie((string)$_COOKIE[PSE_COOKIE]);
}
$pseInitialized = !empty($pseSettings['initialized']);
$pseCsrfToken = $pseAuthenticated ? pseCsrf($pseSettings) : '';
$pseUiSettings = psePublicSettings($pseSettings);
$pseUiSettings['queue_pending'] = (int)$pseQueueStatus['pending'];
$pseDarkTheme = pseThemeIsDark($pseSettings);
$pseIconVersion = is_file(psePwaLogoFile()) ? (int)(@filemtime(psePwaLogoFile()) ?: time()) : time();
$pseIconHref = 'icon.png?v=' . rawurlencode((string)$pseIconVersion);
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="<?= htmlspecialchars((string)$pseSettings['primary_color'], ENT_QUOTES, 'UTF-8') ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)($pseSettings['app_title'] ?? 'PSE Email'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="manifest" href="?pwa=manifest">
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars((string)($pseSettings['app_title'] ?? 'PSE Email'), ENT_QUOTES, 'UTF-8') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --pse-primary: <?= htmlspecialchars((string)$pseSettings['primary_color'], ENT_QUOTES, 'UTF-8') ?>;
      --pse-accent: <?= htmlspecialchars((string)$pseSettings['accent_color'], ENT_QUOTES, 'UTF-8') ?>;
      --pse-bg: <?= htmlspecialchars((string)$pseSettings['background_color'], ENT_QUOTES, 'UTF-8') ?>;
      --pse-panel: <?= htmlspecialchars((string)$pseSettings['panel_color'], ENT_QUOTES, 'UTF-8') ?>;
      --pse-text: <?= $pseDarkTheme ? '#e7edf5' : '#253044' ?>;
      --pse-muted: <?= $pseDarkTheme ? '#aab5c4' : '#687385' ?>;
      --pse-border: <?= $pseDarkTheme ? '#3a4658' : '#dfe4ec' ?>;
      --pse-hover: <?= $pseDarkTheme ? '#26364b' : '#f4f7fb' ?>;
      --pse-input: <?= $pseDarkTheme ? '#1d293b' : '#ffffff' ?>;
      --pse-row-pad: <?= $pseSettings['density'] === 'ultra_compact' ? '3px' : ($pseSettings['density'] === 'compact' ? '7px' : ($pseSettings['density'] === 'large' ? '16px' : '11px')) ?>;
      --pse-font-size: <?= $pseSettings['density'] === 'compact' ? '13px' : ($pseSettings['density'] === 'large' ? '16px' : '14px') ?>;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0;
      overflow: hidden;
      background: var(--pse-bg);
      color: var(--pse-text);
      font-size: var(--pse-font-size);
    }
    .btn-primary { --bs-btn-bg: var(--pse-primary); --bs-btn-border-color: var(--pse-primary); }
    .text-pse { color: var(--pse-primary) !important; }
    .pse-auth-page {
      min-height: 100vh;
      overflow: auto;
      display: grid;
      place-items: center;
      padding: 24px;
      background:
        radial-gradient(circle at 15% 20%, color-mix(in srgb, var(--pse-accent) 16%, transparent), transparent 38%),
        radial-gradient(circle at 85% 80%, color-mix(in srgb, var(--pse-primary) 18%, transparent), transparent 40%),
        var(--pse-bg);
    }
    .pse-auth-card {
      width: min(440px, 100%);
      border: 0;
      border-radius: 20px;
      box-shadow: 0 18px 60px rgba(23, 45, 78, .14);
    }
    .pse-logo {
      width: 58px;
      height: 58px;
      display: inline-grid;
      place-items: center;
      border-radius: 16px;
      color: #fff;
      background: linear-gradient(135deg, var(--pse-primary), var(--pse-accent));
      box-shadow: 0 8px 22px color-mix(in srgb, var(--pse-primary) 35%, transparent);
    }
    .pse-shell {
      height: 100vh;
      display: grid;
      grid-template-rows: auto minmax(0, 1fr) auto;
      background: var(--pse-bg);
    }
    .pse-header {
      position: sticky;
      top: 0;
      z-index: 1020;
      min-height: 62px;
      color: #fff;
      background: linear-gradient(105deg, var(--pse-primary), color-mix(in srgb, var(--pse-primary) 72%, #111f36));
      box-shadow: 0 2px 12px rgba(17, 32, 56, .22);
    }
    .pse-brand {
      min-width: 230px;
      max-width: 350px;
      display: flex;
      align-items: center;
      min-height: 34px;
      font-weight: 700;
      letter-spacing: .2px;
      overflow: visible;
      white-space: nowrap;
    }
    .pse-brand-avatar {
      width: 32px;
      height: 32px;
      flex: 0 0 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 8px;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,.5);
      border-radius: 50%;
      background: rgba(255,255,255,.16);
      box-shadow: 0 1px 5px rgba(0,0,0,.18);
    }
    .pse-brand-avatar img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
    }
    .pse-brand-title {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-account-switcher {
      position: relative;
      display: inline-flex;
      min-width: 0;
      margin-left: 7px;
      vertical-align: middle;
    }
    .pse-account-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      max-width: 150px;
      min-width: 0;
      padding: 2px 7px;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,.38);
      border-radius: 10px;
      color: inherit;
      font: inherit;
      font-size: 11px;
      font-weight: 500;
      line-height: 1.25;
      text-align: left;
      vertical-align: middle;
      background: rgba(255,255,255,.12);
      appearance: none;
      -webkit-appearance: none;
    }
    .pse-account-badge-name {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-account-badge.switchable {
      cursor: pointer;
      transition: background .14s ease, border-color .14s ease, box-shadow .14s ease;
    }
    .pse-account-badge.switchable:hover,
    .pse-account-badge.switchable[aria-expanded="true"] {
      border-color: rgba(255,255,255,.68);
      background: rgba(255,255,255,.2);
      box-shadow: 0 2px 8px rgba(0,0,0,.12);
    }
    .pse-account-badge.switchable:focus-visible {
      outline: 2px solid rgba(255,255,255,.82);
      outline-offset: 2px;
    }
    .pse-account-badge-chevron {
      flex: 0 0 auto;
      font-size: 8px;
      opacity: .82;
      transition: transform .14s ease;
    }
    .pse-account-badge[aria-expanded="true"] .pse-account-badge-chevron { transform: rotate(180deg); }
    .pse-account-menu {
      position: absolute;
      z-index: 1085;
      top: calc(100% + 7px);
      left: 0;
      width: max-content;
      min-width: 210px;
      max-width: min(330px, calc(100vw - 28px));
      padding: 5px;
      border: 1px solid var(--pse-border);
      border-radius: 12px;
      background: var(--pse-panel);
      box-shadow: 0 12px 30px rgba(0,0,0,.2);
      color: var(--pse-text);
    }
    .pse-account-menu[hidden] { display: none !important; }
    .pse-account-menu-item {
      width: 100%;
      display: grid;
      grid-template-columns: 26px minmax(0, 1fr) 18px;
      align-items: center;
      gap: 7px;
      padding: 7px 8px;
      border: 0;
      border-radius: 8px;
      color: inherit;
      background: transparent;
      text-align: left;
      cursor: pointer;
    }
    .pse-account-menu-item:hover,
    .pse-account-menu-item:focus-visible { background: color-mix(in srgb, var(--pse-primary) 9%, var(--pse-panel)); outline: none; }
    .pse-account-menu-item.active { background: color-mix(in srgb, var(--pse-primary) 13%, var(--pse-panel)); }
    .pse-account-menu-icon {
      width: 26px;
      height: 26px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 7px;
      color: var(--pse-primary);
      background: color-mix(in srgb, var(--pse-primary) 9%, transparent);
    }
    .pse-account-menu-copy { min-width: 0; }
    .pse-account-menu-name {
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: 12px;
      font-weight: 650;
    }
    .pse-account-menu-detail {
      display: block;
      margin-top: 1px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: var(--pse-muted);
      font-size: 10px;
    }
    .pse-account-menu-check { color: var(--pse-primary); font-size: 11px; text-align: center; }
    .pse-footer-version {
      border: 0;
      padding: 0;
      color: inherit;
      background: transparent;
      font: inherit;
      opacity: .76;
      cursor: pointer;
      transition: opacity .14s ease, text-decoration-color .14s ease;
    }
    .pse-footer-version:hover,
    .pse-footer-version:focus-visible {
      opacity: 1;
      text-decoration: underline;
      text-underline-offset: 2px;
      outline: none;
    }
    .pse-search { max-width: 720px; }
    #toggleViewMode { min-width: 32px; }
    #viewModeIcon { display: inline-block !important; min-width: 1em; }
    .pse-search .input-group-text,
    .pse-search .form-control {
      border: 0;
      background: rgba(255,255,255,.96);
    }
    .pse-workspace {
      min-height: 0;
      display: grid;
      grid-template-columns: minmax(10px, 240px) 5px minmax(10px, 430px) 5px minmax(10px, 1fr);
      grid-template-rows: minmax(0, 1fr);
      grid-template-areas: "folders resize1 messages resize2 preview";
      overflow: hidden;
    }
    #foldersPanel { grid-area: folders; }
    #resizer1 { grid-area: resize1; }
    #messagesPanel { grid-area: messages; }
    #resizer2 { grid-area: resize2; }
    #previewPanel { grid-area: preview; }
    body.pse-view-stacked .pse-workspace {
      grid-template-columns: minmax(10px, 240px) 5px minmax(10px, 1fr);
      grid-template-rows: minmax(10px, 42%) 5px minmax(10px, 1fr);
      grid-template-areas:
        "folders resize1 messages"
        "folders resize1 resize2"
        "folders resize1 preview";
    }
    body.pse-view-stacked #messagesPanel {
      border-right: 0;
      border-bottom: 1px solid var(--pse-border);
    }
    body.pse-view-stacked #previewPanel { border-right: 0; }
    body.pse-view-stacked #resizer2 { cursor: row-resize; }
    .pse-panel {
      min-width: 0;
      min-height: 0;
      overflow: hidden;
      background: var(--pse-panel);
      border-right: 1px solid var(--pse-border);
    }
    .pse-panel-inner {
      height: 100%;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
    .pse-panel-toolbar {
      flex: 0 0 auto;
      min-height: 50px;
      padding: 8px 10px;
      border-bottom: 1px solid var(--pse-border);
      background: color-mix(in srgb, var(--pse-panel) 94%, var(--pse-primary));
    }
    .pse-folder-sort {
      min-width: 0;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 2px 0;
      overflow: hidden;
      color: inherit;
      border: 0;
      background: transparent;
      cursor: pointer;
    }
    .pse-folder-sort:hover { color: var(--pse-primary); }
    .pse-folder-sort:focus-visible {
      outline: 2px solid var(--pse-accent);
      outline-offset: 2px;
      border-radius: 3px;
    }
    .pse-folder-sort #currentFolderName {
      min-width: 0;
      display: block;
    }
    .pse-folder-sort #currentFolderSortIcon { flex: 0 0 auto; }
    .pse-page-jump {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 3px;
      min-width: 48px;
      white-space: nowrap;
    }
    #filterAttachments,
    #toggleCalendar {
      width: 31px;
      min-width: 31px;
      height: 31px;
      flex: 0 0 31px;
      padding: 0;
      line-height: 1;
    }
    #filterAttachments > i,
    #toggleCalendar > i {
      display: inline-block;
      margin: 0;
      line-height: 1;
    }
    .pse-calendar-view {
      min-height: 0;
      height: 100%;
      display: flex;
      flex-direction: column;
      background: var(--pse-panel);
    }
    .pse-calendar-toolbar {
      flex: 0 0 auto;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px;
      border-bottom: 1px solid var(--pse-border);
      background: color-mix(in srgb, var(--pse-panel) 94%, var(--pse-accent));
    }
    .pse-calendar-title {
      min-width: 0;
      max-width: 240px;
      font-weight: 700;
    }
    .pse-calendar-scroll {
      min-height: 0;
      flex: 1 1 auto;
      overflow: auto;
      padding: 8px;
    }
    .pse-calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(118px, 1fr));
      min-width: 826px;
      border-top: 1px solid var(--pse-border);
      border-left: 1px solid var(--pse-border);
      background: var(--pse-border);
      gap: 1px;
    }
    .pse-calendar-weekday {
      position: sticky;
      top: 0;
      z-index: 2;
      padding: 6px 4px;
      font-size: 11px;
      font-weight: 700;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: .03em;
      background: var(--pse-panel);
    }
    .pse-calendar-blank {
      min-height: 105px;
      background: color-mix(in srgb, var(--pse-panel) 96%, var(--pse-background));
    }
    .pse-calendar-day {
      min-height: 105px;
      padding: 6px;
      border: 0;
      border-radius: 0;
      color: var(--pse-text);
      background: var(--pse-panel);
      text-align: left;
      vertical-align: top;
      cursor: pointer;
    }
    .pse-calendar-day:hover,
    .pse-calendar-day:focus-visible {
      position: relative;
      z-index: 1;
      background: color-mix(in srgb, var(--pse-panel) 88%, var(--pse-accent));
      outline: 2px solid var(--pse-accent);
      outline-offset: -2px;
    }
    .pse-calendar-day.today .pse-calendar-number {
      color: #fff;
      background: var(--pse-primary);
    }
    .pse-calendar-day.has-mail {
      background: color-mix(in srgb, var(--pse-panel) 96%, var(--pse-accent));
    }
    .pse-calendar-day-head {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 5px;
    }
    .pse-calendar-number {
      min-width: 22px;
      height: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
    }
    .pse-calendar-stats {
      margin-left: auto;
      display: inline-flex;
      gap: 3px;
      flex-wrap: wrap;
      justify-content: flex-end;
      font-size: 10px;
    }
    .pse-calendar-stat {
      padding: 1px 4px;
      border: 1px solid var(--pse-border);
      border-radius: 999px;
      background: var(--pse-panel);
      white-space: nowrap;
    }
    .pse-calendar-emails {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .pse-calendar-email {
      min-width: 0;
      padding-top: 3px;
      border-top: 1px dotted var(--pse-border);
      font-size: 10px;
      line-height: 1.18;
    }
    .pse-calendar-email:first-child {
      padding-top: 0;
      border-top: 0;
    }
    .pse-calendar-email-sender,
    .pse-calendar-email-subject {
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-calendar-email-sender { font-weight: 700; }
    .pse-calendar-email-subject { color: var(--pse-muted); }
    .pse-calendar-empty-month {
      min-height: 100%;
      display: grid;
      place-items: center;
      color: var(--pse-muted);
      text-align: center;
      padding: 24px;
    }
    .pse-page-number {
      min-width: 1.35em;
      padding: 0 2px;
      color: inherit;
      font: inherit;
      line-height: inherit;
      text-align: center;
      border: 0;
      border-bottom: 1px dotted currentColor;
      background: transparent;
      cursor: text;
    }
    .pse-page-number:hover { color: var(--pse-primary); }
    .pse-page-number:focus-visible {
      outline: 2px solid var(--pse-accent);
      outline-offset: 1px;
      border-radius: 2px;
    }
    .pse-page-input {
      width: 4.2em;
      min-width: 0;
      height: 26px;
      padding: 1px 4px;
      color: var(--pse-text);
      font: inherit;
      text-align: center;
      border: 1px solid var(--pse-accent);
      border-radius: 4px;
      background: var(--pse-panel);
      outline: 0;
    }
    .pse-scroll { min-height: 0; overflow: auto; }
    #messagesPanel .pse-panel-inner { position: relative; }
    #messagesList {
      overscroll-behavior-y: contain;
      touch-action: pan-y;
      -webkit-overflow-scrolling: touch;
      transform: translateY(0) scaleY(1);
      transform-origin: center center;
      transition: transform .2s cubic-bezier(.2,.75,.25,1), box-shadow .2s ease;
      will-change: transform;
    }
    #messagesList.pse-pulling { transition: none; }
    #messagesList.pse-pull-previous {
      transform-origin: top center;
      transform: translateY(var(--pse-pull-offset, 0px)) scaleY(var(--pse-pull-scale, 1));
      box-shadow: inset 0 12px 20px -18px var(--pse-accent);
    }
    #messagesList.pse-pull-next {
      transform-origin: bottom center;
      transform: translateY(calc(-1 * var(--pse-pull-offset, 0px))) scaleY(var(--pse-pull-scale, 1));
      box-shadow: inset 0 -12px 20px -18px var(--pse-accent);
    }
    .pse-page-pull-indicator {
      position: absolute;
      left: 50%;
      z-index: 25;
      max-width: calc(100% - 28px);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 6px 11px;
      border: 1px solid color-mix(in srgb, var(--pse-accent) 45%, var(--pse-border));
      border-radius: 999px;
      color: var(--pse-text);
      background: color-mix(in srgb, var(--pse-panel) 92%, var(--pse-accent));
      box-shadow: 0 3px 12px rgba(0,0,0,.15);
      font-size: .8rem;
      font-weight: 650;
      line-height: 1.15;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transform: translateX(-50%) scale(.96);
      overflow: hidden;
      transition: opacity .12s ease, transform .12s ease;
    }
    .pse-page-pull-indicator::after {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      height: 2px;
      background: var(--pse-accent);
      transform: scaleX(var(--pse-pull-progress, 0));
      transform-origin: left center;
      transition: transform .06s linear;
    }
    .pse-page-pull-indicator.show {
      opacity: 1;
      transform: translateX(-50%) scale(1);
    }
    .pse-page-pull-indicator.ready {
      border-color: var(--pse-accent);
      color: var(--pse-accent);
    }
    .pse-resizer {
      position: relative;
      z-index: 10;
      cursor: col-resize;
      background: #dbe1e9;
      transition: background .15s;
      touch-action: none;
    }
    .pse-resizer:hover, .pse-resizer.dragging { background: var(--pse-accent); }
    .pse-folder {
      width: 100%;
      border: 0;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: var(--pse-row-pad) 12px;
      color: var(--pse-text);
      background: transparent;
      text-align: left;
      border-radius: 9px;
    }
    .pse-folder:hover { background: var(--pse-hover); }
    .pse-folder.active {
      color: var(--pse-primary);
      background: color-mix(in srgb, var(--pse-primary) 18%, var(--pse-panel));
      font-weight: 700;
    }
    .pse-folder-name {
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-folder > .badge {
      flex: 0 0 auto;
      align-self: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 18px;
      margin-left: auto;
      line-height: 1;
      vertical-align: middle;
    }
    #contactsButton,
    #savedButton,
    #spaceUsedToggle {
      display: flex;
      align-items: center;
      min-height: 31px;
    }
    #contactsButton > .badge,
    #savedButton > .badge {
      float: none !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 18px;
      margin-left: auto;
      line-height: 1;
      vertical-align: middle;
    }
    #spaceUsedToggle > .float-end {
      float: none !important;
      align-self: center;
      margin-left: auto;
    }
    #spaceUsedBadge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 18px;
      line-height: 1;
      vertical-align: middle;
    }
    .pse-message {
      position: relative;
      width: 100%;
      border: 0;
      border-bottom: 1px solid var(--pse-border);
      display: grid;
      grid-template-columns: 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "action from date"
        "action subject subject";
      gap: 3px 7px;
      padding: var(--pse-row-pad) 11px;
      text-align: left;
      color: var(--pse-text);
      background: var(--pse-panel);
    }
    .pse-message.multi-select {
      grid-template-columns: 24px 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "select action from date"
        "select action subject subject";
    }
    .pse-message-select {
      grid-area: select;
      display: none;
      align-self: center;
    }
    .pse-message.multi-select .pse-message-select { display: block; }
    .pse-message.bulk-selected {
      background: color-mix(in srgb, var(--pse-accent) 20%, var(--pse-panel));
      box-shadow: inset 3px 0 var(--pse-accent);
    }
    .pse-message:hover { background: var(--pse-hover); }
    .pse-message.active {
      background: color-mix(in srgb, var(--pse-accent) 16%, var(--pse-panel));
      box-shadow: inset 3px 0 var(--pse-accent);
    }
    .pse-message.unread .pse-message-from { font-weight: 750; color: var(--pse-text); }
    .pse-message-action {
      grid-area: action;
      align-self: center;
      justify-self: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2px;
      min-width: 24px;
    }
    .pse-message-trash {
      color: #c75b68;
    }
    .pse-message-trash:hover { color: #dc3545; }
    .pse-message-trash.pse-message-restore { color: var(--pse-accent); }
    .pse-message-trash.pse-message-restore:hover { color: var(--pse-primary); }
    .pse-message-attachment-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 2px;
      min-width: 22px;
      padding: 1px 5px;
      border: 1px solid color-mix(in srgb, var(--pse-muted) 50%, transparent);
      border-radius: 999px;
      color: var(--pse-muted);
      background: color-mix(in srgb, var(--pse-panel) 88%, var(--pse-border));
      font-size: .66rem;
      font-weight: 700;
      line-height: 1.25;
    }
    .pse-message-from {
      grid-area: from;
      min-width: 0;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    .pse-message-date {
      grid-area: date;
      min-width: 0;
      max-width: 5.5rem;
      overflow: hidden;
      text-overflow: ellipsis;
      justify-self: end;
      text-align: right;
      font-size: .78em;
      color: var(--pse-muted);
      white-space: nowrap;
    }
    .pse-message-size {
      color: var(--pse-muted);
      font-size: .66em;
      line-height: 1;
      white-space: nowrap;
    }
    .pse-message-subject {
      grid-area: subject;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      color: var(--pse-muted);
    }
    .pse-message.has-preview {
      grid-template-areas:
        "action from date"
        "action subject subject"
        "action preview preview";
    }
    .pse-message.has-preview.multi-select {
      grid-template-areas:
        "select action from date"
        "select action subject subject"
        "select action preview preview";
    }
    .pse-message-preview {
      grid-area: preview;
      min-width: 0;
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: var(--pse-preview-lines, 2);
      color: var(--pse-muted);
      font-size: .83em;
      line-height: 1.32;
      white-space: normal;
      overflow-wrap: anywhere;
    }
    .pse-message.search-result.has-preview {
      grid-template-areas:
        "action from date"
        "action subject subject"
        "action preview preview"
        "action context context";
    }
    .pse-message.search-result.has-preview.multi-select {
      grid-template-areas:
        "select action from date"
        "select action subject subject"
        "select action preview preview"
        "select action context context";
    }
    .pse-message.same-sender {
      grid-template-areas: "action subject date";
      gap: 2px 7px;
      padding-top: max(4px, calc(var(--pse-row-pad) - 2px));
      padding-bottom: max(4px, calc(var(--pse-row-pad) - 2px));
    }
    .pse-message.same-sender.multi-select {
      grid-template-areas: "select action subject date";
    }
    .pse-message.same-sender.has-preview {
      grid-template-areas:
        "action subject date"
        "action preview preview";
    }
    .pse-message.same-sender.has-preview.multi-select {
      grid-template-areas:
        "select action subject date"
        "select action preview preview";
    }
    .pse-message.same-sender.search-result {
      grid-template-areas:
        "action subject date"
        "action context context";
    }
    .pse-message.same-sender.search-result.multi-select {
      grid-template-areas:
        "select action subject date"
        "select action context context";
    }
    .pse-message.same-sender.search-result.has-preview {
      grid-template-areas:
        "action subject date"
        "action preview preview"
        "action context context";
    }
    .pse-message.same-sender.search-result.has-preview.multi-select {
      grid-template-areas:
        "select action subject date"
        "select action preview preview"
        "select action context context";
    }
    .pse-message.no-action {
      grid-template-columns: minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "from date"
        "subject subject";
    }
    .pse-message.no-action.multi-select {
      grid-template-columns: 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "select from date"
        "select subject subject";
    }
    .pse-message.no-action.has-preview {
      grid-template-areas:
        "from date"
        "subject subject"
        "preview preview";
    }
    .pse-message.no-action.has-preview.multi-select {
      grid-template-areas:
        "select from date"
        "select subject subject"
        "select preview preview";
    }
    .pse-message.no-action.search-result {
      grid-template-columns: minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "from date"
        "subject subject"
        "context context";
    }
    .pse-message.no-action.search-result.multi-select {
      grid-template-columns: 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "select from date"
        "select subject subject"
        "select context context";
    }
    .pse-message.no-action.search-result.has-preview {
      grid-template-areas:
        "from date"
        "subject subject"
        "preview preview"
        "context context";
    }
    .pse-message.no-action.search-result.has-preview.multi-select {
      grid-template-areas:
        "select from date"
        "select subject subject"
        "select preview preview"
        "select context context";
    }
    .pse-message.no-action.same-sender {
      grid-template-columns: minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas: "subject date";
    }
    .pse-message.no-action.same-sender.multi-select {
      grid-template-columns: 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas: "select subject date";
    }
    .pse-message.no-action.same-sender.has-preview {
      grid-template-areas:
        "subject date"
        "preview preview";
    }
    .pse-message.no-action.same-sender.has-preview.multi-select {
      grid-template-areas:
        "select subject date"
        "select preview preview";
    }
    .pse-message.no-action.same-sender.search-result {
      grid-template-areas:
        "subject date"
        "context context";
    }
    .pse-message.no-action.same-sender.search-result.multi-select {
      grid-template-areas:
        "select subject date"
        "select context context";
    }
    .pse-message.no-action.same-sender.search-result.has-preview {
      grid-template-areas:
        "subject date"
        "preview preview"
        "context context";
    }
    .pse-message.no-action.same-sender.search-result.has-preview.multi-select {
      grid-template-areas:
        "select subject date"
        "select preview preview"
        "select context context";
    }
    .pse-message.same-sender.unread .pse-message-subject {
      color: var(--pse-text);
      font-weight: 750;
    }
    .pse-day-separator {
      position: sticky;
      top: 0;
      z-index: 3;
      padding: 5px 10px 4px;
      border-bottom: 1px solid var(--pse-border);
      color: var(--pse-text);
      background: color-mix(in srgb, var(--pse-panel) 88%, var(--pse-primary));
      font-size: .82em;
      font-weight: 750;
      letter-spacing: .15px;
    }
    .pse-sender-filter-banner {
      position: sticky;
      top: 0;
      z-index: 4;
      padding: 6px 10px;
      overflow: hidden;
      border-bottom: 1px solid var(--pse-border);
      color: var(--pse-text);
      background: color-mix(in srgb, var(--pse-panel) 82%, var(--pse-accent));
      font-size: .84em;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-sender-filter-banner ~ .pse-day-separator { top: 31px; }
    .pse-space-details {
      max-height: 38vh;
      overflow: auto;
      padding: 7px 8px;
      border: 1px solid var(--pse-border);
      border-radius: 7px;
      background: color-mix(in srgb, var(--pse-panel) 94%, var(--pse-primary));
      font-size: .78em;
    }
    .pse-space-section + .pse-space-section {
      margin-top: 7px;
      padding-top: 7px;
      border-top: 1px solid var(--pse-border);
    }
    .pse-space-row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      padding: 1px 0;
    }
    .pse-space-account + .pse-space-account {
      margin-top: 6px;
      padding-top: 6px;
      border-top: 1px dotted var(--pse-border);
    }
    #spaceUsedToggle[aria-expanded="true"] #spaceUsedChevron { transform: rotate(180deg); }
    #spaceUsedChevron { transition: transform .16s ease; }
    .pse-message.search-result {
      grid-template-columns: 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "action from date"
        "action subject subject"
        "action context context";
    }
    .pse-message.search-result.multi-select {
      grid-template-columns: 24px 24px minmax(0, 1fr) minmax(0, 5.5rem);
      grid-template-areas:
        "select action from date"
        "select action subject subject"
        "select action context context";
    }
    .pse-message-search-context {
      grid-area: context;
      min-width: 0;
      margin-top: 3px;
      padding: 4px 6px;
      border-left: 2px solid color-mix(in srgb, var(--pse-accent) 58%, var(--pse-border));
      border-radius: 3px;
      color: var(--pse-muted);
      background: color-mix(in srgb, var(--pse-panel) 88%, var(--pse-accent));
      font-size: .84em;
      line-height: 1.3;
    }
    .pse-search-context-line {
      display: block;
      min-height: 1.3em;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-search-context-line.match { color: var(--pse-text); }
    .pse-search-context-line mark {
      padding: 0 2px;
      border-radius: 2px;
      color: #201a00;
      background: #ffdf3d;
    }
    .pse-empty {
      height: 100%;
      min-height: 180px;
      display: grid;
      place-items: center;
      padding: 30px;
      color: #8a94a4;
      text-align: center;
    }
    .pse-preview-header {
      flex: 0 0 auto;
      padding: 18px 22px 12px;
      border-bottom: 1px solid #e5e8ed;
    }
    .pse-preview-subject { font-size: 1.42em; font-weight: 700; color: var(--pse-text); }
    .pse-spam-warning {
      flex: 0 0 auto;
      margin: var(--pse-pad);
      padding: 18px;
      border: 3px solid #8b0017;
      border-radius: 10px;
      color: #fff;
      background: #c1122f;
      box-shadow: 0 6px 18px rgba(139, 0, 23, .22);
      font-size: 1.05rem;
      line-height: 1.45;
    }
    .pse-spam-warning-title {
      font-size: 1.25rem;
      font-weight: 800;
    }
    .pse-address-line { color: var(--pse-muted); }
    .pse-email-frame {
      width: 100%;
      height: 100%;
      border: 0;
      background: #fff;
    }
    .pse-resizing,
    .pse-resizing * { cursor: col-resize !important; }
    .pse-resizing.pse-resizing-row,
    .pse-resizing.pse-resizing-row * { cursor: row-resize !important; }
    .pse-resizing .pse-email-frame { pointer-events: none !important; }
    .pse-attachments {
      flex: 0 0 auto;
      border-top: 1px solid #e5e8ed;
      padding: 10px 18px;
      background: #fafbfd;
    }
    .pse-footer {
      position: sticky;
      bottom: 0;
      z-index: 1020;
      min-height: 31px;
      display: flex;
      align-items: center;
      gap: 18px;
      padding: 4px 12px;
      font-size: 12px;
      color: #526075;
      background: #e8ecf2;
      border-top: 1px solid #ccd3de;
      white-space: nowrap;
      overflow: hidden;
    }
    .pse-footer-action {
      border: 0;
      padding: 0;
      color: inherit;
      background: transparent;
      font: inherit;
      white-space: nowrap;
      cursor: pointer;
    }
    .pse-footer-action:hover,
    .pse-footer-action:focus-visible {
      color: var(--pse-primary);
      text-decoration: underline;
    }
    .pse-status-dot {
      width: 8px;
      height: 8px;
      display: inline-block;
      border-radius: 50%;
      margin-right: 5px;
      background: #8994a5;
    }
    .pse-status-dot.online { background: #22a06b; }
    .pse-spinner {
      position: fixed;
      inset: 0;
      z-index: 3000;
      display: none;
      place-items: center;
      background: rgba(246, 248, 251, .62);
      backdrop-filter: blur(1.5px);
    }
    .pse-spinner.show { display: grid; }
    .pse-spinner-box {
      min-width: 132px;
      padding: 18px;
      border-radius: 14px;
      text-align: center;
      color: var(--pse-primary);
      background: #fff;
      box-shadow: 0 12px 40px rgba(20,40,70,.18);
    }
    .pse-recipient-area {
      min-height: 42px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 5px;
      padding: 5px 8px;
      border: 1px solid #dee2e6;
      border-radius: .375rem;
      background: #fff;
    }
    .pse-recipient-chip {
      max-width: 260px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 7px;
      border-radius: 14px;
      color: #114b7a;
      background: #e6f2fc;
      font-size: .9em;
    }
    .pse-recipient-chip span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pse-recipient-chip button {
      border: 0;
      padding: 0;
      color: inherit;
      background: transparent;
    }
    .recipient-picker.pse-recipient-active {
      color: #fff !important;
      border-color: var(--pse-primary) !important;
      background: var(--pse-primary) !important;
      box-shadow: 0 0 0 .15rem color-mix(in srgb, var(--pse-primary) 24%, transparent);
    }
    .pse-recipient-area.pse-recipient-active {
      border-color: var(--pse-primary);
      box-shadow: 0 0 0 .15rem color-mix(in srgb, var(--pse-primary) 16%, transparent);
    }
    .pse-recipient-chip[draggable="true"] { cursor: grab; }
    .pse-recipient-chip.pse-recipient-dragging { opacity: .48; cursor: grabbing; }
    .pse-recipient-area.pse-recipient-drop-target {
      border-color: var(--pse-primary);
      background: color-mix(in srgb, var(--pse-primary) 7%, #fff);
      box-shadow: 0 0 0 .18rem color-mix(in srgb, var(--pse-primary) 18%, transparent);
    }
    .pse-recipient-suggestions {
      position: fixed;
      z-index: 1090;
      min-width: min(320px, calc(100vw - 24px));
      overflow: hidden;
      border: 1px solid #cfd6df;
      border-radius: 9px;
      background: #fff;
      box-shadow: 0 12px 34px rgba(20, 35, 60, .24);
    }
    .pse-recipient-suggestions-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      border-bottom: 1px solid #e2e6eb;
      background: #f8f9fb;
    }
    .pse-recipient-suggestions-list {
      max-height: 320px;
      overflow-y: auto;
      overscroll-behavior: contain;
    }
    .pse-recipient-suggestion {
      width: 100%;
      display: grid;
      grid-template-columns: 24px minmax(0, 1fr);
      gap: 9px;
      align-items: center;
      padding: 9px 10px;
      border: 0;
      border-bottom: 1px solid #edf0f4;
      text-align: left;
      color: inherit;
      background: #fff;
    }
    .pse-recipient-suggestion:hover,
    .pse-recipient-suggestion.selected {
      background: #edf6ff;
    }
    .pse-emoji-menu {
      width: 292px;
      max-width: calc(100vw - 32px);
      border-color: var(--pse-border);
      color: var(--pse-text);
      background: var(--pse-panel);
    }
    .pse-emoji-grid {
      display: grid;
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: 3px;
    }
    .pse-emoji-choice {
      display: grid;
      place-items: center;
      min-width: 0;
      aspect-ratio: 1;
      border: 0;
      border-radius: 6px;
      color: inherit;
      background: transparent;
      font-size: 1.35rem;
      line-height: 1;
    }
    .pse-emoji-choice:hover,
    .pse-emoji-choice:focus {
      background: var(--pse-hover);
    }
    .pse-compose-format-sticky {
      position: sticky;
      top: 0;
      z-index: 8;
      background: var(--pse-panel);
      box-shadow: 0 12px 16px -18px rgba(15, 31, 56, .7);
      isolation: isolate;
    }
    .pse-color-picker-menu {
      width: 218px;
      max-width: calc(100vw - 32px);
      color: var(--pse-text);
      background: var(--pse-panel);
      border-color: var(--pse-border);
    }
    .pse-color-grid {
      display: grid;
      grid-template-columns: repeat(4, 38px);
      justify-content: center;
      gap: 8px;
    }
    .pse-color-choice {
      position: relative;
      width: 38px;
      height: 38px;
      padding: 0;
      border: 2px solid color-mix(in srgb, var(--pse-border) 84%, #000);
      border-radius: 8px;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .3);
    }
    .pse-color-choice:hover,
    .pse-color-choice:focus-visible {
      transform: scale(1.08);
      outline: 2px solid color-mix(in srgb, var(--pse-primary) 55%, transparent);
      outline-offset: 1px;
    }
    .pse-color-choice.active::after {
      content: '\2713';
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      color: #fff;
      font-size: 1rem;
      font-weight: 800;
      text-shadow: 0 1px 3px rgba(0, 0, 0, .85);
    }
    .pse-color-choice[data-light="1"].active::after {
      color: #172033;
      text-shadow: 0 1px 2px rgba(255, 255, 255, .9);
    }
    .pse-current-color {
      width: 18px;
      height: 18px;
      display: inline-block;
      flex: 0 0 18px;
      border: 1px solid rgba(0, 0, 0, .35);
      border-radius: 4px;
      background: #202632;
    }
    .pse-native-color-input {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      opacity: 0;
      pointer-events: none;
    }
    .pse-compose-body {
      min-height: 280px;
      transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .pse-compose-body.pse-image-drag {
      border-color: var(--pse-accent);
      box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--pse-accent) 35%, transparent);
      background: color-mix(in srgb, var(--pse-accent) 5%, white);
    }
    .pse-compose-body img {
      max-width: 100%;
      height: auto;
    }
    #composeModal.pse-compose-maximized .modal-dialog {
      width: 100vw;
      max-width: none;
      height: 100vh;
      margin: 0;
    }
    #composeModal.pse-compose-maximized .modal-content {
      height: 100vh;
      max-height: 100vh;
      border-radius: 0;
    }
    #composeModal.pse-compose-maximized .modal-body {
      max-height: none;
    }
    #composeModal.pse-compose-maximized .pse-compose-body {
      min-height: max(280px, 45vh);
    }
    .pse-compose-window-controls {
      display: flex;
      align-items: center;
      gap: .2rem;
      margin-left: auto;
    }
    #composeMaximizeButton {
      line-height: 1;
      margin: 0;
    }
    #composeCloseButton {
      margin: 0 !important;
    }
    .pse-image-progress {
      display: none;
      padding: 7px 10px;
      border: 1px solid #dee2e6;
      border-top: 0;
      background: #f8f9fa;
    }
    .pse-image-progress.show { display: block; }
    .pse-unknown-contact {
      display: grid;
      grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr);
      gap: 10px;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #edf0f4;
    }
    .pse-read-contact-suggestion {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr) auto;
      gap: 8px;
      align-items: center;
      padding: 7px 0;
      border-bottom: 1px solid #edf0f4;
    }
    .pse-read-contact-suggestion:last-child { border-bottom: 0; }
    .pse-contact-row {
      display: grid;
      grid-template-columns: 32px minmax(0, 1fr) auto;
      align-items: center;
      gap: 10px;
      padding: 9px 8px;
      border-bottom: 1px solid #edf0f4;
    }
    .pse-contact-row:hover { background: #f6f8fb; }
    .pse-contact-name { font-weight: 650; }
    .pse-contact-email { color: #718096; font-size: .88em; }
    .pse-contact-edit-popup {
      width: min(640px, calc(100vw - 32px)) !important;
      max-width: calc(100vw - 32px) !important;
    }
    .pse-contact-edit-popup .swal2-html-container {
      overflow-x: hidden;
      margin-left: 0;
      margin-right: 0;
      padding-left: 1rem;
      padding-right: 1rem;
    }
    .pse-saved-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      align-items: center;
      padding: 10px 6px;
      border-bottom: 1px solid #e8ebef;
    }
    .pse-saved-title {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-weight: 650;
    }
    .pse-file-list { font-size: .88em; color: #667085; }
    .modal-content { border: 0; box-shadow: 0 15px 50px rgba(15,31,56,.24); }
    body.pse-theme-dark .modal-content,
    body.pse-theme-dark .card,
    body.pse-theme-dark .dropdown-menu,
    body.pse-theme-dark .pse-recipient-area,
    body.pse-theme-dark .pse-recipient-suggestions,
    body.pse-theme-dark .pse-recipient-suggestion,
    body.pse-theme-dark .pse-spinner-box {
      color: var(--pse-text);
      background: var(--pse-panel);
      border-color: var(--pse-border);
    }
    body.pse-theme-dark .form-control,
    body.pse-theme-dark .form-select,
    body.pse-theme-dark .input-group-text,
    body.pse-theme-dark .btn-light {
      color: var(--pse-text);
      background-color: var(--pse-input);
      border-color: var(--pse-border);
    }
    body.pse-theme-dark .form-control::placeholder { color: #8592a5; }
    body.pse-theme-dark .text-secondary,
    body.pse-theme-dark .form-text,
    body.pse-theme-dark .pse-contact-email,
    body.pse-theme-dark .pse-file-list { color: var(--pse-muted) !important; }
    body.pse-theme-dark .bg-light,
    body.pse-theme-dark .pse-recipient-suggestions-header,
    body.pse-theme-dark .pse-attachments,
    body.pse-theme-dark .pse-image-progress {
      color: var(--pse-text) !important;
      background: color-mix(in srgb, var(--pse-panel) 88%, var(--pse-accent)) !important;
      border-color: var(--pse-border);
    }
    body.pse-theme-dark .pse-recipient-suggestion:hover,
    body.pse-theme-dark .pse-recipient-suggestion.selected,
    body.pse-theme-dark .pse-contact-row:hover {
      background: var(--pse-hover);
    }
    body.pse-theme-dark .pse-footer {
      color: var(--pse-muted);
      background: color-mix(in srgb, var(--pse-panel) 82%, black);
      border-color: var(--pse-border);
    }
    body.pse-theme-dark .pse-spinner { background: rgba(7, 12, 20, .68); }
    body.pse-theme-dark .btn-close { filter: invert(1) grayscale(100%) brightness(180%); }
    body.pse-theme-dark .nav-tabs,
    body.pse-theme-dark .tab-content,
    body.pse-theme-dark .modal-header,
    body.pse-theme-dark .modal-footer,
    body.pse-theme-dark .pse-contact-row,
    body.pse-theme-dark .pse-saved-row {
      border-color: var(--pse-border) !important;
    }
    body.pse-density-ultra_compact {
      line-height: 1.18;
    }
    body.pse-density-ultra_compact .pse-header {
      min-height: 40px;
      gap: 6px !important;
      padding: 3px 7px !important;
    }
    body.pse-density-ultra_compact .pse-brand { min-width: 160px; }
    body.pse-density-ultra_compact .pse-panel-toolbar {
      min-height: 34px;
      padding: 3px 5px;
    }
    body.pse-density-ultra_compact .pse-folder {
      gap: 4px;
      padding: 3px 5px;
      border-radius: 4px;
      line-height: 1.12;
    }
    body.pse-density-ultra_compact .pse-message {
      grid-template-columns: 18px minmax(0, 1fr) auto;
      gap: 1px 4px;
      padding: 3px 5px;
      line-height: 1.12;
    }
    body.pse-density-ultra_compact .pse-message.multi-select {
      grid-template-columns: 18px 18px minmax(0, 1fr) auto;
    }
    body.pse-density-ultra_compact .pse-message.search-result {
      grid-template-columns: 18px minmax(0, .8fr) minmax(0, 1.4fr) auto;
    }
    body.pse-density-ultra_compact .pse-message.search-result.multi-select {
      grid-template-columns: 18px 18px minmax(0, .8fr) minmax(0, 1.4fr) auto;
    }
    body.pse-density-ultra_compact .pse-message-search-context {
      margin-top: 1px;
      padding: 2px 4px;
    }
    body.pse-density-ultra_compact .pse-preview-header { padding: 6px 8px 4px; }
    body.pse-density-ultra_compact .pse-preview-subject { font-size: 1.12em; }
    body.pse-density-ultra_compact .pse-attachments { padding: 4px 7px; }
    body.pse-density-ultra_compact .pse-footer {
      min-height: 23px;
      gap: 8px;
      padding: 1px 6px;
    }
    body.pse-density-ultra_compact .modal-header,
    body.pse-density-ultra_compact .modal-footer { padding: 5px 7px; }
    body.pse-density-ultra_compact .modal-body { padding: 7px; }
    body.pse-density-ultra_compact .tab-content { padding: 6px !important; }
    body.pse-density-ultra_compact .row {
      --bs-gutter-x: .5rem;
      --bs-gutter-y: .35rem;
    }
    body.pse-density-ultra_compact .form-label { margin-bottom: 2px; }
    body.pse-density-ultra_compact .form-control,
    body.pse-density-ultra_compact .form-select,
    body.pse-density-ultra_compact .input-group-text,
    body.pse-density-ultra_compact .btn {
      min-height: 28px;
      padding-top: 2px;
      padding-bottom: 2px;
      line-height: 1.18;
    }
    body.pse-density-ultra_compact .pse-recipient-area {
      min-height: 30px;
      gap: 3px;
      padding: 2px 4px;
    }
    body.pse-density-ultra_compact .pse-recipient-chip { padding: 1px 5px; }
    body.pse-density-ultra_compact .pse-contact-row,
    body.pse-density-ultra_compact .pse-saved-row,
    body.pse-density-ultra_compact .pse-unknown-contact { padding: 3px 4px; }
    .pse-mobile-pane-arrow { display: none; }
    .pse-mobile-swipe-hint {
      display: none;
      position: fixed;
      left: 10px;
      top: 50%;
      z-index: 2200;
      width: 72px;
      height: 72px;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      color: #fff;
      background: color-mix(in srgb, var(--pse-primary) 88%, #000);
      border: 2px solid rgba(255,255,255,.8);
      border-radius: 50%;
      box-shadow: 0 8px 28px rgba(0,0,0,.28);
      font-size: 34px;
      opacity: 0;
    }
    @keyframes pseSwipeBackHint {
      0% { opacity: 0; transform: translate(34px, -50%) scale(.72); }
      12% { opacity: 1; transform: translate(25px, -50%) scale(1); }
      48% { opacity: 1; transform: translate(2px, -50%) scale(1); }
      72% { opacity: .9; transform: translate(-8px, -50%) scale(.96); }
      100% { opacity: 0; transform: translate(-28px, -50%) scale(.82); }
    }
    .pse-pwa-install-button {
      position: relative;
      overflow: hidden;
    }
    .pse-pwa-install-button.pse-pwa-ready::after {
      content: '';
      position: absolute;
      inset: -60% -25%;
      transform: translateX(-110%) rotate(18deg);
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.42), transparent);
      animation: psePwaInstallShine 3.4s ease-in-out infinite;
      pointer-events: none;
    }
    @keyframes psePwaInstallShine {
      0%, 62% { transform: translateX(-110%) rotate(18deg); }
      82%, 100% { transform: translateX(110%) rotate(18deg); }
    }
    .pse-app-icon-upload {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 12px;
      border: 1px solid var(--pse-border);
      border-radius: 12px;
      background: color-mix(in srgb, var(--pse-panel) 97%, var(--pse-primary));
    }
    .pse-app-icon-upload-preview {
      width: 54px;
      height: 54px;
      flex: 0 0 54px;
      border-radius: 13px;
      object-fit: cover;
      border: 1px solid var(--pse-border);
      background: var(--pse-panel);
      box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }
    .pse-app-icon-upload-copy { min-width: 0; flex: 1 1 auto; }
    @media (max-width: 575.98px) {
      .pse-app-icon-upload { align-items: flex-start; flex-wrap: wrap; }
      .pse-app-icon-upload .btn { margin-left: 66px; }
    }

    .pse-pwa-settings-card {
      border: 1px solid color-mix(in srgb, var(--pse-primary) 22%, var(--pse-border));
      border-radius: 14px;
      background: color-mix(in srgb, var(--pse-panel) 94%, var(--pse-primary));
      box-shadow: 0 4px 18px rgba(15, 31, 52, .06);
    }
    .pse-pwa-settings-icon {
      width: 48px;
      height: 48px;
      flex: 0 0 48px;
      overflow: hidden;
      border-radius: 12px;
      box-shadow: 0 4px 13px rgba(0,0,0,.16);
      background: var(--pse-panel);
    }
    .pse-pwa-settings-icon img { width: 100%; height: 100%; object-fit: cover; }
    .pse-pwa-status-dot {
      width: 9px;
      height: 9px;
      display: inline-block;
      border-radius: 50%;
      background: #6c757d;
      box-shadow: 0 0 0 3px color-mix(in srgb, #6c757d 16%, transparent);
    }
    .pse-pwa-status-dot.ready { background: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.14); }
    .pse-pwa-status-dot.installed { background: #198754; box-shadow: 0 0 0 3px rgba(25,135,84,.14); }
    .pse-pwa-status-dot.warning { background: #f0ad4e; box-shadow: 0 0 0 3px rgba(240,173,78,.16); }
    .pse-pwa-swal-popup { border-radius: 22px !important; overflow: hidden; }
    .pse-pwa-dialog { text-align: left; }
    .pse-pwa-dialog-hero {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: -2px 0 18px;
    }
    .pse-pwa-dialog-logo {
      width: 72px;
      height: 72px;
      flex: 0 0 72px;
      border-radius: 18px;
      object-fit: cover;
      box-shadow: 0 9px 24px rgba(0,0,0,.18);
    }
    .pse-pwa-dialog-title { margin: 0; font-size: 1.34rem; font-weight: 750; color: var(--pse-text); }
        .pse-pwa-dialog-status {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      margin-top: 8px;
      padding: 5px 9px;
      border: 1px solid var(--pse-border);
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 650;
      background: var(--pse-panel);
      color: var(--pse-text);
    }
    .pse-pwa-benefits {
      display: grid;
      gap: 9px;
      margin: 15px 0 0;
    }
    .pse-pwa-benefit {
      display: grid;
      grid-template-columns: 52px 1fr;
      gap: 14px;
      align-items: center;
      padding: 10px 11px;
      border: 1px solid var(--pse-border);
      border-radius: 12px;
      background: color-mix(in srgb, var(--pse-panel) 96%, var(--pse-primary));
    }
    .pse-pwa-benefit-icon {
      width: 52px;
      height: 52px;
      display: flex;
      align-items: center;
      justify-content: center;
      justify-self: center;
      align-self: center;
      border-radius: 14px;
      color: var(--pse-primary);
      background: color-mix(in srgb, var(--pse-primary) 11%, transparent);
      overflow: hidden;
      line-height: 1;
      padding: 0;
    }
    .pse-pwa-benefit-icon > i {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 0;
      line-height: 1;
      text-align: center;
      font-size: 1.2rem;
      flex: 0 0 auto;
    }
    .pse-pwa-benefit strong { display: block; color: var(--pse-text); font-size: .9rem; }
    .pse-pwa-benefit span { display: block; color: var(--pse-muted); font-size: .78rem; line-height: 1.25; }
    .pse-pwa-instructions {
      margin-top: 14px;
      padding: 11px 12px;
      border-radius: 11px;
      background: color-mix(in srgb, var(--pse-primary) 7%, var(--pse-panel));
      color: var(--pse-text);
      font-size: .84rem;
      line-height: 1.4;
    }
    @media (max-width: 900px) {
      body { overflow: auto; }
      .pse-header .pse-brand-title,
      .pse-header .pse-account-switcher { display: none !important; }
      .pse-footer-label,
      .pse-footer-version { display: none !important; }
      #statFolder {
        display: inline-block !important;
        max-width: 92px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
      }
      .pse-footer {
        justify-content: space-between;
        gap: 8px;
      }
      .pse-footer-action i { margin-right: 3px !important; }
      #footerFolderAction i { margin-right: 3px !important; }
      body.pse-mobile-single-pane .pse-workspace {
        display: block;
        position: relative;
        overflow: hidden;
      }
      body.pse-mobile-single-pane #foldersPanel,
      body.pse-mobile-single-pane #messagesPanel,
      body.pse-mobile-single-pane #previewPanel {
        display: none;
        width: 100%;
        height: 100%;
        border-right: 0;
      }
      body.pse-mobile-single-pane.pse-mobile-pane-folders #foldersPanel,
      body.pse-mobile-single-pane.pse-mobile-pane-messages #messagesPanel,
      body.pse-mobile-single-pane.pse-mobile-pane-preview #previewPanel { display: block; }
      body.pse-mobile-single-pane #resizer1,
      body.pse-mobile-single-pane #resizer2 { display: none !important; }
      body.pse-mobile-single-pane #toggleViewMode { display: none !important; }
      body.pse-mobile-single-pane .pse-mobile-pane-arrow {
        width: 29px;
        height: 29px;
        min-width: 29px;
        flex: 0 0 29px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        color: #fff;
        background: var(--pse-primary);
        border: 1px solid color-mix(in srgb, var(--pse-primary) 70%, #000);
        border-radius: 50%;
        box-shadow: 0 1px 5px rgba(0,0,0,.28);
        font-size: 15px;
        line-height: 1;
        text-decoration: none;
      }
      body.pse-mobile-single-pane .pse-mobile-pane-arrow:hover,
      body.pse-mobile-single-pane .pse-mobile-pane-arrow:focus-visible {
        color: #fff;
        background: color-mix(in srgb, var(--pse-primary) 82%, #000);
        text-decoration: none;
      }
      body.pse-mobile-single-pane .pse-mobile-pane-arrow:disabled {
        opacity: .28;
        box-shadow: none;
        cursor: default;
      }
      body.pse-mobile-single-pane .pse-mobile-swipe-hint.pse-show {
        display: flex;
        animation: pseSwipeBackHint 2s ease-out forwards;
      }
      .pse-shell { min-height: 100vh; height: auto; }
      .pse-header .pse-brand { min-width: auto; max-width: 180px; }
      .pse-workspace {
        height: calc(100vh - 95px);
        grid-template-columns: minmax(34px, 22fr) 4px minmax(34px, 38fr) 4px minmax(34px, 40fr);
        grid-template-rows: minmax(0, 1fr);
        grid-template-areas: "folders resize1 messages resize2 preview";
      }
      #previewPanel, #resizer2 { display: block; }
      body.pse-view-stacked .pse-workspace {
        grid-template-columns: minmax(34px, 28fr) 4px minmax(34px, 72fr);
        grid-template-rows: minmax(80px, 42%) 4px minmax(80px, 1fr);
        grid-template-areas:
          "folders resize1 messages"
          "folders resize1 resize2"
          "folders resize1 preview";
      }
      body.pse-view-stacked #previewPanel,
      body.pse-view-stacked #resizer2 { display: block; }
      body.pse-view-stacked #resizer2 { min-height: 4px; }
      .pse-footer { gap: 9px; }
    }
  </style>
</head>
<body class="pse-density-<?= htmlspecialchars((string)$pseSettings['density'], ENT_QUOTES, 'UTF-8') ?><?= $pseDarkTheme ? ' pse-theme-dark' : '' ?>">
<?php if (!empty($pseBootError)): ?>
  <main class="pse-auth-page">
    <div class="card pse-auth-card">
      <div class="card-body p-4 p-md-5">
        <div class="text-danger fs-1 mb-3"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h1 class="h4">Application cannot start</h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars($pseBootError, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
  </main>
<?php elseif (!$pseInitialized || !$pseAuthenticated): ?>
  <main class="pse-auth-page">
    <div class="card pse-auth-card">
      <div class="card-body p-4 p-md-5">
        <div class="pse-logo mb-3"><i class="fa-solid fa-envelope-open-text fa-xl"></i></div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string)$pseSettings['app_title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-4">
          <?= !$pseInitialized ? 'First-time setup' : 'Enter your application password' ?>
        </p>
        <form id="authForm">
          <input type="hidden" id="authAction" value="<?= !$pseInitialized ? 'setup' : 'login' ?>">
          <label class="form-label" for="authPassword"><?= !$pseInitialized ? 'Create application password' : 'Password' ?></label>
          <div class="input-group mb-3">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input
              class="form-control"
              type="password"
              id="authPassword"
              value="<?= !$pseInitialized ? 'password' : '' ?>"
              autocomplete="<?= !$pseInitialized ? 'new-password' : 'current-password' ?>"
              required
              autofocus
            >
            <button class="btn btn-outline-secondary" type="button" id="toggleAuthPassword" aria-label="Show password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
          <?php if (!$pseInitialized): ?>
            <div class="alert alert-warning py-2 small">
              The default is <code>password</code>. Change it now if the page is publicly reachable.
            </div>
          <?php endif; ?>
          <button class="btn btn-primary w-100" type="submit">
            <i class="fa-solid fa-right-to-bracket me-2"></i><?= !$pseInitialized ? 'Create and open' : 'Open email' ?>
          </button>
          <button class="btn btn-outline-secondary w-100 mt-2 d-none pse-pwa-install-button" id="pwaAuthInstallButton" type="button">
            <i class="fa-solid fa-download me-2"></i><span>Install PSE</span>
          </button>
        </form>
      </div>
    </div>
  </main>
  <div class="pse-spinner" id="authSpinner">
    <div class="pse-spinner-box"><div class="spinner-border mb-2"></div><div>Working…</div></div>
  </div>
  <script>
    document.getElementById('toggleAuthPassword').addEventListener('click', function () {
      const field = document.getElementById('authPassword');
      field.type = field.type === 'password' ? 'text' : 'password';
    });
    document.getElementById('authForm').addEventListener('submit', async function (event) {
      event.preventDefault();
      const spinner = document.getElementById('authSpinner');
      spinner.classList.add('show');
      try {
        const action = document.getElementById('authAction').value;
        const response = await fetch('?ajax=' + encodeURIComponent(action), {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({password: document.getElementById('authPassword').value})
        });
        const raw = await response.text();
        let result;
        try {
          result = JSON.parse(raw);
        } catch (parseError) {
          throw new Error('The server returned an invalid response. Check the PHP error log.');
        }
        if (!response.ok || !result.ok) {
          throw new Error(result.error || 'Unable to continue.');
        }
        location.reload();
      } catch (e) {
        Swal.fire({
          icon: 'error',
          title: 'Unable to continue',
          text: e.message || String(e)
        });
      } finally {
        spinner.classList.remove('show');
      }
    });
  </script>
<?php else: ?>
  <div class="pse-shell">
    <header class="pse-header d-flex align-items-center gap-3 px-3 py-2">
      <div class="pse-brand" title="<?= htmlspecialchars((string)$pseSettings['app_title'], ENT_QUOTES, 'UTF-8') ?>">
        <span class="pse-brand-avatar" aria-hidden="true"><img src="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
        <span class="pse-brand-title"><?= htmlspecialchars((string)$pseSettings['app_title'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="pse-account-switcher" id="accountQuickSwitcher">
          <button class="pse-account-badge" id="activeAccountButton" type="button" aria-haspopup="menu" aria-expanded="false" title="<?= htmlspecialchars((string)$pseSettings['account_name'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="pse-account-badge-name" id="activeAccountName"><?= htmlspecialchars((string)$pseSettings['account_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fa-solid fa-chevron-down pse-account-badge-chevron d-none" id="accountQuickChevron" aria-hidden="true"></i>
          </button>
          <div class="pse-account-menu" id="accountQuickMenu" role="menu" hidden></div>
        </span>
      </div>
      <div class="pse-search flex-grow-1 mx-auto">
        <div class="input-group">
          <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
          <input class="form-control" id="globalSearch" placeholder="Search this folder…" autocomplete="off">
          <button class="btn btn-light d-none" id="restoreLastSearch" title="Show last saved search">
            <i class="fa-solid fa-clock-rotate-left"></i>
          </button>
          <button class="btn btn-light d-none" id="clearSearch" title="Clear search">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-light" id="headerCompose" title="New email">
          <i class="fa-solid fa-pen-to-square"></i><span class="d-none d-lg-inline ms-1">New</span>
        </button>
        <button
          class="btn btn-sm btn-outline-light d-inline-flex align-items-center justify-content-center flex-shrink-0"
          id="toggleViewMode"
          type="button"
          title="Three-column view: switch to stacked preview"
          aria-label="Three-column view: switch to stacked preview"
          aria-pressed="false"
        >
          <i class="fa-solid fa-table-columns" id="viewModeIcon"></i>
        </button>
        <button class="btn btn-sm btn-light d-none pse-pwa-install-button" id="pwaInstallButton" type="button" title="Install PSE" aria-label="Install PSE">
          <i class="fa-solid fa-download"></i><span class="d-none d-xl-inline ms-1">Install</span>
        </button>
        <button class="btn btn-sm btn-outline-light" id="openSettings" title="Settings">
          <i class="fa-solid fa-gear"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="logoutButton" title="Logout">
          <i class="fa-solid fa-right-from-bracket"></i>
        </button>
      </div>
    </header>

    <main class="pse-workspace" id="workspace">
      <section class="pse-panel" id="foldersPanel">
        <div class="pse-panel-inner">
          <div class="pse-panel-toolbar d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" id="composeButton">
              <i class="fa-solid fa-plus me-1"></i>New email
            </button>
            <button class="btn btn-outline-secondary" id="refreshFolders" title="Force refresh from the email server">
              <i class="fa-solid fa-rotate"></i>
            </button>
          </div>
          <div class="px-2 pb-2 small text-secondary text-truncate" id="lastSyncStatus" title="Mailbox cache status">
            Loading cached mailbox…
          </div>
          <div class="pse-scroll p-2" id="foldersList">
            <div class="pse-empty"><div><i class="fa-regular fa-folder-open fa-2x mb-2"></i><br>Loading folders…</div></div>
          </div>
          <div class="border-top p-2">
            <button class="btn btn-sm btn-light w-100 text-start" id="contactsButton">
              <i class="fa-solid fa-address-book me-2 text-pse"></i>Contacts
              <span class="badge text-bg-secondary float-end" id="contactsBadge">0</span>
            </button>
            <button class="btn btn-sm btn-light w-100 text-start mt-1" id="savedButton">
              <i class="fa-solid fa-file-pen me-2 text-pse"></i>Saved drafts (.PSE)
              <span class="badge text-bg-secondary float-end" id="savedBadge">0</span>
            </button>
            <button
              class="btn btn-sm btn-light w-100 text-start mt-1"
              id="spaceUsedToggle"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#spaceUsedDetails"
              aria-expanded="false"
              aria-controls="spaceUsedDetails"
            >
              <i class="fa-solid fa-hard-drive me-2 text-pse"></i>Space Used
              <span class="float-end d-inline-flex align-items-center gap-2">
                <span class="badge text-bg-secondary" id="spaceUsedBadge">—</span>
                <i class="fa-solid fa-chevron-down small" id="spaceUsedChevron"></i>
              </span>
            </button>
            <div class="collapse" id="spaceUsedDetails">
              <div class="pse-space-details mt-1" id="spaceUsedContent">
                <div class="text-secondary small text-center py-2">Open to calculate storage usage.</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div class="pse-resizer" id="resizer1" role="separator" aria-orientation="vertical"></div>

      <section class="pse-panel" id="messagesPanel">
        <div class="pse-panel-inner">
          <div class="pse-panel-toolbar">
            <div class="d-flex align-items-center gap-2">
              <button
                class="pse-folder-sort fw-semibold text-start flex-grow-1"
                id="currentFolderSort"
                type="button"
                title="Sort oldest to newest"
                aria-label="Sort oldest to newest"
              >
                <span class="text-truncate" id="currentFolderName">Inbox</span>
                <i class="fa-solid fa-arrow-down small" id="currentFolderSortIcon" aria-hidden="true"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="toggleUnreadOnly" title="Show only unread messages">
                <i class="fa-regular fa-envelope"></i>
              </button>
              <button
                class="btn btn-sm btn-outline-secondary"
                id="filterSameSender"
                title="Select an email first, then show only messages from the same sender"
                aria-label="Show only messages from the same sender"
                disabled
              >
                <i class="fa-solid fa-user-tag"></i>
              </button>
              <button
                class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                id="filterAttachments"
                type="button"
                title="Show only emails with attachments"
                aria-label="Attachment filter: all emails"
                aria-pressed="false"
              >
                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
              </button>
              <button
                class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center<?= empty($pseUiSettings['show_calendar']) ? ' d-none' : '' ?>"
                id="toggleCalendar"
                type="button"
                title="Show monthly email calendar"
                aria-label="Show monthly email calendar"
                aria-pressed="false"
              >
                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="previousPage" title="Previous page">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <span
                class="small text-secondary pse-page-jump"
                id="pageLabel"
                title="Click the current page number to jump to another page"
              >
                <button class="pse-page-number" id="currentPageNumber" type="button" aria-label="Jump to page 1">1</button>
                <span aria-hidden="true">/</span>
                <span id="totalPageNumber">1</span>
              </span>
              <button class="btn btn-sm btn-outline-secondary" id="nextPage" title="Next page">
                <i class="fa-solid fa-chevron-right"></i>
              </button>
              <button class="btn btn-sm btn-outline-primary" id="toggleMultiSelect" title="Enable multiple selection">
                <i class="fa-regular fa-square-check"></i>
              </button>
            </div>
            <div class="d-none align-items-center gap-1 mt-2" id="bulkActions">
              <button class="btn btn-sm btn-outline-secondary" id="bulkSelectAll" title="Select all on this page">
                <i class="fa-solid fa-check-double"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="bulkSelectAllPages" title="Select all emails in all pages">
                <i class="fa-solid fa-layer-group"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="bulkClear" title="Clear selection">
                <i class="fa-solid fa-xmark"></i>
              </button>
              <span class="small text-secondary flex-grow-1" id="bulkCount">0 selected</span>
              <button class="btn btn-sm btn-outline-secondary" id="bulkRead" title="Mark selected as read">
                <i class="fa-regular fa-envelope-open"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="bulkUnread" title="Mark selected as unread">
                <i class="fa-regular fa-envelope"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary" id="bulkForward" title="Forward selected emails">
                <i class="fa-solid fa-share"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" id="bulkDelete" title="Move selected to Trash">
                <i class="fa-solid fa-trash"></i>
              </button>
              <button class="btn btn-sm btn-outline-primary d-none" id="bulkRestore" title="Restore selected to Inbox">
                <i class="fa-solid fa-trash-arrow-up"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger d-none" id="bulkDeleteForever" title="Delete selected forever">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </div>
          </div>
          <div class="pse-scroll" id="messagesList">
            <div class="pse-empty"><div><i class="fa-regular fa-envelope fa-2x mb-2"></i><br>Select a folder</div></div>
          </div>
          <div
            class="pse-page-pull-indicator"
            id="messagePullIndicator"
            role="status"
            aria-live="polite"
            aria-hidden="true"
          >
            <i class="fa-solid fa-chevron-up" id="messagePullIcon"></i>
            <span id="messagePullText">Pull up for next page</span>
          </div>
        </div>
      </section>

      <div class="pse-resizer" id="resizer2" role="separator" aria-orientation="vertical"></div>

      <section class="pse-panel" id="previewPanel">
        <div class="pse-panel-inner" id="previewContent">
          <div class="pse-empty">
            <div><i class="fa-regular fa-envelope-open fa-3x mb-3"></i><br>Select an email to preview it</div>
          </div>
        </div>
      </section>
    </main>

    <footer class="pse-footer">
      <button class="pse-footer-action pse-mobile-pane-arrow" id="mobilePaneBack" type="button" title="Previous mobile pane" aria-label="Previous mobile pane" disabled><i class="fa-solid fa-chevron-left"></i></button>
      <span><span class="pse-status-dot" id="connectionDot"></span><span class="pse-footer-label" id="connectionText">Not connected</span></span>
      <button class="pse-footer-action" id="footerFolderAction" title="Show all messages in this folder"><i class="fa-regular fa-folder me-1"></i><span class="pse-footer-label" id="statFolder">Inbox</span></button>
      <button class="pse-footer-action" id="footerMessagesAction" title="Show all messages"><i class="fa-regular fa-envelope me-1"></i><span id="statMessages">0</span><span class="pse-footer-label"> messages</span></button>
      <button class="pse-footer-action" id="footerUnreadAction" title="Show only unread messages"><i class="fa-solid fa-envelope-circle-check me-1"></i><span id="statUnread">0</span><span class="pse-footer-label"> unread</span></button>
      <button class="pse-footer-action" id="footerContactsAction" title="Open contacts"><i class="fa-solid fa-address-book me-1"></i><span id="statContacts">0</span><span class="pse-footer-label"> contacts</span></button>
      <button class="pse-footer-action" id="footerQueueAction" title="No queued deletions to undo" disabled><i class="fa-solid fa-list-check me-1"></i><span id="statQueue">0</span><span class="pse-footer-label"> queued</span></button>
      <button class="ms-auto pse-footer-version" id="footerVersionCheck" type="button" title="Check GitHub for a newer version">PSE <?= PSE_VERSION ?></button>
      <button class="pse-footer-action pse-mobile-pane-arrow" id="mobilePaneForward" type="button" title="Next mobile pane" aria-label="Next mobile pane"><i class="fa-solid fa-chevron-right"></i></button>
    </footer>
    <div class="pse-mobile-swipe-hint" id="mobileSwipeBackHint" aria-hidden="true">
      <i class="fa-solid fa-arrow-left"></i>
    </div>
  </div>

  <div class="pse-spinner" id="globalSpinner">
    <div class="pse-spinner-box">
      <div class="spinner-border mb-2" role="status"></div>
      <div id="spinnerText">Working…</div>
    </div>
  </div>
  <div class="pse-recipient-suggestions d-none" id="recipientSuggestions" aria-label="Matching contacts">
    <div class="pse-recipient-suggestions-header">
      <div class="fw-semibold flex-grow-1">
        Matching addresses
        <span class="badge text-bg-secondary ms-1" id="recipientSuggestionCount">0</span>
      </div>
      <button class="btn btn-sm btn-primary" id="recipientSuggestionsDone" type="button">Done</button>
    </div>
    <div class="pse-recipient-suggestions-list" id="recipientSuggestionsList"></div>
  </div>
  <div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">
            <i class="fa-solid fa-pen-to-square me-2 text-pse"></i><span id="composeTitleText">Compose email</span>
            <span class="d-inline-flex flex-wrap gap-1 ms-2 align-middle" id="composeRecipientTotals" aria-live="polite"></span>
          </h2>
          <div class="pse-compose-window-controls">
            <button
              type="button"
              class="btn btn-sm btn-link text-secondary p-1"
              id="composeMaximizeButton"
              title="Maximize compose window"
              aria-label="Maximize compose window"
              aria-pressed="false"
            ><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
            <button type="button" class="btn-close" id="composeCloseButton" data-bs-dismiss="modal" title="Close"></button>
          </div>
        </div>
        <div class="modal-body">
          <input type="hidden" id="composePseId">
          <div class="row g-2 align-items-start mb-2">
            <div class="col-auto">
              <button class="btn btn-sm btn-outline-secondary recipient-picker mt-1" data-field="to" type="button">To:</button>
            </div>
            <div class="col">
              <div class="pse-recipient-area" id="toRecipients"></div>
            </div>
          </div>
          <div class="row g-2 align-items-start mb-2" id="ccRow">
            <div class="col-auto">
              <button class="btn btn-sm btn-outline-secondary recipient-picker mt-1" data-field="cc" type="button">Cc:</button>
            </div>
            <div class="col">
              <div class="pse-recipient-area" id="ccRecipients"></div>
            </div>
          </div>
          <div class="row g-2 align-items-start mb-2" id="bccRow">
            <div class="col-auto">
              <button class="btn btn-sm btn-outline-secondary recipient-picker mt-1" data-field="bcc" type="button">Bcc:</button>
            </div>
            <div class="col">
              <div class="pse-recipient-area" id="bccRecipients"></div>
            </div>
          </div>
          <div class="d-flex justify-content-end gap-3 mb-2">
            <button class="btn btn-link btn-sm p-0" type="button" id="toggleCc">Hide Cc</button>
            <button class="btn btn-link btn-sm p-0" type="button" id="toggleBcc">Hide Bcc</button>
          </div>
          <div class="input-group mb-2 pse-compose-editable">
            <span class="input-group-text">Subject</span>
            <input class="form-control" id="composeSubject">
          </div>
          <div class="btn-toolbar gap-1 border rounded-top p-1 bg-light pse-compose-editable pse-compose-format-sticky" role="toolbar">
            <button class="btn btn-sm btn-light compose-format" type="button" data-command="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
            <button class="btn btn-sm btn-light compose-format" type="button" data-command="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
            <button class="btn btn-sm btn-light compose-format" type="button" data-command="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
            <button class="btn btn-sm btn-light compose-format" type="button" data-command="createLink" title="Link"><i class="fa-solid fa-link"></i></button>
            <div class="dropdown d-inline-flex align-items-center">
              <button
                class="btn btn-sm btn-light d-inline-flex align-items-center gap-1"
                id="composeTextColorButton"
                type="button"
                data-bs-toggle="dropdown"
                data-bs-auto-close="true"
                aria-expanded="false"
                title="Text color"
              >
                <i class="fa-solid fa-font" aria-hidden="true"></i>
                <span class="d-none d-lg-inline">Text</span>
                <span class="pse-current-color" id="composeTextColorSwatch" aria-hidden="true"></span>
              </button>
              <div class="dropdown-menu p-3 pse-color-picker-menu" aria-labelledby="composeTextColorButton">
                <div class="small fw-semibold mb-2">Text color</div>
                <div class="pse-color-grid" id="composeTextColorPalette" role="group" aria-label="Common text colors"></div>
                <hr class="my-3">
                <button class="btn btn-sm btn-outline-secondary w-100" id="composeCustomTextColorButton" type="button">
                  <i class="fa-solid fa-sliders me-1" aria-hidden="true"></i>Pick from slider…
                </button>
                <input class="pse-native-color-input" id="composeTextColor" type="color" value="#202632" aria-label="Custom text color">
              </div>
            </div>
            <label class="btn btn-sm btn-light d-inline-flex align-items-center gap-1 mb-0" for="composeBackgroundColor" title="Text background color">
              <i class="fa-solid fa-fill-drip" aria-hidden="true"></i>
              <span class="d-none d-lg-inline">Background</span>
              <input class="form-control form-control-color form-control-sm" id="composeBackgroundColor" type="color" value="#fff2a8" aria-label="Text background color" style="width:28px;height:25px;padding:2px">
            </label>
            <select class="form-select form-select-sm" id="composeFontSize" title="Font size" aria-label="Font size" style="width:auto">
              <option value="">Size</option>
              <option value="10">10 px</option>
              <option value="12">12 px</option>
              <option value="14">14 px</option>
              <option value="16">16 px</option>
              <option value="18">18 px</option>
              <option value="24">24 px</option>
              <option value="32">32 px</option>
            </select>
            <div class="dropdown d-inline-flex align-items-center">
              <button
                class="btn btn-sm btn-light d-inline-flex align-items-center justify-content-center"
                id="composeEmojiButton"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Insert emoticon"
                aria-label="Insert emoticon"
              ><i class="fa-regular fa-face-smile"></i></button>
              <div class="dropdown-menu p-2 pse-emoji-menu" aria-labelledby="composeEmojiButton">
                <div class="pse-emoji-grid" id="composeEmojiGrid"></div>
              </div>
            </div>
            <button class="btn btn-sm btn-light" id="insertImageButton" type="button" title="Insert image"><i class="fa-regular fa-image"></i></button>
            <button class="btn btn-sm btn-light compose-format" type="button" data-command="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
            <input type="file" id="composeImageInput" accept="image/*" hidden>
          </div>
          <div
            class="form-control rounded-top-0 pse-compose-body pse-compose-editable"
            id="composeBody"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
          ></div>
          <div class="pse-image-progress pse-compose-editable" id="imageUploadProgress">
            <div class="d-flex justify-content-between small mb-1">
              <span id="imageProgressLabel">Adding image…</span>
              <span id="imageProgressPercent">0%</span>
            </div>
            <div class="progress" style="height:7px">
              <div class="progress-bar progress-bar-striped progress-bar-animated" id="imageProgressBar" style="width:0%"></div>
            </div>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pse-compose-editable">
            <label class="btn btn-sm btn-outline-secondary mb-0">
              <i class="fa-solid fa-paperclip me-1"></i>Attachments
              <input type="file" id="composeAttachments" multiple hidden>
            </label>
            <div class="pse-file-list flex-grow-1" id="attachmentList">No attachments</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger me-auto" id="deleteComposeForever" type="button">
            <i class="fa-solid fa-trash me-1"></i>Delete forever
          </button>
          <button class="btn btn-outline-primary d-none" id="savePse" type="button">
            <i class="fa-solid fa-floppy-disk me-1"></i>Save draft
          </button>
          <button class="btn btn-primary" id="sendEmail">
            <i class="fa-solid fa-paper-plane me-1"></i>Send
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="recipientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Select contacts for <span id="recipientFieldLabel">To</span></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="input-group mb-3 sticky-top bg-white py-1">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input class="form-control" id="recipientSearch" placeholder="Search name or email…" autocomplete="off">
          </div>
          <div id="recipientContacts"></div>
        </div>
        <div class="modal-footer">
          <span class="me-auto text-secondary" id="recipientSelectedCount">0 selected</span>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="applyRecipients">Add selected</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="contactsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">
            <i class="fa-solid fa-address-book me-2 text-pse"></i>Contacts
            <span class="badge text-bg-secondary ms-2" id="contactsModalCount">0 contacts</span>
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4"><input class="form-control" id="contactName" placeholder="Displayed name"></div>
            <div class="col-md-5"><input class="form-control" id="contactEmail" type="email" placeholder="email@example.com"></div>
            <div class="col-md-3 d-grid"><button class="btn btn-primary" id="addContact"><i class="fa-solid fa-plus me-1"></i>Add contact</button></div>
          </div>
          <div class="border rounded p-3 mb-3 bg-light">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <label class="btn btn-outline-secondary mb-0">
                <i class="fa-solid fa-file-csv me-1"></i>Choose CSV
                <input type="file" id="contactsCsv" accept=".csv,text/csv" hidden>
              </label>
              <button class="btn btn-outline-primary" id="importContacts" disabled>Import CSV</button>
              <button class="btn btn-outline-success" id="exportContacts">
                <i class="fa-solid fa-file-export me-1"></i>Export all
              </button>
              <span class="small text-secondary">Required columns: <b>Displayed name</b> and <b>email</b>.</span>
            </div>
            <div class="small mt-2" id="csvFileName"></div>
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input class="form-control" id="contactsSearch" placeholder="Search contacts…">
          </div>
          <div id="contactsList"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="savedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5"><i class="fa-solid fa-file-pen me-2 text-pse"></i>Saved drafts (.PSE) <span class="badge text-bg-secondary ms-1" id="savedModalCount">0</span></h2>
          <button type="button" class="btn btn-sm btn-outline-danger ms-auto me-2" id="deleteAllSaved" disabled>
            <i class="fa-solid fa-trash-can me-1"></i>Delete all
          </button>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2 small">
            Drafts are stored by this application on the server. Reopen one here to continue editing it.
          </div>
          <div id="savedList"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="unknownContactsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5"><i class="fa-solid fa-user-plus me-2 text-pse"></i>Add new recipients to contacts?</h2>
          <button type="button" class="btn-close" id="cancelUnknownContacts"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary">
            These email addresses are not in Contacts. Enter an optional displayed name, add them, or skip this step.
          </p>
          <div id="unknownContactsList"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary me-auto" id="skipUnknownContacts">Skip and send</button>
          <button class="btn btn-primary" id="addUnknownContacts">
            <i class="fa-solid fa-user-plus me-1"></i>Add contacts and send
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="readContactSuggestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h2 class="modal-title fs-6">
            <i class="fa-solid fa-address-card me-2 text-pse"></i>Add to contacts?
            <span class="badge text-bg-secondary ms-1" id="readContactSuggestionCount">0</span>
          </h2>
          <button type="button" class="btn-close" id="closeReadContactSuggestions" aria-label="Close"></button>
        </div>
        <div class="modal-body py-2">
          <div class="small text-secondary mb-2">
            These sender or Cc addresses are not in Contacts. Select the ones you want to save.
          </div>
          <div id="readContactSuggestionList"></div>
          <div class="form-check border-top pt-2 mt-2">
            <input class="form-check-input" id="disableReadContactSuggestions" type="checkbox">
            <label class="form-check-label small" for="disableReadContactSuggestions">
              Do not ask anymore <span class="text-secondary">(re-enable in Settings)</span>
            </label>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button class="btn btn-sm btn-outline-secondary me-auto" id="skipReadContactSuggestions">Not now</button>
          <button class="btn btn-sm btn-primary" id="addReadContactSuggestions">
            <i class="fa-solid fa-user-plus me-1"></i>Add selected
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5"><i class="fa-solid fa-gear me-2 text-pse"></i>Settings</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="card border-primary-subtle mb-3">
            <div class="card-body">
              <div class="row g-2 align-items-end">
                <div class="col-lg-3">
                  <label class="form-label fw-semibold" for="settingsAccountSelect">Email account</label>
                  <select class="form-select" id="settingsAccountSelect"></select>
                </div>
                <div class="col-lg-3">
                  <label class="form-label fw-semibold" for="account_name">Account name</label>
                  <input class="form-control setting" id="account_name" maxlength="80" placeholder="Work email">
                </div>
                <div class="col-lg-3">
                  <label class="form-label fw-semibold" for="account_type">Account type</label>
                  <select class="form-select setting" id="account_type">
                    <option value="imap">Regular IMAP</option>
                    <option value="gmail">Gmail / Google Workspace (OAuth2)</option>
                  </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                  <button class="btn btn-outline-primary flex-grow-1" id="addEmailAccount" type="button">
                    <i class="fa-solid fa-plus me-1"></i>Add account
                  </button>
                  <button class="btn btn-outline-danger" id="deleteEmailAccount" type="button">
                    <i class="fa-solid fa-trash me-1"></i>Delete
                  </button>
                </div>
                <div class="col-12">
                  <div class="form-text">The selected account and its connection, identity, and interface settings are remembered.</div>
                </div>
              </div>
            </div>
          </div>
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#imapTab">Connection</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#smtpTab">Sending &amp; identity</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#appearanceTab">Appearance</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#storageTab">Offline storage</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#securityTab">Security</button></li>
          </ul>
          <div class="tab-content border border-top-0 rounded-bottom p-3">
            <div class="tab-pane fade show active" id="imapTab">
              <div class="row g-3 regular-imap-settings">
                <div class="col-md-6"><label class="form-label">IMAP host</label><input class="form-control setting" id="imap_host"></div>
                <div class="col-md-2"><label class="form-label">Port</label><input class="form-control setting" id="imap_port" type="number"></div>
                <div class="col-md-4"><label class="form-label">Encryption</label><select class="form-select setting" id="imap_encryption"><option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none">None</option></select></div>
                <div class="col-md-6"><label class="form-label">Username / full email</label><input class="form-control setting" id="imap_username" autocomplete="username"></div>
                <div class="col-md-6"><label class="form-label">Password / App Password</label><input class="form-control setting" id="imap_password" type="password" placeholder="Leave blank to keep saved password" autocomplete="new-password"></div>
                <div class="col-12 form-check ms-2"><input class="form-check-input setting" id="imap_validate_cert" type="checkbox"><label class="form-check-label" for="imap_validate_cert">Validate SSL certificate</label></div>
                <div class="col-12"><button class="btn btn-outline-primary" id="testImap"><i class="fa-solid fa-plug me-1"></i>Test IMAP</button></div>
              </div>
              <div class="gmail-account-settings d-none" id="googleOAuthPanel">
                <div class="alert alert-info">
                  <div class="fw-semibold mb-1"><i class="fa-brands fa-google me-2"></i>Google OAuth2 connection</div>
                  This account uses the Gmail API for Gmail and Google Workspace mail. Create an OAuth 2.0 Web application in Google Cloud, enable the Gmail API, and register the exact redirect URI shown below.
                  <div class="mt-2">
                    <b>Important:</b> for an External OAuth app, set the OAuth consent screen publishing status to
                    <b>In production</b> before connecting. In Testing, Google normally expires refresh tokens after 7 days.
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label" for="google_client_id">Google OAuth Client ID</label>
                    <input class="form-control setting" id="google_client_id" autocomplete="off" placeholder="…apps.googleusercontent.com">
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label" for="google_client_secret">Google OAuth Client Secret</label>
                    <input class="form-control setting" id="google_client_secret" type="password" autocomplete="new-password">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="google_redirect_uri">Authorized redirect URI</label>
                    <div class="input-group">
                      <input class="form-control font-monospace" id="google_redirect_uri" readonly>
                      <button class="btn btn-outline-secondary" id="copyGoogleRedirect" type="button" title="Copy redirect URI">
                        <i class="fa-regular fa-copy"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-secondary" id="googleConnectionStatus">Not connected</span>
                    <button class="btn btn-primary" id="connectGoogle" type="button">
                      <i class="fa-brands fa-google me-1"></i>Connect with Google
                    </button>
                    <button class="btn btn-outline-danger d-none" id="disconnectGoogle" type="button">
                      <i class="fa-solid fa-link-slash me-1"></i>Disconnect
                    </button>
                  </div>
                  <div class="col-12 form-check ms-2">
                    <input class="form-check-input setting" id="hide_useless_gmail_folders" type="checkbox">
                    <label class="form-check-label" for="hide_useless_gmail_folders">Hide useless Gmail folders</label>
                    <div class="form-text">Enabled by default. Currently hides YELLOW_STAR, Scheduled, and Notes, matching folder IDs and names case-insensitively.</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="smtpTab">
              <div class="row g-3">
                <div class="col-md-6 regular-imap-settings"><label class="form-label">SMTP host</label><input class="form-control setting" id="smtp_host"></div>
                <div class="col-md-2 regular-imap-settings"><label class="form-label">Port</label><input class="form-control setting" id="smtp_port" type="number"></div>
                <div class="col-md-4 regular-imap-settings"><label class="form-label">Encryption</label><select class="form-select setting" id="smtp_encryption"><option value="tls">STARTTLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>
                <div class="col-md-6 regular-imap-settings"><label class="form-label">SMTP username</label><input class="form-control setting" id="smtp_username" autocomplete="username"></div>
                <div class="col-md-6 regular-imap-settings"><label class="form-label">SMTP password / App Password</label><input class="form-control setting" id="smtp_password" type="password" placeholder="Leave blank to keep saved password" autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">From email</label><input class="form-control setting" id="from_email" type="email"></div>
                <div class="col-md-6"><label class="form-label">From name</label><input class="form-control setting" id="from_name"></div>
                <div class="col-md-6"><label class="form-label">Reply-To (optional)</label><input class="form-control setting" id="reply_to" type="email"></div>
                <div class="col-md-6 form-check mt-5 ps-5 regular-imap-settings"><input class="form-check-input setting" id="smtp_validate_cert" type="checkbox"><label class="form-check-label" for="smtp_validate_cert">Validate SSL certificate</label></div>
                <div class="col-12">
                  <label class="form-label" for="signature">HTML signature (optional)</label>
                  <textarea class="form-control setting font-monospace" id="signature" rows="5" spellcheck="false" placeholder="<table>...</table>"></textarea>
                  <div class="form-text">
                    Enter an HTML fragment. It is inserted directly and without escaping into the editor for new messages, replies, and reply-all, so it can be reviewed or edited before sending.
                  </div>
                </div>
                <div class="col-12 form-check ms-2 regular-imap-settings">
                  <input class="form-check-input setting" id="save_sent_via_imap" type="checkbox">
                  <label class="form-check-label" for="save_sent_via_imap">Ensure a copy is stored in Sent through IMAP</label>
                  <div class="form-text">The Message-ID is checked first so providers such as Gmail do not get duplicate Sent copies.</div>
                </div>
                <div class="col-12 regular-imap-settings"><button class="btn btn-outline-primary" id="testSmtp"><i class="fa-solid fa-plug me-1"></i>Test SMTP</button></div>
                <div class="col-12 gmail-account-settings d-none">
                  <div class="form-text">Messages are sent through the authorized Gmail API connection. The HTML signature above is included exactly as shown in the editor.</div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="appearanceTab">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Application title</label><input class="form-control setting" id="app_title"></div>
                <div class="col-md-3"><label class="form-label">Date format</label><select class="form-select setting" id="date_format"><option value="d-m-Y">DD-MM-YYYY</option><option value="m/d/Y">MM/DD/YYYY</option><option value="Y-m-d">YYYY-MM-DD</option><option value="d M Y">DD Mon YYYY</option></select></div>
                <div class="col-md-3"><label class="form-label">Time format</label><select class="form-select setting" id="time_format"><option value="H:i">24-hour</option><option value="g:i A">12-hour</option><option value="H:i:s">24-hour + seconds</option></select></div>
                <div class="col-12">
                  <label class="form-label">Application icon</label>
                  <div class="pse-app-icon-upload">
                    <img class="pse-app-icon-upload-preview" id="settingsAppIconPreview" src="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>" alt="Application icon">
                    <div class="pse-app-icon-upload-copy">
                      <div class="fw-semibold">icon.png</div>
                      <div class="form-text mt-0">Upload PNG, JPEG, WebP, or GIF up to 5 MB. Non-square images open a crop tool. The final icon is saved as a square PNG no larger than 256×256 and replaces icon.png.</div>
                    </div>
                    <button class="btn btn-outline-primary" id="chooseAppIcon" type="button"><i class="fa-solid fa-image me-1"></i>Change icon</button>
                    <input class="d-none" id="appIconFile" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                  </div>
                </div>
                <div class="col-12">
                  <div class="border rounded-3 p-3" id="updateSettingsCard">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                      <div class="fs-4 text-pse"><i class="fa-solid fa-arrows-rotate"></i></div>
                      <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">Application updates</div>
                        <div class="small text-secondary" id="updateSettingsStatus">Current version <?= htmlspecialchars(PSE_VERSION, ENT_QUOTES, 'UTF-8') ?> — checking GitHub when PSE starts.</div>
                      </div>
                      <button class="btn btn-sm btn-outline-primary" id="checkUpdatesNow" type="button"><i class="fa-solid fa-rotate me-1"></i>Check now</button>
                      <button class="btn btn-sm btn-primary d-none" id="installUpdateNow" type="button"><i class="fa-solid fa-download me-1"></i>Update now</button>
                    </div>
                    <div class="form-check mt-3 mb-0">
                      <input class="form-check-input setting" id="auto_update" type="checkbox">
                      <label class="form-check-label" for="auto_update">Automatically install new versions from the official GitHub repository</label>
                    </div>
                    <div class="form-text mt-2">Source: ziobit/PSE-Email-Client. Before replacing this PHP file, PSE downloads and validates the newer version and creates a backup under pse_data/updates.</div>
                  </div>
                </div>
                <div class="col-md-4"><label class="form-label">Timezone</label><input class="form-control setting" id="timezone" placeholder="Asia/Bangkok"></div>
                <div class="col-md-4"><label class="form-label">UI spacing</label><select class="form-select setting" id="density"><option value="ultra_compact">Ultra Compact</option><option value="compact">Compact</option><option value="medium">Medium</option><option value="large">Large</option></select></div>
                <div class="col-md-4"><label class="form-label">Emails per page</label><input class="form-control setting" id="items_per_page" type="number" min="10" max="200"></div>
                <div class="col-lg-8">
                  <label class="form-label" for="theme">Theme</label>
                  <select class="form-select setting" id="theme">
                    <option value="custom">Custom colors</option>
                    <optgroup label="Light themes">
                      <option value="alpine">Alpine Air</option>
                      <option value="sandstone">Sandstone</option>
                      <option value="lavender">Lavender Mist</option>
                      <option value="mint">Mint Desk</option>
                      <option value="rosepaper">Rose Paper</option>
                    </optgroup>
                    <optgroup label="Dark themes">
                      <option value="midnight">Midnight Blue</option>
                      <option value="graphite">Graphite</option>
                      <option value="oceanic">Oceanic</option>
                      <option value="aubergine">Aubergine</option>
                      <option value="forestnight">Forest Night</option>
                    </optgroup>
                  </select>
                </div>
                <div class="col-lg-4">
                  <label class="form-label">Theme preview</label>
                  <div class="d-flex border rounded overflow-hidden" id="themePreview" style="height:38px" aria-label="Selected theme colors"></div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Header search delay (seconds)</label>
                  <input class="form-control setting" id="search_delay_seconds" type="number" min="0" max="60" step="0.1">
                  <div class="form-text">Search starts after this much time has passed since the last typed character.</div>
                </div>
                <div class="col-12">
                  <div class="pse-pwa-settings-card p-3" id="pwaSettingsCard">
                    <div class="d-flex align-items-center gap-3">
                      <div class="pse-pwa-settings-icon"><img id="pwaSettingsIconPreview" src="<?= htmlspecialchars($pseIconHref, ENT_QUOTES, 'UTF-8') ?>" alt="PSE"></div>
                      <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">Install <?= htmlspecialchars((string)($pseSettings['app_title'] ?? 'PSE Email'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-secondary mt-1 d-flex align-items-center gap-2">
                          <span class="pse-pwa-status-dot" id="pwaSettingsStatusDot"></span>
                          <span id="pwaSettingsStatus">Checking installation status…</span>
                        </div>
                      </div>
                      <button class="btn btn-sm btn-primary pse-pwa-install-button" id="pwaSettingsInstallButton" type="button">
                        <i class="fa-solid fa-download me-1"></i><span>Install</span>
                      </button>
                    </div>
                    <div class="form-text mt-2 mb-0" id="pwaSettingsDetail">
                      Installed PSE runs in its own app window. The mailbox itself remains server-backed; the service worker does not cache private email content.
                    </div>
                  </div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="mobile_single_pane" type="checkbox">
                  <label class="form-check-label" for="mobile_single_pane">Single-pane mobile navigation</label>
                  <div class="form-text">Enabled by default. On phones, show one full-width screen at a time: folders → email list → email reader. Disable it to keep the existing separated panes on mobile.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="mobile_swipe_hint_seconds">Swipe-back arrow duration (seconds)</label>
                  <input class="form-control setting" id="mobile_swipe_hint_seconds" type="number" min="0" max="5" step="0.5">
                  <div class="form-text">How long the animated left arrow remains visible after opening the email list or an email. Set to 0 to disable the hint.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="smart_datetime" type="checkbox">
                  <label class="form-check-label" for="smart_datetime">Display smart date/time</label>
                  <div class="form-text">Shows relative times, “Yesterday at…”, and weekday names for recent messages.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="group_messages_by_day" type="checkbox">
                  <label class="form-check-label" for="group_messages_by_day">Separate the email list by day</label>
                  <div class="form-text">Disabled by default. Adds compact headings such as Today, Yesterday, and dates using the configured date format.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="email_preview_rows">Email preview rows in the message list</label>
                  <select class="form-select setting" id="email_preview_rows">
                    <option value="0">0 — No preview</option>
                    <option value="1">1 row</option>
                    <option value="2">2 rows</option>
                    <option value="3">3 rows</option>
                    <option value="4">4 rows</option>
                    <option value="5">5 rows</option>
                  </select>
                  <div class="form-text">IMAP accounts may take longer to synchronize because message text must be read for every listed email.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="show_attachment_pill" type="checkbox">
                  <label class="form-check-label" for="show_attachment_pill">Show an attachment pill in the email list</label>
                  <div class="form-text">Disabled by default. Shows a paperclip and the attachment count in the left-side message controls. Counts are stored in the persistent mailbox cache and reused on later synchronizations.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="show_list_trash" type="checkbox">
                  <label class="form-check-label" for="show_list_trash">Show Trash icon in the email list</label>
                  <div class="form-text">Disabled by default. Shows the Trash button on the left of every email row.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="show_list_size" type="checkbox">
                  <label class="form-check-label" for="show_list_size">Show message size in the email list</label>
                  <div class="form-text">Disabled by default. Shows the message size on the left of every email row.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="show_calendar" type="checkbox">
                  <label class="form-check-label" for="show_calendar">Show Calendar</label>
                  <div class="form-text">Disabled by default. Adds a calendar button above the email list. The monthly view uses message metadata only and replaces the normal preview pane while it is open.</div>
                </div>
                <div class="col-md-3"><label class="form-label">Header color</label><input class="form-control form-control-color setting" id="primary_color" type="color"></div>
                <div class="col-md-3"><label class="form-label">Accent color</label><input class="form-control form-control-color setting" id="accent_color" type="color"></div>
                <div class="col-md-3"><label class="form-label">Background</label><input class="form-control form-control-color setting" id="background_color" type="color"></div>
                <div class="col-md-3"><label class="form-label">Panels</label><input class="form-control form-control-color setting" id="panel_color" type="color"></div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="block_remote_images" type="checkbox">
                  <label class="form-check-label" for="block_remote_images">Block likely remote tracking images until explicitly loaded</label>
                  <div class="form-text">Tracking pixels remain blocked when ordinary remote images are loaded automatically.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="always_load_remote_images" type="checkbox">
                  <label class="form-check-label" for="always_load_remote_images">Always load ordinary remote images</label>
                  <div class="form-text">Disabled by default. Likely tracking images still follow the separate tracking-image setting above.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="show_image_attachments_inline" type="checkbox">
                  <label class="form-check-label" for="show_image_attachments_inline">Always show image attachments inside the email when possible</label>
                  <div class="form-text">Enabled by default. PNG, JPG and other browser-readable image attachments remain downloadable and are also previewed at the end of the message.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="suggest_unknown_read_contacts" type="checkbox">
                  <label class="form-check-label" for="suggest_unknown_read_contacts">Suggest adding unknown senders and Cc addresses when reading email</label>
                  <div class="form-text">Turn this on to re-enable suggestions after choosing “Do not ask anymore”. The add-users button remains available in an opened email.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="confirm_delete_messages" type="checkbox">
                  <label class="form-check-label" for="confirm_delete_messages">Ask for confirmation before moving email to Trash</label>
                  <div class="form-text">Enabled by default. Disable it to move messages to Trash immediately from both the list and the opened email.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input class="form-check-input setting" id="compose_save_drafts" type="checkbox">
                  <label class="form-check-label" for="compose_save_drafts">Enable Save draft in Compose</label>
                  <div class="form-text">Disabled by default. When enabled, Compose shows the Save draft button and automatically saves a changed message when the compose window is closed.</div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="storageTab">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                  <div class="text-secondary small">Total offline/cache space used on this server</div>
                  <div class="fs-3 fw-semibold" id="cacheUsageTotal">Calculating…</div>
                  <div class="small text-secondary" id="cacheUsageSummary"></div>
                </div>
                <button class="btn btn-outline-primary" id="refreshCacheUsage" type="button">
                  <i class="fa-solid fa-rotate me-1"></i>Recalculate
                </button>
              </div>
              <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Account</th>
                      <th class="text-end">Mailbox data</th>
                      <th class="text-end">Images &amp; attachments</th>
                      <th class="text-end">Total</th>
                      <th class="text-end">Files</th>
                    </tr>
                  </thead>
                  <tbody id="cacheUsageAccounts">
                    <tr><td colspan="5" class="text-center text-secondary py-4">Calculating cache usage…</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="form-text mt-2">
                This includes persistent mailbox lists and message bodies in <code>pse_data/mail_cache</code>, plus cached images and attachments in <code>pse_data/cache</code>.
              </div>
            </div>
            <div class="tab-pane fade" id="securityTab">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">New application password</label>
                  <input class="form-control setting" id="new_app_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                </div>
                <div class="col-12">
                  <div class="border rounded p-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <button class="btn btn-outline-warning" id="forceRefreshFolderNames" type="button">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Force refresh folder names
                      </button>
                      <span class="small text-secondary" id="forceRefreshFolderNamesStatus"></span>
                    </div>
                    <div class="form-text mt-2">
                      Reads the folder list directly from the mail server, replaces the cached folder names, and removes list/calendar cache entries for folders that no longer exist. Cached email bodies and attachments are preserved.
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="alert alert-secondary mb-0">
                    Login is remembered in an HttpOnly cookie for up to <?= PSE_COOKIE_YEARS ?> years and remains valid until Logout. Use HTTPS on public servers.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="saveSettings"><i class="fa-solid fa-floppy-disk me-1"></i>Save settings</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
  <script>
    (() => {
      'use strict';

      const csrf = <?= json_encode($pseCsrfToken) ?>;
      let appIconVersion = <?= json_encode((string)$pseIconVersion) ?>;
      const appIconUrl = () => `icon.png?v=${encodeURIComponent(appIconVersion || Date.now())}`;
      const serverSettings = <?= json_encode($pseUiSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      const appearanceSettingKeys = [
        'app_title', 'date_format', 'time_format', 'timezone', 'density', 'items_per_page', 'theme',
        'search_delay_seconds', 'smart_datetime', 'group_messages_by_day', 'email_preview_rows',
        'show_attachment_pill', 'show_list_trash', 'show_list_size', 'show_calendar',
        'primary_color', 'accent_color', 'background_color', 'panel_color', 'block_remote_images',
        'always_load_remote_images', 'show_image_attachments_inline', 'suggest_unknown_read_contacts',
        'confirm_delete_messages', 'compose_save_drafts', 'mobile_single_pane', 'mobile_swipe_hint_seconds'
      ];
      const appearanceStorageVersion = 'v1';

      function appearanceStorageKey(accountId = serverSettings.account_id) {
        return `pse_appearance_${appearanceStorageVersion}:${String(accountId || 'default')}`;
      }

      function pickAppearanceSettings(settings) {
        const appearance = {};
        appearanceSettingKeys.forEach(key => {
          if (key in settings) appearance[key] = settings[key];
        });
        return appearance;
      }

      function readStoredAppearance(accountId, fallbackSettings) {
        const key = appearanceStorageKey(accountId);
        try {
          const stored = JSON.parse(localStorage.getItem(key) || 'null');
          if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
            return pickAppearanceSettings({...fallbackSettings, ...stored});
          }
          const seeded = pickAppearanceSettings(fallbackSettings);
          localStorage.setItem(key, JSON.stringify(seeded));
          return seeded;
        } catch (error) {
          console.warn('Unable to read local Appearance settings.', error);
          return pickAppearanceSettings(fallbackSettings);
        }
      }

      function writeStoredAppearance(appearance, accountId = initialSettings.account_id) {
        try {
          localStorage.setItem(appearanceStorageKey(accountId), JSON.stringify(pickAppearanceSettings(appearance)));
        } catch (error) {
          console.warn('Unable to save local Appearance settings.', error);
        }
      }

      function effectiveSettingsFromServer(settings) {
        const accountId = settings.account_id || serverSettings.account_id;
        return {...settings, ...readStoredAppearance(accountId, settings)};
      }

      const initialSettings = effectiveSettingsFromServer(serverSettings);
      const uiThemes = <?= json_encode(pseThemes(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      const state = {
        folder: 'INBOX',
        folderName: 'Inbox',
        folders: [],
        folderCache: null,
        messages: [],
        messageCache: new Map(),
        messageDetailsCache: new Map(),
        selectedUid: null,
        currentMessage: null,
        multiSelect: false,
        selectedUids: new Set(),
        allPagesSelected: false,
        page: 1,
        pages: 1,
        search: '',
        senderFilter: '',
        attachmentFilter: 'all',
        unreadOnly: false,
        sortOrder: 'desc',
        startDate: '',
        calendarActive: false,
        calendarMonth: '',
        calendarCache: new Map(),
        calendarRequestSerial: 0,
        lastSearch: null,
        contacts: [],
        contactsLoaded: false,
        recipientField: 'to',
        recipientDraftEmails: new Set(),
        recipientSuggestionOpen: false,
        recipientSuggestionField: null,
        recipientSuggestionQuery: '',
        recipients: {to: [], cc: [], bcc: []},
        recipientActiveField: 'to',
        recipientDrag: null,
        composeMode: 'normal',
        bulkForwardUids: [],
        bulkForwardFolder: '',
        composeFiles: [],
        composeDirty: false,
        composeSignatureManaged: false,
        skipDraftOnClose: false,
        composeCloseConfirming: false,
        composeRange: null,
        unknownContactResolver: null,
        readContactPromptOpen: false,
        readContactPrompted: new Set(),
        currentUnknownReadContacts: [],
        lastSyncDisplay: null,
        accountReloadPending: false,
        hardSyncing: false,
        googleReconnectPromptOpen: false,
        googleReconnectPromptDismissed: false,
        folderStatusPolling: false,
        lastFolderStatusCheck: 0,
        staleFolders: new Set(),
        newMailFolders: new Set(),
        messageLoads: 0,
        messageRequestSerial: 0,
        messageOpenSerial: 0,
        messageOpenRequests: new Map(),
        prefetchGeneration: 0,
        prefetchApiSerial: 0,
        prefetchStatusSerial: 0,
        prefetchPauseReasons: new Set(),
        prefetchQueue: [],
        prefetchJobs: new Map(),
        prefetchRunning: null,
        prefetchPumping: false,
        prefetchWakeTimer: null,
        prefetchStatusController: null,
        prefetchForegroundKeys: new Set(),
        prefetchAuthStopped: false,
        prefetchMaxMessageBytes: <?= (int)PSE_PREFETCH_MAX_MESSAGE_BYTES ?>,
        queueFlushing: false,
        queueUndoing: false,
        queuePersisting: 0,
        pendingQueue: Math.max(0, Number(initialSettings.queue_pending || 0)),
        lastActivity: Date.now(),
        lastHardSync: Date.now(),
        busy: 0,
        sidebarSpaceLoaded: false,
        viewMode: 'columns',
        mobilePane: 'folders',
        pullPaging: false
      };

      const $ = (selector, root = document) => root.querySelector(selector);
      const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
      const composeModal = new bootstrap.Modal('#composeModal');
      const recipientModal = new bootstrap.Modal('#recipientModal');
      const contactsModal = new bootstrap.Modal('#contactsModal');
      const savedModal = new bootstrap.Modal('#savedModal');
      const unknownContactsModal = new bootstrap.Modal('#unknownContactsModal', {
        backdrop: 'static',
        keyboard: false
      });
      const readContactSuggestionModal = new bootstrap.Modal('#readContactSuggestionModal', {
        backdrop: 'static'
      });
      const settingsModal = new bootstrap.Modal('#settingsModal');

      function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[char]);
      }

      function highlightSearchText(value, term = state.search) {
        const text = String(value ?? '');
        const needle = String(term || '').trim();
        if (!needle) return escapeHtml(text);
        const lowerText = text.toLocaleLowerCase();
        const lowerNeedle = needle.toLocaleLowerCase();
        let cursor = 0;
        let html = '';
        let index = lowerText.indexOf(lowerNeedle, cursor);
        while (index !== -1) {
          html += escapeHtml(text.slice(cursor, index));
          html += `<mark>${escapeHtml(text.slice(index, index + needle.length))}</mark>`;
          cursor = index + needle.length;
          index = lowerText.indexOf(lowerNeedle, cursor);
        }
        return html + escapeHtml(text.slice(cursor));
      }

      function formatBytes(bytes) {
        bytes = Number(bytes || 0);
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
      }

      function formatCompactBytes(bytes) {
        bytes = Math.max(0, Number(bytes || 0));
        if (bytes < 1024) return `${Math.round(bytes)}b`;
        if (bytes < 1048576) {
          const value = bytes / 1024;
          return `${value < 10 ? value.toFixed(1) : Math.round(value)}k`;
        }
        if (bytes < 1073741824) {
          const value = bytes / 1048576;
          return `${value < 10 ? value.toFixed(1) : Math.round(value)}M`;
        }
        const value = bytes / 1073741824;
        return `${value < 10 ? value.toFixed(1) : Math.round(value)}G`;
      }

      function showSpinner(message = 'Working…') {
        state.busy++;
        $('#spinnerText').textContent = message;
        $('#globalSpinner').classList.add('show');
      }

      function hideSpinner() {
        state.busy = Math.max(0, state.busy - 1);
        if (state.busy === 0) $('#globalSpinner').classList.remove('show');
      }

      function toast(message, type = 'success') {
        const icons = {danger: 'error', error: 'error', warning: 'warning', info: 'info', success: 'success'};
        Swal.fire({
          toast: true,
          position: 'bottom-end',
          icon: icons[type] || 'success',
          title: String(message),
          showConfirmButton: false,
          timer: 4800,
          timerProgressBar: true
        });
      }

      function activeSwalTarget() {
        const visibleModals = $$('.modal.show');
        return visibleModals[visibleModals.length - 1] || document.body;
      }

      async function swalConfirm(title, text = '', confirmText = 'Confirm') {
        const result = await Swal.fire({
          target: activeSwalTarget(),
          title,
          text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: confirmText,
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          confirmButtonColor: initialSettings.primary_color || '#1769aa'
        });
        return result.isConfirmed;
      }

      async function swalTypedConfirmation(
        title,
        text,
        confirmText = 'Delete',
        required = 'YES I AM SURE'
      ) {
        const result = await Swal.fire({
          target: activeSwalTarget(),
          title,
          text,
          icon: 'warning',
          input: 'text',
          inputLabel: `Type ${required} to continue`,
          inputPlaceholder: required,
          showCancelButton: true,
          confirmButtonText: confirmText,
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          confirmButtonColor: '#dc3545',
          inputValidator: value => value === required ? undefined : `You must type exactly ${required}.`
        });
        return result.isConfirmed ? required : null;
      }

      async function swalUrlPrompt() {
        const result = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Insert link',
          input: 'url',
          inputValue: 'https://',
          inputLabel: 'Link URL',
          showCancelButton: true,
          confirmButtonText: 'Insert',
          inputValidator: value => value ? undefined : 'Enter a URL.'
        });
        return result.isConfirmed ? result.value : null;
      }

      function invalidResponseMessage(raw, response) {
        const cleaned = String(raw || '')
          .replace(/<script[\s\S]*?<\/script>/gi, ' ')
          .replace(/<style[\s\S]*?<\/style>/gi, ' ')
          .replace(/<[^>]+>/g, ' ')
          .replace(/\s+/g, ' ')
          .trim();
        if (cleaned) {
          return `Server returned a non-JSON response (HTTP ${response.status}): ${cleaned.slice(0, 320)}`;
        }
        return `Server returned an empty response (HTTP ${response.status}). Check the PHP error log and request-size limits.`;
      }

      async function api(action, data = {}, options = {}) {
        const show = options.spinner !== false;
        const prefetchPauseReason = options.background
          ? ''
          : `api-${++state.prefetchApiSerial}`;
        if (prefetchPauseReason) {
          pausePrefetch(prefetchPauseReason, options.interruptPrefetch !== false);
        }
        if (show) showSpinner(options.spinnerText || 'Working…');
        try {
          const fetchOptions = {
            method: 'POST',
            headers: {'X-PSE-CSRF': csrf}
          };
          if (options.keepalive) {
            fetchOptions.keepalive = true;
          }
          if (options.signal) {
            fetchOptions.signal = options.signal;
          }
          const appearance = pickAppearanceSettings(initialSettings);
          if (data instanceof FormData) {
            if (!data.has('_appearance')) data.set('_appearance', JSON.stringify(appearance));
            fetchOptions.body = data;
          } else {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify({...data, _appearance: appearance});
          }
          const response = await fetch('?ajax=' + encodeURIComponent(action), fetchOptions);
          if (options.blob) {
            if (!response.ok) {
              const raw = await response.text();
              let errorText;
              let googleReconnectRequired = false;
              try {
                const json = JSON.parse(raw);
                errorText = json.error || 'Download failed.';
                googleReconnectRequired = Boolean(json.googleReconnectRequired);
              } catch (ignore) {
                errorText = invalidResponseMessage(raw, response);
              }
              const requestError = new Error(errorText);
              requestError.googleReconnectRequired = googleReconnectRequired;
              requestError.httpStatus = response.status;
              if (googleReconnectRequired) promptGoogleReconnect(errorText);
              throw requestError;
            }
            return response.blob();
          }
          const raw = await response.text();
          let result;
          try {
            result = JSON.parse(raw);
          } catch (error) {
            const invalidResponseError = new Error(invalidResponseMessage(raw, response));
            invalidResponseError.httpStatus = response.status;
            throw invalidResponseError;
          }
          if (result.authRequired) {
            location.reload();
            throw new Error('Authentication required.');
          }
          const googleReconnectRequired = Boolean(
            result.googleReconnectRequired || result.cache?.googleReconnectRequired
          );
          if (googleReconnectRequired) {
            stopPrefetchForAuthentication();
            promptGoogleReconnect(result.error || result.cache?.refreshError || 'Reconnect Google.');
          }
          if (!response.ok || !result.ok) {
            const requestError = new Error(result.error || 'Request failed.');
            requestError.googleReconnectRequired = googleReconnectRequired;
            requestError.httpStatus = response.status;
            throw requestError;
          }
          return result;
        } finally {
          if (show) hideSpinner();
          if (prefetchPauseReason) {
            resumePrefetch(prefetchPauseReason);
          }
        }
      }

      function handleError(error) {
        console.error(error);
        if (error?.googleReconnectRequired) {
          promptGoogleReconnect(error.message || 'Reconnect Google.');
          return;
        }
        toast(error.message || String(error), 'danger');
      }

      async function promptGoogleReconnect(message = '') {
        if (
          initialSettings.account_type !== 'gmail' ||
          state.googleReconnectPromptOpen ||
          state.googleReconnectPromptDismissed
        ) return;
        state.googleReconnectPromptOpen = true;
        initialSettings.google_reconnect_required = true;
        try {
          const result = await Swal.fire({
            target: activeSwalTarget(),
            icon: 'warning',
            title: 'Reconnect Google',
            html: `
              <div class="text-start">
                <p>${escapeHtml(message || 'Google authorization has expired or been revoked.')}</p>
                <p class="mb-0"><b>If your OAuth consent screen is still in Testing, change it to In production before reconnecting.</b> Testing refresh tokens normally expire after 7 days.</p>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-brands fa-google me-1"></i>Reconnect now',
            cancelButtonText: 'Keep cached view'
          });
          if (result.isConfirmed) {
            await connectGoogle();
          } else {
            state.googleReconnectPromptDismissed = true;
          }
        } finally {
          state.googleReconnectPromptOpen = false;
        }
      }

      function prefetchMessageKey(folder, uid, accountId = initialSettings.account_id) {
        return [String(accountId || ''), String(folder || ''), String(uid || '')].join('|');
      }

      function prefetchConnectionAllowsBackground() {
        if (navigator.onLine === false) return false;
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection?.saveData) return false;
        return !['slow-2g', '2g'].includes(String(connection?.effectiveType || '').toLowerCase());
      }

      function interruptPrefetchRequests() {
        if (state.prefetchStatusController) {
          try { state.prefetchStatusController.abort(); } catch (error) {}
        }
        if (state.prefetchRunning?.controller) {
          try { state.prefetchRunning.controller.abort(); } catch (error) {}
        }
      }

      function pausePrefetch(reason, interrupt = false) {
        reason = String(reason || 'foreground');
        state.prefetchPauseReasons.add(reason);
        if (interrupt) interruptPrefetchRequests();
      }

      function resumePrefetch(reason) {
        state.prefetchPauseReasons.delete(String(reason || 'foreground'));
        schedulePrefetchPump(0);
      }

      function prefetchCanRun() {
        return !state.prefetchAuthStopped &&
          state.prefetchPauseReasons.size === 0 &&
          prefetchConnectionAllowsBackground() &&
          !state.hardSyncing &&
          !state.queueFlushing &&
          !state.queueUndoing &&
          state.queuePersisting === 0 &&
          state.messageLoads === 0 &&
          state.busy === 0;
      }

      function schedulePrefetchPump(delay = 0) {
        if (state.prefetchWakeTimer !== null) {
          clearTimeout(state.prefetchWakeTimer);
        }
        state.prefetchWakeTimer = setTimeout(() => {
          state.prefetchWakeTimer = null;
          pumpPrefetchQueue();
        }, Math.max(0, Number(delay || 0)));
      }

      function resolvePrefetchJob(job, outcome) {
        if (!job || job.settled) return;
        job.settled = true;
        job.state = outcome.ok ? 'done' : (outcome.cancelled ? 'cancelled' : 'failed');
        job.resolve(outcome);
        setTimeout(() => {
          if (state.prefetchJobs.get(job.key) === job) {
            state.prefetchJobs.delete(job.key);
          }
        }, outcome.ok ? 30000 : 1000);
      }

      function cancelQueuedPrefetchJob(job) {
        if (!job || job.state !== 'queued') return;
        job.cancelled = true;
        resolvePrefetchJob(job, {ok: false, cancelled: true});
      }

      function beginPrefetchView() {
        state.prefetchGeneration++;
        state.prefetchStatusSerial++;
        state.messageOpenSerial++;
        if (state.prefetchStatusController) {
          try { state.prefetchStatusController.abort(); } catch (error) {}
          state.prefetchStatusController = null;
        }
        const retained = [];
        for (const job of state.prefetchQueue) {
          if (job.generation === state.prefetchGeneration) {
            retained.push(job);
          } else {
            cancelQueuedPrefetchJob(job);
          }
        }
        state.prefetchQueue = retained;
        return state.prefetchGeneration;
      }

      function resetPrefetchState(resetAuthentication = true) {
        const keepAuthenticationPause = !resetAuthentication && state.prefetchAuthStopped;
        beginPrefetchView();
        for (const job of state.prefetchQueue) cancelQueuedPrefetchJob(job);
        state.prefetchQueue = [];
        state.prefetchForegroundKeys.clear();
        state.messageOpenRequests.clear();
        state.messageOpenSerial++;
        interruptPrefetchRequests();
        state.prefetchPauseReasons.clear();
        if (resetAuthentication) {
          state.prefetchAuthStopped = false;
        } else if (keepAuthenticationPause) {
          state.prefetchPauseReasons.add('authentication');
        }
      }

      function stopPrefetchForAuthentication() {
        state.prefetchAuthStopped = true;
        state.prefetchPauseReasons.add('authentication');
        state.prefetchStatusSerial++;
        interruptPrefetchRequests();
        for (const job of state.prefetchQueue) cancelQueuedPrefetchJob(job);
        state.prefetchQueue = [];
      }

      function prefetchErrorIsRetryable(error) {
        const status = Number(error?.httpStatus || 0);
        if (status === 429 || status >= 500) return true;
        const message = String(error?.message || error || '');
        return /HTTP\s+(?:429|5\d\d)|network|failed to fetch|timeout|temporar/i.test(message);
      }

      function prefetchRetryDelay(attempt) {
        return [1500, 4000, 10000][Math.max(0, Math.min(2, Number(attempt || 1) - 1))];
      }

      function enqueuePrefetchMessage(message, context, position) {
        const uid = String(message?.uid || '');
        if (!uid) return null;
        const size = Math.max(0, Number(message?.size || 0));
        if (size > state.prefetchMaxMessageBytes) return null;
        const key = prefetchMessageKey(context.folder, uid, context.accountId);
        if (state.prefetchForegroundKeys.has(key)) return null;

        const existing = state.prefetchJobs.get(key);
        if (existing && !existing.settled) {
          existing.generation = context.generation;
          existing.position = Math.min(existing.position, position);
          return existing;
        }

        let resolveJob;
        const promise = new Promise(resolve => {
          resolveJob = resolve;
        });
        const job = {
          key,
          accountId: context.accountId,
          folder: context.folder,
          uid,
          size,
          position,
          priority: 0,
          generation: context.generation,
          attempts: 0,
          state: 'queued',
          cancelled: false,
          settled: false,
          controller: null,
          promise,
          resolve: resolveJob
        };
        state.prefetchJobs.set(key, job);
        state.prefetchQueue.push(job);
        return job;
      }

      function sortPrefetchQueue() {
        state.prefetchQueue.sort((left, right) => {
          if (left.priority !== right.priority) return right.priority - left.priority;
          const leftLarge = left.size > 2097152 ? 1 : 0;
          const rightLarge = right.size > 2097152 ? 1 : 0;
          if (leftLarge !== rightLarge) return leftLarge - rightLarge;
          return left.position - right.position;
        });
      }

      async function pumpPrefetchQueue() {
        if (state.prefetchPumping || !prefetchCanRun()) return;
        state.prefetchPumping = true;
        try {
          while (prefetchCanRun()) {
            sortPrefetchQueue();
            let job = null;
            while (state.prefetchQueue.length) {
              const candidate = state.prefetchQueue.shift();
              if (!candidate || candidate.cancelled || candidate.settled) continue;
              if (candidate.generation !== state.prefetchGeneration && candidate.priority < 100) {
                cancelQueuedPrefetchJob(candidate);
                continue;
              }
              job = candidate;
              break;
            }
            if (!job) break;

            job.state = 'running';
            job.controller = new AbortController();
            state.prefetchRunning = job;
            try {
              const result = await api('prefetch_message', {
                accountId: job.accountId,
                folder: job.folder,
                uid: job.uid,
                expectedSize: job.size
              }, {
                spinner: false,
                background: true,
                signal: job.controller.signal
              });
              resolvePrefetchJob(job, {ok: true, result});
            } catch (error) {
              if (error?.googleReconnectRequired) {
                stopPrefetchForAuthentication();
                resolvePrefetchJob(job, {ok: false, error});
              } else if (error?.name === 'AbortError') {
                if (
                  !job.cancelled &&
                  !job.settled &&
                  (job.generation === state.prefetchGeneration || job.priority >= 100)
                ) {
                  job.state = 'queued';
                  job.controller = null;
                  state.prefetchQueue.unshift(job);
                } else {
                  resolvePrefetchJob(job, {ok: false, cancelled: true, error});
                }
              } else if (
                prefetchErrorIsRetryable(error) &&
                job.attempts < 3 &&
                (job.generation === state.prefetchGeneration || job.priority >= 100)
              ) {
                job.attempts++;
                job.state = 'retry-wait';
                await new Promise(resolve => setTimeout(resolve, prefetchRetryDelay(job.attempts)));
                if (!job.cancelled && !job.settled) {
                  job.state = 'queued';
                  job.controller = null;
                  state.prefetchQueue.unshift(job);
                }
              } else {
                console.warn('Background message prefetch failed.', error);
                resolvePrefetchJob(job, {ok: false, error});
              }
            } finally {
              job.controller = null;
              if (state.prefetchRunning === job) state.prefetchRunning = null;
            }
            if (prefetchCanRun() && state.prefetchQueue.length) {
              await new Promise(resolve => setTimeout(resolve, 300));
            }
          }
        } finally {
          state.prefetchPumping = false;
          if (state.prefetchQueue.length && prefetchCanRun()) {
            schedulePrefetchPump(300);
          }
        }
      }

      async function scheduleVisibleMessagePrefetch(generation, attempt = 0) {
        if (
          generation !== state.prefetchGeneration ||
          state.prefetchAuthStopped ||
          !prefetchConnectionAllowsBackground() ||
          !state.messages.length
        ) return;
        if (!prefetchCanRun()) {
          setTimeout(() => {
            scheduleVisibleMessagePrefetch(generation, attempt);
          }, 500);
          return;
        }
        const context = {
          generation,
          accountId: String(initialSettings.account_id || ''),
          folder: String(state.folder || '')
        };
        const visibleMessages = state.messages.map(message => ({
          uid: String(message.uid),
          size: Math.max(0, Number(message.size || 0))
        }));
        const statusSerial = ++state.prefetchStatusSerial;
        const controller = new AbortController();
        state.prefetchStatusController = controller;
        try {
          const result = await api('prefetch_status', {
            accountId: context.accountId,
            folder: context.folder,
            uids: visibleMessages.map(message => message.uid)
          }, {
            spinner: false,
            background: true,
            signal: controller.signal
          });
          if (
            statusSerial !== state.prefetchStatusSerial ||
            generation !== state.prefetchGeneration ||
            context.accountId !== String(initialSettings.account_id || '') ||
            context.folder !== String(state.folder || '')
          ) return;
          const missing = new Set((result.missing || []).map(String));
          visibleMessages.forEach((message, position) => {
            if (missing.has(message.uid)) {
              enqueuePrefetchMessage(message, context, position);
            }
          });
          schedulePrefetchPump(0);
        } catch (error) {
          if (error?.name === 'AbortError') return;
          if (error?.googleReconnectRequired) {
            stopPrefetchForAuthentication();
            return;
          }
          if (
            attempt < 2 &&
            prefetchErrorIsRetryable(error) &&
            generation === state.prefetchGeneration
          ) {
            setTimeout(() => {
              scheduleVisibleMessagePrefetch(generation, attempt + 1);
            }, prefetchRetryDelay(attempt + 1));
          } else {
            console.warn('Unable to check the visible-message cache in background.', error);
          }
        } finally {
          if (state.prefetchStatusController === controller) {
            state.prefetchStatusController = null;
          }
        }
      }

      async function promotePrefetchForForeground(folder, uid) {
        const key = prefetchMessageKey(folder, uid);
        const job = state.prefetchJobs.get(key);
        if (!job || job.settled) return null;
        if (job.state === 'queued') {
          if (!prefetchCanRun()) {
            state.prefetchQueue = state.prefetchQueue.filter(candidate => candidate !== job);
            cancelQueuedPrefetchJob(job);
            return null;
          }
          job.priority = 100;
          job.generation = state.prefetchGeneration;
          state.prefetchQueue = state.prefetchQueue.filter(candidate => candidate !== job);
          state.prefetchQueue.unshift(job);
          schedulePrefetchPump(0);
        }
        if (job.state === 'retry-wait') {
          job.cancelled = true;
          resolvePrefetchJob(job, {ok: false, cancelled: true});
          return null;
        }
        if (job.state === 'running' || job.state === 'queued') {
          job.priority = 100;
          const timeout = new Promise(resolve => {
            setTimeout(() => resolve({foregroundTimeout: true}), 20000);
          });
          const outcome = await Promise.race([job.promise, timeout]);
          if (outcome?.foregroundTimeout && !job.settled) {
            job.cancelled = true;
            try { job.controller?.abort(); } catch (error) {}
            resolvePrefetchJob(job, {ok: false, cancelled: true});
            return null;
          }
          return outcome;
        }
        return null;
      }

      async function fetchForegroundMessage(folder, uid, loadRemote) {
        const requestKey = messageDetailsCacheKey(folder, uid, loadRemote);
        const existingRequest = state.messageOpenRequests.get(requestKey);
        if (existingRequest) return await existingRequest;

        const prefetchKey = prefetchMessageKey(folder, uid);
        state.prefetchForegroundKeys.add(prefetchKey);
        const request = (async () => {
          await promotePrefetchForForeground(folder, uid);
          return await api('message', {
            folder,
            uid,
            loadRemote
          }, {spinnerText: 'Opening email…'});
        })();
        state.messageOpenRequests.set(requestKey, request);
        try {
          return await request;
        } finally {
          state.prefetchForegroundKeys.delete(prefetchKey);
          if (state.messageOpenRequests.get(requestKey) === request) {
            state.messageOpenRequests.delete(requestKey);
          }
        }
      }

      function folderIcon(special) {
        return {
          inbox: 'fa-inbox',
          sent: 'fa-paper-plane',
          drafts: 'fa-file-lines',
          archive: 'fa-box-archive',
          spam: 'fa-shield-halved',
          trash: 'fa-trash',
          folder: 'fa-folder'
        }[special] || 'fa-folder';
      }

      function messageCacheKey(
        folder = state.folder,
        page = state.page,
        search = state.search,
        senderFilter = state.senderFilter,
        unreadOnly = state.unreadOnly,
        sortOrder = state.sortOrder,
        attachmentFilter = state.attachmentFilter,
        startDate = state.startDate
      ) {
        return [
          initialSettings.account_id || 'account',
          folder,
          Math.max(1, Number(page) || 1),
          String(search || '').trim().toLowerCase(),
          String(senderFilter || '').trim().toLowerCase(),
          unreadOnly ? 'unread' : 'all',
          sortOrder === 'asc' ? 'asc' : 'desc',
          attachmentFilter === 'with' ? 'with' : 'all',
          String(startDate || '')
        ].join('|');
      }

      function calendarCacheKey(
        month = state.calendarMonth,
        folder = state.folder,
        search = state.search,
        senderFilter = state.senderFilter,
        unreadOnly = state.unreadOnly,
        attachmentFilter = state.attachmentFilter
      ) {
        return [
          initialSettings.account_id || 'account',
          folder,
          String(month || ''),
          String(search || '').trim().toLowerCase(),
          String(senderFilter || '').trim().toLowerCase(),
          unreadOnly ? 'unread' : 'all',
          attachmentFilter === 'with' ? 'with' : 'all'
        ].join('|');
      }

      function unreadPreferenceKey() {
        return `pse_unread_only_${initialSettings.account_id || 'account'}`;
      }

      function sortPreferenceKey() {
        return `pse_message_sort_${initialSettings.account_id || 'account'}`;
      }

      function attachmentFilterPreferenceKey() {
        return `pse_attachment_filter_${initialSettings.account_id || 'account'}`;
      }

      function lastSearchStorageKey() {
        return `pse_last_search_${initialSettings.account_id || 'account'}`;
      }

      function readLastSearch() {
        try {
          const saved = JSON.parse(localStorage.getItem(lastSearchStorageKey()) || 'null');
          if (
            !saved ||
            saved.accountId !== (initialSettings.account_id || 'account') ||
            !saved.search ||
            !saved.data ||
            !Array.isArray(saved.data.messages)
          ) {
            return null;
          }
          return saved;
        } catch (error) {
          return null;
        }
      }

      function currentFolderRecord() {
        return state.folders.find(folder => String(folder.id) === String(state.folder)) || null;
      }

      function currentFolderSpecial() {
        return String(currentFolderRecord()?.special || 'folder');
      }

      function currentFolderIsTrash() {
        return currentFolderSpecial() === 'trash';
      }

      function updateCurrentFolderHeading() {
        const viewDescription = [
          state.unreadOnly ? 'Unread only' : '',
          state.senderFilter ? `From: ${state.senderFilter}` : '',
          state.attachmentFilter === 'with' ? 'With attachments' : '',
          state.startDate ? `Starting ${formatIsoDateLabel(state.startDate)}` : '',
          state.search ? `“${state.search}”` : ''
        ].filter(Boolean).join(' — ');
        const label = viewDescription
          ? `${state.folderName} — ${viewDescription}`
          : state.folderName;
        const name = $('#currentFolderName');
        const button = $('#currentFolderSort');
        const icon = $('#currentFolderSortIcon');
        if (name) name.textContent = label;
        const ascending = state.sortOrder === 'asc';
        const action = ascending ? 'Sort newest to oldest' : 'Sort oldest to newest';
        if (button) {
          button.title = action;
          button.setAttribute('aria-label', `${label}. ${action}`);
        }
        if (icon) {
          icon.className = `fa-solid ${ascending ? 'fa-arrow-up' : 'fa-arrow-down'} small`;
        }
      }

      function selectedSenderEmail() {
        const sender = Array.isArray(state.currentMessage?.from)
          ? state.currentMessage.from.find(item => item && item.email)
          : null;
        const listSender = state.messages.find(
          message => String(message.uid) === String(state.selectedUid || '')
        );
        return String(sender?.email || listSender?.fromEmail || '').trim().toLowerCase();
      }

      function updateSameSenderFilterButton() {
        const button = $('#filterSameSender');
        if (!button) return;
        const selectedEmail = selectedSenderEmail();
        const active = Boolean(state.senderFilter);
        button.disabled = !active && !selectedEmail;
        button.classList.toggle('active', active);
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-outline-secondary', !active);
        button.setAttribute('aria-pressed', String(active));
        button.title = active
          ? `Show all senders (currently filtering ${state.senderFilter})`
          : (selectedEmail
            ? `Show only messages from ${selectedEmail}`
            : 'Select an email first, then show only messages from the same sender');
      }

      function updateAttachmentFilterButton() {
        const button = $('#filterAttachments');
        if (!button) return;
        const active = state.attachmentFilter === 'with';
        button.classList.toggle('btn-outline-secondary', !active);
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', String(active));
        button.title = active
          ? 'Show all emails'
          : 'Show only emails with attachments';
        button.setAttribute(
          'aria-label',
          active
            ? 'Attachment filter: emails with attachments. Click to show all emails.'
            : 'Attachment filter: all emails. Click to show emails with attachments.'
        );
      }

      function formatIsoDateLabel(date) {
        const match = String(date || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return String(date || '');
        const timestamp = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12, 0, 0) / 1000;
        return configuredDayLabel(timestamp);
      }

      function currentCalendarMonth() {
        const parts = zonedDateParts(Date.now() / 1000);
        const year = String(parts.year || new Date().getUTCFullYear());
        const month = String(parts.month || (new Date().getUTCMonth() + 1)).padStart(2, '0');
        return `${year}-${month}`;
      }

      function updateCalendarButton() {
        const button = $('#toggleCalendar');
        if (!button) return;
        const enabled = Boolean(initialSettings.show_calendar);
        button.classList.toggle('d-none', !enabled);
        button.classList.toggle('btn-outline-secondary', !state.calendarActive);
        button.classList.toggle('btn-primary', state.calendarActive);
        button.classList.toggle('active', state.calendarActive);
        button.setAttribute('aria-pressed', String(state.calendarActive));
        button.title = state.calendarActive ? 'Return to email preview' : 'Show monthly email calendar';
        button.setAttribute('aria-label', button.title);
      }

      function updateMailboxViewControls() {
        const unreadButton = $('#toggleUnreadOnly');
        unreadButton.classList.toggle('active', state.unreadOnly);
        unreadButton.classList.toggle('btn-outline-secondary', !state.unreadOnly);
        unreadButton.classList.toggle('btn-primary', state.unreadOnly);
        unreadButton.title = state.unreadOnly ? 'Show all messages' : 'Show only unread messages';
        unreadButton.setAttribute('aria-pressed', String(state.unreadOnly));
        unreadButton.innerHTML = state.unreadOnly
          ? '<i class="fa-solid fa-envelope-open"></i>'
          : '<i class="fa-regular fa-envelope"></i>';
        $('#restoreLastSearch').classList.toggle('d-none', !state.lastSearch);
        updateCurrentFolderHeading();
        updateSameSenderFilterButton();
        updateAttachmentFilterButton();
        updateCalendarButton();
      }

      function loadMailboxPreferences() {
        try {
          state.unreadOnly = localStorage.getItem(unreadPreferenceKey()) === '1';
          state.sortOrder = localStorage.getItem(sortPreferenceKey()) === 'asc' ? 'asc' : 'desc';
          const savedAttachmentFilter = localStorage.getItem(attachmentFilterPreferenceKey()) || 'all';
          state.attachmentFilter = savedAttachmentFilter === 'with' ? 'with' : 'all';
        } catch (error) {
          state.unreadOnly = false;
          state.sortOrder = 'desc';
          state.attachmentFilter = 'all';
        }
        state.lastSearch = readLastSearch();
        updateMailboxViewControls();
      }

      function saveLastSearch(data) {
        if (!state.search) return;
        const record = {
          accountId: initialSettings.account_id || 'account',
          folder: state.folder,
          folderName: state.folderName,
          search: state.search,
          senderFilter: state.senderFilter,
          attachmentFilter: state.attachmentFilter,
          page: state.page,
          unreadOnly: state.unreadOnly,
          sortOrder: state.sortOrder,
          startDate: state.startDate,
          savedAt: Date.now(),
          data: JSON.parse(JSON.stringify(data))
        };
        state.lastSearch = record;
        try {
          localStorage.setItem(lastSearchStorageKey(), JSON.stringify(record));
        } catch (error) {
          // The current result remains available for this tab if storage is unavailable.
        }
        updateMailboxViewControls();
      }

      function restoreLastSearchResults() {
        const saved = state.lastSearch || readLastSearch();
        if (!saved) return;
        const folder = state.folders.find(item => item.id === saved.folder);
        state.folder = folder ? folder.id : saved.folder;
        state.folderName = folder ? folder.name : (saved.folderName || saved.folder);
        state.search = saved.search;
        state.senderFilter = String(saved.senderFilter || '').trim().toLowerCase();
        state.attachmentFilter = saved.attachmentFilter === 'with' ? 'with' : 'all';
        state.unreadOnly = Boolean(saved.unreadOnly);
        state.sortOrder = saved.sortOrder === 'asc' ? 'asc' : 'desc';
        state.startDate = /^\d{4}-\d{2}-\d{2}$/.test(String(saved.startDate || ''))
          ? String(saved.startDate)
          : '';
        state.calendarActive = false;
        state.page = Math.max(1, Number(saved.page) || 1);
        state.selectedUid = null;
        state.currentMessage = null;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        try {
          localStorage.setItem(unreadPreferenceKey(), state.unreadOnly ? '1' : '0');
          localStorage.setItem(sortPreferenceKey(), state.sortOrder);
          localStorage.setItem(attachmentFilterPreferenceKey(), state.attachmentFilter);
        } catch (error) {
          // The restored filter and sorting still apply for this tab.
        }
        $('#globalSearch').value = state.search;
        $('#clearSearch').classList.remove('d-none');
        clearPreview();
        renderFolders();
        const data = JSON.parse(JSON.stringify(saved.data));
        data._folder = state.folder;
        data._search = state.search;
        data._senderFilter = state.senderFilter;
        data._attachmentFilter = state.attachmentFilter;
        data._unreadOnly = state.unreadOnly;
        data._sortOrder = state.sortOrder;
        data._startDate = state.startDate;
        state.messageCache.set(
          messageCacheKey(
            state.folder,
            state.page,
            state.search,
            state.senderFilter,
            state.unreadOnly,
            state.sortOrder,
            state.attachmentFilter,
            state.startDate
          ),
          data
        );
        const prefetchGeneration = beginPrefetchView();
        applyMessageData(data);
        scheduleVisibleMessagePrefetch(prefetchGeneration);
        updateMailboxViewControls();
        toast('Last search restored.', 'info');
      }

      function setUnreadOnlyView(enabled, clearSearch = false) {
        clearTimeout(searchTimer);
        searchTimer = null;
        resumePrefetch('search-typing');
        state.unreadOnly = Boolean(enabled);
        state.page = 1;
        state.selectedUid = null;
        state.currentMessage = null;
        state.multiSelect = false;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        if (clearSearch) {
          state.search = '';
          state.startDate = '';
          $('#globalSearch').value = '';
          $('#clearSearch').classList.add('d-none');
        }
        try {
          localStorage.setItem(unreadPreferenceKey(), state.unreadOnly ? '1' : '0');
        } catch (error) {
          // The setting still applies for this tab.
        }
        clearPreview();
        updateMailboxViewControls();
        return loadMessages(1);
      }

      function messageDetailsCacheKey(folder, uid, loadRemote = false) {
        return [
          initialSettings.account_id || 'account',
          folder,
          String(uid),
          loadRemote ? 'remote' : 'blocked'
        ].join('|');
      }

      function smartSyncTime(timestampSeconds) {
        const timestamp = Number(timestampSeconds || 0) * 1000;
        if (!timestamp) return 'unknown time';
        const now = Date.now();
        const difference = Math.max(0, now - timestamp);
        const date = new Date(timestamp);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date >= today && difference < 60000) return 'Now';
        if (date >= today && difference < 3600000) {
          const minutes = Math.max(1, Math.floor(difference / 60000));
          return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
        }
        if (date >= today) {
          const hours = Math.max(1, Math.floor(difference / 3600000));
          return `${hours} hour${hours === 1 ? '' : 's'} ago`;
        }
        if (date >= yesterday) return 'Yesterday';
        const days = Math.max(2, Math.floor(difference / 86400000));
        if (days < 7) return `${days} days ago`;
        return date.toLocaleString();
      }

      function refreshLastSyncStatus() {
        const element = $('#lastSyncStatus');
        const display = state.lastSyncDisplay;
        if (!element || !display) return;
        const cache = display.cache;
        const savedAt = Number(cache.savedAt || 0);
        const relative = smartSyncTime(savedAt);
        const exact = savedAt ? new Date(savedAt * 1000).toLocaleString() : 'unknown time';
        const refreshFailed = Boolean(cache.refreshError);
        element.textContent = refreshFailed
          ? `${display.prefix} synchronized ${relative} — refresh failed`
          : `${display.prefix} synchronized ${relative}`;
        element.title = refreshFailed
          ? `The server refresh failed, so the persistent cache is being shown. Last successful synchronization: ${exact}. ${cache.refreshError}`
          : (cache.cached
            ? `Loaded from the persistent cache. Last server synchronization: ${exact}`
            : `Loaded from the email server and saved permanently: ${exact}`);
      }

      function updateLastSyncStatus(cache, prefix = 'Mailbox') {
        if (!cache) return;
        state.lastSyncDisplay = {cache: {...cache}, prefix};
        refreshLastSyncStatus();
      }

      function resetMailboxState() {
        clearTimeout(searchTimer);
        searchTimer = null;
        resetPrefetchState(true);
        state.folder = 'INBOX';
        state.folderName = 'Inbox';
        state.folders = [];
        state.folderCache = null;
        state.messages = [];
        state.messageCache.clear();
        state.messageDetailsCache.clear();
        state.calendarCache.clear();
        state.staleFolders.clear();
        state.newMailFolders.clear();
        state.selectedUid = null;
        state.currentMessage = null;
        state.mobilePane = 'folders';
        state.selectedUids.clear();
        state.allPagesSelected = false;
        state.readContactPromptOpen = false;
        state.readContactPrompted.clear();
        state.currentUnknownReadContacts = [];
        state.lastSyncDisplay = null;
        state.googleReconnectPromptDismissed = false;
        state.messageRequestSerial++;
        state.page = 1;
        state.pages = 1;
        state.search = '';
        state.senderFilter = '';
        state.startDate = '';
        state.calendarActive = false;
        state.calendarMonth = '';
        $('#globalSearch').value = '';
        $('#clearSearch').classList.add('d-none');
        loadMailboxPreferences();
        $('#foldersList').innerHTML = '<div class="pse-empty"><div>Loading folders…</div></div>';
        $('#messagesList').innerHTML = '<div class="pse-empty"><div>Loading emails…</div></div>';
        clearPreview();
        updateMobilePaneNavigation();
      }

      async function loadFolders(
        withSpinner = true,
        reloadMessages = true,
        hardRefresh = false,
        spinnerText = 'Loading folders…'
      ) {
        try {
          if (hardRefresh) {
            state.folderCache = null;
            state.messageCache.clear();
          }
          if (state.folderCache && !hardRefresh) {
            state.folders = state.folderCache;
          } else {
            const result = await api('folders', {forceRefresh: hardRefresh}, {spinner: withSpinner, spinnerText});
            state.folderCache = result.folders;
            state.folders = state.folderCache;
            for (const folderId of result.changedFolders || []) {
              const changedFolderId = String(folderId);
              state.staleFolders.add(changedFolderId);
              invalidateMessageListCacheForFolder(changedFolderId);
            }
            updateLastSyncStatus(result.cache, 'Folders');
          }
          if (!state.folders.some(folder => folder.id === state.folder)) {
            const inbox = state.folders.find(folder => folder.special === 'inbox') || state.folders[0];
            if (inbox) {
              state.folder = inbox.id;
              state.folderName = inbox.name;
              state.page = 1;
            }
          }
          renderFolders();
          if (reloadMessages) {
            await loadMessages(state.page, false, hardRefresh, spinnerText);
          }
          setConnection(true);
        } catch (error) {
          setConnection(false);
          if (!reloadMessages) {
            console.error(error);
            return;
          }
          $('#foldersList').innerHTML = `<div class="pse-empty"><div><i class="fa-solid fa-circle-exclamation fa-2x mb-2"></i><br>${escapeHtml(error.message)}<br><button class="btn btn-sm btn-primary mt-3" id="configureNow">Open settings</button></div></div>`;
          $('#configureNow')?.addEventListener('click', openSettings);
          $('#messagesList').innerHTML = '<div class="pse-empty"><div>Configure this account connection to load messages.</div></div>';
        }
      }

      async function hardResync(spinnerText = 'Re-synch in progress') {
        if (state.hardSyncing) return;
        state.hardSyncing = true;
        showSpinner(spinnerText);
        try {
          await flushActionQueue(true);
          await loadFolders(false, true, true, spinnerText);
        } finally {
          state.lastHardSync = Date.now();
          state.hardSyncing = false;
          hideSpinner();
        }
      }

      function renderFolders() {
        const list = $('#foldersList');
        list.innerHTML = '';
        for (const folder of state.folders) {
          const button = document.createElement('button');
          button.className = 'pse-folder' + (folder.id === state.folder ? ' active' : '');
          button.dataset.folder = folder.id;
          button.innerHTML = `
            <i class="fa-solid ${folderIcon(folder.special)}"></i>
            <span class="pse-folder-name">${escapeHtml(folder.name)}</span>
            ${folder.unseen ? `<span class="badge rounded-pill text-bg-primary">${folder.unseen}</span>` : ''}
          `;
          button.addEventListener('click', () => selectFolder(folder));
          list.appendChild(button);
        }
        const current = state.folders.find(folder => folder.id === state.folder);
        if (current) {
          $('#statFolder').textContent = current.name;
          updateCurrentFolderHeading();
          $('#statMessages').textContent = current.messages;
          $('#statUnread').textContent = current.unseen;
        }
      }

      async function selectFolder(folder) {
        clearTimeout(searchTimer);
        searchTimer = null;
        resumePrefetch('search-typing');
        const folderId = String(folder.id);
        const synchronizeForNewMail = state.newMailFolders.has(folderId);
        state.folder = folder.id;
        state.folderName = folder.name;
        state.page = 1;
        state.selectedUid = null;
        state.currentMessage = null;
        state.multiSelect = false;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        $('#globalSearch').value = '';
        $('#clearSearch').classList.add('d-none');
        state.search = '';
        state.senderFilter = '';
        state.startDate = '';
        state.calendarActive = false;
        state.calendarMonth = '';
        state.lastSyncDisplay = null;
        const syncStatus = $('#lastSyncStatus');
        if (syncStatus) {
          if (synchronizeForNewMail) {
            syncStatus.textContent = `${folder.name} has new mail — synchronizing…`;
            syncStatus.title = 'New mail was detected, so this folder is being synchronized before it is shown.';
          } else {
            syncStatus.textContent = state.staleFolders.has(folderId)
              ? `${folder.name} has server changes — press Refresh to synchronize.`
              : `${folder.name} — cached view`;
            syncStatus.title = 'Folder navigation uses the local cache. Press Refresh to synchronize this folder with the email server.';
          }
        }
        updateMailboxViewControls();
        renderFolders();
        clearPreview();
        if (isSinglePaneMobileActive()) setMobilePane('messages', true);
        await loadMessages(
          1,
          true,
          synchronizeForNewMail,
          synchronizeForNewMail ? `Synchronizing ${folder.name}…` : `Opening ${folder.name} from cache…`,
          !synchronizeForNewMail
        );
      }

      function folderStatusIds() {
        const ids = new Set();
        if (state.folder) ids.add(String(state.folder));
        const inbox = state.folders.find(folder => folder.special === 'inbox');
        if (inbox?.id) ids.add(String(inbox.id));
        return [...ids];
      }

      function cacheMessagePage(data, context) {
        data._folder = context.folder;
        data._search = context.search;
        data._senderFilter = context.senderFilter;
        data._attachmentFilter = context.attachmentFilter;
        data._unreadOnly = context.unreadOnly;
        data._sortOrder = context.sortOrder;
        data._startDate = context.startDate || '';
        state.messageCache.set(
          messageCacheKey(
            context.folder,
            context.page,
            context.search,
            context.senderFilter,
            context.unreadOnly,
            context.sortOrder,
            context.attachmentFilter,
            context.startDate || ''
          ),
          data
        );
      }

      async function refreshFolderPageOne(folderId, folderName = '', visibleContext = false) {
        const isVisible = String(state.folder) === String(folderId);
        const context = visibleContext && isVisible
          ? {
              folder: state.folder,
              folderName: state.folderName,
              page: 1,
              search: state.search,
              senderFilter: state.senderFilter,
              attachmentFilter: state.attachmentFilter,
              unreadOnly: state.unreadOnly,
              sortOrder: state.sortOrder,
              startDate: state.startDate,
              visiblePage: state.page
            }
          : {
              folder: folderId,
              folderName,
              page: 1,
              search: '',
              senderFilter: '',
              attachmentFilter: 'all',
              unreadOnly: false,
              sortOrder: 'desc',
              startDate: '',
              visiblePage: isVisible ? state.page : 1
            };
        if (!context.folder) return;

        state.messageLoads++;
        try {
          const result = await api('messages', {
            folder: context.folder,
            page: 1,
            search: context.search,
            senderFilter: context.senderFilter,
            attachmentFilter: context.attachmentFilter,
            unreadOnly: context.unreadOnly,
            sortOrder: context.sortOrder,
            startDate: context.startDate,
            forceRefresh: true
          }, {spinner: false});

          const data = result.data;
          cacheMessagePage(data, context);
          state.staleFolders.delete(String(context.folder));
          if (visibleContext && isVisible) {
            state.newMailFolders.delete(String(context.folder));
          }
          if (
            isVisible &&
            state.folder === context.folder &&
            state.page === 1 &&
            state.search === context.search &&
            state.senderFilter === context.senderFilter &&
            state.attachmentFilter === context.attachmentFilter &&
            state.unreadOnly === context.unreadOnly &&
            state.sortOrder === context.sortOrder &&
            state.startDate === context.startDate
          ) {
            const prefetchGeneration = beginPrefetchView();
            applyMessageData(data);
            updateLastSyncStatus(result.cache, context.folderName || 'Mailbox');
            scheduleVisibleMessagePrefetch(prefetchGeneration);
          }
          return data;
        } finally {
          state.messageLoads = Math.max(0, state.messageLoads - 1);
        }
      }

      async function refreshVisibleFolderPageOne() {
        return refreshFolderPageOne(state.folder, state.folderName, true);
      }

      async function pollFolderStatus() {
        if (
          document.hidden ||
          state.folderStatusPolling ||
          state.hardSyncing ||
          state.queueFlushing ||
          state.queuePersisting > 0 ||
          state.messageLoads > 0 ||
          state.busy > 0 ||
          !state.folders.length ||
          (state.lastFolderStatusCheck > 0 && Date.now() - state.lastFolderStatusCheck < 15000)
        ) {
          return;
        }
        const requestedFolders = folderStatusIds();
        if (!requestedFolders.length) return;

        state.folderStatusPolling = true;
        state.lastFolderStatusCheck = Date.now();
        try {
          const result = await api('folder_status', {
            folders: requestedFolders
          }, {spinner: false});
          if (result.cache?.refreshError) {
            console.warn('Folder status refresh failed:', result.cache.refreshError);
            return;
          }

          const changedFolders = new Set((result.changedFolders || []).map(String));
          const previousCounts = new Map((state.folders || []).map(folder => [
            String(folder.id),
            {
              messages: Math.max(0, Number(folder.messages || 0)),
              unseen: Math.max(0, Number(folder.unseen || 0))
            }
          ]));
          state.folderCache = Array.isArray(result.folders) ? result.folders : state.folderCache;
          state.folders = state.folderCache || state.folders;

          const newMailFolders = [];
          for (const folderId of changedFolders) {
            const previous = previousCounts.get(folderId);
            const current = state.folders.find(folder => String(folder.id) === folderId);
            const hasNewMail = Boolean(
              previous &&
              current &&
              Math.max(0, Number(current.messages || 0)) > previous.messages
            );
            if (hasNewMail) {
              newMailFolders.push(folderId);
              state.newMailFolders.add(folderId);
            } else {
              // Read/unread, delete and move changes remain cache-first until explicit Refresh.
              state.staleFolders.add(folderId);
            }
          }
          renderFolders();

          // A real increase in message count means new mail. Refresh that folder automatically
          // so the new message is immediately available instead of showing a stale warning.
          for (const folderId of newMailFolders) {
            const folder = state.folders.find(item => String(item.id) === folderId);
            try {
              await refreshFolderPageOne(
                folderId,
                folder?.name || '',
                folderId === String(state.folder)
              );
            } catch (refreshError) {
              console.warn('Automatic new-mail refresh failed:', refreshError);
              state.staleFolders.add(folderId);
              state.newMailFolders.add(folderId);
            }
          }
          renderFolders();

          if (changedFolders.has(String(state.folder)) && !newMailFolders.includes(String(state.folder))) {
            const syncStatus = $('#lastSyncStatus');
            if (syncStatus) {
              syncStatus.textContent = `${state.folderName || 'Mailbox'} has server changes — press Refresh to synchronize.`;
              syncStatus.title = 'Moved, deleted or read/unread changes were detected. The cached list is intentionally kept until you press Refresh.';
            }
          }
          setConnection(true);
        } catch (error) {
          console.warn('Unable to check for mailbox count changes.', error);
        } finally {
          state.folderStatusPolling = false;
        }
      }


      function renderPaginationControls() {
        const label = $('#pageLabel');
        if (label) {
          label.innerHTML = `
            <button
              class="pse-page-number"
              id="currentPageNumber"
              type="button"
              aria-label="Jump to page ${state.page}; last page is ${state.pages}"
            >${state.page}</button>
            <span aria-hidden="true">/</span>
            <span id="totalPageNumber">${state.pages}</span>
          `;
          label.title = `Page ${state.page} of ${state.pages}. Click ${state.page} to jump to another page.`;
        }
        $('#previousPage').disabled = state.page <= 1;
        $('#nextPage').disabled = state.page >= state.pages;
      }

      function resetMessagePullIndicator() {
        const indicator = $('#messagePullIndicator');
        if (!indicator) return;
        indicator.classList.remove('show', 'ready');
        indicator.setAttribute('aria-hidden', 'true');
        indicator.style.top = '';
        indicator.style.bottom = '';
        indicator.style.removeProperty('--pse-pull-progress');
        const list = $('#messagesList');
        if (list) {
          list.classList.remove('pse-pulling', 'pse-pull-previous', 'pse-pull-next');
          list.style.removeProperty('--pse-pull-offset');
          list.style.removeProperty('--pse-pull-scale');
        }
      }

      function showMessagePullIndicator(direction, distance, threshold) {
        const indicator = $('#messagePullIndicator');
        const icon = $('#messagePullIcon');
        const text = $('#messagePullText');
        const list = $('#messagesList');
        if (!indicator || !icon || !text || !list) return;

        const progress = Math.max(0, Math.min(1, distance / Math.max(1, threshold)));
        const stretchProgress = Math.max(0, Math.min(1.2, distance / Math.max(1, threshold)));
        const offset = Math.min(34, 5 + stretchProgress * 24);
        const scale = 1 + Math.min(.022, stretchProgress * .018);
        const ready = distance >= threshold;
        indicator.classList.add('show');
        indicator.style.setProperty('--pse-pull-progress', String(progress));
        list.classList.add('pse-pulling');
        list.classList.toggle('pse-pull-previous', direction === 'previous');
        list.classList.toggle('pse-pull-next', direction !== 'previous');
        list.style.setProperty('--pse-pull-offset', `${offset}px`);
        list.style.setProperty('--pse-pull-scale', String(scale));
        indicator.classList.toggle('ready', ready);
        indicator.setAttribute('aria-hidden', 'false');

        if (direction === 'previous') {
          indicator.style.top = `${Math.max(6, list.offsetTop + 7)}px`;
          indicator.style.bottom = 'auto';
          icon.className = ready
            ? 'fa-solid fa-arrow-down'
            : 'fa-solid fa-chevron-down';
          text.textContent = ready ? 'Release for previous page' : 'Pull down for previous page';
        } else {
          indicator.style.top = 'auto';
          indicator.style.bottom = '8px';
          icon.className = ready
            ? 'fa-solid fa-arrow-up'
            : 'fa-solid fa-chevron-up';
          text.textContent = ready ? 'Release for next page' : 'Pull up for next page';
        }
      }

      function initializeMessagePullPaging() {
        const list = $('#messagesList');
        if (!list || list.dataset.pullPagingReady === '1') return;
        list.dataset.pullPagingReady = '1';

        const touchThreshold = 64;
        const wheelThreshold = 480;
        const wheelDeltaCap = 48;
        let touchGesture = null;
        let wheelGesture = null;
        let wheelFinishTimer = 0;

        const atTop = () => list.scrollTop <= 4;
        const atBottom = () => list.scrollHeight - list.clientHeight - list.scrollTop <= 4;

        const clearWheelTimer = () => {
          if (wheelFinishTimer) {
            clearTimeout(wheelFinishTimer);
            wheelFinishTimer = 0;
          }
        };

        const resetTouchGesture = () => {
          touchGesture = null;
          if (!state.pullPaging && !wheelGesture) resetMessagePullIndicator();
        };

        const resetWheelGesture = () => {
          clearWheelTimer();
          wheelGesture = null;
          if (!state.pullPaging && !touchGesture) resetMessagePullIndicator();
        };

        const changePageFromPull = async direction => {
          if (state.pullPaging || state.messageLoads > 0) return;

          const targetPage = direction === 'next'
            ? Math.min(state.pages, state.page + 1)
            : Math.max(1, state.page - 1);
          if (targetPage === state.page) {
            resetMessagePullIndicator();
            return;
          }

          state.pullPaging = true;
          // The application already shows its normal loading spinner. Hide the
          // pull hint immediately so "Release for..." never remains visible
          // while the page request is running.
          resetMessagePullIndicator();

          try {
            await loadMessages(
              targetPage,
              true,
              false,
              direction === 'next' ? 'Loading next page…' : 'Loading previous page…'
            );
            requestAnimationFrame(() => {
              if (direction === 'previous') {
                list.scrollTop = Math.max(0, list.scrollHeight - list.clientHeight);
              } else {
                list.scrollTop = 0;
              }
            });
          } catch (error) {
            handleError(error);
          } finally {
            state.pullPaging = false;
            touchGesture = null;
            wheelGesture = null;
            clearWheelTimer();
            resetMessagePullIndicator();
          }
        };

        list.addEventListener('touchstart', event => {
          if (
            state.pullPaging ||
            state.messageLoads > 0 ||
            event.touches.length !== 1
          ) {
            resetTouchGesture();
            return;
          }

          const touch = event.touches[0];
          touchGesture = {
            lastY: touch.clientY,
            direction: '',
            distance: 0
          };
        }, {passive: true});

        list.addEventListener('touchmove', event => {
          if (!touchGesture || event.touches.length !== 1 || state.pullPaging) return;

          const currentY = event.touches[0].clientY;
          const movement = currentY - touchGesture.lastY;
          touchGesture.lastY = currentY;

          let direction = '';
          let amount = 0;
          if (movement > 0 && atTop() && state.page > 1) {
            direction = 'previous';
            amount = movement;
          } else if (movement < 0 && atBottom() && state.page < state.pages) {
            direction = 'next';
            amount = -movement;
          }

          if (!direction || amount <= 0) {
            if (touchGesture.direction !== '') {
              touchGesture.direction = '';
              touchGesture.distance = 0;
              resetMessagePullIndicator();
            }
            return;
          }

          if (touchGesture.direction !== direction) {
            touchGesture.direction = direction;
            touchGesture.distance = 0;
          }
          touchGesture.distance = Math.min(
            touchThreshold * 1.6,
            touchGesture.distance + amount
          );

          if (touchGesture.distance >= 5) {
            showMessagePullIndicator(direction, touchGesture.distance, touchThreshold);
          }

          // Only suppress browser rubber-band/page scrolling once this is clearly
          // an outward pull. Ordinary scrolling inside the message list remains native.
          if (touchGesture.distance >= 12) {
            event.preventDefault();
          }
        }, {passive: false});

        list.addEventListener('touchend', async () => {
          if (!touchGesture || state.pullPaging) {
            resetTouchGesture();
            return;
          }

          const direction = touchGesture.direction;
          const shouldChangePage = touchGesture.distance >= touchThreshold;
          touchGesture = null;

          if (!shouldChangePage || !direction) {
            resetMessagePullIndicator();
            return;
          }
          await changePageFromPull(direction);
        }, {passive: true});

        list.addEventListener('touchcancel', resetTouchGesture, {passive: true});

        list.addEventListener('wheel', event => {
          if (state.pullPaging || state.messageLoads > 0 || event.ctrlKey) return;

          let delta = event.deltaY;
          if (event.deltaMode === 1) delta *= 16;
          if (event.deltaMode === 2) delta *= Math.max(1, list.clientHeight);

          let direction = '';
          if (delta < 0 && atTop() && state.page > 1) {
            direction = 'previous';
          } else if (delta > 0 && atBottom() && state.page < state.pages) {
            direction = 'next';
          }

          if (!direction) {
            resetWheelGesture();
            return;
          }

          // Keep the wheel/touchpad gesture inside this pane at its boundary.
          // The accumulated movement is interpreted as the page pull gesture.
          event.preventDefault();

          const now = performance.now();
          if (
            !wheelGesture ||
            wheelGesture.direction !== direction ||
            now - wheelGesture.lastTime > 700
          ) {
            wheelGesture = {
              direction,
              distance: 0,
              lastTime: now
            };
          }

          // Cap each wheel event so one large mouse-wheel notch or a
          // touchpad momentum spike cannot immediately change page. The user
          // must continue pushing beyond the top/bottom boundary deliberately.
          const wheelStep = Math.min(wheelDeltaCap, Math.abs(delta));
          wheelGesture.distance = Math.min(
            wheelThreshold * 1.5,
            wheelGesture.distance + wheelStep
          );
          wheelGesture.lastTime = now;
          showMessagePullIndicator(direction, wheelGesture.distance, wheelThreshold);

          clearWheelTimer();
          wheelFinishTimer = window.setTimeout(async () => {
            const completed = wheelGesture;
            wheelGesture = null;
            wheelFinishTimer = 0;
            if (!completed || completed.distance < wheelThreshold) {
              resetMessagePullIndicator();
              return;
            }
            await changePageFromPull(completed.direction);
          }, 220);
        }, {passive: false});
      }

      function beginPageJumpEdit() {
        const button = $('#currentPageNumber');
        if (!button || $('#pageJumpInput')) return;

        const input = document.createElement('input');
        input.id = 'pageJumpInput';
        input.className = 'pse-page-input';
        input.type = 'number';
        input.step = '1';
        input.min = '1';
        input.max = String(Math.max(1, state.pages));
        input.value = String(state.page);
        input.inputMode = 'numeric';
        input.setAttribute('aria-label', `Jump to a page from 1 to ${Math.max(1, state.pages)}`);
        button.replaceWith(input);

        let finished = false;
        const finish = async applyValue => {
          if (finished) return;
          finished = true;

          if (!applyValue) {
            renderPaginationControls();
            return;
          }

          let targetPage = Number.parseInt(input.value, 10);
          if (!Number.isFinite(targetPage) || targetPage <= 0) targetPage = 1;
          targetPage = Math.min(Math.max(1, targetPage), Math.max(1, state.pages));
          renderPaginationControls();

          if (targetPage === state.page) return;
          try {
            await loadMessages(targetPage);
          } catch (error) {
            handleError(error);
          }
        };

        input.addEventListener('keydown', event => {
          if (event.key === 'Enter') {
            event.preventDefault();
            input.blur();
          } else if (event.key === 'Escape') {
            event.preventDefault();
            finish(false);
          }
        });
        input.addEventListener('blur', () => finish(true), {once: true});
        input.focus();
        input.select();
      }

      function applyMessageData(data) {
        data.messages = (data.messages || []).map(message => ({
          ...message,
          uid: String(message.uid)
        }));
        data.messages.sort((left, right) => {
          const delta = Number(left.timestamp || 0) - Number(right.timestamp || 0);
          if (delta !== 0) return state.sortOrder === 'asc' ? delta : -delta;
          return state.sortOrder === 'asc'
            ? String(left.uid).localeCompare(String(right.uid))
            : String(right.uid).localeCompare(String(left.uid));
        });
        state.messages = data.messages;
        if (!state.multiSelect) {
          state.selectedUids.clear();
          state.allPagesSelected = false;
        }
        state.page = data.page;
        state.pages = data.pages;
        const currentFolder = state.folders.find(folder => folder.id === state.folder);
        if (currentFolder) {
          currentFolder.messages = data.folderTotal;
          currentFolder.unseen = data.folderUnseen;
          renderFolders();
        }
        renderMessages();
        renderPaginationControls();
        $('#statMessages').textContent = data.folderTotal;
        $('#statUnread').textContent = data.folderUnseen;
        updateMailboxViewControls();
      }

      function rememberCurrentMessageData() {
        const key = messageCacheKey();
        const data = state.messageCache.get(key);
        if (!data) return;
        const currentFolder = state.folders.find(folder => folder.id === state.folder);
        data.messages = state.messages;
        data.page = state.page;
        data.pages = state.pages;
        if (currentFolder) {
          data.folderTotal = Number(currentFolder.messages || 0);
          data.folderUnseen = Number(currentFolder.unseen || 0);
        }
      }

      function showMessageListLoading(message = 'Reading messages…') {
        $('#messagesList').innerHTML = `
          <div class="pse-empty" role="status" aria-live="polite">
            <div>
              <div class="spinner-border text-primary mb-3"></div>
              <div>${escapeHtml(message)}</div>
            </div>
          </div>
        `;
      }

      async function loadMessages(
        page = 1,
        withSpinner = true,
        hardRefresh = false,
        spinnerText = 'Reading messages…',
        cacheOnly = false
      ) {
        const requestSerial = ++state.messageRequestSerial;
        const prefetchGeneration = beginPrefetchView();
        const prefetchPauseReason = `message-load-${requestSerial}`;
        let shouldPrefetchVisibleMessages = false;
        pausePrefetch(prefetchPauseReason, true);
        const requestedFolder = state.folder;
        const requestedFolderName = state.folderName;
        const requestedSearch = state.search;
        const requestedSenderFilter = state.senderFilter;
        const requestedAttachmentFilter = state.attachmentFilter;
        const requestedUnreadOnly = state.unreadOnly;
        const requestedSortOrder = state.sortOrder;
        const requestedStartDate = state.startDate;
        try {
          const key = messageCacheKey(
            requestedFolder,
            page,
            requestedSearch,
            requestedSenderFilter,
            requestedUnreadOnly,
            requestedSortOrder,
            requestedAttachmentFilter,
            requestedStartDate
          );
          let data = !hardRefresh ? state.messageCache.get(key) : null;
          if (!data) {
            state.messageLoads++;
            showMessageListLoading(spinnerText);
            try {
              const result = await api('messages', {
                folder: requestedFolder,
                page,
                search: requestedSearch,
                senderFilter: requestedSenderFilter,
                attachmentFilter: requestedAttachmentFilter,
                unreadOnly: requestedUnreadOnly,
                sortOrder: requestedSortOrder,
                startDate: requestedStartDate,
                forceRefresh: hardRefresh,
                cacheOnly
              }, {spinner: false});
              if (
                requestSerial !== state.messageRequestSerial ||
                state.folder !== requestedFolder ||
                state.search !== requestedSearch ||
                state.senderFilter !== requestedSenderFilter ||
                state.attachmentFilter !== requestedAttachmentFilter ||
                state.unreadOnly !== requestedUnreadOnly ||
                state.sortOrder !== requestedSortOrder ||
                state.startDate !== requestedStartDate
              ) {
                return;
              }
              if (result.cacheMiss) {
                if (
                  requestSerial !== state.messageRequestSerial ||
                  state.folder !== requestedFolder
                ) {
                  return;
                }
                state.messages = [];
                state.page = page;
                state.pages = 1;
                state.selectedUid = null;
                state.currentMessage = null;
                $('#messagesList').innerHTML = `
                  <div class="pse-empty" role="status">
                    <div>
                      <i class="fa-solid fa-database fa-2x mb-2"></i><br>
                      No cached copy of this folder is available.<br>
                      <span class="small text-secondary">Synchronize it from the email server to load the messages.</span><br>
                      <button class="btn btn-sm btn-primary mt-3" id="cacheMissRefresh" type="button">
                        <i class="fa-solid fa-rotate me-1"></i>Refresh
                      </button>
                    </div>
                  </div>
                `;
                $('#cacheMissRefresh')?.addEventListener('click', () => {
                  loadMessages(
                    page,
                    true,
                    true,
                    `Synchronizing ${requestedFolderName || 'folder'}…`
                  );
                });
                renderPaginationControls();
                updateMailboxViewControls();
                return;
              }
              data = result.data;
              updateLastSyncStatus(result.cache, requestedFolderName || 'Mailbox');
              cacheMessagePage(data, {
                folder: requestedFolder,
                page,
                search: requestedSearch,
                senderFilter: requestedSenderFilter,
                attachmentFilter: requestedAttachmentFilter,
                unreadOnly: requestedUnreadOnly,
                sortOrder: requestedSortOrder,
                startDate: requestedStartDate
              });
              if (hardRefresh) {
                state.staleFolders.delete(String(requestedFolder));
                state.newMailFolders.delete(String(requestedFolder));
              }
            } finally {
              state.messageLoads = Math.max(0, state.messageLoads - 1);
            }
          }
          if (
            requestSerial !== state.messageRequestSerial ||
            state.folder !== requestedFolder ||
            state.search !== requestedSearch ||
            state.senderFilter !== requestedSenderFilter ||
            state.attachmentFilter !== requestedAttachmentFilter ||
            state.unreadOnly !== requestedUnreadOnly ||
            state.sortOrder !== requestedSortOrder ||
            state.startDate !== requestedStartDate
          ) {
            return;
          }
          applyMessageData(data);
          shouldPrefetchVisibleMessages = true;
          if (requestedSearch) saveLastSearch(data);
          setConnection(true);
          if (state.calendarActive && initialSettings.show_calendar) {
            await loadCalendarMonth(state.calendarMonth || currentCalendarMonth(), hardRefresh);
          }
        } catch (error) {
          if (requestSerial !== state.messageRequestSerial) {
            return;
          }
          setConnection(false);
          $('#messagesList').innerHTML = `<div class="pse-empty"><div>${escapeHtml(error.message)}</div></div>`;
          handleError(error);
        } finally {
          resumePrefetch(prefetchPauseReason);
          if (
            shouldPrefetchVisibleMessages &&
            requestSerial === state.messageRequestSerial &&
            prefetchGeneration === state.prefetchGeneration
          ) {
            scheduleVisibleMessagePrefetch(prefetchGeneration);
          }
        }
      }


      function zonedDateParts(timestampSeconds, extraOptions = {}) {
        const date = new Date(Math.max(0, Number(timestampSeconds || 0)) * 1000);
        const options = {
          timeZone: initialSettings.timezone || 'UTC',
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          ...extraOptions
        };
        let formatter;
        try {
          formatter = new Intl.DateTimeFormat('en-US', options);
        } catch (error) {
          formatter = new Intl.DateTimeFormat('en-US', {...options, timeZone: 'UTC'});
        }
        const parts = {};
        formatter.formatToParts(date).forEach(part => {
          if (part.type !== 'literal') parts[part.type] = part.value;
        });
        return parts;
      }

      function configuredDayLabel(timestampSeconds) {
        const numeric = zonedDateParts(timestampSeconds);
        const shortMonth = zonedDateParts(timestampSeconds, {month: 'short'}).month || '';
        const longMonth = zonedDateParts(timestampSeconds, {month: 'long'}).month || '';
        const shortWeekday = zonedDateParts(timestampSeconds, {weekday: 'short'}).weekday || '';
        const longWeekday = zonedDateParts(timestampSeconds, {weekday: 'long'}).weekday || '';
        const year = String(numeric.year || '');
        const month = String(numeric.month || '').padStart(2, '0');
        const day = String(numeric.day || '').padStart(2, '0');
        const replacements = {
          d: day,
          j: String(Number(day || 0)),
          m: month,
          n: String(Number(month || 0)),
          Y: year,
          y: year.slice(-2),
          M: shortMonth,
          F: longMonth,
          D: shortWeekday,
          l: longWeekday
        };
        const pattern = String(initialSettings.date_format || 'd-m-Y');
        let output = '';
        let escaped = false;
        for (const character of pattern) {
          if (escaped) {
            output += character;
            escaped = false;
          } else if (character === '\\') {
            escaped = true;
          } else {
            output += Object.prototype.hasOwnProperty.call(replacements, character)
              ? replacements[character]
              : character;
          }
        }
        return output || `${day}-${month}-${year}`;
      }

      function configuredDateTimeLabel(timestampSeconds) {
        const timestamp = Number(timestampSeconds || 0);
        if (timestamp <= 0) return '';
        const numeric = zonedDateParts(timestamp, {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hourCycle: 'h23'
        });
        const shortMonth = zonedDateParts(timestamp, {month: 'short'}).month || '';
        const longMonth = zonedDateParts(timestamp, {month: 'long'}).month || '';
        const shortWeekday = zonedDateParts(timestamp, {weekday: 'short'}).weekday || '';
        const longWeekday = zonedDateParts(timestamp, {weekday: 'long'}).weekday || '';
        const year = String(numeric.year || '');
        const month = String(numeric.month || '').padStart(2, '0');
        const day = String(numeric.day || '').padStart(2, '0');
        const hour24 = Math.max(0, Math.min(23, Number(numeric.hour || 0)));
        const hour12 = hour24 % 12 || 12;
        const minute = String(numeric.minute || '0').padStart(2, '0');
        const second = String(numeric.second || '0').padStart(2, '0');
        const replacements = {
          d: day,
          j: String(Number(day || 0)),
          m: month,
          n: String(Number(month || 0)),
          Y: year,
          y: year.slice(-2),
          M: shortMonth,
          F: longMonth,
          D: shortWeekday,
          l: longWeekday,
          H: String(hour24).padStart(2, '0'),
          G: String(hour24),
          h: String(hour12).padStart(2, '0'),
          g: String(hour12),
          i: minute,
          s: second,
          A: hour24 < 12 ? 'AM' : 'PM',
          a: hour24 < 12 ? 'am' : 'pm'
        };
        const pattern = `${String(initialSettings.date_format || 'd-m-Y')} ${String(initialSettings.time_format || 'H:i')}`;
        let output = '';
        let escaped = false;
        for (const character of pattern) {
          if (escaped) {
            output += character;
            escaped = false;
          } else if (character === '\\') {
            escaped = true;
          } else {
            output += Object.prototype.hasOwnProperty.call(replacements, character)
              ? replacements[character]
              : character;
          }
        }
        return output.trim();
      }

      function messageDayGroup(timestampSeconds) {
        if (Number(timestampSeconds || 0) <= 0) {
          return {key: 'unknown', label: 'Unknown date'};
        }
        const message = zonedDateParts(timestampSeconds);
        const today = zonedDateParts(Math.floor(Date.now() / 1000));
        const messageSerial = Date.UTC(
          Number(message.year || 1970),
          Math.max(0, Number(message.month || 1) - 1),
          Number(message.day || 1)
        ) / 86400000;
        const todaySerial = Date.UTC(
          Number(today.year || 1970),
          Math.max(0, Number(today.month || 1) - 1),
          Number(today.day || 1)
        ) / 86400000;
        const difference = todaySerial - messageSerial;
        return {
          key: `${message.year || '0000'}-${message.month || '00'}-${message.day || '00'}`,
          label: difference === 0
            ? 'Today'
            : (difference === 1 ? 'Yesterday' : configuredDayLabel(timestampSeconds))
        };
      }

      function renderMessages() {
        const list = $('#messagesList');
        if (!state.messages.length) {
          const emptyMessage = state.search
            ? 'No matching emails'
            : (state.senderFilter
              ? `No emails from ${state.senderFilter}`
              : (state.unreadOnly ? 'No unread emails' : 'This folder is empty'));
          list.innerHTML = `<div class="pse-empty"><div><i class="fa-regular fa-face-meh fa-2x mb-2"></i><br>${emptyMessage}</div></div>`;
          updateBulkUI();
          return;
        }
        list.innerHTML = '';
        const previewRows = Math.max(0, Math.min(5, Number(initialSettings.email_preview_rows || 0)));
        const groupByDay = Boolean(initialSettings.group_messages_by_day);
        if (state.senderFilter) {
          const senderMessage = state.messages.find(message => message.fromEmail || message.fromName) || {};
          const senderName = String(senderMessage.fromName || '').trim();
          const senderEmail = String(senderMessage.fromEmail || state.senderFilter).trim();
          const senderLabel = senderName && senderEmail && senderName.toLowerCase() !== senderEmail.toLowerCase()
            ? `${senderName} <${senderEmail}>`
            : (senderName || senderEmail || state.senderFilter);
          const banner = document.createElement('div');
          banner.className = 'pse-sender-filter-banner';
          banner.title = senderLabel;
          banner.innerHTML = `<i class="fa-solid fa-user-tag me-1"></i><b>Sender:</b> ${escapeHtml(senderLabel)}`;
          list.appendChild(banner);
        }
        let previousDayKey = null;
        for (const message of state.messages) {
          if (groupByDay) {
            const group = messageDayGroup(message.timestamp);
            if (group.key !== previousDayKey) {
              const heading = document.createElement('div');
              heading.className = 'pse-day-separator';
              heading.textContent = group.label;
              list.appendChild(heading);
              previousDayKey = group.key;
            }
          }
          const button = document.createElement('button');
          const hasSearchContext = Boolean(state.search && Array.isArray(message.searchContext));
          const previewText = String(message.previewText || '').trim();
          const hasPreview = previewRows > 0 && previewText !== '';
          const sender = String(message.fromName || '').trim() || String(message.fromEmail || '').trim() || '(Unknown sender)';
          const senderHtml = state.search ? highlightSearchText(sender) : escapeHtml(sender);
          const subjectHtml = state.search
            ? highlightSearchText(message.subject)
            : escapeHtml(message.subject);
          const sizeLabel = formatCompactBytes(message.size);
          const attachmentCount = Math.max(0, Number(message.attachmentCount || 0));
          const attachmentPill = initialSettings.show_attachment_pill && attachmentCount > 0
            ? `<span class="pse-message-attachment-pill" title="${attachmentCount} attachment${attachmentCount === 1 ? '' : 's'}"><i class="fa-solid fa-paperclip"></i>${attachmentCount}</span>`
            : '';
          const trashControl = initialSettings.show_list_trash
            ? (currentFolderIsTrash()
              ? '<span class="pse-message-trash pse-message-restore" title="Restore to Inbox"><i class="fa-solid fa-trash-arrow-up"></i></span>'
              : '<span class="pse-message-trash" title="Move to Trash"><i class="fa-solid fa-trash"></i></span>')
            : '';
          const sizeControl = initialSettings.show_list_size
            ? `<span class="pse-message-size" title="Message size">${escapeHtml(sizeLabel)}</span>`
            : '';
          const hasLeftActions = trashControl !== '' || attachmentPill !== '' || sizeControl !== '';
          button.className = 'pse-message' +
            (!message.seen ? ' unread' : '') +
            (message.uid === state.selectedUid && !state.multiSelect ? ' active' : '') +
            (state.multiSelect ? ' multi-select' : '') +
            (hasSearchContext ? ' search-result' : '') +
            (hasPreview ? ' has-preview' : '') +
            (state.senderFilter ? ' same-sender' : '') +
            (!hasLeftActions ? ' no-action' : '') +
            (state.selectedUids.has(message.uid) ? ' bulk-selected' : '');
          button.dataset.uid = message.uid;
          const searchContext = hasSearchContext
            ? `<span class="pse-message-search-context">${
                [0, 1, 2].map(index => {
                  const line = String(message.searchContext[index] || '');
                  return `<span class="pse-search-context-line${index === 1 ? ' match' : ''}">${
                    line ? highlightSearchText(line) : '&nbsp;'
                  }</span>`;
                }).join('')
              }</span>`
            : '';
          const preview = hasPreview
            ? `<span class="pse-message-preview" style="--pse-preview-lines:${previewRows}">${escapeHtml(previewText)}</span>`
            : '';
          button.innerHTML = `
            <span class="pse-message-select">
              <i class="${state.selectedUids.has(message.uid) ? 'fa-solid fa-square-check text-primary' : 'fa-regular fa-square'}"></i>
            </span>
            ${hasLeftActions ? `<span class="pse-message-action">
              ${trashControl}
              ${attachmentPill}
              ${sizeControl}
            </span>` : ''}
            ${state.senderFilter ? '' : `<span class="pse-message-from">${senderHtml}</span>`}
            <span class="pse-message-date" title="${escapeHtml(message.date)}">${escapeHtml(message.date)}</span>
            <span class="pse-message-subject">${message.answered ? '<i class="fa-solid fa-reply me-1"></i>' : ''}${subjectHtml}</span>
            ${preview}
            ${searchContext}
          `;
          button.addEventListener('click', event => {
            if (event.target.closest('.pse-message-trash')) {
              event.stopPropagation();
              if (currentFolderIsTrash()) {
                restoreMessages([message.uid]);
              } else {
                deleteMessagesWithConfirmation([message.uid]);
              }
              return;
            }
            if (state.multiSelect) {
              setBulkSelected(message.uid, !state.selectedUids.has(message.uid));
              return;
            }
            openMessage(message.uid);
          });
          list.appendChild(button);
        }
        updateBulkUI();
      }

      function setBulkSelected(uid, selected) {
        uid = String(uid);
        state.allPagesSelected = false;
        if (selected) {
          state.selectedUids.add(uid);
        } else {
          state.selectedUids.delete(uid);
        }
        renderMessages();
      }

      function updateBulkUI() {
        const actions = $('#bulkActions');
        actions.classList.toggle('d-none', !state.multiSelect);
        actions.classList.toggle('d-flex', state.multiSelect);
        $('#toggleMultiSelect').classList.toggle('active', state.multiSelect);
        $('#toggleMultiSelect').title = state.multiSelect ? 'Disable multiple selection' : 'Enable multiple selection';
        $('#bulkCount').textContent = state.allPagesSelected
          ? `${state.selectedUids.size} selected — all pages`
          : `${state.selectedUids.size} selected`;
        const allPagesButton = $('#bulkSelectAllPages');
        if (allPagesButton) {
          allPagesButton.classList.toggle('active', state.allPagesSelected);
          allPagesButton.title = state.allPagesSelected
            ? 'All emails in all pages are selected'
            : 'Select all emails in all pages';
        }
        const inTrash = currentFolderIsTrash();
        $('#bulkDelete').classList.toggle('d-none', inTrash);
        $('#bulkRestore').classList.toggle('d-none', !inTrash);
        $('#bulkDeleteForever').classList.toggle('d-none', !inTrash);
        ['#bulkRead', '#bulkUnread', '#bulkForward', '#bulkDelete', '#bulkRestore', '#bulkDeleteForever', '#bulkClear'].forEach(selector => {
          $(selector).disabled = state.selectedUids.size === 0;
        });
      }

      function toggleMultiSelect() {
        state.multiSelect = !state.multiSelect;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        renderMessages();
      }

      async function selectAllMessagesAcrossPages() {
        if (!state.multiSelect) return;
        try {
          const result = await api('message_ids', {
            folder: state.folder,
            search: state.search,
            senderFilter: state.senderFilter,
            attachmentFilter: state.attachmentFilter,
            unreadOnly: state.unreadOnly,
            startDate: state.startDate,
            sortOrder: state.sortOrder
          }, {spinnerText: 'Selecting all emails in all pages…'});
          const uids = Array.isArray(result.uids) ? result.uids.map(String).filter(Boolean) : [];
          state.selectedUids = new Set(uids);
          state.allPagesSelected = true;
          renderMessages();
          toast(`${uids.length} email${uids.length === 1 ? '' : 's'} selected across all pages.`);
        } catch (error) {
          handleError(error);
        }
      }

      function chunkUids(uids, size = 500) {
        const chunks = [];
        for (let index = 0; index < uids.length; index += size) {
          chunks.push(uids.slice(index, index + size));
        }
        return chunks;
      }

      function applyKnownBulkOperation(uids, operation, affected = uids.length) {
        const selected = new Set(uids.map(String));
        const selectedMessages = state.messages.filter(message => selected.has(String(message.uid)));
        const unreadBefore = selectedMessages.filter(message => !message.seen).length;
        const readBefore = selectedMessages.length - unreadBefore;
        const currentFolder = currentFolderRecord();
        const removesFromCurrentFolder = ['delete', 'restore', 'delete_forever'].includes(operation);

        if (currentFolder) {
          if (operation === 'read') {
            currentFolder.unseen = Math.max(0, Number(currentFolder.unseen || 0) - unreadBefore);
          } else if (operation === 'unread') {
            currentFolder.unseen = Number(currentFolder.unseen || 0) + readBefore;
          } else if (removesFromCurrentFolder) {
            currentFolder.messages = Math.max(0, Number(currentFolder.messages || 0) - affected);
            currentFolder.unseen = Math.max(0, Number(currentFolder.unseen || 0) - unreadBefore);

            if (operation === 'delete') {
              const trash = state.folders.find(folder => folder.special === 'trash');
              if (trash && trash.id !== currentFolder.id) {
                trash.messages = Number(trash.messages || 0) + affected;
                trash.unseen = Number(trash.unseen || 0) + unreadBefore;
                invalidateMessageCacheForFolder(trash.id);
              }
            } else if (operation === 'restore') {
              const inbox = state.folders.find(folder => folder.special === 'inbox');
              if (inbox && inbox.id !== currentFolder.id) {
                inbox.messages = Number(inbox.messages || 0) + affected;
                inbox.unseen = Number(inbox.unseen || 0) + unreadBefore;
                invalidateMessageCacheForFolder(inbox.id);
                state.staleFolders.add(String(inbox.id));
                state.newMailFolders.add(String(inbox.id));
              }
            }
          }
        }

        for (const data of state.messageCache.values()) {
          if (data._folder !== state.folder) continue;
          const cachedMatches = data.messages.filter(message => selected.has(String(message.uid)));
          if (removesFromCurrentFolder) {
            data.messages = data.messages.filter(message => !selected.has(String(message.uid)));
            const removed = cachedMatches.length;
            data.total = Math.max(0, Number(data.total || 0) - removed);
            data.folderTotal = Math.max(0, Number(data.folderTotal || 0) - affected);
            data.pages = Math.max(1, Math.ceil(data.total / Math.max(1, Number(data.perPage || 1))));
          } else {
            cachedMatches.forEach(message => {
              message.seen = operation === 'read';
            });
          }
          if (currentFolder) {
            data.folderUnseen = Number(currentFolder.unseen || 0);
          }
        }

        if (removesFromCurrentFolder) {
          for (const key of [...state.messageDetailsCache.keys()]) {
            if (uids.some(uid => key.includes(`|${String(uid)}|`))) {
              state.messageDetailsCache.delete(key);
            }
          }
        } else {
          for (const [key, cachedMessage] of state.messageDetailsCache.entries()) {
            if (uids.some(uid => key.includes(`|${String(uid)}|`))) {
              cachedMessage.seen = operation === 'read';
              state.messageDetailsCache.set(key, cachedMessage);
            }
          }
        }
        const currentData = state.messageCache.get(messageCacheKey());
        if (currentData) {
          const prefetchGeneration = beginPrefetchView();
          applyMessageData(currentData);
          scheduleVisibleMessagePrefetch(prefetchGeneration);
          if (state.search && removesFromCurrentFolder) {
            saveLastSearch(currentData);
          }
        } else {
          renderFolders();
          renderMessages();
        }
        invalidateCalendarCacheForFolder(state.folder);
        state.selectedUids.clear();
        state.allPagesSelected = false;
        updateBulkUI();
      }

      function invalidateCalendarCacheForFolder(folderId) {
        for (const [key, data] of state.calendarCache.entries()) {
          if (data._folder === folderId) {
            state.calendarCache.delete(key);
          }
        }
      }

      function invalidateMessageListCacheForFolder(folderId) {
        for (const [key, data] of state.messageCache.entries()) {
          if (data._folder === folderId) {
            state.messageCache.delete(key);
          }
        }
        invalidateCalendarCacheForFolder(folderId);
      }

      function invalidateMessageCacheForFolder(folderId) {
        for (const [key, data] of state.messageCache.entries()) {
          if (data._folder === folderId) {
            state.messageCache.delete(key);
          }
        }
        invalidateCalendarCacheForFolder(folderId);
        for (const key of [...state.messageDetailsCache.keys()]) {
          if (key.includes(`|${folderId}|`)) {
            state.messageDetailsCache.delete(key);
          }
        }
      }

      function noteSentMessage(count = 1) {
        count = Math.max(1, Number(count || 1));
        const sentFolder = state.folders.find(folder => folder.special === 'sent');
        if (!sentFolder) return;
        sentFolder.messages = Number(sentFolder.messages || 0) + count;
        invalidateMessageCacheForFolder(sentFolder.id);
        state.staleFolders.add(String(sentFolder.id));
        state.newMailFolders.add(String(sentFolder.id));
        renderFolders();
      }

      function updateQueueStat() {
        const queue = $('#statQueue');
        const action = $('#footerQueueAction');
        const count = Math.max(0, Number(state.pendingQueue || 0));
        queue.textContent = count;
        queue.classList.toggle('text-warning', count > 0);
        if (action) {
          action.disabled = count <= 0;
          action.title = count > 0
            ? `Undo ${count} queued delete operation${count === 1 ? '' : 's'}`
            : 'No queued deletions to undo';
        }
      }

      async function undoQueuedDeletes() {
        const count = Math.max(0, Number(state.pendingQueue || 0));
        if (count <= 0 || state.queueUndoing) return;
        if (state.queueFlushing) {
          toast('Queued deletions are currently being processed and cannot be undone at this moment.', 'warning');
          return;
        }
        state.queueUndoing = true;
        try {
          for (let wait = 0; state.queuePersisting > 0 && wait < 250; wait++) {
            await new Promise(resolve => setTimeout(resolve, 20));
          }
          const confirmation = await Swal.fire({
            target: activeSwalTarget(),
            icon: 'question',
            title: 'Undo queued deletions?',
            text: `Are you sure to undo the delete operations of ${count} mail${count === 1 ? '' : 's'}?`,
            showCancelButton: true,
            confirmButtonText: 'Undo deletes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: initialSettings.primary_color || '#1769aa'
          });
          if (!confirmation.isConfirmed) return;
          const result = await api('undo_queue', {confirmed: true}, {
            spinnerText: 'Undoing queued deletions…'
          });
          const queue = result.queue || {};
          const removed = Math.max(0, Number(queue.removed || 0));
          state.pendingQueue = Math.max(0, Number(queue.pending || 0));
          updateQueueStat();
          if (removed > 0) {
            state.folderCache = null;
            state.messageCache.clear();
            state.messageDetailsCache.clear();
            await loadFolders(false, true, true, 'Restoring mailbox after undo…');
            toast(`${removed} queued deletion${removed === 1 ? '' : 's'} undone.`);
          } else {
            toast('There were no queued deletions left to undo.', 'info');
          }
        } catch (error) {
          handleError(error);
        } finally {
          state.queueUndoing = false;
        }
      }

      async function flushActionQueue(silent = true) {
        if (state.queueFlushing || state.queueUndoing) return null;
        state.queueFlushing = true;
        try {
          for (let wait = 0; state.queuePersisting > 0 && wait < 250; wait++) {
            await new Promise(resolve => setTimeout(resolve, 20));
          }
          const result = await api('handle_queue', {}, {
            spinner: !silent,
            spinnerText: 'Processing queued actions…',
            keepalive: true
          });
          const queue = result.queue || {};
          state.pendingQueue = Math.max(0, Number(queue.pending || 0));
          updateQueueStat();
          if (!silent && Number(queue.processed || 0) > 0) {
            toast(`${queue.processed} queued deletion${Number(queue.processed) === 1 ? '' : 's'} completed.`);
          }
          if (!silent && Number(queue.failed || 0) > 0) {
            toast(`${queue.failed} queued deletion${Number(queue.failed) === 1 ? '' : 's'} will be retried.`, 'warning');
          }
          return queue;
        } catch (error) {
          if (silent) {
            console.error(error);
          } else {
            handleError(error);
          }
          return null;
        } finally {
          state.queueFlushing = false;
        }
      }

      async function queueDeleteMessages(uids, confirmation = '') {
        uids = [...new Set((uids || []).map(String).filter(Boolean))];
        if (!uids.length) return;
        const deletingCurrent = Boolean(
          state.currentMessage && uids.includes(String(state.currentMessage.uid))
        );
        if (deletingCurrent) {
          state.selectedUid = null;
          state.currentMessage = null;
          clearPreview();
          if (isSinglePaneMobileActive()) setMobilePane('messages');
        }
        applyKnownBulkOperation(uids, 'delete', uids.length);
        const pendingBefore = Math.max(0, Number(state.pendingQueue || 0));
        let persistedPending = pendingBefore;
        state.pendingQueue = pendingBefore + uids.length;
        updateQueueStat();
        state.queuePersisting++;
        try {
          let pending = state.pendingQueue;
          for (const uidChunk of chunkUids(uids)) {
            const result = await api('queue_delete', {
              folder: state.folder,
              uids: uidChunk,
              confirmation
            }, {
              spinner: false,
              keepalive: true
            });
            pending = Math.max(0, Number(result.pending ?? pending));
            persistedPending = pending;
            state.pendingQueue = pending;
            updateQueueStat();
          }
          toast(`${uids.length} email${uids.length === 1 ? '' : 's'} queued for Trash.`);
        } catch (error) {
          state.messageCache.clear();
          state.messageDetailsCache.clear();
          state.folderCache = null;
          state.pendingQueue = persistedPending;
          updateQueueStat();
          handleError(error);
          await loadFolders(true, true, true, 'Restoring mailbox…');
        } finally {
          state.queuePersisting = Math.max(0, state.queuePersisting - 1);
        }
      }

      async function requestDeleteConfirmation(uids) {
        uids = [...new Set((uids || []).map(String).filter(Boolean))];
        if (!uids.length) return null;
        if (!initialSettings.confirm_delete_messages) return '';
        if (uids.length > 10) {
          return await swalTypedConfirmation(
            'Delete many messages?',
            `${uids.length} messages will be moved to Trash.`,
            'Delete messages'
          );
        }
        const confirmed = await swalConfirm(
          uids.length === 1 ? 'Move email to Trash?' : 'Delete selected messages?',
          uids.length === 1
            ? 'You can still find it in the Trash folder.'
            : `${uids.length} messages will be moved to Trash.`,
          uids.length === 1 ? 'Move' : 'Delete'
        );
        return confirmed ? '' : null;
      }

      async function deleteMessagesWithConfirmation(uids) {
        const confirmation = await requestDeleteConfirmation(uids);
        if (confirmation === null) return;
        await queueDeleteMessages(uids, confirmation);
      }

      async function requestDeleteForeverConfirmation(uids) {
        uids = [...new Set((uids || []).map(String).filter(Boolean))];
        if (!uids.length) return false;
        if (uids.length > 10) {
          return (await swalTypedConfirmation(
            'Delete messages forever?',
            `${uids.length} messages will be permanently deleted. This cannot be undone.`,
            'Delete forever',
            'DELETE FOREVER'
          )) !== null;
        }
        const result = await Swal.fire({
          target: activeSwalTarget(),
          icon: 'warning',
          title: uids.length === 1 ? 'Delete email forever?' : 'Delete selected emails forever?',
          text: uids.length === 1
            ? 'This email will be permanently deleted and cannot be restored.'
            : `${uids.length} emails will be permanently deleted and cannot be restored.`,
          showCancelButton: true,
          confirmButtonText: 'Delete forever',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          confirmButtonColor: '#dc3545'
        });
        return result.isConfirmed;
      }

      async function runDirectTrashOperation(uids, operation) {
        uids = [...new Set((uids || []).map(String).filter(Boolean))];
        if (!uids.length) return;
        if (!currentFolderIsTrash()) {
          toast('This action is available only in the Trash folder.', 'warning');
          return;
        }

        const deletingCurrent = Boolean(
          state.currentMessage && uids.includes(String(state.currentMessage.uid))
        );
        const completedUids = [];
        try {
          showSpinner(operation === 'restore' ? 'Restoring to Inbox…' : 'Deleting forever…');
          for (const uidChunk of chunkUids(uids)) {
            const result = await api('bulk_messages', {
              folder: state.folder,
              uids: uidChunk,
              operation,
              confirmation: ''
            }, {spinner: false});
            const affected = Math.max(0, Number(result.affected || 0));
            if (affected > 0) {
              completedUids.push(...uidChunk.slice(0, affected));
            }
          }
        } catch (error) {
          if (completedUids.length) {
            if (deletingCurrent && completedUids.includes(String(state.currentMessage?.uid || ''))) {
              state.selectedUid = null;
              state.currentMessage = null;
              clearPreview();
              if (isSinglePaneMobileActive()) setMobilePane('messages');
            }
            applyKnownBulkOperation(completedUids, operation, completedUids.length);
            await loadFolders(false, false, true, 'Updating mailbox counts…');
          }
          handleError(error);
          return;
        } finally {
          hideSpinner();
        }

        if (deletingCurrent) {
          state.selectedUid = null;
          state.currentMessage = null;
          clearPreview();
          if (isSinglePaneMobileActive()) setMobilePane('messages');
        }
        applyKnownBulkOperation(completedUids, operation, completedUids.length);
        // Re-read authoritative folder counters after the server mutation. This also
        // prevents large bulk operations from leaving approximate unread counts.
        await loadFolders(false, false, true, 'Updating mailbox counts…');
        if (operation === 'restore') {
          toast(`${completedUids.length} email${completedUids.length === 1 ? '' : 's'} restored to Inbox.`);
        } else {
          toast(`${completedUids.length} email${completedUids.length === 1 ? '' : 's'} permanently deleted.`);
        }
      }

      async function restoreMessages(uids) {
        await runDirectTrashOperation(uids, 'restore');
      }

      async function deleteMessagesForever(uids) {
        if (!await requestDeleteForeverConfirmation(uids)) return;
        await runDirectTrashOperation(uids, 'delete_forever');
      }

      async function runBulkOperation(operation) {
        const uids = [...state.selectedUids];
        if (!uids.length) return;
        if (operation === 'delete') {
          await deleteMessagesWithConfirmation(uids);
          return;
        }
        if (operation === 'restore') {
          await restoreMessages(uids);
          return;
        }
        if (operation === 'delete_forever') {
          await deleteMessagesForever(uids);
          return;
        }
        let confirmation = '';
        const selectedAcrossAllPages = state.allPagesSelected;
        try {
          let affected = 0;
          showSpinner('Updating selected messages…');
          for (const uidChunk of chunkUids(uids)) {
            const result = await api('bulk_messages', {
              folder: state.folder,
              uids: uidChunk,
              operation,
              confirmation
            }, {spinner: false});
            affected += Math.max(0, Number(result.affected || 0));
          }
          const labels = {delete: 'deleted', read: 'marked read', unread: 'marked unread'};
          toast(`${affected} message${affected === 1 ? '' : 's'} ${labels[operation]}.`);
          applyKnownBulkOperation(uids, operation, affected);
          if (selectedAcrossAllPages && (operation === 'read' || operation === 'unread')) {
            invalidateMessageListCacheForFolder(state.folder);
            await loadMessages(state.page, false, true, 'Refreshing message status…');
          }
        } catch (error) {
          handleError(error);
        } finally {
          hideSpinner();
        }
      }


      function calendarMonthShift(month, delta) {
        const match = String(month || '').match(/^(\d{4})-(\d{2})$/);
        if (!match) return currentCalendarMonth();
        const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1 + Number(delta || 0), 1, 12, 0, 0));
        return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
      }

      function calendarMonthLabel(month) {
        const match = String(month || '').match(/^(\d{4})-(\d{2})$/);
        if (!match) return month;
        const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1, 12, 0, 0));
        try {
          return new Intl.DateTimeFormat(undefined, {month: 'long', year: 'numeric'}).format(date);
        } catch (error) {
          return `${match[2]}-${match[1]}`;
        }
      }

      function calendarTodayKey() {
        const parts = zonedDateParts(Date.now() / 1000);
        return `${String(parts.year || '').padStart(4, '0')}-${String(parts.month || '').padStart(2, '0')}-${String(parts.day || '').padStart(2, '0')}`;
      }

      function showCalendarLoading(month) {
        const content = $('#previewContent');
        content.innerHTML = `
          <div class="pse-calendar-view">
            <div class="pse-calendar-toolbar">
              <button class="btn btn-sm btn-outline-secondary" type="button" disabled><i class="fa-solid fa-chevron-left"></i></button>
              <div class="fw-semibold flex-grow-1 text-center">${escapeHtml(calendarMonthLabel(month))}</div>
              <button class="btn btn-sm btn-outline-secondary" type="button" disabled><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="pse-calendar-empty-month">
              <div>
                <div class="spinner-border text-primary mb-3"></div>
                <div>Loading calendar…</div>
              </div>
            </div>
          </div>
        `;
      }

      function renderCalendarView(data) {
        if (!state.calendarActive) return;
        const month = String(data?.month || state.calendarMonth || currentCalendarMonth());
        state.calendarMonth = month;
        const match = month.match(/^(\d{4})-(\d{2})$/);
        if (!match) return;
        const year = Number(match[1]);
        const monthNumber = Number(match[2]);
        const firstDay = new Date(Date.UTC(year, monthNumber - 1, 1, 12, 0, 0));
        const daysInMonth = new Date(Date.UTC(year, monthNumber, 0, 12, 0, 0)).getUTCDate();
        const leading = (firstDay.getUTCDay() + 6) % 7;
        const dayMap = new Map((data.days || []).map(day => [String(day.date || ''), day]));
        const todayKey = calendarTodayKey();
        const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        let cells = weekdays.map(day => `<div class="pse-calendar-weekday">${day}</div>`).join('');
        for (let index = 0; index < leading; index++) {
          cells += '<div class="pse-calendar-blank" aria-hidden="true"></div>';
        }

        for (let dayNumber = 1; dayNumber <= daysInMonth; dayNumber++) {
          const date = `${year}-${String(monthNumber).padStart(2, '0')}-${String(dayNumber).padStart(2, '0')}`;
          const day = dayMap.get(date) || {count: 0, distinctSenders: 0, emails: []};
          const emails = Array.isArray(day.emails) ? day.emails : [];
          const emailHtml = emails.map(email => {
            const sender = String(email.sender || email.senderEmail || '(Unknown sender)');
            const subject = String(email.subject || '(No subject)');
            return `
              <span class="pse-calendar-email" title="${escapeHtml(`${sender} — ${subject}`)}">
                <span class="pse-calendar-email-sender">${escapeHtml(sender)}</span>
                <span class="pse-calendar-email-subject">${escapeHtml(subject)}</span>
              </span>
            `;
          }).join('');
          const mailCount = Math.max(0, Number(day.count || emails.length || 0));
          const senderCount = Math.max(0, Number(day.distinctSenders || 0));
          const stats = mailCount > 0
            ? `
              <span class="pse-calendar-stats">
                <span class="pse-calendar-stat" title="${mailCount} email${mailCount === 1 ? '' : 's'}">${mailCount} <i class="fa-regular fa-envelope"></i></span>
                <span class="pse-calendar-stat" title="${senderCount} distinct sender${senderCount === 1 ? '' : 's'}">${senderCount} <i class="fa-regular fa-user"></i></span>
              </span>
            `
            : '';
          cells += `
            <button
              class="pse-calendar-day${mailCount ? ' has-mail' : ''}${date === todayKey ? ' today' : ''}"
              type="button"
              data-calendar-date="${date}"
              title="Show the normal email list starting at ${escapeHtml(formatIsoDateLabel(date))}"
            >
              <span class="pse-calendar-day-head">
                <span class="pse-calendar-number">${dayNumber}</span>
                ${stats}
              </span>
              <span class="pse-calendar-emails">${emailHtml}</span>
            </button>
          `;
        }

        const trailing = (7 - ((leading + daysInMonth) % 7)) % 7;
        for (let index = 0; index < trailing; index++) {
          cells += '<div class="pse-calendar-blank" aria-hidden="true"></div>';
        }

        const monthNames = [
          'January', 'February', 'March', 'April', 'May', 'June',
          'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const currentYear = Number(zonedDateParts(Date.now() / 1000).year || new Date().getUTCFullYear());
        const oldestYear = currentYear - 10;
        const monthOptions = monthNames.map((name, index) => {
          const value = String(index + 1).padStart(2, '0');
          return `<option value="${value}"${index + 1 === monthNumber ? ' selected' : ''}>${name}</option>`;
        }).join('');
        const yearOptions = Array.from({length: 11}, (_, index) => currentYear - index).map(value =>
          `<option value="${value}"${value === year ? ' selected' : ''}>${value}</option>`
        ).join('');

        const content = $('#previewContent');
        content.innerHTML = `
          <div class="pse-calendar-view">
            <div class="pse-calendar-toolbar">
              <button class="btn btn-sm btn-outline-secondary" id="calendarPreviousMonth" type="button" title="Previous month">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <div class="dropdown flex-grow-1 text-center">
                <button
                  class="btn btn-sm btn-light dropdown-toggle pse-calendar-title"
                  id="calendarTitle"
                  type="button"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                  title="Choose month and year"
                >${escapeHtml(calendarMonthLabel(month))}</button>
                <div class="dropdown-menu p-3">
                  <div class="row g-2">
                    <div class="col-7">
                      <label class="form-label small mb-1" for="calendarMonthPicker">Month</label>
                      <select class="form-select form-select-sm" id="calendarMonthPicker">
                        ${monthOptions}
                      </select>
                    </div>
                    <div class="col-5">
                      <label class="form-label small mb-1" for="calendarYearPicker">Year</label>
                      <select class="form-select form-select-sm" id="calendarYearPicker" title="Available from ${oldestYear} to ${currentYear}">
                        ${yearOptions}
                      </select>
                    </div>
                  </div>
                  <button class="btn btn-sm btn-primary w-100 mt-2" id="calendarMonthGo" type="button">Go</button>
                </div>
              </div>
              <span class="small text-secondary text-nowrap" title="Emails in this month">${Math.max(0, Number(data.total || 0))} emails</span>
              <button class="btn btn-sm btn-outline-secondary" id="calendarNextMonth" type="button" title="Next month">
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
            <div class="pse-calendar-scroll">
              <div class="pse-calendar-grid">${cells}</div>
            </div>
          </div>
        `;

        $('#calendarPreviousMonth').addEventListener('click', () => loadCalendarMonth(calendarMonthShift(month, -1)));
        $('#calendarNextMonth').addEventListener('click', () => loadCalendarMonth(calendarMonthShift(month, 1)));
        const goToPickedMonth = () => {
          const pickedMonth = String($('#calendarMonthPicker')?.value || '').padStart(2, '0');
          const pickedYear = String($('#calendarYearPicker')?.value || '');
          const picked = `${pickedYear}-${pickedMonth}`;
          if (/^\d{4}-\d{2}$/.test(picked)) {
            bootstrap.Dropdown.getOrCreateInstance($('#calendarTitle')).hide();
            loadCalendarMonth(picked);
          }
        };
        $('#calendarMonthGo').addEventListener('click', goToPickedMonth);
        $('#calendarMonthPicker').addEventListener('keydown', event => {
          if (event.key === 'Enter') {
            event.preventDefault();
            goToPickedMonth();
          }
        });
        $('#calendarYearPicker').addEventListener('keydown', event => {
          if (event.key === 'Enter') {
            event.preventDefault();
            goToPickedMonth();
          }
        });
        $$('.pse-calendar-day', content).forEach(day => {
          day.addEventListener('click', () => openMessagesStartingDate(day.dataset.calendarDate));
        });
      }

      async function loadCalendarMonth(month, forceRefresh = false) {
        if (!initialSettings.show_calendar || !state.calendarActive) return;
        month = /^\d{4}-\d{2}$/.test(String(month || '')) ? String(month) : currentCalendarMonth();
        state.calendarMonth = month;
        const requestSerial = ++state.calendarRequestSerial;
        const context = {
          folder: state.folder,
          search: state.search,
          senderFilter: state.senderFilter,
          unreadOnly: state.unreadOnly,
          attachmentFilter: state.attachmentFilter,
          month
        };
        const key = calendarCacheKey(
          context.month,
          context.folder,
          context.search,
          context.senderFilter,
          context.unreadOnly,
          context.attachmentFilter
        );
        let data = !forceRefresh ? state.calendarCache.get(key) : null;
        if (!data) {
          showCalendarLoading(month);
          try {
            const result = await api('calendar_month', {
              folder: context.folder,
              month: context.month,
              search: context.search,
              senderFilter: context.senderFilter,
              unreadOnly: context.unreadOnly,
              attachmentFilter: context.attachmentFilter,
              forceRefresh
            }, {spinner: false});
            data = {
              ...result.data,
              _folder: context.folder,
              _search: context.search,
              _senderFilter: context.senderFilter,
              _unreadOnly: context.unreadOnly,
              _attachmentFilter: context.attachmentFilter
            };
            state.calendarCache.set(key, data);
            updateLastSyncStatus(result.cache, `${state.folderName} calendar`);
          } catch (error) {
            if (requestSerial !== state.calendarRequestSerial || !state.calendarActive) return;
            $('#previewContent').innerHTML = `
              <div class="pse-calendar-empty-month">
                <div>
                  <i class="fa-solid fa-circle-exclamation fa-2x mb-2"></i><br>
                  ${escapeHtml(error.message)}
                </div>
              </div>
            `;
            handleError(error);
            return;
          }
        }
        if (
          requestSerial !== state.calendarRequestSerial ||
          !state.calendarActive ||
          state.folder !== context.folder ||
          state.search !== context.search ||
          state.senderFilter !== context.senderFilter ||
          state.unreadOnly !== context.unreadOnly ||
          state.attachmentFilter !== context.attachmentFilter ||
          state.calendarMonth !== context.month
        ) {
          return;
        }
        renderCalendarView(data);
      }

      async function toggleCalendarView() {
        if (!initialSettings.show_calendar) return;
        if (state.calendarActive) {
          state.calendarActive = false;
          state.calendarRequestSerial++;
          updateMailboxViewControls();
          if (state.currentMessage) {
            renderPreview();
          } else {
            clearPreview();
          }
          return;
        }
        state.calendarActive = true;
        state.calendarMonth = state.startDate
          ? state.startDate.slice(0, 7)
          : (state.calendarMonth || currentCalendarMonth());
        updateMailboxViewControls();
        await loadCalendarMonth(state.calendarMonth);
      }

      async function openMessagesStartingDate(date) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(date || ''))) return;
        state.calendarActive = false;
        state.calendarRequestSerial++;
        state.startDate = String(date);
        state.page = 1;
        state.selectedUid = null;
        state.currentMessage = null;
        state.multiSelect = false;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        updateMailboxViewControls();
        clearPreview();
        try {
          await loadMessages(1, true, false, `Loading emails starting ${formatIsoDateLabel(date)}…`);
        } catch (error) {
          handleError(error);
        }
      }

      async function openMessage(uid, loadRemote = false) {
        const openSerial = ++state.messageOpenSerial;
        const requestedFolder = String(state.folder);
        try {
          if (state.calendarActive) {
            state.calendarActive = false;
            state.calendarRequestSerial++;
            updateCalendarButton();
          }
          uid = String(uid);
          state.selectedUid = uid;
          renderMessages();
          const listMessage = state.messages.find(message => message.uid === uid);
          const wasUnread = Boolean(listMessage && !listMessage.seen);
          const cacheKey = messageDetailsCacheKey(requestedFolder, uid, loadRemote);
          let message = state.messageDetailsCache.get(cacheKey);
          if (!message) {
            const result = await fetchForegroundMessage(requestedFolder, uid, loadRemote);
            if (
              openSerial !== state.messageOpenSerial ||
              requestedFolder !== String(state.folder) ||
              uid !== String(state.selectedUid || '')
            ) return;
            message = {...result.message, uid: String(result.message.uid)};
            state.messageDetailsCache.set(cacheKey, message);
            if (loadRemote) {
              state.messageDetailsCache.set(messageDetailsCacheKey(requestedFolder, uid, false), message);
            }
          }
          if (
            openSerial !== state.messageOpenSerial ||
            requestedFolder !== String(state.folder) ||
            uid !== String(state.selectedUid || '')
          ) return;
          if (Number(message.timestamp || 0) <= 0 && Number(listMessage?.timestamp || 0) > 0) {
            message.timestamp = Number(listMessage.timestamp);
            state.messageDetailsCache.set(cacheKey, message);
          }
          message.seen = true;
          state.currentMessage = message;
          updateSameSenderFilterButton();
          if (listMessage) listMessage.seen = true;
          if (wasUnread) {
            invalidateCalendarCacheForFolder(requestedFolder);
            const currentFolder = state.folders.find(folder => folder.id === requestedFolder);
            if (currentFolder) {
              currentFolder.unseen = Math.max(0, Number(currentFolder.unseen || 0) - 1);
              renderFolders();
            }
          }
          rememberCurrentMessageData();
          renderMessages();
          renderPreview();
          if (isSinglePaneMobileActive()) setMobilePane('preview', true);

          // Do not make the preview wait for the provider to update \Seen. The
          // UI is already updated locally; synchronize the server in parallel.
          if (wasUnread) {
            api('set_flag', {
              folder: requestedFolder,
              uid,
              flag: '\\Seen',
              enabled: true
            }, {spinner: false, background: true, keepalive: true}).catch(flagError => {
              console.warn('Unable to synchronize the read flag; the email remains visible.', flagError);
            });
          }

          await maybeSuggestReadContacts(message);
        } catch (error) {
          handleError(error);
        }
      }

      function addressText(items) {
        return (items || []).map(item => item.name ? `${item.name} <${item.email}>` : item.email).join(', ');
      }

      function updateReadContactSelection() {
        const count = $$('.read-contact-select:checked', $('#readContactSuggestionList')).length;
        const button = $('#addReadContactSuggestions');
        button.disabled = count === 0;
        button.innerHTML = `<i class="fa-solid fa-user-plus me-1"></i>Add selected${count ? ` (${count})` : ''}`;
      }

      async function unknownReadContacts(message, excludePrompted = false) {
        if (!message) return [];
        await loadContacts(false, false);
        const known = new Set(state.contacts.map(contact => String(contact.email || '').toLowerCase()));
        const ownAddresses = new Set([
          initialSettings.from_email,
          initialSettings.imap_username,
          initialSettings.smtp_username,
          initialSettings.google_oauth_email
        ].map(value => String(value || '').trim().toLowerCase()).filter(Boolean));
        const candidates = new Map();
        const addCandidate = (person, role) => {
          const email = String(person?.email || '').trim();
          const key = email.toLowerCase();
          if (
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ||
            known.has(key) ||
            ownAddresses.has(key) ||
            (excludePrompted && state.readContactPrompted.has(key))
          ) {
            return;
          }
          if (!candidates.has(key)) {
            candidates.set(key, {
              email,
              name: String(person?.name || '').trim(),
              roles: new Set()
            });
          }
          candidates.get(key).roles.add(role);
        };
        (message.from || []).forEach(person => addCandidate(person, 'Sender'));
        (message.cc || []).forEach(person => addCandidate(person, 'Cc'));
        return [...candidates.values()];
      }

      function updateUnknownContactButton() {
        const button = $('#addUnknownReadContacts');
        if (!button) return;
        const count = state.currentUnknownReadContacts.length;
        button.classList.toggle('d-none', count === 0);
        button.title = count
          ? `Add ${count} email contact${count === 1 ? '' : 's'}`
          : 'All sender and Cc addresses are already in contacts';
        button.setAttribute('aria-label', button.title);
        const badge = button.querySelector('.pse-unknown-count');
        if (badge) badge.textContent = String(count);
      }

      function showReadContactSuggestionModal(unknown, markPrompted) {
        if (!unknown.length || state.readContactPromptOpen) return false;
        if (markPrompted) {
          unknown.forEach(person => state.readContactPrompted.add(person.email.toLowerCase()));
        }
        $('#readContactSuggestionCount').textContent = unknown.length;
        $('#readContactSuggestionList').innerHTML = unknown.map(person => `
          <div class="pse-read-contact-suggestion">
            <input
              class="form-check-input read-contact-select"
              type="checkbox"
              data-email="${escapeHtml(person.email)}"
              checked
              aria-label="Select ${escapeHtml(person.email)}"
            >
            <div class="min-w-0">
              <input
                class="form-control form-control-sm read-contact-name"
                value="${escapeHtml(person.name)}"
                maxlength="160"
                placeholder="Displayed name"
                aria-label="Displayed name for ${escapeHtml(person.email)}"
              >
              <div class="small text-secondary text-truncate mt-1" title="${escapeHtml(person.email)}">${escapeHtml(person.email)}</div>
            </div>
            <span class="badge text-bg-light">${escapeHtml([...person.roles].join(' + '))}</span>
          </div>
        `).join('');
        $('#disableReadContactSuggestions').checked = false;
        $$('.read-contact-select', $('#readContactSuggestionList')).forEach(check => {
          check.addEventListener('change', updateReadContactSelection);
        });
        updateReadContactSelection();
        state.readContactPromptOpen = true;
        readContactSuggestionModal.show();
        setTimeout(() => $('.read-contact-name', $('#readContactSuggestionList'))?.focus(), 200);
        return true;
      }

      async function openUnknownReadContacts() {
        if (!state.currentMessage) return;
        try {
          const unknown = await unknownReadContacts(state.currentMessage, false);
          state.currentUnknownReadContacts = unknown;
          updateUnknownContactButton();
          if (!unknown.length) {
            toast('All sender and Cc addresses are already in contacts.', 'info');
            return;
          }
          showReadContactSuggestionModal(unknown, false);
        } catch (error) {
          handleError(error);
        }
      }

      async function maybeSuggestReadContacts(message) {
        if (!message) return;
        try {
          const allUnknown = await unknownReadContacts(message, false);
          if (!state.currentMessage || String(state.currentMessage.uid) !== String(message.uid)) return;
          state.currentUnknownReadContacts = allUnknown;
          updateUnknownContactButton();
          if (!initialSettings.suggest_unknown_read_contacts || state.readContactPromptOpen) return;
          const automaticUnknown = allUnknown.filter(person =>
            !state.readContactPrompted.has(person.email.toLowerCase())
          );
          showReadContactSuggestionModal(automaticUnknown, true);
        } catch (error) {
          console.error('Unable to check message contacts:', error);
        }
      }

      async function applyReadContactPromptPreference() {
        if (!$('#disableReadContactSuggestions').checked) return;
        try {
          const result = await api('set_unknown_read_contact_suggestions', {
            enabled: false
          }, {spinner: false});
          initialSettings.suggest_unknown_read_contacts = Boolean(result.enabled);
          $('#suggest_unknown_read_contacts').checked = Boolean(result.enabled);
        } catch (error) {
          handleError(error);
        }
      }

      async function dismissReadContactSuggestions() {
        await applyReadContactPromptPreference();
        state.readContactPromptOpen = false;
        readContactSuggestionModal.hide();
      }

      async function addSuggestedReadContacts() {
        const contacts = $$('.read-contact-select:checked', $('#readContactSuggestionList')).map(check => {
          const row = check.closest('.pse-read-contact-suggestion');
          return {
            email: check.dataset.email,
            name: row?.querySelector('.read-contact-name')?.value.trim() || ''
          };
        });
        if (!contacts.length) return;
        try {
          const result = await api('save_contacts_batch', {
            contacts
          }, {spinnerText: 'Adding contacts…'});
          await loadContacts(true, false);
          state.currentUnknownReadContacts = state.currentMessage
            ? await unknownReadContacts(state.currentMessage, false)
            : [];
          updateUnknownContactButton();
          await applyReadContactPromptPreference();
          state.readContactPromptOpen = false;
          readContactSuggestionModal.hide();
          toast(`${result.saved} contact${Number(result.saved) === 1 ? '' : 's'} added.`);
        } catch (error) {
          handleError(error);
        }
      }

      function renderPreview() {
        const message = state.currentMessage;
        if (!message) {
          clearPreview();
          return;
        }
        const content = $('#previewContent');
        const inTrash = currentFolderIsTrash();
        content.innerHTML = `
          <div class="pse-panel-toolbar d-flex flex-wrap align-items-center gap-1">
            <button class="btn btn-sm btn-outline-secondary" id="replyMessage" title="Reply"><i class="fa-solid fa-reply"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="replyAllMessage" title="Reply all"><i class="fa-solid fa-reply-all"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="forwardMessage" title="Forward"><i class="fa-solid fa-share"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="markUnread" title="Mark unread"><i class="fa-regular fa-envelope"></i></button>
            <button class="btn btn-sm btn-outline-secondary d-none position-relative" id="addUnknownReadContacts" title="Add email contacts" aria-label="Add email contacts">
              <i class="fa-solid fa-users"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-primary pse-unknown-count">0</span>
            </button>
            ${inTrash ? `
              <button class="btn btn-sm btn-outline-primary" id="restoreMessage" title="Restore to Inbox"><i class="fa-solid fa-trash-arrow-up"></i></button>
              <button class="btn btn-sm btn-outline-danger" id="deleteForeverMessage" title="Delete forever"><i class="fa-solid fa-trash-can"></i></button>
            ` : `
              <button class="btn btn-sm btn-outline-danger" id="trashMessage" title="Move to Trash"><i class="fa-solid fa-trash"></i></button>
            `}
            <div class="dropdown ms-auto">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fa-solid fa-file-export me-1"></i>Export</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" id="exportEml"><i class="fa-solid fa-envelope me-2 text-secondary"></i>Original email (.eml)</button></li>
                <li><button class="dropdown-item" id="exportTxt"><i class="fa-solid fa-file-lines me-2 text-secondary"></i>TXT — readable text only</button></li>
                <li><button class="dropdown-item" id="exportRawTxt"><i class="fa-solid fa-code me-2 text-secondary"></i>RAW TXT — complete email source</button></li>
                <li><button class="dropdown-item" id="exportPdf"><i class="fa-solid fa-file-pdf me-2 text-danger"></i>Rendered PDF</button></li>
                <li><button class="dropdown-item" id="exportWord"><i class="fa-solid fa-file-word me-2 text-primary"></i>Export Microsoft Word</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" id="exportPng"><i class="fa-regular fa-image me-2 text-success"></i>Full email screenshot (.png)</button></li>
                <li><button class="dropdown-item" id="exportJpg"><i class="fa-solid fa-image me-2 text-warning"></i>Full email screenshot (.jpg)</button></li>
              </ul>
            </div>
          </div>
          ${message.largeMessage ? `
            <div class="alert alert-warning rounded-0 border-start-0 border-end-0 mb-0" role="alert">
              <div class="fw-bold">
                <i class="fa-solid fa-gauge-high me-2"></i>Large message: ${escapeHtml(formatBytes(message.size))}
              </div>
              <div class="small mt-1">
                To keep this preview fast, remote images are blocked and attachment bodies are not downloaded as part of the initial message fetch.
                ${message.attachments.length
                  ? 'Open only the file you need below, or use Download all.'
                  : 'Use the image-loading control only if you need it.'}
              </div>
            </div>
          ` : ''}
          ${message.impersonationWarning ? `
            <div class="pse-spam-warning" role="alert">
              <div class="pse-spam-warning-title mb-2">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Possible brand impersonation
              </div>
              <div><b>MARCO will tell you this is probably spam because the sender email does not match the name.</b></div>
              <div class="mt-2">
                Sender <b>PRETENDS</b> to be
                <b>${escapeHtml(message.impersonationWarning.brand)}</b>,
                but the email domain is
                <span class="badge text-bg-light">${escapeHtml(message.impersonationWarning.senderDomain)}</span>.
              </div>
              <div class="small mt-2">This is a warning heuristic. Verify the sender independently before clicking links or opening files.</div>
            </div>
          ` : ''}
          <div class="pse-preview-header">
            <div class="pse-preview-subject mb-2">${escapeHtml(message.subject)}</div>
            <div class="d-flex gap-2">
              <div class="flex-grow-1 min-w-0">
                <div><b>From:</b> ${escapeHtml(addressText(message.from))}</div>
                <div class="pse-address-line text-truncate" title="${escapeHtml(addressText(message.to))}"><b>To:</b> ${escapeHtml(addressText(message.to))}</div>
                ${message.cc?.length ? `<div class="pse-address-line text-truncate"><b>Cc:</b> ${escapeHtml(addressText(message.cc))}</div>` : ''}
              </div>
              <div class="text-secondary small text-nowrap">${escapeHtml(message.date)}</div>
            </div>
            ${message.remoteImagesBlocked ? '<button class="btn btn-sm btn-outline-secondary mt-2" id="loadRemoteImages"><i class="fa-regular fa-image me-1"></i>Load remote images</button>' : ''}
          </div>
          <div class="pse-scroll flex-grow-1" id="emailFrameHolder"></div>
          ${message.attachments.length ? `
            <div class="pse-attachments">
              <div class="d-flex align-items-center gap-2 mb-1">
                <div class="fw-semibold flex-grow-1">
                  <i class="fa-solid fa-paperclip me-1"></i>${message.attachments.length}
                  attachment${message.attachments.length === 1 ? '' : 's'}
                </div>
                <button class="btn btn-sm btn-primary" id="downloadAllAttachments" type="button">
                  <i class="fa-solid fa-file-zipper me-1"></i>Download all
                </button>
              </div>
              <div class="small text-secondary mb-2">
                ${Number(message.embeddedImagesBlocked || 0)
                  ? `${Number(message.embeddedImagesBlocked)} embedded image${Number(message.embeddedImagesBlocked) === 1 ? '' : 's'} hidden. `
                  : ''}
                Attachments stay on demand; inline and preview images load lazily after the message body.
              </div>
              <div class="d-flex flex-wrap gap-2" id="previewAttachments"></div>
            </div>
          ` : ''}
        `;
        const iframe = document.createElement('iframe');
        iframe.className = 'pse-email-frame';
        iframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
        iframe.setAttribute('referrerpolicy', 'no-referrer');
        const bodyHtml = String(message.html || '');
        const inlineAttachmentHtml = initialSettings.show_image_attachments_inline
          ? (message.attachments || [])
              .filter(attachment =>
                attachment.previewable &&
                /^image\/(?:png|jpe?g|gif|webp|avif|bmp)$/i.test(String(attachment.mime || '')) &&
                !(attachment.inline && bodyHtml.includes(String(attachment.url || '')))
              )
              .map(attachment => `
                <figure class="pse-inline-image-attachment">
                  <img src="${escapeHtml(attachment.url)}" alt="${escapeHtml(attachment.filename)}" loading="lazy" decoding="async">
                  <figcaption>${escapeHtml(attachment.filename)} (${escapeHtml(formatBytes(attachment.size))})</figcaption>
                </figure>
              `).join('')
          : '';
        const inlineAttachmentSection = inlineAttachmentHtml
          ? `<section class="pse-inline-image-attachments"><h3>Image attachments</h3>${inlineAttachmentHtml}</section>`
          : '';
        const imageSources = 'data: blob: http: https:';
        const csp = `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src ${imageSources}; style-src 'unsafe-inline'">`;
        iframe.srcdoc = `<!doctype html><html><head><meta charset="UTF-8">${csp}<style>body{margin:20px;font:14px/1.55 Arial,sans-serif;color:#242d3c;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}.pse-inline-image-attachments{margin-top:28px;padding-top:18px;border-top:1px solid #dfe4ec}.pse-inline-image-attachments h3{margin:0 0 12px;font-size:16px}.pse-inline-image-attachment{margin:0 0 18px;padding:10px;border:1px solid #dfe4ec;border-radius:8px}.pse-inline-image-attachment img{display:block;margin:0 auto}.pse-inline-image-attachment figcaption{margin-top:8px;color:#687385;font-size:12px;overflow-wrap:anywhere}</style></head><body>${bodyHtml}${inlineAttachmentSection}</body></html>`;
        iframe.addEventListener('load', () => {
          try {
            bindMobileSwipeBack(iframe.contentDocument);
          } catch (error) {
            console.debug('Unable to attach mobile swipe navigation inside the email frame.', error);
          }
        }, {once: true});
        $('#emailFrameHolder').appendChild(iframe);
        if (message.attachments.length) {
          for (const attachment of message.attachments) {
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            const open = document.createElement(attachment.previewable ? 'a' : 'button');
            open.className = 'btn btn-outline-secondary';
            if (attachment.previewable) {
              open.href = attachment.url;
              open.target = '_blank';
              open.rel = 'noopener';
            } else {
              open.type = 'button';
              open.addEventListener('click', () => saveAttachment(attachment));
            }
            open.innerHTML = `<i class="fa-solid ${attachment.inline ? 'fa-image' : 'fa-paperclip'} me-1"></i>${escapeHtml(attachment.filename)} <span class="text-secondary">(${formatBytes(attachment.size)})</span>`;
            const preview = document.createElement('button');
            preview.type = 'button';
            preview.className = 'btn btn-outline-secondary';
            preview.title = 'Open temporary preview';
            preview.innerHTML = '<i class="fa-solid fa-eye"></i>';
            preview.addEventListener('click', () => previewAttachmentTemporary(attachment));
            const download = document.createElement('button');
            download.type = 'button';
            download.className = 'btn btn-outline-secondary';
            download.title = 'Save attachment';
            download.innerHTML = '<i class="fa-solid fa-download"></i>';
            download.addEventListener('click', () => saveAttachment(attachment));
            group.append(open, preview, download);
            $('#previewAttachments').appendChild(group);
          }
        }
        $('#replyMessage').addEventListener('click', () => replyToMessage('reply'));
        $('#replyAllMessage').addEventListener('click', () => replyToMessage('replyAll'));
        $('#forwardMessage').addEventListener('click', () => replyToMessage('forward'));
        $('#markUnread').addEventListener('click', markCurrentUnread);
        $('#addUnknownReadContacts').addEventListener('click', openUnknownReadContacts);
        updateUnknownContactButton();
        $('#trashMessage')?.addEventListener('click', moveCurrent);
        $('#restoreMessage')?.addEventListener('click', restoreCurrent);
        $('#deleteForeverMessage')?.addEventListener('click', deleteCurrentForever);
        $('#exportEml').addEventListener('click', () => exportCurrent('eml'));
        $('#exportTxt').addEventListener('click', () => exportCurrent('txt'));
        $('#exportRawTxt').addEventListener('click', () => exportCurrent('raw_txt'));
        $('#exportPdf').addEventListener('click', () => exportCurrent('pdf'));
        $('#exportWord').addEventListener('click', () => exportCurrent('word'));
        $('#exportPng').addEventListener('click', () => exportCurrent('png'));
        $('#exportJpg').addEventListener('click', () => exportCurrent('jpg'));
        $('#downloadAllAttachments')?.addEventListener('click', () => saveAllAttachments(message));
        $('#loadRemoteImages')?.addEventListener('click', () => openMessage(message.uid, true));
      }

      function clearPreview() {
        if (state.calendarActive && initialSettings.show_calendar) {
          updateSameSenderFilterButton();
          return;
        }
        $('#previewContent').innerHTML = '<div class="pse-empty"><div><i class="fa-regular fa-envelope-open fa-3x mb-3"></i><br>Select an email to preview it</div></div>';
        updateSameSenderFilterButton();
      }

      async function markCurrentUnread() {
        if (!state.currentMessage) return;
        try {
          await api('set_flag', {
            folder: state.folder,
            uid: state.currentMessage.uid,
            flag: '\\Seen',
            enabled: false
          });
          const listMessage = state.messages.find(item => item.uid === state.currentMessage.uid);
          if (listMessage) listMessage.seen = false;
          state.currentMessage.seen = false;
          for (const [key, cachedMessage] of state.messageDetailsCache.entries()) {
            if (key.includes(`|${String(state.currentMessage.uid)}|`)) {
              cachedMessage.seen = false;
              state.messageDetailsCache.set(key, cachedMessage);
            }
          }
          const currentFolder = state.folders.find(folder => folder.id === state.folder);
          if (currentFolder) {
            currentFolder.unseen = Number(currentFolder.unseen || 0) + 1;
            renderFolders();
          }
          invalidateCalendarCacheForFolder(state.folder);
          rememberCurrentMessageData();
          renderMessages();
          toast('Message marked unread.');
        } catch (error) {
          handleError(error);
        }
      }

      async function moveCurrent() {
        if (!state.currentMessage) return;
        await deleteMessagesWithConfirmation([state.currentMessage.uid]);
      }

      async function restoreCurrent() {
        if (!state.currentMessage) return;
        await restoreMessages([state.currentMessage.uid]);
      }

      async function deleteCurrentForever() {
        if (!state.currentMessage) return;
        await deleteMessagesForever([state.currentMessage.uid]);
      }

      function exportFileBase() {
        return (state.currentMessage?.subject || 'email')
          .replace(/[^a-z0-9_-]+/gi, '_')
          .replace(/^_+|_+$/g, '')
          .slice(0, 80) || 'email';
      }

      function exportTypeDetails(type) {
        return {
          eml: {suffix: '.eml', mime: 'message/rfc822', description: 'Email message'},
          txt: {suffix: '.txt', mime: 'text/plain', description: 'Text file'},
          raw_txt: {suffix: '.raw.txt', pickerSuffix: '.txt', mime: 'text/plain', description: 'Raw text file'},
          pdf: {suffix: '.pdf', mime: 'application/pdf', description: 'PDF document'},
          word: {suffix: '.doc', mime: 'application/msword', description: 'Microsoft Word document'},
          png: {suffix: '.png', mime: 'image/png', description: 'PNG image'},
          jpg: {suffix: '.jpg', mime: 'image/jpeg', description: 'JPEG image'}
        }[type] || null;
      }

      function normalizeExportFilename(filename, type) {
        const details = exportTypeDetails(type);
        if (!details) return filename;
        let value = String(filename || '').trim().replace(/[\/\\:*?"<>|\x00-\x1f]/g, '_');
        if (!value) value = exportFileBase() + details.suffix;
        const expected = details.suffix.toLowerCase();
        if (!value.toLowerCase().endsWith(expected)) {
          const pickerExpected = String(details.pickerSuffix || details.suffix).toLowerCase();
          if (type === 'raw_txt' && value.toLowerCase().endsWith(pickerExpected)) {
            value = value.slice(0, -pickerExpected.length) + details.suffix;
          } else {
            value += details.suffix;
          }
        }
        return value;
      }

      async function chooseExportDestination(type) {
        const details = exportTypeDetails(type);
        if (!details) throw new Error('Unsupported export format.');
        const suggestedName = normalizeExportFilename(exportFileBase() + details.suffix, type);

        if (typeof window.showSaveFilePicker === 'function' && window.isSecureContext) {
          try {
            const pickerSuffix = details.pickerSuffix || details.suffix;
            const handle = await window.showSaveFilePicker({
              id: `pse-email-export-${type}`,
              suggestedName,
              startIn: type === 'png' || type === 'jpg' ? 'pictures' : 'downloads',
              types: [{
                description: details.description,
                accept: {[details.mime]: [pickerSuffix]}
              }]
            });
            return {handle, filename: normalizeExportFilename(handle.name || suggestedName, type)};
          } catch (error) {
            if (error?.name === 'AbortError') return null;
            console.warn('Native Save As picker unavailable; using browser download fallback.', error);
          }
        }

        const result = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Save export as',
          text: 'Choose the file name. Your browser controls the destination folder for normal downloads.',
          input: 'text',
          inputValue: suggestedName,
          showCancelButton: true,
          confirmButtonText: 'Save',
          inputValidator: value => String(value || '').trim() ? undefined : 'Enter a file name.'
        });
        if (!result.isConfirmed) return null;
        return {handle: null, filename: normalizeExportFilename(result.value, type)};
      }

      async function saveExportBlob(blob, destination) {
        if (!destination) return false;
        if (destination.handle) {
          const writable = await destination.handle.createWritable();
          await writable.write(blob);
          await writable.close();
          return true;
        }
        downloadBlob(blob, destination.filename);
        return true;
      }


      function normalizeAttachmentFilename(filename, fallback = 'attachment.bin') {
        let value = String(filename || '').trim().replace(/[\/\\:*?"<>|\x00-\x1f]/g, '_');
        return value || fallback;
      }

      async function chooseAttachmentDestination(filename, mime = 'application/octet-stream', pickerId = 'pse-attachment-save') {
        const safeName = normalizeAttachmentFilename(filename);

        if (typeof window.showSaveFilePicker === 'function' && window.isSecureContext) {
          try {
            const handle = await window.showSaveFilePicker({
              id: pickerId,
              suggestedName: safeName,
              startIn: 'downloads'
            });
            return {handle, filename: normalizeAttachmentFilename(handle.name || safeName, safeName)};
          } catch (error) {
            if (error?.name === 'AbortError') return null;
            console.warn('Native attachment Save As picker unavailable; using browser download fallback.', error);
          }
        }

        const result = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Save attachment as',
          text: 'Choose the file name. Your browser controls the destination folder for normal downloads.',
          input: 'text',
          inputValue: safeName,
          showCancelButton: true,
          confirmButtonText: 'Save',
          inputValidator: value => String(value || '').trim() ? undefined : 'Enter a file name.'
        });
        if (!result.isConfirmed) return null;
        return {handle: null, filename: normalizeAttachmentFilename(result.value, safeName)};
      }

      async function fetchDownloadBlob(url, spinnerText = 'Downloading…') {
        showSpinner(spinnerText);
        try {
          const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
          });
          if (!response.ok) {
            const message = (await response.text()).trim();
            throw new Error(message || `Download failed (${response.status}).`);
          }
          return await response.blob();
        } finally {
          hideSpinner();
        }
      }

      async function previewAttachmentTemporary(attachment) {
        const filename = normalizeAttachmentFilename(attachment?.filename, 'attachment.bin');
        let previewWindow = null;
        try {
          // Open the tab synchronously so popup blockers do not reject it after the fetch completes.
          previewWindow = window.open('', '_blank');
          if (!previewWindow) {
            throw new Error('The browser blocked the preview window. Allow pop-ups for PSE and try again.');
          }
          previewWindow.document.title = filename;
          previewWindow.document.body.innerHTML = '<div style="font:14px Arial,sans-serif;padding:24px">Loading attachment…</div>';

          const blob = await fetchDownloadBlob(
            String(attachment?.url || ''),
            `Opening ${filename}…`
          );
          const temporaryUrl = URL.createObjectURL(blob);
          previewWindow.location.replace(temporaryUrl);

          // Blob URLs live only in this browser session and are not written by PSE to Downloads.
          // Keep it alive long enough for the new tab/browser viewer to finish consuming it.
          window.setTimeout(() => URL.revokeObjectURL(temporaryUrl), 30 * 60 * 1000);
        } catch (error) {
          if (previewWindow && !previewWindow.closed) {
            try { previewWindow.close(); } catch (closeError) {}
          }
          handleError(error);
        }
      }

      async function saveAttachment(attachment) {
        try {
          const filename = normalizeAttachmentFilename(attachment?.filename, 'attachment.bin');
          const destination = await chooseAttachmentDestination(
            filename,
            String(attachment?.mime || 'application/octet-stream'),
            'pse-attachment-save'
          );
          if (!destination) return;
          const separator = String(attachment?.url || '').includes('?') ? '&' : '?';
          const blob = await fetchDownloadBlob(
            String(attachment?.url || '') + separator + 'download=1',
            `Downloading ${filename}…`
          );
          await saveExportBlob(blob, destination);
        } catch (error) {
          handleError(error);
        }
      }

      async function saveAllAttachments(message) {
        try {
          const uid = String(message?.uid || 'attachments').replace(/[^a-zA-Z0-9_-]+/g, '-');
          const filename = `PSE-attachments-${uid || 'attachments'}.zip`;
          const destination = await chooseAttachmentDestination(
            filename,
            'application/zip',
            'pse-attachment-save-all'
          );
          if (!destination) return;
          const url = String(message?.downloadAllUrl || '');
          if (!url) throw new Error('Attachment archive is not available.');
          const blob = await fetchDownloadBlob(url, 'Creating attachment archive…');
          await saveExportBlob(blob, destination);
        } catch (error) {
          handleError(error);
        }
      }

      async function serverExportBlob(type) {
        const details = {
          eml: ['export_eml', 'Downloading original email…'],
          txt: ['export_txt', 'Creating readable text export…'],
          raw_txt: ['export_raw_txt', 'Downloading complete raw email source…'],
          pdf: ['export_pdf', 'Creating simplified PDF…'],
          word: ['export_word', 'Creating Word document…']
        }[type];
        if (!details) throw new Error('Unsupported server export format.');
        return api(details[0], {
          folder: state.folder,
          uid: state.currentMessage.uid
        }, {blob: true, spinnerText: details[1]});
      }

      function buildEmailExportFrame() {
        const message = state.currentMessage;
        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.setAttribute('referrerpolicy', 'no-referrer');
        iframe.style.cssText = [
          'position:fixed',
          'left:-20000px',
          'top:0',
          'width:900px',
          'height:1200px',
          'border:0',
          'background:#fff',
          'pointer-events:none'
        ].join(';');

        const attachmentImages = (message.attachments || [])
          .filter(attachment =>
            attachment.previewable &&
            /^image\/(?:png|jpe?g|gif|webp|bmp)$/i.test(String(attachment.mime || ''))
          )
          .map(attachment => `
            <figure class="pse-export-attachment">
              <img src="${escapeHtml(attachment.url)}" alt="${escapeHtml(attachment.filename)}">
              <figcaption>
                <span>${escapeHtml(attachment.filename)}</span>
                <span>${escapeHtml(formatBytes(attachment.size))}</span>
              </figcaption>
            </figure>
          `).join('');

        const cc = message.cc?.length
          ? `<div class="pse-export-meta-row"><b>Cc:</b><span>${escapeHtml(addressText(message.cc))}</span></div>`
          : '';
        const attachments = attachmentImages
          ? `<section class="pse-export-attachments"><h2>Image attachments</h2>${attachmentImages}</section>`
          : '';
        const csp = "default-src 'none'; img-src data: blob: http: https:; style-src 'unsafe-inline'";
        iframe.srcdoc = `<!doctype html>
          <html>
            <head>
              <meta charset="UTF-8">
              <meta http-equiv="Content-Security-Policy" content="${csp}">
              <style>
                *{box-sizing:border-box}
                html,body{margin:0;background:#fff;color:#242d3c}
                body{width:900px;font:14px/1.55 Arial,Helvetica,sans-serif}
                .pse-export-sheet{width:900px;padding:34px 40px 46px;background:#fff}
                .pse-export-subject{margin:0 0 18px;font-size:24px;line-height:1.25;color:#172033;overflow-wrap:anywhere}
                .pse-export-meta{margin-bottom:24px;padding:14px 16px;border:1px solid #dfe4ec;border-radius:8px;background:#f8fafc}
                .pse-export-meta-row{display:grid;grid-template-columns:62px minmax(0,1fr);gap:8px;margin:3px 0;overflow-wrap:anywhere}
                .pse-export-message{padding-top:24px;border-top:1px solid #dfe4ec;overflow-wrap:anywhere;word-break:break-word}
                .pse-export-message a{overflow-wrap:anywhere!important;word-break:break-all!important;color:#0b57d0}
                .pse-export-message img,.pse-export-message svg{max-width:100%!important;height:auto!important}
                .pse-export-message table{max-width:100%!important}
                .pse-export-message pre{white-space:pre-wrap!important;overflow-wrap:anywhere!important}
                .pse-export-attachments{margin-top:32px;padding-top:22px;border-top:1px solid #dfe4ec}
                .pse-export-attachments h2{margin:0 0 14px;font-size:17px;color:#172033}
                .pse-export-attachment{margin:0 0 22px;padding:12px;border:1px solid #dfe4ec;border-radius:8px;background:#fff}
                .pse-export-attachment img{display:block;max-width:100%;height:auto;margin:0 auto}
                .pse-export-attachment figcaption{display:flex;justify-content:space-between;gap:16px;margin-top:9px;color:#687385;font-size:12px;overflow-wrap:anywhere}
                .pse-export-image-error{padding:18px;border:1px dashed #c8ced8;color:#687385;background:#f8fafc;text-align:center}
              </style>
            </head>
            <body>
              <article class="pse-export-sheet">
                <h1 class="pse-export-subject">${escapeHtml(message.subject)}</h1>
                <section class="pse-export-meta">
                  <div class="pse-export-meta-row"><b>From:</b><span>${escapeHtml(addressText(message.from))}</span></div>
                  <div class="pse-export-meta-row"><b>To:</b><span>${escapeHtml(addressText(message.to))}</span></div>
                  ${cc}
                  <div class="pse-export-meta-row"><b>Date:</b><span>${escapeHtml(message.date)}</span></div>
                </section>
                <section class="pse-export-message">${message.html}</section>
                ${attachments}
              </article>
            </body>
          </html>`;
        document.body.appendChild(iframe);
        return iframe;
      }

      function waitForExportFrame(iframe) {
        return new Promise((resolve, reject) => {
          let settled = false;
          const finish = () => {
            if (settled) return;
            settled = true;
            const doc = iframe.contentDocument;
            if (!doc?.body) {
              reject(new Error('The email export could not be prepared.'));
              return;
            }
            resolve(doc);
          };
          iframe.addEventListener('load', finish, {once: true});
          setTimeout(finish, 1200);
        });
      }

      function blobAsDataUrl(blob) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(String(reader.result || ''));
          reader.onerror = () => reject(new Error('Unable to embed an export image.'));
          reader.readAsDataURL(blob);
        });
      }

      async function exportImageDataUrl(url) {
        const source = String(url || '').trim();
        if (!source) return '';
        if (/^data:image\//i.test(source)) return source;
        try {
          const absolute = new URL(source, location.href).href;
          const response = await fetch(absolute, {
            credentials: 'same-origin',
            cache: 'force-cache',
            referrerPolicy: 'no-referrer'
          });
          if (!response.ok) return '';
          const blob = await response.blob();
          if (!String(blob.type || '').toLowerCase().startsWith('image/')) return '';
          return await blobAsDataUrl(blob);
        } catch (error) {
          return '';
        }
      }

      async function inlineCssExportImages(cssText) {
        let result = String(cssText || '');
        const pattern = /url\(\s*(?:"([^"]*)"|'([^']*)'|([^)]*?))\s*\)/gi;
        const replacements = new Map();
        for (const match of result.matchAll(pattern)) {
          const url = String(match[1] ?? match[2] ?? match[3] ?? '').trim();
          if (!url || /^data:/i.test(url) || replacements.has(url)) continue;
          replacements.set(url, await exportImageDataUrl(url));
        }
        result = result.replace(pattern, (whole, doubleQuoted, singleQuoted, unquoted) => {
          const url = String(doubleQuoted ?? singleQuoted ?? unquoted ?? '').trim();
          const dataUrl = replacements.get(url);
          return dataUrl ? `url("${dataUrl}")` : whole;
        });
        return result;
      }

      async function inlineExportImages(doc) {
        // Do not use an xlink:href CSS selector here. The colon is namespace syntax
        // in selectors and is rejected by some browsers, even when escaped.
        const imageNodes = Array.from(doc.querySelectorAll('img[src], image'));
        await Promise.all(imageNodes.map(async image => {
          let source = '';
          let attribute = '';
          if (image.tagName.toLowerCase() === 'img') {
            source = image.getAttribute('src') || '';
            attribute = 'src';
          } else {
            source = image.getAttribute('href') ||
              image.getAttributeNS('http://www.w3.org/1999/xlink', 'href') ||
              image.getAttribute('xlink:href') || '';
            attribute = image.hasAttribute('href') ? 'href' : 'xlink:href';
          }
          const dataUrl = await exportImageDataUrl(source);
          if (!dataUrl) return;
          if (attribute === 'src') {
            image.setAttribute('src', dataUrl);
          } else {
            image.setAttribute('href', dataUrl);
            image.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', dataUrl);
          }
          image.removeAttribute('srcset');
          image.removeAttribute('crossorigin');
        }));
        for (const source of Array.from(doc.querySelectorAll('source[srcset]'))) {
          const first = String(source.getAttribute('srcset') || '').split(',')[0].trim().split(/\s+/)[0];
          const dataUrl = await exportImageDataUrl(first);
          if (dataUrl) source.setAttribute('srcset', dataUrl);
        }
        for (const element of Array.from(doc.querySelectorAll('[style*="url("]'))) {
          element.setAttribute('style', await inlineCssExportImages(element.getAttribute('style') || ''));
        }
        for (const style of Array.from(doc.querySelectorAll('style'))) {
          style.textContent = await inlineCssExportImages(style.textContent || '');
        }
      }

      async function waitForExportImages(doc) {
        const images = Array.from(doc.images);
        await Promise.all(images.map(image => new Promise(resolve => {
          if (image.complete) {
            resolve();
            return;
          }
          const finish = () => resolve();
          image.addEventListener('load', finish, {once: true});
          image.addEventListener('error', finish, {once: true});
          setTimeout(finish, 8000);
        })));
        for (const image of images) {
          if (!image.naturalWidth) {
            const placeholder = doc.createElement('div');
            placeholder.className = 'pse-export-image-error';
            placeholder.textContent = image.alt
              ? `Image unavailable: ${image.alt}`
              : 'An image could not be loaded for this export.';
            image.replaceWith(placeholder);
          }
        }
      }

      async function renderEmailCanvas() {
        if (typeof window.html2canvas !== 'function') {
          throw new Error('The email rendering library did not load.');
        }
        const iframe = buildEmailExportFrame();
        try {
          const doc = await waitForExportFrame(iframe);
          await inlineExportImages(doc);
          await waitForExportImages(doc);
          if (doc.fonts?.ready) await doc.fonts.ready;
          const height = Math.max(
            doc.body.scrollHeight,
            doc.documentElement.scrollHeight,
            1
          );
          iframe.style.height = `${height}px`;
          const scale = Math.min(2, Math.max(0.01, 30000 / height));
          return await window.html2canvas(doc.body, {
            backgroundColor: '#ffffff',
            scale,
            useCORS: true,
            allowTaint: false,
            logging: false,
            width: 900,
            height,
            windowWidth: 900,
            windowHeight: height,
            scrollX: 0,
            scrollY: 0
          });
        } finally {
          iframe.remove();
        }
      }

      function canvasBlob(canvas, type, quality) {
        return new Promise((resolve, reject) => {
          canvas.toBlob(blob => {
            if (blob) resolve(blob);
            else reject(new Error('The browser could not create the image file.'));
          }, type, quality);
        });
      }

      function findPdfPageBreak(canvas, startY, idealHeight) {
        const remaining = canvas.height - startY;
        if (remaining <= idealHeight) return canvas.height;
        const context = canvas.getContext('2d', {willReadFrequently: true});
        if (!context) return Math.min(canvas.height, startY + idealHeight);
        const idealEnd = Math.min(canvas.height - 1, startY + idealHeight);
        const searchRadius = Math.min(140, Math.floor(idealHeight * 0.08));
        const from = Math.max(startY + Math.floor(idealHeight * 0.72), idealEnd - searchRadius);
        const to = Math.min(canvas.height - 1, idealEnd + searchRadius);
        const sampleWidth = Math.max(1, canvas.width - 24);
        let bestY = idealEnd;
        let bestScore = Number.POSITIVE_INFINITY;
        for (let y = from; y <= to; y += 2) {
          const row = context.getImageData(12, y, sampleWidth, 1).data;
          let ink = 0;
          for (let x = 0; x < row.length; x += 16) {
            const alpha = row[x + 3];
            if (alpha > 10 && (row[x] < 246 || row[x + 1] < 246 || row[x + 2] < 246)) {
              ink++;
            }
          }
          const distancePenalty = Math.abs(y - idealEnd) / Math.max(1, searchRadius);
          const score = ink + distancePenalty * 2;
          if (score < bestScore) {
            bestScore = score;
            bestY = y;
            if (ink === 0 && Math.abs(y - idealEnd) < 20) break;
          }
        }
        return Math.max(startY + 1, bestY);
      }

      async function renderedPdfBlob(canvas) {
        const JsPdf = window.jspdf?.jsPDF;
        if (!JsPdf) throw new Error('The PDF rendering library did not load.');
        const pdf = new JsPdf({
          orientation: 'portrait',
          unit: 'mm',
          format: 'a4',
          compress: true,
          putOnlyUsedFonts: true
        });
        const pageWidth = 210;
        const pageHeight = 297;
        const margin = 8;
        const contentWidth = pageWidth - margin * 2;
        const contentHeight = pageHeight - margin * 2;
        const idealPixelsPerPage = Math.max(
          1,
          Math.floor(canvas.width * contentHeight / contentWidth)
        );
        let sourceY = 0;
        let page = 0;
        while (sourceY < canvas.height) {
          const endY = findPdfPageBreak(canvas, sourceY, idealPixelsPerPage);
          const sliceHeight = Math.max(1, endY - sourceY);
          const slice = document.createElement('canvas');
          slice.width = canvas.width;
          slice.height = sliceHeight;
          const context = slice.getContext('2d');
          if (!context) throw new Error('The browser could not prepare a PDF page.');
          context.fillStyle = '#ffffff';
          context.fillRect(0, 0, slice.width, slice.height);
          context.drawImage(
            canvas,
            0, sourceY, canvas.width, sliceHeight,
            0, 0, canvas.width, sliceHeight
          );
          if (page > 0) pdf.addPage();
          const imageHeight = Math.min(
            contentHeight,
            sliceHeight * contentWidth / canvas.width
          );
          pdf.addImage(
            slice.toDataURL('image/png'),
            'PNG',
            margin,
            margin,
            contentWidth,
            imageHeight,
            undefined,
            'FAST'
          );
          sourceY = endY;
          page++;
        }
        return pdf.output('blob');
      }

      async function renderedExportBlob(type) {
        showSpinner(type === 'pdf'
          ? 'Rendering email and images for PDF…'
          : `Capturing the full email as ${type.toUpperCase()}…`);
        try {
          if (state.currentMessage.remoteImagesBlocked) {
            toast('Remote images are currently blocked, so the export will match the displayed email. Load them first if wanted.', 'info');
          }
          const canvas = await renderEmailCanvas();
          if (type === 'pdf') {
            return renderedPdfBlob(canvas);
          }
          const mime = type === 'jpg' ? 'image/jpeg' : 'image/png';
          return canvasBlob(canvas, mime, type === 'jpg' ? 0.94 : undefined);
        } finally {
          hideSpinner();
        }
      }

      async function exportCurrent(type) {
        if (!state.currentMessage) return;
        try {
          // Ask for the destination before asynchronous rendering/fetching so browsers
          // that require direct user activation can open their native Save As dialog.
          const destination = await chooseExportDestination(type);
          if (!destination) return;

          let blob;
          if (type === 'eml' || type === 'txt' || type === 'raw_txt' || type === 'word') {
            blob = await serverExportBlob(type);
          } else if (type === 'pdf') {
            try {
              blob = await renderedExportBlob(type);
            } catch (error) {
              toast('Rendered PDF was unavailable. Creating a simplified PDF instead.', 'warning');
              blob = await serverExportBlob('pdf');
            }
          } else if (type === 'png' || type === 'jpg') {
            blob = await renderedExportBlob(type);
          } else {
            throw new Error('Unsupported export format.');
          }

          await saveExportBlob(blob, destination);
          toast(`Saved ${destination.filename}.`);
        } catch (error) {
          handleError(error);
        }
      }

      function setConnection(online) {
        $('#connectionDot').classList.toggle('online', online);
        $('#connectionText').textContent = online ? 'IMAP connected' : 'Not connected';
      }

      function isSinglePaneMobileViewport() {
        return window.matchMedia('(max-width: 900px)').matches;
      }

      function isSinglePaneMobileActive() {
        return Boolean(initialSettings.mobile_single_pane) && isSinglePaneMobileViewport();
      }

      function updateMobilePaneNavigation() {
        const active = isSinglePaneMobileActive();
        const panes = ['folders', 'messages', 'preview'];
        if (!panes.includes(state.mobilePane)) state.mobilePane = 'folders';

        document.body.classList.toggle('pse-mobile-single-pane', active);
        for (const pane of panes) {
          document.body.classList.toggle(`pse-mobile-pane-${pane}`, active && state.mobilePane === pane);
        }

        const back = $('#mobilePaneBack');
        const forward = $('#mobilePaneForward');
        const folderAction = $('#footerFolderAction');
        if (folderAction) {
          folderAction.title = active ? 'Back to folders' : 'Show all messages in this folder';
          folderAction.setAttribute('aria-label', folderAction.title);
        }
        if (!back || !forward) return;

        const index = panes.indexOf(state.mobilePane);
        back.disabled = !active || index <= 0;
        const canOpenPreview = Boolean(state.selectedUid || state.currentMessage);
        forward.disabled = !active || index >= panes.length - 1 || (index === 1 && !canOpenPreview);

        const previousName = index > 0 ? (index === 1 ? 'folders' : 'email list') : '';
        const nextName = index < panes.length - 1 ? (index === 0 ? 'email list' : 'email reader') : '';
        back.title = previousName ? `Back to ${previousName}` : 'Previous mobile pane';
        back.setAttribute('aria-label', back.title);
        forward.title = nextName ? `Go to ${nextName}` : 'Next mobile pane';
        forward.setAttribute('aria-label', forward.title);
      }

      let mobileSwipeHintTimer = null;
      let mobileSwipeStart = null;

      function showMobileSwipeBackHint() {
        if (!isSinglePaneMobileActive() || state.mobilePane === 'folders') return;
        const hint = $('#mobileSwipeBackHint');
        if (!hint) return;
        const durationSeconds = Math.max(0, Math.min(5, Number(initialSettings.mobile_swipe_hint_seconds ?? 2)));
        clearTimeout(mobileSwipeHintTimer);
        hint.classList.remove('pse-show');
        if (durationSeconds <= 0) return;
        hint.style.animationDuration = `${durationSeconds}s`;
        void hint.offsetWidth;
        hint.classList.add('pse-show');
        mobileSwipeHintTimer = setTimeout(
          () => hint.classList.remove('pse-show'),
          durationSeconds * 1000
        );
      }

      function setMobilePane(pane, showBackHint = false) {
        if (!['folders', 'messages', 'preview'].includes(pane)) return;
        state.mobilePane = pane;
        updateMobilePaneNavigation();
        if (showBackHint) showMobileSwipeBackHint();
      }

      function moveMobilePane(direction) {
        if (!isSinglePaneMobileActive()) return;
        const panes = ['folders', 'messages', 'preview'];
        const index = panes.indexOf(state.mobilePane);
        const targetIndex = index + (direction < 0 ? -1 : 1);
        if (targetIndex < 0 || targetIndex >= panes.length) return;
        if (targetIndex === 2 && !state.selectedUid && !state.currentMessage) return;
        setMobilePane(panes[targetIndex]);
      }

      function mobileSwipeTouchStart(event) {
        mobileSwipeStart = null;
        if (!isSinglePaneMobileActive() || state.mobilePane === 'folders' || event.touches?.length !== 1) return;
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('input, textarea, select, [contenteditable="true"]')) return;
        const touch = event.touches[0];
        mobileSwipeStart = {
          x: touch.clientX,
          y: touch.clientY,
          at: Date.now()
        };
      }

      function mobileSwipeTouchEnd(event) {
        if (!mobileSwipeStart || !isSinglePaneMobileActive() || state.mobilePane === 'folders') {
          mobileSwipeStart = null;
          return;
        }
        const touch = event.changedTouches?.[0];
        if (!touch) {
          mobileSwipeStart = null;
          return;
        }
        const dx = touch.clientX - mobileSwipeStart.x;
        const dy = touch.clientY - mobileSwipeStart.y;
        const elapsed = Date.now() - mobileSwipeStart.at;
        mobileSwipeStart = null;
        if (dx > -65 || Math.abs(dx) < Math.abs(dy) * 1.25 || elapsed > 1200) return;
        if (event.cancelable) event.preventDefault();
        moveMobilePane(-1);
      }

      function bindMobileSwipeBack(target) {
        if (!target || target.__pseMobileSwipeBound) return;
        target.__pseMobileSwipeBound = true;
        target.addEventListener('touchstart', mobileSwipeTouchStart, {passive: true});
        target.addEventListener('touchend', mobileSwipeTouchEnd, {passive: false});
        target.addEventListener('touchcancel', () => { mobileSwipeStart = null; }, {passive: true});
      }

      function setupMobileSwipeBack() {
        bindMobileSwipeBack($('#workspace'));
      }

      function setupResizers() {
        const workspace = $('#workspace');
        const resizer1 = $('#resizer1');
        const resizer2 = $('#resizer2');
        const viewButton = $('#toggleViewMode');
        const viewIcon = $('#viewModeIcon');
        const modeStorageKey = 'pse_view_mode';
        const mobileModeStorageKey = 'pse_view_mode_mobile';
        const columnStorageKey = 'pse_column_widths';
        const mobileColumnStorageKey = 'pse_column_widths_mobile';
        const stackedStorageKey = 'pse_stacked_sizes';
        const mobileStackedStorageKey = 'pse_stacked_sizes_mobile';
        let resizeTimer = null;

        function readStoredJson(key, fallback) {
          try {
            const value = JSON.parse(localStorage.getItem(key) || 'null');
            return value ?? fallback;
          } catch (error) {
            console.warn(`Unable to read ${key}.`, error);
            return fallback;
          }
        }

        function writeStoredJson(key, value) {
          try {
            localStorage.setItem(key, JSON.stringify(value));
          } catch (error) {
            console.warn(`Unable to save ${key}.`, error);
          }
        }

        function isMobileLayout() {
          return window.innerWidth <= 900 || (navigator.maxTouchPoints > 0 && window.innerWidth <= 1200);
        }

        function currentModeStorageKey() {
          return isMobileLayout() ? mobileModeStorageKey : modeStorageKey;
        }

        function currentColumnStorageKey() {
          return isMobileLayout() ? mobileColumnStorageKey : columnStorageKey;
        }

        function currentStackedStorageKey() {
          return isMobileLayout() ? mobileStackedStorageKey : stackedStorageKey;
        }

        function readStoredMode() {
          try {
            return localStorage.getItem(currentModeStorageKey()) === 'stacked' ? 'stacked' : 'columns';
          } catch (error) {
            return 'columns';
          }
        }

        function writeStoredMode(mode) {
          try {
            localStorage.setItem(currentModeStorageKey(), mode);
          } catch (error) {
            console.warn('Unable to remember the view mode.', error);
          }
        }

        function applyColumns(widths) {
          if (state.viewMode !== 'columns') return;
          const mobile = isMobileLayout();
          document.body.classList.toggle('pse-mobile-layout', mobile);
          const available = Math.max(120, workspace.clientWidth);
          const minimum = mobile ? 34 : 10;
          const separator = mobile ? 4 : 5;
          const resizers = separator * 2;
          const defaultLeft = mobile ? Math.round((available - resizers) * 0.22) : 240;
          const defaultMiddle = mobile ? Math.round((available - resizers) * 0.38) : 430;
          const leftMax = Math.max(minimum, available - minimum - minimum - resizers);
          const left = Math.max(minimum, Math.min(leftMax, Number(widths?.[0]) || defaultLeft));
          const middleMax = Math.max(minimum, available - left - minimum - resizers);
          const middle = Math.max(minimum, Math.min(middleMax, Number(widths?.[1]) || defaultMiddle));
          workspace.style.gridTemplateRows = 'minmax(0, 1fr)';
          workspace.style.gridTemplateColumns = `${left}px ${separator}px ${middle}px ${separator}px minmax(${minimum}px, 1fr)`;
        }

        function applyStacked(sizes) {
          if (state.viewMode !== 'stacked') return;
          const availableWidth = Math.max(25, workspace.clientWidth);
          const availableHeight = Math.max(25, workspace.clientHeight);
          const mobile = isMobileLayout();
          document.body.classList.toggle('pse-mobile-layout', mobile);
          const separator = mobile ? 4 : 5;
          const minimum = mobile ? 34 : 10;
          const leftMax = Math.max(minimum, availableWidth - minimum - separator);
          const leftDefault = mobile ? Math.round((availableWidth - separator) * 0.28) : 240;
          const left = Math.max(minimum, Math.min(leftMax, Number(sizes?.[0]) || leftDefault));
          const topMax = Math.max(minimum, availableHeight - minimum - separator);
          const topDefault = Math.max(140, Math.round(availableHeight * 0.42));
          const top = Math.max(minimum, Math.min(topMax, Number(sizes?.[1]) || topDefault));
          workspace.style.gridTemplateColumns = `${left}px ${separator}px minmax(${minimum}px, 1fr)`;
          workspace.style.gridTemplateRows = `${top}px ${separator}px minmax(${minimum}px, 1fr)`;
        }

        function applySavedLayout() {
          if (state.viewMode === 'stacked') {
            applyStacked(readStoredJson(currentStackedStorageKey(), null));
          } else {
            applyColumns(readStoredJson(currentColumnStorageKey(), null));
          }
        }

        function updateViewButton() {
          const stacked = state.viewMode === 'stacked';
          const title = stacked
            ? 'Stacked preview view: switch to three columns'
            : 'Three-column view: switch to stacked preview';
          viewIcon.className = stacked
            ? 'fa-solid fa-bars'
            : 'fa-solid fa-table-columns';
          viewButton.title = title;
          viewButton.setAttribute('aria-label', title);
          viewButton.setAttribute('aria-pressed', stacked ? 'true' : 'false');
          resizer2.setAttribute('aria-orientation', stacked ? 'horizontal' : 'vertical');
        }

        function applyViewMode(mode, persist = true) {
          state.viewMode = mode === 'stacked' ? 'stacked' : 'columns';
          document.body.classList.toggle('pse-view-stacked', state.viewMode === 'stacked');
          workspace.style.gridTemplateColumns = '';
          workspace.style.gridTemplateRows = '';
          updateViewButton();
          if (persist) writeStoredMode(state.viewMode);
          requestAnimationFrame(applySavedLayout);
        }

        function beginDrag(event, index) {
          event.preventDefault();
          const resizer = event.currentTarget;
          const rowResize = state.viewMode === 'stacked' && index === 1;
          if (resizer.setPointerCapture) {
            resizer.setPointerCapture(event.pointerId);
          }
          resizer.classList.add('dragging');
          document.body.classList.add('pse-resizing');
          document.body.classList.toggle('pse-resizing-row', rowResize);

          const startX = event.clientX;
          const startY = event.clientY;
          const folderWidth = $('#foldersPanel').getBoundingClientRect().width;
          const messageWidth = $('#messagesPanel').getBoundingClientRect().width;
          const messageHeight = $('#messagesPanel').getBoundingClientRect().height;

          const onMove = moveEvent => {
            if (state.viewMode === 'stacked') {
              if (index === 0) {
                applyStacked([
                  folderWidth + (moveEvent.clientX - startX),
                  $('#messagesPanel').getBoundingClientRect().height
                ]);
              } else {
                applyStacked([
                  $('#foldersPanel').getBoundingClientRect().width,
                  messageHeight + (moveEvent.clientY - startY)
                ]);
              }
              return;
            }

            const delta = moveEvent.clientX - startX;
            const columns = [folderWidth, messageWidth];
            if (index === 0) {
              columns[0] = folderWidth + delta;
              columns[1] = messageWidth - delta;
            } else {
              columns[1] = messageWidth + delta;
            }
            applyColumns(columns);
          };

          const onUp = () => {
            if (resizer.releasePointerCapture && resizer.hasPointerCapture?.(event.pointerId)) {
              resizer.releasePointerCapture(event.pointerId);
            }
            resizer.classList.remove('dragging');
            document.body.classList.remove('pse-resizing', 'pse-resizing-row');
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            document.removeEventListener('pointercancel', onUp);

            if (state.viewMode === 'stacked') {
              writeStoredJson(currentStackedStorageKey(), [
                Math.round($('#foldersPanel').getBoundingClientRect().width),
                Math.round($('#messagesPanel').getBoundingClientRect().height)
              ]);
            } else {
              writeStoredJson(currentColumnStorageKey(), [
                Math.round($('#foldersPanel').getBoundingClientRect().width),
                Math.round($('#messagesPanel').getBoundingClientRect().width)
              ]);
            }
          };

          document.addEventListener('pointermove', onMove);
          document.addEventListener('pointerup', onUp);
          document.addEventListener('pointercancel', onUp);
        }

        resizer1.addEventListener('pointerdown', event => beginDrag(event, 0));
        resizer2.addEventListener('pointerdown', event => beginDrag(event, 1));
        viewButton.addEventListener('click', () => {
          applyViewMode(state.viewMode === 'stacked' ? 'columns' : 'stacked');
        });
        window.addEventListener('resize', () => {
          clearTimeout(resizeTimer);
          resizeTimer = setTimeout(() => {
            applySavedLayout();
            updateMobilePaneNavigation();
          }, 80);
        });

        applyViewMode(readStoredMode(), false);
        updateMobilePaneNavigation();
      }

      async function loadContacts(force = false, spinner = true) {
        if (state.contactsLoaded && !force) return state.contacts;
        const result = await api('contacts', {}, {spinner, spinnerText: 'Loading contacts…'});
        state.contacts = result.contacts;
        state.contactsLoaded = true;
        $('#contactsBadge').textContent = result.total;
        $('#statContacts').textContent = result.total;
        $('#contactsModalCount').textContent = `${result.total} contact${result.total === 1 ? '' : 's'}`;
        return state.contacts;
      }

      async function editContact(contact) {
        const result = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Edit contact',
          html: `
            <div class="text-start">
              <label class="form-label" for="swalContactName">Displayed name</label>
              <input class="form-control" id="swalContactName" maxlength="160" value="${escapeHtml(contact.name || '')}" placeholder="Displayed name">
              <label class="form-label mt-3" for="swalContactEmail">Email address</label>
              <input class="form-control" id="swalContactEmail" type="email" maxlength="320" value="${escapeHtml(contact.email)}" placeholder="name@example.com">
            </div>
          `,
          customClass: {popup: 'pse-contact-edit-popup'},
          showCancelButton: true,
          confirmButtonText: 'Save changes',
          focusConfirm: false,
          didOpen: () => $('#swalContactName')?.focus(),
          preConfirm: () => {
            const name = String($('#swalContactName')?.value || '').trim();
            const email = String($('#swalContactEmail')?.value || '').trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
              Swal.showValidationMessage('Enter a valid email address.');
              return false;
            }
            const duplicate = state.contacts.some(item =>
              String(item.id) !== String(contact.id) &&
              String(item.email || '').toLowerCase() === email.toLowerCase()
            );
            if (duplicate) {
              Swal.showValidationMessage('A contact with this email address already exists.');
              return false;
            }
            return {name, email};
          }
        });
        if (!result.isConfirmed) return;
        try {
          await api('save_contact', {
            id: contact.id,
            name: result.value.name,
            email: result.value.email
          }, {spinnerText: 'Updating contact…'});
          await loadContacts(true, false);
          renderContacts($('#contactsSearch').value);
          toast('Contact updated.');
        } catch (error) {
          handleError(error);
        }
      }

      function renderContacts(filter = '') {
        const term = filter.trim().toLowerCase();
        const contacts = state.contacts.filter(contact =>
          !term || contact.name.toLowerCase().startsWith(term) || contact.email.toLowerCase().startsWith(term)
        );
        $('#contactsModalCount').textContent = term
          ? `${contacts.length} of ${state.contacts.length} contacts`
          : `${state.contacts.length} contact${state.contacts.length === 1 ? '' : 's'}`;
        const root = $('#contactsList');
        if (!contacts.length) {
          root.innerHTML = '<div class="pse-empty"><div>No contacts found.</div></div>';
          return;
        }
        root.innerHTML = contacts.map(contact => `
          <div class="pse-contact-row">
            <div class="rounded-circle bg-light text-pse d-grid" style="width:30px;height:30px;place-items:center"><i class="fa-solid fa-user"></i></div>
            <div class="min-w-0">
              <div class="pse-contact-name text-truncate">${escapeHtml(contact.name || '(No displayed name)')}</div>
              <div class="pse-contact-email text-truncate">${escapeHtml(contact.email)}</div>
            </div>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary edit-contact" data-id="${escapeHtml(contact.id)}" title="Edit"><i class="fa-solid fa-pen"></i></button>
              <button class="btn btn-outline-danger delete-contact" data-id="${escapeHtml(contact.id)}" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
        `).join('');
        $$('.edit-contact', root).forEach(button => {
          button.addEventListener('click', () => {
            const contact = state.contacts.find(item => String(item.id) === String(button.dataset.id));
            if (contact) editContact(contact);
          });
        });
        $$('.delete-contact', root).forEach(button => {
          button.addEventListener('click', async () => {
            if (!await swalConfirm('Delete this contact?', 'The contact will be removed from this client.', 'Delete')) return;
            try {
              await api('delete_contact', {id: button.dataset.id});
              await loadContacts(true, false);
              renderContacts($('#contactsSearch').value);
            } catch (error) {
              handleError(error);
            }
          });
        });
      }

      async function openContacts() {
        try {
          await loadContacts(true);
          $('#contactsSearch').value = '';
          renderContacts();
          contactsModal.show();
        } catch (error) {
          handleError(error);
        }
      }

      async function openRecipientPicker(field) {
        try {
          hideRecipientSuggestions(false);
          setActiveRecipientField(field);
          state.recipientField = field;
          state.recipientDraftEmails = new Set(
            state.recipients[field].map(item => item.email.toLowerCase())
          );
          await loadContacts(false);
          $('#recipientFieldLabel').textContent = field[0].toUpperCase() + field.slice(1);
          $('#recipientSearch').value = '';
          renderRecipientContacts();
          const modalElement = $('#recipientModal');
          modalElement.addEventListener('shown.bs.modal', () => {
            const search = $('#recipientSearch');
            search.focus();
            search.setSelectionRange(search.value.length, search.value.length);
          }, {once: true});
          recipientModal.show();
        } catch (error) {
          handleError(error);
        }
      }

      function renderRecipientContacts(filter = '') {
        const term = filter.trim().toLowerCase();
        const contacts = state.contacts.filter(contact =>
          !term || contact.name.toLowerCase().startsWith(term) || contact.email.toLowerCase().startsWith(term)
        );
        const root = $('#recipientContacts');
        if (!contacts.length) {
          root.innerHTML = '<div class="pse-empty"><div>No matching contacts.</div></div>';
          $('#recipientSelectedCount').textContent = '0 selected';
          return;
        }
        root.innerHTML = contacts.map(contact => `
          <label class="pse-contact-row" style="cursor:pointer">
            <input class="form-check-input recipient-check" type="checkbox" data-id="${escapeHtml(contact.id)}" ${state.recipientDraftEmails.has(contact.email.toLowerCase()) ? 'checked' : ''}>
            <div class="min-w-0">
              <div class="pse-contact-name text-truncate">${escapeHtml(contact.name || '(No displayed name)')}</div>
              <div class="pse-contact-email text-truncate">${escapeHtml(contact.email)}</div>
            </div>
            <span></span>
          </label>
        `).join('');
        updateRecipientSelectedCount();
        $$('.recipient-check', root).forEach(check => check.addEventListener('change', () => {
          const contact = state.contacts.find(item => item.id === check.dataset.id);
          if (!contact) return;
          const email = contact.email.toLowerCase();
          if (check.checked) {
            state.recipientDraftEmails.add(email);
          } else {
            state.recipientDraftEmails.delete(email);
          }
          updateRecipientSelectedCount();
        }));
      }

      function updateRecipientSelectedCount() {
        const count = state.recipientDraftEmails.size;
        $('#recipientSelectedCount').textContent = `${count} selected`;
      }

      function markComposeDirty() {
        state.composeDirty = true;
      }

      function composeColorStorageKey(kind) {
        return `pse_compose_${kind}_${initialSettings.account_id || 'default'}`;
      }

      function validComposeColor(value, fallback) {
        return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : fallback;
      }

      function rememberedComposeColors() {
        const textFallback = $('#composeTextColor').defaultValue || '#202632';
        const backgroundFallback = $('#composeBackgroundColor').defaultValue || '#fff2a8';
        try {
          return {
            text: validComposeColor(localStorage.getItem(composeColorStorageKey('text_color')), textFallback),
            background: validComposeColor(localStorage.getItem(composeColorStorageKey('background_color')), backgroundFallback)
          };
        } catch (error) {
          return {text: textFallback, background: backgroundFallback};
        }
      }

      function rememberComposeColor(kind, value) {
        try {
          localStorage.setItem(composeColorStorageKey(kind), value);
        } catch (error) {
          console.warn('Unable to remember compose color.', error);
        }
      }

      function restoreRememberedComposeColorPickers() {
        const colors = rememberedComposeColors();
        setComposeTextColorPickerValue(colors.text);
        $('#composeBackgroundColor').value = colors.background;
      }

      function setComposeTextColorPickerValue(value) {
        const color = validComposeColor(value, '#202632').toLowerCase();
        $('#composeTextColor').value = color;
        $('#composeTextColorSwatch').style.backgroundColor = color;
        $('#composeTextColorSwatch').title = color.toUpperCase();
        $$('.pse-color-choice', $('#composeTextColorPalette')).forEach(button => {
          const active = String(button.dataset.color || '').toLowerCase() === color;
          button.classList.toggle('active', active);
          button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
      }

      function chooseComposeTextColor(value) {
        const color = validComposeColor(value, '#202632').toLowerCase();
        setComposeTextColorPickerValue(color);
        rememberComposeColor('text_color', color);
        applyComposeColor('foreColor', 'color', color);
      }

      function suppressBrowserAutofill(root = document) {
        const inputs = root instanceof HTMLInputElement ? [root] : $$('input', root);
        for (const input of inputs) {
          const type = String(input.type || 'text').toLowerCase();
          if (['file', 'color', 'checkbox', 'radio', 'button', 'submit', 'hidden'].includes(type)) continue;
          input.setAttribute('autocomplete', type === 'password' ? 'new-password' : 'off');
          input.setAttribute('autocorrect', 'off');
          input.setAttribute('autocapitalize', 'none');
          input.setAttribute('spellcheck', 'false');
          input.setAttribute('data-lpignore', 'true');
          input.setAttribute('data-1p-ignore', 'true');
          input.setAttribute('data-bwignore', 'true');
          if (!input.name) {
            input.name = `pse-no-autofill-${input.id || 'field'}-${Math.random().toString(36).slice(2, 9)}`;
          }
        }
      }

      function setActiveRecipientField(field) {
        if (!['to', 'cc', 'bcc'].includes(field)) return;
        state.recipientActiveField = field;
        $$('.recipient-picker').forEach(button => {
          button.classList.toggle('pse-recipient-active', button.dataset.field === field);
        });
        ['to', 'cc', 'bcc'].forEach(name => {
          $('#' + name + 'Recipients')?.classList.toggle('pse-recipient-active', name === field);
        });
      }

      function recipientInputForField(field) {
        return $(`.pse-recipient-input[data-field="${field}"]`);
      }

      function positionRecipientSuggestions() {
        if (!state.recipientSuggestionOpen || !state.recipientSuggestionField) return;
        const input = recipientInputForField(state.recipientSuggestionField);
        if (!input) return;
        const anchor = input.closest('.pse-recipient-area');
        const panel = $('#recipientSuggestions');
        const list = $('#recipientSuggestionsList');
        const rect = anchor.getBoundingClientRect();
        const width = Math.min(window.innerWidth - 24, Math.max(320, rect.width));
        const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
        const headerSpace = 54;
        const below = window.innerHeight - rect.bottom - 12;
        const above = rect.top - 12;
        let top = rect.bottom + 4;
        let listHeight = Math.min(340, Math.max(120, below - headerSpace));
        if (below < 180 && above > below) {
          listHeight = Math.min(340, Math.max(120, above - headerSpace));
          top = Math.max(12, rect.top - listHeight - headerSpace - 4);
        }
        panel.style.left = `${left}px`;
        panel.style.top = `${top}px`;
        panel.style.width = `${width}px`;
        list.style.maxHeight = `${listHeight}px`;
      }

      function hideRecipientSuggestions(clearInput = false) {
        const field = state.recipientSuggestionField;
        const input = field ? recipientInputForField(field) : null;
        $('#recipientSuggestions').classList.add('d-none');
        state.recipientSuggestionOpen = false;
        state.recipientSuggestionField = null;
        state.recipientSuggestionQuery = '';
        if (clearInput && input) {
          input.value = '';
          input.focus();
        }
      }

      function recipientContactMatches(query) {
        const term = String(query || '').trim().toLowerCase();
        if (!term) return [];
        return state.contacts.filter(contact =>
          String(contact.name || '').toLowerCase().startsWith(term) ||
          String(contact.email || '').toLowerCase().startsWith(term)
        );
      }

      function acceptFirstRecipientMatch(field, query) {
        const matches = recipientContactMatches(query);
        if (!matches.length) return false;
        const existing = new Set(
          state.recipients[field].map(recipient => String(recipient.email || '').toLowerCase())
        );
        const contact = matches.find(item => !existing.has(String(item.email || '').toLowerCase())) || matches[0];
        if (!contact) return false;
        hideRecipientSuggestions(false);
        if (!existing.has(String(contact.email || '').toLowerCase())) {
          state.recipients[field].push({name: contact.name || '', email: contact.email});
          markComposeDirty();
        }
        renderRecipientChips(field);
        const nextInput = recipientInputForField(field);
        if (nextInput) nextInput.focus();
        return true;
      }

      function renderRecipientSuggestionsPanel() {
        if (!state.recipientSuggestionOpen || !state.recipientSuggestionField) return;
        const field = state.recipientSuggestionField;
        const matches = recipientContactMatches(state.recipientSuggestionQuery);
        $('#recipientSuggestionCount').textContent = matches.length;
        const root = $('#recipientSuggestionsList');
        if (!matches.length) {
          root.innerHTML = '<div class="p-3 text-secondary text-center">No matching addresses.</div>';
          positionRecipientSuggestions();
          return;
        }
        root.innerHTML = matches.map(contact => {
          const selected = state.recipients[field].some(
            recipient => recipient.email.toLowerCase() === contact.email.toLowerCase()
          );
          return `
            <button
              class="pse-recipient-suggestion ${selected ? 'selected' : ''}"
              type="button"
              data-id="${escapeHtml(contact.id)}"
            >
              <i class="${selected ? 'fa-solid fa-square-check text-primary' : 'fa-regular fa-square'}"></i>
              <span class="min-w-0">
                <span class="d-block fw-semibold text-truncate">${escapeHtml(contact.name || '(No displayed name)')}</span>
                <span class="d-block small text-secondary text-truncate">${escapeHtml(contact.email)}</span>
              </span>
            </button>
          `;
        }).join('');
        $$('.pse-recipient-suggestion', root).forEach(button => {
          button.addEventListener('click', () => {
            const contact = state.contacts.find(item => item.id === button.dataset.id);
            if (!contact) return;
            const email = contact.email.toLowerCase();
            const exists = state.recipients[field].some(
              recipient => recipient.email.toLowerCase() === email
            );
            if (exists) {
              state.recipients[field] = state.recipients[field].filter(
                recipient => recipient.email.toLowerCase() !== email
              );
            } else {
              state.recipients[field].push({name: contact.name || '', email: contact.email});
            }
            markComposeDirty();
            const query = state.recipientSuggestionQuery;
            renderRecipientChips(field, query);
            state.recipientSuggestionOpen = true;
            state.recipientSuggestionField = field;
            state.recipientSuggestionQuery = query;
            const nextInput = recipientInputForField(field);
            if (nextInput) {
              nextInput.focus();
              nextInput.setSelectionRange(query.length, query.length);
            }
            $('#recipientSuggestions').classList.remove('d-none');
            renderRecipientSuggestionsPanel();
          });
        });
        positionRecipientSuggestions();
      }

      async function showRecipientSuggestions(input) {
        const query = input.value.trim();
        if (query.length < 1) {
          if (state.recipientSuggestionField === input.dataset.field) {
            hideRecipientSuggestions(false);
          }
          return;
        }
        const expectedValue = input.value;
        await loadContacts(false, false);
        if (!input.isConnected || input.value !== expectedValue) return;
        state.recipientSuggestionOpen = true;
        state.recipientSuggestionField = input.dataset.field;
        state.recipientSuggestionQuery = query;
        $('#recipientSuggestions').classList.remove('d-none');
        renderRecipientSuggestionsPanel();
      }

      function parseRecipientText(value) {
        const parsed = [];
        const parts = String(value || '').split(/\s*[;,]\s*/).filter(Boolean);
        for (const part of parts) {
          const match = part.trim().match(/^(.*?)<([^>]+)>$/);
          const email = (match ? match[2] : part).trim();
          const name = match ? match[1].trim().replace(/^["']|["']$/g, '') : '';
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            throw new Error(`Invalid email address: ${email}`);
          }
          parsed.push({name, email});
        }
        return parsed;
      }

      function commitRecipientInput(input, showError = true) {
        const value = input.value.trim().replace(/[;,]+$/, '');
        if (!value) return true;
        if (
          state.recipientSuggestionOpen &&
          state.recipientSuggestionField === input.dataset.field &&
          !value.includes('@')
        ) {
          hideRecipientSuggestions(true);
          return true;
        }
        try {
          const recipients = parseRecipientText(value);
          hideRecipientSuggestions(false);
          for (const recipient of recipients) {
            addRecipient(input.dataset.field, recipient);
          }
          return true;
        } catch (error) {
          if (showError) toast(error.message, 'warning');
          input.focus();
          return false;
        }
      }

      function commitPendingRecipientInputs() {
        for (const input of $$('.pse-recipient-input')) {
          if (!commitRecipientInput(input)) return false;
        }
        return true;
      }

      function updateComposeRecipientTotals() {
        const totals = [
          ['To', state.recipients.to.length],
          ['Cc', state.recipients.cc.length],
          ['Bcc', state.recipients.bcc.length]
        ].filter(([, count]) => count > 0);
        $('#composeRecipientTotals').innerHTML = totals.map(([label, count]) =>
          `<span class="badge rounded-pill text-bg-light border text-secondary">${label}: ${count}</span>`
        ).join('');
      }

      function moveRecipientBetweenFields(sourceField, targetField, email) {
        if (!['to', 'cc', 'bcc'].includes(sourceField) || !['to', 'cc', 'bcc'].includes(targetField)) return;
        const key = String(email || '').toLowerCase();
        if (!key) return;
        const recipient = state.recipients[sourceField].find(item => item.email.toLowerCase() === key);
        if (!recipient) return;
        if (sourceField !== targetField) {
          state.recipients[sourceField] = state.recipients[sourceField].filter(item => item.email.toLowerCase() !== key);
          if (!state.recipients[targetField].some(item => item.email.toLowerCase() === key)) {
            state.recipients[targetField].push(recipient);
          }
          markComposeDirty();
          renderRecipientChips(sourceField);
          renderRecipientChips(targetField);
        }
        setActiveRecipientField(targetField);
        recipientInputForField(targetField)?.focus();
      }

      function renderRecipientChips(field, inputValue = '') {
        const root = $('#' + field + 'Recipients');
        const recipients = state.recipients[field];
        root.innerHTML = '';
        for (const recipient of recipients) {
          const chip = document.createElement('span');
          chip.className = 'pse-recipient-chip';
          chip.title = recipient.email;
          chip.draggable = true;
          chip.dataset.field = field;
          chip.dataset.email = recipient.email;
          chip.innerHTML = `<span>${escapeHtml(recipient.name || recipient.email)}</span><button type="button" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>`;
          chip.addEventListener('dragstart', event => {
            state.recipientDrag = {field, email: recipient.email};
            chip.classList.add('pse-recipient-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-pse-recipient', JSON.stringify(state.recipientDrag));
            event.dataTransfer.setData('text/plain', recipient.email);
          });
          chip.addEventListener('dragend', () => {
            chip.classList.remove('pse-recipient-dragging');
            state.recipientDrag = null;
            $$('.pse-recipient-area').forEach(area => area.classList.remove('pse-recipient-drop-target'));
          });
          chip.querySelector('button').addEventListener('click', () => {
            state.recipients[field] = state.recipients[field].filter(item => item.email.toLowerCase() !== recipient.email.toLowerCase());
            markComposeDirty();
            const query = state.recipientSuggestionOpen && state.recipientSuggestionField === field
              ? state.recipientSuggestionQuery
              : '';
            renderRecipientChips(field, query);
            setActiveRecipientField(field);
            if (query) renderRecipientSuggestionsPanel();
          });
          root.appendChild(chip);
        }
        const input = document.createElement('input');
        input.className = 'border-0 flex-grow-1 pse-recipient-input';
        input.dataset.field = field;
        input.style.minWidth = '150px';
        input.placeholder = recipients.length ? 'Add another email…' : 'Type an email or click ' + field[0].toUpperCase() + field.slice(1) + ':';
        input.value = inputValue;
        suppressBrowserAutofill(input);
        input.addEventListener('focus', () => {
          setActiveRecipientField(field);
          if (
            state.recipientSuggestionOpen &&
            state.recipientSuggestionField !== field
          ) {
            hideRecipientSuggestions(false);
          }
        });
        input.addEventListener('input', () => {
          markComposeDirty();
          showRecipientSuggestions(input).catch(handleError);
        });
        input.addEventListener('keydown', event => {
          if (
            event.key === 'Enter' &&
            state.recipientSuggestionOpen &&
            state.recipientSuggestionField === field &&
            !input.value.includes('@')
          ) {
            event.preventDefault();
            if (acceptFirstRecipientMatch(field, input.value)) return;
          }
          if (
            event.key === 'Tab' &&
            state.recipientSuggestionOpen &&
            state.recipientSuggestionField === field &&
            !input.value.includes('@')
          ) {
            event.preventDefault();
            return;
          }
          if (['Enter', ',', ';', 'Tab'].includes(event.key)) {
            if (input.value.trim()) {
              event.preventDefault();
              commitRecipientInput(input);
            }
          }
        });
        input.addEventListener('blur', () => {
          setTimeout(() => {
            if (state.recipientSuggestionOpen && state.recipientSuggestionField === field) return;
            if (input.isConnected && input.value.trim()) commitRecipientInput(input);
          }, 0);
        });
        root.appendChild(input);
        root.onclick = event => {
          if (event.target.closest('.pse-recipient-chip button')) return;
          setActiveRecipientField(field);
          input.focus();
        };
        root.ondragenter = event => {
          if (!state.recipientDrag) return;
          event.preventDefault();
          root.classList.add('pse-recipient-drop-target');
          setActiveRecipientField(field);
        };
        root.ondragover = event => {
          if (!state.recipientDrag) return;
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
          root.classList.add('pse-recipient-drop-target');
        };
        root.ondragleave = event => {
          if (!root.contains(event.relatedTarget)) {
            root.classList.remove('pse-recipient-drop-target');
          }
        };
        root.ondrop = event => {
          if (!state.recipientDrag) return;
          event.preventDefault();
          root.classList.remove('pse-recipient-drop-target');
          const source = state.recipientDrag;
          state.recipientDrag = null;
          moveRecipientBetweenFields(source.field, field, source.email);
        };
        root.classList.toggle('pse-recipient-active', state.recipientActiveField === field);
        updateComposeRecipientTotals();
      }

      function addRecipient(field, recipient) {
        if (!state.recipients[field].some(item => item.email.toLowerCase() === recipient.email.toLowerCase())) {
          state.recipients[field].push({name: recipient.name || '', email: recipient.email});
          markComposeDirty();
        }
        renderRecipientChips(field);
      }

      function setRecipientRowVisibility(field, visible) {
        const row = $('#' + field + 'Row');
        const toggle = $('#toggle' + field[0].toUpperCase() + field.slice(1));
        row.classList.toggle('d-none', !visible);
        toggle.textContent = (visible ? 'Hide ' : 'Show ') + field[0].toUpperCase() + field.slice(1);
        if (!visible && state.recipientActiveField === field) {
          setActiveRecipientField('to');
        }
      }

      function resetCompose() {
        hideRecipientSuggestions(false);
        state.recipients = {to: [], cc: [], bcc: []};
        state.recipientActiveField = 'to';
        state.recipientDrag = null;
        state.composeMode = 'normal';
        state.bulkForwardUids = [];
        state.bulkForwardFolder = '';
        state.composeFiles = [];
        state.composeRange = null;
        state.composeDirty = false;
        state.composeSignatureManaged = false;
        state.skipDraftOnClose = false;
        state.composeCloseConfirming = false;
        setComposeMaximized(false);
        $('#composePseId').value = '';
        $('#composeSubject').value = '';
        $('#composeBody').innerHTML = '';
        restoreRememberedComposeColorPickers();
        $('#composeAttachments').value = '';
        setRecipientRowVisibility('cc', true);
        setRecipientRowVisibility('bcc', true);
        $$('.pse-compose-editable').forEach(element => element.classList.remove('d-none'));
        $('#composeTitleText').textContent = 'Compose email';
        $('#sendEmail').innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send';
        $('#deleteComposeForever').classList.remove('d-none');
        ['to', 'cc', 'bcc'].forEach(field => renderRecipientChips(field));
        setActiveRecipientField('to');
        updateComposeDraftUi();
        renderAttachmentList();
        updateImageProgress(0, '');
        suppressBrowserAutofill($('#composeModal'));
      }

      function composeSignatureBlockHtml() {
        const signature = String(initialSettings.signature || '').trim();
        return signature === ''
          ? ''
          : `<div data-pse-signature="1">${signature}</div>`;
      }

      function insertComposeSignature(position = 'end') {
        const body = $('#composeBody');
        const existing = body.querySelector('[data-pse-signature="1"]');
        if (existing) {
          state.composeSignatureManaged = true;
          return true;
        }
        const signatureHtml = composeSignatureBlockHtml();
        if (!signatureHtml) return false;
        if (position === 'before-quote') {
          const quote = [...body.children].find(element =>
            element.matches('[data-pse-quote="1"]') ||
            String(element.style?.borderLeft || '').trim() !== ''
          );
          if (quote) {
            quote.insertAdjacentHTML('beforebegin', signatureHtml + '<br>');
          } else {
            body.insertAdjacentHTML('beforeend', '<br><br>' + signatureHtml);
          }
        } else {
          body.insertAdjacentHTML('beforeend', (body.innerHTML.trim() ? '<br><br>' : '') + signatureHtml);
        }
        state.composeSignatureManaged = true;
        return true;
      }

      function setDefaultComposeRange() {
        const body = $('#composeBody');
        const range = document.createRange();
        const signature = body.querySelector('[data-pse-signature="1"]');
        if (signature) {
          range.setStartBefore(signature);
          range.collapse(true);
        } else {
          range.selectNodeContents(body);
          range.collapse(false);
        }
        state.composeRange = range.cloneRange();
      }

      function updateComposeDraftUi() {
        const bulkForward = state.composeMode === 'bulk-forward';
        const enabled = Boolean(initialSettings.compose_save_drafts) && !bulkForward;
        $('#savePse').classList.toggle('d-none', !enabled);
        $('#deleteComposeForever').classList.toggle('d-none', bulkForward);
        $('#composeCloseButton').title = initialSettings.compose_save_drafts
          ? (bulkForward ? 'Close' : 'Close and save draft')
          : 'Close (email will be lost)';
      }

      function composeMaximizedStorageKey() {
        return `pse_compose_maximized:${String(initialSettings.account_id || 'default')}`;
      }

      function readComposeMaximizedState() {
        try {
          return localStorage.getItem(composeMaximizedStorageKey()) === '1';
        } catch (error) {
          return false;
        }
      }

      function rememberComposeMaximizedState(maximized) {
        try {
          localStorage.setItem(composeMaximizedStorageKey(), maximized ? '1' : '0');
        } catch (error) {
          // The current state still works for this tab even if browser storage is unavailable.
        }
      }

      function setComposeMaximized(maximized, remember = true) {
        const modal = $('#composeModal');
        const button = $('#composeMaximizeButton');
        const icon = button.querySelector('i');
        const isMaximized = Boolean(maximized);
        modal.classList.toggle('pse-compose-maximized', isMaximized);
        icon.classList.toggle('fa-expand', !isMaximized);
        icon.classList.toggle('fa-compress', isMaximized);
        button.title = isMaximized ? 'Restore compose window' : 'Maximize compose window';
        button.setAttribute('aria-label', button.title);
        button.setAttribute('aria-pressed', isMaximized ? 'true' : 'false');
        if (remember) rememberComposeMaximizedState(isMaximized);
        requestAnimationFrame(positionRecipientSuggestions);
      }

      function toggleComposeMaximized() {
        setComposeMaximized(!$('#composeModal').classList.contains('pse-compose-maximized'));
      }

      async function confirmComposeCloseWithoutDrafts(event) {
        if (state.skipDraftOnClose || initialSettings.compose_save_drafts) return;

        event.preventDefault();
        if (state.composeCloseConfirming) return;
        state.composeCloseConfirming = true;

        try {
          const confirmed = await swalConfirm(
            'Close this email?',
            'Draft saving is disabled. If you close this compose window, this email will be lost forever.',
            'Close and lose email'
          );
          if (!confirmed) return;

          state.skipDraftOnClose = true;
          composeModal.hide();
        } finally {
          state.composeCloseConfirming = false;
        }
      }

      function insertComposeText(text) {
        if (!text) return;
        restoreComposeSelection();
        const body = $('#composeBody');
        const selection = window.getSelection();
        let range = selection?.rangeCount ? selection.getRangeAt(0) : state.composeRange;
        if (!range || !body.contains(range.commonAncestorContainer)) {
          range = document.createRange();
          range.selectNodeContents(body);
          range.collapse(false);
        }
        range.deleteContents();
        const node = document.createTextNode(text);
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        state.composeRange = range.cloneRange();
        markComposeDirty();
        body.focus();
      }

      function openCompose() {
        resetCompose();
        if (isSinglePaneMobileViewport()) setComposeMaximized(true, false);
        if (composeSignatureBlockHtml()) {
          $('#composeBody').innerHTML = '<div><br></div>';
          insertComposeSignature();
        }
        setDefaultComposeRange();
        composeModal.show();
        setTimeout(() => {
          setActiveRecipientField('to');
          recipientInputForField('to')?.focus();
        }, 250);
      }

      function openBulkForward() {
        const uids = [...state.selectedUids].map(String).filter(Boolean);
        if (!uids.length) {
          toast('Select at least one email to forward.', 'warning');
          return;
        }
        resetCompose();
        state.composeMode = 'bulk-forward';
        state.bulkForwardUids = uids;
        state.bulkForwardFolder = String(state.folder);
        state.composeDirty = false;
        $$('.pse-compose-editable').forEach(element => element.classList.add('d-none'));
        $('#composeTitleText').textContent = `Forward ${uids.length} selected email${uids.length === 1 ? '' : 's'} separately`;
        $('#sendEmail').innerHTML = '<i class="fa-solid fa-share me-1"></i>Forward';
        updateComposeDraftUi();
        composeModal.show();
        setTimeout(() => {
          setActiveRecipientField('to');
          recipientInputForField('to')?.focus();
        }, 250);
      }

      function uniqueAddresses(items) {
        const unique = new Map();
        for (const item of items || []) {
          const email = String(item?.email || '').trim();
          if (!email) continue;
          const key = email.toLowerCase();
          if (!unique.has(key)) {
            unique.set(key, {name: String(item?.name || ''), email});
          }
        }
        return [...unique.values()];
      }

      function quotedMessageHtml(message) {
        const template = document.createElement('template');
        template.innerHTML = message.html || '';
        template.content.querySelectorAll('img[src^="data:"]').forEach(image => {
          const note = document.createElement('span');
          note.textContent = `[inline image omitted: ${image.alt || 'image'}]`;
          note.style.fontStyle = 'italic';
          image.replaceWith(note);
        });
        template.content.querySelectorAll('[style*="data:"]').forEach(node => {
          node.style.backgroundImage = '';
        });
        return template.innerHTML;
      }

      function quotedMessageDate(message) {
        const timestamp = Number(message?.timestamp || 0);
        if (timestamp > 0) {
          return configuredDateTimeLabel(timestamp);
        }
        return String(message?.date || '');
      }

      function replyToMessage(mode = 'reply') {
        const message = state.currentMessage;
        if (!message) return;
        resetCompose();
        if (isSinglePaneMobileViewport() && (mode === 'reply' || mode === 'replyAll')) {
          setComposeMaximized(true, false);
        }
        if (mode === 'forward') {
          $('#composeSubject').value = /^fwd:/i.test(message.subject) ? message.subject : 'Fwd: ' + message.subject;
        } else {
          const replyAddresses = message.replyTo?.length ? message.replyTo : message.from;
          state.recipients.to = uniqueAddresses(replyAddresses);
          if (mode === 'replyAll') {
            const ownAddresses = new Set([
              initialSettings.from_email,
              initialSettings.imap_username,
              initialSettings.smtp_username
            ].filter(Boolean).map(email => String(email).toLowerCase()));
            state.recipients.to = state.recipients.to.filter(
              item => !ownAddresses.has(item.email.toLowerCase())
            );
            const toAddresses = new Set(state.recipients.to.map(item => item.email.toLowerCase()));
            state.recipients.cc = uniqueAddresses([...(message.to || []), ...(message.cc || [])]).filter(item => {
              const email = item.email.toLowerCase();
              return !ownAddresses.has(email) && !toAddresses.has(email);
            });
            renderRecipientChips('cc');
            setRecipientRowVisibility('cc', true);
          }
          renderRecipientChips('to');
          $('#composeSubject').value = /^re:/i.test(message.subject) ? message.subject : 'Re: ' + message.subject;
        }
        $('#composeBody').innerHTML = `<div><br></div><br><div data-pse-quote="1" style="border-left:3px solid #ccd3dd;padding-left:12px;color:#606b7c"><p><b>On ${escapeHtml(quotedMessageDate(message))}, ${escapeHtml(addressText(message.from))} wrote:</b></p>${quotedMessageHtml(message)}</div>`;
        if (mode !== 'forward') {
          insertComposeSignature('before-quote');
        }
        setDefaultComposeRange();
        state.composeDirty = true;
        composeModal.show();
      }

      function renderAttachmentList() {
        $('#attachmentList').textContent = state.composeFiles.length
          ? state.composeFiles.map(file => `${file.name} (${formatBytes(file.size || file.data?.length * .75 || 0)})`).join(', ')
          : 'No attachments';
      }

      function filesToPayload() {
        return Promise.all(state.composeFiles.map(file => {
          if (file.data) return Promise.resolve({name: file.name, type: file.type || 'application/octet-stream', data: file.data});
          return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve({name: file.name, type: file.type || 'application/octet-stream', data: String(reader.result).split(',')[1] || ''});
            reader.onerror = () => reject(new Error('Unable to read ' + file.name));
            reader.readAsDataURL(file);
          });
        }));
      }

      function composeAttachmentBlob(file) {
        if (file instanceof Blob) return file;
        const encoded = String(file?.data || '').replace(/^data:[^,]+,/, '').replace(/\s+/g, '');
        if (!encoded) return new Blob([], {type: file?.type || 'application/octet-stream'});
        let binary;
        try {
          binary = atob(encoded);
        } catch (error) {
          throw new Error(`Unable to decode ${file?.name || 'attachment'}.`);
        }
        const bytes = new Uint8Array(binary.length);
        for (let index = 0; index < binary.length; index++) bytes[index] = binary.charCodeAt(index);
        return new Blob([bytes], {type: file?.type || 'application/octet-stream'});
      }

      async function sha256Blob(blob) {
        if (!window.crypto?.subtle) {
          throw new Error('Secure SHA-256 support is unavailable. Open PSE over HTTPS to send attachments safely.');
        }
        const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
      }

      async function uploadChunkWithRetry(uploadId, index, chunk) {
        let lastError = null;
        for (let attempt = 0; attempt < 4; attempt++) {
          try {
            const form = new FormData();
            form.set('uploadId', uploadId);
            form.set('index', String(index));
            form.set('chunk', chunk, 'chunk.bin');
            return await api('upload_attachment_chunk', form, {spinner: false});
          } catch (error) {
            lastError = error;
            if (attempt >= 3) break;
            await new Promise(resolve => setTimeout(resolve, 300 * (attempt + 1)));
          }
        }
        throw lastError || new Error('Attachment chunk upload failed.');
      }

      async function uploadComposeAttachments() {
        const references = [];
        for (let fileIndex = 0; fileIndex < state.composeFiles.length; fileIndex++) {
          const file = state.composeFiles[fileIndex];
          const blob = composeAttachmentBlob(file);
          const name = String(file?.name || 'attachment.bin');
          const type = String(file?.type || blob.type || 'application/octet-stream');
          const totalChunks = Math.max(1, Math.ceil(blob.size / <?= PSE_ATTACHMENT_CHUNK_BYTES ?>));
          updateImageProgress(1, `Verifying ${name}…`);
          const sha256 = await sha256Blob(blob);
          const previous = file?._pseUpload;
          const canResume = previous &&
            Number(previous.size) === blob.size &&
            String(previous.sha256 || '') === sha256 &&
            Number(previous.totalChunks) === totalChunks;
          const initialized = await api('upload_attachment_init', {
            uploadId: canResume ? String(previous.uploadId || '') : '',
            name,
            type,
            size: blob.size,
            sha256,
            totalChunks
          }, {spinner: false});
          const upload = initialized.upload || {};
          file._pseUpload = {
            uploadId: String(upload.uploadId || ''),
            sha256,
            size: blob.size,
            totalChunks
          };
          if (!file._pseUpload.uploadId) throw new Error(`Unable to initialize upload for ${name}.`);

          if (!upload.complete) {
            const received = new Set((upload.received || []).map(Number));
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
              if (received.has(chunkIndex)) continue;
              const start = chunkIndex * <?= PSE_ATTACHMENT_CHUNK_BYTES ?>;
              const end = Math.min(blob.size, start + <?= PSE_ATTACHMENT_CHUNK_BYTES ?>);
              const chunk = blob.slice(start, end, type);
              const overall = Math.round(((chunkIndex + 0.25) / totalChunks) * 100);
              updateImageProgress(overall, `Uploading ${name} — chunk ${chunkIndex + 1}/${totalChunks}…`);
              await uploadChunkWithRetry(file._pseUpload.uploadId, chunkIndex, chunk);
            }
          }

          updateImageProgress(99, `Checking ${name}…`);
          const finalized = await api('upload_attachment_finalize', {
            uploadId: file._pseUpload.uploadId
          }, {spinner: false});
          const completed = finalized.upload || {};
          if (!completed.complete) throw new Error(`Upload verification failed for ${name}.`);
          references.push({
            uploadId: String(completed.uploadId),
            name: String(completed.name || name),
            type: String(completed.type || type),
            size: Number(completed.size || blob.size),
            sha256: String(completed.sha256 || sha256)
          });
          updateImageProgress(100, `${name} uploaded`);
        }
        setTimeout(() => updateImageProgress(0, ''), 650);
        return references;
      }

      function updateImageProgress(percent, label) {
        const root = $('#imageUploadProgress');
        const value = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
        if (!label) {
          root.classList.remove('show');
          $('#imageProgressBar').style.width = '0%';
          $('#imageProgressPercent').textContent = '0%';
          return;
        }
        root.classList.add('show');
        $('#imageProgressLabel').textContent = label;
        $('#imageProgressBar').style.width = value + '%';
        $('#imageProgressPercent').textContent = value + '%';
      }

      function saveComposeSelection() {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount) return;
        const range = selection.getRangeAt(0);
        const container = range.commonAncestorContainer.nodeType === Node.TEXT_NODE
          ? range.commonAncestorContainer.parentNode
          : range.commonAncestorContainer;
        if ($('#composeBody').contains(container) || container === $('#composeBody')) {
          state.composeRange = range.cloneRange();
        }
      }

      function restoreComposeSelection() {
        const body = $('#composeBody');
        body.focus();
        const selection = window.getSelection();
        if (!state.composeRange || !body.contains(state.composeRange.commonAncestorContainer)) {
          const range = document.createRange();
          range.selectNodeContents(body);
          range.collapse(false);
          selection.removeAllRanges();
          selection.addRange(range);
          state.composeRange = range.cloneRange();
          return;
        }
        selection.removeAllRanges();
        selection.addRange(state.composeRange);
      }

      function applyComposeColor(command, styleProperty, value) {
        restoreComposeSelection();
        const selection = window.getSelection();
        if (!selection?.rangeCount) return;
        const range = selection.getRangeAt(0);
        if (range.collapsed) {
          const span = document.createElement('span');
          span.style[styleProperty] = value;
          const marker = document.createTextNode('\u200b');
          span.appendChild(marker);
          range.insertNode(span);
          // Keep the caret before the marker and therefore inside the styled span.
          // A caret after the marker can be normalized outside the span by Firefox/WebKit.
          range.setStart(marker, 0);
          range.collapse(true);
          selection.removeAllRanges();
          selection.addRange(range);
          state.composeRange = range.cloneRange();
        } else {
          const applied = document.execCommand(command, false, value);
          if (command === 'hiliteColor' && !applied) {
            document.execCommand('backColor', false, value);
          }
          saveComposeSelection();
        }
        markComposeDirty();
      }

      function applyComposeFontSize(size) {
        if (!size) return;
        restoreComposeSelection();
        const selection = window.getSelection();
        if (selection?.rangeCount && selection.getRangeAt(0).collapsed) {
          const range = selection.getRangeAt(0);
          const span = document.createElement('span');
          span.style.fontSize = `${size}px`;
          const marker = document.createTextNode('\u200b');
          span.appendChild(marker);
          range.insertNode(span);
          range.setStart(marker, 0);
          range.collapse(true);
          selection.removeAllRanges();
          selection.addRange(range);
          state.composeRange = range.cloneRange();
          markComposeDirty();
          return;
        }
        document.execCommand('fontSize', false, '7');
        $$('font[size="7"]', $('#composeBody')).forEach(font => {
          const span = document.createElement('span');
          span.style.fontSize = `${size}px`;
          while (font.firstChild) span.appendChild(font.firstChild);
          font.replaceWith(span);
        });
        markComposeDirty();
        saveComposeSelection();
      }

      function insertAtComposeCursor(node) {
        const body = $('#composeBody');
        body.focus();
        const selection = window.getSelection();
        let range = state.composeRange;
        if (!range || !body.contains(range.commonAncestorContainer)) {
          range = document.createRange();
          range.selectNodeContents(body);
          range.collapse(false);
        }
        range.deleteContents();
        range.insertNode(node);
        const spacer = document.createElement('br');
        node.after(spacer);
        range.setStartAfter(spacer);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        state.composeRange = range.cloneRange();
      }

      function insertInlineImage(file) {
        return new Promise((resolve, reject) => {
          if (!file || !String(file.type).startsWith('image/')) {
            reject(new Error('Only image files can be inserted into the email text.'));
            return;
          }
          if (Number(file.size || 0) > <?= PSE_MAX_INLINE_IMAGE_BYTES ?>) {
            reject(new Error('Inline images are limited to 5 MB each.'));
            return;
          }
          const reader = new FileReader();
          updateImageProgress(2, `Adding ${file.name || 'pasted image'}…`);
          reader.onprogress = event => {
            if (event.lengthComputable) {
              updateImageProgress((event.loaded / event.total) * 92, `Adding ${file.name || 'image'}…`);
            }
          };
          reader.onerror = () => {
            updateImageProgress(0, '');
            reject(new Error('Unable to read the selected image.'));
          };
          reader.onload = () => {
            const image = document.createElement('img');
            image.src = String(reader.result);
            image.alt = file.name || 'Inline image';
            image.style.maxWidth = '100%';
            image.style.height = 'auto';
            insertAtComposeCursor(image);
            markComposeDirty();
            updateImageProgress(100, `${file.name || 'Image'} added`);
            setTimeout(() => updateImageProgress(0, ''), 650);
            resolve();
          };
          reader.readAsDataURL(file);
        });
      }

      async function composePayload(includeAttachmentData = true) {
        if (!commitPendingRecipientInputs()) {
          throw new Error('Check the recipient email address.');
        }
        const attachments = includeAttachmentData
          ? await filesToPayload()
          : state.composeFiles.map(file => ({
              name: String(file?.name || 'attachment.bin'),
              type: String(file?.type || 'application/octet-stream'),
              size: Number(file?.size || (file?.data ? file.data.length * .75 : 0))
            }));
        const bodyHtml = $('#composeBody').innerHTML.replace(/\u200b/g, '');
        const bodyText = $('#composeBody').innerText.replace(/\u200b/g, '');
        return {
          to: state.recipients.to,
          cc: state.recipients.cc,
          bcc: state.recipients.bcc,
          subject: $('#composeSubject').value,
          bodyHtml,
          bodyText,
          signatureHandled: state.composeSignatureManaged,
          signaturePresent: Boolean($('#composeBody').querySelector('[data-pse-signature="1"]')),
          attachments
        };
      }

      async function askAboutUnknownContacts() {
        await loadContacts(false, false);
        const known = new Set(state.contacts.map(contact => contact.email.toLowerCase()));
        const unique = new Map();
        for (const recipient of [
          ...state.recipients.to,
          ...state.recipients.cc,
          ...state.recipients.bcc
        ]) {
          const key = recipient.email.toLowerCase();
          if (!known.has(key)) unique.set(key, recipient);
        }
        const unknown = [...unique.values()];
        if (!unknown.length) return true;

        $('#unknownContactsList').innerHTML = unknown.map((recipient, index) => `
          <div class="pse-unknown-contact">
            <div>
              <div class="fw-semibold">${escapeHtml(recipient.email)}</div>
              <div class="small text-secondary">New contact</div>
            </div>
            <input
              class="form-control unknown-contact-name"
              data-email="${escapeHtml(recipient.email)}"
              value="${escapeHtml(recipient.name || '')}"
              placeholder="Displayed name (optional)"
              aria-label="Displayed name for ${escapeHtml(recipient.email)}"
            >
          </div>
        `).join('');

        return new Promise(resolve => {
          state.unknownContactResolver = resolve;
          unknownContactsModal.show();
          setTimeout(() => $('.unknown-contact-name')?.focus(), 250);
        });
      }

      function composeAuthoredTextForAttachmentReminder() {
        const body = $('#composeBody');
        const clone = body ? body.cloneNode(true) : null;
        if (clone) {
          clone.querySelectorAll('[data-pse-quote="1"], [data-pse-signature="1"]').forEach(node => node.remove());
        }
        return [
          String($('#composeSubject')?.value || ''),
          String(clone?.innerText || clone?.textContent || '')
        ].join('\n').replace(/\u200b/g, ' ').trim();
      }

      function composeMentionsAttachment() {
        const text = composeAuthoredTextForAttachmentReminder().toLocaleLowerCase();
        if (!text) return false;

        const patterns = [
          /(^|[^a-z])attach(?:ed|ing|ment|ments)?(?=$|[^a-z])/,
          /(^|[^a-z])enclos(?:e|ed|ing|ure|ures)(?=$|[^a-z])/,
          /(^|[^a-z])alleg(?:o|are|ato|ata|ati|ate)(?=$|[^a-z])/,
          /(^|[^a-z])see\s+(?:the\s+)?files?(?=$|[^a-z])/,
          /(^|[^a-z])find\s+(?:the\s+)?(?:file|attachment)s?(?=$|[^a-z])/
        ];
        return patterns.some(pattern => pattern.test(text));
      }

      async function confirmPossibleMissingAttachment(payload) {
        if ((payload.attachments || []).length || !composeMentionsAttachment()) return true;
        const result = await Swal.fire({
          target: activeSwalTarget(),
          icon: 'warning',
          title: 'Did you forget the attachment?',
          text: 'Your message mentions an attachment or file, but no attachment has been added.',
          showCancelButton: true,
          confirmButtonText: 'Send anyway',
          cancelButtonText: 'Go back',
          reverseButtons: true,
          confirmButtonColor: initialSettings.primary_color || '#1769aa'
        });
        return result.isConfirmed;
      }

      async function sendBulkForward() {
        const sendButton = $('#sendEmail');
        const wasDisabled = Boolean(sendButton?.disabled);
        if (sendButton) sendButton.disabled = true;
        try {
          if (!commitPendingRecipientInputs()) {
            throw new Error('Check the recipient email address.');
          }
          const recipients = [
            ...state.recipients.to,
            ...state.recipients.cc,
            ...state.recipients.bcc
          ];
          if (!recipients.length) {
            throw new Error('Add at least one recipient.');
          }
          if (!state.bulkForwardUids.length) {
            throw new Error('No selected emails are available to forward.');
          }
          if (!await askAboutUnknownContacts()) return;
          const count = state.bulkForwardUids.length;
          const result = await api('bulk_forward', {
            folder: state.bulkForwardFolder || state.folder,
            uids: state.bulkForwardUids,
            to: state.recipients.to,
            cc: state.recipients.cc,
            bcc: state.recipients.bcc
          }, {spinnerText: `Forwarding ${count} email${count === 1 ? '' : 's'} separately…`});
          const sentCount = Math.max(0, Number(result.sentCount || 0));
          const failedCount = Math.max(0, Number(result.failedCount || 0));
          const warnings = Array.isArray(result.warnings) ? result.warnings : [];
          const failures = Array.isArray(result.failures) ? result.failures : [];

          if (sentCount > 0) {
            noteSentMessage(sentCount);
            state.skipDraftOnClose = true;
            state.composeDirty = false;
            composeModal.hide();
          }

          if (failedCount > 0) {
            const firstFailure = String(failures[0]?.error || 'Unknown error');
            toast(
              `${sentCount} forwarded, ${failedCount} failed. First error: ${firstFailure}`,
              sentCount > 0 ? 'warning' : 'danger'
            );
          } else if (warnings.length) {
            toast(
              `${sentCount} email${sentCount === 1 ? '' : 's'} forwarded separately. ` +
              `${warnings.length} Sent-folder warning${warnings.length === 1 ? '' : 's'}.`,
              'warning'
            );
          } else {
            toast(`${sentCount} email${sentCount === 1 ? '' : 's'} forwarded separately.`);
          }
        } catch (error) {
          handleError(error);
        } finally {
          if (sendButton) sendButton.disabled = wasDisabled;
        }
      }

      async function sendCompose() {
        if (state.composeMode === 'bulk-forward') {
          await sendBulkForward();
          return;
        }
        const sendButton = $('#sendEmail');
        const wasDisabled = Boolean(sendButton?.disabled);
        if (sendButton) sendButton.disabled = true;
        try {
          const payload = await composePayload(false);
          if (![...payload.to, ...payload.cc, ...payload.bcc].length) {
            throw new Error('Add at least one recipient.');
          }
          if (!await confirmPossibleMissingAttachment(payload)) return;
          if (!await askAboutUnknownContacts()) return;
          payload.attachments = await uploadComposeAttachments();
          const draftId = $('#composePseId').value;
          const sent = await api('send_message', payload, {spinnerText: 'Sending email…'});
          if (draftId) {
            await api('delete_pse', {id: draftId}, {spinner: false});
            await refreshSavedCount(false);
          }
          state.skipDraftOnClose = true;
          state.composeDirty = false;
          composeModal.hide();
          if (sent.sentCopyWarning) {
            toast(sent.sentCopyWarning, 'warning');
          } else if (sent.signatureApplied) {
            toast('Email sent with HTML signature.');
          } else {
            toast('Email sent.');
          }
          noteSentMessage();
        } catch (error) {
          updateImageProgress(0, '');
          handleError(error);
        } finally {
          if (sendButton) sendButton.disabled = wasDisabled;
        }
      }

      async function saveCompose() {
        if (!initialSettings.compose_save_drafts) return;
        try {
          const message = await composePayload();
          const result = await api('save_pse', {
            id: $('#composePseId').value,
            message
          }, {spinnerText: 'Saving draft…'});
          $('#composePseId').value = result.saved.id;
          state.composeDirty = false;
          await refreshSavedCount(false);
          state.skipDraftOnClose = true;
          composeModal.hide();
          toast('Draft saved. Reopen it from Saved drafts (.PSE) in the left column.');
        } catch (error) {
          handleError(error);
        }
      }

      async function deleteComposeForever() {
        const draftId = $('#composePseId').value;
        try {
          if (draftId) {
            await api('delete_pse', {id: draftId}, {spinnerText: 'Deleting draft forever…'});
            await refreshSavedCount(false);
          }
          state.skipDraftOnClose = true;
          state.composeDirty = false;
          $('#composeModal').addEventListener('hidden.bs.modal', resetCompose, {once: true});
          composeModal.hide();
          toast(draftId ? 'Draft deleted forever.' : 'Email discarded.', 'info');
        } catch (error) {
          handleError(error);
        }
      }

      function composeHasContent() {
        return Boolean(
          $('#composeSubject').value.trim() ||
          $('#composeBody').innerText.replace(/\u200b/g, '').trim() ||
          $('#composeBody').querySelector('img') ||
          state.composeFiles.length ||
          state.recipients.to.length ||
          state.recipients.cc.length ||
          state.recipients.bcc.length ||
          $$('.pse-recipient-input').some(input => input.value.trim())
        );
      }

      async function autoSaveComposeDraft() {
        if (state.composeMode === 'bulk-forward') {
          state.skipDraftOnClose = false;
          return;
        }
        if (!initialSettings.compose_save_drafts) {
          state.skipDraftOnClose = false;
          return;
        }
        if (state.skipDraftOnClose) {
          state.skipDraftOnClose = false;
          return;
        }
        if (!state.composeDirty || !composeHasContent()) return;
        try {
          const message = await composePayload();
          if (!composeHasContent()) return;
          const result = await api('save_pse', {
            id: $('#composePseId').value,
            message
          }, {spinner: false});
          $('#composePseId').value = result.saved.id;
          state.composeDirty = false;
          await refreshSavedCount(false);
          toast('Draft saved automatically.', 'info');
        } catch (error) {
          handleError(error);
        }
      }

      function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      }

      function applyPseRecord(record) {
        if (!record || record.format !== 'PSE/1' || !record.message) {
          throw new Error('This is not a valid PSE/1 email file.');
        }
        const message = record.message;
        state.recipients.to = Array.isArray(message.to) ? message.to : [];
        state.recipients.cc = Array.isArray(message.cc) ? message.cc : [];
        state.recipients.bcc = Array.isArray(message.bcc) ? message.bcc : [];
        ['to', 'cc', 'bcc'].forEach(field => renderRecipientChips(field));
        $('#composeSubject').value = message.subject || '';
        $('#composeBody').innerHTML = message.bodyHtml || escapeHtml(message.bodyText || '').replace(/\n/g, '<br>');
        state.composeSignatureManaged = Boolean(
          message.signatureHandled ||
          $('#composeBody').querySelector('[data-pse-signature="1"]')
        );
        if (!state.composeSignatureManaged) {
          insertComposeSignature('before-quote');
        }
        setDefaultComposeRange();
        state.composeFiles = (message.attachments || []).map(item => ({
          name: item.name || 'attachment.bin',
          type: item.type || 'application/octet-stream',
          data: item.data || ''
        }));
        $('#composePseId').value = record.id || '';
        setRecipientRowVisibility('cc', true);
        setRecipientRowVisibility('bcc', true);
        renderAttachmentList();
        state.composeDirty = false;
        state.skipDraftOnClose = false;
      }

      async function openSaved() {
        try {
          const result = await api('saved_list', {}, {spinnerText: 'Loading saved emails…'});
          updateSavedDraftCount(result.items.length);
          const root = $('#savedList');
          if (!result.items.length) {
            root.innerHTML = '<div class="pse-empty"><div>No editable drafts yet.</div></div>';
          } else {
            root.innerHTML = result.items.map(item => `
              <div class="pse-saved-row">
                <div class="min-w-0">
                  <div class="pse-saved-title">${escapeHtml(item.subject || '(No subject)')}</div>
                  <div class="small text-secondary">${escapeHtml(item.updatedAt)} · ${formatBytes(item.size)}</div>
                </div>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary load-saved" data-id="${escapeHtml(item.id)}"><i class="fa-solid fa-folder-open me-1"></i>Open</button>
                  <button class="btn btn-outline-danger delete-saved" data-id="${escapeHtml(item.id)}"><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            `).join('');
            $$('.load-saved', root).forEach(button => button.addEventListener('click', () => loadSaved(button.dataset.id)));
            $$('.delete-saved', root).forEach(button => button.addEventListener('click', () => deleteSaved(button.dataset.id)));
          }
          savedModal.show();
        } catch (error) {
          handleError(error);
        }
      }

      function updateSavedDraftCount(count) {
        count = Math.max(0, Number(count || 0));
        $('#savedBadge').textContent = count;
        $('#savedModalCount').textContent = count;
        $('#deleteAllSaved').disabled = count === 0;
      }

      async function refreshSavedCount(spinner = false) {
        try {
          const result = await api('saved_list', {}, {spinner, spinnerText: 'Checking saved drafts…'});
          updateSavedDraftCount(result.items.length);
          return result.items.length;
        } catch (error) {
          console.error(error);
          return 0;
        }
      }

      async function deleteAllSaved() {
        if ($('#deleteAllSaved').disabled) return;
        const confirmation = await swalTypedConfirmation(
          'Delete all saved drafts?',
          'Every saved .PSE draft will be permanently removed.',
          'Delete all',
          'I AM SURE'
        );
        if (!confirmation) return;
        try {
          const result = await api('delete_all_pse', {
            confirmation
          }, {spinnerText: 'Deleting all saved drafts…'});
          updateSavedDraftCount(0);
          $('#savedList').innerHTML = '<div class="pse-empty"><div>No editable drafts yet.</div></div>';
          toast(`${result.deleted} saved draft${Number(result.deleted) === 1 ? '' : 's'} deleted.`);
        } catch (error) {
          handleError(error);
        }
      }

      async function loadSaved(id) {
        try {
          const result = await api('load_pse', {id}, {spinnerText: 'Opening saved email…'});
          resetCompose();
          applyPseRecord(result.data);
          savedModal.hide();
          composeModal.show();
        } catch (error) {
          handleError(error);
        }
      }

      async function deleteSaved(id) {
        if (!await swalConfirm('Delete this draft?', 'This saved PSE draft will be removed.', 'Delete')) return;
        try {
          await api('delete_pse', {id});
          await openSaved();
        } catch (error) {
          handleError(error);
        }
      }

      function colorIsDark(color) {
        const value = String(color || '').replace('#', '');
        if (!/^[0-9a-f]{6}$/i.test(value)) return false;
        const red = parseInt(value.slice(0, 2), 16);
        const green = parseInt(value.slice(2, 4), 16);
        const blue = parseInt(value.slice(4, 6), 16);
        return ((red * 299) + (green * 587) + (blue * 114)) / 1000 < 132;
      }

      function currentThemeColors() {
        return {
          primary_color: $('#primary_color').value,
          accent_color: $('#accent_color').value,
          background_color: $('#background_color').value,
          panel_color: $('#panel_color').value
        };
      }

      function updateThemePreview() {
        const preview = $('#themePreview');
        const colors = currentThemeColors();
        preview.innerHTML = Object.entries(colors).map(([name, color]) =>
          `<span class="flex-fill" style="background:${escapeHtml(color)}" title="${escapeHtml(name.replace('_color', '').replace('_', ' '))}: ${escapeHtml(color)}"></span>`
        ).join('');
      }

      function applyThemeToInterface(themeId = $('#theme').value, copyPalette = true) {
        const theme = uiThemes[themeId];
        if (theme && copyPalette) {
          ['primary_color', 'accent_color', 'background_color', 'panel_color'].forEach(key => {
            $('#' + key).value = theme[key];
          });
        }
        const colors = currentThemeColors();
        const dark = theme ? theme.mode === 'dark' : colorIsDark(colors.background_color);
        const root = document.documentElement.style;
        root.setProperty('--pse-primary', colors.primary_color);
        root.setProperty('--pse-accent', colors.accent_color);
        root.setProperty('--pse-bg', colors.background_color);
        root.setProperty('--pse-panel', colors.panel_color);
        root.setProperty('--pse-text', dark ? '#e7edf5' : '#253044');
        root.setProperty('--pse-muted', dark ? '#aab5c4' : '#687385');
        root.setProperty('--pse-border', dark ? '#3a4658' : '#dfe4ec');
        root.setProperty('--pse-hover', dark ? '#26364b' : '#f4f7fb');
        root.setProperty('--pse-input', dark ? '#1d293b' : '#ffffff');
        document.body.classList.toggle('pse-theme-dark', dark);
        updateThemePreview();
      }

      function applyDensityToInterface(density) {
        [...document.body.classList].forEach(className => {
          if (className.startsWith('pse-density-')) document.body.classList.remove(className);
        });
        document.body.classList.add(`pse-density-${density}`);
        document.documentElement.style.setProperty(
          '--pse-font-size',
          density === 'compact' ? '13px' : (density === 'large' ? '16px' : '14px')
        );
      }

      function updateAccountTypeUi(settings = initialSettings) {
        const gmail = $('#account_type').value === 'gmail';
        $$('.regular-imap-settings').forEach(element => element.classList.toggle('d-none', gmail));
        $$('.gmail-account-settings').forEach(element => element.classList.toggle('d-none', !gmail));
        const connected = gmail && Boolean(settings.google_connected);
        const reconnectRequired = gmail && Boolean(settings.google_reconnect_required);
        const email = String(settings.google_oauth_email || '').trim();
        const status = $('#googleConnectionStatus');
        status.className = `badge ${reconnectRequired ? 'text-bg-danger' : (connected ? 'text-bg-success' : 'text-bg-secondary')}`;
        status.textContent = reconnectRequired
          ? `Reconnect required${email ? ` for ${email}` : ''}`
          : (connected ? `Connected${email ? ` as ${email}` : ''}` : 'Not connected');
        $('#connectGoogle').innerHTML = connected || reconnectRequired
          ? '<i class="fa-brands fa-google me-1"></i>Reconnect Google'
          : '<i class="fa-brands fa-google me-1"></i>Connect with Google';
        $('#disconnectGoogle').classList.toggle('d-none', !connected);
      }

      function formatStorageBytes(bytes) {
        bytes = Math.max(0, Number(bytes || 0));
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes;
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
          value /= 1024;
          unit++;
        }
        const decimals = unit === 0 ? 0 : (value < 10 ? 2 : (value < 100 ? 1 : 0));
        return `${value.toFixed(decimals)} ${units[unit]}`;
      }

      function renderCacheUsage(usage) {
        const total = usage.total || {};
        $('#cacheUsageTotal').textContent = formatStorageBytes(total.bytes);
        $('#cacheUsageSummary').textContent = `${Number(total.files || 0).toLocaleString()} files — ${formatStorageBytes(total.mailBytes)} mailbox data + ${formatStorageBytes(total.assetBytes)} images and attachments`;
        const rows = (usage.accounts || []).map(account => `
          <tr>
            <td>
              <div class="fw-semibold">${escapeHtml(account.name || account.id)}</div>
              <div class="small text-secondary">${account.type === 'gmail' ? 'Gmail API' : 'IMAP'}</div>
            </td>
            <td class="text-end">${escapeHtml(formatStorageBytes(account.mailBytes))}</td>
            <td class="text-end">${escapeHtml(formatStorageBytes(account.assetBytes))}</td>
            <td class="text-end fw-semibold">${escapeHtml(formatStorageBytes(account.bytes))}</td>
            <td class="text-end">${Number(account.files || 0).toLocaleString()}</td>
          </tr>
        `);
        const other = usage.other || {};
        if (Number(other.bytes || 0) > 0 || Number(other.files || 0) > 0) {
          rows.push(`
            <tr>
              <td><div class="fw-semibold">Other / legacy cache</div><div class="small text-secondary">Unassigned or temporary cache files</div></td>
              <td class="text-end">—</td>
              <td class="text-end">—</td>
              <td class="text-end fw-semibold">${escapeHtml(formatStorageBytes(other.bytes))}</td>
              <td class="text-end">${Number(other.files || 0).toLocaleString()}</td>
            </tr>
          `);
        }
        $('#cacheUsageAccounts').innerHTML = rows.length
          ? rows.join('')
          : '<tr><td colspan="5" class="text-center text-secondary py-4">No offline cache is currently stored.</td></tr>';
      }

      async function loadCacheUsage() {
        $('#cacheUsageTotal').textContent = 'Calculating…';
        $('#cacheUsageSummary').textContent = '';
        $('#cacheUsageAccounts').innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-4"><span class="spinner-border spinner-border-sm me-2"></span>Calculating cache usage…</td></tr>';
        try {
          const result = await api('cache_usage', {}, {spinner: false});
          renderCacheUsage(result.usage || {});
        } catch (error) {
          $('#cacheUsageTotal').textContent = 'Unable to calculate';
          $('#cacheUsageAccounts').innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
        }
      }

      function renderSidebarSpaceUsage(usage, quota) {
        const total = usage.total || {};
        const accounts = Array.isArray(usage.accounts) ? usage.accounts : [];
        const activeAccount = accounts.find(account => account.id === initialSettings.account_id) || null;
        $('#spaceUsedBadge').textContent = formatStorageBytes(total.bytes);
        const accountRows = accounts.map(account => `
          <div class="pse-space-account">
            <div class="d-flex justify-content-between gap-2 fw-semibold" title="${escapeHtml(account.name || account.id)}">
              <span class="text-truncate">${escapeHtml(account.name || account.id)}</span>
              <span class="text-nowrap">${account.type === 'gmail' ? 'Gmail' : 'IMAP'}</span>
            </div>
            <div class="pse-space-row"><span>Mailbox</span><span>${escapeHtml(formatStorageBytes(account.mailBytes))}</span></div>
            <div class="pse-space-row"><span>Images/files</span><span>${escapeHtml(formatStorageBytes(account.assetBytes))}</span></div>
            <div class="pse-space-row"><span>Total</span><b>${escapeHtml(formatStorageBytes(account.bytes))}</b></div>
            <div class="pse-space-row"><span>Files</span><span>${Number(account.files || 0).toLocaleString()}</span></div>
          </div>
        `).join('');
        const quotaVisible = initialSettings.account_type === 'imap';
        let quotaHtml = '';
        if (quotaVisible) {
          if (quota && quota.supported) {
            const percentage = Number(quota.limitBytes || 0) > 0
              ? Math.min(100, Math.max(0, Number(quota.usedBytes || 0) / Number(quota.limitBytes) * 100))
              : 0;
            quotaHtml = `
              <div class="pse-space-section">
                <div class="fw-semibold mb-1"><i class="fa-solid fa-server me-1"></i>IMAP mailbox quota</div>
                <div class="pse-space-row"><span>Used</span><b>${escapeHtml(formatStorageBytes(quota.usedBytes))}</b></div>
                <div class="pse-space-row"><span>Available</span><b>${escapeHtml(formatStorageBytes(quota.availableBytes))}</b></div>
                <div class="pse-space-row"><span>Total</span><b>${escapeHtml(formatStorageBytes(quota.limitBytes))}</b></div>
                <div class="progress mt-1" style="height:5px" title="${percentage.toFixed(1)}% used">
                  <div class="progress-bar" style="width:${percentage.toFixed(2)}%"></div>
                </div>
              </div>`;
          } else {
            quotaHtml = `
              <div class="pse-space-section">
                <div class="fw-semibold mb-1"><i class="fa-solid fa-server me-1"></i>IMAP mailbox quota</div>
                <div class="text-secondary">${escapeHtml(quota?.message || 'Quota information is not available from this IMAP server.')}</div>
              </div>`;
          }
        }
        $('#spaceUsedContent').innerHTML = `
          <div class="pse-space-section">
            <div class="fw-semibold mb-1"><i class="fa-solid fa-cloud-arrow-down me-1"></i>Local offline/cache</div>
            <div class="pse-space-row"><span>All accounts</span><b>${escapeHtml(formatStorageBytes(total.bytes))}</b></div>
            <div class="pse-space-row"><span>Mailbox data</span><span>${escapeHtml(formatStorageBytes(total.mailBytes))}</span></div>
            <div class="pse-space-row"><span>Images &amp; attachments</span><span>${escapeHtml(formatStorageBytes(total.assetBytes))}</span></div>
            <div class="pse-space-row"><span>Files</span><span>${Number(total.files || 0).toLocaleString()}</span></div>
            ${Number(usage.other?.bytes || 0) > 0 ? `<div class="pse-space-row"><span>Other / legacy</span><span>${escapeHtml(formatStorageBytes(usage.other.bytes))}</span></div>` : ''}
            ${activeAccount ? `<div class="pse-space-row mt-1"><span>Current account</span><b>${escapeHtml(formatStorageBytes(activeAccount.bytes))}</b></div>` : ''}
          </div>
          ${accountRows ? `<div class="pse-space-section"><div class="fw-semibold mb-1">By account</div>${accountRows}</div>` : ''}
          ${quotaHtml}
        `;
      }

      async function loadSidebarSpaceUsage(force = false) {
        if (state.sidebarSpaceLoaded && !force) return;
        $('#spaceUsedContent').innerHTML = '<div class="text-center text-secondary py-2"><span class="spinner-border spinner-border-sm me-1"></span>Calculating…</div>';
        try {
          const result = await api('cache_usage', {
            includeQuota: true,
            folder: state.folder
          }, {spinner: false});
          renderSidebarSpaceUsage(result.usage || {}, result.quota || null);
          state.sidebarSpaceLoaded = true;
        } catch (error) {
          $('#spaceUsedContent').innerHTML = `<div class="text-danger py-1">${escapeHtml(error.message)}</div>`;
        }
      }

      function closeQuickAccountMenu() {
        const menu = $('#accountQuickMenu');
        const button = $('#activeAccountButton');
        if (menu) menu.hidden = true;
        if (button) button.setAttribute('aria-expanded', 'false');
      }

      function renderQuickAccountSwitcher(settings = initialSettings) {
        const button = $('#activeAccountButton');
        const name = $('#activeAccountName');
        const menu = $('#accountQuickMenu');
        const chevron = $('#accountQuickChevron');
        if (!button || !name || !menu || !chevron) return;

        const accounts = Array.isArray(settings.accounts) ? settings.accounts : [];
        const switchable = accounts.length > 1;
        name.textContent = settings.account_name || 'Account';
        button.title = switchable ? `Switch account — ${settings.account_name || 'Account'}` : (settings.account_name || 'Account');
        button.classList.toggle('switchable', switchable);
        button.disabled = !switchable;
        button.setAttribute('aria-disabled', switchable ? 'false' : 'true');
        chevron.classList.toggle('d-none', !switchable);

        if (!switchable) {
          menu.innerHTML = '';
          closeQuickAccountMenu();
          return;
        }

        menu.innerHTML = accounts.map(account => {
          const active = String(account.id) === String(settings.account_id);
          const detail = account.google_email || account.username || account.from_email || (account.type === 'gmail' ? 'Google account' : 'IMAP account');
          const icon = account.type === 'gmail' ? 'fa-brands fa-google' : 'fa-solid fa-envelope';
          return `
            <button class="pse-account-menu-item${active ? ' active' : ''}" type="button" role="menuitem" data-account-id="${escapeHtml(account.id)}"${active ? ' aria-current="true"' : ''}>
              <span class="pse-account-menu-icon"><i class="${icon}"></i></span>
              <span class="pse-account-menu-copy"><span class="pse-account-menu-name">${escapeHtml(account.name || account.id)}</span><span class="pse-account-menu-detail">${escapeHtml(detail)}</span></span>
              <span class="pse-account-menu-check">${active ? '<i class="fa-solid fa-check"></i>' : ''}</span>
            </button>`;
        }).join('');

        $$('.pse-account-menu-item', menu).forEach(item => {
          item.addEventListener('click', () => quickSwitchAccount(item.dataset.accountId || ''));
        });
      }

      async function quickSwitchAccount(accountId) {
        accountId = String(accountId || '');
        if (!accountId || accountId === String(initialSettings.account_id || '')) {
          closeQuickAccountMenu();
          return;
        }
        closeQuickAccountMenu();
        try {
          const result = await api('switch_account', {account_id: accountId}, {spinnerText: 'Switching email account…'});
          const oldName = initialSettings.account_name || 'account';
          const newName = result.settings?.account_name || 'account';
          try { sessionStorage.setItem('pse_account_switched', `${oldName} → ${newName}`); } catch (ignore) {}
          location.reload();
        } catch (error) {
          handleError(error);
        }
      }

      function applySettingsToForm(settings) {
        Object.assign(serverSettings, settings);
        const effectiveSettings = effectiveSettingsFromServer(settings);
        Object.assign(initialSettings, effectiveSettings);
        const accountSelect = $('#settingsAccountSelect');
        accountSelect.innerHTML = (effectiveSettings.accounts || []).map(account => {
          const detail = account.google_email || account.username || account.from_email || 'Not configured';
          const type = account.type === 'gmail' ? 'Google' : 'IMAP';
          return `<option value="${escapeHtml(account.id)}">${escapeHtml(account.name)} [${type}] — ${escapeHtml(detail)}</option>`;
        }).join('');
        accountSelect.value = effectiveSettings.account_id;
        $$('.setting').forEach(field => {
          if (!(field.id in effectiveSettings)) return;
          if (field.type === 'checkbox') {
            field.checked = Boolean(effectiveSettings[field.id]);
          } else {
            field.value = effectiveSettings[field.id] ?? '';
          }
        });
        $('#imap_password').value = '';
        $('#smtp_password').value = '';
        $('#google_client_secret').value = '';
        $('#new_app_password').value = '';
        $('#imap_password').placeholder = effectiveSettings.imap_password_set ? 'Saved — leave blank to keep it' : 'Enter password / App Password';
        $('#smtp_password').placeholder = effectiveSettings.smtp_password_set ? 'Saved — leave blank to keep it' : 'Enter password / App Password';
        $('#google_client_secret').placeholder = effectiveSettings.google_client_secret_set
          ? 'Saved — leave blank to keep it'
          : 'Enter Google OAuth Client Secret';
        $('#google_redirect_uri').value = effectiveSettings.google_redirect_uri || '';
        renderQuickAccountSwitcher(effectiveSettings);
        const appTitle = String(effectiveSettings.app_title || 'PSE Email');
        document.title = appTitle;
        const brandTitle = $('.pse-brand-title');
        const brand = $('.pse-brand');
        if (brandTitle) brandTitle.textContent = appTitle;
        if (brand) brand.title = appTitle;
        applyThemeToInterface(effectiveSettings.theme || 'custom', false);
        applyDensityToInterface(effectiveSettings.density || 'medium');
        updateAccountTypeUi(effectiveSettings);
        updateComposeDraftUi();
        updateCalendarButton();
        updateMobilePaneNavigation();
      }

      async function forceRefreshFolderNames() {
        const button = $('#forceRefreshFolderNames');
        const status = $('#forceRefreshFolderNamesStatus');
        const previousFolders = Array.isArray(state.folders) ? [...state.folders] : [];
        const previousIds = new Set(previousFolders.map(folder => String(folder.id)));
        if (button) button.disabled = true;
        if (status) status.textContent = 'Refreshing directly from server…';
        try {
          const result = await api('folders', {forceRefresh: true}, {
            spinnerText: 'Refreshing folder names from server…'
          });
          if (result.cache?.refreshError) {
            throw new Error(result.cache.refreshError);
          }
          const folders = Array.isArray(result.folders) ? result.folders : [];
          const currentIds = new Set(folders.map(folder => String(folder.id)));
          const removedIds = [...previousIds].filter(id => !currentIds.has(id));

          state.folderCache = folders;
          state.folders = folders;
          for (const folderId of result.changedFolders || []) {
            const id = String(folderId);
            state.staleFolders.add(id);
            invalidateMessageListCacheForFolder(id);
          }

          if (state.lastSearch) {
            const savedSearchFolder = folders.find(
              folder => String(folder.id) === String(state.lastSearch.folder || '')
            );
            try {
              if (!savedSearchFolder) {
                state.lastSearch = null;
                localStorage.removeItem(lastSearchStorageKey());
              } else if (state.lastSearch.folderName !== savedSearchFolder.name) {
                state.lastSearch.folderName = savedSearchFolder.name;
                localStorage.setItem(lastSearchStorageKey(), JSON.stringify(state.lastSearch));
              }
            } catch (error) {
              // Browser storage cleanup/update is best-effort only.
            }
          }

          let currentFolder = folders.find(folder => String(folder.id) === String(state.folder));
          let switchedFolder = false;
          if (!currentFolder) {
            currentFolder = folders.find(folder => folder.special === 'inbox') || folders[0] || null;
            if (currentFolder) {
              state.folder = currentFolder.id;
              state.page = 1;
              state.selectedUid = null;
              state.currentMessage = null;
              state.selectedUids.clear();
              state.allPagesSelected = false;
              state.search = '';
              state.senderFilter = '';
              state.startDate = '';
              state.calendarActive = false;
              state.calendarMonth = '';
              $('#globalSearch').value = '';
              $('#clearSearch').classList.add('d-none');
              switchedFolder = true;
            }
          }
          if (currentFolder) state.folderName = currentFolder.name;
          renderFolders();
          updateMailboxViewControls();
          updateLastSyncStatus(result.cache, 'Folders');

          if (switchedFolder && currentFolder) {
            clearPreview();
            await loadMessages(1, false, false, `Loading ${currentFolder.name}…`);
          }

          const removedText = removedIds.length
            ? ` Removed ${removedIds.length} folder${removedIds.length === 1 ? '' : 's'} that no longer exist.`
            : ' No obsolete cached folders were found.';
          const message = `Folder names refreshed: ${folders.length} folder${folders.length === 1 ? '' : 's'}.${removedText}`;
          if (status) status.textContent = message;
          toast(message, 'info');
        } catch (error) {
          if (status) status.textContent = `Refresh failed: ${error.message}`;
          handleError(error);
        } finally {
          if (button) button.disabled = false;
        }
      }

      function refreshApplicationIcon(version) {
        appIconVersion = String(version || Date.now());
        const url = appIconUrl();
        ['settingsAppIconPreview', 'pwaSettingsIconPreview'].forEach(id => {
          const image = $('#' + id);
          if (image) image.src = url;
        });
        $$('.pse-brand-avatar img').forEach(image => { image.src = url; });
        document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]').forEach(link => {
          link.href = url;
        });
      }

      function loadLocalImage(file) {
        return new Promise((resolve, reject) => {
          const url = URL.createObjectURL(file);
          const image = new Image();
          image.onload = () => resolve({image, url});
          image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('The selected file could not be opened as an image.'));
          };
          image.src = url;
        });
      }

      function canvasToPngBlob(canvas) {
        return new Promise((resolve, reject) => {
          canvas.toBlob(blob => {
            if (blob) resolve(blob);
            else reject(new Error('The browser could not prepare the PNG icon.'));
          }, 'image/png');
        });
      }

      async function resizeSquareIconLocally(file, image) {
        const naturalSize = Math.max(1, Math.min(image.naturalWidth, image.naturalHeight));
        const size = Math.min(256, naturalSize);
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('The browser could not resize the application icon.');
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.clearRect(0, 0, size, size);
        context.drawImage(image, 0, 0, image.naturalWidth, image.naturalHeight, 0, 0, size, size);
        return canvasToPngBlob(canvas);
      }

      async function cropApplicationIconLocally(file, image, imageUrl) {
        if (typeof Cropper === 'undefined') {
          throw new Error('The image crop tool could not be loaded. Check the internet connection and try again.');
        }
        let cropper = null;
        const result = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Crop application icon',
          html: `
            <div class="text-start small text-secondary mb-2">Drag or zoom the image. The saved icon will be square and no larger than 256×256.</div>
            <div style="height:min(56vh,460px);background:#111;border-radius:12px;overflow:hidden">
              <img id="pseAppIconCropImage" src="${escapeHtml(imageUrl)}" alt="Crop application icon" style="display:block;max-width:100%">
            </div>`,
          width: 700,
          showCancelButton: true,
          confirmButtonText: '<i class="fa-solid fa-crop-simple me-1"></i>Crop & use',
          cancelButtonText: 'Cancel',
          focusConfirm: false,
          didOpen: () => {
            const cropImage = document.getElementById('pseAppIconCropImage');
            cropper = new Cropper(cropImage, {
              aspectRatio: 1,
              viewMode: 1,
              autoCropArea: 0.9,
              responsive: true,
              background: false,
              guides: true,
              center: true,
              movable: true,
              zoomable: true,
              scalable: false,
              rotatable: false
            });
          },
          preConfirm: async () => {
            if (!cropper) {
              Swal.showValidationMessage('The crop tool is not ready yet.');
              return false;
            }
            const data = cropper.getData(true);
            const sourceSide = Math.max(1, Math.floor(Math.min(data.width || 1, data.height || 1)));
            const size = Math.min(256, sourceSide);
            const canvas = cropper.getCroppedCanvas({
              width: size,
              height: size,
              imageSmoothingEnabled: true,
              imageSmoothingQuality: 'high',
              fillColor: 'transparent'
            });
            if (!canvas) {
              Swal.showValidationMessage('Unable to create the cropped icon.');
              return false;
            }
            try {
              return await canvasToPngBlob(canvas);
            } catch (error) {
              Swal.showValidationMessage(error.message);
              return false;
            }
          },
          willClose: () => {
            cropper?.destroy();
            cropper = null;
          }
        });
        return result.isConfirmed && result.value instanceof Blob ? result.value : null;
      }

      async function prepareApplicationIcon(file) {
        const loaded = await loadLocalImage(file);
        try {
          const {image, url} = loaded;
          if (!image.naturalWidth || !image.naturalHeight) {
            throw new Error('The selected image has invalid dimensions.');
          }
          if (image.naturalWidth === image.naturalHeight) {
            return await resizeSquareIconLocally(file, image);
          }
          return await cropApplicationIconLocally(file, image, url);
        } finally {
          URL.revokeObjectURL(loaded.url);
        }
      }

      async function uploadApplicationIcon(file) {
        if (!file) return;
        if (file.size > 5242880) {
          toast('The application icon cannot exceed 5 MB.', 'warning');
          $('#appIconFile').value = '';
          return;
        }
        try {
          const prepared = await prepareApplicationIcon(file);
          if (!prepared) {
            $('#appIconFile').value = '';
            return;
          }
          const form = new FormData();
          form.append('icon', prepared, 'icon.png');
          const result = await api('upload_app_icon', form, {spinnerText: 'Updating application icon…'});
          refreshApplicationIcon(result.icon?.version || Date.now());
          $('#appIconFile').value = '';
          toast(`Application icon updated (${result.icon?.width || 256}×${result.icon?.height || 256}).`);
        } catch (error) {
          $('#appIconFile').value = '';
          handleError(error);
        }
      }

      let lastUpdateInfo = null;

      function renderApplicationUpdateStatus(update) {
        if (!update) return;
        lastUpdateInfo = update;
        const status = $('#updateSettingsStatus');
        const installButton = $('#installUpdateNow');
        if (status) {
          if (update.updateAvailable) {
            status.innerHTML = `<span class="text-success fw-semibold">Update available:</span> <strong>${escapeHtml(update.currentVersion)}</strong> <i class="fa-solid fa-arrow-right mx-1"></i> <strong>${escapeHtml(update.latestVersion)}</strong>`;
          } else if (update.status === 'current') {
            status.innerHTML = `Current version <strong>${escapeHtml(update.currentVersion)}</strong> — up to date.`;
          } else {
            status.innerHTML = `Current version <strong>${escapeHtml(update.currentVersion)}</strong> — ${escapeHtml(update.message || 'Update status unavailable.')}`;
          }
        }
        if (installButton) {
          installButton.classList.toggle('d-none', !update.updateAvailable);
          installButton.disabled = !update.updateAvailable;
          if (update.updateAvailable) {
            installButton.innerHTML = `<i class="fa-solid fa-download me-1"></i>${escapeHtml(update.currentVersion)} → ${escapeHtml(update.latestVersion)}`;
          }
        }
      }

      async function promptApplicationUpdate(update) {
        if (!update?.updateAvailable) return false;
        const answer = await Swal.fire({
          target: activeSwalTarget(),
          icon: 'info',
          title: 'PSE update available',
          html: `
            <div class="py-2">
              <div class="fs-4 fw-bold">${escapeHtml(update.currentVersion)} <i class="fa-solid fa-arrow-right mx-2 text-pse"></i> ${escapeHtml(update.latestVersion)}</div>
              <div class="text-secondary mt-2">A newer version is available from the official GitHub repository.</div>
            </div>`,
          showCancelButton: true,
          confirmButtonText: '<i class="fa-solid fa-download me-1"></i>Update now',
          cancelButtonText: 'Later'
        });
        if (answer.isConfirmed) {
          await installApplicationUpdate(update, false);
          return true;
        }
        return false;
      }

      async function installApplicationUpdate(update, automatic = false) {
        if (!update?.updateAvailable) return;
        try {
          const result = await api('apply_update', {
            confirmed: !automatic,
            automatic
          }, {spinnerText: `Updating ${update.currentVersion} → ${update.latestVersion}…`});
          if (!result.updated) {
            renderApplicationUpdateStatus(result.update || update);
            if (!automatic) toast('No newer version is currently available.', 'info');
            return;
          }
          try {
            sessionStorage.setItem('pse_update_applied', JSON.stringify(result.result || {}));
          } catch (ignore) {}
          location.reload();
        } catch (error) {
          if (automatic) {
            toast(`Automatic update failed: ${error.message}`, 'warning');
          } else {
            handleError(error);
          }
        }
      }

      async function checkApplicationUpdate(force = false, startup = false, offerInstall = false) {
        try {
          const result = await api('update_check', {force}, {
            spinner: force,
            spinnerText: 'Checking GitHub for updates…',
            background: !force
          });
          const update = result.update || null;
          renderApplicationUpdateStatus(update);
          if (!update) return null;

          if (update.updateAvailable && startup && initialSettings.auto_update) {
            await installApplicationUpdate(update, true);
            return update;
          }

          if (update.updateAvailable && startup && !initialSettings.auto_update) {
            const promptKey = `pse_update_prompt_${update.latestVersion}`;
            let alreadyPrompted = false;
            try { alreadyPrompted = sessionStorage.getItem(promptKey) === '1'; } catch (ignore) {}
            if (!alreadyPrompted) {
              try { sessionStorage.setItem(promptKey, '1'); } catch (ignore) {}
              await promptApplicationUpdate(update);
            }
          } else if (update.updateAvailable && offerInstall) {
            await promptApplicationUpdate(update);
          } else if (force && update.status === 'current') {
            toast(`PSE ${update.currentVersion} is up to date.`, 'success');
          } else if (force && !update.updateAvailable) {
            toast(update.message || 'No published update is available.', 'info');
          }
          return update;
        } catch (error) {
          if (force) handleError(error);
          const status = $('#updateSettingsStatus');
          if (status) status.textContent = `Current version ${initialSettings.version || '<?= PSE_VERSION ?>'} — update check failed: ${error.message}`;
          return null;
        }
      }

      function showAppliedUpdateNotice() {
        try {
          const raw = sessionStorage.getItem('pse_update_applied');
          if (!raw) return;
          sessionStorage.removeItem('pse_update_applied');
          const update = JSON.parse(raw);
          if (update?.oldVersion && update?.newVersion) {
            toast(`PSE updated ${update.oldVersion} → ${update.newVersion}.`, 'success');
          }
        } catch (ignore) {}
      }

      function showAccountSwitchedNotice() {
        try {
          const text = sessionStorage.getItem('pse_account_switched');
          if (!text) return;
          sessionStorage.removeItem('pse_account_switched');
          toast(`Account switched: ${text}.`, 'success');
        } catch (ignore) {}
      }

      async function openSettings() {
        try {
          const result = await api('settings', {}, {spinnerText: 'Loading settings…'});
          applySettingsToForm(result.settings);
          settingsModal.show();
          loadCacheUsage();
        } catch (error) {
          handleError(error);
        }
      }

      function settingsPayload() {
        const data = {};
        $$('.setting').forEach(field => {
          data[field.id] = field.type === 'checkbox' ? field.checked : field.value;
        });
        data.account_id = $('#settingsAccountSelect').value;
        return data;
      }

      async function switchSettingsAccount(accountId) {
        try {
          const result = await api('switch_account', {
            account_id: accountId
          }, {spinnerText: 'Switching email account…'});
          applySettingsToForm(result.settings);
          resetMailboxState();
          state.accountReloadPending = true;
          toast(`Using ${result.settings.account_name}.`, 'info');
        } catch (error) {
          handleError(error);
          $('#settingsAccountSelect').value = initialSettings.account_id;
        }
      }

      async function addEmailAccount() {
        const prompt = await Swal.fire({
          target: activeSwalTarget(),
          title: 'Add email account',
          html: `
            <label class="form-label w-100 text-start" for="swalAccountName">Account name</label>
            <input class="swal2-input mt-0 w-100" id="swalAccountName" maxlength="80" placeholder="Work email">
            <div class="text-start mt-3">
              <div class="form-label">Account type</div>
              <label class="d-flex align-items-start gap-2 border rounded p-2 mb-2">
                <input class="form-check-input mt-1" name="swalAccountType" type="radio" value="imap" checked>
                <span><b>Regular IMAP</b><br><small class="text-secondary">Use your existing IMAP and SMTP server settings.</small></span>
              </label>
              <label class="d-flex align-items-start gap-2 border rounded p-2">
                <input class="form-check-input mt-1" name="swalAccountType" type="radio" value="gmail">
                <span><b>Gmail / Google Workspace</b><br><small class="text-secondary">Sign in securely with Google OAuth2.</small></span>
              </label>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Add account',
          focusConfirm: false,
          didOpen: () => $('#swalAccountName')?.focus(),
          preConfirm: () => {
            const name = String($('#swalAccountName')?.value || '').trim();
            const type = $('input[name="swalAccountType"]:checked')?.value;
            if (!name) {
              Swal.showValidationMessage('Enter an account name.');
              return false;
            }
            if (!['imap', 'gmail'].includes(type)) {
              Swal.showValidationMessage('Choose an account type.');
              return false;
            }
            return {name, type};
          }
        });
        if (!prompt.isConfirmed) return;
        try {
          const result = await api('create_account', {
            name: prompt.value.name,
            type: prompt.value.type
          }, {spinnerText: 'Creating email account…'});
          applySettingsToForm(result.settings);
          resetMailboxState();
          state.accountReloadPending = true;
          toast(prompt.value.type === 'gmail'
            ? 'Gmail account created. Enter your Google OAuth credentials and connect.'
            : 'Email account created. Enter its IMAP and SMTP details.');
        } catch (error) {
          handleError(error);
        }
      }

      async function deleteEmailAccount() {
        const accountId = $('#settingsAccountSelect').value;
        const accountName = $('#account_name').value.trim() || 'this account';
        const confirmation = await swalTypedConfirmation(
          `Delete ${accountName}?`,
          'Its saved IMAP and SMTP credentials and account-specific settings will be permanently removed.',
          'Delete account'
        );
        if (!confirmation) return;
        try {
          const result = await api('delete_account', {
            account_id: accountId,
            confirmation
          }, {spinnerText: 'Deleting email account…'});
          applySettingsToForm(result.settings);
          resetMailboxState();
          state.accountReloadPending = true;
          toast('Email account deleted.');
        } catch (error) {
          handleError(error);
        }
      }

      async function saveSettings() {
        const pendingAppearance = {};
        appearanceSettingKeys.forEach(key => {
          const field = $('#' + key);
          if (!field) return;
          pendingAppearance[key] = field.type === 'checkbox' ? field.checked : field.value;
        });
        try {
          const result = await api('save_settings', settingsPayload(), {spinnerText: 'Saving settings…'});
          writeStoredAppearance(pendingAppearance, result.settings?.account_id || $('#settingsAccountSelect').value);
          applySettingsToForm(result.settings);
          state.accountReloadPending = false;
          settingsModal.hide();
          toast('Settings saved. Reloading interface…');
          setTimeout(() => location.reload(), 500);
        } catch (error) {
          handleError(error);
        }
      }

      async function connectGoogle() {
        try {
          const saved = await api('save_settings', settingsPayload(), {
            spinnerText: 'Saving Google OAuth settings…'
          });
          applySettingsToForm(saved.settings);
          const result = await api('google_oauth_start', {}, {
            spinnerText: 'Preparing Google sign-in…'
          });
          location.href = result.authorizationUrl;
        } catch (error) {
          handleError(error);
        }
      }

      async function disconnectGoogle() {
        if (!await swalConfirm(
          'Disconnect Google?',
          'The saved OAuth tokens for this account will be removed. You can reconnect later.',
          'Disconnect'
        )) return;
        try {
          const result = await api('google_oauth_disconnect', {}, {spinnerText: 'Disconnecting Google…'});
          applySettingsToForm(result.settings);
          toast('Google account disconnected.', 'info');
        } catch (error) {
          handleError(error);
        }
      }

      async function toggleSameSenderFilter() {
        if (state.senderFilter) {
          state.senderFilter = '';
        } else {
          const email = selectedSenderEmail();
          if (!email) return;
          state.senderFilter = email;
        }
        state.page = 1;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        updateMailboxViewControls();
        try {
          await loadMessages(1, true, false, state.senderFilter
            ? `Filtering messages from ${state.senderFilter}…`
            : 'Showing all senders…');
        } catch (error) {
          handleError(error);
        }
      }

      async function toggleAttachmentFilter() {
        state.attachmentFilter = state.attachmentFilter === 'with' ? 'all' : 'with';
        state.page = 1;
        state.selectedUid = null;
        state.currentMessage = null;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        try {
          localStorage.setItem(attachmentFilterPreferenceKey(), state.attachmentFilter);
        } catch (error) {
          // The filter still works for this tab if browser storage is unavailable.
        }
        clearPreview();
        updateMailboxViewControls();
        const message = state.attachmentFilter === 'with'
          ? 'Showing emails with attachments…'
          : 'Showing all emails…';
        try {
          await loadMessages(1, true, false, message);
        } catch (error) {
          handleError(error);
        }
      }

      let searchTimer = null;
      $('#globalSearch').addEventListener('input', event => {
        clearTimeout(searchTimer);
        pausePrefetch('search-typing', true);
        beginPrefetchView();
        const value = event.target.value;
        $('#clearSearch').classList.toggle('d-none', value === '');
        const delay = Math.max(0, Math.min(60, Number(initialSettings.search_delay_seconds ?? 2))) * 1000;
        searchTimer = setTimeout(async () => {
          searchTimer = null;
          try {
            state.search = value.trim();
            state.selectedUids.clear();
            state.allPagesSelected = false;
            state.selectedUid = null;
            state.currentMessage = null;
            clearPreview();
            await loadMessages(1);
          } finally {
            resumePrefetch('search-typing');
          }
        }, delay);
      });
      $('#clearSearch').addEventListener('click', async () => {
        clearTimeout(searchTimer);
        searchTimer = null;
        pausePrefetch('search-typing', true);
        beginPrefetchView();
        $('#globalSearch').value = '';
        $('#clearSearch').classList.add('d-none');
        state.search = '';
        state.selectedUids.clear();
        state.allPagesSelected = false;
        state.selectedUid = null;
        state.currentMessage = null;
        clearPreview();
        updateMailboxViewControls();
        try {
          await loadMessages(1);
        } finally {
          resumePrefetch('search-typing');
        }
      });
      $('#restoreLastSearch').addEventListener('click', restoreLastSearchResults);

      $('#composeButton').addEventListener('click', openCompose);
      $('#headerCompose').addEventListener('click', openCompose);
      $('#refreshFolders').addEventListener('click', () => hardResync());
      $('#toggleUnreadOnly').addEventListener('click', () => setUnreadOnlyView(!state.unreadOnly));
      $('#filterSameSender').addEventListener('click', toggleSameSenderFilter);
      $('#filterAttachments').addEventListener('click', toggleAttachmentFilter);
      $('#toggleCalendar').addEventListener('click', toggleCalendarView);
      $('#currentFolderSort').addEventListener('click', async () => {
        state.sortOrder = state.sortOrder === 'asc' ? 'desc' : 'asc';
        state.page = 1;
        state.selectedUid = null;
        state.currentMessage = null;
        state.selectedUids.clear();
        state.allPagesSelected = false;
        try {
          localStorage.setItem(sortPreferenceKey(), state.sortOrder);
        } catch (error) {
          // Sorting still works for this tab if browser storage is unavailable.
        }
        updateCurrentFolderHeading();
        clearPreview();
        try {
          await loadMessages(1, true, false, 'Sorting messages…');
        } catch (error) {
          handleError(error);
        }
      });
      $('#toggleMultiSelect').addEventListener('click', toggleMultiSelect);
      $('#bulkSelectAll').addEventListener('click', () => {
        state.allPagesSelected = false;
        state.messages.forEach(message => state.selectedUids.add(message.uid));
        renderMessages();
      });
      $('#bulkSelectAllPages').addEventListener('click', selectAllMessagesAcrossPages);
      $('#bulkClear').addEventListener('click', () => {
        state.selectedUids.clear();
        state.allPagesSelected = false;
        renderMessages();
      });
      $('#bulkRead').addEventListener('click', () => runBulkOperation('read'));
      $('#bulkUnread').addEventListener('click', () => runBulkOperation('unread'));
      $('#bulkForward').addEventListener('click', openBulkForward);
      $('#bulkDelete').addEventListener('click', () => runBulkOperation('delete'));
      $('#bulkRestore').addEventListener('click', () => runBulkOperation('restore'));
      $('#bulkDeleteForever').addEventListener('click', () => runBulkOperation('delete_forever'));
      $('#previousPage').addEventListener('click', () => state.page > 1 && loadMessages(state.page - 1));
      $('#pageLabel').addEventListener('click', event => {
        if (event.target.closest('#currentPageNumber')) beginPageJumpEdit();
      });
      $('#nextPage').addEventListener('click', () => state.page < state.pages && loadMessages(state.page + 1));
      initializeMessagePullPaging();
      $('#contactsButton').addEventListener('click', openContacts);
      $('#savedButton').addEventListener('click', openSaved);
      $('#deleteAllSaved').addEventListener('click', deleteAllSaved);
      $('#footerContactsAction').addEventListener('click', openContacts);
      $('#footerQueueAction').addEventListener('click', undoQueuedDeletes);
      $('#mobilePaneBack').addEventListener('click', () => moveMobilePane(-1));
      $('#mobilePaneForward').addEventListener('click', () => moveMobilePane(1));
      setupMobileSwipeBack();
      $('#refreshCacheUsage').addEventListener('click', loadCacheUsage);
      $('#spaceUsedDetails').addEventListener('show.bs.collapse', () => loadSidebarSpaceUsage(true));
      $('#spaceUsedDetails').addEventListener('shown.bs.collapse', () => {
        $('#spaceUsedToggle').setAttribute('aria-expanded', 'true');
      });
      $('#spaceUsedDetails').addEventListener('hidden.bs.collapse', () => {
        $('#spaceUsedToggle').setAttribute('aria-expanded', 'false');
      });
      $('#footerUnreadAction').addEventListener('click', () => setUnreadOnlyView(true, true));
      $('#footerMessagesAction').addEventListener('click', () => setUnreadOnlyView(false, true));
      $('#footerFolderAction').addEventListener('click', () => {
        if (isSinglePaneMobileActive()) {
          setMobilePane('folders');
          return;
        }
        setUnreadOnlyView(false, true);
      });
      $('#openSettings').addEventListener('click', openSettings);
      $('#logoutButton').addEventListener('click', async () => {
        if (!await swalConfirm('Logout from this browser?', 'The persistent login cookie will be removed.', 'Logout')) return;
        try {
          await flushActionQueue(true);
          await api('logout');
          location.reload();
        } catch (error) {
          handleError(error);
        }
      });

      suppressBrowserAutofill(document);
      const autofillObserver = new MutationObserver(mutations => {
        for (const mutation of mutations) {
          for (const node of mutation.addedNodes) {
            if (!(node instanceof Element)) continue;
            if (node.matches('input')) suppressBrowserAutofill(node);
            if (node.querySelector?.('input')) suppressBrowserAutofill(node);
          }
        }
      });
      autofillObserver.observe(document.body, {childList: true, subtree: true});
      setActiveRecipientField('to');
      $$('.recipient-picker').forEach(button => button.addEventListener('click', () => openRecipientPicker(button.dataset.field)));
      $('#recipientSuggestionsDone').addEventListener('click', () => {
        const field = state.recipientSuggestionField;
        const input = field ? recipientInputForField(field) : null;
        if (input && input.value.trim() && !commitRecipientInput(input)) return;
        hideRecipientSuggestions(false);
      });
      window.addEventListener('resize', positionRecipientSuggestions);
      $('#composeModal .modal-body')?.addEventListener('scroll', positionRecipientSuggestions);
      $('#recipientSearch').addEventListener('input', event => renderRecipientContacts(event.target.value));
      $('#recipientSearch').addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        const matches = recipientContactMatches(event.target.value);
        if (!matches.length) return;
        event.preventDefault();
        const contact = matches[0];
        const field = state.recipientField;
        if (!state.recipients[field].some(item => item.email.toLowerCase() === contact.email.toLowerCase())) {
          state.recipients[field].push({name: contact.name || '', email: contact.email});
          markComposeDirty();
        }
        renderRecipientChips(field);
        recipientModal.hide();
      });
      $('#applyRecipients').addEventListener('click', () => {
        const contactEmails = new Set(state.contacts.map(contact => contact.email.toLowerCase()));
        const manual = state.recipients[state.recipientField].filter(
          recipient => !contactEmails.has(recipient.email.toLowerCase()) &&
            state.recipientDraftEmails.has(recipient.email.toLowerCase())
        );
        const selectedContacts = state.contacts
          .filter(contact => state.recipientDraftEmails.has(contact.email.toLowerCase()))
          .map(contact => ({name: contact.name || '', email: contact.email}));
        state.recipients[state.recipientField] = [...manual, ...selectedContacts];
        markComposeDirty();
        renderRecipientChips(state.recipientField);
        recipientModal.hide();
      });
      ['cc', 'bcc'].forEach(field => {
        $('#toggle' + field[0].toUpperCase() + field.slice(1)).addEventListener('click', () => {
          setRecipientRowVisibility(field, $('#' + field + 'Row').classList.contains('d-none'));
        });
      });
      const composeEmojis = [
        '😀','😃','😄','😁','😆','😅','😂','🤣',
        '😊','🙂','🙃','😉','😍','🥰','😘','😎',
        '🤓','🤔','🤗','🤩','🥳','😇','😋','😜',
        '😐','😕','🙄','😴','😢','😭','😡','🤯',
        '👍','👎','👏','🙏','👌','✌️','🤝','💪',
        '❤️','💙','💚','💛','💜','🧡','💔','💯',
        '✅','❌','⚠️','⭐','🎉','🎂','🎁','🔥',
        '☕','🍷','🍕','🌞','🌙','🌈','🚀','📌'
      ];
      $('#composeEmojiGrid').innerHTML = composeEmojis.map(emoji =>
        `<button class="pse-emoji-choice" type="button" data-emoji="${emoji}" aria-label="Insert ${emoji}">${emoji}</button>`
      ).join('');
      $('#composeEmojiButton').addEventListener('mousedown', () => saveComposeSelection());
      $$('.pse-emoji-choice', $('#composeEmojiGrid')).forEach(button => {
        button.addEventListener('click', () => insertComposeText(button.dataset.emoji || ''));
      });

      const composeTextColors = [
        {value: '#000000', name: 'Black'},
        {value: '#343a40', name: 'Dark gray'},
        {value: '#6c757d', name: 'Gray'},
        {value: '#ffffff', name: 'White', light: true},
        {value: '#dc3545', name: 'Red'},
        {value: '#fd7e14', name: 'Orange'},
        {value: '#ffc107', name: 'Yellow', light: true},
        {value: '#795548', name: 'Brown'},
        {value: '#198754', name: 'Green'},
        {value: '#20c997', name: 'Teal', light: true},
        {value: '#0dcaf0', name: 'Cyan', light: true},
        {value: '#0d6efd', name: 'Blue'},
        {value: '#084298', name: 'Dark blue'},
        {value: '#6610f2', name: 'Indigo'},
        {value: '#6f42c1', name: 'Purple'},
        {value: '#d63384', name: 'Pink'}
      ];
      $('#composeTextColorPalette').innerHTML = composeTextColors.map(color => `
        <button
          class="pse-color-choice"
          type="button"
          data-color="${color.value}"
          data-light="${color.light ? '1' : '0'}"
          style="background-color:${color.value}"
          title="${color.name} (${color.value.toUpperCase()})"
          aria-label="${color.name}"
          aria-pressed="false"
        ></button>
      `).join('');
      setComposeTextColorPickerValue(rememberedComposeColors().text);
      $('#composeTextColorButton').addEventListener('pointerdown', saveComposeSelection);
      $$('.pse-color-choice', $('#composeTextColorPalette')).forEach(button => {
        button.addEventListener('pointerdown', event => event.preventDefault());
        button.addEventListener('click', () => chooseComposeTextColor(button.dataset.color));
      });
      $('#composeCustomTextColorButton').addEventListener('pointerdown', event => {
        event.preventDefault();
        saveComposeSelection();
      });
      $('#composeCustomTextColorButton').addEventListener('click', () => $('#composeTextColor').click());

      $$('.compose-format').forEach(button => button.addEventListener('click', async event => {
        event.preventDefault();
        const command = button.dataset.command;
        let value = null;
        if (command === 'createLink') {
          value = await swalUrlPrompt();
          if (!value) return;
        }
        restoreComposeSelection();
        document.execCommand(command, false, value);
        markComposeDirty();
        saveComposeSelection();
      }));
      $$('.compose-format').forEach(button => button.addEventListener('mousedown', event => {
        event.preventDefault();
        saveComposeSelection();
      }));
      ['#composeTextColor', '#composeBackgroundColor', '#composeFontSize'].forEach(selector => {
        $(selector).addEventListener('pointerdown', saveComposeSelection);
      });
      $('#composeTextColor').addEventListener('change', event => {
        chooseComposeTextColor(event.target.value);
      });
      $('#composeBackgroundColor').addEventListener('change', event => {
        rememberComposeColor('background_color', event.target.value);
        applyComposeColor('hiliteColor', 'backgroundColor', event.target.value);
      });
      $('#composeFontSize').addEventListener('change', event => {
        applyComposeFontSize(event.target.value);
        event.target.value = '';
      });
      $('#composeSubject').addEventListener('input', markComposeDirty);
      $('#composeBody').addEventListener('input', () => {
        markComposeDirty();
        saveComposeSelection();
      });
      $('#composeBody').addEventListener('mouseup', saveComposeSelection);
      $('#composeBody').addEventListener('keyup', saveComposeSelection);
      $('#insertImageButton').addEventListener('mousedown', event => {
        event.preventDefault();
        saveComposeSelection();
      });
      $('#insertImageButton').addEventListener('click', () => $('#composeImageInput').click());
      $('#composeImageInput').addEventListener('change', async event => {
        const files = Array.from(event.target.files || []);
        try {
          for (const file of files) {
            await insertInlineImage(file);
          }
        } catch (error) {
          handleError(error);
        } finally {
          event.target.value = '';
        }
      });
      $('#composeBody').addEventListener('paste', async event => {
        const files = Array.from(event.clipboardData?.items || [])
          .filter(item => item.kind === 'file' && String(item.type).startsWith('image/'))
          .map(item => item.getAsFile())
          .filter(Boolean);
        if (!files.length) return;
        event.preventDefault();
        saveComposeSelection();
        try {
          for (const file of files) {
            await insertInlineImage(file);
          }
        } catch (error) {
          handleError(error);
        }
      });
      $('#composeBody').addEventListener('dragover', event => {
        if (!Array.from(event.dataTransfer?.types || []).includes('Files')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        $('#composeBody').classList.add('pse-image-drag');
      });
      $('#composeBody').addEventListener('dragleave', event => {
        if (!$('#composeBody').contains(event.relatedTarget)) {
          $('#composeBody').classList.remove('pse-image-drag');
        }
      });
      $('#composeBody').addEventListener('drop', async event => {
        const files = Array.from(event.dataTransfer?.files || [])
          .filter(file => String(file.type).startsWith('image/'));
        if (!files.length) return;
        event.preventDefault();
        $('#composeBody').classList.remove('pse-image-drag');
        if (document.caretRangeFromPoint) {
          state.composeRange = document.caretRangeFromPoint(event.clientX, event.clientY);
        } else if (document.caretPositionFromPoint) {
          const position = document.caretPositionFromPoint(event.clientX, event.clientY);
          if (position) {
            const range = document.createRange();
            range.setStart(position.offsetNode, position.offset);
            range.collapse(true);
            state.composeRange = range;
          }
        }
        try {
          for (const file of files) {
            await insertInlineImage(file);
          }
        } catch (error) {
          handleError(error);
        }
      });
      $('#composeAttachments').addEventListener('change', event => {
        state.composeFiles = [...state.composeFiles, ...Array.from(event.target.files)];
        const total = state.composeFiles.reduce((sum, file) => sum + Number(file.size || file.data?.length * .75 || 0), 0);
        if (total > <?= PSE_MAX_ATTACHMENT_BYTES ?>) {
          toast('Attachments exceed the 15 MB limit.', 'warning');
          state.composeFiles = [];
          event.target.value = '';
        }
        markComposeDirty();
        renderAttachmentList();
      });
      $('#sendEmail').addEventListener('click', sendCompose);
      $('#savePse').addEventListener('click', saveCompose);
      $('#deleteComposeForever').addEventListener('click', deleteComposeForever);
      setComposeMaximized(readComposeMaximizedState(), false);
      $('#composeMaximizeButton').addEventListener('click', toggleComposeMaximized);
      $('#composeModal').addEventListener('hide.bs.modal', confirmComposeCloseWithoutDrafts);
      $('#composeModal').addEventListener('shown.bs.modal', () => suppressBrowserAutofill($('#composeModal')));
      $('#composeModal').addEventListener('hidden.bs.modal', autoSaveComposeDraft);
      $('#composeModal').addEventListener('hidden.bs.modal', () => hideRecipientSuggestions(false));
      $('#skipUnknownContacts').addEventListener('click', () => {
        unknownContactsModal.hide();
        const resolve = state.unknownContactResolver;
        state.unknownContactResolver = null;
        if (resolve) resolve(true);
      });
      $('#cancelUnknownContacts').addEventListener('click', () => {
        unknownContactsModal.hide();
        const resolve = state.unknownContactResolver;
        state.unknownContactResolver = null;
        if (resolve) resolve(false);
      });
      $('#addUnknownContacts').addEventListener('click', async () => {
        const contacts = $$('.unknown-contact-name').map(input => ({
          email: input.dataset.email,
          name: input.value.trim()
        }));
        try {
          await api('save_contacts_batch', {contacts}, {spinnerText: 'Adding contacts…'});
          await loadContacts(true, false);
          unknownContactsModal.hide();
          const resolve = state.unknownContactResolver;
          state.unknownContactResolver = null;
          if (resolve) resolve(true);
          toast(`${contacts.length} contact${contacts.length === 1 ? '' : 's'} added.`);
        } catch (error) {
          handleError(error);
        }
      });
      $('#skipReadContactSuggestions').addEventListener('click', dismissReadContactSuggestions);
      $('#closeReadContactSuggestions').addEventListener('click', dismissReadContactSuggestions);
      $('#addReadContactSuggestions').addEventListener('click', addSuggestedReadContacts);
      $('#readContactSuggestionModal').addEventListener('hidden.bs.modal', () => {
        state.readContactPromptOpen = false;
      });

      $('#contactsSearch').addEventListener('input', event => renderContacts(event.target.value));
      $('#addContact').addEventListener('click', async () => {
        try {
          await api('save_contact', {name: $('#contactName').value, email: $('#contactEmail').value});
          $('#contactName').value = '';
          $('#contactEmail').value = '';
          await loadContacts(true, false);
          renderContacts($('#contactsSearch').value);
          toast('Contact saved.');
        } catch (error) {
          handleError(error);
        }
      });
      $('#contactsCsv').addEventListener('change', event => {
        const file = event.target.files[0];
        $('#csvFileName').textContent = file ? `${file.name} (${formatBytes(file.size)})` : '';
        $('#importContacts').disabled = !file;
      });
      $('#importContacts').addEventListener('click', async () => {
        const file = $('#contactsCsv').files[0];
        if (!file) return;
        const form = new FormData();
        form.append('csv', file);
        try {
          const result = await api('import_contacts', form, {spinnerText: 'Importing contacts…'});
          await loadContacts(true, false);
          renderContacts();
          toast(`Imported ${result.result.imported}, updated ${result.result.updated}, skipped ${result.result.skipped}.`);
          $('#contactsCsv').value = '';
          $('#csvFileName').textContent = '';
          $('#importContacts').disabled = true;
        } catch (error) {
          handleError(error);
        }
      });
      $('#exportContacts').addEventListener('click', async () => {
        try {
          const blob = await api('export_contacts', {}, {
            blob: true,
            spinnerText: 'Exporting contacts…'
          });
          const today = new Date().toISOString().slice(0, 10);
          downloadBlob(blob, `pse_contacts_${today}.csv`);
          toast(`Exported ${state.contacts.length} contact${state.contacts.length === 1 ? '' : 's'}.`);
        } catch (error) {
          handleError(error);
        }
      });

      $('#forceRefreshFolderNames').addEventListener('click', forceRefreshFolderNames);
      $('#chooseAppIcon').addEventListener('click', () => $('#appIconFile').click());
      $('#appIconFile').addEventListener('change', event => uploadApplicationIcon(event.target.files?.[0] || null));
      $('#checkUpdatesNow').addEventListener('click', () => checkApplicationUpdate(true, false));
      $('#footerVersionCheck')?.addEventListener('click', () => checkApplicationUpdate(true, false, true));
      $('#activeAccountButton')?.addEventListener('click', event => {
        const button = event.currentTarget;
        if (!button.classList.contains('switchable')) return;
        const menu = $('#accountQuickMenu');
        const willOpen = menu.hidden;
        closeQuickAccountMenu();
        if (willOpen) {
          menu.hidden = false;
          button.setAttribute('aria-expanded', 'true');
        }
      });
      document.addEventListener('click', event => {
        if (!event.target.closest('#accountQuickSwitcher')) closeQuickAccountMenu();
      });
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeQuickAccountMenu();
      });
      $('#installUpdateNow').addEventListener('click', () => {
        if (lastUpdateInfo?.updateAvailable) installApplicationUpdate(lastUpdateInfo, false);
      });
      $('#saveSettings').addEventListener('click', saveSettings);
      $('#settingsAccountSelect').addEventListener('change', event => {
        switchSettingsAccount(event.target.value);
      });
      $('#addEmailAccount').addEventListener('click', addEmailAccount);
      $('#deleteEmailAccount').addEventListener('click', deleteEmailAccount);
      $('#account_type').addEventListener('change', () => updateAccountTypeUi(initialSettings));
      $('#theme').addEventListener('change', event => applyThemeToInterface(event.target.value, true));
      ['primary_color', 'accent_color', 'background_color', 'panel_color'].forEach(id => {
        $('#' + id).addEventListener('input', () => {
          $('#theme').value = 'custom';
          applyThemeToInterface('custom', false);
        });
      });
      $('#density').addEventListener('change', event => applyDensityToInterface(event.target.value));
      $('#connectGoogle').addEventListener('click', connectGoogle);
      $('#disconnectGoogle').addEventListener('click', disconnectGoogle);
      $('#copyGoogleRedirect').addEventListener('click', async () => {
        const field = $('#google_redirect_uri');
        try {
          await navigator.clipboard.writeText(field.value);
          toast('Google redirect URI copied.', 'info');
        } catch (error) {
          field.select();
          document.execCommand('copy');
          toast('Google redirect URI copied.', 'info');
        }
      });
      $('#settingsModal').addEventListener('hidden.bs.modal', () => {
        if (state.accountReloadPending) {
          state.accountReloadPending = false;
          location.reload();
          return;
        }
        ['primary_color', 'accent_color', 'background_color', 'panel_color'].forEach(key => {
          $('#' + key).value = initialSettings[key];
        });
        $('#theme').value = initialSettings.theme || 'custom';
        $('#density').value = initialSettings.density || 'medium';
        applyThemeToInterface(initialSettings.theme || 'custom', false);
        applyDensityToInterface(initialSettings.density || 'medium');
      });
      $('#testImap').addEventListener('click', async () => {
        try {
          const saved = await api('save_settings', settingsPayload(), {spinnerText: 'Saving settings for test…'});
          applySettingsToForm(saved.settings);
          const result = await api('test_imap', {}, {spinnerText: 'Testing IMAP…'});
          toast(result.message);
        } catch (error) {
          handleError(error);
        }
      });
      $('#testSmtp').addEventListener('click', async () => {
        try {
          const saved = await api('save_settings', settingsPayload(), {spinnerText: 'Saving settings for test…'});
          applySettingsToForm(saved.settings);
          const result = await api('test_smtp', {}, {spinnerText: 'Testing SMTP…'});
          toast(result.message);
        } catch (error) {
          handleError(error);
        }
      });

      applySettingsToForm(serverSettings);
      ['to', 'cc', 'bcc'].forEach(field => renderRecipientChips(field));
      setupResizers();
      loadContacts(false, false).catch(() => {});
      refreshSavedCount(false);
      loadMailboxPreferences();
      updateQueueStat();
      showAppliedUpdateNotice();
      showAccountSwitchedNotice();
      window.setTimeout(() => checkApplicationUpdate(false, true), 700);
      loadFolders().then(() => {
        window.setTimeout(pollFolderStatus, 5000);
      });
      if (new URLSearchParams(location.search).get('google_oauth') === 'success') {
        const cleanUrl = new URL(location.href);
        cleanUrl.searchParams.delete('google_oauth');
        history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
        setTimeout(async () => {
          toast('Google account connected.', 'success');
          await openSettings();
        }, 250);
      }
      const recordActivity = () => {
        state.lastActivity = Date.now();
      };
      ['pointerdown', 'keydown', 'touchstart', 'wheel'].forEach(eventName => {
        document.addEventListener(eventName, recordActivity, {passive: true});
      });
      window.addEventListener('offline', () => {
        pausePrefetch('network', true);
      });
      window.addEventListener('online', () => {
        resumePrefetch('network');
        scheduleVisibleMessagePrefetch(state.prefetchGeneration);
      });
      const networkConnection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
      networkConnection?.addEventListener?.('change', () => {
        if (prefetchConnectionAllowsBackground()) {
          resumePrefetch('network-policy');
          scheduleVisibleMessagePrefetch(state.prefetchGeneration);
        } else {
          pausePrefetch('network-policy', true);
        }
      });
      if (navigator.onLine === false) pausePrefetch('network');
      if (!prefetchConnectionAllowsBackground()) pausePrefetch('network-policy');
      window.addEventListener('pagehide', () => {
        interruptPrefetchRequests();
        fetch('?ajax=handle_queue', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-PSE-CSRF': csrf
          },
          body: '{}',
          keepalive: true
        }).catch(() => {});
      });
      setInterval(() => {
        flushActionQueue(true);
      }, 60000);
      setInterval(pollFolderStatus, 60000);
      setInterval(refreshLastSyncStatus, 300000);
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
          refreshLastSyncStatus();
          pollFolderStatus();
        }
      });
    })();
  </script>
<?php endif; ?>
  <script>
    (() => {
      'use strict';

      const pwa = {
        deferredPrompt: null,
        installed: false,
        runningStandalone: false,
        installKnown: false,
        relatedAppsSupported: 'getInstalledRelatedApps' in navigator,
        relatedAppsChecked: false,
        secure: window.isSecureContext,
        localHint: false,
        registration: null,
        installedEvidence: 'none'
      };
      const pwaHintKey = 'pse_pwa_installed_hint_v1';
      const pwaWorkerUrl = new URL('?pwa=sw', location.href).href;
      const pwaScopeUrl = new URL('./', location.href).href;

      function pwaIsStandalone() {
        const modes = ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'];
        return modes.some(mode => window.matchMedia(`(display-mode: ${mode})`).matches) || navigator.standalone === true;
      }

      function pwaIsIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      }

      function pwaIsSafari() {
        return /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(navigator.userAgent);
      }

      function pwaReadHint() {
        try {
          return localStorage.getItem(pwaHintKey) === '1';
        } catch (error) {
          return false;
        }
      }

      function pwaWriteHint(installed) {
        try {
          if (installed) localStorage.setItem(pwaHintKey, '1');
          else localStorage.removeItem(pwaHintKey);
        } catch (error) {}
      }

      function pwaState() {
        if (pwa.runningStandalone) {
          return {
            code: 'running',
            label: 'Running as installed app',
            detail: 'PSE is currently running in standalone app mode.',
            dot: 'installed'
          };
        }
        if (pwa.installed) {
          if (pwa.installedEvidence === 'hint') {
            return {
              code: 'installed-hint',
              label: 'Installation previously detected',
              detail: 'PSE was previously installed in this browser profile, but this browser cannot directly verify that it is still installed.',
              dot: 'installed'
            };
          }
          return {
            code: 'installed',
            label: 'Installed on this device',
            detail: 'PSE appears to already be installed. Open it from your Home Screen or app launcher.',
            dot: 'installed'
          };
        }
        if (!pwa.secure) {
          return {
            code: 'insecure',
            label: 'HTTPS required for installation',
            detail: 'Open this same PSE URL over HTTPS before installing it as an app.',
            dot: 'warning'
          };
        }
        if (pwa.deferredPrompt) {
          return {
            code: 'ready',
            label: 'Ready to install',
            detail: 'Your browser can install PSE directly from this page.',
            dot: 'ready'
          };
        }
        if (pwaIsIos()) {
          return {
            code: 'manual-ios',
            label: 'Install from the browser Share menu',
            detail: 'On iPhone/iPad, use Share → Add to Home Screen. Browser support differs by iOS version and browser.',
            dot: 'ready'
          };
        }
        return {
          code: 'manual',
          label: pwa.installKnown ? 'Installation not currently offered' : 'Installation status unavailable',
          detail: 'Use your browser menu and choose Install app or Add to Home Screen if available.',
          dot: 'warning'
        };
      }

      function pwaSetButton(button, state) {
        if (!button) return;
        const icon = button.querySelector('i');
        const text = button.querySelector('span');
        button.classList.remove('pse-pwa-ready');
        button.disabled = false;

        if (state.code === 'running') {
          button.classList.add('d-none');
          return;
        }

        button.classList.remove('d-none');
        if (state.code === 'installed' || state.code === 'installed-hint') {
          if (icon) icon.className = 'fa-solid fa-circle-check' + (button.id === 'pwaSettingsInstallButton' ? ' me-1' : '');
          if (text) text.textContent = 'Installed';
          button.disabled = false;
          button.title = 'PSE is already installed';
          return;
        }

        if (icon) icon.className = 'fa-solid fa-download' + (button.id === 'pwaSettingsInstallButton' || button.id === 'pwaAuthInstallButton' ? ' me-1' : '');
        if (text) text.textContent = button.id === 'pwaInstallButton' ? 'Install' : 'Install';
        if (state.code === 'ready') button.classList.add('pse-pwa-ready');
        button.title = state.label;
      }

      function pwaRender() {
        pwa.runningStandalone = pwaIsStandalone();
        if (pwa.runningStandalone) {
          pwa.installed = true;
          pwa.installedEvidence = 'standalone';
          pwa.installKnown = true;
          pwaWriteHint(true);
        }
        const state = pwaState();
        pwaSetButton(document.getElementById('pwaInstallButton'), state);
        pwaSetButton(document.getElementById('pwaAuthInstallButton'), state);
        pwaSetButton(document.getElementById('pwaSettingsInstallButton'), state);

        const status = document.getElementById('pwaSettingsStatus');
        const detail = document.getElementById('pwaSettingsDetail');
        const dot = document.getElementById('pwaSettingsStatusDot');
        if (status) status.textContent = state.label;
        if (detail) detail.textContent = state.detail + ' Private email content is not cached by the PWA service worker.';
        if (dot) {
          dot.className = 'pse-pwa-status-dot' + (state.dot ? ' ' + state.dot : '');
        }
      }

      function pwaDialogHtml(state) {
        let instructions = '';
        if (state.code === 'manual-ios') {
          instructions = `<div class="pse-pwa-instructions"><strong><i class="fa-solid fa-arrow-up-from-bracket me-1"></i>iPhone / iPad</strong><br>Open the browser <strong>Share</strong> menu, choose <strong>Add to Home Screen</strong>, then confirm <strong>Add</strong>.</div>`;
        } else if (state.code === 'manual') {
          instructions = `<div class="pse-pwa-instructions"><strong><i class="fa-solid fa-ellipsis-vertical me-1"></i>Manual installation</strong><br>Open your browser menu and choose <strong>Install app</strong>, <strong>Install PSE</strong>, or <strong>Add to Home Screen</strong>. The exact wording depends on the browser.</div>`;
        } else if (state.code === 'insecure') {
          instructions = `<div class="pse-pwa-instructions"><strong><i class="fa-solid fa-lock me-1"></i>Secure connection required</strong><br>PWA installation requires HTTPS (except localhost development). Reopen this PSE installation using its HTTPS URL.</div>`;
        } else if (state.code === 'installed' || state.code === 'installed-hint') {
          instructions = `<div class="pse-pwa-instructions"><strong><i class="fa-solid fa-house me-1"></i>${state.code === 'installed-hint' ? 'Previously installed' : 'Already installed'}</strong><br>${state.code === 'installed-hint' ? 'Direct verification is unavailable in this browser. If you removed PSE, use the browser Install app command when it becomes available.' : 'Launch PSE from your Home Screen, desktop, Start menu, Dock, or application launcher.'}</div>`;
        } else if (state.code === 'running') {
          instructions = `<div class="pse-pwa-instructions"><strong><i class="fa-solid fa-circle-check me-1"></i>Installed mode active</strong><br>You are already using the standalone PSE app.</div>`;
        }

        return `
          <div class="pse-pwa-dialog">
            <div class="pse-pwa-dialog-hero">
              <img class="pse-pwa-dialog-logo" src="${escapePwaHtml(appIconUrl())}" alt="PSE">
              <div>
                <div class="pse-pwa-dialog-title">Install ${escapePwaHtml(String(initialSettings.app_title || 'PSE Email'))}</div>
                <div class="pse-pwa-dialog-status"><span class="pse-pwa-status-dot ${escapePwaClass(state.dot)}"></span>${escapePwaHtml(state.label)}</div>
              </div>
            </div>
            <div class="pse-pwa-benefits">
              <div class="pse-pwa-benefit"><span class="pse-pwa-benefit-icon"><i class="fa-solid fa-window-maximize"></i></span><div><strong>Dedicated app window</strong><span>Launch PSE without normal browser chrome.</span></div></div>
              <div class="pse-pwa-benefit"><span class="pse-pwa-benefit-icon"><i class="fa-solid fa-mobile-screen-button"></i></span><div><strong>Home Screen & app launcher</strong><span>Open it like your other installed applications.</span></div></div>
              <div class="pse-pwa-benefit"><span class="pse-pwa-benefit-icon"><i class="fa-solid fa-shield-halved"></i></span><div><strong>Private by design</strong><span>The service worker does not cache mailbox or authenticated email responses.</span></div></div>
            </div>
            ${instructions}
          </div>`;
      }

      function escapePwaHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char]);
      }

      function escapePwaClass(value) {
        return /^[a-z-]+$/.test(String(value || '')) ? String(value) : '';
      }

      async function pwaShowInstall() {
        const state = pwaState();
        const canPrompt = state.code === 'ready' && pwa.deferredPrompt;
        const result = await Swal.fire({
          html: pwaDialogHtml(state),
          showCancelButton: true,
          showConfirmButton: canPrompt,
          confirmButtonText: '<i class="fa-solid fa-download me-2"></i>Install PSE',
          cancelButtonText: state.code === 'running' || state.code === 'installed' || state.code === 'installed-hint' ? 'Close' : 'Not now',
          buttonsStyling: true,
          customClass: {popup: 'pse-pwa-swal-popup'},
          width: 520,
          focusConfirm: false
        });

        if (!result.isConfirmed || !canPrompt) return;
        const prompt = pwa.deferredPrompt;
        pwa.deferredPrompt = null;
        pwaRender();
        try {
          await prompt.prompt();
          const choice = await prompt.userChoice;
          if (choice && choice.outcome === 'accepted') {
            pwaWriteHint(true);
          }
        } catch (error) {
          console.warn('PWA installation prompt failed.', error);
        }
        window.setTimeout(pwaDetectInstalled, 500);
      }

      async function pwaDetectInstalled() {
        pwa.runningStandalone = pwaIsStandalone();
        pwa.localHint = pwaReadHint();
        if (pwa.runningStandalone) {
          pwa.installed = true;
          pwa.installedEvidence = 'standalone';
          pwa.installKnown = true;
          pwaWriteHint(true);
          pwaRender();
          return;
        }

        if (pwa.relatedAppsSupported) {
          try {
            const apps = await navigator.getInstalledRelatedApps();
            pwa.relatedAppsChecked = true;
            pwa.installKnown = true;
            const webApps = Array.isArray(apps) ? apps.filter(app => String(app.platform || '').toLowerCase() === 'webapp') : [];
            if (webApps.length > 0) {
              pwa.installed = true;
              pwa.installedEvidence = 'related';
              pwaWriteHint(true);
            } else if (!pwa.deferredPrompt) {
              pwa.installed = pwa.localHint;
              pwa.installedEvidence = pwa.localHint ? 'hint' : 'none';
            }
          } catch (error) {
            pwa.relatedAppsChecked = true;
            pwa.installed = pwa.localHint;
            pwa.installedEvidence = pwa.localHint ? 'hint' : 'none';
          }
        } else {
          pwa.installed = pwa.localHint;
          pwa.installedEvidence = pwa.localHint ? 'hint' : 'none';
        }
        pwaRender();
      }

      async function pwaRegisterServiceWorker() {
        if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
        try {
          pwa.registration = await navigator.serviceWorker.register(pwaWorkerUrl, {scope: pwaScopeUrl});
          pwa.registration.update().catch(() => {});
        } catch (error) {
          console.warn('PSE service worker registration failed.', error);
        }
      }

      window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        pwa.deferredPrompt = event;
        pwa.installKnown = true;
        pwa.installed = false;
        pwa.installedEvidence = 'none';
        pwaWriteHint(false);
        pwaRender();
      });

      window.addEventListener('appinstalled', () => {
        pwa.deferredPrompt = null;
        pwa.installed = true;
        pwa.installedEvidence = 'event';
        pwa.installKnown = true;
        pwaWriteHint(true);
        pwaRender();
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'success',
            title: 'PSE installed',
            text: 'PSE Email is now available from your app launcher or Home Screen.',
            timer: 2600,
            showConfirmButton: false
          });
        }
      });

      ['pwaInstallButton', 'pwaAuthInstallButton', 'pwaSettingsInstallButton'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', pwaShowInstall);
      });

      ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'].forEach(mode => {
        const query = window.matchMedia(`(display-mode: ${mode})`);
        query.addEventListener?.('change', pwaDetectInstalled);
      });

      pwaRegisterServiceWorker();
      pwaDetectInstalled();
      window.setTimeout(pwaRender, 1200);
    })();
  </script>
</body>
</html>
