'use strict';

// Run the real renderers and click handlers against a disposable mailbox DOM.
// Mailbox loads and ID queries are simulated, including delayed responses.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');
function block(start, end) {
  const first = source.indexOf(start);
  const last = source.indexOf(end, first + start.length);
  assert(first >= 0 && last > first, `Missing production block: ${start}`);
  return source.slice(first, last);
}
const production = [
  block('      function currentFolderRecord()', '      function formatIsoDateLabel('),
  block('      function updateCalendarButton()', '      function loadMailboxPreferences('),
  block('      function setUnreadOnlyView(', '      function messageDetailsCacheKey('),
  block('      function setBulkSelected(', '      function chunkUids('),
  block('      async function toggleCalendarView()', '      async function openMessagesStartingDate('),
  block('      async function toggleSameSenderFilter()', '      let searchTimer = null;'),
  block("      $('#toggleUnreadOnly').addEventListener(", "      $('#currentFolderSort').addEventListener("),
  block("      $('#toggleMultiSelect').addEventListener(", "      $('#bulkRead').addEventListener("),
  block("      $('#footerUnreadAction').addEventListener(", "      $('#footerFolderAction').addEventListener(")
].join('\n');
const markup = block('    <header class="pse-header', '  <div class="modal fade"');
const tick = () => new Promise(resolve => setImmediate(resolve));
function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return {promise, resolve};
}

function initialise(window, options = {}) {
  window.$ = selector => window.document.querySelector(selector);
  window.initialSettings = {account_type:'imap',show_calendar:true};
  window.state = {
    folder:'INBOX',folderName:'Inbox',folders:[{id:'INBOX',special:'inbox'}],
    page:1,messages:[{uid:'1',fromEmail:'alice@example.test'},{uid:'2',fromEmail:'bob@example.test'}],
    selectedUid:'1',currentMessage:{from:[{email:'alice@example.test'}]},selectedUids:new Set(),
    multiSelect:false,allPagesSelected:false,bulkSelectionRequestSerial:0,
    unreadOnly:false,senderFilter:'',attachmentFilter:'all',search:'',startDate:'',sortOrder:'desc',
    calendarActive:false,calendarMonth:'',calendarRequestSerial:0,lastSearch:null
  };
  window.events = [];
  window.searchTimer = null;
  window.resumePrefetch = window.clearPreview = window.renderPreview = () => {};
  window.formatIsoDateLabel = date => date;
  window.currentCalendarMonth = () => '2026-10';
  window.unreadPreferenceKey = () => 'fixture-unread';
  window.attachmentFilterPreferenceKey = () => 'fixture-attachments';
  window.loadMessages = (...args) => {
    window.events.push({load:args});
    return options.loadGate?.promise ?? Promise.resolve();
  };
  window.loadCalendarMonth = (...args) => {window.events.push({calendar:args});return Promise.resolve();};
  window.api = async (action, payload) => {
    assert.equal(action,'message_ids','Only a simulated read of mailbox IDs is allowed.');
    window.events.push({ids:payload});
    if (options.idGate) await options.idGate.promise;
    return {uids:options.uids ?? ['1','2','3']};
  };
  window.toast = () => {};
  window.handleError = error => {throw error;};
  window.eval(production);
  window.renderMessages = () => window.updateBulkUI();
  window.updateMailboxViewControls();
  window.updateBulkUI();
}

function environment(options = {}) {
  const dom = new JSDOM(`<!doctype html><body>${markup}</body>`,{url:'https://pse.example/index.php',runScripts:'outside-only'});
  initialise(dom.window,options);
  return {
    window:dom.window,state:dom.window.state,document:dom.window.document,
    async click(id) {dom.window.document.getElementById(id).click();await tick();},
    pressed(id, expected) {
      const button = dom.window.document.getElementById(id);
      assert.equal(button.getAttribute('aria-pressed'),String(expected),`${id} announces the actual toggle state.`);
      assert.equal(button.classList.contains('active'),expected,`${id} looks selected only while active.`);
    },
    close() {dom.window.close();}
  };
}

