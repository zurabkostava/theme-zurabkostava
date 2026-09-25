const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, 'scriptreader.js'), 'utf8');

test('ReadRoad ignores canceled Piper output and errors after switching sentences', async () => {
    let worker;
    const context = vm.createContext({
        piperWorkers: {}, piperRequestId: 0, console,
        Worker: class { constructor() { worker = this; } postMessage() {} terminate() {} },
        getPiperWorkerUrl: () => '/worker.js', setTtsStatus() {},
        localStorage: { setItem() {} }, document: { getElementById: () => null },
        isNatiaVoice: () => true, stopReading: () => assert.fail('stale error stopped new playback'),
        setTimeout() {}
    });
    vm.runInContext(source.slice(source.indexOf('function initPiperWorker('), source.indexOf('// \'ka_GE\'')), context);
    vm.runInContext(source.slice(source.indexOf('function piperSynthesize('), source.indexOf('function waitForPiperReady(')), context);
    vm.runInContext("initPiperWorker('ka', 'voice')", context);
    worker.onmessage({ data: { kind: 'ready' } });
    const old = vm.runInContext("piperSynthesize(piperWorkers.ka, 'ძველი', 1.5)", context);
    vm.runInContext('stopPiperAudio()', context);
    await assert.rejects(old, /Stopped/);
    const next = vm.runInContext("piperSynthesize(piperWorkers.ka, 'ახალი', 1.5)", context);
    worker.onmessage({ data: { kind: 'output', requestId: 1, wav: 'old' } });
    worker.onmessage({ data: { kind: 'error', requestId: 1, message: 'late error' } });
    assert.equal(context.piperWorkers.ka.pending.length, 1);
    worker.onmessage({ data: { kind: 'output', requestId: 2, wav: 'new' } });
    assert.equal(await next, 'new');
});

test('ReadRoad paused Piper stays resumable; stop/seek settles playback', async () => {
    let guard, audio;
    const context = vm.createContext({
        URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} },
        Audio: class { constructor() { audio = this; } play() { return Promise.resolve(); } pause() {} },
        setInterval: callback => { guard = callback; return 1; }, clearInterval() {},
        runWordHighlights() {}, playbackToken: 1, isPlaying: true, state: {}
    });
    vm.runInContext(source.slice(source.indexOf('function playPiperAudio('), source.indexOf('// Classifies a sentence')), context);
    const playback = vm.runInContext('playPiperAudio(state, {}, 1.5, {}, 1)', context);
    context.isPlaying = false;
    guard();
    assert.equal(context.state.currentAudio, audio);
    assert.equal(audio.playbackRate, 1);
    context.playbackToken++;
    guard();
    await playback;
    assert.equal(context.state.currentAudio, null);
});
