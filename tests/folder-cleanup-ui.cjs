const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const start = source.indexOf('      function formatCleanupDate(');
const end = source.indexOf('      function mailboxCheckIntervalMs()', start);
assert(start >= 0 && end > start, 'Cleanup UI functions must be present.');

const listeners = {};
const elements = new Map();
const element = selector => {
  if (!elements.has(selector)) {
    elements.set(selector, {
      value: '',
      textContent: '',
      disabled: false,
      addEventListener(event, fn) { listeners[`${selector}:${event}`] = fn; },
      checkValidity() { return true; },
      reportValidity() {}
    });
  }
  return elements.get(selector);
};
const calls = [];
let executeCalls = 0;
let failSecond = false;
let cancelled = false;
let noMatches = false;
let expireNext = false;
let previewPermanent = true;
const context = {
  Intl, Date, String, Number, Boolean, Map, Set,
  $: element,
  $$: () => [],
  initialSettings: {account_id: 'one'},
  folderCleanupContext: null,
  folderCleanupClosing: false,
  folderCleanupModal: {
    show() { calls.push('show'); },
    hide() { calls.push('hide'); }
  },
  state: {folder: 'INBOX', selectedUid: '1', currentMessage: {}, selectedUids: new Set(['1']), allPagesSelected: true},
  api: async (name, args) => {
    calls.push({name, args});
    if (name === 'folder_cleanup_options') {
      return {cleanup: {
        presets: {week: '2026-09-29', month: '2026-09-06', two_months: '2026-08-06'},
        today: '2026-10-06', timeZone: 'Asia/Bangkok', permanent: false
      }};
    }
    if (name === 'folder_cleanup_preview') {
      return {cleanup: {token: 'fixed-token', count: noMatches ? 0 : 125, until: '2026-09-29', processed: 0, permanent: previewPermanent}};
    }
    if (name === 'folder_cleanup_execute') {
      executeCalls++;
      if (expireNext) {
        expireNext = false;
        throw new Error('The cleanup preview expired. Preview the cleanup again.');
      }
      if (failSecond && executeCalls === 2) throw new Error('network interrupted');
      return {cleanup: {
        token: 'fixed-token',
        processed: executeCalls === 1 ? 100 : 125,
        affected: executeCalls === 1 ? 100 : 125,
        complete: executeCalls !== 1
      }};
    }
    throw new Error(`Unexpected API action: ${name}`);
  },
  swalTypedConfirmation: async (...args) => {
    calls.push({confirmation: args});
    return cancelled ? null : args[3];
  },
  pausePrefetch: () => calls.push('pause'),
  resumePrefetch: () => calls.push('resume'),
  toast: message => calls.push({toast: message}),
  invalidateMessageCacheForFolder: folder => calls.push({invalidate: folder}),
  clearPreview: () => calls.push('clear'),
  loadFolders: async () => calls.push('refresh')
};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);

async function run() {
  await context.openFolderCleanup({id: 'INBOX', name: 'Inbox'});
  assert.equal(element('#folderCleanupRange').value, 'week');
  assert.equal(element('#folderCleanupDate').disabled, true);
  assert.equal(element('#folderCleanupDate').max, '2026-10-06');
  assert.match(element('#folderCleanupRange option[value="week"]').textContent, /1 week \(until .* included\)/);
  assert.equal(element('#folderCleanupDelete').disabled, false);

  // A later batch fails: retry must retain the checked snapshot and token.
  failSecond = true;
  await context.runFolderCleanup();
  assert.equal(calls.filter(call => call.name === 'folder_cleanup_preview').length, 1);
  assert.equal(executeCalls, 2);
  assert.equal(context.folderCleanupContext.job.token, 'fixed-token');
  assert.match(element('#folderCleanupProgress').textContent, /Press Delete to retry the same checked selection/);
  assert.equal(calls.find(call => call.confirmation).confirmation[3], 'YES DELETE ALL');
  assert.match(calls.find(call => call.confirmation).confirmation[1], /will be permanently deleted/,
    'Confirmation must use the preview action even if the original options promised Trash.');
  assert(calls.some(call => call.name === 'folder_cleanup_execute' && call.args.confirmation === 'YES DELETE ALL'));

  failSecond = false;
  await context.runFolderCleanup();
  assert.equal(executeCalls, 3);
  assert.equal(calls.filter(call => call.name === 'folder_cleanup_preview').length, 1);
  assert.equal(context.folderCleanupContext.job, null);
  assert(calls.includes('hide'));
  assert.equal(context.state.selectedUids.size, 0);
  assert.equal(context.state.allPagesSelected, false);
  assert.equal(element('#folderCleanupDelete').disabled, false);

  context.folderCleanupContext = null;
  previewPermanent = false;
  await context.openFolderCleanup({id: 'Trash', name: 'Trash'});
  cancelled = true;
  await context.runFolderCleanup();
  assert.equal(executeCalls, 3, 'Cancelling the typed confirmation must not execute deletion.');
  assert.equal(context.folderCleanupContext.job.token, 'fixed-token');
  listeners['#folderCleanupRange:change']();
  assert.equal(context.folderCleanupContext.job, null, 'Changing the cutoff must discard its snapshot.');

  cancelled = false;
  noMatches = true;
  await context.runFolderCleanup();
  assert.equal(executeCalls, 3);
  assert.equal(context.folderCleanupContext.job, null);
  assert.equal(element('#folderCleanupProgress').textContent, 'No messages match this selection.');

  noMatches = false;
  expireNext = true;
  await context.runFolderCleanup();
  assert.equal(context.folderCleanupContext.job, null, 'An expired preview must be discarded.');
  assert.match(element('#folderCleanupProgress').textContent, /check the remaining messages again/);
  const previousPreviews = calls.filter(call => call.name === 'folder_cleanup_preview').length;
  await context.runFolderCleanup();
  assert.equal(calls.filter(call => call.name === 'folder_cleanup_preview').length, previousPreviews + 1,
    'Retry after expiry must obtain a fresh preview.');

  context.folderCleanupContext.busy = true;
  let blocked = false;
  listeners['#folderCleanupModal:hide.bs.modal']({preventDefault() { blocked = true; }});
  assert.equal(blocked, true, 'A running cleanup must prevent closing the modal.');
  context.folderCleanupContext.busy = false;
  listeners['#folderCleanupModal:hide.bs.modal']({preventDefault() { throw new Error('Idle close must be allowed.'); }});
  assert.equal(context.folderCleanupClosing, true);
  const closingContext = context.folderCleanupContext;
  await context.openFolderCleanup({id: 'Other', name: 'Other'});
  assert.equal(context.folderCleanupContext, closingContext, 'Opening another modal while closing must be ignored.');
  listeners['#folderCleanupModal:hidden.bs.modal']();
  assert.equal(context.folderCleanupContext, null);
  assert.equal(context.folderCleanupClosing, false);
  console.log('Cleanup UI: presets, exact phrase, preview action, snapshot retry, expiry recovery, cancellation, selection reset, zero matches and modal guards passed.');
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
