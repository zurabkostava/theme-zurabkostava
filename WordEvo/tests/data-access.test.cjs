const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { readAll, latestWriter } = require('../data-access.js');

test('loads all 2,001 words and 5,500 tag relations even with a lower server cap', async () => {
    for (const total of [0, 41, 500, 2000, 2001, 5500]) {
        const rows = Array.from({ length: total }, (_, id) => ({ id }));
        let calls = 0;
        const result = await readAll(() => ({ range: async (from, to) => {
            calls++;
            return { data: rows.slice(from, Math.min(to + 1, from + 300)), error: null };
        } }));
        assert.deepEqual(result, rows);
        assert.equal(calls, Math.ceil(total / 300) + 1);
    }
});

test('a failed page rejects the whole load, never presenting a partial dictionary', async () => {
    let calls = 0;
    await assert.rejects(readAll(() => ({ range: async () => ++calls === 1
        ? { data: [{ id: 1 }] } : { error: new Error('unavailable') } })), /unavailable/);
    assert.equal(calls, 2);
});

test('two quiz counters in the same turn produce one complete write', async () => {
    const sent = [];
    const writer = latestWriter(async value => { sent.push(value); }, assert.fail);
    const first = writer.enqueue('stats:user-a', { tests: 1, correct: 0 });
    const second = writer.enqueue('stats:user-a', { tests: 1, correct: 1 });
    assert.deepEqual(await Promise.all([first, second]), [true, true]);
    assert.deepEqual(sent, [{ tests: 1, correct: 1 }]);
});

test('slow writes remain serialized and a later progress value wins', async () => {
    const sent = [];
    let release;
    const blocked = new Promise(done => { release = done; });
    const writer = latestWriter(async value => {
        sent.push(value);
        if (sent.length === 1) await blocked;
    }, assert.fail);
    writer.enqueue('card-a', 1);
    await Promise.resolve();
    const second = writer.enqueue('card-a', 2);
    const third = writer.enqueue('card-a', 3);
    const other = writer.enqueue('card-b', 10);
    assert.deepEqual(sent, [1]);
    release();
    await writer.flush();
    assert.deepEqual(sent, [1, 3, 10]);
    assert.deepEqual(await Promise.all([second, third, other]), [true, true, true]);
});

test('Supabase returned errors are visible and do not cause an automatic retry storm', async () => {
    let calls = 0;
    const errors = [];
    const writer = latestWriter(async () => { calls++; return { error: new Error('read only') }; }, e => errors.push(e.message));
    assert.equal(await writer.enqueue('a', 1), false);
    await writer.flush();
    assert.equal(calls, 1);
    assert.deepEqual(errors, ['read only']);
    assert.equal(await writer.enqueue('a', 2), false);
    assert.equal(calls, 2);
});

test('100% progress and rounded no-op changes do not write, real changes still do', async () => {
    const writes = [];
    const context = vm.createContext({
        WordevoData: { latestWriter }, currentUser: { id: 'user-a' }, console,
        showToast: assert.fail,
        supabaseClient: { from: () => ({ update: value => ({ eq: () => ({ eq: async () => {
            writes.push(value); return { error: null };
        } }) }) }) }
    });
    vm.runInContext(fs.readFileSync(require.resolve('../utils.js'), 'utf8'), context);
    const card = { dataset: { id: 'card-a', progress: '100' }, classList: { add() {}, remove() {} }, querySelector: () => null };
    context.updateCardProgress(card, 1);
    await Promise.resolve();
    assert.equal(writes.length, 0);
    context.updateCardProgress(card, -1);
    await vm.runInContext('progressWriter.flush()', context);
    assert.equal(writes.length, 1);
    assert.equal(writes[0].progress, 99);
    context.updateCardProgress(card, 0.01);
    await Promise.resolve();
    assert.equal(writes.length, 1);
});
