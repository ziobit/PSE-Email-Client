# PSE Email Client changelog

Release notes are shown when checking for an update and after an update is installed. Dates in automatically recorded merge entries use UTC. The repository keeps the complete history; the single PHP file bundles the latest notes for offline use.

## 2.18.6 (2026-10-10)

- On phones, search now occupies its own full-width header row. The icon, text field, saved-search and clear buttons stay together without wrapping.
- Ultra Compact mode no longer reserves desktop logo space on mobile. The message area adjusts to the header height, keeping the footer navigation on screen.

### Merged changes
- 2026-10-10: [#6](https://github.com/ziobit/PSE-Email-Client/pull/6) — Release 2\.18\.6: fix mobile search layout. Commit [5aa5c86](https://github.com/ziobit/PSE-Email-Client/commit/5aa5c8657df4b3cc9fd68377ba96ea6230f2f967). <!-- pse-pr:ziobit/PSE-Email-Client#6 -->

## 2.18.5 (2026-10-10)

- Portable `.pse` email exports and downloaded compose drafts now let you choose the folder and file name, just like saving attachments, when the browser supports the native Save As dialog.
- Cancelling Save As stops before attachments are prepared. Browsers without the native dialog offer a file-name prompt and keep using normal downloads.
- Updates now run automated checks for mailbox synchronization, caching, deletions, cleanup, file opening and exports before merging changes.

### Merged changes
- 2026-10-10: [#5](https://github.com/ziobit/PSE-Email-Client/pull/5) — Release 2\.18\.5: Save As for PSE files and regression CI. Commit [8b7d197](https://github.com/ziobit/PSE-Email-Client/commit/8b7d19763a012c8a5acd7f779e9c75e1ab59fce5). <!-- pse-pr:ziobit/PSE-Email-Client#5 -->

## 2.18.4 (2026-10-07)

- Queuing a deletion immediately removes its rows from the visible folder, including after Gmail history has invalidated the page cache. Read/unread, restore and permanent-delete actions also update visible rows without relying on a cached page.
- Refreshed lists hide pending queued deletions until they are processed or undone. Optimistic cache updates preserve Gmail revision and view-filter metadata.
- Background list responses started before a mailbox action cannot replace the updated list. Multi-batch deletion keeps using its original folder when the user navigates elsewhere.

### Merged changes
- 2026-10-07: [#4](https://github.com/ziobit/PSE-Email-Client/pull/4) — Release 2\.18\.4: remove queued deletions from message lists immediately. Commit [327ab05](https://github.com/ziobit/PSE-Email-Client/commit/327ab059acf6f4f3d74f86278a4cafd1d2c668ec). <!-- pse-pr:ziobit/PSE-Email-Client#4 -->

## 2.18.3 (2026-10-06)

- Gmail accounts now save a per-account `historyId` checkpoint and request mailbox changes since the last successful sync. Unchanged message details, bodies and calendar entries are reused from cache.
- New mail, read/unread changes, moves, deletions and draft changes update the relevant folders. Background refresh preserves the visible Gmail page and filters, including changes that leave message counts unchanged.
- Expired Gmail history checkpoints rebuild the requested cached views safely. Interrupted, rate-limited or failed requests preserve the checkpoint and available cached messages for retry.
- Synchronization coordinates concurrent requests, keeps accounts isolated, and rejects stale cache writes. Local mailbox actions invalidate or update the corresponding cached messages.

### Merged changes
- 2026-10-06: [#3](https://github.com/ziobit/PSE-Email-Client/pull/3) — Release 2\.18\.3: incremental Gmail sync using historyId. Commit [67f3814](https://github.com/ziobit/PSE-Email-Client/commit/67f3814f059b21a71f0bb28d604d3ef79763775b). <!-- pse-pr:ziobit/PSE-Email-Client#3 -->

## 2.18.2 (2026-10-06)

- Opening a `.pse` file requests the existing PWA window without reloading it on browsers supporting the Launch Handler API. Multiple files opened together share one window.
- A new file-launch window goes directly to its local files. Startup folder/message syncing, queued mailbox work, polling, and message prefetch stay paused until Open mailbox or Refresh is chosen. Password sign-in preserves this behavior.
- Folder cleanup now shows a progress bar, spinner, processed counts, estimated finish time, and Cancel. Estimates adjust after completed batches. Cancellation stops future batches after the current request finishes and preserves the remaining selection for a confirmed resume.

### Merged changes
- 2026-10-06: [#2](https://github.com/ziobit/PSE-Email-Client/pull/2) — Release 2\.18\.2: fast PSE file launches and cancellable cleanup progress. Commit [788bd22](https://github.com/ziobit/PSE-Email-Client/commit/788bd224ffc871018c9040c4db0bd738dd8c55c5). <!-- pse-pr:ziobit/PSE-Email-Client#2 -->

## 2.18.1 (2026-10-06)

- Added this changelog and automatic maintenance after every merge into `main`, including GitHub merge, squash, and rebase merges.
- Each merge publishes a new app version automatically if its changes do not already include a higher version number.
- The update dialog presents release notes before installation and again after a successful update. Notes are pinned to the same source revision as the downloaded PHP file.
- The PHP file includes bundled release notes so deployment continues to require only `index.php`.

### Sent, folder cleanup and Windows files

- Sent-folder rows and calendar entries show recipient names and addresses, with Cc/Bcc fallback when To is empty. Sender details remain available for replies and sender filtering.
- Added a red cleanup bin after each folder's unread count. Choose one week, one month, two months, all messages, or a custom date; cutoff dates are inclusive and displayed in the configured account timezone.
- Folder cleanup previews all matching messages and always requires typing `YES DELETE ALL`. Server snapshots bind the operation to the account, folder, selected messages, and destination; batches can resume after a failed request.
- Cleanup normally moves messages to Trash. Cleaning Trash, or an IMAP account without a detected Trash folder, deletes permanently; the confirmation explains which operation applies.
- Added black-cat application and document icons, a Windows `.ico`, portable `.pse` email and draft files, and local-file opening with Reply, Reply all, Forward, and Edit copy.
- Added Windows app installation, `.pse` association, and icon setup instructions in `README-Windows-PSE.md`.

### Merged changes
- 2026-10-06: [#1](https://github.com/ziobit/PSE-Email-Client/pull/1) — Release 2\.18\.1: Sent recipients, folder cleanup, PSE files and update changelog. Commit [cb02eff](https://github.com/ziobit/PSE-Email-Client/commit/cb02eff140abe81ab3d5d8dc0a3d40f1893f8ee5). <!-- pse-pr:ziobit/PSE-Email-Client#1 -->
