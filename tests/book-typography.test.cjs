const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const file of ['script-book.js', 'script-book.min.js']) {
    test(`${file}: Georgian uppercase preserves Latin labels and numbers`, () => {
        const source = fs.readFileSync(path.join(__dirname, '../book-engine', file), 'utf8').replace(/\r\n/g, '\n');
        const start = source.indexOf('function bookGeorgianUppercase(');
        const end = source.indexOf('\n}\n', start) + 3;
        const context = vm.createContext({});
        vm.runInContext(source.slice(start, end), context);
        assert.equal(vm.runInContext('bookGeorgianUppercase("სარჩევი")', context), 'ᲡᲐᲠᲩᲔᲕᲘ');
        assert.equal(vm.runInContext('bookGeorgianUppercase("LOG_001: [საწყისი] 302 კმ")', context), 'LOG_001: [ᲡᲐᲬᲧᲘᲡᲘ] 302 ᲙᲛ');
        assert.equal(vm.runInContext('bookGeorgianUppercase("ᲡᲐᲠᲩᲔᲕᲘ")', context), 'ᲡᲐᲠᲩᲔᲕᲘ');
        assert.match(source, /li.dataset.sourceTitle = labelText/);
    });
    test(`${file}: Georgian pagination waits for newly requested font subsets`, async () => {
        const source = fs.readFileSync(path.join(__dirname, '../book-engine', file), 'utf8').replace(/\r\n/g, '\n');
        const start = source.indexOf('async function waitForBookTypography()');
        const end = source.indexOf('\n}\n', start) + 3;
        const calls = [];
        const pending = [];
        let ready = false;
        let painted = false;
        const context = vm.createContext({
            currentLanguage: 'ka',
            document: { fonts: {
                load: (font, text) => {
                    calls.push({ font, text });
                    return new Promise(resolve => pending.push(resolve));
                },
                get ready() { ready = true; return Promise.resolve(); }
            } },
            requestAnimationFrame: callback => { painted = true; callback(); }
        });
        vm.runInContext(source.slice(start, end), context);
        const promise = vm.runInContext('waitForBookTypography()', context);
        assert.equal(calls.length, 8);
        assert.ok(calls.some(call => call.font.includes('Noto Serif Georgian')));
        assert.ok(calls.some(call => call.font.includes('Noto Sans Georgian')));
        assert.ok(calls.every(call => call.text.includes('ქართული') && call.text.includes('ᲡᲐᲠᲩᲔᲕᲘ')));
        assert.equal(ready, false);
        assert.equal(painted, false);
        pending.forEach(resolve => resolve([]));
        await promise;
        assert.equal(ready, true);
        assert.equal(painted, true);
        assert.match(source, /await waitForBookTypography\(\);/);
    });
}
