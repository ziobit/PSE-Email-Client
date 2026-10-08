'use strict';

// Compile every production inline script in each server-rendered view, without
// executing scripts, loading CDN assets, starting a server, or using a mailbox.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = JSON.parse(process.env.PSE_TEST_PHP_COMMAND || '["php", "-n"]');
let count = 0;

for (const mode of ['setup', 'login', 'authenticated', 'service-worker']) {
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'pse-render-'));
  try {
    const file = path.join(temporary, 'index.php');
    fs.copyFileSync(path.join(root, 'index.php'), file);
    const output = execFileSync(php[0], [...php.slice(1), path.join(__dirname, 'fixtures/render-client.php'), file, mode], {
      encoding: 'utf8', timeout: 15000, maxBuffer: 4 * 1024 * 1024
    });
    if (mode === 'service-worker') {
      assert.match(output, /self\.addEventListener\('install'/, 'Production service worker must render.');
      new vm.Script(output, {filename: 'index.php:service-worker'});
      count++;
      continue;
    }
    assert.match(output, /<!doctype html>/i, `${mode} must render HTML.`);
    assert.match(output, new RegExp(`const authenticated = ${mode === 'authenticated' ? 'true' : 'false'};`));
    if (mode === 'authenticated') assert.match(output, /id="messagesList"/, 'The mailbox view must render.');
    const scripts = [...output.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi)]
      .filter(match => !/\bsrc\s*=/i.test(match[1]));
    assert(scripts.length > 0, `${mode} must contain inline JavaScript.`);
    for (const [index, script] of scripts.entries()) {
      assert(!script[2].includes('<?'), 'PHP expressions must be rendered before parsing.');
      new vm.Script(script[2], {filename: `index.php:${mode}:script-${index + 1}`});
      count++;
    }
  } finally {
    fs.rmSync(temporary, {recursive: true, force: true});
  }
}
console.log(`PASS: ${count} rendered JavaScript blocks (setup, login, mailbox and PWA service worker).`);
