'use strict';

// Exercise the production UI with local storage, user gestures and fullscreen
// transitions simulated in a DOM. No mailbox, server or remote assets are used.
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
const production = block('      /* PSE_FULLSCREEN_START */', '      /* PSE_FULLSCREEN_END */');
const markup = block('    <header class="pse-header', '    <main class="pse-workspace"') +
  block('                  <div class="border rounded-3 p-3" id="fullscreenSettingsCard">', '                <div class="col-12 form-check ms-2">');
const key = 'pse_fullscreen_preference_v1';
const tick = () => new Promise(resolve => setImmediate(resolve));
function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return {promise, resolve};
}
let scenarios = 0;

function environment(options = {}) {
  const dom = new JSDOM(`<!doctype html><body>${markup}<div class="modal" id="fixtureModal"></div></body>`, {
    url: 'https://pse.example/index.php', runScripts: 'outside-only'
  });
  const window = dom.window;
  const document = window.document;
  const storage = options.storage ?? new Map(options.saved === undefined ? [] : [[key, options.saved]]);
  const timers = [];
  const calls = [];
  let active = null;
  let gesture = false;
  Object.defineProperty(window, 'localStorage', {value: {
    getItem(name) { if (options.blockStorage) throw new Error('Storage blocked'); return storage.get(name) ?? null; },
    setItem(name, value) { if (options.blockStorage) throw new Error('Storage blocked'); storage.set(name, value); }
  }});
  Object.defineProperty(window.navigator, 'userAgent', {value: options.desktop ? 'Desktop browser' : 'Android mobile browser'});
  Object.defineProperty(window.navigator, 'userAgentData', {value: {mobile: Boolean(options.mobileHint)}});
  Object.defineProperty(window.navigator, 'maxTouchPoints', {value: options.touch ?? (options.desktop ? 0 : 5)});
  window.matchMedia = query => ({
    matches: query.includes('display-mode') ? Boolean(options.appFullscreen) : (options.width ?? 360) <= 900,
    addEventListener() {}
  });
  Object.defineProperty(document, 'hidden', {get: () => Boolean(options.hidden)});
  Object.defineProperty(document, 'fullscreenEnabled', {value: options.permission !== false});
  Object.defineProperty(document, 'webkitFullscreenEnabled', {value: options.permission !== false});
  Object.defineProperty(document, options.vendor ? 'webkitFullscreenElement' : 'fullscreenElement', {get: () => active});
  const transition = entered => {
    active = entered ? document.documentElement : null;
    document.dispatchEvent(new window.Event(options.vendor ? 'webkitfullscreenchange' : 'fullscreenchange'));
  };
  if (options.supported !== false) {
    document.documentElement[options.vendor ? 'webkitRequestFullscreen' : 'requestFullscreen'] = function(config) {
      assert(gesture, 'Fullscreen entry must be requested within the original user gesture, before any asynchronous work.');
      assert.equal(this, document.documentElement, 'The whole app goes fullscreen.');
      calls.push({request: true, config});
      if (options.syncError) throw new Error('Synchronous refusal');
      if (options.reject) return Promise.reject(new Error('Browser denied fullscreen'));
      if (options.vendor) { transition(true); return undefined; }
      return (options.gate?.promise ?? Promise.resolve()).then(() => transition(true));
    };
    document[options.vendor ? 'webkitExitFullscreen' : 'exitFullscreen'] = function() {
      calls.push({exit: true});
      transition(false);
      return Promise.resolve();
    };
  }
  window.$ = selector => document.querySelector(selector);
  window.Swal = {isVisible: () => Boolean(options.otherPopup)};
  window.fileLaunchReady = options.fileReady ?? Promise.resolve();
  window.applicationUpdateStartupScheduled = Boolean(options.updateScheduled);
  window.applicationUpdateStartupPromise = options.updateReady ?? null;
  window.setTimeout = (callback, delay) => { timers.push({callback, delay}); return timers.length; };
  window.console.warn = (...args) => calls.push({warning: args});
  window.eval(production);
  return {
    window, document, storage, timers, calls, options,
    visible: () => !document.querySelector('#fullscreenPrompt').classList.contains('d-none'),
    active: () => Boolean(active), exitFromBrowser: () => transition(false),
    async timer() { const pending = timers.shift(); if (pending) await pending.callback(); await tick(); },
    async act(id, checked) {
      const target = document.getElementById(id);
      if (checked !== undefined) target.checked = checked;
      gesture = true;
      target.dispatchEvent(new window.Event(checked === undefined ? 'click' : 'change', {bubbles: true}));
      gesture = false;
      await tick();
    },
    close: () => dom.window.close()
  };
}

