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
    const classes = new Set(selector === '#folderCleanupActivity' || selector === '#folderCleanupSpinner' ? ['d-none'] : []);
    const attributes = new Map();
    elements.set(selector, {
      value: '',
      textContent: '',
      disabled: false,
      style: {},
      classList: {
        toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); },
        contains(name) { return classes.has(name); }
      },
      setAttribute(name, value) { attributes.set(name, value); },
      removeAttribute(name) { attributes.delete(name); },
      getAttribute(name) { return attributes.get(name); },
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
let previewCount = 125;
let executeHook = null;
let previewHook = null;
let now = Date.UTC(2026, 9, 6, 14, 45);
class CleanupDate extends Date {
  constructor(...args) { super(...(args.length ? args : [now])); }
  static now() { return now; }
}
const timers = new Map();
let timerId = 0;
const context = {
  Intl, Date: CleanupDate, String, Number, Boolean, Map, Set,
  setInterval(fn) { timers.set(++timerId, fn); return timerId; },
  clearInterval(id) { timers.delete(id); },
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
      if (previewHook) return previewHook();
      return {cleanup: {token: 'fixed-token', count: noMatches ? 0 : previewCount, until: '2026-09-29', processed: 0, permanent: previewPermanent}};
    }
    if (name === 'folder_cleanup_execute') {
      executeCalls++;
      if (executeHook) return executeHook();
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
  assert.equal(element('#folderCleanupActivity').classList.contains('d-none'), true);

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
  assert.equal(timers.size, 0, 'Completion must stop the ETA timer.');

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

  // Cancel cannot retract an in-flight batch. Its final response is recorded,
  // subsequent batches stop, and resuming uses the same checked selection.
  previewCount = 325;
  let finishBatch;
  executeHook = () => new Promise(resolve => { finishBatch = resolve; });
  await context.openFolderCleanup({id: 'INBOX', name: 'Inbox'});
  const countBeforeCancel = executeCalls;
  const deleting = context.runFolderCleanup();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(executeCalls, countBeforeCancel + 1);
  assert.equal(element('#folderCleanupCancel').disabled, false);
  assert.equal(element('#folderCleanupSpinner').classList.contains('d-none'), false);
  assert.equal(element('#folderCleanupProgressBar').getAttribute('aria-valuenow'), '0');
  assert.match(element('#folderCleanupEstimate').textContent, /after the first completed batch/);
  now += 10000;
  finishBatch({cleanup: {processed: 100, affected: 99, skipped: 1, complete: false}});
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(executeCalls, countBeforeCancel + 2);
  assert.equal(element('#folderCleanupProgressBar').style.width, '31%');
  assert.equal(element('#folderCleanupProgressBar').getAttribute('aria-valuetext'), '100 of 325 messages processed');
  assert.match(element('#folderCleanupCount').textContent, /99 moved to Trash, 1 skipped/);
  assert.match(element('#folderCleanupEstimate').textContent, /Estimated finish: .*about 23 sec remaining/);
  now += 1000;
  for (const tick of timers.values()) tick();
  assert.match(element('#folderCleanupEstimate').textContent, /about 22 sec remaining/);
  listeners['#folderCleanupCancel:click']();
  assert.equal(context.folderCleanupContext.cancelRequested, true);
  assert.equal(element('#folderCleanupCancel').disabled, true);
  assert.match(element('#folderCleanupProgress').textContent, /current batch finishes/);
  assert.match(element('#folderCleanupEstimate').textContent, /no further batches will start/);
  assert.equal(context.folderCleanupContext.job.processed, 100, 'Cancel must await the current batch response.');
  now += 3000;
  finishBatch({cleanup: {processed: 200, affected: 198, skipped: 2, complete: false}});
  await deleting;
  assert.equal(executeCalls, countBeforeCancel + 2, 'Cancel must prevent every subsequent batch.');
  assert.equal(context.folderCleanupContext.job.token, 'fixed-token');
  assert.equal(context.folderCleanupContext.job.processed, 200);
  assert.equal(context.folderCleanupContext.busy, false);
  assert.equal(element('#folderCleanupProgressBar').style.width, '62%');
  assert.equal(element('#folderCleanupSpinner').classList.contains('d-none'), true);
  assert.match(element('#folderCleanupProgress').textContent, /Cancelled: 200 of 325 messages processed; 198 messages moved to Trash/);
  assert.match(element('#folderCleanupProgress').textContent, /remain changed.*resume the same checked selection/);
  assert.equal(timers.size, 0, 'Cancel must stop the ETA timer.');
  context.folderCleanupContext.job.processed = 324;
  context.updateFolderCleanupProgress(context.folderCleanupContext);
  assert.equal(element('#folderCleanupProgressBar').getAttribute('aria-valuenow'), '99',
    'Rounding must not claim completion while a message remains.');
  context.folderCleanupContext.job.processed = 200;
  const savedPreviewCount = calls.filter(call => call.name === 'folder_cleanup_preview').length;
  executeHook = async () => ({cleanup: {processed: 325, affected: 323, skipped: 2, complete: true}});
  await context.runFolderCleanup();
  assert.equal(calls.filter(call => call.name === 'folder_cleanup_preview').length, savedPreviewCount);
  assert.match(calls.filter(call => call.confirmation).at(-1).confirmation[1], /125 remaining messages/);
  assert.equal(context.folderCleanupContext.job, null);
  assert.equal(timers.size, 0);

  // Cancelling a long preview also leaves every message untouched.
  context.folderCleanupContext = null;
  let finishPreview;
  previewHook = () => new Promise(resolve => { finishPreview = resolve; });
  await context.openFolderCleanup({id: 'INBOX', name: 'Inbox'});
  const previewExecutionCount = executeCalls;
  const checking = context.runFolderCleanup();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(element('#folderCleanupCancel').disabled, false);
  assert.equal(element('#folderCleanupProgressBar').getAttribute('aria-valuenow'), undefined,
    'An unknown preview total must use indeterminate progress.');
  listeners['#folderCleanupCancel:click']();
  finishPreview({cleanup: {token: 'checked-token', count: 125, processed: 0, permanent: false}});
  await checking;
  assert.equal(executeCalls, previewExecutionCount);
  assert.equal(context.folderCleanupContext.job.token, 'checked-token');
  assert.equal(element('#folderCleanupSpinner').classList.contains('d-none'), true);
  assert.match(element('#folderCleanupProgress').textContent, /Cancelled before deleting any messages/);
  listeners['#folderCleanupDate:change']();
  assert.equal(context.folderCleanupContext.job, null);
  assert.equal(element('#folderCleanupActivity').classList.contains('d-none'), true);
  listeners['#folderCleanupCancel:click']();
  assert.equal(calls.at(-1), 'hide', 'An idle Cancel button should close the dialog.');

  console.log('Cleanup UI: snapshots, confirmation, retries, accessible progress, ETA, in-flight cancellation, confirmed resume, preview cancellation and modal guards passed.');
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