async function run() {
  let scenarios = 0;
  const filters = environment();
  for (const id of ['toggleUnreadOnly','footerUnreadAction','filterAttachments','toggleCalendar']) {
    for (let cycle=0;cycle<2;cycle++) {
      await filters.click(id);filters.pressed(id,true);
      await filters.click(id);filters.pressed(id,false);
    }
    scenarios++;
  }
  assert.equal(filters.window.localStorage.getItem('fixture-unread'),'0');
  assert.equal(filters.window.localStorage.getItem('fixture-attachments'),'all');
  filters.state.selectedUid = '1';
  filters.state.currentMessage = {from:[{email:'alice@example.test'}]};
  filters.window.updateMailboxViewControls();
  await filters.click('filterSameSender');filters.pressed('filterSameSender',true);
  filters.state.selectedUid = null;filters.state.currentMessage = null;
  filters.window.updateMailboxViewControls();
  assert.equal(filters.document.querySelector('#filterSameSender').disabled,false,'An active sender filter stays available to turn off after the preview is cleared.');
  await filters.click('filterSameSender');filters.pressed('filterSameSender',false);
  assert.equal(filters.state.senderFilter,'');
  filters.close();scenarios++;

  const selection = environment();
  for (let cycle=0;cycle<2;cycle++) {
    await selection.click('toggleMultiSelect');selection.pressed('toggleMultiSelect',true);
    assert.equal(selection.document.querySelector('#bulkActions').classList.contains('d-none'),false);
    selection.state.selectedUids.add('1');selection.window.updateBulkUI();
    await selection.click('toggleMultiSelect');selection.pressed('toggleMultiSelect',false);
    assert.equal(selection.state.selectedUids.size,0);
    assert.equal(selection.document.querySelector('#bulkActions').classList.contains('d-none'),true);
  }
  scenarios++;
  await selection.click('toggleMultiSelect');
  await selection.click('bulkSelectAll');selection.pressed('bulkSelectAll',true);
  assert.deepEqual([...selection.state.selectedUids],['1','2']);
  await selection.click('bulkSelectAll');selection.pressed('bulkSelectAll',false);
  assert.equal(selection.state.selectedUids.size,0);scenarios++;
  await selection.click('bulkSelectAllPages');selection.pressed('bulkSelectAllPages',true);
  selection.pressed('bulkSelectAll',true);
  assert.equal(selection.state.selectedUids.size,3);
  await selection.click('bulkSelectAllPages');selection.pressed('bulkSelectAllPages',false);
  assert.equal(selection.state.selectedUids.size,0);
  assert.equal(selection.window.events.filter(e=>e.ids).length,1,'Deselecting all pages needs no mailbox request.');scenarios++;
  await selection.click('bulkSelectAllPages');
  await selection.click('bulkSelectAll');
  assert.deepEqual([...selection.state.selectedUids],['3'],'Deselecting this page preserves checked messages on other pages.');
  selection.pressed('bulkSelectAll',false);selection.pressed('bulkSelectAllPages',false);
  selection.close();scenarios++;

  const empty = environment({uids:[]});
  await empty.click('toggleMultiSelect');await empty.click('bulkSelectAllPages');
  empty.pressed('bulkSelectAllPages',false);empty.pressed('bulkSelectAll',false);
  assert.equal(empty.state.selectedUids.size,0,'An empty ID result cannot show an active selection.');
  empty.close();scenarios++;

  const loadGate = deferred();
  const loading = environment({loadGate});
  await loading.click('toggleMultiSelect');await loading.click('bulkSelectAll');
  await loading.click('toggleUnreadOnly');
  loading.pressed('toggleUnreadOnly',true);loading.pressed('toggleMultiSelect',false);
  assert.equal(loading.document.querySelector('#bulkActions').classList.contains('d-none'),true,'Changing filters resets selection UI before the mailbox request finishes.');
  await loading.click('toggleUnreadOnly');loading.pressed('toggleUnreadOnly',false);
  loadGate.resolve();await tick();loading.pressed('toggleUnreadOnly',false);
  loading.close();scenarios++;

  for (const action of ['bulkClear','toggleMultiSelect','filterAttachments','filter-return','row']) {
    const idGate = deferred();
    const stale = environment({idGate});
    await stale.click('toggleMultiSelect');
    stale.state.selectedUids.add('1');stale.window.updateBulkUI();
    await stale.click('bulkSelectAllPages');
    if (action==='filter-return') {
      await stale.click('filterAttachments');await stale.click('filterAttachments');
    } else if (action==='row') {
      stale.window.setBulkSelected('1',false);
    } else {
      await stale.click(action);
    }
    if (action==='toggleMultiSelect') await stale.click('toggleMultiSelect');
    idGate.resolve();await tick();
    assert.equal(stale.state.selectedUids.size,0,`An old ID response cannot undo ${action} or reactivate its cleared selection.`);
    stale.pressed('bulkSelectAllPages',false);
    stale.close();scenarios++;
  }
  console.log(`Mailbox toggles: ${scenarios} scenarios passed (repeat taps, toolbar/footer filters, selection clearing, page/all-page toggles and delayed responses).`);
}

module.exports = {production,initialise};
if (require.main === module) run().catch(error=>{console.error(error);process.exitCode=1;});
