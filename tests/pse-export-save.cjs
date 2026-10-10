'use strict';

// Exercise the production save flow with fake file handles and attachment bytes.
// Record sanitization is covered separately by local-pse-files.cjs.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');
function block(start, end) {
  const first = source.indexOf(start);
  const last = source.indexOf(end, first + start.length);
  assert(first >= 0 && last > first, `Missing production block: ${start}`);
  return source.slice(first, last);
}
const saving = block('      function exportFileBase()', '      function normalizeAttachmentFilename(');
const portable = block('      function portablePseFilename(', "      $('#openLocalPse').addEventListener(");
let scenarios = 0;

function fixture(options = {}) {
  const events = [];
  const downloads = [];
  const writes = [];
  const errors = [];
  let activeGesture = true;
  const attachments = options.attachments ?? [{name: 'hello.txt', type: 'text/plain', bytes: Buffer.from('hello')}];
  const message = {
    subject: 'Fixture mail', html: '<p>Hello</p>', text: 'Hello',
    attachments: attachments.map((item, index) => ({url: `fixture:attachment-${index}`, filename: item.name, type: item.type}))
  };
  const draft = {
    subject: options.subject ?? 'Edited draft',
    attachments: attachments.map(item => ({name: item.name, type: item.type, data: Buffer.from(item.bytes).toString('base64')}))
  };
  const window = {isSecureContext: options.secure !== false};
  if (options.native !== false) window.showSaveFilePicker = config => {
    events.push({kind: 'picker', config});
    assert(activeGesture, 'Save As must open before any asynchronous attachment/draft preparation.');
    if (options.pickerError) return Promise.reject(options.pickerError);
    return Promise.resolve({
      name: 'Chosen.pse',
      createWritable: async () => {
        events.push({kind: 'writable'});
        return {
          write: async blob => {
            if (options.writeError) throw options.writeError;
            writes.push(blob);
          },
          close: async () => events.push({kind: 'close'})
        };
      }
    });
  };
  const context = {
    Blob, JSON, Date, String, window,
    console: {warn: (...args) => events.push({kind: 'warning', args})},
    state: {currentMessage: message},
    initialSettings: {version: '2.18.5'},
    localPseMaxFileBytes: options.maxBytes ?? 24 * 1024 * 1024,
    $: selector => {
      assert.equal(selector, '#composeSubject');
      return {value: options.subject ?? draft.subject};
    },
    activeSwalTarget: () => undefined,
    Swal: {fire: async config => {
      events.push({kind: 'fallback', config});
      return options.cancelFallback ? {isConfirmed: false} : {isConfirmed: true, value: options.filename ?? 'Portable backup'};
    }},
    normalizeLocalPseRecord: record => structuredClone(record),
    downloadBlob: (blob, filename) => downloads.push({blob, filename}),
    downloadForwardAttachments: async items => {
      events.push({kind: 'attachments', attachments: items});
      return attachments.map(item => new File([item.bytes], item.name, {type: item.type}));
    },
    blobAsDataUrl: async file => `data:${file.type};base64,${Buffer.from(await file.arrayBuffer()).toString('base64')}`,
    composePayload: async () => {
      events.push({kind: 'compose'});
      if (options.composeError) throw options.composeError;
      return draft;
    },
    showSpinner: () => events.push({kind: 'spinner'}),
    hideSpinner: () => events.push({kind: 'hide-spinner'}),
    updateImageProgress: () => {},
    toast: text => events.push({kind: 'toast', text}),
    handleError: error => errors.push(error)
  };
  vm.createContext(context);
  vm.runInContext(saving + portable, context);
  return {context, events, downloads, writes, errors, message, endGesture: () => { activeGesture = false; }};
}

async function perform(f, draft = false) {
  const pending = draft ? f.context.downloadComposePse() : f.context.exportCurrentPse();
  f.endGesture();
  await pending;
}

