'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');
const htmlStart = source.indexOf('  <div class="modal fade" id="accountCleanupModal"');
const htmlEnd = source.indexOf('  <div class="modal fade" id="composeModal"', htmlStart);
const dom = new JSDOM(`<body><span id="spaceUsedBadge"></span><div id="spaceUsedContent"></div>${source.slice(htmlStart, htmlEnd)}</body>`);
const $ = selector => dom.window.document.querySelector(selector);
const $$ = selector => [...dom.window.document.querySelectorAll(selector)];
const calls = [], pauses = new Set();
const rows = [
  {index: 0, subject: '<script>bad()</script>', from: '<b>sender</b>', date: '01-01-2020', size: 6000000, folder: 'Inbox', permanent: false},
  {index: 1, subject: 'Old sent', from: 'sender@example.com', date: '01-01-2019', size: 7000000, folder: 'Sent', permanent: false},
  {index: 2, subject: 'Old trash', from: 'sender@example.com', date: '01-01-2018', size: 8000000, folder: 'Trash', permanent: true}
];
let scanCount = 0, executeCount = 0, confirmation = true, hook = null, fail = false;
function job(extra = {}) {
  return {token: 'fixed-token', accountId: 'account-two', filters: {timeZone: 'Asia/Bangkok'}, scanComplete: true, scanned: 3, count: 3, bytes: 21000000, permanentCount: 1, page: 1, pages: 2, rows: rows.slice(0, 2), selectionLocked: false, selectedCount: 0, processed: 0, affected: 0, skipped: 0, complete: false, ...extra};
}
const context = {
  $, $$, Set, Map, Date, Math, Number, String, Boolean, Array,
  setInterval, clearInterval,
  initialSettings: {account_id: 'account-one', account_type: 'gmail'},
  accountCleanupContext: null,
  accountCleanupModal: {show() { calls.push('show'); }, hide() { calls.push('hide'); }},
  state: {folders: [], selectedUids: new Set(), messageRequestSerial: 0},
  pausePrefetch(reason) { pauses.add(reason); },
  resumePrefetch(reason) { pauses.delete(reason); },
  formatStorageBytes: size => `${size} bytes`,
  escapeHtml: value => String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
  swalTypedConfirmation: async (...args) => { calls.push({confirmation: args}); return confirmation ? args[3] : null; },
  loadSidebarSpaceUsage: async () => calls.push('usage'),
  invalidateMessageCacheForFolder() {}, clearPreview() {}, loadFolders: async () => calls.push('folders'),
  api: async (action, data) => {
    calls.push({action, data});
    if (action === 'account_cleanup_start') { scanCount = 0; executeCount = 0; return {cleanup: job({scanComplete: false, count: 0, rows: []})}; }
    if (action === 'account_cleanup_scan') {
      scanCount++;
      if (hook) await hook();
      return {cleanup: job({scanComplete: scanCount > 1})};
    }
    if (action === 'account_cleanup_page') return {cleanup: job({page: data.page, rows: data.page === 2 ? rows.slice(2) : rows.slice(0, 2)})};
    if (action === 'account_cleanup_execute') {
      executeCount++;
      if (fail) { fail = false; throw new Error('Response lost'); }
      if (hook) await hook();
      return {cleanup: job({selectionLocked: true, selectedCount: 3, processed: executeCount === 1 ? 2 : 3, affected: executeCount === 1 ? 2 : 3, complete: executeCount > 1})};
    }
    throw new Error('Unexpected API: ' + action);
  }
};
vm.createContext(context);
const start = source.indexOf('      function updateAccountCleanupControls()');
const end = source.indexOf('      function formatCleanupDate(', start);
assert(start > 0 && end > start);
vm.runInContext(source.slice(start, end), context);
const sidebarStart = source.indexOf('      function renderSidebarSpaceUsage(');
const sidebarEnd = source.indexOf('      async function loadSidebarSpaceUsage(', sidebarStart);
vm.runInContext(source.slice(sidebarStart, sidebarEnd), context);
const change = id => $(id).dispatchEvent(new dom.window.Event('change', {bubbles: true}));

