'use strict';

// Test-only dependency: npm install --no-save jsdom
// Run with NODE_PATH pointing at the directory containing jsdom, when necessary.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const block = (start, end) => {
  const first = source.indexOf(start);
  const last = source.indexOf(end, first + start.length);
  assert(first >= 0 && last > first, `Missing source block ${start}`);
  return source.slice(first, last).replace(/<\?=\s*PSE_MAX_ATTACHMENT_BYTES\s*\?>/g, '15728640');
};
const dom = new JSDOM('<!doctype html><body></body>', {url: 'https://pse.example/index.php', runScripts: 'outside-only'});
const window = dom.window;
window.Blob = Blob;
window.File = File;
const blobs = new Map();
window.URL.createObjectURL = blob => {
  const key = `blob:local-test/${blobs.size}`;
  blobs.set(key, blob);
  return key;
};
window.escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
window.eval([
  block('      function uniqueAddresses(items) {', '      function quotedMessageHtml(message) {'),
  block('      function composeAttachmentBlob(file) {', '      async function sha256Blob(blob) {'),
  block('      function sanitizeLocalPseHtml(html) {', '      function renderLocalPseFile(index) {'),
  'window.pseTest = {sanitizeLocalPseHtml, normalizeLocalPseRecord, localPseMessage};'
].join('\n'));
const api = window.pseTest;
const record = message => ({format: 'PSE/1', kind: 'message', id: 'existing-server-draft', message});
const valid = record({
  from: [{name: 'Alice', email: 'alice@example.com'}],
  replyTo: [{name: 'Helpdesk', email: 'help@example.com'}],
  to: [{name: 'Bob', email: 'bob@example.com'}],
  cc: [], bcc: [], subject: 'Portable message',
  bodyHtml: '<p style="color:red">Hello <b>Bob</b></p>', bodyText: 'Hello Bob',
  attachments: [{name: 'sample.txt', type: 'text/plain', data: 'aGVsbG8='}]
});
const normalized = api.normalizeLocalPseRecord(valid);
assert.equal(normalized.id, '', 'Local draft IDs cannot overwrite server drafts');
assert.equal(normalized.message.replyTo[0].email, 'help@example.com');
assert.equal(normalized.message.attachments[0].size, 5);
assert.equal(normalized.kind, 'message');
assert.match(normalized.message.bodyHtml, /<b>Bob<\/b>/);
assert.match(normalized.message.bodyHtml, /color: red/);
const localMessage = api.localPseMessage(normalized);
assert.equal(localMessage.replyTo[0].email, 'help@example.com');
assert.equal(localMessage.localPseAttachments[0].data, 'aGVsbG8=');
assert.equal(localMessage.attachments[0].size, 5);
assert.equal(blobs.get(localMessage.attachments[0].url).size, 5, 'Forward/download blobs include all attachment bytes');

const hostile = '<script>alert(1)</script><svg onload="alert(1)"><a href="javascript:alert(1)">x</a></svg>' +
  '<img src="https://tracker.example/pixel" onerror="alert(1)"><iframe src="https://attacker.example"></iframe>' +
  '<p id="composeBody" onclick="alert(1)" style="color:blue;background-image:url(https://tracker.example);position:fixed">Safe text</p>' +
  '<a href="javascript:alert(1)">Unsafe link</a><a href="https://example.com">Safe link</a>' +
  '<img src="data:image/svg+xml;base64,PHN2Zz4=" onerror="alert(1)"><form action="https://attacker.example"><input name="password"></form>';
