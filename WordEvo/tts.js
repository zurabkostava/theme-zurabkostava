// ==== tts.js ====

const VOICE_STORAGE_KEY = 'selected_voice_name';
const GEORGIAN_VOICE_KEY = 'selected_georgian_voice';
const ENGLISH_RATE_KEY = 'english_voice_rate';
const GEORGIAN_RATE_KEY = 'georgian_voice_rate';

let selectedVoice = null;
let selectedGeorgianVoice = null;
let piperVoicesList = []; // Array of fetched piper voices

// Workers map: we keep up to 2 workers alive (one for lang1, one for lang2)
let piperWorkers = {
    lang1: { worker: null, ready: false, initializing: false, queue: [], pendingCallbacks: [], currentAudio: null, voicePath: null },
    lang2: { worker: null, ready: false, initializing: false, queue: [], pendingCallbacks: [], currentAudio: null, voicePath: null }
};

async function fetchPiperVoices() {
    try {
        const cached = localStorage.getItem('piper_voices_cache');
        if (cached) {
            piperVoicesList = JSON.parse(cached);
            return piperVoicesList;
        }
        const res = await fetch('https://huggingface.co/rhasspy/piper-voices/raw/main/voices.json');
        const json = await res.json();
        const mapped = Object.keys(json).map(k => {
            const v = json[k];
            // find onnx file
            const onnxFile = Object.keys(v.files).find(f => f.endsWith('.onnx'));
            if (!onnxFile) return null;
            return {
                isPiper: true,
                key: v.key,
                name: `☁️ Piper — ${v.language.name_english} (${v.name}, ${v.quality})`,
                lang: v.language.code,
                path: onnxFile.replace('.onnx', '')
            };
        }).filter(Boolean);
        
        piperVoicesList = mapped.sort((a, b) => a.name.localeCompare(b.name));
        localStorage.setItem('piper_voices_cache', JSON.stringify(piperVoicesList));
        return piperVoicesList;
    } catch (e) {
        console.error('Failed to fetch piper voices', e);
        // Fallback dummy if fetch fails
        piperVoicesList = [
            { isPiper: true, key: 'ka_GE-natia-medium', name: '☁️ Piper — Georgian (Natia, medium)', lang: 'ka_GE', path: 'ka/ka_GE/natia/medium/ka_GE-natia-medium' },
            { isPiper: true, key: 'en_US-lessac-medium', name: '☁️ Piper — English (Lessac, medium)', lang: 'en_US', path: 'en/en_US/lessac/medium/en_US-lessac-medium' }
        ];
        return piperVoicesList;
    }
}

function getAllLanguages() {
    const langs = new Map();
    const nativeVoices = speechSynthesis.getVoices();
    nativeVoices.forEach(v => {
        const langCode = v.lang.split('-')[0];
        try {
            const name = new Intl.DisplayNames(['en'], { type: 'language' }).of(langCode);
            langs.set(langCode, name);
        } catch(e) { langs.set(langCode, langCode); }
    });
    piperVoicesList.forEach(v => {
        const langCode = v.lang.split('_')[0];
        try {
            const name = new Intl.DisplayNames(['en'], { type: 'language' }).of(langCode);
            langs.set(langCode, name);
        } catch(e) { langs.set(langCode, langCode); }
    });
    return Array.from(langs.entries()).map(([code, name]) => ({ code, name })).sort((a, b) => a.name.localeCompare(b.name));
}

function getDictionaryLangs() {
    if (!localStorage.getItem('wordevo_current_dictionary')) return { lang1: 'en', lang2: 'ka' };
    const stored = localStorage.getItem('dict_langs_' + localStorage.getItem('wordevo_current_dictionary'));
    if (stored) return JSON.parse(stored);
    return { lang1: 'en', lang2: 'ka' };
}

function getEnglishVoices() {
    return speechSynthesis.getVoices().filter(v => v.lang.startsWith('en'));
}

