# PSE Email Client

Single-file PHP email client for IMAP/SMTP and Google OAuth2/Gmail API accounts. The current version appears in the app footer and [changelog](CHANGELOG.md).

Deploy `index.php` on your PHP web server and open it to configure the application. The file serves its own PWA manifest, service worker, and embedded icons. PHP 7.4+ and OpenSSL/JSON are required; regular mail accounts also require PHP IMAP. Gmail needs cURL or HTTPS URL access. Downloading attachment ZIPs requires PHP ZIP.

## Changes in 2.18.1

- Sent-folder rows show all To recipients, with Cc/Bcc fallback when there are no To recipients. Sender information remains available for replies and sender filtering. Sent calendar entries use recipients too; old Sent summary caches refresh automatically.
- Every mailbox folder has a red cleanup bin after its unread pill. Choose one week, one month, two months, all messages, or a custom date. Preset labels show the exact inclusive cutoff. Dates follow the configured account timezone and the message timestamp shown in PSE; month-end subtraction clamps to the last day of the target month.
- Pressing Delete checks all pages in that folder, displays the matching count, and always requires the exact text `YES DELETE ALL`, regardless of the ordinary per-message confirmation setting. Search, unread, and attachment filters do not limit folder cleanup.
- Cleanup moves messages to Trash. Cleaning Trash, or an IMAP account without a detected Trash folder, permanently deletes messages; the confirmation states which operation applies. Cleanup keeps the folder itself.
- Server snapshots bind cleanup to the signed-in browser, mail account, folder, destination, and checked message IDs. Batches can resume after a failed request; new messages arriving after the preview are not added. Messages moved out of the selection are skipped. IMAP UIDVALIDITY changes require a new preview.
- Black-cat application/document icons, Windows `.ico`, portable `.pse` message/draft downloads, and local file opening with Reply, Reply all, Forward, and Edit copy.

See [Windows setup and cat icon instructions](README-Windows-PSE.md). Deployment still requires only `index.php`; `assets/` supplies downloadable image files and artwork provenance.

## Changelog and updates

Every merge into `main` is recorded in [CHANGELOG.md](CHANGELOG.md) by the **Maintain merge changelog** GitHub Actions workflow. It covers merge, squash, and rebase merges, and records direct pushes too. If a merge does not include a higher app version, the workflow increments the patch version so the updater can detect the changes. Explicit higher versions are preserved.

PSE shows release notes before a manual update and after installation, including automatic updates. The installed notice remains pending for each signed-in browser until acknowledged. The PHP download and notes use the same Git commit. Recent notes are also bundled inside `index.php` for manual deployments and offline viewing.

The workflow needs permission to write to `main`; repository rules must permit its bot commit. It scans changes since its saved checkpoint, retries a push if another merge arrives, and can be rerun manually from Actions to recover missed runs. To synchronize the bundled notes while developing, run `python3 scripts/update-changelog.py --sync-only`.

## Cleanup behavior on IMAP

PHP IMAP exposes whole-mailbox EXPUNGE. PSE checks for unrelated messages already marked for deletion before changing or expunging the selected batch, and refuses cleanup if it finds any. Another mail client marking an unrelated message between the final check and EXPUNGE is still a server-side concurrency limitation; avoid simultaneous delete operations from other clients during cleanup.

Date-based cleanup skips IMAP messages with unreadable dates. Delete all includes them. Custom cutoff dates must be from January 1, 1970 through today. Cleanup previews expire after one hour of inactivity and are automatically rechecked when an expired preview is retried.

## Verification

The regression suites use fixture mailboxes and never connect to a live account or delete real email:

```bash
php -l index.php
php tests/sent-recipients.php
php tests/folder-cleanup.php
php tests/update-changelog.php
node tests/folder-cleanup-ui.cjs
node tests/update-changelog-ui.cjs
python tests/icon-assets.py
python tests/changelog-generator.py
npm install --no-save jsdom
node tests/local-pse-files.cjs
```

PHP fixture extraction requires the tokenizer extension. The local-file DOM test uses Node.js 20+ and the test-only `jsdom` package. Real account/provider behavior and Windows double-click registration/icon appearance also need manual deployment checks.