const clean = api.sanitizeLocalPseHtml(hostile);
assert.doesNotMatch(clean, /<script|<svg|<iframe|<form|<input|onerror|onclick|javascript:|tracker\.example|attacker\.example|position:|background-image|id="composeBody"/i);
assert.match(clean, /Safe text/);
assert.match(clean, /external image blocked/);
assert.match(clean, /href="https:\/\/example.com"/);
assert.match(clean, /noopener noreferrer/);
assert.match(api.sanitizeLocalPseHtml('<img src="data:image/png;base64,aGVsbG8=" onerror="bad()">'), /src="data:image\/png/);
assert.doesNotMatch(api.sanitizeLocalPseHtml('<img src="data:image/png;base64,aGVsbG8=" onerror="bad()">'), /onerror/);

assert.throws(() => api.normalizeLocalPseRecord({format: 'PSE/2', message: {}}), /PSE\/1/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: {bad: true}})), /attachment list/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [{url: '?cached_attachment=token'}]})), /embedded file data/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [{data: 'a%%%'}]})), /attachment encoding/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [{data: 'a===', type: 'text/plain'}]})), /attachment encoding/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [{data: '', type: 'text/plain\r\nX-Header: value'}]})), /attachment type/);
assert.throws(() => api.normalizeLocalPseRecord(record({to: [{email: 'bob@example.com\r\nBcc: hidden@example.com'}]})), /email address/);
assert.throws(() => api.normalizeLocalPseRecord(record({bodyHtml: 'x'.repeat(2097153)})), /HTML body/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [{data: 'A'.repeat(20971524), type: 'text/plain'}]})), /attachment encoding/);
assert.throws(() => api.normalizeLocalPseRecord(record({attachments: [
  {data: 'A'.repeat(10485764), type: 'text/plain'},
  {data: 'A'.repeat(10485764), type: 'text/plain'}
]})), /15 MB/);
assert.equal(api.normalizeLocalPseRecord({format: 'PSE/1', message: {to: [], subject: 'Old draft'}}).kind, 'draft', 'Legacy PSE drafts remain supported');
const filename = api.normalizeLocalPseRecord(record({attachments: [{name: '../../secret.txt', type: 'text/plain', data: ''}]})).message.attachments[0].name;
assert.doesNotMatch(filename, /[\\/]/, 'Saved attachment names cannot contain paths');
const recipient = api.normalizeLocalPseRecord(record({to: [{name: 'Bob', email: 'bob@example.com'}, {name: 'BOB', email: 'BOB@example.com'}]}));
assert.equal(recipient.message.to.length, 1);
window.document.body.innerHTML = '<input id="composeSubject"><div id="composeBody"></div><div id="attachmentList"></div><button id="sendEmail"></button>';
window.state = {currentMessage: {from: [{email: 'wrong@example.com'}]}, recipients: {to: [], cc: [], bcc: []}, composeFiles: [], composeSession: 0};
window.initialSettings = {from_email: 'me@example.com', from_name: 'Me'};
window.$ = selector => window.document.querySelector(selector);
window.resetCompose = () => { window.state.composeSession++; window.state.recipients = {to: [], cc: [], bcc: []}; window.state.composeFiles = []; };
window.isSinglePaneMobileViewport = () => false;
window.renderRecipientChips = () => {};
window.setRecipientRowVisibility = () => {};
window.insertComposeSignature = () => {};
window.setDefaultComposeRange = () => {};
window.composeModal = {show: () => {}};
window.renderAttachmentList = () => {};
window.updateImageProgress = () => {};
window.setTimeout = () => 0;
window.handleError = error => { throw error; };
window.downloadForwardAttachments = () => { throw new Error('A local PSE forward must use embedded bytes, never fetch a server token.'); };
window.eval(block('      function addressText(items) {', '      function updateReadContactSelection() {'));
window.eval(block('      function quotedMessageHtml(message) {', '      function renderAttachmentList() {'));
(async () => {
  await window.replyToMessage('reply', localMessage);
  assert.equal(window.state.recipients.to[0].email, 'help@example.com', 'Local Reply uses the saved Reply-To');
  assert.equal(window.document.querySelector('#composeSubject').value, 'Re: Portable message');
  await window.replyToMessage('forward', localMessage);
  assert.equal(window.document.querySelector('#composeSubject').value, 'Fwd: Portable message');
  assert.equal(window.state.composeFiles.length, 1);
  assert.equal(await window.state.composeFiles[0].text(), 'hello', 'Forward keeps the exact embedded attachment');
  console.log('PSE local-file tests passed: reply/forward, portable attachments, reply-to, legacy drafts, safe HTML, invalid input and limits.');
})().catch(error => { console.error(error); process.exitCode = 1; });