function getGeorgianVoices() {
    const voices = speechSynthesis.getVoices();
    const georgian = voices.filter(v => v.lang.startsWith('ka'));
    const multilingual = voices.filter(v => v.name.toLowerCase().includes('multilingual') && !v.lang.startsWith('ka'));
    return [...georgian, ...multilingual];
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function loadSpeechRates() {
    const englishRateSlider = document.getElementById('englishRateSlider');
    const georgianRateSlider = document.getElementById('georgianRateSlider');

    if(englishRateSlider) englishRateSlider.value = localStorage.getItem(ENGLISH_RATE_KEY) || 1;
    if(georgianRateSlider) georgianRateSlider.value = localStorage.getItem(GEORGIAN_RATE_KEY) || 1;
}

async function populateDropdowns() {
    if (piperVoicesList.length === 0) {
        await fetchPiperVoices();
    }
    
    // Force a fresh getVoices() call
    speechSynthesis.getVoices();
    
    const voiceSelect = document.getElementById('voiceSelect');
    const geoSelect = document.getElementById('georgianVoiceSelect');
    const { lang1, lang2 } = getDictionaryLangs();
    
    if (voiceSelect) {
        voiceSelect.innerHTML = '';
        const nativeGroup = document.createElement('optgroup');
        nativeGroup.label = "Native Browser Voices";
        speechSynthesis.getVoices().filter(v => v.lang.startsWith(lang1)).forEach(voice => {
            const option = document.createElement('option');
            option.value = voice.name;
            option.textContent = voice.name;
            nativeGroup.appendChild(option);
        });
        voiceSelect.appendChild(nativeGroup);

        const piperGroup = document.createElement('optgroup');
        piperGroup.label = "Piper TTS Voices (Downloads Locally)";
        piperVoicesList.filter(pv => pv.lang.startsWith(lang1)).forEach(pv => {
            const option = document.createElement('option');
            option.value = 'piper:' + pv.key;
            option.textContent = pv.name;
            piperGroup.appendChild(option);
        });
        voiceSelect.appendChild(piperGroup);
        
        // Also show voices that were previously selected even if language mismatch
        const storedVoice = localStorage.getItem(VOICE_STORAGE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'));
        if (storedVoice) {
            let found = Array.from(voiceSelect.options).some(o => o.value === storedVoice);
            if (!found) {
                const option = document.createElement('option');
                option.value = storedVoice;
                option.textContent = storedVoice + ' (Saved)';
                voiceSelect.appendChild(option);
            }
            voiceSelect.value = storedVoice;
        }
    }

    if (geoSelect) {
        geoSelect.innerHTML = '';
        const nativeGroup = document.createElement('optgroup');
        nativeGroup.label = "Native Browser Voices";
        speechSynthesis.getVoices().filter(v => v.lang.startsWith(lang2)).forEach(voice => {
            const option = document.createElement('option');
            option.value = voice.name;
            option.textContent = voice.name;
            nativeGroup.appendChild(option);
        });
        geoSelect.appendChild(nativeGroup);

        const piperGroup = document.createElement('optgroup');
        piperGroup.label = "Piper TTS Voices (Downloads Locally)";
        piperVoicesList.filter(pv => pv.lang.startsWith(lang2)).forEach(pv => {
            const option = document.createElement('option');
            option.value = 'piper:' + pv.key;
            option.textContent = pv.name;
            piperGroup.appendChild(option);
        });
        geoSelect.appendChild(piperGroup);
        
        const storedGeo = localStorage.getItem(GEORGIAN_VOICE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'));
        if (storedGeo) {
            let found = Array.from(geoSelect.options).some(o => o.value === storedGeo);
            if (!found) {
                const option = document.createElement('option');
                option.value = storedGeo;
                option.textContent = storedGeo + ' (Saved)';
                geoSelect.appendChild(option);
            }
            geoSelect.value = storedGeo;
        }
    }
}

async function loadVoices() {
    if (piperVoicesList.length === 0) {
        await fetchPiperVoices();
    }
    const voices = speechSynthesis.getVoices();
    const { lang1, lang2 } = getDictionaryLangs();

    let storedVoice = localStorage.getItem(VOICE_STORAGE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'));
    if (!storedVoice) {
        const globalVoice = localStorage.getItem(VOICE_STORAGE_KEY);
        let globalMatches = false;
        if (globalVoice && globalVoice.startsWith('piper:')) {
            const pv = piperVoicesList.find(p => p.key === globalVoice.split('piper:')[1]);
            if (pv && pv.lang.startsWith(lang1)) globalMatches = true;
        } else if (globalVoice) {
            const v = voices.find(v => v.name === globalVoice);
            if (v && v.lang.startsWith(lang1)) globalMatches = true;
        }

        if (globalMatches) {
            storedVoice = globalVoice;
        } else {
            const hasNativeLang1 = voices.some(v => v.lang.startsWith(lang1));
            const firstPiper = piperVoicesList.find(v => v.lang.startsWith(lang1));
            storedVoice = hasNativeLang1 ? voices.find(v => v.lang.startsWith(lang1)).name : (firstPiper ? 'piper:' + firstPiper.key : '');
        }
        if (storedVoice) localStorage.setItem(VOICE_STORAGE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'), storedVoice);
    }

    if (storedVoice && storedVoice.startsWith('piper:')) {
        const key = storedVoice.split('piper:')[1];
        const pv = piperVoicesList.find(p => p.key === key);
        if (pv) {
            selectedVoice = { name: pv.name, lang: pv.lang, isPiperDummy: true, voicePath: pv.path };
            piperWorkers['lang1'].voicePath = pv.path;
        }
    } else if (storedVoice) {
        selectedVoice = voices.find(v => v.name === storedVoice);
        piperWorkers['lang1'].voicePath = null;
        if (piperWorkers['lang1'].worker) {
            piperWorkers['lang1'].worker.terminate();
            piperWorkers['lang1'].worker = null;
            piperWorkers['lang1'].ready = false;
        }
    }

    let storedGeo = localStorage.getItem(GEORGIAN_VOICE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'));
    // Migration for old hardcoded Piper voice name
    if (storedGeo === '🌐 Piper TTS — Natia (ქართული)') {
        storedGeo = 'piper:ka_GE-natia-medium';
        localStorage.setItem(GEORGIAN_VOICE_KEY + '_' + localStorage.getItem('wordevo_current_dictionary'), storedGeo);
    }

    if (!storedGeo) {
        const globalGeo = localStorage.getItem(GEORGIAN_VOICE_KEY);
        let globalMatches = false;
        if (globalGeo && globalGeo.startsWith('piper:')) {
            const pv = piperVoicesList.find(p => p.key === globalGeo.split('piper:')[1]);
            if (pv && pv.lang.startsWith(lang2)) globalMatches = true;
        } else if (globalGeo) {
            const v = voices.find(v => v.name === globalGeo);
            if (v && v.lang.startsWith(lang2)) globalMatches = true;
        }

        if (globalMatches) {
            storedGeo = globalGeo;
        } else {
            const hasNativeLang2 = voices.some(v => v.lang.startsWith(lang2));
            const firstPiper2 = piperVoicesList.find(v => v.lang.startsWith(lang2));
            storedGeo = hasNativeLang2 ? voices.find(v => v.lang.startsWith(lang2)).name : (firstPiper2 ? 'piper:' + firstPiper2.key : '');
        }
        if (storedGeo) localStorage.setItem(GEORGIAN_VOICE_KEY + '_' + window.currentDictionaryId, storedGeo);
    }

    if (storedGeo && storedGeo.startsWith('piper:')) {
        const key = storedGeo.split('piper:')[1];
        const pv = piperVoicesList.find(p => p.key === key);
        if (pv) {
            selectedGeorgianVoice = { name: pv.name, lang: pv.lang, isPiperDummy: true, voicePath: pv.path };
            piperWorkers['lang2'].voicePath = pv.path;
        }
    } else if (storedGeo) {
        selectedGeorgianVoice = voices.find(v => v.name === storedGeo);
        piperWorkers['lang2'].voicePath = null;
        if (piperWorkers['lang2'].worker) {
            piperWorkers['lang2'].worker.terminate();
            piperWorkers['lang2'].worker = null;
            piperWorkers['lang2'].ready = false;
        }
    }

    populateDropdowns();
}

function loadVoicesWithDelay(retry = 0) {
    const voices = speechSynthesis.getVoices();
    if (voices.length > 0 || retry >= 20) {
        loadVoices();
        if (retry < 20) {
            setTimeout(() => {
                const newVoices = speechSynthesis.getVoices();
                if (newVoices.length > voices.length) loadVoices();
            }, 2000);
        }
        return;
    }
    setTimeout(() => loadVoicesWithDelay(retry + 1), 300);
}

speechSynthesis.onvoiceschanged = loadVoices;

function initPiperWorker(workerKey, voicePath) {
    const state = piperWorkers[workerKey];
    if (state.worker && state.voicePath === voicePath) return; // Already loaded

    if (state.worker) {
        state.worker.terminate();
        state.worker = null;
        state.ready = false;
        state.initializing = false;
    }

    state.initializing = true;
    state.voicePath = voicePath;

    if (typeof showToast === 'function') showToast(`ხმის მოდელი იტვირთება (${workerKey})...`, 'info');
    
    const workerPath = (window.WORDEVO_ASSET_PATH || '.') + '/piper-worker.js';
    const worker = new Worker(workerPath);
    state.worker = worker;

    worker.addEventListener('message', (e) => {
        const { kind, wav, message } = e.data;
        if (kind === 'ready') {
            state.ready = true;
            state.initializing = false;
            if (typeof showToast === 'function') showToast(`Piper TTS მზადაა!`, 'success');
            while (state.queue.length > 0) {
                const queued = state.queue.shift();
                state.pendingCallbacks.push(queued);
                worker.postMessage({ kind: 'synthesize', text: queued.text });
            }
        } else if (kind === 'output') {
            if (state.pendingCallbacks.length > 0) {
                state.pendingCallbacks.shift().resolve(wav);
            }
        } else if (kind === 'error') {
            console.error('[Piper] Error:', message);
            if (state.pendingCallbacks.length > 0) state.pendingCallbacks.shift().reject(new Error(message));
            if (!state.ready) {
                state.initializing = false;
                while (state.queue.length > 0) state.queue.shift().reject(new Error(message));
                if (typeof showToast === 'function') showToast('Piper TTS ვერ ჩაიტვირთა', 'error');
            }
        } else if (kind === 'status') {
            console.log('[Piper]', message);
        }
    });

    worker.postMessage({ kind: 'init', voicePath });
}

function speakWithPiper(text, rate = 1, workerKey) {
    return new Promise((resolve, reject) => {
        const state = piperWorkers[workerKey];
        if (!state.voicePath) {
            reject(new Error('No Piper voice selected'));
            return;
        }

        if (state.currentAudio) {
            state.currentAudio.pause();
            state.currentAudio = null;
        }

        initPiperWorker(workerKey, state.voicePath);

        const onWav = (wav) => {
            const audio = new Audio();
            audio.src = URL.createObjectURL(wav);
            audio.playbackRate = Math.max(0.5, Math.min(rate, 2));
            state.currentAudio = audio;
            audio.onended = () => { state.currentAudio = null; resolve(); };
            audio.onerror = () => { state.currentAudio = null; reject(new Error('Piper playback failed')); };
            audio.play().catch(reject);
        };

        if (state.ready) {
            state.pendingCallbacks.push({ resolve: onWav, reject });
            state.worker.postMessage({ kind: 'synthesize', text });
        } else {
            state.queue.push({ text, resolve: onWav, reject });
        }
    });
}

function stopAllTTS() {
    if (piperWorkers.lang1.currentAudio) piperWorkers.lang1.currentAudio.pause();
    if (piperWorkers.lang2.currentAudio) piperWorkers.lang2.currentAudio.pause();
    piperWorkers.lang1.currentAudio = null;
    piperWorkers.lang2.currentAudio = null;
    if (window.speechSynthesis) speechSynthesis.cancel();
}

async function speakWithVoice(text, voiceObj, buttonEl = null, extraText = null, highlightEl = null) {
    if (!text || !voiceObj) return;

    const isPiper = voiceObj.isPiperDummy === true;
    const workerKey = (voiceObj === selectedVoice) ? 'lang1' : 'lang2';
    
    stopAllTTS();
    await delay(100);

    const speak = (txt, el) => {
        return new Promise(resolve => {
            if (el) el.classList.add('highlighted-sentence');
            if (buttonEl) buttonEl.classList.add('active');

            if (isPiper) {
                const rateKey = workerKey === 'lang1' ? ENGLISH_RATE_KEY : GEORGIAN_RATE_KEY;
                const rate = parseFloat(localStorage.getItem(rateKey) || 1);
                speakWithPiper(txt, rate, workerKey)
                    .then(() => {
                        if (el) el.classList.remove('highlighted-sentence');
                        if (buttonEl) buttonEl.classList.remove('active');
                        resolve();
                    })
                    .catch(() => {
                        if (el) el.classList.remove('highlighted-sentence');
                        if (buttonEl) buttonEl.classList.remove('active');
                        resolve();
                    });
                return;
            }

            const utterance = new SpeechSynthesisUtterance(txt);
            utterance.voice = voiceObj;
            utterance.lang = voiceObj.lang;

            const rateKey = workerKey === 'lang1' ? ENGLISH_RATE_KEY : GEORGIAN_RATE_KEY;
            utterance.rate = parseFloat(localStorage.getItem(rateKey) || 1);

            utterance.onend = () => {
                if (el) el.classList.remove('highlighted-sentence');
                if (buttonEl) buttonEl.classList.remove('active');
                resolve();
            };

            speechSynthesis.speak(utterance);
        });
    };

    await speak(text, highlightEl);

    if (extraText) {
        await delay(100);
        await speak(extraText, highlightEl);
    }
}

document.addEventListener('click', (e) => {
    const speakBtn = e.target.closest('.speak-btn');
    if (!speakBtn) return;

    e.stopPropagation();

    const text = speakBtn.dataset.text || speakBtn.dataset.word;
    const extraText = speakBtn.dataset.extra || null;
    const lang = speakBtn.dataset.lang;

    if (lang === 'ka') {
        speakWithVoice(text, selectedGeorgianVoice, speakBtn, extraText);
    } else {
        speakWithVoice(text, selectedVoice, speakBtn);
    }
});

