'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');
function extract(name) {
  const start = source.search(new RegExp(`      (?:async )?function ${name}\\(`));
  assert(start >= 0, `Missing ${name}`);
  const tail = source.slice(start);
  const next = tail.slice(1).search(/\n      (?:async )?function /);
  assert(next >= 0, `Missing function boundary after ${name}`);
  return tail.slice(0, next + 1);
}

function fixture(cacheMode = 'missing', accountType = 'gmail') {
  const first = {uid: 'mail-a', seen: false};
  const second = {uid: 'mail-b', seen: true};
  const messages = [first, second];
  const folder = {id: 'INBOX', name: 'Inbox', messages: 2, unseen: 1};
  const trash = {id: 'TRASH', name: 'Trash', special: 'trash', messages: 0, unseen: 0};
  const state = {
    folder: 'INBOX', folderName: 'Inbox', page: 1, pages: 1, messages, folders: [folder, trash],
    messageCache: new Map(), messageDetailsCache: new Map(), calendarCache: new Map(),
    selectedUids: new Set(['mail-a']), allPagesSelected: false,
    staleFolders: new Set(), newMailFolders: new Set(), search: '',
    selectedUid: 'mail-a', currentMessage: first,
    pendingQueue: 0, queuePersisting: 0, folderCache: [folder, trash],
    messageRequestSerial: 0, messageLoads: 0, folderSyncedAt: new Map(),
    senderFilter: '', attachmentFilter: 'all', unreadOnly: false, sortOrder: 'desc', startDate: ''
  };
  if (cacheMode !== 'missing') {
    state.messageCache.set(cacheMode === 'current' ? 'current' : 'old-view', {
      _folder: 'INBOX', messages, total: 2, folderTotal: 2, folderUnseen: 1,
      page: 1, pages: 1, perPage: 50
    });
  }
  let rendered = [];
  const calls = [], errors = [], notifications = [];
  const context = {
    state, initialSettings: {account_type: accountType, account_id: 'fixture-one'},
    Set, Map, Promise, Error, String, Number, Math,
    currentFolderRecord: () => state.folders.find(item => item.id === state.folder),
    messageCacheKey: () => 'current',
    beginPrefetchView: () => 1,
    applyMessageData(data) { state.messages = data.messages; context.renderMessages(); },
    scheduleVisibleMessagePrefetch() {},
    updateLastSyncStatus() {},
    cacheMessagePage(data, requestContext) {
      calls.push({action: 'cacheMessagePage', requestContext});
      state.messageCache.set('current', data);
    },
    saveLastSearch() {},
    renderFolders() {},
    renderMessages() { rendered = state.messages.map(message => String(message.uid)); },
    updateBulkUI() {},
    clearPreview() {},
    isSinglePaneMobileActive: () => false,
    setMobilePane() {},
    updateQueueStat() {},
    toast: (...args) => notifications.push(args),
    handleError: error => errors.push(error.message),
    api: async (action, data) => {
      calls.push({action, data});
      return {pending: data.uids.length};
    },
    async loadFolders(...args) {
      calls.push({action: 'loadFolders', args});
      state.messages = [{uid: 'mail-a', seen: false}, {uid: 'mail-b', seen: true}];
      state.folders = [
        {id: 'INBOX', name: 'Inbox', messages: 2, unseen: 1},
        {id: 'TRASH', name: 'Trash', special: 'trash', messages: 0, unseen: 0}
      ];
      context.renderMessages();
    }
  };
  vm.createContext(context);
  vm.runInContext([
    'chunkUids', 'invalidateCalendarCacheForFolder', 'invalidateMessageCacheForFolder',
    'applyKnownBulkOperation', 'queueDeleteMessages', 'refreshFolderPageOne'
  ].map(extract).join('\n'), context);
  return {context, state, calls, errors, notifications, rendered: () => rendered};
}