async function run() {
  const nativeMessage = fixture();
  await perform(nativeMessage);
  assert.equal(nativeMessage.events[0].kind, 'picker');
  const picker = nativeMessage.events[0].config;
  assert.equal(picker.suggestedName, 'Fixture_mail.pse');
  assert.equal(picker.id, 'pse-email-export-pse');
  assert.equal(picker.excludeAcceptAllOption, true);
  assert.equal(picker.types[0].accept['application/vnd.pse.email+json'][0], '.pse');
  assert.equal(nativeMessage.writes.length, 1);
  assert.equal(nativeMessage.writes[0].type, 'application/vnd.pse.email+json');
  const record = JSON.parse(await nativeMessage.writes[0].text());
  assert.equal(record.format, 'PSE/1');
  assert.equal(record.kind, 'message');
  assert.equal(record.message.bodyHtml, '<p>Hello</p>');
  assert.equal(record.message.attachments[0].data, 'aGVsbG8=');
  assert(nativeMessage.events.some(event => event.kind === 'close'));
  assert.equal(nativeMessage.downloads.length, 0);
  assert.equal(nativeMessage.errors.length, 0);
  scenarios++;

  const nativeDraft = fixture({subject: 'New / edited draft'});
  await perform(nativeDraft, true);
  assert.equal(nativeDraft.events[0].kind, 'picker');
  assert.equal(nativeDraft.events[0].config.suggestedName, 'New_edited_draft.pse');
  assert(nativeDraft.events.findIndex(event => event.kind === 'compose') > 0);
  const draft = JSON.parse(await nativeDraft.writes[0].text());
  assert.equal(draft.kind, 'draft');
  assert.equal(draft.message.subject, 'New / edited draft');
  assert.equal(draft.message.attachments[0].data, 'aGVsbG8=');
  scenarios++;

  const attachedFiles = [
    {name: 'notes.txt', type: 'text/plain', bytes: Buffer.from('Hello, portable email!\n')},
    {name: 'binary.bin', type: 'application/octet-stream', bytes: Buffer.from(Array.from({length: 256}, (_, index) => index))}
  ];
  for (const isDraft of [false, true]) {
    for (const native of [false, true]) {
      const multi = fixture({native, attachments: attachedFiles});
      await perform(multi, isDraft);
      assert.equal(multi.writes.length + multi.downloads.length, 1, 'Multiple attachments must produce exactly one PSE file.');
      assert.equal(multi.errors.length, 0);
      const saved = JSON.parse(await (multi.writes[0] ?? multi.downloads[0].blob).text());
      assert.equal(saved.format, 'PSE/1');
      assert.equal(saved.kind, isDraft ? 'draft' : 'message');
      assert.equal(saved.message.attachments.length, attachedFiles.length);
      saved.message.attachments.forEach((item, index) => {
        assert.equal(item.name, attachedFiles[index].name);
        assert.equal(item.type, attachedFiles[index].type);
        assert.deepEqual(Buffer.from(item.data, 'base64'), attachedFiles[index].bytes, 'All attachment bytes must be embedded in the single file.');
        assert.equal(item.url, undefined, 'An attachment cannot depend on an external download link.');
      });
      assert.equal(multi.events.filter(event => event.kind === 'close').length, native ? 1 : 0);
      scenarios++;
    }
  }

  for (const isDraft of [false, true]) {
    for (const native of [false, true]) {
      const cancelled = fixture({native, cancelFallback: true, pickerError: new DOMException('Cancelled', 'AbortError')});
      await perform(cancelled, isDraft);
      assert(!cancelled.events.some(event => ['attachments', 'compose', 'writable', 'spinner'].includes(event.kind)));
      assert.equal(cancelled.writes.length + cancelled.downloads.length + cancelled.errors.length, 0);
      if (native) assert(!cancelled.events.some(event => event.kind === 'fallback'));
      scenarios++;
    }
  }

  for (const options of [{native: false}, {secure: false}, {pickerError: new DOMException('Denied', 'SecurityError')}]) {
    const fallback = fixture(options);
    await perform(fallback);
    assert.equal(fallback.downloads.length, 1);
    assert.equal(fallback.downloads[0].filename, 'Portable backup.pse');
    assert.equal(JSON.parse(await fallback.downloads[0].blob.text()).kind, 'message');
    assert.equal(fallback.writes.length + fallback.errors.length, 0);
    if (options.secure === false) assert(!fallback.events.some(event => event.kind === 'picker'));
    scenarios++;
  }

  const draftFallback = fixture({native: false, subject: '', filename: 'Renamed.PSE'});
  await perform(draftFallback, true);
  assert.equal(draftFallback.events[0].config.inputValue, 'draft.pse');
  assert.equal(draftFallback.downloads[0].filename, 'Renamed.PSE');
  assert.equal(JSON.parse(await draftFallback.downloads[0].blob.text()).kind, 'draft');
  scenarios++;

  for (const options of [{writeError: new Error('Disk full')}, {maxBytes: 1}, {composeError: new Error('Attachment preparation failed')}]) {
    const failure = fixture(options);
    await perform(failure, Boolean(options.composeError));
    assert.equal(failure.errors.length, 1);
    assert.equal(failure.downloads.length, 0, 'Failed native writes must not silently download to another folder.');
    if (!options.writeError) assert.equal(failure.writes.length, 0);
    scenarios++;
  }

  const missingAttachment = fixture();
  missingAttachment.message.attachments = [{filename: 'missing.txt'}];
  await perform(missingAttachment);
  assert.equal(missingAttachment.errors.length, 1);
  assert(!missingAttachment.events.some(event => event.kind === 'attachments' || event.kind === 'writable'));
  scenarios++;

  const pdf = fixture();
  await pdf.context.chooseExportDestination('pdf');
  assert.equal(pdf.events[0].config.suggestedName, 'Fixture_mail.pdf');
  assert.equal(pdf.events[0].config.types[0].accept['application/pdf'][0], '.pdf');
  assert.equal(pdf.events[0].config.excludeAcceptAllOption, false);
  scenarios++;
  console.log(`PSE Save As: ${scenarios} scenarios passed (messages/drafts, user activation, cancellation, fallback, attachment bytes and errors).`);
}

run().catch(error => { console.error(error); process.exitCode = 1; });
