const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../script.js'), 'utf8');
const preview = source.slice(source.indexOf('async function speakPreviewCard('), source.indexOf('function saveTextareaToLocalStorage('));

for (const action of ['stop', 'next']) {
    test(`WordEvo ${action} during a word never starts its old translation or examples`, async () => {
        const spoken = [], progress = [];
        const context = vm.createContext({
            autoPlayRunId: 1, stopRequested: false,
            selectedVoice: {}, selectedGeorgianVoice: {},
            window: { ProgressConfig: {} },
            localStorage: { getItem: () => null },
            document: { getElementById: () => null, querySelectorAll: () => [] },
            delay: async () => {}, updateWordevoMediaMetadata() {},
            updateCardProgress: (...args) => progress.push(args),
            speakWithVoice: async text => {
                spoken.push(text);
                context.autoPlayRunId++;
                context.stopRequested = action === 'stop';
            },
            card: {
                dataset: { id: '1', english: '["example"]', georgian: '["მაგალითი"]', mnemonic: 'mnemonic' },
                querySelector: selector => selector === '.word' ? { textContent: 'word' } :
                    { childNodes: [{ textContent: 'სიტყვა' }], querySelector: () => null }
            }
        });
        vm.runInContext(preview, context);
        await vm.runInContext('speakPreviewCard(card)', context);
        assert.deepEqual(spoken, ['word']);
        assert.equal(progress.length, 0);
    });
}
