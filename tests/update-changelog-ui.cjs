const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const start = source.indexOf('      let lastUpdateInfo = null;');
const end = source.indexOf('      function showAccountSwitchedNotice()', start);
assert(start >= 0 && end > start, 'Application update UI functions must be present.');

const pending = {
  id: 'release-2181', fromVersion: '2.18.0', toVersion: '2.18.1',
  text: '## 2.18.1\n- Release notes\n</pre><img src=x onerror=alert(1)>'
};
const available = {
  updateAvailable: true, currentVersion: '2.18.0', latestVersion: '2.18.1',
  sourceCommit: '1234567890', changelog: pending.text,
  changelogUrl: 'https://github.com/ziobit/PSE-Email-Client/blob/main/CHANGELOG.md'
};

function environment(options = {}) {
  const calls = [];
  const storage = new Map(options.stored ? [['pse_update_applied', JSON.stringify(options.stored)]] : []);
  const elements = new Map();
  const context = {
    URL, String, JSON, console,
    window: {setTimeout: callback => setImmediate(callback)},
    initialSettings: {version: '2.18.1', auto_update: Boolean(options.automatic)},
    activeSwalTarget: () => undefined,
    escapeHtml: value => String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char])),
    $: selector => {
      if (!elements.has(selector)) elements.set(selector, {
        innerHTML: '', textContent: '', disabled: false, classList: {toggle() {}}
      });
      return elements.get(selector);
    },
    sessionStorage: {
      getItem(key) { if (options.blockStorage) throw new Error('blocked'); return storage.get(key) || null; },
      setItem(key, value) { if (options.blockStorage) throw new Error('blocked'); storage.set(key, value); },
      removeItem(key) { if (options.blockStorage) throw new Error('blocked'); calls.push({removed: key}); storage.delete(key); }
    },
    Swal: {isVisible: () => options.isVisible ? options.isVisible() : false, fire: async config => {
      calls.push({modal: config});
      return options.modal ? options.modal(config) : {isConfirmed: true};
    }},
    api: async (name, args, settings) => {
      calls.push({name, args, settings});
      if (options.api) return options.api(name, args);
      if (name === 'update_changelog') return {changelog: options.pending ?? null};
      if (name === 'update_changelog_ack') return {ok: true};
      if (name === 'update_check') return {update: options.available || {status: 'current', currentVersion: '2.18.1'}};
      if (name === 'apply_update') return {updated: true, result: {oldVersion: '2.18.0', newVersion: '2.18.1', changelog: pending.text}};
      throw new Error(`Unexpected action: ${name}`);
    },
    toast: (message, type) => calls.push({toast: message, type}),
    handleError: error => { throw error; },
    location: {reload: () => calls.push('reload')}
  };
  vm.createContext(context);
  vm.runInContext(source.slice(start, end), context);
  return {context, calls, storage};
}

