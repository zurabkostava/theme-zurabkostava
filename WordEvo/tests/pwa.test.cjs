const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');

test('notification clicks ignore other apps and open the canonical Wordevo page', async () => {
    const handlers = {};
    const target = 'https://example.com/projects/wordevo/';
    let opened;
    let focused = false;
    let clients = [{ url: 'https://example.com/projects/reader/', focus() { throw Error('Wrong app'); } }];
    const self = {
        location: { href: 'https://example.com/wp-content/themes/test/WordEvo/sw.js?app=' + encodeURIComponent(target) },
        addEventListener: (name, handler) => { handlers[name] = handler; },
        clients: { matchAll: async () => clients, openWindow: async url => { opened = url; } }
    };
    vm.runInNewContext(source('sw.js'), { self, URL });
    let pending;
    const click = () => handlers.notificationclick({ notification: { close() {} }, waitUntil(promise) { pending = promise; } });
    click(); await pending;
    assert.equal(opened, target);
    clients.push({ url: target, focus() { focused = true; } });
    opened = undefined;
    click(); await pending;
    assert.equal(focused, true);
    assert.equal(opened, undefined);
});

test('installation reuses one worker registration and consumes each prompt once', async () => {
    const handlers = {};
    let registrations = 0;
    let prompts = 0;
    const button = { hidden: true, addEventListener(name, handler) { this[name] = handler; } };
    const window = {
        WORDEVO_ASSET_PATH: 'https://example.com/theme/WordEvo',
        WORDEVO_APP_URL: 'https://example.com/projects/wordevo/',
        matchMedia: () => ({ matches: false }),
        addEventListener: (name, handler) => { handlers[name] = handler; }
    };
    vm.runInNewContext(source('pwa.js'), {
        window, URL, console, location: { href: window.WORDEVO_APP_URL },
        document: { getElementById: () => button },
        navigator: { serviceWorker: { register: async url => {
            registrations++;
            assert.equal(new URL(url).searchParams.get('app'), window.WORDEVO_APP_URL);
            return {};
        } } }
    });
    await window.registerWordevoWorker();
    assert.equal(registrations, 1);
    handlers.beforeinstallprompt({ preventDefault() {}, prompt: async () => { prompts++; }, userChoice: Promise.resolve({ outcome: 'accepted' }) });
    assert.equal(button.hidden, false);
    await button.click(); await button.click();
    assert.equal(prompts, 1);
    assert.equal(button.hidden, true);
});
