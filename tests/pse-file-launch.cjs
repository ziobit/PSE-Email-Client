'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
function sourceBlock(start, end) {
  const first = source.indexOf(start);
  const last = source.indexOf(end, first + start.length);
  assert(first >= 0 && last > first, `Missing production block: ${start}`);
  return source.slice(first, last);
}
const bridgeSource = sourceBlock('    window.pseFileLaunch = (() => {', '  </script>');
const startupSource = sourceBlock('      /* PSE_MAILBOX_STARTUP_START */', '      /* PSE_MAILBOX_STARTUP_END */');
const tick = () => new Promise(resolve => setImmediate(resolve));
function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return {promise, resolve};
}

// Exercise the real launch bridge against asynchronous IDB transaction events.
function fakeIndexedDb(records, options = {}) {
  return {open() {
    const request = {};
    const open = () => {
      request.result = {
        close() {},
        createObjectStore() {},
        transaction() {
          const transaction = {};
          let pending = 0;
          let complete = false;
          const schedule = callback => {
            pending++;
            setImmediate(() => {
              callback();
              pending--;
              setImmediate(() => {
                if (!pending && !complete) {
                  complete = true;
                  transaction.oncomplete?.();
                }
              });
            });
          };
          const store = {
            get(key) {
              const get = {};
              schedule(() => { get.result = records.get(key); get.onsuccess?.(); });
              return get;
            },
            put(value, key) { records.set(key, value); schedule(() => {}); },
            delete(key) { records.delete(key); schedule(() => {}); },
            openCursor() {
              const cursorRequest = {};
              const keys = Array.from(records.keys());
              let index = 0;
              const next = () => schedule(() => {
                const key = keys[index++];
                cursorRequest.result = key === undefined ? null : {
                  value: records.get(key),
                  delete: () => records.delete(key),
                  continue: next
                };
                cursorRequest.onsuccess?.();
              });
              next();
              return cursorRequest;
            }
          };
          transaction.objectStore = () => store;
          transaction.abort = () => transaction.onabort?.();
          schedule(() => {});
          return transaction;
        }
      };
      request.onsuccess?.();
    };
    if (options.openGate) options.openGate.promise.then(open);
    else setImmediate(open);
    return request;
  }};
}

function bridgeEnvironment(options = {}) {
  let consumer;
  const session = options.session || new Map();
  const records = options.records || new Map();
  const elements = new Map();
  const authForm = {appendChild: element => elements.set(element.id, element)};
  if (options.authenticated === false) elements.set('authForm', authForm);
  const context = {
    URLSearchParams, URL, Date, Math, Array, Promise, console,
    location: {pathname: '/index.php', search: options.search || '', href: `https://pse.example/index.php${options.search || ''}`},
    sessionStorage: {
      getItem(key) { if (options.blockSession) throw new Error('blocked'); return session.get(key) || null; },
      setItem(key, value) { if (options.blockSession) throw new Error('blocked'); session.set(key, value); },
      removeItem(key) { session.delete(key); }
    },
    document: {
      getElementById: id => elements.get(id) || null,
      createElement: () => ({})
    },
    window: {launchQueue: {setConsumer(callback) { consumer = callback; }}}
  };
  context.history = {replaceState(state, title, path) {
    const url = new URL(path, context.location.href);
    context.location.pathname = url.pathname;
    context.location.search = url.search;
    context.location.href = url.href;
  }};
  if (!options.blockIdb) {
    context.indexedDB = fakeIndexedDb(records, options);
    context.window.indexedDB = context.indexedDB;
  }
  vm.createContext(context);
  vm.runInContext(bridgeSource.replace(/<\?=\s*!empty\(\$pseAuthenticated\)[\s\S]*?\?>/, options.authenticated === false ? 'false' : 'true'), context);
  return {
    bridge: context.window.pseFileLaunch, records, session, elements, location: context.location,
    launch: files => consumer({files})
  };
}