async function run() {
  const normal = environment();
  await normal.context.promptApplicationUpdate(available);
  const prompt = normal.calls.find(call => call.modal).modal;
  assert.match(prompt.html, /What's new/);
  assert.match(prompt.html, /white-space:pre-wrap/);
  assert.match(prompt.html, /&lt;\/pre&gt;&lt;img/);
  assert(!prompt.html.includes('<img src=x'), 'Changelog HTML must never execute.');
  assert.match(prompt.html, /View the changelog on GitHub/);
  const install = normal.calls.find(call => call.name === 'apply_update');
  assert.equal(install.args.expectedVersion, '2.18.1');
  assert.equal(install.args.expectedCommit, '1234567890');
  assert.equal(install.args.confirmed, true);
  assert.equal(JSON.parse(normal.storage.get('pse_update_applied')).changelog, pending.text);
  assert(normal.calls.includes('reload'));

  for (const unsafe of [
    'javascript:alert(1)', 'https://github.com.evil.example/ziobit/PSE-Email-Client',
    'https://github.com/another/repository', 'https://user:password@github.com/ziobit/PSE-Email-Client',
    'http://github.com/ziobit/PSE-Email-Client', 'https://github.com:444/ziobit/PSE-Email-Client'
  ]) {
    assert(!normal.context.applicationChangelogHtml('Notes', unsafe).includes('<a '), unsafe);
  }

  const blocked = environment({blockStorage: true, pending});
  await Promise.all([blocked.context.showAppliedUpdateNotice(), blocked.context.showAppliedUpdateNotice()]);
  assert.equal(blocked.calls.filter(call => call.name === 'update_changelog').length, 1);
  assert.equal(blocked.calls.filter(call => call.modal).length, 1);
  const shown = blocked.calls.find(call => call.modal).modal;
  assert.equal(shown.title, 'PSE updated');
  assert.equal(shown.allowOutsideClick, false);
  assert.equal(shown.allowEscapeKey, false);
  assert.match(shown.html, /Release notes/);
  assert.match(shown.html, /&lt;\/pre&gt;&lt;img/);
  assert.equal(blocked.calls.find(call => call.name === 'update_changelog_ack').args.id, pending.id);

  const manualDeployment = environment({blockStorage: true, pending: {...pending, fromVersion: ''}});
  await manualDeployment.context.showAppliedUpdateNotice();
  const manualNotice = manualDeployment.calls.find(call => call.modal).modal;
  assert.match(manualNotice.html, /PSE 2\.18\.1/);
  assert(!manualNotice.html.includes('fa-arrow-right'), 'A manual deployment with no known previous version should show the current version alone.');
  assert.equal(manualDeployment.calls.find(call => call.name === 'update_changelog_ack').args.id, pending.id);

  let reopened;
  let popupCount = 0;
  const dismissed = environment({pending, modal: () => {
    if (++popupCount === 1) return {isConfirmed: false};
    return new Promise(resolve => { reopened = resolve; });
  }});
  const interruptedNotice = dismissed.context.showAppliedUpdateNotice();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
  assert(!dismissed.calls.some(call => call.name === 'update_changelog_ack'), 'Only an explicit acknowledgement clears notes.');
  assert(reopened, 'Another startup popup replacing release notes must cause the notes to reopen.');
  reopened({isConfirmed: true});
  await interruptedNotice;
  assert.equal(dismissed.calls.filter(call => call.name === 'update_changelog_ack').length, 1);

  let otherPopupVisible = true;
  const queued = environment({pending, isVisible: () => otherPopupVisible});
  const queuedNotice = queued.context.showAppliedUpdateNotice();
  await new Promise(resolve => setImmediate(resolve));
  assert(!queued.calls.some(call => call.modal), 'Release notes must wait for an existing startup popup.');
  otherPopupVisible = false;
  await queuedNotice;
  assert.equal(queued.calls.filter(call => call.modal).length, 1);

  const fallback = environment({
    stored: {oldVersion: '2.17.0', newVersion: '2.18.0', changelog: 'Saved release notes'},
    api: name => { throw new Error(`Offline: ${name}`); }
  });
  await fallback.context.showAppliedUpdateNotice();
  assert.match(fallback.calls.find(call => call.modal).modal.html, /Saved release notes/);
  assert(!fallback.storage.has('pse_update_applied'));

  const retryAck = environment({
    pending, stored: {oldVersion: '2.18.0', newVersion: '2.18.1', changelog: 'Saved'},
    api: name => {
      if (name === 'update_changelog') return {changelog: pending};
      throw new Error('Acknowledgement failed');
    }
  });
  await retryAck.context.showAppliedUpdateNotice();
  assert(retryAck.storage.has('pse_update_applied'), 'Failed acknowledgement must preserve the browser fallback.');

  let completeModal;
  const serial = environment({pending, modal: () => new Promise(resolve => { completeModal = resolve; })});
  const startup = serial.context.checkApplicationUpdate(false, true);
  await new Promise(resolve => setImmediate(resolve));
  assert(completeModal, 'Startup must first present the installed release notes.');
  assert(!serial.calls.some(call => call.name === 'update_check'), 'New update checks must wait for the release notes acknowledgement.');
  completeModal({isConfirmed: true});
  await startup;
  assert(serial.calls.findIndex(call => call.name === 'update_changelog_ack')
    < serial.calls.findIndex(call => call.name === 'update_check'));

  const automatic = environment({automatic: true, blockStorage: true, available});
  await automatic.context.checkApplicationUpdate(false, true);
  assert.equal(automatic.calls.find(call => call.name === 'apply_update').args.automatic, true);
  assert(automatic.calls.includes('reload'), 'Blocked browser storage must not stop installation.');
  console.log('Update changelog UI: escaped notes, safe links, manual/automatic install, durable fallback, acknowledgement and startup ordering passed.');
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
