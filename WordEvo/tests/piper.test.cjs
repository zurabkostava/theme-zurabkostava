const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../piper-worker.js'), 'utf8');

test('Piper browser runtime is pinned to the clean v1.0.0 model and deterministic ORT', () => {
    assert.match(source, /onnxruntime-web\/1\.18\.0/);
    assert.match(source, /piper-voices\/resolve\/v1\.0\.0/);
    assert.match(source, /numThreads = 1/);
    assert.match(source, /piper-models-cache-v2/);
});

test('Piper changes model timing instead of stretching generated audio', async () => {
    const messages = [];
    const tensors = {};
    const ctx = vm.createContext({
        phonemize: async () => [1, 2, 3],
        modelConfig: { audio: { sample_rate: 22050 }, inference: { noise_scale: 0.667, length_scale: 1, noise_w: 0.8 } },
        ort: {
            Tensor: function (type, data) { this.data = data; this.type = type; },
        },
        ortSession: { run: async feeds => { Object.assign(tensors, feeds); return { output: { data: new Float32Array([0.1]) } }; } },
        PCM2WAV: () => ({}),
        self: { postMessage: message => messages.push(message) }
    });
    const start = source.indexOf('async function synthesize(');
    const end = source.indexOf('let queue =', start);
    vm.runInContext(source.slice(start, end), ctx);
    await vm.runInContext("synthesize('გამარჯობა', 7, 1.5)", ctx);
    assert.ok(Math.abs(tensors.scales.data[1] - 2 / 3) < 0.00001);
    assert.equal(messages[0].requestId, 7);
});

test('Piper PCM conversion preserves quiet amplitude without automatic gain', async () => {
    const ctx = vm.createContext({ Blob, self: { addEventListener() {} } });
    vm.runInContext(source, ctx);
    const wav = await vm.runInContext('PCM2WAV(new Float32Array([0.1, -0.1, 2]), 22050)', ctx).arrayBuffer();
    const view = new DataView(wav);
    assert.ok(Math.abs(view.getInt16(44, true) - 3276) <= 1);
    assert.equal(view.getInt16(48, true), 32767);
});

test('unknown download size reports bytes and a full cache never repeats download', async () => {
    const messages = [];
    let downloads = 0;
    const ctx = vm.createContext({
        Blob, Response, console, navigator: {}, importScripts() {},
        self: { addEventListener() {}, postMessage: msg => messages.push(msg) },
        createPiperPhonemize: async () => ({}),
        ort: { env: { wasm: {} }, InferenceSession: { create: async () => ({}) } },
        caches: { open: async () => ({ match: async () => null, put: async () => { throw Error('Quota'); } }) },
        fetch: async url => {
            if (url.endsWith('.json')) return { ok: true, json: async () => ({}) };
            downloads++;
            return new Response(new Uint8Array([1, 2, 3]));
        }
    });
    vm.runInContext(source, ctx);
    await vm.runInContext("init('test')", ctx);
    assert.equal(downloads, 1);
    assert.ok(messages.some(m => m.kind === 'progress' && m.info.loaded === 3 && m.info.total === 0));
    assert.ok(messages.some(m => m.kind === 'cache-warning'));
    assert.equal(messages.at(-1).kind, 'ready');
});