function startupEnvironment(options = {}) {
  const calls = [];
  const elements = new Map();
  const context = {
    console, URL, Promise,
    state: {mailboxStarted: false},
    fileLaunchReady: options.ready || Promise.resolve(),
    location: {href: options.href || 'https://pse.example/index.php'},
    window: {
      pseFileLaunch: {isFileLaunch: () => Boolean(options.fileLaunch)},
      setTimeout: callback => { calls.push({timer: callback}); return 1; }
    },
    $: selector => {
      if (selector === '.modal.show') return options.modalOpen ? {} : null;
      if (!elements.has(selector)) {
        const classes = new Set(selector === '#fileLaunchMailboxHint' ? ['d-none'] : []);
        elements.set(selector, {
          innerHTML: '', textContent: '', classes,
          classList: {add: name => classes.add(name), remove: name => classes.delete(name), toggle(name) { if (classes.has(name)) classes.delete(name); else classes.add(name); }},
          addEventListener() {}, setAttribute() {}, removeAttribute() {}
        });
      }
      return elements.get(selector);
    },
    pausePrefetch: reason => calls.push({pause: reason}),
    resumePrefetch: reason => calls.push({resume: reason}),
    loadFolders: async (...args) => { calls.push({folders: args}); return options.loadFolders ? options.loadFolders(...args) : {}; },
    pollFolderStatus: () => calls.push({poll: true}),
    checkApplicationUpdate: (...args) => calls.push({update: args}),
    showAppliedUpdateNotice: async () => calls.push({notice: true}),
    showAccountSwitchedNotice: () => calls.push({accountNotice: true}),
    handleError: error => { throw error; }
  };
  context.history = {replaceState(state, title, path) {
    calls.push({navigation: path});
    context.location.href = new URL(path, context.location.href).href;
  }};
  vm.createContext(context);
  vm.runInContext(startupSource, context);
  return {context, calls, elements};
}

function backgroundEnvironment() {
  const calls = [];
  const timers = [];
  const listeners = {window: new Map(), document: new Map(), connection: new Map()};
  const bind = scope => (event, callback) => listeners[scope].set(event, callback);
  const context = {
    console, csrf: 'fixture-token',
    state: {mailboxStarted: false, prefetchGeneration: 1},
    window: {addEventListener: bind('window')},
    document: {hidden: false, addEventListener: bind('document')},
    navigator: {onLine: true, connection: {addEventListener: bind('connection')}},
    $: () => null,
    setInterval: (callback, milliseconds) => { timers.push({callback, milliseconds}); return timers.length; },
    mailboxCheckIntervalMs: () => 60000,
    prefetchConnectionAllowsBackground: () => true,
    pausePrefetch: (...args) => calls.push({pause: args}),
    resumePrefetch: (...args) => calls.push({resume: args}),
    scheduleVisibleMessagePrefetch: (...args) => calls.push({prefetch: args}),
    flushActionQueue: (...args) => calls.push({queue: args}),
    pollFolderStatus: () => calls.push({poll: true}),
    refreshLastSyncStatus: () => calls.push({display: true}),
    saveComposeBeforeBrowserClose: () => calls.push({saveCompose: true}),
    interruptPrefetchRequests: () => calls.push({interrupt: true}),
    scheduleApplicationUpdateStartup: () => calls.push({scheduleUpdate: true}),
    fetch: (...args) => { calls.push({fetch: args}); return Promise.resolve({}); }
  };
  vm.createContext(context);
  vm.runInContext(sourceBlock('      /* PSE_MAILBOX_BACKGROUND_START */', '      /* PSE_MAILBOX_BACKGROUND_END */'), context);
  context.installMailboxBackgroundHandlers();
  return {context, calls, timers, listeners};
}

