'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../index.php'), 'utf8');
function extract(name) {
  const start = source.indexOf(`      async function ${name}(`);
  assert(start >= 0, `Missing ${name}`);
  const tail = source.slice(start);
  const end = tail.slice(1).search(/\n      (?:async )?function /);
  assert(end >= 0);
  return tail.slice(0, end + 1);
}
function state() {
  return {
    mailboxStarted: true, folder: 'INBOX', folderName: 'Inbox', page: 3,
    search: 'receipt', senderFilter: '', attachmentFilter: 'all', unreadOnly: false,
    sortOrder: 'desc', startDate: '', folders: [{id: 'INBOX', name: 'Inbox', messages: 50, unseen: 2}],
    staleFolders: new Set(), newMailFolders: new Set(), gmailFolderRevisions: new Map(),
    folderSyncedAt: new Map(), messageLoads: 0, queuePersisting: 0, busy: 0,
    lastFolderStatusCheck: 0
  };
}
let checks = 0;
async function pollCase(result, initial = state(), fail = false) {
  const refreshed = [], invalidated = [], status = {};
  const context = {
    state: initial, document: {hidden: false}, console: {warn() {}}, Date, Set, Map, Error,
    folderStatusIds: () => ['INBOX'], api: async () => result,
    invalidateMessageCacheForFolder: id => invalidated.push(id), renderFolders() {},
    refreshFolderPageOne: async (...args) => {
      refreshed.push(args);
      if (fail) throw new Error('offline');
      initial.staleFolders.delete(args[0]);
    }, $: () => status, setConnection() {}
  };
  vm.createContext(context);
  vm.runInContext(extract('pollFolderStatus'), context);
  await context.pollFolderStatus();
  checks++;
  return {refreshed, invalidated, state: initial, status, context};
}
(async () => {
  const gmail = {gmailHistorySynced: true, changedFolders: ['INBOX'], folders: state().folders};
  let r = await pollCase(gmail);
  assert.deepEqual(r.refreshed, [['INBOX', 'Inbox', true, true]], 'Equal counts still refresh Gmail current page');
  assert.deepEqual(r.invalidated, ['INBOX']);
  assert(!r.state.staleFolders.has('INBOX'));
  r = await pollCase({...gmail, changedFolders: []});
  assert.equal(r.refreshed.length, 0, 'Unchanged history performs no message request');
  const initial = state(); initial.gmailFolderRevisions.set('INBOX', 'old');
  r = await pollCase({...gmail, changedFolders: [], gmailFolderRevisions: {INBOX: 'new'}}, initial);
  assert.equal(r.refreshed.length, 1, 'Persistent revision detects history consumed by another request');
  initial.lastFolderStatusCheck = 0;
  r = await pollCase({...gmail, changedFolders: [], gmailFolderRevisions: {INBOX: 'new'}}, initial);
  assert.equal(r.refreshed.length, 0, 'Acknowledged revision does not repeatedly reload');
  r = await pollCase({...gmail, gmailHistorySynced: false});
  assert.equal(r.refreshed.length, 0, 'IMAP count-only behavior is preserved');
  assert.match(r.status.textContent, /press Refresh/);
  r = await pollCase(gmail, state(), true);
  assert(r.state.staleFolders.has('INBOX'));
  assert(!r.state.newMailFolders.has('INBOX'), 'Failed read-state refresh is not flagged as new mail');
  r.state.lastFolderStatusCheck = 0;
  r = await pollCase({...gmail, changedFolders: []}, r.state);
  assert.equal(r.refreshed.length, 1, 'Failed refresh retries on next successful history poll');
  r = await pollCase({...gmail, cache: {refreshError: 'offline'}, gmailFolderRevisions: {INBOX: 'new'}});
  assert.equal(r.refreshed.length, 0);
  assert.equal(r.state.gmailFolderRevisions.size, 0, 'Failed history does not acknowledge client revisions');
  const fileState = state(); fileState.mailboxStarted = false;
  r = await pollCase(gmail, fileState);
  assert.equal(r.refreshed.length, 0, 'File-only launch remains paused');

  const visible = state(), requests = [], applied = [];
  const ctx = {state: visible, api: async (action, data) => {
    requests.push({action, data});
    return {data: {page: data.page, messages: []}, cache: {savedAt: 10}};
  }, cacheMessagePage() {}, beginPrefetchView: () => 1,
    applyMessageData: data => applied.push(data), updateLastSyncStatus() {}, scheduleVisibleMessagePrefetch() {}, Error};
  vm.createContext(ctx); vm.runInContext(extract('refreshFolderPageOne'), ctx);
  await ctx.refreshFolderPageOne('INBOX', 'Inbox', true, true); checks++;
  assert.equal(requests[0].data.page, 3, 'History refresh preserves visible page');
  assert.equal(requests[0].data.search, 'receipt', 'History refresh preserves active filters');
  assert.equal(applied.length, 1);
  ctx.api = async () => ({data: {}, cache: {refreshError: 'rate limited'}});
  await assert.rejects(ctx.refreshFolderPageOne('INBOX', 'Inbox', true, true), /rate limited/); checks++;
  assert.equal(visible.messageLoads, 0, 'Failed refresh releases busy state');
  console.log(`Gmail history UI: ${checks} scenarios passed`);
})().catch(error => {console.error(error); process.exitCode = 1;});