async function run() {
  context.renderSidebarSpaceUsage({accounts: [{id: 'account-one', name: 'One'}, {id: 'account-two', name: 'Two'}]}, null);
  assert.equal($$('[data-clean-account]').length, 2, 'Each account has a Clean button');
  $('[data-clean-account="account-two"]').click();
  assert.equal(context.accountCleanupContext.accountId, 'account-two');
  assert.equal(context.initialSettings.account_id, 'account-one', 'Cleaning another account does not switch the mailbox');
  assert.equal($('#accountCleanupRange').value, 'any');
  assert.deepEqual($$('#accountCleanupSize option').map(item => item.value), ['1', '5', '10', '20', '50', 'custom']);
  assert.deepEqual($$('#accountCleanupRange option').map(item => item.value), ['any', 'month', 'three_months', 'six_months', 'year', 'two_years', 'three_years', 'five_years', 'custom']);
  $('#accountCleanupSize').value = 'custom'; change('#accountCleanupSize');
  assert(!$('#accountCleanupCustomSize').classList.contains('d-none'));
  $('#accountCleanupCustomSize').value = '1.5';
  $('#accountCleanupRange').value = 'custom'; change('#accountCleanupRange');
  assert(!$('#accountCleanupDates').classList.contains('d-none'));
  $('#accountCleanupFrom').value = '2020-01-01'; $('#accountCleanupUntil').value = '2020-02-01';
  await context.searchAccountCleanup();
  const request = calls.find(call => call.action === 'account_cleanup_start');
  assert.equal(request.data.accountId, 'account-two');
  assert.equal(request.data.minMB, 1.5); assert.equal(request.data.mode, 'custom');
  assert.equal(request.data.from, '2020-01-01'); assert.equal(request.data.until, '2020-02-01');
  assert.equal($('#accountCleanupRows script'), null, 'Message text cannot inject HTML');
  assert.equal($('#accountCleanupDelete').disabled, true, 'No messages are selected automatically');
  $('#accountCleanupSelectPage').checked = true; change('#accountCleanupSelectPage');
  assert.equal(context.accountCleanupContext.selected.size, 2);
  await context.pageAccountCleanup(2);
  assert.match($('#accountCleanupRows').textContent, /Permanent deletion/);
  $('#accountCleanupRows input').checked = true; change('#accountCleanupRows input');
  assert.equal(context.accountCleanupContext.selected.size, 3, 'Selection survives pagination');
  $('#accountCleanupSelectAll').click(); assert(context.accountCleanupContext.all);
  $('#accountCleanupRows input').checked = false; change('#accountCleanupRows input');
  assert(!context.accountCleanupContext.all); assert.equal(context.accountCleanupContext.selected.size, 2, 'Deselecting one after all preserves the other selections');
  $('#accountCleanupSelectAll').click();
  confirmation = false;
  await context.deleteAccountCleanup();
  assert.equal(executeCount, 0, 'Cancelling typed confirmation does not delete');
  confirmation = true;
  hook = async () => $('#accountCleanupCancel').click();
  await context.deleteAccountCleanup();
  hook = null;
  assert.equal(executeCount, 1, 'Cancellation lets the in-flight batch finish and stops the next');
  assert.equal($('#accountCleanupDelete').textContent, 'Resume deletion');
  assert.equal($('#accountCleanupSize').disabled, true, 'Frozen deletion selection cannot change filters');
  await context.deleteAccountCleanup();
  assert.equal(executeCount, 2);
  assert.match($('#accountCleanupStatus').textContent, /Completed: 3/);
  assert.equal($('#accountCleanupDelete').disabled, true);
  assert(!calls.includes('folders'), 'Cleaning an inactive account does not replace the active mailbox');
  assert.equal(pauses.size, 0);

  context.openAccountCleanup({id: 'account-two', name: 'Two'});
  hook = async () => $('#accountCleanupCancel').click();
  await context.searchAccountCleanup();
  hook = null;
  assert.equal(scanCount, 1); assert.equal($('#accountCleanupDelete').disabled, true);
  assert.equal($('#accountCleanupSearch').textContent, 'Resume search');
  await context.searchAccountCleanup();
  assert.equal(scanCount, 2, 'Resuming search keeps the snapshot');
  $('#accountCleanupSelectAll').click();
  fail = true;
  await context.deleteAccountCleanup();
  assert.match($('#accountCleanupStatus').textContent, /Response lost/);
  assert.equal($('#accountCleanupDelete').disabled, false);
  await context.deleteAccountCleanup();
  assert.equal(context.accountCleanupContext.job.complete, true);
  assert.equal(pauses.size, 0, 'Errors release prefetch pauses');
  context.openAccountCleanup({id: 'account-two', name: 'Two'});
  $('#accountCleanupSize').value = 'custom'; $('#accountCleanupCustomSize').value = '0';
  const starts = calls.filter(call => call.action === 'account_cleanup_start').length;
  await context.searchAccountCleanup();
  assert.equal(calls.filter(call => call.action === 'account_cleanup_start').length, starts, 'Invalid custom size never starts a search');
  assert.match($('#accountCleanupStatus').textContent, /greater than zero/);
  console.log('PASS: Account Clean buttons, all filters, custom ranges, escaping, pagination, selection, confirmation, cancellation, resume, errors and account isolation.');
}
run().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => dom.window.close());