let checks = 0;
(async () => {
  for (const mode of ['missing', 'old-view', 'current']) {
    const f = fixture(mode);
    let finishRequest;
    f.context.api = async (action, data) => {
      f.calls.push({action, data});
      return new Promise(resolve => { finishRequest = () => resolve({pending: 1}); });
    };
    const request = f.context.queueDeleteMessages(['mail-a', 'mail-a']);
    assert.deepEqual(f.rendered(), ['mail-b'], `Queued delete immediately removes the visible row with ${mode} page cache`);
    assert.equal(f.state.currentMessage, null, 'Deleted preview closes immediately');
    assert.equal(f.state.pendingQueue, 1, 'Duplicate UIDs queue only once');
    assert.equal(f.state.folders[0].messages, 1);
    assert.equal(f.state.folders[0].unseen, 0);
    assert.equal(f.state.folders[1].messages, 1);
    assert.equal(f.state.folders[1].unseen, 1);
    assert.equal(f.state.queuePersisting, 1);
    assert.equal(f.state.messageRequestSerial, 1, 'A local mailbox action invalidates older list requests');
    assert.deepEqual(Array.from(f.state.selectedUids), []);
    finishRequest();
    await request;
    assert.deepEqual(f.rendered(), ['mail-b'], 'Persisting the queue keeps the row removed');
    assert.equal(f.state.queuePersisting, 0);
    assert.equal(f.calls[0].action, 'queue_delete');
    assert.deepEqual(Array.from(f.calls[0].data.uids), ['mail-a']);
    checks++;
  }

  for (const operation of ['read', 'unread']) {
    const f = fixture('missing');
    const uid = operation === 'read' ? 'mail-a' : 'mail-b';
    f.context.applyKnownBulkOperation([uid], operation);
    assert.equal(f.state.messages.find(message => message.uid === uid).seen, operation === 'read',
      `Visible ${operation} state updates when its page cache has been invalidated`);
    assert.equal(f.state.folders[0].unseen, operation === 'read' ? 0 : 2);
    checks++;
  }

  for (const operation of ['restore', 'delete_forever']) {
    const f = fixture('missing');
    f.context.applyKnownBulkOperation(['mail-a'], operation);
    assert.deepEqual(f.rendered(), ['mail-b'], `${operation} removes the visible row without a page cache`);
    assert.equal(f.state.folders[0].messages, 1);
    checks++;
  }

  for (const accountType of ['gmail', 'imap']) {
    for (const operation of ['delete', 'restore', 'delete_forever']) {
      const f = fixture('current', accountType);
      const otherFolder = {
        _folder: 'Label_42', messages: [{uid: 'mail-a', seen: false}, {uid: 'mail-c', seen: true}],
        total: 2, folderTotal: 2, folderUnseen: 1, pages: 1, page: 1, perPage: 50
      };
      const unrelated = {
        _folder: 'Unrelated', messages: [{uid: 'mail-z', seen: true}],
        total: 1, folderTotal: 1, folderUnseen: 0, pages: 1, page: 1, perPage: 50
      };
      f.state.messageCache.set('other-folder', otherFolder);
      f.state.messageCache.set('unrelated-folder', unrelated);
      f.context.applyKnownBulkOperation(['mail-a'], operation);
      assert.equal(f.state.messageCache.has('other-folder'), accountType === 'imap',
        `${accountType} ${operation} respects global Gmail IDs and folder-specific IMAP UIDs`);
      assert(f.state.messageCache.has('unrelated-folder'), 'Unrelated folder pages remain cached');
      assert.deepEqual(otherFolder.messages.map(message => message.uid), ['mail-a', 'mail-c'],
        'Other folders are invalidated as needed without changing their cached objects in place');
      checks++;
    }
  }

  const race = fixture('missing');
  let finishRefresh;
  race.context.api = async (action, data) => {
    if (action === 'messages') {
      return new Promise(resolve => { finishRefresh = () => resolve({
        data: {messages: [{uid: 'mail-a', seen: false}, {uid: 'mail-b', seen: true}]},
        cache: {savedAt: 10}
      }); });
    }
    return {pending: data.uids.length};
  };
  const staleRefresh = race.context.refreshFolderPageOne('INBOX', 'Inbox', true, true);
  await race.context.queueDeleteMessages(['mail-a']);
  finishRefresh();
  await staleRefresh;
  assert.deepEqual(race.rendered(), ['mail-b'], 'An in-flight Gmail refresh cannot restore the optimistically deleted row');
  assert.equal(race.calls.filter(call => call.action === 'cacheMessagePage').length, 0,
    'An older response cannot repopulate the invalidated page cache');
  assert.equal(race.state.messageLoads, 0, 'Discarding the old response releases refresh busy state');
  checks++;

  const navigation = fixture('missing');
  const uids = ['mail-a', ...Array.from({length: 500}, (_, index) => `bulk-${index}`)];
  navigation.context.api = async (action, data) => {
    navigation.calls.push({action, data});
    navigation.state.folder = 'Archive';
    return {pending: navigation.calls.length === 1 ? 500 : 501};
  };
  await navigation.context.queueDeleteMessages(uids);
  assert.equal(navigation.calls.length, 2, 'Large selections are persisted in bounded chunks');
  assert.equal(navigation.calls[0].data.uids.length, 500);
  assert.equal(navigation.calls[1].data.uids.length, 1);
  assert(navigation.calls.every(call => call.data.folder === 'INBOX'),
    'Navigating between queue chunks preserves the original source folder');
  assert.equal(navigation.state.pendingQueue, 501);
  assert.equal(navigation.state.queuePersisting, 0);
  checks++;

  const f = fixture('missing');
  f.context.api = async () => { throw new Error('Queue could not be saved'); };
  await f.context.queueDeleteMessages(['mail-a']);
  assert.deepEqual(f.rendered(), ['mail-a', 'mail-b'], 'Failed queue persistence restores authoritative messages');
  assert.equal(f.state.pendingQueue, 0);
  assert.equal(f.state.queuePersisting, 0);
  assert.equal(f.state.folders[0].messages, 2);
  assert.deepEqual(f.errors, ['Queue could not be saved']);
  assert.equal(f.calls[0].action, 'loadFolders');
  assert.deepEqual(Array.from(f.calls[0].args.slice(0, 3)), [true, true, true]);
  checks++;
  console.log(`Queued delete UI: ${checks} scenarios passed`);
})().catch(error => { console.error(error); process.exitCode = 1; });
