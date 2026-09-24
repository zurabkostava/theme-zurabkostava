const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { stripTypeScriptTypes } = require('node:module');

async function run(options = {}) {
    let handler;
    const calls = [];
    const db = {
        from(table) {
            const query = {
                select() { return this; }, eq() { return this; }, contains() { return this; },
                lt() { return this; }, ilike() { return this; },
                delete() { this.op = 'delete'; return this; },
                insert(value) { this.op = 'insert'; this.value = value; return this; },
                then(resolve, reject) {
                    calls.push({ table, op: this.op || 'select', value: this.value });
                    let data = [];
                    let error = null;
                    if (table === 'notification_schedules') data = options.noSchedules ? [] : [{ id: 's1', user_id: 'u1' }];
                    if (table === 'push_subscriptions' && !this.op) data = options.noSubs ? [] : [{ id: 'sub1', endpoint: 'https://push.example.test/a' }];
                    if (table === 'push_queue' && this.op === 'insert' && options.queueError) error = { message: 'read only' };
                    return Promise.resolve({ data, error }).then(resolve, reject);
                }
            };
            return query;
        },
        async rpc(name, params) {
            calls.push({ rpc: name, params });
            return options.cardError ? { error: { message: 'unavailable' } }
                : { data: { word: 'test', main_translations: ['translation'] } };
        }
    };
    let source = fs.readFileSync(require.resolve('../supabase/functions/send-push/index.ts'), 'utf8');
    source = source.replace(/^import .*createClient.*\r?\n/m, '');
    const ctx = vm.createContext({
        createClient: () => db, Deno: { env: { get: () => 'unused' }, serve: f => { handler = f; } },
        Response, URL, Date, AbortSignal, console: { log() {}, error() {} }
    });
    vm.runInContext(stripTypeScriptTypes(source), ctx);
    vm.runInContext('sendPush = async endpoint => recordPush(endpoint)', Object.assign(ctx, { recordPush: endpoint => calls.push({ push: endpoint }) }));
    const result = await handler(new Request('https://example.test'));
    return { calls, body: await result.json() };
}

test('an idle minute only checks schedules', async () => {
    const { calls } = await run({ noSchedules: true });
    assert.deepEqual(calls.map(c => c.table), ['notification_schedules']);
});
test('no device means no word/progress query', async () => {
    const { calls } = await run({ noSubs: true });
    assert.equal(calls.some(c => c.rpc || c.push), false);
});
test('a reminder selects one word by RPC, saves its content, then sends push', async () => {
    const { calls, body } = await run();
    assert.equal(calls.filter(c => c.rpc).length, 1);
    assert.equal(calls.some(c => c.table === 'cards' || c.table === 'card_tags'), false);
    const queued = calls.find(c => c.op === 'insert');
    assert.equal(queued.value.title, 'test');
    assert.equal(queued.value.body, 'translation');
    assert.equal(body.sent, 1);
});
test('queue or card errors never send a misleading push', async () => {
    for (const options of [{ queueError: true }, { cardError: true }]) {
        const { calls, body } = await run(options);
        assert.equal(calls.some(c => c.push), false);
        assert.equal(body.errors, 1);
    }
});
