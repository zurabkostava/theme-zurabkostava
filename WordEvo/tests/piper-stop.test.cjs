const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../tts.js'), 'utf8');
const start = source.indexOf('function initPiperWorker(');
const end = source.indexOf('async function speakWithVoice(', start);

test('Stop cancels Piper playback and queued synthesis before it can restart', async () => {
    const workers = [];
    let canceled = 0;
    let paused = 0;
    class Worker {
        constructor() { this.handlers = {}; this.messages = []; this.terminated = false; workers.push(this); }
        addEventListener(type, handler) { this.handlers[type] = handler; }
        postMessage(message) { this.messages.push(message); }
        terminate() { this.terminated = true; }
        emit(message) { this.handlers.message({ data: message }); }
    }
    class Audio {
        play() { return Promise.resolve(); }
        pause() { paused++; }
    }
    const state = { worker: null, ready: false, initializing: false, queue: [], pendingCallbacks: [], currentAudio: null, voicePath: null };
    const context = vm.createContext({
        Worker, Audio, URL: { createObjectURL: () => 'blob:sample', revokeObjectURL() {} },
        window: { WORDEVO_ASSET_PATH: '/theme/WordEvo', speechSynthesis: { cancel() { canceled++; } } },
        piperWorkers: { lang1: state }, piperRequestId: 0, ttsGeneration: 0,
        speechSynthesis: { cancel() { canceled++; } }, setPiperStatus() {}, console
    });
    vm.runInContext(source.slice(start, end), context);

    vm.runInContext("initPiperWorker('lang1', 'voice')", context);
    workers[0].emit({ kind: 'ready' });
    const first = vm.runInContext("speakWithPiper('first', 1, 'lang1')", context);
    workers[0].emit({ kind: 'output', requestId: 1, wav: {} });
    await Promise.resolve();
    assert.ok(state.currentAudio);

    const queued = vm.runInContext("speakWithPiper('queued', 1, 'lang1')", context);
    vm.runInContext('stopAllTTS(true)', context);
    await assert.rejects(first, /stopped/);
    await assert.rejects(queued, /stopped/);
    assert.equal(paused, 1);
    assert.equal(canceled, 1);
    assert.equal(state.currentAudio, null);
    assert.equal(state.pendingCallbacks.length, 0);
    assert.equal(workers[0].terminated, true);
    workers[0].emit({ kind: 'output', requestId: 2, wav: {} });
    assert.equal(state.currentAudio, null);
});

test('late output from canceled request does not satisfy new playback', async () => {
    const workers = [];
    class Worker {
        constructor() { this.handlers = {}; this.messages = []; workers.push(this); }
        addEventListener(type, handler) { this.handlers[type] = handler; }
        postMessage(message) { this.messages.push(message); }
        emit(message) { this.handlers.message({ data: message }); }
    }
    class Audio { play() { return Promise.resolve(); } pause() {} }
    const state = { worker: null, ready: false, initializing: false, queue: [], pendingCallbacks: [], currentAudio: null, voicePath: null };
    const context = vm.createContext({
        Worker, Audio, URL: { createObjectURL: () => 'blob:sample', revokeObjectURL() {} },
        window: { WORDEVO_ASSET_PATH: '/theme/WordEvo', speechSynthesis: { cancel() {} } },
        piperWorkers: { lang1: state }, piperRequestId: 0, ttsGeneration: 0,
        speechSynthesis: { cancel() {} }, setPiperStatus() {}, console
    });
    vm.runInContext(source.slice(start, end), context);
    vm.runInContext("initPiperWorker('lang1', 'voice')", context);
    workers[0].emit({ kind: 'ready' });
    const old = vm.runInContext("speakWithPiper('old', 1, 'lang1')", context);
    vm.runInContext('stopAllTTS()', context);
    await assert.rejects(old, /stopped/);
    const next = vm.runInContext("speakWithPiper('new', 1, 'lang1')", context);
    workers[0].emit({ kind: 'output', requestId: 1, wav: {} });
    assert.equal(state.currentAudio, null);
    workers[0].emit({ kind: 'output', requestId: 2, wav: {} });
    assert.ok(state.currentAudio);
    state.currentAudio.onended();
    await next;
});
