'use strict';

// Run the real icon selector and desktop switcher against disposable DOMs.
// Account switches are simulated API responses, never real mailbox operations.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {JSDOM} = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');

function block(start, end) {
  const first = source.indexOf(start);
  const last = source.indexOf(end, first + start.length);
  assert(first >= 0 && last > first, 'Missing production block: ' + start);
  return source.slice(first, last);
}
const markup = block('    <header class="pse-header', '    </header>') + '    </header>';
const production = [
  block('      function isSinglePaneMobileViewport()', '      function isSinglePaneMobileActive()'),
  block('      let mobileAccountSwitchPending = false;', '      function applySettingsToForm('),
  block('      installMobileAccountSwitcher();', "      $('#installUpdateNow').addEventListener(")
].join('\n');
const tick = () => new Promise(resolve => setImmediate(resolve));
const accounts = [
  {id:'personal',name:'Personal',type:'imap',username:'personal@example.test'},
  {id:'work',name:'Work',type:'gmail',google_email:'work@example.test'}
];
function deferred() {
  let resolve;
  const promise = new Promise(done => {resolve = done;});
  return {promise,resolve};
}

function environment(options = {}) {
  const dom = new JSDOM('<!doctype html><body>' + markup + '</body>', {
    url:'https://pse.example/index.php',runScripts:'outside-only'
  });
  const window = dom.window;
  Object.defineProperty(window,'innerWidth',{value:options.width ?? 390,writable:true});
  window.matchMedia = query => {
    assert.equal(query,'(max-width: 900px)');
    return {matches:window.innerWidth <= 900};
  };
  const settings = {
    account_id:'personal',account_name:'Personal',
    accounts:options.accounts ?? accounts
  };
  const events = {requests:[],reloads:0,errors:[]};
  const context = vm.createContext({
    window,document:window.document,initialSettings:settings,
    $:selector=>window.document.querySelector(selector),
    $$:(selector,root=window.document)=>Array.from(root.querySelectorAll(selector)),
    escapeHtml:value=>String(value ?? '').replace(/[&<>"']/g,char=>({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[char]),
    sessionStorage:options.storageBlocked ? {setItem(){throw new Error('Storage blocked.');}} : window.sessionStorage,
    location:{reload(){events.reloads++;}},
    handleError:error=>events.errors.push(error.message),
    api:async (action,payload,requestOptions)=>{
      assert.equal(action,'switch_account','Only simulated account switching is allowed.');
      events.requests.push({action,payload:JSON.parse(JSON.stringify(payload)),requestOptions});
      if (options.gate) await options.gate.promise;
      if (options.error) throw new Error(options.error);
      const account = settings.accounts.find(item=>String(item.id)===String(payload.account_id));
      assert(account,'The simulated account must exist.');
      return {settings:{account_id:account.id,account_name:account.name}};
    }
  });
  vm.runInContext(production,context,{filename:'production-account-switcher.js'});
  context.renderQuickAccountSwitcher(settings);
  const select = window.document.getElementById('mobileAccountSelect');
  const avatar = window.document.querySelector('.pse-brand-avatar');
  return {
    window,document:window.document,settings,events,context,select,avatar,
    async choose(id) {
      select.value = id;
      select.dispatchEvent(new window.Event('change',{bubbles:true}));
      await tick();
    },
    resize(width) {
      window.innerWidth = width;
      window.dispatchEvent(new window.Event('resize'));
    },
    close(){window.close();}
  };
}

async function run() {
  let scenarios = 0;
  for (const list of [[],accounts.slice(0,1)]) {
    const single = environment({accounts:list});
    assert.equal(single.select.hidden,true);
    assert.equal(single.select.disabled,true);
    assert.equal(single.avatar.classList.contains('switchable'),false);
    await single.choose('personal');
    assert.equal(single.events.requests.length,0,'The icon is decorative without multiple accounts.');
    single.close();scenarios++;
  }

  const mobile = environment();
  assert.equal(mobile.select.tagName,'SELECT','The icon uses a real native combo.');
  assert.equal(mobile.select.parentElement,mobile.avatar,'Tapping the icon targets its overlaid select.');
  assert.equal(mobile.avatar.getAttribute('aria-hidden'),null,'The picker is exposed to assistive technology.');
  assert.equal(mobile.select.hidden,false);
  assert.equal(mobile.select.disabled,false);
  assert.equal(mobile.avatar.classList.contains('switchable'),true);
  assert.deepEqual(Array.from(mobile.select.options,option=>option.value),['personal','work']);
  assert.equal(mobile.select.value,'personal','The current account is selected initially.');
  assert.match(mobile.select.getAttribute('aria-label'),/Switch email account.*Personal/);
  assert.match(mobile.select.options[1].textContent,/Work.*work@example.test/);
  mobile.select.focus();
  assert.equal(mobile.document.activeElement,mobile.select,'The native combo supports keyboard focus.');
  mobile.select.click();await tick();
  assert.equal(mobile.events.requests.length,0,'Opening or cancelling the picker never switches accounts.');
  scenarios++;
  await mobile.choose('personal');
  assert.equal(mobile.events.requests.length,0,'Re-selecting the active account needs no request.');
  assert.equal(mobile.events.reloads,0);
  scenarios++;
  await mobile.choose('work');
  assert.deepEqual(mobile.events.requests.map(event=>event.payload),[{account_id:'work'}]);
  assert.equal(mobile.events.requests[0].requestOptions.spinnerText,'Switching email account…');
  assert.equal(mobile.events.reloads,1,'Changing the native combo reuses the existing switch-and-reload path.');
  assert.equal(mobile.window.sessionStorage.getItem('pse_account_switched'),'Personal → Work');
  mobile.close();scenarios++;

  const tablet = environment({width:900});
  assert.equal(tablet.select.hidden,false,'The icon picker follows the existing mobile header breakpoint.');
  await tablet.choose('work');assert.equal(tablet.events.reloads,1);
  tablet.close();scenarios++;

  const desktop = environment({width:901});
  assert.equal(desktop.select.hidden,true);
  assert.equal(desktop.select.disabled,true);
  assert.equal(desktop.avatar.classList.contains('switchable'),false);
  await desktop.choose('work');
  assert.equal(desktop.events.requests.length,0,'Desktop ignores mobile combo changes.');
  const button = desktop.document.getElementById('activeAccountButton');
  const menu = desktop.document.getElementById('accountQuickMenu');
  assert.equal(button.disabled,false);
  button.click();
  assert.equal(menu.hidden,false,'The existing desktop badge still opens its menu.');
  desktop.document.dispatchEvent(new desktop.window.KeyboardEvent('keydown',{key:'Escape'}));
  assert.equal(menu.hidden,true);
  assert.equal(button.getAttribute('aria-expanded'),'false');
  button.click();
  menu.querySelector('[data-account-id="work"]').click();await tick();
  assert.equal(desktop.events.requests.length,1);
  assert.equal(desktop.events.reloads,1);
  assert.equal(menu.hidden,true);
  desktop.close();scenarios++;

  const resizing = environment({width:1200});
  resizing.resize(320);
  assert.equal(resizing.select.hidden,false);
  assert.equal(resizing.select.disabled,false);
  resizing.resize(1200);
  assert.equal(resizing.select.hidden,true);
  assert.equal(resizing.select.disabled,true);
  resizing.resize(390);
  assert.equal(resizing.select.value,'personal','Resizing preserves the selected account.');
  resizing.close();scenarios++;

  const failed = environment({error:'Simulated switch failure.'});
  await failed.choose('work');
  assert.equal(failed.events.reloads,0);
  assert.deepEqual(failed.events.errors,['Simulated switch failure.']);
  assert.equal(failed.select.value,'personal','A failed switch restores the active account.');
  assert.equal(failed.select.disabled,false,'The picker is available to retry after a failure.');
  failed.close();scenarios++;

  const gate = deferred();
  const pending = environment({gate});
  const first = pending.choose('work');
  assert.equal(pending.select.disabled,true,'A pending request prevents repeated selection.');
  pending.resize(1200);pending.resize(390);
  assert.equal(pending.select.disabled,true,'Resizing cannot enable a picker during a pending switch.');
  await pending.choose('personal');
  assert.equal(pending.events.requests.length,1);
  gate.resolve();await first;await tick();
  assert.equal(pending.events.reloads,1);
  assert.equal(pending.select.disabled,false);
  pending.close();scenarios++;

  const changed = environment();
  Object.assign(changed.settings,{accounts:[accounts[1]],account_id:'work',account_name:'Work'});
  changed.context.renderQuickAccountSwitcher(changed.settings);
  assert.equal(changed.select.value,'work');
  assert.equal(changed.select.options.length,1);
  assert.equal(changed.select.hidden,true,'Removing an account makes the icon decorative again.');
  assert.equal(changed.select.disabled,true);
  changed.resize(1200);changed.resize(390);
  assert.equal(changed.select.hidden,true);
  changed.close();scenarios++;

  const escaped = environment({accounts:[
    accounts[0],
    {id:'special"account',name:'<Work & family>',type:'imap',username:'<img src=x onerror=alert(1)>'}
  ]});
  assert.equal(escaped.select.options[1].value,'special"account');
  assert.equal(escaped.select.options[1].textContent,'<Work & family> — <img src=x onerror=alert(1)>');
  assert.equal(escaped.select.querySelector('img'),null,'Account metadata is text, never executable markup.');
  escaped.close();scenarios++;

  const blocked = environment({storageBlocked:true});
  await blocked.choose('work');
  assert.equal(blocked.events.reloads,1,'An unavailable session notification does not block switching.');
  assert.equal(blocked.events.errors.length,0);
  blocked.close();scenarios++;

  console.log('Mobile account picker: ' + scenarios + ' scenarios passed (native combo, account count, viewport changes, desktop menu, switching, errors and delayed responses).');
}

run().catch(error=>{console.error(error);process.exitCode=1;});