(async () => {
  const first = environment();
  assert.equal(first.document.querySelector('#preferFullscreen').classList.contains('setting'), false, 'Fullscreen preference must not be sent to mailbox account settings.');
  assert.equal(first.visible(), false);
  await first.timer();
  assert.equal(first.visible(), true);
  assert.equal(first.calls.length, 0, 'Showing a prompt cannot request fullscreen automatically.');
  await first.act('acceptFullscreen');
  assert.equal(first.storage.get(key), '1');
  assert.equal(first.active(), true);
  assert.equal(first.visible(), false);
  assert.equal(first.calls.find(c => c.request).config.navigationUI, 'hide');
  assert.equal(first.document.querySelector('#toggleFullscreen').getAttribute('aria-pressed'), 'true');
  first.exitFromBrowser();
  assert.equal(first.storage.get(key), '1', 'Browser exit keeps the saved preference.');
  assert.equal(first.document.querySelector('#toggleFullscreen').getAttribute('aria-pressed'), 'false');
  const reopened = environment({storage: first.storage});
  assert.equal(reopened.timers.length, 0, 'An accepted first-visit choice is not asked again.');
  assert.equal(reopened.calls.length, 0, 'Remembering full screen must not attempt entry without a fresh tap.');
  assert.equal(reopened.document.querySelector('#toggleFullscreen').classList.contains('d-none'), false);
  await reopened.act('toggleFullscreen');
  assert.equal(reopened.active(), true);
  await reopened.act('toggleFullscreen');
  assert.equal(reopened.active(), false);
  assert.equal(reopened.storage.get(key), '1', 'Header exit is temporary; Settings controls the preference.');
  first.close(); reopened.close(); scenarios++;

  const declined = environment();
  await declined.timer();
  await declined.act('declineFullscreen');
  assert.equal(declined.storage.get(key), '0');
  assert.equal(declined.visible(), false);
  assert.equal(declined.calls.length, 0);
  const declinedReload = environment({storage: declined.storage});
  assert.equal(declinedReload.timers.length, 0, 'Declining is remembered across reloads.');
  assert.equal(declinedReload.document.querySelector('#toggleFullscreen').classList.contains('d-none'), true);
  declined.close(); declinedReload.close(); scenarios++;

  const settings = environment({desktop: true, saved: '0'});
  await settings.act('preferFullscreen', true);
  assert.equal(settings.storage.get(key), '1');
  assert.equal(settings.active(), true);
  assert.equal(settings.document.querySelector('#preferFullscreen').checked, true);
  await settings.act('preferFullscreen', false);
  assert.equal(settings.storage.get(key), '0');
  assert.equal(settings.active(), false);
  assert.equal(settings.document.querySelector('#preferFullscreen').checked, false);
  await settings.act('settingsFullscreenToggle');
  assert.equal(settings.active(), true, 'Settings can enter fullscreen immediately.');
  await settings.act('settingsFullscreenToggle');
  assert.equal(settings.active(), false);
  assert.equal(settings.storage.get(key), '0', 'A manual enter/exit action does not change the preference.');
  settings.close(); scenarios++;

  for (const options of [{desktop:true}, {supported:false}, {permission:false}, {appFullscreen:true}]) {
    const ignored = environment(options);
    assert.equal(ignored.timers.length, 0, 'Desktop, unsupported, prohibited or already-fullscreen app launches do not show a mobile prompt.');
    assert.equal(ignored.visible(), false);
    assert.equal(ignored.calls.length, 0);
    if (options.supported === false || options.permission === false) {
      assert.equal(ignored.document.querySelector('#preferFullscreen').disabled, true);
      assert.match(ignored.document.querySelector('#fullscreenSettingsStatus').textContent, /unavailable.*Install/i);
    }
    if (options.appFullscreen) assert.equal(ignored.document.querySelector('#settingsFullscreenToggle').disabled, true, 'Do not offer an API exit for fullscreen set by the app manifest.');
    ignored.close(); scenarios++;
  }

  for (const options of [{width:1200}, {desktop:true,mobileHint:true}, {desktop:true,touch:5}, {saved:'invalid'}]) {
    const mobile = environment(options);
    await mobile.timer();
    assert.equal(mobile.visible(), true, 'Landscape phones, mobile hints and touch tablets receive the first prompt.');
    mobile.close(); scenarios++;
  }

  const vendor = environment({vendor:true});
  await vendor.timer(); await vendor.act('acceptFullscreen');
  assert.equal(vendor.active(), true);
  await vendor.act('toggleFullscreen');
  assert.equal(vendor.active(), false, 'Prefixed fullscreen APIs and void return values work.');
  vendor.close(); scenarios++;

  for (const options of [{reject:true}, {syncError:true}]) {
    const denied = environment(options);
    await denied.timer(); await denied.act('acceptFullscreen');
    assert.equal(denied.active(), false);
    assert.equal(denied.visible(), true);
    assert.equal(denied.storage.get(key), '1', 'Failure retains the chosen preference for retry.');
    assert.match(denied.document.querySelector('#fullscreenPromptError').textContent, /could not/);
    await denied.act('declineFullscreen');
    assert.equal(denied.visible(), false);
    assert.equal(denied.storage.get(key), '1');
    options.reject = false; options.syncError = false;
    await denied.act('toggleFullscreen');
    assert.equal(denied.active(), true);
    assert.doesNotMatch(denied.document.querySelector('#fullscreenSettingsStatus').textContent, /could not/);
    denied.close(); scenarios++;
  }

  const blocked = environment({blockStorage:true});
  await blocked.timer(); await blocked.act('acceptFullscreen');
  assert.equal(blocked.active(), true);
  assert.match(blocked.document.querySelector('#fullscreenSettingsStatus').textContent, /this visit only/);
  await blocked.timer();
  assert.equal(blocked.visible(), false, 'Blocked storage does not repeat the prompt during this visit.');
  blocked.close(); scenarios++;

  const gate = deferred();
  const busy = environment({saved:'1',gate});
  await busy.act('toggleFullscreen');
  assert.equal(busy.document.querySelector('#toggleFullscreen').disabled, true);
  await busy.act('toggleFullscreen');
  assert.equal(busy.calls.filter(c=>c.request).length, 1, 'Overlapping requests are suppressed.');
  gate.resolve(); await tick();
  assert.equal(busy.active(), true);
  assert.equal(busy.document.querySelector('#toggleFullscreen').disabled, false);
  busy.close(); scenarios++;

  const updateGate = deferred();
  const updating = environment({updateScheduled:true,updateReady:updateGate.promise});
  const waiting = updating.timer();
  await tick();
  assert.equal(updating.visible(), false, 'Release notes and update checks finish before asking about fullscreen.');
  updateGate.resolve(); await waiting;
  assert.equal(updating.visible(), true);
  updating.close(); scenarios++;

  const notStarted = environment({updateScheduled:true});
  await notStarted.timer();
  assert.equal(notStarted.visible(), false);
  notStarted.window.applicationUpdateStartupPromise = Promise.resolve();
  await notStarted.timer();
  assert.equal(notStarted.visible(), true);
  notStarted.close(); scenarios++;

  for (const kind of ['modal','popup','hidden']) {
    const opts = {otherPopup:kind==='popup',hidden:kind==='hidden'};
    const delayed = environment(opts);
    if (kind==='modal') delayed.document.querySelector('#fixtureModal').classList.add('show');
    await delayed.timer();
    assert.equal(delayed.visible(), false, 'Other dialogs and background tabs are not interrupted.');
    delayed.document.querySelector('#fixtureModal').classList.remove('show');
    opts.otherPopup=false; opts.hidden=false;
    await delayed.timer();
    assert.equal(delayed.visible(), true);
    delayed.close(); scenarios++;
  }

  const fileGate = deferred();
  const launching = environment({fileReady:fileGate.promise});
  const opening = launching.timer();
  await tick(); assert.equal(launching.visible(), false, 'Opening a local file takes priority.');
  fileGate.resolve(); await opening;
  assert.equal(launching.visible(), true);
  assert.equal(launching.calls.length, 0, 'No mailbox API is involved in the first mobile prompt.');
  launching.close(); scenarios++;

  const otherTab = environment({saved:'1'});
  otherTab.storage.set(key,'0');
  otherTab.window.dispatchEvent(new otherTab.window.StorageEvent('storage',{key}));
  assert.equal(otherTab.document.querySelector('#preferFullscreen').checked, false);
  assert.equal(otherTab.document.querySelector('#toggleFullscreen').classList.contains('d-none'), true);
  assert.equal(otherTab.calls.length, 0, 'Cross-tab changes update the preference without making fullscreen requests.');
  otherTab.close(); scenarios++;

  console.log(`Fullscreen UI: ${scenarios} scenarios passed (first mobile choice, persistence, gesture timing, settings, exits, unsupported browsers, blocked storage, failures and startup coordination).`);
})().catch(error=>{console.error(error);process.exitCode=1;});