async function run() {
  const file = {name: 'saved.pse', getFile: async () => ({name: 'saved.pse'})};
  const cold = bridgeEnvironment({search: '?open_pse=1', blockIdb: true});
  assert.equal(cold.bridge.isFileLaunch(), true, 'File action URL suppresses mailbox work before OS file delivery.');
  const coldFiles = [];
  await cold.bridge.connect(files => coldFiles.push(...files));
  assert.equal(coldFiles.length, 0);
  await cold.launch([file]);
  assert.deepEqual(coldFiles, [file], 'A late OS file opens directly after the receiver connects.');

  const normal = bridgeEnvironment();
  assert.equal(normal.bridge.isFileLaunch(), false);
  await normal.launch([null, {}, {name: 'unsupported'}]);
  assert.equal(normal.bridge.isFileLaunch(), false, 'A launch without usable handles is ordinary app activation.');
  const received = [];
  await normal.bridge.connect(files => received.push(...files));
  await normal.launch([file]);
  assert.deepEqual(received, [file], 'An existing app receiver handles launches without navigation.');
  assert.equal(normal.bridge.isFileLaunch(), true);
  assert.equal(normal.location.search, '', 'A warm app keeps its normal URL when it receives another file.');

  const queued = bridgeEnvironment({blockIdb: true});
  await queued.launch([file]);
  const queuedFiles = [];
  await queued.bridge.connect(files => queuedFiles.push(...files));
  assert.equal(queued.bridge.isFileLaunch(), true);
  assert.deepEqual(queuedFiles, [file], 'Handles arriving before the app is ready are drained once.');

  const signedOut = bridgeEnvironment({authenticated: false});
  await signedOut.launch([file]);
  await signedOut.bridge.whenStored();
  assert.equal(signedOut.records.size, 1, 'Signed-out launches survive the password reload locally.');
  assert.equal(new URLSearchParams(signedOut.location.search).get('open_pse'), '1', 'The password reload retains the fast file-action server path.');
  assert.match(signedOut.elements.get('pendingPseLaunchHint').textContent, /waiting|Sign in/);
  const signedIn = bridgeEnvironment({session: signedOut.session, records: signedOut.records});
  const restored = [];
  await signedIn.bridge.connect(files => restored.push(...files));
  assert.equal(signedIn.bridge.isFileLaunch(), true, 'Restoring a retained launch suppresses mail startup even without the URL marker.');
  assert.equal(new URLSearchParams(signedIn.location.search).get('open_pse'), '1');
  assert.deepEqual(restored, [file]);
  assert.equal(signedOut.records.size, 0, 'Restored handles are removed from retention storage.');

  const expired = bridgeEnvironment({session: new Map([['pse_pending_launch_key', 'expired']]), records: new Map([
    ['expired', {createdAt: Date.now() - 900001, files: [file]}]
  ])});
  const expiredFiles = [];
  await expired.bridge.connect(files => expiredFiles.push(...files));
  assert.equal(expired.bridge.isFileLaunch(), false);
  assert.equal(expiredFiles.length, 0, 'Expired local handles are not opened.');

  const openGate = deferred();
  const race = bridgeEnvironment({openGate, session: new Map([['pse_pending_launch_key', 'race']]), records: new Map([
    ['race', {createdAt: Date.now(), files: [file]}]
  ])});
  const raced = [];
  const connected = race.bridge.connect(files => raced.push(...files));
  const second = {name: 'another.pse', getFile: async () => ({name: 'another.pse'})};
  await race.launch([second]);
  openGate.resolve();
  await connected;
  assert.equal(race.bridge.isFileLaunch(), true);
  assert.equal(raced.filter(item => item === file).length, 1);
  assert.equal(raced.filter(item => item === second).length, 1, 'New OS delivery during IDB restoration is not lost or duplicated.');

  const regularStartup = startupEnvironment();
  await regularStartup.context.initialiseApplicationStartup();
  assert.equal(regularStartup.context.state.mailboxStarted, true);
  assert.equal(regularStartup.calls.filter(call => call.folders).length, 1, 'Ordinary startup loads the mailbox normally.');

  const viewer = startupEnvironment({fileLaunch: true, href: 'https://pse.example/index.php?open_pse=1&other=keep#draft'});
  await viewer.context.initialiseApplicationStartup();
  assert.equal(viewer.context.state.mailboxStarted, false);
  assert.equal(viewer.calls.filter(call => call.folders).length, 0, 'Cold file viewer never loads folders or the inbox.');
  assert.equal(viewer.calls.filter(call => call.timer).length, 0, 'Cold file startup does not schedule updates over the local-file dialog.');
  assert.equal(viewer.elements.get('#fileLaunchMailboxHint').classes.has('d-none'), false);
  await viewer.context.startApplicationMailbox();
  assert.equal(viewer.context.state.mailboxStarted, true);
  assert.equal(viewer.calls.filter(call => call.folders).length, 1, 'The explicit mailbox action resumes normal mail loading.');
  assert.equal(viewer.context.location.href, 'https://pse.example/index.php?other=keep#draft', 'Resuming the mailbox removes the file-only URL marker while preserving other URL state.');

  const ready = deferred();
  const pendingStartup = startupEnvironment({ready: ready.promise});
  const bootstrap = pendingStartup.context.initialiseApplicationStartup();
  await tick();
  assert.equal(pendingStartup.calls.filter(call => call.folders).length, 0, 'Mailbox startup awaits retained launch restoration.');
  ready.resolve();
  await bootstrap;
  assert.equal(pendingStartup.calls.filter(call => call.folders).length, 1);

  const lateReady = deferred();
  const lateOptions = {ready: lateReady.promise, fileLaunch: false};
  const lateStartup = startupEnvironment(lateOptions);
  const lateBootstrap = lateStartup.context.initialiseApplicationStartup();
  lateOptions.fileLaunch = true;
  lateReady.resolve();
  await lateBootstrap;
  assert.equal(lateStartup.context.state.mailboxStarted, false, 'A file discovered during async restoration selects the file-only cold startup.');
  assert.equal(lateStartup.calls.filter(call => call.folders).length, 0);

  const loading = deferred();
  const concurrent = startupEnvironment({loadFolders: () => loading.promise});
  const firstStart = concurrent.context.startApplicationMailbox();
  const secondStart = concurrent.context.startApplicationMailbox();
  assert.equal(concurrent.calls.filter(call => call.folders).length, 1, 'Repeated Open mailbox actions share a single in-flight startup.');
  loading.resolve();
  await Promise.all([firstStart, secondStart]);

  const inactive = {
    console,
    state: {mailboxStarted: false, messages: [{uid: '7'}], folders: [{id: 'INBOX'}], prefetchGeneration: 3, prefetchPauseReasons: new Set()},
    document: {hidden: false}, navigator: {onLine: true},
    api: () => { throw new Error('A suspended file window attempted a mailbox API request.'); },
    prefetchConnectionAllowsBackground: () => true,
    setTimeout: () => { throw new Error('Suspended prefetch must not schedule a retry.'); }
  };
  vm.createContext(inactive);
  for (const [start, end] of [
    ['      function prefetchCanRun() {', '      function schedulePrefetchPump('],
    ['      async function scheduleVisibleMessagePrefetch(', '      async function promotePrefetchForForeground('],
    ['      async function pollFolderStatus() {', '      function renderPaginationControls()'],
    ['      async function flushActionQueue(', '      async function queueDeleteMessages(']
  ]) vm.runInContext(sourceBlock(start, end), inactive);
  assert.equal(inactive.prefetchCanRun(), false);
  await inactive.scheduleVisibleMessagePrefetch(3);
  await inactive.pollFolderStatus();
  assert.equal(await inactive.flushActionQueue(true), null, 'Background deletion processing remains suspended.');

  const background = backgroundEnvironment();
  for (const timer of background.timers) timer.callback();
  background.listeners.window.get('online')();
  background.listeners.connection.get('change')();
  background.listeners.document.get('visibilitychange')();
  background.listeners.document.get('hidden.bs.modal')();
  background.listeners.window.get('pagehide')();
  const mailCalls = () => background.calls.filter(call => call.prefetch || call.queue || call.poll || call.fetch || call.resume);
  assert.equal(mailCalls().length, 0, 'File-only timers, connectivity changes, dialog closure, visibility and pagehide do not contact the mailbox.');
  assert(background.calls.some(call => call.display), 'Refreshing the local last-sync label remains safe.');
  assert(background.calls.some(call => call.saveCompose), 'Closing a user-created reply may still save that draft.');

  background.context.state.mailboxStarted = true;
  for (const timer of background.timers) timer.callback();
  background.listeners.window.get('online')();
  background.listeners.connection.get('change')();
  background.listeners.document.get('visibilitychange')();
  background.listeners.window.get('pagehide')();
  assert(background.calls.some(call => call.queue), 'Normal mailbox timers process queued actions.');
  assert.equal(background.calls.filter(call => call.prefetch).length, 2);
  assert.equal(background.calls.filter(call => call.poll).length, 2);
  assert.equal(background.calls.filter(call => call.fetch).length, 1, 'Normal mailbox pagehide keeps the existing queue flush.');

  console.log('PSE file-launch tests passed: cold/late/warm launches, password retention, startup gating, explicit mailbox resume and background API suppression.');
}

run().catch(error => { console.error(error); process.exitCode = 1; });
