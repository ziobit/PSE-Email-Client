# PSE Email Client

Single-file PHP email client for IMAP/SMTP and Google OAuth2/Gmail API accounts. The current version appears in the app footer and [changelog](CHANGELOG.md).

Deploy `index.php` on your PHP web server and open it to configure the application. The file serves its own PWA manifest, service worker, and embedded icons. PHP 7.4+ and OpenSSL/JSON are required; regular mail accounts also require PHP IMAP. Gmail needs cURL or HTTPS URL access. Downloading attachment ZIPs requires PHP ZIP.

## Changes in 2.18.10

- On mobile screens (up to 900px), tap the application icon at the top left to open the native account combo when two or more email accounts are configured. The current account is selected; choosing another switches accounts and reloads PSE. With one account, the icon stays decorative. Desktop keeps the existing account badge menu.
- The picker is disabled while switching. A failed switch shows the existing error notice and restores the current selection so you can try again.

## Changes in 2.18.9

- Toggle buttons return to their unselected appearance when tapped again, even when a mobile browser retains its hover effect. Full screen, multiple selection, mailbox filters, calendar, layout and compose maximize controls reflect their actual state.
- The footer unread button toggles the same filter as the toolbar. Selecting all messages on the current page or across all pages now toggles off on a second tap. Filter changes clear the selection controls immediately; delayed ID responses cannot restore a selection after it has been cleared or the view changed.

## Changes in 2.18.8

- On phones, search now shares the top header row with the avatar and action buttons. The field can shrink without wrapping, even if the placeholder is clipped. While editing, the search icon gives way to more text space; saved-search and clear controls remain available.
- **Portable email (.PSE)** is now the first option in the message **Export** menu, followed by **Original email (.eml)**.

## Changes in 2.18.7

- **Settings → Appearance → Full screen** offers a **Prefer full screen on this device** switch and an enter/exit button. The preference is saved immediately in the current browser, independently of email accounts and the Settings Save button.
- The first authenticated mobile visit in a browser that supports full screen asks **Use full screen on this device?** Both accepting and declining are remembered. The prompt waits for local-file opening, update notices and other dialogs.
- Browsers require a user action to enter full screen. When the preference is enabled, reopening PSE shows a header expand button; tap it to resume. The same button exits full screen. Exiting with browser controls keeps the preference; turn the switch off to disable it. Clearing site storage resets the first-visit choice, and blocked storage remembers it for the current visit only.
- Installed-app mode and full screen are separate. Unsupported browsers show guidance in Settings; installed apps already launched in full screen do not receive the first-visit prompt.

## Changes in 2.18.6

- Search has a full-width row below the header buttons on phones, in every UI spacing mode. Its icon and saved-search/clear buttons stay on the same line. The message area adjusts to the taller header so mobile footer navigation remains visible.
- The hidden mobile title no longer reserves desktop logo space in Ultra Compact mode.

## Changes in 2.18.5

- **Export → Portable email (.PSE)** and the composer's **Download .PSE** now use the same Save As mechanism as attachments. On supported browsers in a secure context, choose the folder and file name before the export is prepared. Cancel stops without downloading attachments or building the draft. The suggested name follows the email subject or the current compose subject; the `.pse` extension is retained.
- When the browser cannot show the native picker, PSE asks for a file name and uses normal downloads; the browser's download settings control the destination folder.
- Every `.pse` export is one self-contained file with the email and all attachment contents embedded inside. Copying or sending that file keeps the attachments together; no companion folder or separate attachment files are needed.
- Automated regression checks now run on pull requests and updates to `main`; see **Verification** below.

## Changes in 2.18.2

- Installed PWA file launches focus an existing app window where the browser supports it. A new file-launch window opens the local email directly, with mailbox synchronization paused until **Open mailbox** or **Refresh** is chosen. See [Windows setup](README-Windows-PSE.md) for refreshing an older installed manifest.
- Folder cleanup displays a spinner, progress bar, processed counts, estimated finishing time and Cancel. Cancel waits for the current batch, then stops further batches; already processed messages stay changed. Press Delete and confirm again to resume the remaining checked selection. The estimate starts after the first completed batch and adapts to mail-server speed.

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

The **PSE regression tests** workflow in [.github/workflows/regression.yml](.github/workflows/regression.yml) runs on every pull request, push to `main`, merge-queue check and manual dispatch. It runs PHP syntax checks and every top-level PHP regression suite on **PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5**, preserving the advertised PHP 7.4+ compatibility. The separate client job uses **Node.js 24** and PHP 8.5, plus the runner's Python 3, for JavaScript, PWA/icon and changelog-generator checks.

Coverage includes Gmail `historyId` synchronization and mutations, message/list caches, queued deletions, Sent recipients, cancellable folder cleanup, update/changelog behavior, portable local files, mobile account selection, PWA manifests and file launches. JavaScript checks also compile the real setup, login and authenticated page scripts and PWA service worker in temporary application copies. These checks do not fetch CDN assets or execute the rendered page scripts.

Dependencies are downloaded before tests run. Each test command then executes inside a fresh Linux network namespace with only loopback, and the runner verifies that isolation before starting. Tests cannot reach Google, IMAP, SMTP or any other external service. They use simulated mailboxes, disposable storage and no credentials or Actions secrets. PHP runs with `-n` (no deployment `php.ini`), only JSON/tokenizer added when needed, no native IMAP/cURL, URL wrappers disabled and mail/socket connection functions disabled. Each fixture process receives a minimal environment and its own temporary directory.

The only npm dependency is test-only `jsdom`, pinned with `tests/package-lock.json`; CI uses `npm ci --ignore-scripts` and caches downloads. Deployment still requires only `index.php`. PHP versions run in parallel, without coverage tooling; newer runs cancel obsolete runs, jobs time out after ten minutes, and individual checks time out after ninety seconds. The runner discovers all top-level `tests/*.php`, `tests/*.cjs` and `tests/*.py` files, so new suites join CI automatically. Put helper fixtures in `tests/fixtures/`.

Failures appear as PR annotations, named log groups and per-job summary tables. The **Regression gate** job always evaluates the PHP matrix and client job and fails if either failed, was cancelled or was skipped. In the `main` branch protection/ruleset, enable **Require status checks to pass** and select the exact check name **`Regression gate`** (workflow: **PSE regression tests**). Its name stays stable when PHP versions are added. No path filters or optional failure allowances bypass the gate. This workflow does not change repository protection settings or the merge-changelog workflow.

To run the same checks locally, install PHP with JSON/tokenizer, Node.js 24.15+ and Python 3. On Linux with `sudo` and `unshare`, use:

```bash
npm ci --prefix tests --ignore-scripts --no-audit --no-fund
sudo unshare --net -- env "PATH=$PATH" python3 scripts/run-regressions.py all --require-offline
```

For environments without Linux network namespaces, `python3 scripts/run-regressions.py all` still runs the fixture suites with isolated PHP configuration and temporary storage, but does not enforce OS-level network isolation. Use `php`, `client` or `python` instead of `all` to run one group. Real account/provider behavior and Windows double-click registration/icon appearance still need manual deployment checks.
