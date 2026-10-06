# PSE Email Client changelog

Release notes are shown when checking for an update and after an update is installed. Dates in automatically recorded merge entries use UTC. The repository keeps the complete history; the single PHP file bundles the latest notes for offline use.

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

