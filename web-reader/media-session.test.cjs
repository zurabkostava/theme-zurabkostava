const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, 'scriptreader.js'), 'utf8');
const start = source.indexOf('function updateMediaSessionMetadata()');
const end = source.indexOf('function updateMediaPosition()', start);

test('media controls use the loaded EPUB cover and fall back to a local icon', () => {
    const actions = {};
    const session = { setActionHandler: (name, fn) => { actions[name] = fn; } };
    const context = vm.createContext({
        navigator: { mediaSession: session },
        MediaMetadata: function (value) { Object.assign(this, value); },
        readerAssetBase: new URL('https://example.com/theme/web-reader/'),
        currentBook: {}, currentMediaCoverUrl: 'blob:https://example.com/cover',
        document: { getElementById: id => ({ textContent: id === 'book-title-text' ? 'The Book' : 'The Author' }) },
        updateMediaPosition() {}, togglePlay() {}, navigateSentence() {}, stopReading() {}, URL
    });
    vm.runInContext(source.slice(start, end), context);
    vm.runInContext('updateMediaSessionMetadata()', context);
    assert.equal(session.metadata.title, 'The Book');
    assert.equal(session.metadata.artist, 'The Author');
    assert.equal(session.metadata.artwork[0].src, 'blob:https://example.com/cover');
    assert.equal(typeof actions.play, 'function');
    vm.runInContext('currentMediaCoverUrl = null; updateMediaSessionMetadata()', context);
    assert.equal(session.metadata.artwork[0].src, 'https://example.com/theme/web-reader/icons/icon-512.png');
});

test('media companion is a valid local WAV', () => {
    const wav = fs.readFileSync(path.join(__dirname, 'silent-loop.wav'));
    assert.equal(wav.toString('ascii', 0, 4), 'RIFF');
    assert.equal(wav.toString('ascii', 8, 12), 'WAVE');
    assert.equal(wav.readUInt32LE(4) + 8, wav.length);
});
