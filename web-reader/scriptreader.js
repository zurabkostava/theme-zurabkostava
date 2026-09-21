let synthesis = window.speechSynthesis;
const PIPER_FALLBACK_VOICES = [
    { isPiper: true, key: 'ka_GE-natia-medium', name: '☁️ Piper — Georgian (Natia, medium)', lang: 'ka_GE', path: 'ka/ka_GE/natia/medium/ka_GE-natia-medium' },
    { isPiper: true, key: 'en_US-lessac-medium', name: '☁️ Piper — English (Lessac, medium)', lang: 'en_US', path: 'en/en_US/lessac/medium/en_US-lessac-medium' }
];
let piperVoicesList = PIPER_FALLBACK_VOICES.slice();
let detectedBookLanguages = new Set();
let piperWorkers = {};
let parsedContent = [];
let currentIdx = 0;
let isPlaying = false;
let voices = [];
let isEditMode = false;
window.utterances = [];

let playbackToken = 0;
let pauseSettings = {
    mainHeader: 5000,
    internalHeader: 3000,
    postHeader: 2000,
    paragraph: 0
};
try {
    const savedPause = localStorage.getItem('ttsPauseSettings');
    if (savedPause) {
        pauseSettings = { ...pauseSettings, ...JSON.parse(savedPause) };
    }
} catch (e) {
    console.error("Error loading pause settings:", e);
}

let skipParenthesesSetting = false;
try {
    skipParenthesesSetting = localStorage.getItem('tts-skip-parentheses') === 'true';
} catch (e) {
    console.error("Error loading skip parentheses setting:", e);
}

let cloudSaveTimeout = null;
function syncProgressToCloud(force = false) {
    if (!window.currentRawEpubFile) return;
    const bookName = window.currentRawEpubFile.name;
    const idx = localStorage.getItem('epub_idx_' + bookName) || 0;
    const href = localStorage.getItem('epub_progress_' + bookName) || '';
    const perc = localStorage.getItem('epub_perc_' + bookName) || '';
    
    const sendData = () => {
        fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(bookName)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ href, idx, perc })
        }).catch(e => console.error('Cloud sync error', e));
    };

    clearTimeout(cloudSaveTimeout);
    
    if (force) {
        sendData();
    } else {
        cloudSaveTimeout = setTimeout(sendData, 2000);
    }
}

window.addEventListener('beforeunload', () => {
    if (window.currentRawEpubFile) {
        syncProgressToCloud(true);
    }
});

// EPUB Globals
let currentBook = null;
let currentSpineIndex = 0; // ეს არის ის, რასაც ვუყურებთ
let tocHrefSet = new Set();
// --- GHOST PLAYER (Android Fix) ---
const ghostAudio = new Audio("https://github.com/anars/blank-audio/blob/master/10-minutes-of-silence.mp3?raw=true");
ghostAudio.loop = true;
ghostAudio.preload = 'auto';
ghostAudio.volume = 0.1;
let wakeLock = null;

function isValidPiperEntry(v) {
    return !!v && v.isPiper === true
        && typeof v.name === 'string' && v.name.length > 0
        && typeof v.lang === 'string' && v.lang.length > 0
        && typeof v.path === 'string' && v.path.length > 0;
}

async function fetchPiperVoices(forceRefresh = false) {
    if (forceRefresh) {
        try { localStorage.removeItem('piper_voices_cache'); } catch (e) {}
    }
    try {
        const cached = localStorage.getItem('piper_voices_cache');
        if (cached) {
            try {
                const parsed = JSON.parse(cached);
                // Every entry must be well-formed, otherwise the cache is corrupt — drop it and refetch
                if (Array.isArray(parsed) && parsed.length > 0 && parsed.every(isValidPiperEntry)) {
                    piperVoicesList = parsed;
                    if (!piperVoicesList.some(v => v.key === 'ka_GE-natia-medium')) {
                        piperVoicesList.unshift(PIPER_FALLBACK_VOICES[0]);
                    }
                    return piperVoicesList;
                }
                localStorage.removeItem('piper_voices_cache');
            } catch(e) {
                localStorage.removeItem('piper_voices_cache');
            }
        }
        const res = await fetch('https://huggingface.co/rhasspy/piper-voices/raw/main/voices.json');
        if (!res.ok) throw new Error('voices.json HTTP ' + res.status);
        const json = await res.json();
        const mapped = Object.keys(json).map(k => {
            const v = json[k];
            if (!v || !v.files || !v.language || !v.language.code) return null;
            const onnxFile = Object.keys(v.files).find(f => f.endsWith('.onnx'));
            if (!onnxFile) return null;
            return {
                isPiper: true,
                key: v.key || k,
                name: (k === 'ka_GE-natia-medium') ? '☁️ Piper — Georgian (Natia, medium)' : `☁️ Piper — ${v.language.name_english || v.language.code} (${v.name || k}, ${v.quality || 'medium'})`,
                lang: String(v.language.code),
                path: onnxFile.replace('.onnx', '')
            };
        }).filter(isValidPiperEntry);
        if (mapped.length === 0) throw new Error('voices.json parsed to 0 usable voices');

        piperVoicesList = mapped.sort((a, b) => a.name.localeCompare(b.name));

        const hasGeorgian = piperVoicesList.some(v => v.key === 'ka_GE-natia-medium');
        if (!hasGeorgian) {
            piperVoicesList.unshift(PIPER_FALLBACK_VOICES[0]);
        }

        try { localStorage.setItem('piper_voices_cache', JSON.stringify(piperVoicesList)); } catch (e) {}
        return piperVoicesList;
    } catch (e) {
        console.error('Failed to fetch piper voices', e);
        piperVoicesList = PIPER_FALLBACK_VOICES.slice();
        return piperVoicesList;
    }
}

// --- 🧠 CORE HELPER: რეალური პოზიციის გაგება ---
function getRealSavedIndex() {
    if (!currentBook || !window.currentRawEpubFile) return -1;
    const savedHref = localStorage.getItem('epub_progress_' + window.currentRawEpubFile.name);
    if (!savedHref) return -1;
    const item = currentBook.spine.get(savedHref);
    return item ? item.index : -1;
}

async function requestWakeLock() {
    if ('wakeLock' in navigator) {
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            console.log('Wake Lock active');
        } catch (err) {
            console.error(`${err.name}, ${err.message}`);
        }
    }
}
function releaseWakeLock() {
    if (wakeLock !== null) {
        wakeLock.release().then(() => {
            wakeLock = null;
            console.log('Wake Lock released');
        });
    }
}
function updateMediaSessionMetadata() {
    if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: 'Neural Reader Playing',
            artist: 'Zurab Kostava',
            album: currentBook ? 'EPUB Book' : 'Reading Session',
            artwork: [
                { src: 'https://cdn-icons-png.flaticon.com/512/2995/2995101.png', sizes: '512x512', type: 'image/png' }
            ]
        });
        navigator.mediaSession.setActionHandler('play', () => { togglePlay(); });
        navigator.mediaSession.setActionHandler('pause', () => { togglePlay(); });
        navigator.mediaSession.setActionHandler('previoustrack', () => navigateSentence(-1));
        navigator.mediaSession.setActionHandler('nexttrack', () => navigateSentence(1));
        navigator.mediaSession.setActionHandler('stop', () => stopReading());
        updateMediaPosition();
    }
}
function updateMediaPosition() {
    if ('mediaSession' in navigator && parsedContent.length > 0) {
        try {
            const SCALE_FACTOR = 60;
            const totalDuration = parsedContent.length * SCALE_FACTOR;
            let currentPosition = currentIdx * SCALE_FACTOR;
            if (currentPosition >= totalDuration) currentPosition = totalDuration - 1;
            if (currentPosition < 0) currentPosition = 0;
            navigator.mediaSession.setPositionState({
                duration: totalDuration,
                playbackRate: 1.0,
                position: currentPosition
            });
        } catch (error) {
            console.error("Media Session Position Error:", error);
        }
    }
}
// --- 2. DOM Elements ---
const editBtn = document.getElementById('edit-btn');
const contentArea = document.getElementById('content-area');
const playBtn = document.getElementById('play-btn');
const stopBtn = document.getElementById('stop-btn');
const nextBtn = document.getElementById('next-btn');
const prevBtn = document.getElementById('prev-btn');
const settingsBtn = document.getElementById('settings-btn');
const settingsModal = document.getElementById('settings-modal') || document.getElementById('settings-panel');
const closeSettingsBtn = document.getElementById('close-settings-btn');
const dynamicVoiceSettings = document.getElementById('dynamic-voice-settings');
// --- LIBRARY ELEMENTS ---
const libraryBtn = document.getElementById('library-btn');
const libraryModal = document.getElementById('library-modal');
const closeLibraryBtn = document.getElementById('close-library-btn');
const libraryGrid = document.getElementById('library-grid');
let myBooks = [];
// Sidebar Elements
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
const closeSidebarBtn = document.getElementById('close-sidebar-btn');
const tocList = document.getElementById('toc-list');
// Upload Elements
const uploadBtn = document.getElementById('upload-btn');
const fileInput = document.getElementById('file-input');
const dropZone = document.getElementById('drop-zone');
// --- 3. MEDIA SESSION ---
const refreshVoicesBtn = document.getElementById('refresh-voices-btn');
if (refreshVoicesBtn) refreshVoicesBtn.onclick = async () => {
    wakeUpSpeechEngine();
    await fetchPiperVoices(true);
    loadVoices();
};

function initMediaSession() {
    if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: 'Neural Reader',
            artist: 'Zurab Kostava',
            album: 'EPUB Audiobook',
            artwork: [{ src: 'https://cdn-icons-png.flaticon.com/512/2995/2995101.png', sizes: '512x512', type: 'image/png' }]
        });
        navigator.mediaSession.setActionHandler('play', () => togglePlay());
        navigator.mediaSession.setActionHandler('pause', () => togglePlay());
        navigator.mediaSession.setActionHandler('previoustrack', () => navigateSentence(-1));
        navigator.mediaSession.setActionHandler('nexttrack', () => navigateSentence(1));
        navigator.mediaSession.setActionHandler('stop', () => stopReading());
        navigator.mediaSession.setActionHandler('seekto', (details) => {
            if (details.seekTime !== undefined && parsedContent.length > 0) {
                const SCALE_FACTOR = 60;
                let targetIdx = Math.floor(details.seekTime / SCALE_FACTOR);
                if (targetIdx < 0) targetIdx = 0;
                if (targetIdx >= parsedContent.length) targetIdx = parsedContent.length - 1;
                synthesis.cancel(); stopPiperAudio();
                currentIdx = targetIdx;
                highlightSentence(currentIdx);
                updateMediaPosition();
                if (isPlaying) playMergedQueue();
            }
        });
    }
}
// --- 4. EPUB LOGIC ---
fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) loadEpub(file);
});
uploadBtn.addEventListener('click', () => fileInput.click());

function bindHubEvents() {
    const hubLibraryBtn = document.getElementById('hub-library-btn');
    if (hubLibraryBtn) hubLibraryBtn.onclick = () => libraryBtn.click();

    const hubOpenEpubBtn = document.getElementById('hub-open-epub-btn');
    if (hubOpenEpubBtn) hubOpenEpubBtn.onclick = () => fileInput.click();

    const hubEditTextBtn = document.getElementById('hub-edit-text-btn');
    if (hubEditTextBtn) hubEditTextBtn.onclick = () => editBtn.click();

    const hubSettingsBtn = document.getElementById('hub-settings-btn');
    if (hubSettingsBtn) hubSettingsBtn.onclick = () => settingsBtn.click();
}
// Initial bind on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindHubEvents);
} else {
    bindHubEvents();
}

contentArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    contentArea.classList.add('dragover');
    const dz = document.getElementById('drop-zone');
    if (dz) dz.classList.add('dragover');
});
contentArea.addEventListener('dragleave', (e) => {
    e.preventDefault();
    contentArea.classList.remove('dragover');
    const dz = document.getElementById('drop-zone');
    if (dz) dz.classList.remove('dragover');
});
contentArea.addEventListener('drop', (e) => {
    e.preventDefault();
    contentArea.classList.remove('dragover');
    const dz = document.getElementById('drop-zone');
    if (dz) dz.classList.remove('dragover');
    if (e.dataTransfer.items) {
        [...e.dataTransfer.items].forEach((item, i) => {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file && file.name.endsWith('.epub')) loadEpub(file);
            }
        });
    } else if (e.dataTransfer.files) {
        [...e.dataTransfer.files].forEach(file => {
            if (file && file.name.endsWith('.epub')) loadEpub(file);
        });
    }
});

// Logo click to return to home Welcome Hub
const appLogo = document.querySelector('.logo');
if (appLogo) {
    appLogo.style.cursor = 'pointer';
    appLogo.title = 'Neural Reader PRO — Return to Home Hub';
    appLogo.addEventListener('click', () => {
        if (document.body.classList.contains('is-reading')) {
            if (confirm("Return to home screen?")) {
                stopReading();
                showDropZone();
            }
        }
    });
}

async function handleMetaClick() {
    const modal = document.getElementById('book-info-modal');
    if (!modal) return;
    const safeSetText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    const title = document.getElementById('book-title-text')?.textContent || "Unknown Title";
    const author = document.getElementById('book-author-text')?.textContent || "Unknown Author";
    const currentCoverSrc = document.getElementById('book-cover-img')?.src;
    safeSetText('modal-book-title', title);
    safeSetText('modal-book-author', author);

    const coverContainer = document.getElementById('modal-cover-container');
    const modalCover = document.getElementById('modal-book-cover');
    if (coverContainer) {
        if (currentCoverSrc && !currentCoverSrc.includes(window.location.host + '/#') && currentCoverSrc !== window.location.href) {
            coverContainer.innerHTML = '';
            if (modalCover) {
                modalCover.src = currentCoverSrc;
                modalCover.style.display = 'block';
                coverContainer.appendChild(modalCover);
            }
        } else {
            coverContainer.innerHTML = renderProceduralCover(title, author);
        }
    }

    const fileName = window.currentRawEpubFile ? window.currentRawEpubFile.name : '';
    const markReadCheckbox = document.getElementById('modal-mark-read-checkbox');
    const readBadge = document.getElementById('modal-read-badge');
    if (fileName && markReadCheckbox) {
        const savedPerc = localStorage.getItem('epub_perc_' + fileName) || '0';
        const numPerc = parseFloat(savedPerc) || 0;
        const isCompleted = numPerc >= 99;
        markReadCheckbox.checked = isCompleted;
        if (readBadge) {
            readBadge.textContent = isCompleted ? `${savedPerc}% ✓` : `${numPerc > 0 ? savedPerc + '%' : '0%'}`;
            readBadge.classList.toggle('completed', isCompleted);
        }
        markReadCheckbox.onchange = (e) => {
            if (e.target.checked) {
                try { localStorage.setItem('epub_perc_' + fileName, '100'); } catch(err){}
                fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(fileName)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ href: '', idx: 0, perc: '100.00' })
                }).catch(err => console.error(err));
                if (readBadge) {
                    readBadge.textContent = '100% ✓';
                    readBadge.classList.add('completed');
                }
            } else {
                try {
                    localStorage.removeItem('epub_perc_' + fileName);
                    localStorage.removeItem('epub_progress_' + fileName);
                    localStorage.removeItem('epub_idx_' + fileName);
                } catch(err){}
                fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(fileName)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ href: '', idx: 0, perc: '0.00' })
                }).catch(err => console.error(err));
                if (readBadge) {
                    readBadge.textContent = '0%';
                    readBadge.classList.remove('completed');
                }
            }
        };
    }

    const openBtn = document.getElementById('modal-open-book-btn');
    if (openBtn) {
        openBtn.onclick = () => { modal.classList.add('hidden'); };
    }
    const pubEl = document.getElementById('modal-book-publisher');
    const genreContainer = document.getElementById('modal-book-genre');
    const descEl = document.getElementById('modal-book-desc');
    if (genreContainer) genreContainer.innerHTML = '<span class="genre-tag" style="background:gray; color:white;">Scanning...</span>';
    if (pubEl) pubEl.classList.add('hidden');
    if (descEl) descEl.innerHTML = 'Scanning file for details...';
    modal.classList.remove('hidden');
    if (window.JSZip && window.currentRawEpubFile) {
        try {
            const zip = new JSZip();
            const content = await zip.loadAsync(window.currentRawEpubFile);
            const opfFileName = Object.keys(content.files).find(name => name.endsWith('.opf'));
            if (opfFileName) {
                const opfText = await content.files[opfFileName].async("string");
                if (pubEl) {
                    const pubMatch = opfText.match(/<dc:publisher[^>]*>(.*?)<\/dc:publisher>/i) || opfText.match(/<publisher[^>]*>(.*?)<\/publisher>/i);
                    if (pubMatch && pubMatch[1]) {
                        pubEl.textContent = pubMatch[1].trim();
                        pubEl.classList.remove('hidden');
                    }
                }
                if (genreContainer) {
                    const uniqueGenres = new Set();
                    const subjectRegex = /<dc:subject[^>]*>(.*?)<\/dc:subject>/gi;
                    let match;
                    while ((match = subjectRegex.exec(opfText)) !== null) {
                        let rawGenre = match[1].replace(/<!\[CDATA\[(.*?)\]\]>/g, '$1');
                        rawGenre.split(/[,;]/).forEach(g => {
                            let clean = g.trim();
                            if (clean.length > 1) uniqueGenres.add(clean);
                        });
                    }
                    genreContainer.innerHTML = '';
                    if (uniqueGenres.size > 0) {
                        uniqueGenres.forEach(genre => {
                            const tag = document.createElement('span');
                            tag.className = 'genre-tag';
                            tag.textContent = genre;
                            genreContainer.appendChild(tag);
                        });
                    } else {
                        genreContainer.innerHTML = '<span class="genre-tag">General</span>';
                    }
                }
                if (descEl) {
                    const descMatch = opfText.match(/<dc:description[^>]*>(.*?)<\/dc:description>/is) || opfText.match(/<description[^>]*>(.*?)<\/description>/is);
                    if (descMatch && descMatch[1]) {
                        let d = descMatch[1].replace(/<!\[CDATA\[(.*?)\]\]>/gs, '$1');
                        d = d.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
                        descEl.innerHTML = d;
                    } else {
                        descEl.innerHTML = "No description found.";
                    }
                }
            }
        } catch (err) {
            if (descEl) descEl.innerHTML = "Metadata scan failed.";
        }
    }
}

async function loadEpub(file) {
    const bookName = file.name;
    
    try {
        const res = await fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(bookName)}`);
        if (res.ok) {
            const data = await res.json();
            if (data && (data.href || data.idx || data.perc)) {
                if (data.href) localStorage.setItem('epub_progress_' + bookName, data.href);
                if (data.idx) localStorage.setItem('epub_idx_' + bookName, data.idx);
                if (data.perc) localStorage.setItem('epub_perc_' + bookName, data.perc);
            }
        }
    } catch(e) { console.error('Cloud progress fetch error', e); }

    window.currentRawEpubFile = file;

    if (currentBook) {
        currentBook.destroy();
        tocList.innerHTML = '';
        tocHrefSet.clear();
        document.getElementById('book-meta-container').classList.add('hidden');
        document.getElementById('book-cover-img').src = '';
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const bookData = e.target.result;
        currentBook = ePub(bookData);
        window.currentBook = currentBook;

        // 🏗️ 1. Locations დაგენერირება (Background / Non-blocking)
        currentBook.ready.then(() => {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => calculateGlobalChapterWeights(), { timeout: 2000 });
            } else {
                setTimeout(() => calculateGlobalChapterWeights(), 60);
            }
        });

        // 🏗️ 2. Metadata
        currentBook.loaded.metadata.then(meta => {
            const metaContainer = document.getElementById('book-meta-container');
            document.getElementById('book-title-text').textContent = meta.title || "Unknown Title";
            document.getElementById('book-author-text').textContent = meta.creator || "Unknown Author";
            currentBook.coverUrl().then(url => {
                const img = document.getElementById('book-cover-img');
                if(url) { img.src = url; img.style.display = 'block'; }
                else { img.style.display = 'none'; }
            });
            metaContainer.classList.remove('hidden');
            document.body.classList.add('is-reading');
            metaContainer.onclick = handleMetaClick;
        });

        // 🏗️ 3. Navigation & Display
        Promise.all([currentBook.loaded.navigation, currentBook.loaded.spine]).then(([nav, spine]) => {
            populateTocSet(nav.toc);
            renderSidebar(nav.toc);
            sidebarToggleBtn.classList.remove('hidden');
            openSidebar(); // Automatically open sidebar when EPUB loads

            const savedLocation = localStorage.getItem('epub_progress_' + bookName);
            if (savedLocation) {
                console.log("📍 Restoring position to:", savedLocation);
                displayChapter(savedLocation);
            } else {
                // ახალი წიგნი
                if (nav.toc && nav.toc.length > 0) displayChapter(nav.toc[0].href);
                else if (spine.items.length > 0) displayChapter(spine.items[0].href);

                // 🔥 დაზღვევა: თუ ახალი წიგნია, ეგრევე დავწეროთ 0% (სანამ Locations დაითვლის)
                updateProgressPercentage();
            }
        });
    };
    reader.readAsArrayBuffer(file);
}
function populateTocSet(items) {
    items.forEach(item => {
        const cleanHref = item.href.split('#')[0];
        tocHrefSet.add(cleanHref);
        if (item.subitems && item.subitems.length > 0) {
            populateTocSet(item.subitems);
        }
    });
}
function renderSidebar(toc) {
    tocList.innerHTML = '';
    const buildList = (items, parent) => {
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'toc-item';
            div.textContent = item.label.trim();
            div.dataset.href = item.href;
            div.onclick = () => {
                // აქ არ ვშლით ინდექსს! მხოლოდ გადავდივართ "სათვალიერებლად"
                // რეალური ინდექსი localStorage-ში ხელუხლებელი რჩება.
                displayChapter(item.href);
                if(window.innerWidth < 768) closeSidebar();
            };
            parent.appendChild(div);
            if (item.subitems && item.subitems.length > 0) {
                const subContainer = document.createElement('div');
                subContainer.style.paddingLeft = '15px';
                buildList(item.subitems, subContainer);
                parent.appendChild(subContainer);
            }
        });
    };
    buildList(toc, tocList);
}

// 🔥 განახლებული Sidebar Stylization
// ახლა ის ამოწმებს რეალურ შენახულ პოზიციას და არა მიმდინარე ხედს
function updateSidebarStyling() {
    if (!currentBook) return;

    // ვიღებთ რეალურად შენახულ ინდექსს
    const savedIndex = getRealSavedIndex();

    const items = document.querySelectorAll('.toc-item');
    items.forEach(el => {
        const href = el.dataset.href;
        const spineItem = currentBook.spine.get(href);

        if (spineItem) {
            // თუ შენახული ინდექსი არ გვაქვს, არაფერს ვაშავებთ
            if (savedIndex === -1) {
                el.classList.remove('read-chapter');
            }
            // თუ თავი რეალურ პოზიციაზე უკანაა -> ჩავაქროთ
            else if (spineItem.index < savedIndex) {
                el.classList.add('read-chapter');
            } else {
                el.classList.remove('read-chapter');
            }
        }
    });
}

async function calculateGlobalChapterWeights() {
    if (!currentBook || !window.currentRawEpubFile) return;
    const cacheKey = 'epub_weights_' + window.currentRawEpubFile.name;
    const cached = localStorage.getItem(cacheKey);
    
    if (cached) {
        try {
            window.chapterWeights = JSON.parse(cached);
            updateProgressPercentage();
            return;
        } catch(e) {}
    }
    
    const weights = [];
    let totalLength = 0;
    const spineLength = currentBook.spine ? currentBook.spine.length : 0;
    
    // Process in batches yielding to the event loop so the UI remains completely smooth
    for (let i = 0; i < spineLength; i++) {
        if (!currentBook) return;
        const item = currentBook.spine.get(i);
        if (!item) {
            weights.push(0);
            continue;
        }
        try {
            const doc = await currentBook.load(item.href);
            // Ultra-fast text length: direct textContent without expensive DOM cloning or image resolution
            const root = doc ? (doc.body || doc.documentElement) : null;
            const len = root ? (root.textContent || '').length : 0;
            weights.push(len);
            totalLength += len;
        } catch(e) {
            weights.push(100);
            totalLength += 100;
        }

        // Yield execution to event loop every 4 chapters to keep 60 FPS UI
        if (i % 4 === 0) {
            await new Promise(r => setTimeout(r, 0));
        }
    }
    
    window.chapterWeights = { weights, totalLength };
    try { localStorage.setItem(cacheKey, JSON.stringify(window.chapterWeights)); } catch(e) {}
    updateProgressPercentage();
}

function updateProgressPercentage() {
    if (!currentBook || !window.currentRawEpubFile) return;

    let activeIndex = getRealSavedIndex();
    if (activeIndex === -1) activeIndex = 0;

    let activeSentenceIdx = parseInt(localStorage.getItem('epub_idx_' + window.currentRawEpubFile.name) || 0);

    const badge = document.getElementById('header-progress-badge');
    let finalFraction = 0;

    // 1. Precise Global Calculation
    if (window.chapterWeights && window.chapterWeights.totalLength > 0) {
        const { weights, totalLength } = window.chapterWeights;
        
        let previousLength = 0;
        for (let i = 0; i < activeIndex; i++) {
            previousLength += (weights[i] || 0);
        }
        
        let currentChapterLength = weights[activeIndex] || 0;
        let progressInChapter = 0;
        
        if (activeIndex === currentSpineIndex && parsedContent.length > 0) {
            let charsUpToActive = 0;
            const totalCharsInChapter = window.currentChapterTotalChars || 1;
            const limit = Math.min(activeSentenceIdx, parsedContent.length);
            for (let i = 0; i < limit; i++) {
                charsUpToActive += (parsedContent[i].charLen || 1);
            }
            progressInChapter = (charsUpToActive / totalCharsInChapter);
        }
        
        let currentProgressLength = currentChapterLength * progressInChapter;
        finalFraction = (previousLength + currentProgressLength) / totalLength;
    } 
    // 2. Fallback (Simple Chapter-based)
    else {
        const totalChapters = currentBook.spine.length;
        if (totalChapters > 0) {
            const currentChapterWeight = 1 / totalChapters;
            const baseProgress = (activeIndex / totalChapters);

            let chapterInsideProgress = 0;
            if (activeIndex === currentSpineIndex && parsedContent.length > 0) {
                let charsUpToActive = 0;
                const totalCharsInChapter = window.currentChapterTotalChars || 1;
                const limit = Math.min(activeSentenceIdx, parsedContent.length);
                for (let i = 0; i < limit; i++) {
                    charsUpToActive += (parsedContent[i].charLen || 1);
                }
                const progressInChapter = (charsUpToActive / totalCharsInChapter);
                chapterInsideProgress = progressInChapter * currentChapterWeight;
            }
            finalFraction = baseProgress + chapterInsideProgress;
        } else {
            finalFraction = 0;
        }
    }

// 4. ფორმატირება
    let displayPercentage = finalFraction * 100;
    if (displayPercentage < 0) displayPercentage = 0;
    if (displayPercentage > 100) displayPercentage = 100;

    // 5. UI განახლება (აუცილებლად!)
    if (badge) {
        const isCompleted = displayPercentage >= 99;
        badge.innerHTML = isCompleted ? `<span style="color:#10b981;font-weight:bold;margin-right:3px;">✓</span>${displayPercentage.toFixed(2)}%` : `${displayPercentage.toFixed(2)}%`;
        badge.classList.toggle('completed', isCompleted);
        badge.classList.remove('hidden'); // 🔥 ეს აჩენს ჰედერში ეგრევე
        const resetBtn = document.getElementById('reset-progress-btn');
        if (resetBtn) resetBtn.classList.remove('hidden');
    }

    // 6. 💾 SAVE FOR LIBRARY (აი ეს გვაკლდა!)
    // რადგან ჩვენ ვითვლით "შენახულ" (Real) ინდექსზე დაყრდნობით,
    // ამ შედეგების შენახვა უსაფრთხოა ბიბლიოთეკისთვის.
    localStorage.setItem('epub_perc_' + window.currentRawEpubFile.name, displayPercentage.toFixed(2));
    syncProgressToCloud(true); // Force sync to guarantee library gets latest percent
}
// Helper to determine logical chapter boundaries based on TOC entries
function getLogicalChapterBounds(spineIndex) {
    if (!currentBook || !currentBook.spine || !currentBook.spine.spineItems) {
        return { start: spineIndex, end: spineIndex + 1 };
    }
    
    let start = spineIndex;
    // Go backwards to find the nearest TOC entry
    while (start > 0) {
        const prevHref = currentBook.spine.spineItems[start].href;
        if (tocHrefSet.has(prevHref)) {
            break;
        }
        start--;
    }
    
    let end = currentBook.spine.spineItems.length;
    // Go forwards from start+1 to find the next TOC entry
    for (let i = start + 1; i < currentBook.spine.spineItems.length; i++) {
        const nextHref = currentBook.spine.spineItems[i].href;
        if (tocHrefSet.has(nextHref)) {
            end = i;
            break;
        }
    }
    
    return { start, end };
}

async function displayChapter(href, delay = 0) {
    if (!currentBook) return;
    
    let requestedSpineIndex = currentBook.spine.spineItems.findIndex(item => item.href === href || href.startsWith(item.href + '#'));
    if (requestedSpineIndex === -1) requestedSpineIndex = currentSpineIndex;
    if (requestedSpineIndex === -1) return;

    // Get logical chapter bounds
    const bounds = getLogicalChapterBounds(requestedSpineIndex);
    
    // Update global state to point to the START of this logical chapter
    currentSpineIndex = bounds.start;

    // 🔥 ამოღებულია: localStorage.setItem('epub_progress_'...)
    // დათვალიერება არ ინახავს პროგრესს!

    // განვლილი თავების ვიזუალური ჩაქრობა (Sidebar)
    updateSidebarStyling();

    window.scrollTo(0, 0);
    document.querySelectorAll('.toc-item').forEach(el => {
        el.classList.remove('active');
        if (href.includes(el.dataset.href) || el.dataset.href.includes(href)) {
            el.classList.add('active');
        }
    });

    setTtsStatus("Loading chapter text...");
    
    let fullBodyText = "";
    // Load all spine items in the logical chapter sequentially
    for (let i = bounds.start; i < bounds.end; i++) {
        try {
            const item = currentBook.spine.spineItems[i];
            const doc = await currentBook.load(item.href);
            const text = await extractTextFromDoc(doc, item.href, currentBook, true);
            if (text) {
                fullBodyText += text + "\n\n\n";
            }
        } catch (e) {
            console.error("Failed to load spine item " + i, e);
        }
    }
    
    setTtsStatus(null);
    processText(fullBodyText);

    // 🔥 GHOST DIMMING: თუ ვათვალიერებთ უკვე წაკითხულ თავს
    // ვამოწმებთ რეალურ შენახულ ინდექსთან
    const savedRealIndex = getRealSavedIndex();

    if (savedRealIndex !== -1 && currentSpineIndex < savedRealIndex) {
        // თუ ეს თავი რეალურ პოზიციაზე ნაკლებია -> სრულად ჩავაქროთ
        document.querySelectorAll('.sentence').forEach(el => {
            el.classList.add('read');
            el.classList.remove('active');
        });
    }
    // თუ ზუსტად იმ თავში ვართ, სადაც გავჩერდით -> აღვადგინოთ წინადადება
    else if (savedRealIndex === currentSpineIndex || savedRealIndex === -1) {
        const savedIdx = localStorage.getItem('epub_idx_' + window.currentRawEpubFile.name);
        if (savedIdx !== null && delay === 0) {
            currentIdx = parseInt(savedIdx);
            setTimeout(() => {
                highlightSentence(currentIdx, false); // false = არ შეინახო ხელახლა, უბრალოდ გაანათე
            }, 100);
        }
    }

    if (delay > 0) setTimeout(() => { playMergedQueue(); }, delay);
    else if (delay === -1) playMergedQueue();
}
// Protect sentence-ending punctuation inside header text so a header like
// "2. Chapter Title:" survives the sentence regex as ONE piece.
function protectHeaderPunct(s) {
    return s.replace(/\./g, '___DOT___').replace(/!/g, '___EXCL___').replace(/\?/g, '___QUEST___');
}

// Namespace-safe bold check: EPUB XHTML can serialize tags as
// <strong xmlns="http://www.w3.org/1999/xhtml">, which trips selector-based
// matching in XML documents — localName sidesteps that entirely.
function isBoldNode(node) {
    if (!node || node.nodeType !== 1) return false;
    const ln = (node.localName || '').toLowerCase();
    return ln === 'b' || ln === 'strong';
}

// Detects <p><strong>Header:</strong> normal text…</p>: a bold element that
// opens the paragraph AND is followed by real content. Whole-bold paragraphs
// are excluded here (the 70%-bold rule handles those).
function hasLeadingBoldHeader(p) {
    let node = p.firstChild;
    while (node && node.nodeType === 3 && !node.textContent.trim()) node = node.nextSibling;
    if (!isBoldNode(node) || !node.textContent.trim()) return false;
    let trailing = '';
    for (let sib = node.nextSibling; sib; sib = sib.nextSibling) trailing += sib.textContent;
    return trailing.trim().length > 0;
}

// Stamps the epub-header marker classes onto an existing attribute string,
// merging with any class attribute already present (a duplicate class attr
// would be dropped by the HTML parser, losing the marker).
function addHeaderClassToAttrs(attrs) {
    if (/class\s*=\s*"/i.test(attrs)) return attrs.replace(/class\s*=\s*"([^"]*)"/i, 'class="$1 epub-header epub-header-strong"');
    if (/class\s*=\s*'/i.test(attrs)) return attrs.replace(/class\s*=\s*'([^']*)'/i, "class='$1 epub-header epub-header-strong'");
    return attrs + ' class="epub-header epub-header-strong"';
}

function resolveEpubUrl(href, src) {
    let hrefParts = href.split('/');
    hrefParts.pop(); // remove file name
    let srcParts = src.split('/');
    for (let part of srcParts) {
        if (part === '.') continue;
        if (part === '..') {
            hrefParts.pop();
        } else {
            hrefParts.push(part);
        }
    }
    return hrefParts.join('/');
}

async function extractTextFromDoc(doc, itemHref = null, book = null, resolveImages = false) {
    if (!doc) return '';
    let root = doc.body || doc.documentElement;
    if (!root) return '';
    
    let fallbackText = root.textContent || '';
    
    try {
        let body = root.cloneNode(true);
        
        // Remove unwanted elements that could leak text or cause issues
        body.querySelectorAll('head, style, script, meta, link, noscript').forEach(el => {
            if (el.parentNode) el.parentNode.removeChild(el);
        });

        if (resolveImages && itemHref && book && book.archive) {
            let imgs = body.querySelectorAll('img');
            for (let img of imgs) {
                let src = img.getAttribute('src');
                if (src && !src.startsWith('blob:') && !src.startsWith('data:') && !src.startsWith('http')) {
                    try {
                        let absolutePath = resolveEpubUrl(itemHref, src);
                        let zipPath = absolutePath;
                        if (book.path && typeof book.path.resolve === 'function') {
                            zipPath = book.path.resolve(absolutePath);
                        }
                        let blobUrl = await book.archive.createUrl(zipPath);
                        if (!blobUrl && book.archive.zip && book.archive.zip.files) {
                            // Fallback: search zip files for suffix match
                            let keys = Object.keys(book.archive.zip.files);
                            let matchedKey = keys.find(k => k.endsWith(absolutePath) || k.endsWith(src.split('/').pop()));
                            if (matchedKey) blobUrl = await book.archive.createUrl(matchedKey);
                        }
                        if (blobUrl) img.setAttribute('src', blobUrl);
                    } catch(e) {}
                }
            }
        }
        
        let allElements = body.getElementsByTagName('*');
        const blockTags = new Set(['P', 'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'ASIDE', 'BLOCKQUOTE', 'FIGURE', 'FIGCAPTION', 'LI', 'DD', 'DT', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'TD', 'TH', 'CAPTION', 'TR']);
        
        // Process backwards to avoid messing up live HTMLCollection when injecting nodes
        // Wait, getElementsByTagName returns a live collection. 
        // We will convert it to an array first to safely iterate.
        let elArray = Array.from(allElements);
        
        for (let i = 0; i < elArray.length; i++) {
            let el = elArray[i];
            let tag = el.tagName.toUpperCase();
            
            // Process headers
            if (/^H[1-6]$/.test(tag)) {
                let safeText = protectHeaderPunct(el.textContent.trim());
                if (safeText) {
                    try {
                        el.innerHTML = `<b class="epub-header epub-header-${tag.toLowerCase()}">${safeText}</b>`;
                    } catch(e) {}
                }
            }
            
            // Block element newlines
            if (blockTags.has(tag)) {
                if (el.parentNode) {
                    el.parentNode.insertBefore(doc.createTextNode('\n\n'), el);
                    if (el.nextSibling) {
                        el.parentNode.insertBefore(doc.createTextNode('\n\n'), el.nextSibling);
                    } else {
                        el.parentNode.appendChild(doc.createTextNode('\n\n'));
                    }
                }
            }
            
            if (tag === 'BR') {
                if (el.parentNode) {
                    if (el.nextSibling) {
                        el.parentNode.insertBefore(doc.createTextNode(' '), el.nextSibling);
                    } else {
                        el.parentNode.appendChild(doc.createTextNode(' '));
                    }
                }
            }
        }

        let html = body.innerHTML;
        if (typeof html !== 'string') {
            try { html = new XMLSerializer().serializeToString(body); } catch(e) { html = ''; }
        }
        
        let text = html.replace(/<\/?(?!(b|strong|img)\b)[^>]+>/gi, ' ');
        
        text = text.replace(/&nbsp;/gi, ' ')
                   .replace(/&amp;/gi, '&')
                   .replace(/&lt;/gi, '<')
                   .replace(/&gt;/gi, '>')
                   .replace(/&quot;/gi, '"')
                   .replace(/&#39;/gi, "'");

        let textArray = text.split(/\n\n+/);
        let finalArray = [];
        
        textArray.forEach(block => {
            let t = block.trim();
            if (t.length > 0) {
                t = t.replace(/___SPLIT___/g, '')
                     .replace(/___DOT___/g, '.')
                     .replace(/___EXCL___/g, '!')
                     .replace(/___QUEST___/g, '?');
                     
                t = t.replace(/\s+/g, ' ').trim();
                
                if (t.length > 0) {
                    const lastChar = t.slice(-1);
                    const punctuation = ['.', '!', '?', ':', ';', '…', '"', '»', '”', '’', "'"];
                    if (!punctuation.includes(lastChar)) { t += '.'; }
                    finalArray.push(t);
                }
            }
        });
        
        let finalStr = finalArray.join('\n\n');
        if (!finalStr.trim()) return fallbackText.trim();
        return finalStr;
    } catch (err) {
        console.error("EPUB Extraction Error:", err);
        return fallbackText.trim();
    }
}
function handleNextChapterLogic() {
    // აქ ვშლით ინდექსს, რადგან გადავდივართ "წასაკითხად"
    if (window.currentRawEpubFile) {
        localStorage.removeItem('epub_idx_' + window.currentRawEpubFile.name);
    }
    if (!currentBook) return;
    
    const bounds = getLogicalChapterBounds(currentSpineIndex);
    const nextIndex = bounds.end;
    
    if (nextIndex < currentBook.spine.spineItems.length) {
        // ვინახავთ ახალ რეალურ პოზიციას
        localStorage.setItem('epub_progress_' + window.currentRawEpubFile.name, nextIndex);
        
        const nextItem = currentBook.spine.get(nextIndex);
        if (nextItem) {
            const wasPlaying = isPlaying;
            if (!wasPlaying) {
                displayChapter(nextItem.href, 0);
                return;
            }
            // 5 წამიანი შესვენება მთავარ თავზე გადასვლისას
            displayChapter(nextItem.href, 5000);
        }
    } else {
        stopReading();
    }
}
// --- SIDEBAR CONTROLS ---
function openSidebar() { 
    if (window.innerWidth >= 769) {
        sidebar.classList.remove('collapsed');
    } else {
        sidebar.classList.add('open'); 
        sidebarOverlay.classList.remove('hidden'); 
    }
}

function closeSidebar() { 
    if (window.innerWidth >= 769) {
        sidebar.classList.add('collapsed');
    } else {
        sidebar.classList.remove('open'); 
        sidebarOverlay.classList.add('hidden'); 
    }
}

sidebarToggleBtn.onclick = () => {
    if (window.innerWidth >= 769) {
        sidebar.classList.toggle('collapsed');
    } else {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
};

closeSidebarBtn.onclick = closeSidebar;
sidebarOverlay.onclick = closeSidebar;
// --- 5. TEXT PROCESSING ---
function updateProgressBar() {
    const progressBar = document.getElementById('progress-bar');
    if (parsedContent.length === 0) { progressBar.style.width = '0%'; return; }
    const progress = ((currentIdx + 1) / parsedContent.length) * 100;
    progressBar.style.width = `${progress}%`;
}
// Icons
const iconEdit = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`;
const iconSave = `<svg viewBox="0 0 24 24" fill="none" stroke="#09090b" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>`;
editBtn.onclick = () => {
    isEditMode = !isEditMode;
    if (isEditMode) {
        stopReading();
        contentArea.contentEditable = "true";
        contentArea.classList.add('edit-mode-active');
        contentArea.focus();
        const dz = document.getElementById('drop-zone');
        if(dz) dz.remove();
        const hub = document.getElementById('welcome-hub');
        if(hub) hub.remove();
        editBtn.innerHTML = `${iconSave} <span class="action-label">Save Text</span>`;
        editBtn.style.backgroundColor = '#38bdf8';
        editBtn.style.color = '#09090b';
    } else {
        contentArea.contentEditable = "false";
        contentArea.classList.remove('edit-mode-active');
        let paragraphsArray = [];
        if (contentArea.children.length > 0) {
            for (let child of contentArea.children) {
                let txt = child.innerText.trim();
                if (txt) paragraphsArray.push(txt);
            }
        }
        if (paragraphsArray.length === 0) {
            let txt = contentArea.innerText.trim();
            if (txt) paragraphsArray.push(txt);
        }
        const updatedText = paragraphsArray.join('\n\n');
        editBtn.innerHTML = `${iconEdit} <span class="action-label">Edit Text</span>`;
        editBtn.style.backgroundColor = '';
        editBtn.style.color = '';
        if(updatedText.length > 0) { processText(updatedText); }
        else { showDropZone(); }
    }
};
function showDropZone() {
    document.body.classList.remove('is-reading');
    const bookMeta = document.getElementById('book-meta-container');
    if (bookMeta) bookMeta.classList.add('hidden');
    if (sidebarToggleBtn) sidebarToggleBtn.classList.add('hidden');
    if (sidebar && sidebar.classList.contains('open')) closeSidebar();

    contentArea.innerHTML = `
    <div id="welcome-hub" class="welcome-hub">
        <div class="hub-hero">
            <div class="hub-badge">
                <span class="pulse-dot"></span>
                <span>AI Voice &amp; EPUB Reader</span>
            </div>
            <h1 class="hub-title">Ready to <span class="gradient-text">Listen &amp; Read?</span></h1>
            <p class="hub-subtitle">Choose an option below to start your immersive reading experience</p>
        </div>

        <div class="hub-grid">
            <button id="hub-library-btn" class="hub-card" type="button">
                <div class="hub-card-icon icon-library">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                </div>
                <div class="hub-card-content">
                    <div class="hub-card-title">Library</div>
                    <div class="hub-card-desc">Browse &amp; resume saved books</div>
                </div>
                <div class="hub-card-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
            </button>

            <button id="hub-open-epub-btn" class="hub-card" type="button">
                <div class="hub-card-icon icon-epub">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                </div>
                <div class="hub-card-content">
                    <div class="hub-card-title">Open EPUB</div>
                    <div class="hub-card-desc">Choose or drop an .epub file</div>
                </div>
                <div class="hub-card-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
            </button>

            <button id="hub-edit-text-btn" class="hub-card" type="button">
                <div class="hub-card-icon icon-edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </div>
                <div class="hub-card-content">
                    <div class="hub-card-title">Edit / Paste Text</div>
                    <div class="hub-card-desc">Type, paste or edit any text</div>
                </div>
                <div class="hub-card-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
            </button>

            <button id="hub-settings-btn" class="hub-card" type="button">
                <div class="hub-card-icon icon-settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </div>
                <div class="hub-card-content">
                    <div class="hub-card-title">Settings</div>
                    <div class="hub-card-desc">Voices, speed &amp; pause tuning</div>
                </div>
                <div class="hub-card-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
            </button>
        </div>

        <div class="hub-drop-hint">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="12" y2="12"></line>
                <line x1="15" y1="15" x2="12" y2="12"></line>
            </svg>
            <span>Or drag &amp; drop an EPUB file anywhere on this screen</span>
        </div>
    </div>`;
    bindHubEvents();
}
// Helpers


function getPiperWorkerUrl() {
    // Same resolution strategy as WordEvo (WORDEVO_ASSET_PATH): PHP injects THEME_URI,
    // hardcoded path only as a last-resort fallback
    const base = window.THEME_URI || '/wp-content/themes/zurabkostava';
    return base + '/WordEvo/piper-worker.js?v=' + Date.now();
}

function setTtsStatus(message) {
    const ttsIndicator = document.getElementById('tts-status-indicator');
    const ttsStatusText = document.getElementById('tts-status-text');
    if (!ttsIndicator || !ttsStatusText) return;
    if (message) {
        ttsIndicator.classList.remove('hidden');
        ttsStatusText.textContent = message;
    } else {
        ttsIndicator.classList.add('hidden');
    }
}

function initPiperWorker(langCode, voicePath) {
    if (!piperWorkers[langCode]) piperWorkers[langCode] = { worker: null, ready: false, initializing: false, currentAudio: null, voicePath: null, pending: [] };
    const state = piperWorkers[langCode];
    if (!Array.isArray(state.pending)) state.pending = [];
    const rejectPending = (err) => { while (state.pending.length) { try { state.pending.shift().reject(err); } catch (e) {} } };
    // Reuse the worker only while it's healthy (ready or still initializing).
    // After a failure the state is wiped below, so a re-click actually retries.
    if (state.voicePath === voicePath && state.worker && (state.ready || state.initializing)) return;
    if (state.worker) state.worker.terminate();
    rejectPending(new Error('Piper worker restarted'));
    state.ready = false; state.initializing = true; state.voicePath = voicePath;

    const failInit = (message) => {
        state.initializing = false;
        state.ready = false;
        if (state.worker) { state.worker.terminate(); state.worker = null; }
        state.voicePath = null; // allows a clean retry via the Download button
        rejectPending(new Error(message));
        console.error("Piper init failed:", message);
        setTtsStatus("❌ " + message);
        setTimeout(() => setTtsStatus(null), 6000);
        const unifiedDlBtn = document.getElementById('unified-download-btn');
        const unifiedLangSelect = document.getElementById('unified-lang-select');
        if (unifiedDlBtn && unifiedLangSelect && unifiedLangSelect.value === langCode) {
            unifiedDlBtn.textContent = "🔁 Retry Download";
            unifiedDlBtn.disabled = false;
            unifiedDlBtn.style.opacity = "1";
        }
        stopReading(); // გააჩეროს გაჭედილი attemptPlay ციკლი
    };

    let worker;
    try {
        worker = new Worker(getPiperWorkerUrl());
    } catch (e) {
        failInit("Worker creation failed: " + (e.message || e));
        return;
    }
    state.worker = worker;
    setTtsStatus("Initializing Neural Voice...");

    // Fires when the worker script itself can't load (404/MIME/network) or throws at top level.
    // Without this the UI waits for a 'ready' message that will never come.
    worker.onerror = (e) => {
        failInit(e.message || "Voice worker script failed to load — check " + getPiperWorkerUrl());
    };

    worker.onmessage = (e) => {
        const msg = e.data || {};
        if (msg.kind === 'status') {
            setTtsStatus(msg.message);
            const unifiedDlBtn = document.getElementById('unified-download-btn');
            const unifiedLangSelect = document.getElementById('unified-lang-select');
            if (unifiedDlBtn && unifiedLangSelect && unifiedLangSelect.value === langCode && !state.ready) {
                unifiedDlBtn.textContent = "⏳ " + msg.message;
            }
        } else if (msg.kind === 'progress') {
            const dlBtn = document.getElementById('unified-download-btn');
            if (dlBtn && msg.info && !state.ready) {
                if (msg.info.status === 'progress' && msg.info.total) {
                    const loadedMB = (msg.info.loaded / 1024 / 1024).toFixed(1);
                    const totalMB = (msg.info.total / 1024 / 1024).toFixed(1);
                    dlBtn.textContent = `⏳ Downloading ${msg.info.file}: ${loadedMB}/${totalMB} MB`;
                }
            }
        }
        else if (msg.kind === 'ready') {
            state.ready = true;
            state.initializing = false;
            try { localStorage.setItem('piper_downloaded_' + voicePath, 'true'); } catch (e) {}
            setTtsStatus(null);

            const unifiedDlBtn = document.getElementById('unified-download-btn');
            const unifiedVoiceSelect = document.getElementById('unified-voice-select');
            const currentVoiceName = unifiedVoiceSelect ? unifiedVoiceSelect.value : '';
            const isMatch = (voicePath === state.voicePath) || (isNatiaVoice(currentVoiceName) && isNatiaVoice(voicePath));
            if (unifiedDlBtn && isMatch) {
                unifiedDlBtn.textContent = "✅ Voice Ready";
                unifiedDlBtn.style.opacity = "0.5";
                unifiedDlBtn.disabled = true;
                unifiedDlBtn.style.background = "transparent";
                unifiedDlBtn.style.border = "1px solid rgba(255,255,255,0.1)";
                unifiedDlBtn.style.color = "var(--text-muted)";
            }
        }
        else if (msg.kind === 'error') {
            if (!state.ready) {
                // Error during init (model download, WASM load...) — reset so retry works
                failInit(msg.message);
            } else {
                // Runtime synthesis error: reject only the affected sentence request,
                // the playback loop decides whether to retry/skip — don't kill playback here
                console.error("Piper Error:", msg.message);
                const p = state.pending.shift();
                if (p) {
                    p.reject(new Error(msg.message));
                } else {
                    setTtsStatus("Error: " + msg.message);
                    setTimeout(() => setTtsStatus(null), 5000);
                    stopReading();
                }
            }
        }
        else if (msg.kind === 'output' && msg.wav) {
            const p = state.pending.shift();
            if (p) p.resolve(msg.wav);
        }
    };
    worker.postMessage({ kind: 'init', voicePath: voicePath });
}

function stopPiperAudio() {
    Object.values(piperWorkers).forEach(state => {
        if (state.currentAudio) { state.currentAudio.pause(); state.currentAudio = null; }
        if (state.worker) { state.worker.postMessage({ kind: 'clear' }); }
        while (state.pending && state.pending.length > 0) {
            const p = state.pending.shift();
            if (p && p.reject) p.reject(new Error('Stopped'));
        }
    });
}


// 'ka_GE', 'ka-GE', 'KA-ge' → all normalize to 'ka-ge' so they match langCode 'ka'
function normalizeLang(code) {
    return String(code || '').toLowerCase().replace(/_/g, '-').trim();
}
function langMatches(voiceLang, langCode) {
    const v = normalizeLang(voiceLang);
    const base = normalizeLang(langCode);
    if (!v || !base) return false;
    return v === base || v.startsWith(base + '-');
}

function isNatiaVoice(name) {
    if (!name || typeof name !== 'string') return false;
    return name.includes('Natia') || name.includes('natia') || name.includes('ka_GE-natia') || name.includes('ქართული ფონეტიკური');
}

function findPiperVoice(voiceName) {
    if (!voiceName) return null;
    if (isNatiaVoice(voiceName)) {
        return piperVoicesList.find(v => v.key === 'ka_GE-natia-medium') || PIPER_FALLBACK_VOICES[0];
    }
    return piperVoicesList.find(v => v && v.name === voiceName) || null;
}

function autoWarmupSavedPiperVoices() {
    if (!localStorage.getItem('voice-ka')) {
        try { localStorage.setItem('voice-ka', '☁️ Piper — Georgian (Natia, medium)'); } catch (e) {}
    }

    const savedUiLang = localStorage.getItem('unified-ui-lang') || 'ka';
    const langsToCheck = new Set(['ka', savedUiLang]);
    if (detectedBookLanguages) {
        detectedBookLanguages.forEach(l => langsToCheck.add(String(l).split(/[-_]/)[0].toLowerCase()));
    }

    langsToCheck.forEach(lang => {
        const vName = localStorage.getItem(`voice-${lang}`);
        if (vName) {
            const pVoice = findPiperVoice(vName);
            if (pVoice) {
                const workerLang = (isNatiaVoice(pVoice.name) || pVoice.key === 'ka_GE-natia-medium') ? 'ka' : lang;
                const isDownloaded = localStorage.getItem('piper_downloaded_' + pVoice.path) === 'true';
                if (isDownloaded || isNatiaVoice(pVoice.name)) {
                    const state = piperWorkers[workerLang];
                    if (!state || (!state.ready && !state.initializing)) {
                        initPiperWorker(workerLang, pVoice.path);
                    }
                }
            }
        }
    });
}

async function checkAndMarkCachedPiperVoices() {
    if (!('caches' in window)) return;
    try {
        const cache = await caches.open('piper-models-cache-v1');
        const keys = await cache.keys();
        keys.forEach(req => {
            const url = req.url || '';
            piperVoicesList.forEach(v => {
                if (v && v.path && url.includes(v.path)) {
                    try { localStorage.setItem('piper_downloaded_' + v.path, 'true'); } catch (e) {}
                }
            });
        });
        autoWarmupSavedPiperVoices();
        const dlBtn = document.getElementById('unified-download-btn');
        const vSelect = document.getElementById('unified-voice-select');
        if (dlBtn && vSelect) {
            const pVoice = findPiperVoice(vSelect.value);
            if (pVoice) {
                const isDownloaded = localStorage.getItem('piper_downloaded_' + pVoice.path) === 'true';
                const workerLang = (isNatiaVoice(pVoice.name) || pVoice.key === 'ka_GE-natia-medium') ? 'ka' : (document.getElementById('unified-lang-select')?.value || 'ka');
                const state = piperWorkers[workerLang];
                if (state && state.ready) {
                    dlBtn.textContent = "✅ Voice Ready";
                    dlBtn.style.opacity = "0.5"; dlBtn.disabled = true;
                } else if (isDownloaded && (!state || (!state.ready && !state.initializing))) {
                    initPiperWorker(workerLang, pVoice.path);
                }
            }
        }
    } catch (e) {
        console.warn('Cache check note:', e);
    }
}

function rebuildDynamicSettings() {
    const container = dynamicVoiceSettings || document.getElementById('dynamic-voice-settings');
    if (!container) {
        console.warn('rebuildDynamicSettings: #dynamic-voice-settings container not found in DOM');
        return;
    }
    container.innerHTML = '';

    const nativeList = (Array.isArray(voices) ? voices : Array.from(voices || [])).filter(v => v && typeof v.name === 'string' && typeof v.lang === 'string');
    if (!Array.isArray(piperVoicesList)) piperVoicesList = [];
    const piperList = piperVoicesList.filter(isValidPiperEntry);

    const getBaseLang = (l) => String(l || '').split(/[-_]/)[0].toLowerCase().trim();
    
    const allLangs = new Set();
    // Always include our main languages
    allLangs.add('ka');
    allLangs.add('en');
    allLangs.add('ru');
    const savedUiLang = localStorage.getItem('unified-ui-lang');
    if (savedUiLang) allLangs.add(savedUiLang.toLowerCase());

    if (detectedBookLanguages) {
        detectedBookLanguages.forEach(l => allLangs.add(getBaseLang(l)));
    }
    nativeList.forEach(v => {
        if (v.lang) allLangs.add(getBaseLang(v.lang));
    });
    piperList.forEach(v => {
        if (v.lang) allLangs.add(getBaseLang(v.lang));
    });
    
    const sortedLangs = Array.from(allLangs).sort((a, b) => {
        const aDet = detectedBookLanguages && detectedBookLanguages.has(a);
        const bDet = detectedBookLanguages && detectedBookLanguages.has(b);
        if (aDet && !bDet) return -1;
        if (!aDet && bDet) return 1;
        if (a === 'ka' && b !== 'ka') return -1;
        if (b === 'ka' && a !== 'ka') return 1;
        if (a === 'en' && b !== 'en') return -1;
        if (b === 'en' && a !== 'en') return 1;
        return a.localeCompare(b);
    });

    const wrapper = document.createElement('div');
    wrapper.className = 'settings-card-section';
    wrapper.innerHTML = `
        <div class="settings-section-badge">
            <span class="settings-section-icon">🎙️</span>
            <span class="settings-section-title">Voice & Speech Engine</span>
        </div>
        <div class="setting-group">
            <label>Select Language to Configure</label>
            <div class="select-wrapper">
                <select id="unified-lang-select" class="settings-select"></select>
                <div class="select-chevron">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>
        </div>
        <div class="setting-group">
            <label>Selected Voice</label>
            <div class="select-wrapper">
                <select id="unified-voice-select" class="settings-select"></select>
                <div class="select-chevron">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>
            <button id="unified-download-btn" class="hidden settings-download-btn">📥 Download / Init Voice</button>
        </div>
        <div class="setting-group slider-group">
            <div class="slider-header-row">
                <label>Reading Speed</label>
                <span class="slider-val-badge" id="unified-rate-val">1x</span>
            </div>
            <input type="range" class="settings-slider" id="unified-rate-input" min="0.5" max="4" step="0.1" value="1">
        </div>
        <div class="setting-group" style="margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.07);">
            <label class="settings-toggle-label">
                <input type="checkbox" id="skip-parentheses-checkbox" class="settings-toggle-checkbox" ${skipParenthesesSetting ? 'checked' : ''}>
                <span class="settings-toggle-custom"></span>
                <div class="toggle-text-block">
                    <span class="toggle-title">🚫 Skip Parentheses ( )</span>
                    <span class="toggle-desc">Automatically skips text enclosed in parentheses during reading</span>
                </div>
            </label>
        </div>
    `;
    container.appendChild(wrapper);

    const langSelect = wrapper.querySelector('#unified-lang-select');
    const voiceSelect = wrapper.querySelector('#unified-voice-select');
    const dlBtn = wrapper.querySelector('#unified-download-btn');
    const rateInput = wrapper.querySelector('#unified-rate-input');
    const rateVal = wrapper.querySelector('#unified-rate-val');

    sortedLangs.forEach(langCode => {
        const opt = document.createElement('option');
        opt.value = langCode;
        let displayLang = langCode.toUpperCase();
        try { displayLang = new Intl.DisplayNames(['en'], { type: 'language' }).of(langCode.split('-')[0]) || displayLang; } catch(e){}
        const isDetected = detectedBookLanguages.has(langCode) || detectedBookLanguages.has(langCode.split('-')[0]);
        opt.textContent = isDetected ? `📍 ${displayLang} (In Book)` : displayLang;
        langSelect.appendChild(opt);
    });

    if (savedUiLang && Array.from(langSelect.options).some(o => o.value === savedUiLang)) {
        langSelect.value = savedUiLang;
    } else if (detectedBookLanguages && detectedBookLanguages.size > 0) {
        const firstDet = Array.from(detectedBookLanguages)[0];
        if (Array.from(langSelect.options).some(o => o.value === firstDet)) {
            langSelect.value = firstDet;
        } else if (langSelect.options.length > 0) {
            langSelect.selectedIndex = 0;
        }
    } else if (langSelect.options.length > 0) {
        langSelect.selectedIndex = 0;
    }

    function updateVoiceDropdown() {
        const currentLang = langSelect.value;
        if (!currentLang) return;
        try { localStorage.setItem('unified-ui-lang', currentLang); } catch(e){}

        voiceSelect.innerHTML = '';
        const nativeVoices = nativeList.filter(v => langMatches(v.lang, currentLang) || (v.name && v.name.toLowerCase().includes('multilingual')));
        if (nativeVoices.length > 0) {
            nativeVoices.sort((a, b) => {
                const isPremium = (v) => /natural|online|neural|premium|enhanced/i.test(v.name);
                const aP = isPremium(a);
                const bP = isPremium(b);
                if (aP && !bP) return -1;
                if (!aP && bP) return 1;
                return a.name.localeCompare(b.name);
            });
            
            const optGroup = document.createElement('optgroup');
            optGroup.label = "Native Browser Voices";
            nativeVoices.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.name;
                let textName = v.name;
                if (/natural|online|neural|premium|enhanced/i.test(v.name)) {
                    textName = `✨ ${textName}`;
                } else if (v.name.toLowerCase().includes('multilingual')) {
                    textName = `🌐 ${textName}`;
                }
                opt.textContent = textName;
                optGroup.appendChild(opt);
            });
            voiceSelect.appendChild(optGroup);
        }

        const piperForLang = piperList.filter(v => langMatches(v.lang, currentLang)).map(v => ({ ...v }));
        // If currentLang is English ('en'), offer Natia as a Georgian phonetic voice option!
        if (currentLang === 'en') {
            const natiaVoice = piperList.find(v => v.key === 'ka_GE-natia-medium') || PIPER_FALLBACK_VOICES[0];
            if (natiaVoice && !piperForLang.some(v => isNatiaVoice(v.name) || v.key === natiaVoice.key)) {
                piperForLang.push({
                    ...natiaVoice,
                    name: natiaVoice.name,
                    displayName: '🇬🇪 Natia (ქართული ფონეტიკური ტრანსლიტერაციით)'
                });
            }
        }
        if (piperForLang.length > 0) {
            const optGroup = document.createElement('optgroup');
            optGroup.label = "Piper Offline Voices";
            piperForLang.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.name;
                opt.textContent = v.displayName || v.name;
                optGroup.appendChild(opt);
            });
            voiceSelect.appendChild(optGroup);
        }
        
        const googleOptGroup = document.createElement('optgroup');
        googleOptGroup.label = "☁️ Free Cloud (Google)";
        const gOpt = document.createElement('option');
        gOpt.value = 'google:standard';
        gOpt.textContent = "☁️ Google Translate TTS (Standard)";
        googleOptGroup.appendChild(gOpt);

        voiceSelect.appendChild(googleOptGroup);

        // --- Determine the selected voice for currentLang ---
        let savedVoice = localStorage.getItem(`voice-${currentLang}`);

        // Default for Georgian ('ka') is ALWAYS Natia!
        if (currentLang === 'ka') {
            if (!savedVoice || savedVoice.startsWith('google:')) {
                savedVoice = '☁️ Piper — Georgian (Natia, medium)';
                try { localStorage.setItem('voice-ka', savedVoice); } catch(e){}
            }
        }

        const isSavedNatia = isNatiaVoice(savedVoice);

        // Find matching option in the dropdown
        let matchedOpt = null;
        for (let opt of voiceSelect.options) {
            if (savedVoice && opt.value === savedVoice) {
                matchedOpt = opt;
                break;
            }
            if (isSavedNatia && (isNatiaVoice(opt.value) || isNatiaVoice(opt.textContent))) {
                matchedOpt = opt;
                break;
            }
        }

        if (matchedOpt) {
            voiceSelect.value = matchedOpt.value;
            try { localStorage.setItem(`voice-${currentLang}`, matchedOpt.value); } catch(e){}
        } else if (savedVoice) {
            // savedVoice exists, but its option hasn't loaded yet into voiceSelect (e.g. async fetch).
            // Retain it! Add a temporary option so voiceSelect keeps the value and doesn't get overwritten!
            const tempOpt = document.createElement('option');
            tempOpt.value = savedVoice;
            tempOpt.textContent = isSavedNatia ? '🇬🇪 Natia (ქართული ფონეტიკური ტრანსლიტერაციით)' : savedVoice;
            tempOpt.selected = true;
            voiceSelect.insertBefore(tempOpt, voiceSelect.firstChild);
            voiceSelect.value = savedVoice;
        } else {
            // First time ever: pick best default
            const piperOpt = Array.from(voiceSelect.options).find(o => o.parentElement && o.parentElement.label === "Piper Offline Voices");
            if (piperOpt) {
                voiceSelect.value = piperOpt.value;
            } else if (voiceSelect.options.length > 0) {
                voiceSelect.selectedIndex = 0;
            }
            if (voiceSelect.value) {
                try { localStorage.setItem(`voice-${currentLang}`, voiceSelect.value); } catch(e){}
            }
        }

        const savedRate = localStorage.getItem(`rate-${currentLang}`) || '1';
        rateInput.value = savedRate;
        rateVal.textContent = savedRate + 'x';
        updateDlBtn();
    }

    function updateDlBtn() {
        const currentLang = langSelect.value;
        const selectedVoice = voiceSelect.value;
        const chosenPiper = piperList.find(v => v.name === selectedVoice || (isNatiaVoice(selectedVoice) && isNatiaVoice(v.name)));
        
        if (chosenPiper) {
            dlBtn.classList.remove('hidden');
            let workerLang = currentLang;
            if (isNatiaVoice(chosenPiper.name) || chosenPiper.key === 'ka_GE-natia-medium') {
                workerLang = 'ka';
            }
            const state = piperWorkers[workerLang];
            const isDownloaded = localStorage.getItem('piper_downloaded_' + chosenPiper.path) === 'true';

            if (state && state.voicePath === chosenPiper.path && state.ready) {
                dlBtn.textContent = "✅ Voice Ready";
                dlBtn.style.opacity = "0.5"; dlBtn.disabled = true;
                dlBtn.style.background = "transparent"; dlBtn.style.border = "1px solid rgba(255,255,255,0.1)"; dlBtn.style.color = "var(--text-muted)";
            } else if (state && state.voicePath === chosenPiper.path && state.initializing) {
                dlBtn.textContent = "⏳ Initializing Voice...";
                dlBtn.style.opacity = "0.7"; dlBtn.disabled = true;
                dlBtn.style.background = "rgba(56, 189, 248, 0.1)"; dlBtn.style.border = "1px solid rgba(56, 189, 248, 0.3)"; dlBtn.style.color = "#38bdf8";
            } else if (isDownloaded) {
                // If previously downloaded, auto-activate worker so it is ready right away!
                dlBtn.textContent = "⏳ Activating Cached Voice...";
                dlBtn.style.opacity = "0.7"; dlBtn.disabled = true;
                dlBtn.style.background = "rgba(56, 189, 248, 0.1)"; dlBtn.style.border = "1px solid rgba(56, 189, 248, 0.3)"; dlBtn.style.color = "#38bdf8";
                initPiperWorker(workerLang, chosenPiper.path);
            } else {
                dlBtn.textContent = "📥 Download / Init Voice";
                dlBtn.style.opacity = "1"; dlBtn.disabled = false;
                dlBtn.style.background = "rgba(56, 189, 248, 0.1)"; dlBtn.style.border = "1px solid rgba(56, 189, 248, 0.3)"; dlBtn.style.color = "#38bdf8";
            }
        } else {
            dlBtn.classList.add('hidden');
        }
    }

    langSelect.addEventListener('change', updateVoiceDropdown);

    voiceSelect.addEventListener('change', (e) => {
        const currentLang = langSelect.value;
        if(currentLang) {
            try { localStorage.setItem(`voice-${currentLang}`, e.target.value); } catch(err) {}
            updateDlBtn();
        }
    });

    dlBtn.addEventListener('click', () => {
        const currentLang = langSelect.value;
        const selectedVoice = voiceSelect.value;
        
        const chosen = piperList.find(v => v.name === selectedVoice || (isNatiaVoice(selectedVoice) && isNatiaVoice(v.name)));
        if (currentLang && chosen) {
            let workerLang = currentLang;
            if (isNatiaVoice(chosen.name) || chosen.key === 'ka_GE-natia-medium') {
                workerLang = 'ka';
            }
            initPiperWorker(workerLang, chosen.path);
            dlBtn.textContent = "⏳ Initializing...";
            dlBtn.disabled = true; dlBtn.style.opacity = "0.7";
        }
    });

    rateInput.addEventListener('input', (e) => {
        const currentLang = langSelect.value;
        if(currentLang) {
            rateVal.textContent = e.target.value + 'x';
            try { localStorage.setItem(`rate-${currentLang}`, e.target.value); } catch(err) {}
        }
    });

    const skipParenCheckbox = wrapper.querySelector('#skip-parentheses-checkbox');
    if (skipParenCheckbox) {
        skipParenCheckbox.addEventListener('change', (e) => {
            skipParenthesesSetting = e.target.checked;
            try { localStorage.setItem('tts-skip-parentheses', skipParenthesesSetting ? 'true' : 'false'); } catch(err) {}
            if (isPlaying) {
                playMergedQueue();
            }
        });
    }

    updateVoiceDropdown();

    // Add Global Pause Settings
    const pauseWrapper = document.createElement('div');
    pauseWrapper.className = 'settings-card-section';
    pauseWrapper.style.marginTop = '16px';
    pauseWrapper.innerHTML = `
        <div class="settings-section-badge">
            <span class="settings-section-icon">⏱️</span>
            <span class="settings-section-title">Pause Durations</span>
        </div>
        <div class="setting-group slider-group">
            <div class="slider-header-row">
                <label>Main Header</label>
                <span class="slider-val-badge" id="val-pause-main">${pauseSettings.mainHeader} ms</span>
            </div>
            <input type="range" class="settings-slider" id="input-pause-main" min="0" max="10000" step="50" value="${pauseSettings.mainHeader}">
        </div>
        <div class="setting-group slider-group">
            <div class="slider-header-row">
                <label>Post-Header</label>
                <span class="slider-val-badge" id="val-pause-post">${pauseSettings.postHeader} ms</span>
            </div>
            <input type="range" class="settings-slider" id="input-pause-post" min="0" max="10000" step="50" value="${pauseSettings.postHeader}">
        </div>
        <div class="setting-group slider-group">
            <div class="slider-header-row">
                <label>Paragraph</label>
                <span class="slider-val-badge" id="val-pause-paragraph">${pauseSettings.paragraph} ms</span>
            </div>
            <input type="range" class="settings-slider" id="input-pause-paragraph" min="0" max="10000" step="50" value="${pauseSettings.paragraph}">
        </div>
    `;
    container.appendChild(pauseWrapper);

    const bindPauseInput = (id, key, valId) => {
        const input = pauseWrapper.querySelector('#' + id);
        const valSpan = pauseWrapper.querySelector('#' + valId);
        if (input) {
            input.addEventListener('input', (e) => {
                const v = parseInt(e.target.value);
                if (valSpan) valSpan.textContent = `${v} ms`;
                pauseSettings[key] = v;
                try { localStorage.setItem('ttsPauseSettings', JSON.stringify(pauseSettings)); } catch(err) {}
            });
        }
    };
    bindPauseInput('input-pause-main', 'mainHeader', 'val-pause-main');
    bindPauseInput('input-pause-post', 'postHeader', 'val-pause-post');
    bindPauseInput('input-pause-paragraph', 'paragraph', 'val-pause-paragraph');
}

// --- TTS Engine Auto-Wakeup for Android, Microsoft Edge, and Chrome ---
let speechEngineWarmedUp = false;

function wakeUpSpeechEngine() {
    const synth = window.speechSynthesis || synthesis;
    if (!synth) return;

    try {
        if (synth.paused) {
            synth.resume();
        }

        // Silent micro-utterance to force Edge & Android to spin up the speech synthesis service
        const kick = new SpeechSynthesisUtterance(' ');
        kick.volume = 0.001; // Tiny volume so it doesn't get stripped
        kick.rate = 10;
        kick.onend = () => { loadVoices(); };
        kick.onerror = () => { loadVoices(); };
        synth.speak(kick);

        const liveList = synth.getVoices();
        if (liveList && liveList.length > 0) {
            const filtered = Array.from(liveList).filter(v => v && typeof v.name === 'string');
            if (filtered.length > 0) {
                voices = filtered;
                rebuildDynamicSettings();
            }
        }
    } catch (e) {
        console.warn('Speech engine warmup note:', e);
    }
}

// Global user-interaction listener: unlocks audio & TTS on the very first touch/click
const triggerUserGestureWarmup = () => {
    wakeUpSpeechEngine();
    loadVoices();
};
window.addEventListener('touchstart', triggerUserGestureWarmup, { once: true, passive: true });
window.addEventListener('pointerdown', triggerUserGestureWarmup, { once: true, passive: true });
window.addEventListener('click', triggerUserGestureWarmup, { once: true, passive: true });

let voiceLoadAttempts = 0;
function loadVoices() {
    const synth = window.speechSynthesis || synthesis;
    let list = [];
    try { list = synth ? synth.getVoices() : []; } catch (e) { console.warn('getVoices failed', e); }
    const filtered = Array.from(list || []).filter(v => v && typeof v.name === 'string');

    if (filtered.length > 0) {
        voices = filtered;
        rebuildDynamicSettings();
    } else if (voiceLoadAttempts < 25) {
        voiceLoadAttempts++;
        // Proactively stimulate the TTS engine if voices haven't loaded yet
        try {
            if (synth && synth.paused) synth.resume();
            const kick = new SpeechSynthesisUtterance(' ');
            kick.volume = 0.001;
            kick.rate = 10;
            synth.speak(kick);
        } catch(e) {}
        setTimeout(loadVoices, 250);
    }
}

const ROMAN_ORDINALS = {
    'XXX': 'ოცდამეათე', 'XXIX': 'ოცდამეცხრე', 'XXVIII': 'ოცდამეთვრამეტე', 'XXVII': 'ოცდამეჩვიდმეტე',
    'XXVI': 'ოცდამეთექვსმეტე', 'XXV': 'ოცდამეხუთე', 'XXIV': 'ოცდამეოთხე', 'XXIII': 'ოცდამესამე',
    'XXII': 'ოცდამეორე', 'XXI': 'ოცდამეერთე', 'XX': 'მეოცე', 'XIX': 'მეცხრამეტე', 'XVIII': 'მეთვრამეტე',
    'XVII': 'მეჩვიდმეტე', 'XVI': 'მეთექვსმეტე', 'XV': 'მეთხუთმეტე', 'XIV': 'მეთოთხმეტე',
    'XIII': 'მეცამეტე', 'XII': 'მეთორმეტე', 'XI': 'მეთერთმეტე', 'X': 'მეათე', 'IX': 'მეცხრე',
    'VIII': 'მერვე', 'VII': 'მეშვიდე', 'VI': 'მეექვსე', 'V': 'მეხუთე', 'IV': 'მეოთხე',
    'III': 'მესამე', 'II': 'მეორე', 'I': 'პირველი',
    'XL': 'მეორმოცე', 'L': 'ორმოცდამეათე', 'LX': 'მესამოცე', 'LXX': 'სამოცდამეათე',
    'LXXX': 'მეოთხმოცე', 'XC': 'ოთხმოცდამეათე', 'C': 'მეასე', 'D': 'მეხუთასე', 'M': 'მეათასე'
};

function numToGeorgian(num) {
    let n = parseInt(num, 10);
    if (isNaN(n) || n === 0) return "ნული";
    const units = ["", "ერთ", "ორ", "სამ", "ოთხ", "ხუთ", "ექვს", "შვიდ", "რვ", "ცხრ"];
    const baseTens = { 20: "ოც", 40: "ორმოც", 60: "სამოც", 80: "ოთხმოც" };
    function convertUnder20(k, isFinal) {
        k = parseInt(k, 10);
        if (k === 0) return "";
        if (k < 10) {
            let suffix = (k === 8 || k === 9) ? "ა" : "ი";
            return units[k] + (isFinal ? suffix : "");
        }
        const teens = ["ათ", "თერთმეტ", "თორმეტ", "ცამეტ", "თოთხმეტ", "თხუთმეტ", "თექვსმეტ", "ჩვიდმეტ", "თვრამეტ", "ცხრამეტ"];
        return teens[k - 10] + (isFinal ? "ი" : "");
    }
    function convertUnder100(k, isFinal) {
        if (k < 20) return convertUnder20(k, isFinal);
        let rem = k % 20;
        let tenKey = k - rem;
        let prefix = baseTens[tenKey];
        if (rem === 0) return prefix + (isFinal ? "ი" : "");
        return prefix + "და" + convertUnder20(rem, isFinal);
    }
    function convertUnder1000(k, isFinal) {
        if (k < 100) return convertUnder100(k, isFinal);
        let hundreds = Math.floor(k / 100);
        let rem = k % 100;
        let prefix = (hundreds === 1 ? "" : units[hundreds]) + "ას";
        if (rem === 0) return prefix + (isFinal ? "ი" : "");
        return prefix + " " + convertUnder100(rem, isFinal);
    }
    let result = "";
    if (n >= 1000000000) {
        let bill = Math.floor(n / 1000000000);
        n %= 1000000000;
        result += (bill === 1 ? "" : convertUnder1000(bill, false)) + (n === 0 ? " მილიარდი " : " მილიარდ ");
    }
    if (n >= 1000000) {
        let mill = Math.floor(n / 1000000);
        n %= 1000000;
        result += (mill === 1 ? "" : convertUnder1000(mill, false)) + (n === 0 ? " მილიონი " : " მილიონ ");
    }
    if (n >= 1000) {
        let thou = Math.floor(n / 1000);
        n %= 1000;
        result += (thou === 1 ? "" : convertUnder1000(thou, false)) + (n === 0 ? " ათასი " : " ათას ");
    }
    if (n > 0) {
        result += convertUnder1000(n, true);
    }
    return result.trim();
}

function numToGeorgianOrdinal(num) {
    let n = parseInt(num, 10);
    if (isNaN(n) || n <= 0) return numToGeorgian(n);
    if (n === 1) return "პირველი";
    if (n === 8) return "მერვე";
    if (n === 9) return "მეცხრე";
    let cardinal = numToGeorgian(n);
    let words = cardinal.split(/\s+/);
    let last = words[words.length - 1];
    let lastOrd = "";
    if (last.includes("და") && !last.startsWith("და")) {
        let parts = last.split("და");
        let prefix = parts.slice(0, -1).join("და") + "და";
        let remStr = parts[parts.length - 1];
        let remN = n % 20;
        if (remN === 1) {
            lastOrd = prefix + "მეერთე";
        } else if (remN === 8) {
            lastOrd = prefix + "მერვე";
        } else if (remN === 9) {
            lastOrd = prefix + "მეცხრე";
        } else if (remStr.endsWith("ი")) {
            lastOrd = prefix + "მე" + remStr.slice(0, -1) + "ე";
        } else {
            lastOrd = prefix + "მე" + remStr + "ე";
        }
    } else {
        if (last.endsWith("ი") || last.endsWith("ა")) {
            let root = last.slice(0, -1);
            lastOrd = "მე" + root + "ე";
        } else {
            lastOrd = "მე" + last + "ე";
        }
    }
    words[words.length - 1] = lastOrd;
    return words.join(" ");
}

function numToGeorgianWithCase(num, rawSuffix) {
    let cardinal = numToGeorgian(num);
    let suffix = rawSuffix.replace(/^-+/, '').trim();
    let stem = cardinal;
    let hasFinalI = cardinal.endsWith('ი');
    if (hasFinalI) {
        stem = cardinal.slice(0, -1);
    }
    if (suffix === 'მდე' || suffix === 'ამდე') {
        if (cardinal.endsWith('ა')) return cardinal + 'მდე';
        return stem + 'ამდე';
    } else if (suffix === 'დან' || suffix === 'იდან') {
        if (cardinal.endsWith('ა')) return cardinal.slice(0, -1) + 'იდან';
        return stem + 'იდან';
    } else if (suffix === 'ში') {
        return (hasFinalI ? stem : cardinal) + 'ში';
    } else if (suffix === 'ზე') {
        return (hasFinalI ? stem : cardinal) + 'ზე';
    } else if (suffix === 'ით' || suffix === 'თ') {
        if (cardinal.endsWith('ა')) return cardinal.slice(0, -1) + 'ით';
        return stem + 'ით';
    } else if (suffix === 'ად' || suffix === 'დ') {
        if (cardinal.endsWith('ა')) return cardinal + 'დ';
        return stem + 'ად';
    } else if (suffix === 'ს') {
        return (hasFinalI ? stem : cardinal) + 'ს';
    } else if (suffix === 'თან') {
        return (hasFinalI ? stem : cardinal) + 'თან';
    } else if (suffix === 'კენ') {
        return (hasFinalI ? cardinal : cardinal + 'ს') + 'კენ';
    } else if (suffix === 'მა' || suffix === 'მ') {
        if (cardinal.endsWith('ა')) return cardinal + 'მ';
        return stem + 'მა';
    }
    return cardinal + '-' + suffix;
}

function preprocessGeorgianText(text) {
    let t = text;
    const decimalMap = new Map();
    let decimalCounter = 0;
    
    // Decimal fractions: 3.14
    t = t.replace(/(\d+)\.(\d+)/g, (match, whole, frac) => {
        const wholeNum = parseInt(whole, 10);
        const fracNum = parseInt(frac, 10);
        const wholeStr = numToGeorgian(wholeNum);
        const fracStr = numToGeorgian(fracNum);
        const precision = frac.length;
        const precisionMap = { 1: "მეათედი", 2: "მეასედი", 3: "მეათასედი", 4: "მეათიათასედი", 5: "ასათასედი", 6: "მილიონედი" };
        const unit = precisionMap[precision] || "ნაწილი";
        const result = `${wholeStr} მთელი ${fracStr} ${unit}`;
        const placeholder = `___PROCESSED_DECIMAL_${decimalCounter}___`;
        decimalMap.set(placeholder, result);
        decimalCounter++;
        return placeholder;
    });

    // Simple fractions: 1/2
    t = t.replace(/(\d+)\/(\d+)/g, (match, num, den) => {
        const numerator = parseInt(num, 10);
        const denominator = parseInt(den, 10);
        const numStr = numToGeorgian(numerator);
        let denStr = "";
        if (denominator === 2) {
            denStr = (numerator === 1) ? "ნახევარი" : "მეორედი";
        } else if (denominator === 4) {
            denStr = "მეოთხედი";
        } else {
            let tempDen = numToGeorgian(denominator);
            if (tempDen.endsWith("ი")) {
                denStr = "მე" + tempDen.slice(0, -1) + "ედი";
            } else {
                denStr = "მე" + tempDen + "ედი";
            }
        }
        return `${numStr} ${denStr}`;
    });

    // 1. Historical Eras (longest patterns first)
    t = t.replace(/(?:^|[^\wა-ჰ])ძვ[\.\s]+წ[\.\s]+აღ(?:რ)?[\.\s]*/gi, ' ძველი წელთაღრიცხვით ');
    t = t.replace(/(?:^|[^\wა-ჰ])ძვ[\.\s]+წ[\.\s]*/gi, ' ძველი წელთაღრიცხვით ');
    t = t.replace(/(?:^|[^\wა-ჰ])ახ[\.\s]+წ[\.\s]+აღ(?:რ)?[\.\s]*/gi, ' ახალი წელთაღრიცხვით ');
    t = t.replace(/(?:^|[^\wა-ჰ])ახ[\.\s]+წ[\.\s]*/gi, ' ახალი წელთაღრიცხვით ');
    t = t.replace(/(?:^|[^\wა-ჰ])ჩვ[\.\s]+წ[\.\s]+აღ(?:რ)?[\.\s]*/gi, ' ჩვენი წელთაღრიცხვით ');
    t = t.replace(/(?:^|[^\wა-ჰ])ჩვ[\.\s]+წ[\.\s]*/gi, ' ჩვენი წელთაღრიცხვით ');

    // 2. Speeds & Measurements (Georgian & English)
    t = t.replace(/(?:^|[^\wა-ჰ])(?:კმ\s*\/\s*სთ|km\s*\/\s*h|kmh)(?=[\s.,:;!?\)„"\'»]|$)/gi, ' კილომეტრი საათში');
    t = t.replace(/(?:^|[^\wა-ჰ])(?:მ\s*\/\s*წმ|m\s*\/\s*s)(?=[\s.,:;!?\)„"\'»]|$)/gi, ' მეტრი წამში');
    t = t.replace(/(?:^|[^\wა-ჰ])mph(?=[\s.,:;!?\)„"\'»]|$)/gi, ' მილი საათში');
    t = t.replace(/(?:^|[^\wა-ჰ])კვ[\.\s]*კმ(?=[\s.,:;!?\)„"\'»]|$)/gi, ' კვადრატული კილომეტრი');
    t = t.replace(/(?:^|[^\wა-ჰ])კვ[\.\s]*მ(?=[\s.,:;!?\)„"\'»]|$)/gi, ' კვადრატული მეტრი');
    t = t.replace(/(?:^|[^\wა-ჰ])კუბ[\.\s]*მ(?=[\s.,:;!?\)„"\'»]|$)/gi, ' კუბური მეტრი');

    // 3. Roman Numeral Ranges: XI-X, I-II, V-IV, XIX-XX
    t = t.replace(/\b([IVXLCDM]+)\s*[-–—]\s*([IVXLCDM]+)\b/gi, (match, r1, r2) => {
        let u1 = r1.toUpperCase();
        let u2 = r2.toUpperCase();
        if (ROMAN_ORDINALS[u1] && ROMAN_ORDINALS[u2]) {
            return `${ROMAN_ORDINALS[u1]}-${ROMAN_ORDINALS[u2]}`;
        }
        return match;
    });

    // 4. Roman Numerals with case suffix: V-ში, V-დან, V-ს, V-ის, V-ით, V-ად, V-მდე, II-ს
    // MUST run BEFORE abbreviation parsing so II-ს. becomes მეორეს. and is not confused with century!
    t = t.replace(/\b([IVXLCDM]+)-(ში|ის|ით|ზე|ად|ს|დან|იდან|მდე|ამდე|თან|კენ|მა|მ)(?=[\s.,:;!?\)„"\'»]|$)/gi, (match, r, sfx) => {
        let u = r.toUpperCase();
        if (ROMAN_ORDINALS[u]) {
            let ord = ROMAN_ORDINALS[u];
            if (ord === 'პირველი') {
                if (sfx === 'ში' || sfx === 'ზე' || sfx === 'თან' || sfx === 'კენ') return 'პირველ' + sfx;
                if (sfx === 'დან' || sfx === 'იდან') return 'პირველიდან';
                if (sfx === 'მდე' || sfx === 'ამდე') return 'პირველამდე';
                if (sfx === 'ით' || sfx === 'თ') return 'პირველით';
                if (sfx === 'ად' || sfx === 'დ') return 'პირველად';
                if (sfx === 'მა' || sfx === 'მ') return 'პირველმა';
                if (sfx === 'ს') return 'პირველს';
                if (sfx === 'ის') return 'პირველის';
            } else {
                if (sfx === 'ში' || sfx === 'ზე' || sfx === 'თან') return ord + sfx;
                if (sfx === 'დან' || sfx === 'იდან') return ord.slice(0, -1) + 'იდან';
                if (sfx === 'მდე' || sfx === 'ამდე') return ord + 'მდე';
                if (sfx === 'ით' || sfx === 'თ') return ord.slice(0, -1) + 'ით';
                if (sfx === 'ად' || sfx === 'დ') return ord.slice(0, -1) + 'ედ';
                if (sfx === 'მა' || sfx === 'მ') return ord + 'მ';
                if (sfx === 'ს') return ord + 'ს';
                if (sfx === 'ის') return ord.slice(0, -1) + 'ის';
                if (sfx === 'კენ') return ord.slice(0, -1) + 'ისკენ';
            }
        }
        return match;
    });

    // 5. Standalone Roman Numerals
    const sortedRomans = Object.keys(ROMAN_ORDINALS).sort((a, b) => b.length - a.length);
    for (const r of sortedRomans) {
        if (r === 'I') {
            t = t.replace(/\bI\b(?!\s+[a-zA-Z'’])/g, ROMAN_ORDINALS['I']);
        } else {
            t = t.replace(new RegExp(`\\b${r}\\b`, 'g'), ROMAN_ORDINALS[r]);
        }
    }

    // 6. Georgian Numbers with prefix/suffix: მე-2, მე-5, მე-10, 1-ელ, 1-ლი
    t = t.replace(/(^|[\s(„"\'«])მე-(\d+)-(მდე|ამდე|დან|იდან|ში|ზე|ით|ად|ს|თან|კენ|მა|მ)(?=[\s.,:;!?\)„"\'»]|$)/g, (match, pfx, n, sfx) => {
        const ord = numToGeorgianOrdinal(parseInt(n, 10));
        let res = ord;
        if (sfx === 'ს') res = ord + 'ს';
        else if (sfx === 'მ' || sfx === 'მა') res = ord + 'მ';
        else if (sfx === 'ში' || sfx === 'ზე') res = ord + sfx;
        else if (sfx === 'დან' || sfx === 'იდან') res = ord.slice(0, -1) + 'იდან';
        else if (sfx === 'მდე' || sfx === 'ამდე') res = ord + 'მდე';
        else res = ord + sfx;
        return pfx + res;
    });
    t = t.replace(/(^|[\s(„"\'«])მე-(\d+)\b/g, (match, pfx, n) => pfx + numToGeorgianOrdinal(parseInt(n, 10)));
    t = t.replace(/\b1-(?:ელ|ლი|ელი)\b/g, 'პირველი');
    t = t.replace(/\b1-მა\b/g, 'პირველმა');
    t = t.replace(/\b1-ს\b/g, 'პირველს');

    // 7. Spaced Numbers (e.g. 40 000, 1 500 000, 40 000-მდე)
    // Matches digits + space/comma/thin-space + 3 digits, running repeatedly
    while (/(\d+)[\s\u00A0\u2009]+(\d{3})(?=\D|$)/.test(t)) {
        t = t.replace(/(\d+)[\s\u00A0\u2009]+(\d{3})(?=\D|$)/g, '$1$2');
    }

    // 8. Numbers with case suffix: 10-დან, 40000-მდე, 2024-ში, 10-ს
    t = t.replace(/\b(\d+)-(მდე|ამდე|დან|იდან|ში|ზე|ით|ად|ს|თან|კენ|მა|მ)(?=[\s.,:;!?\)„"\'»]|$)/g, (match, n, sfx) => {
        return numToGeorgianWithCase(parseInt(n, 10), sfx);
    });

    // 9. Century & Year inflected abbreviations: ს-ში, ს.-ში, ს-ის, ს-დან, etc.
    // MUST be strictly preceded by whitespace or beginning (NEVER hyphen/dash)
    t = t.replace(/(^|[\s(„"\'«])ს[\.]?-(?:ში|ზე)(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეში');
    t = t.replace(/(^|[\s(„"\'«])ს[\.]?-ის(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნის');
    t = t.replace(/(^|[\s(„"\'«])ს[\.]?-დან(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნიდან');
    t = t.replace(/(^|[\s(„"\'«])ს[\.]?-მდე(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნემდე');
    t = t.replace(/(^|[\s(„"\'«])ს[\.]?-ით(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნით');
    t = t.replace(/(^|[\s(„"\'«])სს[\.]?-(?:ში|ზე)(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეებში');
    t = t.replace(/(^|[\s(„"\'«])სს[\.]?-ის(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეების');
    t = t.replace(/(^|[\s(„"\'«])სს[\.]?-დან(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეებიდან');
    t = t.replace(/(^|[\s(„"\'«])სს[\.]?-მდე(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეებამდე');
    t = t.replace(/(^|[\s(„"\'«])წ[\.]?-ში(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წელში');
    t = t.replace(/(^|[\s(„"\'«])წ[\.]?-დან(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლიდან');
    t = t.replace(/(^|[\s(„"\'«])წ[\.]?-მდე(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლამდე');
    t = t.replace(/(^|[\s(„"\'«])წ[\.]?-ით(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლით');
    t = t.replace(/(^|[\s(„"\'«])წწ[\.]?-(?:ში|ზე)(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლებში');
    t = t.replace(/(^|[\s(„"\'«])წწ[\.]?-დან(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლებიდან');
    t = t.replace(/(^|[\s(„"\'«])წწ[\.]?-მდე(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლებამდე');

    // 10. Century / Year plurals: სს., წწ.
    // MUST be strictly preceded by whitespace, start, or opening quote (NEVER hyphen)
    t = t.replace(/(^|[\s(„"\'«])სს[\.]?(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1საუკუნეებში');
    t = t.replace(/(^|[\s(„"\'«])წწ[\.]?(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1წლებში');

    // 11. Single-letter abbreviations following numbers/ordinals OR standalone:
    // Protected against mistaking personal initials (e.g. "ს. წერეთელი") for "საუკუნე წერეთელი"
    // After ordinal words or numbers:
    t = t.replace(/(^|[\s(„"\'«])([0-9IVXLCDM]+|მე-\d+|პირველი|მეორე|მესამე|მეოთხე|მეხუთე|მეექვსე|მეშვიდე|მერვე|მეცხრე|მეათე|მეთერთმეტე|მეთორმეტე|მეცამეტე|მეთოთხმეტე|მეთხუთმეტე|მეთექვსმეტე|მეჩვიდმეტე|მეთვრამეტე|მეცხრამეტე|მეოცე|ოცდამეერთე|ოცდამეორე|ოცდამეათე|მეორმოცე|ორმოცდამეათე|მეასე|ერთი|ორი|სამი|ოთხი|ხუთი|ექვსი|შვიდი|რვა|ცხრა|ათი|ოცი)\s+ს\.(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1$2 საუკუნე');
    t = t.replace(/(^|[\s(„"\'«])([0-9IVXLCDM]+|მე-\d+|პირველი|მეორე|მესამე|მეოთხე|მეხუთე|მეექვსე|მეშვიდე|მერვე|მეცხრე|მეათე|მეთერთმეტე|მეთორმეტე|მეცამეტე|მეთოთხმეტე|მეთხუთმეტე|მეთექვსმეტე|მეჩვიდმეტე|მეთვრამეტე|მეცხრამეტე|მეოცე|ოცდამეერთე|ოცდამეორე|ოცდამეათე|მეორმოცე|ორმოცდამეათე|მეასე|ერთი|ორი|სამი|ოთხი|ხუთი|ექვსი|შვიდი|რვა|ცხრა|ათი|ოცი)\s+ს(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1$2 საუკუნე');

    // Year abbreviations after numbers/words:
    t = t.replace(/(^|[\s(„"\'«])([ა-ჰ0-9]+)\s+წ\.(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1$2 წელი');
    t = t.replace(/(^|[\s(„"\'«])([ა-ჰ0-9]+)\s+წ(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1$2 წელი');

    // NOTE: Standalone ს. without a century number/ordinal is NEVER 'საუკუნე'!
    // It is an initial (e.g. ს. კოსტავა, ს. წერეთელი), so it must remain as initial 'ს.' and NOT be converted.
    // 'ს.' is only converted to 'საუკუნე' when explicitly preceded by a number or Roman numeral above.

    // Page (გვ.) and Volume (ტ.):
    t = t.replace(/(^|[\s(„"\'«])გვ\.(?=[\s.,:;!?\)„"\'»]|$)/gi, '$1გვერდი');
    t = t.replace(/(^|[\s(„"\'«])ტ\.(?=\s*(?:[0-9IVXLCDM]|პირველ|მეორ|მესამ|მეოთხ|მეხუთ|[.,:;!?\)„"\'»]|$))/gi, '$1ტომი ');

    // 10. Currencies and symbols (handled before normal integers so $999 -> 999 დოლარი -> ცხრაას ოთხმოცდაცხრამეტი დოლარი)
    t = t.replace(/(\d+)\s*₾/g, '$1 ლარი').replace(/₾\s*(\d+)/g, '$1 ლარი');
    t = t.replace(/(\d+)\s*\$/g, '$1 დოლარი').replace(/\$\s*(\d+)/g, '$1 დოლარი');
    t = t.replace(/(\d+)\s*€/g, '$1 ევრო').replace(/€\s*(\d+)/g, '$1 ევრო');
    t = t.replace(/(\d+)\s*£/g, '$1 ფუნტი').replace(/£\s*(\d+)/g, '$1 ფუნტი');
    t = t.replace(/(\d+)\s*¥/g, '$1 იენი').replace(/¥\s*(\d+)/g, '$1 იენი');
    t = t.replace(/%/g, ' პროცენტი').replace(/№/g, ' ნომერი ').replace(/₾/g, ' ლარი ').replace(/°C/g, ' გრადუსი ცელსიუსი');

    // 11. Normal integers
    t = t.replace(/\b\d+\b/g, (match) => numToGeorgian(match));

    // 12. Common Abbreviations
    const abbrList = [
        { k: 'ძვ.წ.', v: 'ძველი წელთაღრიცხვით' },
        { k: 'ახ.წ.', v: 'ახალი წელთაღრიცხვით' },
        { k: 'ე.ი.', v: 'ესე იგი,' },
        { k: 'ე.წ.', v: 'ეგრეთ წოდებული' },
        { k: 'ა.შ.', v: 'ასე შემდეგ.' },
        { k: 'ა.შ', v: 'ასე შემდეგ.' },
        { k: 'აშშ', v: 'ამერიკის შეერთებული შტატები' },
        { k: 'შ.პ.ს.', v: 'შეპესე' },
        { k: 'კგ', v: 'კილოგრამი' },
        { k: 'გრ', v: 'გრამი' },
        { k: 'დნმ', v: 'დეენემი' }
    ];
    abbrList.forEach(item => {
        let pattern = item.k.replace(/\./g, '\\.\\s*');
        t = t.replace(new RegExp(`(^|[\\s(„"\'«])${pattern}(?=[\\s.,:;!?\\)„"\'»]|$)`, 'gi'), `$1${item.v}`);
    });

    // Restore decimal fractions
    decimalMap.forEach((value, key) => {
        t = t.replace(key, value);
    });

    return t.replace(/\s+/g, ' ').trim();
}
function transliterateToGeorgian(text) {
    if (typeof window !== 'undefined' && typeof window.advancedTransliterateToGeorgian === 'function') {
        return window.advancedTransliterateToGeorgian(text);
    }
    const dictionary = {
        'a': 'ეი', 'ABC': 'ეიბისი', 'yoga': 'იოგა', 'ChatGPT': 'ჩეტჯიპიტი'
    };
    const englishSuffixes = [
        { eng: 's', geo: 'ს' }, { eng: 'es', geo: 'ს' }, { eng: 'ed', geo: 'დ' }, { eng: 'ing', geo: 'ინგ' }, { eng: 'ly', geo: 'ლი' }, { eng: 'er', geo: 'ერ' }, { eng: 'est', geo: 'ესთ' }
    ];
    function processSingleWord(word) {
        let t = word.toLowerCase().replace(/[\u2010-\u2015\u2013\u2014]/g, '-');
        if (dictionary[t]) return dictionary[t];
        for (let suffix of englishSuffixes) {
            if (t.endsWith(suffix.eng)) {
                let root = t.slice(0, -suffix.eng.length);
                if (dictionary[root]) { return dictionary[root] + suffix.geo; }
            }
        }
        if (t.startsWith('a') && t.length > 2 && !'aeiou'.includes(t[1])) { t = 'ე' + t.slice(1); }
        t = t.replace(/a([bcdfghjklmnpqrstvwxyz])e$/g, 'ეი$1');
        t = t.replace(/i([bcdfghjklmnpqrstvwxyz])e$/g, 'აი$1');
        t = t.replace(/o([bcdfghjklmnpqrstvwxyz])e$/g, 'ოუ$1');
        t = t.replace(/u([bcdfghjklmnpqrstvwxyz])e$/g, 'იუ$1');
        const complexSuffixes = [ { eng: 'tion', geo: 'შენ' }, { eng: 'sion', geo: 'ჟენ' }, { eng: 'ture', geo: 'ჩერ' }, { eng: 'ment', geo: 'მენთ' }, { eng: 'ght', geo: 'ტ' }, { eng: 'igh', geo: 'აი' }, { eng: 'alk', geo: 'ოქ' }, { eng: 'all', geo: 'ოლ' } ];
        complexSuffixes.forEach(r => t = t.replace(new RegExp(r.eng, 'g'), r.geo));
        const vowels = [ { eng: 'ee', geo: 'ი' }, { eng: 'ea', geo: 'ი' }, { eng: 'oo', geo: 'უ' }, { eng: 'ou', geo: 'აუ' }, { eng: 'ow', geo: 'აუ' }, { eng: 'oa', geo: 'ოუ' }, { eng: 'ai', geo: 'ეი' }, { eng: 'ay', geo: 'ეი' }, { eng: 'au', geo: 'ო' } ];
        vowels.forEach(r => t = t.replace(new RegExp(r.eng, 'g'), r.geo));
        const consonants = [ { eng: 'th', geo: 'თ' }, { eng: 'sh', geo: 'შ' }, { eng: 'ch', geo: 'ჩ' }, { eng: 'ph', geo: 'ფ' }, { eng: 'ck', geo: 'ქ' }, { eng: 'qu', geo: 'ქვ' }, { eng: 'wh', geo: 'ვ' }, { eng: 'kn', geo: 'ნ' } ];
        consonants.forEach(r => t = t.replace(new RegExp(r.eng, 'g'), r.geo));
        t = t.replace(/c(?=[eiy])/g, 'ს').replace(/c/g, 'ქ');
        t = t.replace(/g(?=[eiy])/g, 'ჯ').replace(/g/g, 'გ');
        const map = { 'a': 'ა', 'b': 'ბ', 'd': 'დ', 'e': 'ე', 'f': 'ფ', 'h': 'ჰ', 'i': 'ი', 'j': 'ჯ', 'k': 'ქ', 'l': 'ლ', 'm': 'მ', 'n': 'ნ', 'o': 'ო', 'p': 'ფ', 'q': 'ქ', 'r': 'რ', 's': 'ს', 't': 'თ', 'u': 'უ', 'v': 'ვ', 'w': 'ვ', 'x': 'ქს', 'y': 'ი', 'z': 'ზ' };
        let finalResult = "";
        for (let char of t) { if (/[ა-ჰ]/.test(char)) { finalResult += char; } else { finalResult += map[char] || char; } }
        return finalResult;
    }
    return text.replace(/[a-zA-Z]+(?:[\-\u2010-\u2015\u2013\u2014][a-zA-Z]+)*/g, (match) => { return processSingleWord(match); });
}
function processText(rawHtml) {
    stopReading();
    document.body.classList.add('is-reading');
    lastLoadedText = rawHtml.trim();
    contentArea.innerHTML = '';
    contentArea.scrollTop = 0;
    parsedContent = [];
    window.currentChapterTotalChars = 0;
    lastHighlightedIdx = -1;
    let sCounter = 0;
    let lastDetectedLang = 'ka';
    
    const tags = [];
    let textWithPlaceholders = rawHtml.replace(/<\/?(b|strong|img)[^>]*>/gi, (match) => {
        const index = tags.length;
        tags.push(match);
        return `___HTML_${index}___`;
    });
    
    const textFragment = document.createDocumentFragment();
    const paragraphs = textWithPlaceholders.split(/\n\s*\n+/).map(p => p.trim()).filter(p => p.length > 0);
    paragraphs.forEach((paraText, pIdx) => {
        const pDiv = document.createElement('div');
        pDiv.className = 'paragraph';
        const isParaPrimarilyGeorgian = /[ა-ჰ]/.test(paraText);
        let protectedText = paraText;
        const decimalPlaceholders = [];
        protectedText = protectedText.replace(/(\d+)\.(\d+)/g, (match) => { const placeholder = `___DECIMAL_${decimalPlaceholders.length}___`; decimalPlaceholders.push(match); return placeholder; });
        // Multi-word historical and official abbreviations
        const multiAbbrs = [
            /ძვ\.\s*წ\.\s*აღ(?:რ)?\./gi,
            /ძვ\.\s*წ\./gi,
            /ახ\.\s*წ\.\s*აღ(?:რ)?\./gi,
            /ახ\.\s*წ\./gi,
            /ჩვ\.\s*წ\.\s*აღ(?:რ)?\./gi,
            /ჩვ\.\s*წ\./gi,
            /ე\.\s*წ\./gi,
            /ე\.\s*ი\./gi,
            /ა\.\s*შ\./gi,
            /მ\.\s*შ\./gi,
            /ე\.\s*უ\./gi,
            /შ\.\s*პ\.\s*ს\./gi,
            /ს\.\s*ს\./gi
        ];
        multiAbbrs.forEach(reg => {
            protectedText = protectedText.replace(reg, m => m.replace(/\./g, '___DOT___'));
        });

        // Literature and reference single-word abbreviations when not at end of sentence
        const singleAbbrs = [
            'სს.', 'წწ.', 'გვ.', 'ტტ.', 'ტ.', 'პროფ.', 'აკად.', 'დოქტ.', 'მაგ.', 'მუხ.', 'თავ.', 'ნაწ.',
            'სურ.', 'ნახ.', 'რედ.', 'შემდგ.', 'გამომც.', 'სთ.', 'წთ.', 'წმ.', 'ქ.', 'სოფ.'
        ];
        singleAbbrs.forEach(abbr => {
            let escaped = abbr.replace(/\./g, '\\.');
            let reg = new RegExp(`(^|[\\s(„"\'«])${escaped}(?=\\s+(?:[0-9IVXLCDMა-ჰ]|___DECIMAL))`, 'gi');
            protectedText = protectedText.replace(reg, (m, p1) => p1 + m.slice(p1.length).replace(/\./g, '___DOT___'));
        });

        // Protect century and year abbreviations following numbers or Roman numerals: V ს., 2024 წ.
        protectedText = protectedText.replace(/(\b[IVXLCDM0-9]+(?:-[IVXLCDM0-9]+)?\s+(?:ს|წ))\.(?=\s+(?:[0-9IVXLCDMა-ჰ]|___DECIMAL))/gi, '$1___DOT___');

        // Protect Georgian personal initials: e.g. ი. ჭავჭავაძე, შ. რუსთაველი
        protectedText = protectedText.replace(/(^|[\s(„"\'«])([ა-ჰ]{1,2})\.\s*(?=[ა-ჰ])/g, '$1$2___DOT___ ');
        const emojiRange = "\\u{1F000}-\\u{1FFFF}\\u{2600}-\\u{27BF}\\u{1F300}-\\u{1F5FF}\\u{1F680}-\\u{1F6FF}\\u{1F1E0}-\\u{1F1FF}";
        const sentenceRegex = new RegExp(`[^.!?${emojiRange}]+(?:[.!?]+|[${emojiRange}]+)+|[^.!?${emojiRange}]+$`, 'gu');
        // Hard split at forced header markers FIRST: the greedy [^.!?]+ class
        // would otherwise swallow ___SPLIT___ (it contains no .!?) and merge the
        // header with the text that follows it into one sentence.
        const sentences = [];
        protectedText.split('___SPLIT___').forEach(segment => {
            if (!segment.trim()) return;
            const matched = segment.match(sentenceRegex);
            if (matched) sentences.push(...matched);
            else sentences.push(segment);
        });
        if (sentences.length === 0) sentences.push(protectedText);
        
        let openTagsStack = [];
        
        sentences.forEach((sentText) => {
            let restoredText = sentText.replace(/___DOT___/g, '.').replace(/___EXCL___/g, '!').replace(/___QUEST___/g, '?').replace(/___SPLIT___/g, '');
            restoredText = restoredText.replace(/___DECIMAL_(\d+)___/g, (match, index) => { return decimalPlaceholders[parseInt(index)]; });
            let originalDisplay = restoredText.trim();
            if(!originalDisplay) return;

            let prependedTags = '';
            openTagsStack.forEach(tagIdx => {
                prependedTags += `___HTML_${tagIdx}___`;
            });

            let regex = /___HTML_(\d+)___/g;
            let match;
            while ((match = regex.exec(originalDisplay)) !== null) {
                let idx = parseInt(match[1]);
                let tagStr = tags[idx];
                if (tagStr.startsWith('</')) {
                    openTagsStack.pop();
                } else {
                    openTagsStack.push(idx);
                }
            }

            let appendedTags = '';
            for (let i = openTagsStack.length - 1; i >= 0; i--) {
                let tagIdx = openTagsStack[i];
                let tagStr = tags[tagIdx];
                let closingTag = '';
                if (tagStr.toLowerCase().startsWith('<strong')) closingTag = '</strong>';
                else if (tagStr.toLowerCase().startsWith('<b')) closingTag = '</b>';
                else if (tagStr.toLowerCase().startsWith('<img')) continue; // img doesn't need closing tag injected
                
                let newIdx = tags.length;
                tags.push(closingTag);
                appendedTags += `___HTML_${newIdx}___`;
            }

            originalDisplay = prependedTags + originalDisplay + appendedTags;

            const words = originalDisplay.split(/(\s+|—|–)/g).filter(w => w.trim().length > 0 || w === '—' || w === '–');
            let detectedLang = lastDetectedLang;
            const textForLangCheck = originalDisplay.replace(/___HTML_\d+___/g, '');
            if (/[ა-ჰ]/.test(textForLangCheck)) detectedLang = 'ka';
            else if (/[А-Яа-я]/.test(textForLangCheck)) detectedLang = 'ru';
            else if (/[A-Za-z]/.test(textForLangCheck)) {
                detectedLang = isParaPrimarilyGeorgian ? 'ka' : 'en';
            }
            lastDetectedLang = detectedLang;
            detectedBookLanguages.add(detectedLang);
            const sSpan = document.createElement('span');
            sSpan.className = 'sentence';
            sSpan.dataset.idx = sCounter;
            
            let sentenceHtml = words.map(w => `<span class="word">${w}</span>`).join(' ') + ' ';
            sentenceHtml = sentenceHtml.replace(/___HTML_(\d+)___/g, (match, index) => {
                return tags[parseInt(index)];
            });
            sSpan.innerHTML = sentenceHtml;
            
            sSpan.addEventListener('click', (e) => {
                e.preventDefault(); e.stopPropagation();
                synthesis.cancel(); stopPiperAudio();
                currentIdx = parseInt(sSpan.dataset.idx);

                // 🔥 მხოლოდ აქ ხდება შენახვა! (დაკლიკებისას)
                highlightSentence(currentIdx, true);

                if (isPlaying) playMergedQueue();
            });
            pDiv.appendChild(sSpan);
            const charLen = originalDisplay ? originalDisplay.length : 1;
            window.currentChapterTotalChars += charLen;
            parsedContent.push({ index: sCounter, pIndex: pIdx, textForUI: originalDisplay, lang: detectedLang, element: sSpan, charLen });
            sCounter++;
        });
        textFragment.appendChild(pDiv);
    });

    const footerDiv = document.createElement('div');
    footerDiv.className = 'chapter-nav-footer';

    const prevBtnEl = document.createElement('button');
    prevBtnEl.className = 'nav-chapter-btn';
    prevBtnEl.innerHTML = `<span>←</span> Previous Chapter`;

    const bounds = getLogicalChapterBounds(currentSpineIndex);

    if (bounds.start <= 0) prevBtnEl.classList.add('hidden');

    prevBtnEl.onclick = () => {
        if (currentBook && bounds.start > 0) {
            const prevBounds = getLogicalChapterBounds(bounds.start - 1);
            const prevItem = currentBook.spine.get(prevBounds.start);
            // გადასვლა არ ინახავს!
            const delay = isPlaying ? -1 : 0;
            if (prevItem) displayChapter(prevItem.href, delay);
        }
    };

    const nextBtnEl = document.createElement('button');
    nextBtnEl.className = 'nav-chapter-btn';
    nextBtnEl.innerHTML = `Next Chapter <span>→</span>`;

    if (currentBook && bounds.end >= currentBook.spine.spineItems.length) nextBtnEl.classList.add('hidden');

    nextBtnEl.onclick = () => {
        handleNextChapterLogic();
    };

    footerDiv.appendChild(prevBtnEl);
    footerDiv.appendChild(nextBtnEl);
    textFragment.appendChild(footerDiv);
    contentArea.appendChild(textFragment);

    currentIdx = 0;
    updateProgressBar();
    rebuildDynamicSettings();
}
// --- SEQUENTIAL PLAYBACK ENGINE ---
// Incremented on every play/stop/seek: any in-flight loop holding an old token dies quietly

const EMOJI_TEST_RE = /[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F1E0}-\u{1F1FF}]/u;

// Builds the spoken text for ONE sentence + char offsets of each visual word within it.
// `raw` keeps the trailing space so offsets concatenate cleanly for native utterances.
function buildSpokenSentence(sent, lang) {
    const visualWords = Array.from(sent.element.querySelectorAll('.word'));
    const wordRanges = [];
    let text = "";
    let i = 0;
    const n = visualWords.length;

    let parenDepth = 0;
    const wordInfos = visualWords.map(wordEl => {
        const orig = wordEl.innerText.trim();
        if (!skipParenthesesSetting) {
            return { el: wordEl, raw: orig, skip: false, isOnlyPunct: false, original: orig };
        }
        let outChars = [];
        for (let ch of orig) {
            if (ch === '(' || ch === '[' || ch === '{' || ch === '⟨') {
                parenDepth++;
                continue;
            } else if (ch === ')' || ch === ']' || ch === '}' || ch === '⟩') {
                if (parenDepth > 0) parenDepth--;
                continue;
            }
            if (parenDepth === 0) {
                outChars.push(ch);
            }
        }
        const cleaned = outChars.join('').trim();
        const isOnlyPunct = cleaned.length > 0 && /^[.,:;!?„"\'»]+$/.test(cleaned);
        return {
            el: wordEl,
            raw: cleaned,
            skip: cleaned.length === 0,
            isOnlyPunct: isOnlyPunct,
            original: orig
        };
    });

    const voiceSelectId = `voice-${lang}`;
    const selectEl = document.getElementById(voiceSelectId);
    const selectedVoiceName = (localStorage.getItem(voiceSelectId) || (selectEl ? selectEl.value : '') || '').toLowerCase();
    const isGeorgianTarget = lang === 'ka' || 
                             selectedVoiceName.includes('ka_ge') || 
                             selectedVoiceName.includes('natia') || 
                             selectedVoiceName.includes('georgian') || 
                             selectedVoiceName.includes('ქართული');

    while (i < n) {
        const info = wordInfos[i];
        if (info.skip) {
            i++;
            continue;
        }
        if (info.isOnlyPunct) {
            if (text.length > 0) {
                text = text.trimEnd() + info.raw + " ";
            }
            i++;
            continue;
        }

        const wordEl = info.el;
        let raw = info.raw;

        if (raw === '—' || raw === '–') {
            const start = text.length;
            text += ", ";
            wordRanges.push({ el: wordEl, start: start, end: text.length });
            i++;
            continue;
        }
        if (EMOJI_TEST_RE.test(raw)) {
            const start = text.length;
            text += ". ";
            wordRanges.push({ el: wordEl, start: start, end: text.length });
            i++;
            continue;
        }

        if (isGeorgianTarget) {
            const cleanCurr = raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '');

            // Lookahead Pattern 1: Spaced numbers (e.g. "40" + "000" or "1" + "500" + "000" + optional suffix)
            if (/^\d{1,3}$/.test(cleanCurr) && i + 1 < n && !wordInfos[i + 1].skip && !wordInfos[i + 1].isOnlyPunct) {
                const nextClean = wordInfos[i + 1].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '');
                if (/^\d{3}(?:-(?:მდე|ამდე|დან|იდან|ში|ზე|ით|ად|ს|თან|კენ|მა|მ))?$/.test(nextClean)) {
                    let j = i + 1;
                    while (j + 1 < n && !wordInfos[j + 1].skip && !wordInfos[j + 1].isOnlyPunct) {
                        const candClean = wordInfos[j + 1].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '');
                        if (/^\d{3}(?:-(?:მდე|ამდე|დან|იდან|ში|ზე|ით|ად|ს|თან|კენ|მა|მ))?$/.test(candClean)) {
                            j++;
                        } else {
                            break;
                        }
                    }
                    const matchedEls = visualWords.slice(i, j + 1);
                    const combinedRaw = wordInfos.slice(i, j + 1).map(x => x.raw).join(' ');
                    let spoken = preprocessGeorgianText(combinedRaw);
                    if (/[a-zA-Z]/.test(spoken)) spoken = transliterateToGeorgian(spoken);

                    const start = text.length;
                    text += spoken + " ";
                    const end = text.length;

                    const spokenWords = spoken.split(/\s+/).filter(w => w.length > 0);
                    if (spokenWords.length === matchedEls.length) {
                        let currPos = start;
                        for (let k = 0; k < matchedEls.length; k++) {
                            let wLen = spokenWords[k].length + 1;
                            wordRanges.push({ el: matchedEls[k], start: currPos, end: currPos + wLen });
                            currPos += wLen;
                        }
                    } else {
                        const spanLen = end - start;
                        const chunkLen = spanLen / matchedEls.length;
                        for (let k = 0; k < matchedEls.length; k++) {
                            let sPos = Math.floor(start + k * chunkLen);
                            let ePos = (k < matchedEls.length - 1) ? Math.floor(start + (k + 1) * chunkLen) : end;
                            wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                        }
                    }
                    i = j + 1;
                    continue;
                }
            }

            // Lookahead Pattern 2: Historical Eras across tokens (3 tokens: ძვ. + წ. + აღ. / 2 tokens: ძვ. + წ.)
            if (i + 2 < n && !wordInfos[i + 1].skip && !wordInfos[i + 2].skip) {
                const w1 = cleanCurr.toLowerCase();
                const w2 = wordInfos[i + 1].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toLowerCase();
                const w3 = wordInfos[i + 2].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toLowerCase();
                if ((w1 === 'ძვ' || w1 === 'ახ' || w1 === 'ჩვ') && w2 === 'წ' && (w3 === 'აღ' || w3 === 'აღრ')) {
                    const matchedEls = visualWords.slice(i, i + 3);
                    const combinedRaw = wordInfos.slice(i, i + 3).map(x => x.raw).join(' ');
                    let spoken = preprocessGeorgianText(combinedRaw);
                    const start = text.length;
                    text += spoken + " ";
                    const end = text.length;
                    const chunkLen = (end - start) / 3;
                    for (let k = 0; k < 3; k++) {
                        let sPos = Math.floor(start + k * chunkLen);
                        let ePos = (k < 2) ? Math.floor(start + (k + 1) * chunkLen) : end;
                        wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                    }
                    i += 3;
                    continue;
                }
            }
            if (i + 1 < n && !wordInfos[i + 1].skip) {
                const w1 = cleanCurr.toLowerCase();
                const w2 = wordInfos[i + 1].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toLowerCase();
                if ((w1 === 'ძვ' || w1 === 'ახ' || w1 === 'ჩვ') && w2 === 'წ') {
                    const matchedEls = visualWords.slice(i, i + 2);
                    const combinedRaw = wordInfos.slice(i, i + 2).map(x => x.raw).join(' ');
                    let spoken = preprocessGeorgianText(combinedRaw);
                    const start = text.length;
                    text += spoken + " ";
                    const end = text.length;
                    const chunkLen = (end - start) / 2;
                    for (let k = 0; k < 2; k++) {
                        let sPos = Math.floor(start + k * chunkLen);
                        let ePos = (k < 1) ? Math.floor(start + (k + 1) * chunkLen) : end;
                        wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                    }
                    i += 2;
                    continue;
                }
            }

            // Lookahead Pattern 3: Roman ranges split across tokens: XI + - + X
            if (i + 2 < n && !wordInfos[i + 1].skip && !wordInfos[i + 2].skip) {
                const w1 = cleanCurr.toUpperCase();
                const wMid = wordInfos[i + 1].raw;
                const w3 = wordInfos[i + 2].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toUpperCase();
                if (/^[IVXLCDM]+$/.test(w1) && (wMid === '-' || wMid === '–' || wMid === '—') && /^[IVXLCDM]+$/.test(w3)) {
                    const matchedEls = visualWords.slice(i, i + 3);
                    let spoken = preprocessGeorgianText(`${w1}-${w3}`);
                    const start = text.length;
                    text += spoken + " ";
                    const end = text.length;
                    const chunkLen = (end - start) / 3;
                    for (let k = 0; k < 3; k++) {
                        let sPos = Math.floor(start + k * chunkLen);
                        let ePos = (k < 2) ? Math.floor(start + (k + 1) * chunkLen) : end;
                        wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                    }
                    i += 3;
                    continue;
                }
            }

            // Lookahead Pattern 4: Roman/Number + Century/Year unit: V + ს. or XI-X + სს.
            if (i + 1 < n && !wordInfos[i + 1].skip) {
                const nextClean = wordInfos[i + 1].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toLowerCase();
                const isCenturyOrYearUnit = nextClean === 'ს' || nextClean === 'სს' || nextClean === 'წ' || nextClean === 'წწ' || 
                                            nextClean === 'საუკუნე' || nextClean === 'საუკუნეში' ||
                                            nextClean.startsWith('ს-') || nextClean.startsWith('ს.-') ||
                                            nextClean.startsWith('სს-') || nextClean.startsWith('წ-') || nextClean.startsWith('წწ-');
                if (isCenturyOrYearUnit) {
                    if (/^(?:[IVXLCDM]+|\d+|მე-\d+)(?:-[IVXLCDM\d]+)?$/i.test(cleanCurr)) {
                        const matchedEls = visualWords.slice(i, i + 2);
                        const combinedRaw = wordInfos.slice(i, i + 2).map(x => x.raw).join(' ');
                        let spoken = preprocessGeorgianText(combinedRaw);
                        const start = text.length;
                        text += spoken + " ";
                        const end = text.length;
                        const chunkLen = (end - start) / 2;
                        for (let k = 0; k < 2; k++) {
                            let sPos = Math.floor(start + k * chunkLen);
                            let ePos = (k < 1) ? Math.floor(start + (k + 1) * chunkLen) : end;
                            wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                        }
                        i += 2;
                        continue;
                    }
                }
            }

            // Lookahead Pattern 5: Speed / Measurement unit split: კმ + / + სთ
            if (i + 2 < n && !wordInfos[i + 1].skip && !wordInfos[i + 2].skip) {
                const w1 = cleanCurr.toLowerCase();
                const wMid = wordInfos[i + 1].raw;
                const w3 = wordInfos[i + 2].raw.replace(/^[„"\'«\(\[]+|[.,:;!?„"\'»\)\]]+$/g, '').toLowerCase();
                if ((w1 === 'კმ' && wMid === '/' && w3 === 'სთ') || (w1 === 'მ' && wMid === '/' && w3 === 'წმ')) {
                    const matchedEls = visualWords.slice(i, i + 3);
                    let spoken = preprocessGeorgianText(`${w1}/${w3}`);
                    const start = text.length;
                    text += spoken + " ";
                    const end = text.length;
                    const chunkLen = (end - start) / 3;
                    for (let k = 0; k < 3; k++) {
                        let sPos = Math.floor(start + k * chunkLen);
                        let ePos = (k < 2) ? Math.floor(start + (k + 1) * chunkLen) : end;
                        wordRanges.push({ el: matchedEls[k], start: sPos, end: ePos });
                    }
                    i += 3;
                    continue;
                }
            }

            // Fallback single word for Georgian
            let spoken = preprocessGeorgianText(raw);
            if (/[a-zA-Z]/.test(spoken)) spoken = transliterateToGeorgian(spoken);
            const start = text.length;
            text += spoken + " ";
            wordRanges.push({ el: wordEl, start: start, end: text.length });
            i++;
            continue;
        }

        // Non-Georgian language fallback
        const start = text.length;
        text += raw + " ";
        wordRanges.push({ el: wordEl, start: start, end: text.length });
        i++;
    }
    
    let resultText = text.trim();
    // მომხმარებლის მოთხოვნა: ფიზიკური წერტილის დასმა სათაურებზე
    const hType = getHeaderType(sent.element);
    if (hType) {
        if (resultText.endsWith(':') || resultText.endsWith(';')) {
            resultText = resultText.slice(0, -1) + '.';
        } else if (resultText.length > 0 && !/[.!?]/.test(resultText.slice(-1))) {
            resultText += '.';
        }
    }
    function containsSpeakableText(str) {
        try {
            return new RegExp('\\p{L}|\\p{N}', 'u').test(str);
        } catch(e) {
            return /[a-zA-Z0-9\u10D0-\u10FA\u10A0-\u10CF\u0400-\u04FF]/.test(str);
        }
    }
    
    return { text: resultText, raw: resultText + " ", wordRanges: wordRanges, totalChars: resultText.length, speakable: containsSpeakableText(resultText) };
}

function piperSynthesize(state, text) {
    return new Promise((resolve, reject) => {
        const hasSpeakable = (() => {
            try { return new RegExp('\\p{L}|\\p{N}', 'u').test(text); } 
            catch(e) { return /[a-zA-Z0-9\u10D0-\u10FA\u10A0-\u10CF\u0400-\u04FF]/.test(text); }
        })();
        if (!text || !hasSpeakable) { resolve(null); return; }
        if (!state || !state.worker || !state.ready) { reject(new Error('Piper worker not ready')); return; }
        state.pending.push({ resolve: resolve, reject: reject });
        state.worker.postMessage({ kind: 'synthesize', text: text });
    });
}

function waitForPiperReady(state, token) {
    return new Promise((resolve) => {
        const check = () => {
            if (token !== playbackToken || !isPlaying) return resolve(false);
            if (state.ready) return resolve(true);
            if (!state.worker && !state.initializing) return resolve(false); // init failed
            setTimeout(check, 100);
        };
        check();
    });
}

// A phonemizer crash ("memory access out of bounds") can leave the WASM instance
// corrupted — rebuild the worker (model reloads from Cache API, so it's fast) and retry once.
async function synthesizeSentence(langCode, text, token) {
    let state = piperWorkers[langCode];
    try {
        return await piperSynthesize(state, text);
    } catch (e) {
        console.warn('Piper synth error, rebuilding worker:', e.message);
        const path = state ? state.voicePath : null;
        if (!path || token !== playbackToken) return null;
        state.voicePath = null;
        initPiperWorker(langCode, path);
        state = piperWorkers[langCode];
        const ok = await waitForPiperReady(state, token);
        if (!ok) return null;
        try { return await piperSynthesize(state, text); }
        catch (e2) { console.error('Piper synth failed after worker rebuild, skipping sentence:', e2.message); return null; }
    }
}

// Piper has no onboundary events, so word karaoke is estimated:
// playback position is mapped linearly onto spoken-character offsets.
// Implemented runWordHighlights (replaced by multi_replace above, removing original to prevent duplicate definitions)


function playPiperAudio(state, wavBlob, rate, spoken, token) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(wavBlob);
        const audio = new Audio(url);
        audio.playbackRate = Math.max(0.5, Math.min(rate, 4));
        state.currentAudio = audio;
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            clearInterval(guard);
            URL.revokeObjectURL(url);
            if (state.currentAudio === audio) state.currentAudio = null;
            resolve();
        };
        // stop/pause/seek invalidate the token or isPlaying — kill this audio then
        const guard = setInterval(() => {
            if (token !== playbackToken || !isPlaying) { audio.pause(); finish(); }
        }, 100);
        audio.onended = finish;
        audio.onerror = finish;
        runWordHighlights(audio, spoken, token);
        audio.play().catch(finish);
    });
}

// Classifies a sentence span: 'main' (h1-h6), 'internal' (bold header), or null.
// Detection relies on the .epub-header marker classes stamped in extractTextFromDoc.
// The bold-ratio fallback covers manually pasted/edited text where no marker exists;
// it compares localName (never selectors) so XML-namespaced <strong> tags still match.
function getHeaderType(span) {
    if (!span) return null;
    const marker = span.querySelector('.epub-header');
    if (marker) {
        return /(?:^|\s)epub-header-h[1-6](?:\s|$)/.test(marker.className) ? 'main' : 'internal';
    }
    const words = span.querySelectorAll('.word');
    if (words.length === 0) return null;
    let boldWordCount = 0;
    words.forEach(w => {
        for (const n of w.querySelectorAll('*')) {
            if (isBoldNode(n)) { boldWordCount++; break; }
        }
    });
    return (boldWordCount / words.length) >= 0.7 ? 'internal' : null;
}

async function playPiperChunk(chunk, rate, token) {
    let effectiveLang = chunk.lang;
    const voiceSelectId = `voice-${chunk.lang}`;
    const selectEl = document.getElementById(voiceSelectId);
    const selectedVoiceName = localStorage.getItem(voiceSelectId) || (selectEl ? selectEl.value : null);
    if (isNatiaVoice(selectedVoiceName)) {
        effectiveLang = 'ka';
    }
    const state = piperWorkers[effectiveLang] || piperWorkers['ka'];
    const ready = await waitForPiperReady(state, token);
    if (!ready) return false;

    const spokenList = chunk.sentences.map(s => buildSpokenSentence(s, chunk.lang));
    const wavPromises = spokenList.map(spoken => synthesizeSentence(effectiveLang, spoken.text, token));

    // Group sentences by pIndex
    const paragraphs = [];
    let currentP = [];
    for (let i = 0; i < chunk.sentences.length; i++) {
        const item = chunk.sentences[i];
        if (currentP.length === 0) {
            currentP.push({ item, idx: i });
        } else {
            const prevItem = currentP[currentP.length - 1].item;
            if (item.pIndex !== prevItem.pIndex) {
                paragraphs.push(currentP);
                currentP = [{ item, idx: i }];
            } else {
                currentP.push({ item, idx: i });
            }
        }
    }
    if (currentP.length > 0) paragraphs.push(currentP);

    for (const p of paragraphs) {
        if (token !== playbackToken || !isPlaying) return false;
        
        const pPromises = p.map(x => wavPromises[x.idx]);
        
        const spinnerTimeout = setTimeout(() => {
            setTtsStatus("⏳ Piper Buffering...");
        }, 100); 
        
        try {
            // აუცილებლად ველოდებით აბზაცის პირველ წინადადებას
            await pPromises[0];
            // ვაძლევთ დამატებით 2 წამს ფონურ გენერაციას, რომ დანარჩენი წინადადებებიც მოასწროს
            if (pPromises.length > 1) {
                await Promise.race([
                    Promise.all(pPromises.slice(1)),
                    new Promise(r => setTimeout(r, 2000))
                ]);
            }
        } catch(e) {
            console.error("Piper buffer error", e);
            clearTimeout(spinnerTimeout);
            setTtsStatus(null);
            continue; 
        }
        
        clearTimeout(spinnerTimeout);
        setTtsStatus(null);
        if (token !== playbackToken || !isPlaying) return false;

        for (const { item, idx } of p) {
            if (token !== playbackToken || !isPlaying) return false;
            
            const currentItem = item;
            const globalIdx = currentItem.index;
            if (globalIdx > 0) {
                const prevItem = parsedContent[globalIdx - 1];
                const currType = getHeaderType(currentItem.element);
                const prevType = getHeaderType(prevItem ? prevItem.element : null);

                const beforeMs = currType === 'main' ? pauseSettings.mainHeader : (currType === 'internal' ? pauseSettings.internalHeader : (currentItem.pIndex !== (prevItem ? prevItem.pIndex : currentItem.pIndex) ? pauseSettings.paragraph : 0));
                const afterMs = prevType ? pauseSettings.postHeader : 0;
                const delayMs = Math.max(beforeMs, afterMs);

                if (delayMs > 0) {
                    await new Promise(r => setTimeout(r, delayMs));
                }
            }
            if (token !== playbackToken || !isPlaying) return false;

            let wav;
            const spinnerTimeout = setTimeout(() => {
                setTtsStatus("⏳ Piper is thinking...");
            }, 300); // Show spinner if wait is longer than 300ms
            
            try {
                wav = await wavPromises[idx];
            } catch (err) {
                console.error("Piper synth error", err);
            }
            
            clearTimeout(spinnerTimeout);
            setTtsStatus(null);
            
            if (token !== playbackToken || !isPlaying) return false;

            currentIdx = currentItem.index;
            highlightSentence(currentIdx, true);
            updateMediaPosition();

            if (wav) {
                await playPiperAudio(state, wav, rate, spokenList[idx], token);
            } else {
                await new Promise(r => setTimeout(r, 600 / rate));
            }
        }
    }
    return token === playbackToken && isPlaying;
}

function playGoogleAudio(audioUrl, rate, spoken, token) {
    return new Promise((resolve) => {
        const audio = new Audio(audioUrl);
        audio.playbackRate = Math.max(0.5, Math.min(rate, 4));
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            clearInterval(guard);
            URL.revokeObjectURL(audioUrl); // Clean up Blob URL
            resolve();
        };
        const guard = setInterval(() => {
            if (token !== playbackToken || !isPlaying) { audio.pause(); finish(); }
        }, 100);
        audio.onended = finish;
        audio.onerror = finish;
        audio.addEventListener('loadedmetadata', () => {
            if (spoken && spoken.totalChars) {
                runWordHighlights(audio, spoken, token, null);
            }
        });
        audio.play().catch(finish);
    });
}

async function playGoogleChunk(chunk, rate, token) {
    const spokenList = chunk.sentences.map(s => buildSpokenSentence(s, chunk.lang));
    
    for (let i = 0; i < chunk.sentences.length; i++) {
        if (token !== playbackToken || !isPlaying) return false;

        const currentItem = chunk.sentences[i];
        const globalIdx = currentItem.index;
        if (globalIdx > 0) {
            const prevItem = parsedContent[globalIdx - 1];
            const currType = getHeaderType(currentItem.element);
            const prevType = getHeaderType(prevItem ? prevItem.element : null);

            const beforeMs = currType === 'main' ? pauseSettings.mainHeader : (currType === 'internal' ? pauseSettings.internalHeader : (currentItem.pIndex !== (prevItem ? prevItem.pIndex : currentItem.pIndex) ? pauseSettings.paragraph : 0));
            const afterMs = prevType ? pauseSettings.postHeader : 0;
            const delayMs = Math.max(beforeMs, afterMs);

            if (delayMs > 0) {
                await new Promise(r => setTimeout(r, delayMs));
            }
        }
        if (token !== playbackToken || !isPlaying) return false;

        currentIdx = chunk.sentences[i].index;
        highlightSentence(currentIdx, true);
        updateMediaPosition();

        const base = window.THEME_URI || '/wp-content/themes/zurabkostava';
        let textToSpeak = spokenList[i].text;
        
        async function fetchBlobUrl(text) {
            const hasSpeakable = (() => {
                try { return new RegExp('\\p{L}|\\p{N}', 'u').test(text); } 
                catch(e) { return /[a-zA-Z0-9\u10D0-\u10FA\u10A0-\u10CF\u0400-\u04FF]/.test(text); }
            })();
            if (!text || !hasSpeakable) return null;
            const fetchUrl = base + '/web-reader/google-tts.php?tl=' + encodeURIComponent(chunk.lang.split('-')[0]) + '&text=' + encodeURIComponent(text);
            try {
                const res = await fetch(fetchUrl);
                if (!res.ok) return null;
                const blob = await res.blob();
                return URL.createObjectURL(blob);
            } catch (e) { return null; }
        }
        
        if (textToSpeak.length > 190) {
            const words = textToSpeak.split(' ');
            let temp = '';
            for (let w of words) {
                if (temp.length + w.length + 1 > 190) {
                    const bUrl = await fetchBlobUrl(temp);
                    if (bUrl) await playGoogleAudio(bUrl, rate, null, token); // Disable word-level highlights for sliced chunks
                    temp = w + ' ';
                } else {
                    temp += w + ' ';
                }
            }
            if (temp.trim().length > 0) {
                const bUrl = await fetchBlobUrl(temp);
                if (bUrl) {
                    await playGoogleAudio(bUrl, rate, null, token);
                } else {
                    await new Promise(r => setTimeout(r, 600 / rate));
                }
            }
        } else {
            const bUrl = await fetchBlobUrl(textToSpeak);
            if (bUrl) {
                await playGoogleAudio(bUrl, rate, spokenList[i], token);
            } else {
                await new Promise(r => setTimeout(r, 600 / rate));
            }
        }
    }
    return token === playbackToken && isPlaying;
}

function highlightChunk(chunk) {
    document.querySelectorAll('.active').forEach(el => {
        el.classList.remove('active');
        el.classList.add('read');
    });
    chunk.sentences.forEach(s => {
        if (s.element) {
            s.element.classList.add('active');
            s.element.classList.remove('read');
            if (s === chunk.sentences[0]) {
                const elRect = s.element.getBoundingClientRect();
                if (elRect.top < 0 || elRect.bottom > contentArea.clientHeight) {
                    s.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
    });
}



function runWordHighlights(audio, spoken, token, sentenceRanges) {
    let lastEl = null;
    let lastSentIdx = currentIdx;
    const step = () => {
        if (token !== playbackToken || audio.ended) {
            if (lastEl) { lastEl.classList.remove('active'); }
            return;
        }
        const dur = audio.duration;
        if (isFinite(dur) && dur > 0 && spoken.totalChars > 0) {
            const pos = (audio.currentTime / dur) * spoken.totalChars;
            let range = null;
            for (const r of spoken.wordRanges) { if (pos >= r.start && pos < r.end) { range = r; break; } }
            if (range && lastEl !== range.el) {
                if (lastEl) { lastEl.classList.remove('active'); }
                range.el.classList.add('active');
                lastEl = range.el;
                
                if (sentenceRanges) {
                    const currentSent = sentenceRanges.find(s => pos < s.sentenceEndChar);
                    if (currentSent && currentSent.idx !== lastSentIdx) {
                        lastSentIdx = currentSent.idx;
                        currentIdx = lastSentIdx;
                        highlightSentence(currentIdx, true);
                        updateMediaPosition();
                    }
                }
            }
        }
        requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

async function playNativeChunk(chunk, nativeVoice, rate, token) {
    let currentBatch = [];
    let currentBatchText = "";

    const playBatch = async () => {
        if (currentBatch.length === 0) return true;
        
        currentIdx = currentBatch[0].idx;
        highlightSentence(currentIdx, true);
        updateMediaPosition();

        const ok = await new Promise((resolve) => {
            let settled = false;
            let watchdog = null;

            const utt = new SpeechSynthesisUtterance(currentBatchText);
            
            // Dynamic fallback: If nativeVoice was not ready at batch creation, re-query live voices now!
            if (!nativeVoice && typeof speechSynthesis !== 'undefined') {
                const liveVoices = speechSynthesis.getVoices();
                if (liveVoices && liveVoices.length > 0) {
                    voices = Array.from(liveVoices).filter(v => v && typeof v.name === 'string');
                    nativeVoice = voices.find(v => v && v.name === selectedVoiceName) || voices.find(v => v && langMatches(v.lang, chunk.lang)) || voices[0];
                }
            }
            if (nativeVoice) utt.voice = nativeVoice;
            utt.rate = rate;
            utt.lang = chunk.lang;

            let lastActiveWord = null;
            utt.onboundary = (event) => {
                if (event.name === 'word') {
                    const charPos = event.charIndex;
                    const currentSent = currentBatch.find(s => charPos < s.sentenceEndChar);
                    if (currentSent) {
                        if (currentSent.idx !== currentIdx) {
                            currentIdx = currentSent.idx;
                            highlightSentence(currentIdx, true);
                            updateMediaPosition();
                            lastActiveWord = null;
                        }

                        const currentWordRange = currentSent.wordRanges.find(r => charPos >= r.start && charPos < r.end);
                        if (currentWordRange && lastActiveWord !== currentWordRange.el) {
                            if (lastActiveWord) {
                                lastActiveWord.classList.remove('active');
                                lastActiveWord.classList.add('read');
                            }
                            currentWordRange.el.classList.add('active');
                            lastActiveWord = currentWordRange.el;
                        }
                    }
                }
            };

            const finish = (e) => {
                if (settled) return;
                settled = true;
                if (watchdog) clearInterval(watchdog);
                settle(e);
            };

            const settle = (e) => {
                if (lastActiveWord) {
                    lastActiveWord.classList.remove('active');
                    lastActiveWord.classList.add('read');
                }
                
                // --- Edge/Azure Truncation & Error Safeguard ---
                if (isPlaying && token === playbackToken) {
                    let isUnexpectedDrop = false;
                    
                    // 1. Check for explicit error
                    if (e && e.type === 'error' && e.error && e.error !== 'canceled' && e.error !== 'interrupted') {
                        console.warn("TTS Error encountered:", e.error);
                        isUnexpectedDrop = true;
                    }
                    
                    // 2. Check for silent truncation (onend fired but currentIdx is lagging)
                    if (currentBatch.length > 0) {
                        const lastSent = currentBatch[currentBatch.length - 1];
                        if (currentIdx < lastSent.idx) {
                            console.warn(`TTS Truncated silently! currentIdx=${currentIdx}, expected=${lastSent.idx}`);
                            isUnexpectedDrop = true;
                        }
                    }
                    
                    if (isUnexpectedDrop) {
                        resolve(false);
                        setTimeout(() => {
                            if (isPlaying) {
                                playMergedQueue();
                            }
                        }, 500);
                        return;
                    }
                }
                
                resolve(token === playbackToken && isPlaying);
            };

            // Watchdog: Unpause if Android/Edge paused synthesis unexpectedly
            watchdog = setInterval(() => {
                if (!isPlaying || token !== playbackToken) {
                    finish({ type: 'end' });
                    return;
                }
                const synth = window.speechSynthesis || synthesis;
                if (synth && synth.paused) {
                    synth.resume();
                }
            }, 1000);

            utt.onend = finish;
            utt.onerror = finish;
            window.utterances.push(utt);
            try {
                const synth = window.speechSynthesis || synthesis;
                if (synth && synth.paused) synth.resume();
                synth.speak(utt);
            } catch (err) {
                console.error("synthesis.speak error:", err);
                finish({ type: 'error' });
            }
        });

        currentBatch = [];
        currentBatchText = "";
        return ok;
    };

    for (let i = 0; i < chunk.sentences.length; i++) {
        if (token !== playbackToken || !isPlaying) return false;

        const currentItem = chunk.sentences[i];
        const globalIdx = currentItem.index;
        let delayMs = 0;

        if (globalIdx > 0) {
            const prevItem = parsedContent[globalIdx - 1];
            const currType = getHeaderType(currentItem.element);
            const prevType = getHeaderType(prevItem ? prevItem.element : null);

            const beforeMs = currType === 'main' ? pauseSettings.mainHeader : (currType === 'internal' ? pauseSettings.internalHeader : (currentItem.pIndex !== (prevItem ? prevItem.pIndex : currentItem.pIndex) ? pauseSettings.paragraph : 0));
            const afterMs = prevType ? pauseSettings.postHeader : 0;
            delayMs = Math.max(beforeMs, afterMs);
        }

        if (delayMs > 0) {
            const ok = await playBatch();
            if (!ok || token !== playbackToken || !isPlaying) return false;
            
            await new Promise(r => setTimeout(r, delayMs));
            if (token !== playbackToken || !isPlaying) return false;
        }

        const spoken = buildSpokenSentence(currentItem, chunk.lang);
        
        if (!spoken.speakable) {
            const ok = await playBatch();
            if (!ok || token !== playbackToken || !isPlaying) return false;
            
            currentIdx = currentItem.index;
            highlightSentence(currentIdx, true);
            updateMediaPosition();
            await new Promise(r => setTimeout(r, 600 / rate));
            continue;
        }

        const offset = currentBatchText.length;
        const wordRanges = spoken.wordRanges.map(r => ({ el: r.el, start: r.start + offset, end: r.end + offset }));
        currentBatchText += spoken.raw;
        currentBatch.push({ idx: currentItem.index, element: currentItem.element, wordRanges: wordRanges, sentenceEndChar: currentBatchText.length });
    }
    
    return await playBatch();
}

async function playMergedQueue() {
    synthesis.cancel(); stopPiperAudio();
    window.utterances = [];
    isPlaying = true;
    updatePlayIcon(true);
    const token = ++playbackToken;

    let chunks = [];
    let currentChunk = null;
    const SAFE_CHAR_LIMIT = 4000;
    for (let i = currentIdx; i < parsedContent.length; i++) {
        const item = parsedContent[i];
        let shouldBreak = false;
        if (!currentChunk) shouldBreak = true;
        else {
            if (currentChunk.lang !== item.lang) shouldBreak = true;
            if (currentChunk.textLength + item.textForUI.length > SAFE_CHAR_LIMIT) shouldBreak = true;
        }
        if (shouldBreak) { currentChunk = { lang: item.lang, sentences: [], textLength: 0 }; chunks.push(currentChunk); }
        currentChunk.sentences.push(item);
        currentChunk.textLength += item.textForUI.length;
    }

    for (let chunkIndex = 0; chunkIndex < chunks.length; chunkIndex++) {
        if (token !== playbackToken || !isPlaying) return;
        const chunk = chunks[chunkIndex];
        const voiceSelectId = `voice-${chunk.lang}`;
        const rateInputId = `rate-${chunk.lang}`;
        const selectEl = document.getElementById(voiceSelectId);
        const selectedVoiceName = localStorage.getItem(voiceSelectId) || (selectEl ? selectEl.value : null);
        const rate = parseFloat(localStorage.getItem(rateInputId) || '1');

        const piperVoice = findPiperVoice(selectedVoiceName);

        if (selectedVoiceName && selectedVoiceName.startsWith('google:')) {
            const ok = await playGoogleChunk(chunk, rate, token);
            if (!ok) return;
        } else if (piperVoice) {
            let effectiveLang = chunk.lang;
            if (piperVoice.key === 'ka_GE-natia-medium' || piperVoice.lang === 'ka_GE' || isNatiaVoice(piperVoice.name)) {
                effectiveLang = 'ka';
            }
            let state = piperWorkers[effectiveLang] || piperWorkers['ka'];
            if (!state || state.voicePath !== piperVoice.path || !state.ready) {
                setTtsStatus(`Initializing Neural Voice (${piperVoice.name})...`);
                initPiperWorker(effectiveLang, piperVoice.path);
                state = piperWorkers[effectiveLang] || piperWorkers['ka'];
                const ok = await waitForPiperReady(state, token);
                setTtsStatus(null);
                if (!ok || token !== playbackToken || !isPlaying) {
                    stopReading();
                    return;
                }
            }
            const ok = await playPiperChunk(chunk, rate, token);
            if (!ok) return;
        } else {
            const nativeVoice = voices.find(v => v && v.name === selectedVoiceName) || voices.find(v => v && langMatches(v.lang, chunk.lang)) || voices[0];
            const ok = await playNativeChunk(chunk, nativeVoice, rate, token);
            if (!ok) return;
        }
    }
    if (token === playbackToken && isPlaying) handleNextChapterLogic();
}
function updatePlayIcon(isPlayingState) {
    const playIcon = document.getElementById('play-icon');
    const pauseIcon = document.getElementById('pause-icon');
    if (isPlayingState) {
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
        if('mediaSession' in navigator) navigator.mediaSession.playbackState = "playing";
    } else {
        playIcon.classList.remove('hidden');
        pauseIcon.classList.add('hidden');
        if('mediaSession' in navigator) navigator.mediaSession.playbackState = "paused";
    }
}
function togglePlay() {
    if (parsedContent.length === 0) return;

    if (isPlaying) {
        synthesis.pause();
        ghostAudio.pause();
        isPlaying = false;
        updatePlayIcon(false);
        releaseWakeLock();
        if ('mediaSession' in navigator) navigator.mediaSession.playbackState = "paused";
    } else {
        // Proactively wake up speech engine on user play gesture
        wakeUpSpeechEngine();

        ghostAudio.play().then(() => {
            updateMediaSessionMetadata();
            if ('mediaSession' in navigator) navigator.mediaSession.playbackState = "playing";
            updateMediaPosition();
            requestWakeLock();
            if (synthesis.paused) {
                synthesis.resume();
            } else {
                playMergedQueue();
            }
            isPlaying = true;
            updatePlayIcon(true);
        }).catch(e => {
            console.error("Audio Play failed:", e);
            playMergedQueue();
            isPlaying = true;
            updatePlayIcon(true);
        });
    }
}
function stopReading() {
    playbackToken++; // kill any in-flight playback loop
    synthesis.cancel(); stopPiperAudio();
    window.utterances = [];
    ghostAudio.pause();
    ghostAudio.currentTime = 0;
    isPlaying = false;
    updatePlayIcon(false);
    clearHighlights();
    releaseWakeLock();
    if('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = "none";
    }
}
function navigateSentence(dir) {
    synthesis.cancel(); stopPiperAudio();
    let newIdx = currentIdx + dir;
    if (newIdx < 0) newIdx = 0;
    if (newIdx >= parsedContent.length) newIdx = parsedContent.length - 1;
    currentIdx = newIdx;
    // ღილაკით გადასვლა ინახავს!
    highlightSentence(currentIdx, true);
    updateMediaPosition();
    if (isPlaying) playMergedQueue();
}

// 🔥 Ultra-Optimized Highlight Sentence (O(1) sequential reading, zero DOM thrashing)
let lastHighlightedIdx = -1;

function highlightSentence(idx, saveToStorage = false) {
    if (!parsedContent || parsedContent.length === 0) return;
    if (idx < 0) idx = 0;
    if (idx >= parsedContent.length) idx = parsedContent.length - 1;
    currentIdx = idx;

    const currentItem = parsedContent[idx];
    const currentEl = currentItem ? currentItem.element : null;
    if (!currentEl) return;

    // 1. FAST PATH: Sequential step forward (99.9% of normal continuous reading)
    if (lastHighlightedIdx !== -1 && idx === lastHighlightedIdx + 1) {
        const prevItem = parsedContent[lastHighlightedIdx];
        if (prevItem && prevItem.element) {
            prevItem.element.classList.remove('active');
            prevItem.element.classList.add('read');
            const prevWord = prevItem.element.querySelector('.word.active');
            if (prevWord) prevWord.classList.remove('active');
        }
        currentEl.classList.remove('read');
        currentEl.classList.add('active');
    }
    // 2. JUMP / SEEK / INITIAL LOAD PATH: Only runs when user seeks or jumps
    else {
        for (let i = 0; i < parsedContent.length; i++) {
            const el = parsedContent[i].element;
            if (!el) continue;
            if (i < idx) {
                if (!el.classList.contains('read')) el.classList.add('read');
                if (el.classList.contains('active')) el.classList.remove('active');
            } else if (i === idx) {
                if (el.classList.contains('read')) el.classList.remove('read');
                if (!el.classList.contains('active')) el.classList.add('active');
            } else {
                if (el.classList.contains('read')) el.classList.remove('read');
                if (el.classList.contains('active')) el.classList.remove('active');
            }
        }
        document.querySelectorAll('.word.active').forEach(w => w.classList.remove('active'));
    }

    lastHighlightedIdx = idx;

    // 3. Viewport-aware, steady scrolling (no continuous smooth-scroll interrupts)
    scrollToCenter(contentArea, currentEl);

    // 4. Progress bar (lightweight, single element width calculation)
    updateProgressBar();

    // 5. Storage and Cloud Sync (debounced)
    if (saveToStorage && window.currentRawEpubFile && currentBook) {
        localStorage.setItem('epub_idx_' + window.currentRawEpubFile.name, idx);

        const currentSpineItem = currentBook.spine.get(currentSpineIndex);
        if (currentSpineItem) {
            localStorage.setItem('epub_progress_' + window.currentRawEpubFile.name, currentSpineItem.href);
        }

        updateProgressPercentage();
        // Debounced sync (2s) to prevent spamming HTTP POST on every sentence
        syncProgressToCloud(false);
    }
}

function scrollToCenter(container, element) {
    if (!container || !element) return;
    
    // Check if element is already comfortably in the middle reading viewport
    const containerRect = container.getBoundingClientRect();
    const elementRect = element.getBoundingClientRect();
    
    const relTop = elementRect.top - containerRect.top;
    const relBottom = elementRect.bottom - containerRect.top;
    const viewHeight = container.clientHeight;
    
    // Comfort zone: between 15% and 65% of viewport height
    const isComfortable = (relTop >= viewHeight * 0.15) && (relBottom <= viewHeight * 0.65);
    if (isComfortable) return; // Steady reading — no scroll needed!

    const elementTop = element.offsetTop;
    const elementHeight = element.offsetHeight;
    let targetScroll = elementTop - (viewHeight / 2) + (elementHeight / 2);
    if (targetScroll < 0) targetScroll = 0;
    
    container.scrollTo({ top: targetScroll, behavior: 'smooth' });
}

function clearHighlights() {
    lastHighlightedIdx = -1;
    document.querySelectorAll('.sentence.active').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.word.active').forEach(el => el.classList.remove('active'));
}
function setupModalClosing() {
    const modalOverlay = document.getElementById('book-info-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    if (!modalOverlay) return;

    if (closeBtn) {
        closeBtn.onclick = (e) => { 
            e.preventDefault(); 
            e.stopPropagation(); 
            modalOverlay.classList.add('hidden'); 
            modalOverlay.dataset.openedBy = '';
        };
    }

    // Dismiss on backdrop click or pointerdown
    modalOverlay.addEventListener('pointerdown', (e) => {
        if (e.target === modalOverlay) {
            modalOverlay.classList.add('hidden');
            modalOverlay.dataset.openedBy = '';
        }
    });

    // Mobile Long-Press on Modal or Backdrop to Close (500ms touch & hold)
    let modalTouchTimer = null;
    let modalTouchStartX = 0;
    let modalTouchStartY = 0;

    modalOverlay.addEventListener('touchstart', (e) => {
        if (modalOverlay.classList.contains('hidden')) return;
        if (e.target.closest('#modal-mark-read-checkbox') || 
            e.target.closest('#modal-open-book-btn') || 
            e.target.closest('#close-modal-btn') ||
            e.target.closest('.modal-read-toggle-label')) {
            return;
        }
        if (e.touches.length !== 1) return;
        modalTouchStartX = e.touches[0].clientX;
        modalTouchStartY = e.touches[0].clientY;

        modalTouchTimer = setTimeout(() => {
            if (navigator.vibrate) {
                try { navigator.vibrate(40); } catch(err){}
            }
            modalOverlay.classList.add('hidden');
            modalOverlay.dataset.openedBy = '';
        }, 500);
    }, { passive: true });

    modalOverlay.addEventListener('touchmove', (e) => {
        if (!modalTouchTimer) return;
        const dx = Math.abs(e.touches[0].clientX - modalTouchStartX);
        const dy = Math.abs(e.touches[0].clientY - modalTouchStartY);
        if (dx > 10 || dy > 10) {
            clearTimeout(modalTouchTimer);
            modalTouchTimer = null;
        }
    }, { passive: true });

    modalOverlay.addEventListener('touchend', () => {
        if (modalTouchTimer) {
            clearTimeout(modalTouchTimer);
            modalTouchTimer = null;
        }
    });

    // Desktop Hover / Unhover Auto-close
    const modalContent = modalOverlay.querySelector('.info-modal-content');
    let hoverCloseTimer = null;

    if (modalContent) {
        modalContent.addEventListener('mouseenter', () => {
            modalOverlay.dataset.hasEnteredContent = 'true';
            if (hoverCloseTimer) {
                clearTimeout(hoverCloseTimer);
                hoverCloseTimer = null;
            }
        });

        modalContent.addEventListener('mouseleave', () => {
            if (modalOverlay.dataset.openedBy === 'hover') {
                hoverCloseTimer = setTimeout(() => {
                    modalOverlay.classList.add('hidden');
                    modalOverlay.dataset.openedBy = '';
                }, 220);
            }
        });
    }

    modalOverlay.addEventListener('mousemove', (e) => {
        if (modalOverlay.dataset.openedBy === 'hover' && e.target === modalOverlay) {
            const openTime = parseInt(modalOverlay.dataset.openTime || '0', 10);
            const elapsed = Date.now() - openTime;
            if (modalOverlay.dataset.hasEnteredContent === 'true' || elapsed > 550) {
                if (!hoverCloseTimer) {
                    hoverCloseTimer = setTimeout(() => {
                        modalOverlay.classList.add('hidden');
                        modalOverlay.dataset.openedBy = '';
                    }, 180);
                }
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (!modalOverlay.classList.contains('hidden')) {
                modalOverlay.classList.add('hidden');
                modalOverlay.dataset.openedBy = '';
            }
        }
    });
}
setupModalClosing();
function init() {
    // Build the panel immediately so selects are never blank while Piper voices download
    rebuildDynamicSettings();
    
    // Proactively warm up browser speech engine on Android / Edge
    wakeUpSpeechEngine();

    // Auto-warmup saved Piper voices (e.g. Natia) from cache immediately
    autoWarmupSavedPiperVoices();
    checkAndMarkCachedPiperVoices();

    fetchPiperVoices().then(() => {
        rebuildDynamicSettings();
        loadVoices();
        autoWarmupSavedPiperVoices();
        checkAndMarkCachedPiperVoices();
    }).catch(e => {
        console.error('init voices failed', e);
        rebuildDynamicSettings();
        loadVoices();
    });
    if (typeof speechSynthesis !== 'undefined') {
        speechSynthesis.onvoiceschanged = loadVoices;
        try {
            speechSynthesis.addEventListener('voiceschanged', loadVoices);
        } catch(e) {}
    }
    initMediaSession();
}
// ============================================================================
// 📚 Ultra-Fast Library Cache & Lazy Cover Engine
// ============================================================================
const IDB_NAME = 'neural_reader_db';
const IDB_STORE = 'book_metadata';

function getBookIdb() {
    return new Promise((resolve) => {
        if (!window.indexedDB) return resolve(null);
        try {
            const req = indexedDB.open(IDB_NAME, 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(IDB_STORE)) {
                    db.createObjectStore(IDB_STORE, { keyPath: 'url' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => resolve(null);
        } catch (e) {
            resolve(null);
        }
    });
}

async function getCachedBookMeta(url) {
    try {
        const db = await getBookIdb();
        if (!db) return null;
        return new Promise((resolve) => {
            const tx = db.transaction(IDB_STORE, 'readonly');
            const store = tx.objectStore(IDB_STORE);
            const req = store.get(url);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => resolve(null);
        });
    } catch(e) { return null; }
}

async function saveCachedBookMeta(url, data) {
    try {
        const db = await getBookIdb();
        if (!db) return;
        const tx = db.transaction(IDB_STORE, 'readwrite');
        const store = tx.objectStore(IDB_STORE);
        store.put({ url, ...data, timestamp: Date.now() });
    } catch(e) {}
}

let searchDebounceTimer = null;
let coverObserver = null;
const coverQueue = [];
let activeCoverExtractions = 0;
const MAX_CONCURRENT_COVERS = 2;

function processCoverQueue() {
    while (activeCoverExtractions < MAX_CONCURRENT_COVERS && coverQueue.length > 0) {
        const task = coverQueue.shift();
        activeCoverExtractions++;
        task().finally(() => {
            activeCoverExtractions--;
            processCoverQueue();
        });
    }
}

function initCoverObserver() {
    if (coverObserver) {
        coverObserver.disconnect();
    }
    coverObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                coverObserver.unobserve(el);
                const bookUrl = el.dataset.bookUrl;
                const cardId = el.dataset.cardId;
                if (bookUrl && cardId) {
                    coverQueue.push(() => extractCoverForCard(bookUrl, cardId));
                    processCoverQueue();
                }
            }
        });
    }, { rootMargin: '150px 0px' });
}

let currentLibrarySort = 'title';
let allBooksCache = [];

function getBookProgress(book) {
    const fileName = book.url ? book.url.split('/').pop() : '';
    const saved = localStorage.getItem('epub_perc_' + fileName);
    if (saved !== null && saved !== undefined && !isNaN(parseFloat(saved))) {
        return parseFloat(saved);
    }
    if (book.perc !== undefined && book.perc !== null && !isNaN(parseFloat(book.perc))) {
        return parseFloat(book.perc);
    }
    return 0;
}

function getFilteredAndSortedBooks() {
    const searchInput = document.getElementById('library-search-input');
    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    let list = allBooksCache ? [...allBooksCache] : [];
    if (searchTerm) {
        list = list.filter(book => 
            (book.title && book.title.toLowerCase().includes(searchTerm)) || 
            (book.author && book.author.toLowerCase().includes(searchTerm))
        );
    }
    if (currentLibrarySort === 'title') {
        list.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
    } else if (currentLibrarySort === 'author') {
        list.sort((a, b) => (a.author || '').localeCompare(b.author || ''));
    } else if (currentLibrarySort === 'progress') {
        list.sort((a, b) => {
            const diff = getBookProgress(b) - getBookProgress(a);
            if (Math.abs(diff) > 0.001) return diff;
            return (a.title || '').localeCompare(b.title || '');
        });
    }
    return list;
}

function bindLibrarySortButtons() {
    const sortBtns = document.querySelectorAll('.library-sort-btn');
    sortBtns.forEach(btn => {
        btn.onclick = () => {
            sortBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentLibrarySort = btn.dataset.sort || 'title';
            drawBooksToGrid(getFilteredAndSortedBooks());
        };
    });
}

async function renderLibrary() {
    libraryGrid.innerHTML = '<div style="color:white; text-align:center; padding:20px;">Scanning bookshelf... 📚</div>';
    try {
        bindLibrarySortButtons();
        const response = await fetch('/wp-json/neural/v1/books');
        if (!response.ok) throw new Error("Scanner failed");
        allBooksCache = await response.json();
        updateCountBadge(allBooksCache.length);
        drawBooksToGrid(getFilteredAndSortedBooks());

        const searchInput = document.getElementById('library-search-input');
        if (searchInput) {
            searchInput.value = "";
            searchInput.oninput = (e) => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    drawBooksToGrid(getFilteredAndSortedBooks());
                }, 150);
            };
        }
    } catch (error) {
        console.error("Library Error:", error);
        libraryGrid.innerHTML = '<div style="color:red;">Error loading library.</div>';
    }
}

// --- PROCEDURAL BOOK COVER SYSTEM ---
function getProceduralPaletteIndex(str) {
    if (!str) return 0;
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = (hash << 5) - hash + str.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash) % 8;
}

const PROCEDURAL_EMBLEMS = ['📖', '⚡', '✨', '🌌', '🌿', '🔮', '🏛️', '🧭', '📜', '🌙'];
function getProceduralEmblem(str) {
    if (!str) return '📖';
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = (hash << 3) - hash + str.charCodeAt(i);
        hash |= 0;
    }
    return PROCEDURAL_EMBLEMS[Math.abs(hash) % PROCEDURAL_EMBLEMS.length];
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderProceduralCover(title, author) {
    const safeTitle = escapeHtml(title || 'Untitled');
    const safeAuthor = escapeHtml(author || 'Classic Edition');
    const paletteIdx = getProceduralPaletteIndex(title || '');
    const emblem = getProceduralEmblem(title || '');

    return `
    <div class="procedural-book-cover procedural-palette-${paletteIdx}">
        <div class="cover-spine-highlight"></div>
        <div class="cover-top-accent">
            <span class="cover-emblem">${emblem}</span>
        </div>
        <div class="cover-center-content">
            <div class="cover-book-title">${safeTitle}</div>
            <div class="cover-accent-divider"></div>
            <div class="cover-book-author">${safeAuthor}</div>
        </div>
        <div class="cover-bottom-tag">NEURAL EDITION</div>
    </div>`;
}

let currentModalBook = null;
let currentModalCard = null;

function updateCardProgressBadge(card, fileName, perc) {
    if (!card) return;
    let overlay = card.querySelector('.card-progress-overlay');
    if (!perc || parseFloat(perc) <= 0) {
        if (overlay) overlay.remove();
        return;
    }
    const num = parseFloat(perc) || 0;
    const isCompleted = num >= 99;

    const badgeContent = isCompleted ? `
        <span style="display:flex; align-items:center; gap:4px;"><span class="completed-check-icon">✓</span>${perc}%</span>
        <button class="reset-book-btn" title="Reset Progress" style="background:transparent; border:none; color:#ef4444; cursor:pointer; padding:2px; height:18px; width:18px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
        </button>
    ` : `
        <span>${perc}%</span>
        <button class="reset-book-btn" title="Reset Progress" style="background:transparent; border:none; color:#ef4444; cursor:pointer; padding:2px; height:18px; width:18px;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
        </button>
    `;

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'card-progress-overlay' + (isCompleted ? ' card-progress-completed' : '');
        overlay.style.display = 'flex';
        overlay.style.justifyContent = 'space-between';
        overlay.style.alignItems = 'center';
        overlay.innerHTML = badgeContent;
        card.insertBefore(overlay, card.firstChild);
    } else {
        overlay.className = 'card-progress-overlay' + (isCompleted ? ' card-progress-completed' : '');
        overlay.innerHTML = badgeContent;
    }
}

async function openBookInfoModal(book, card, triggerType = 'click') {
    const modal = document.getElementById('book-info-modal');
    if (!modal) return;
    currentModalBook = book;
    currentModalCard = card;

    modal.dataset.openedBy = triggerType;
    modal.dataset.openTime = Date.now().toString();
    modal.dataset.hasEnteredContent = 'false';

    const safeSetText = (id, text) => { 
        const el = document.getElementById(id); 
        if (el) el.textContent = text; 
    };

    const fileName = book.url ? book.url.split('/').pop() : '';
    safeSetText('modal-book-title', book.title || 'Untitled');
    safeSetText('modal-book-author', book.author || 'Unknown Author');

    // Setup cover in info modal
    const coverContainer = document.getElementById('modal-cover-container');
    if (coverContainer) {
        const hasCover = book.cover && typeof book.cover === 'string' && book.cover.trim() !== '';
        if (hasCover) {
            coverContainer.innerHTML = `<img id="modal-book-cover" src="${escapeHtml(book.cover)}" alt="Cover" onerror="this.parentElement.innerHTML = renderProceduralCover('${escapeHtml(book.title || '')}', '${escapeHtml(book.author || '')}');">`;
        } else {
            coverContainer.innerHTML = renderProceduralCover(book.title, book.author);
        }
    }

    // Progress & Mark as Read Toggle
    const markReadCheckbox = document.getElementById('modal-mark-read-checkbox');
    const readBadge = document.getElementById('modal-read-badge');
    
    let savedPerc = localStorage.getItem('epub_perc_' + fileName);
    if (!savedPerc && book.perc !== undefined && book.perc !== null) {
        savedPerc = String(book.perc);
    }
    const numPerc = parseFloat(savedPerc || '0');
    const isCompleted = numPerc >= 99;

    if (markReadCheckbox) {
        markReadCheckbox.checked = isCompleted;
        markReadCheckbox.onchange = (e) => {
            const checked = e.target.checked;
            if (checked) {
                try { localStorage.setItem('epub_perc_' + fileName, '100'); } catch(err){}
                book.perc = '100';
                fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(fileName)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ href: '', idx: 0, perc: '100.00' })
                }).catch(err => console.error(err));

                if (readBadge) {
                    readBadge.textContent = '100% ✓';
                    readBadge.classList.add('completed');
                }
                updateCardProgressBadge(card, fileName, '100');
            } else {
                try {
                    localStorage.removeItem('epub_perc_' + fileName);
                    localStorage.removeItem('epub_progress_' + fileName);
                    localStorage.removeItem('epub_idx_' + fileName);
                } catch(err){}
                book.perc = '0';
                fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(fileName)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ href: '', idx: 0, perc: '0.00' })
                }).catch(err => console.error(err));

                if (readBadge) {
                    readBadge.textContent = '0%';
                    readBadge.classList.remove('completed');
                }
                updateCardProgressBadge(card, fileName, null);
            }
        };
    }

    if (readBadge) {
        if (isCompleted) {
            readBadge.textContent = `${savedPerc || '100'}% ✓`;
            readBadge.classList.add('completed');
        } else {
            readBadge.textContent = numPerc > 0 ? `${savedPerc}%` : '0%';
            readBadge.classList.remove('completed');
        }
    }

    const openBtn = document.getElementById('modal-open-book-btn');
    if (openBtn) {
        openBtn.onclick = () => {
            modal.classList.add('hidden');
            loadBookFromUrl(book.url);
        };
    }

    const pubEl = document.getElementById('modal-book-publisher');
    const genreContainer = document.getElementById('modal-book-genre');
    const descEl = document.getElementById('modal-book-desc');

    if (genreContainer) genreContainer.innerHTML = '<span class="genre-tag">Library Book</span>';
    if (pubEl) pubEl.classList.add('hidden');
    if (descEl) descEl.innerHTML = 'Scan in reader for full book summary.';

    if (book.url) {
        getCachedBookMeta(book.url).then(cached => {
            if (cached) {
                if (cached.publisher && pubEl) {
                    pubEl.textContent = cached.publisher;
                    pubEl.classList.remove('hidden');
                }
                if (cached.genres && cached.genres.length > 0 && genreContainer) {
                    genreContainer.innerHTML = '';
                    cached.genres.forEach(g => {
                        const tag = document.createElement('span');
                        tag.className = 'genre-tag';
                        tag.textContent = g;
                        genreContainer.appendChild(tag);
                    });
                }
                if (cached.description && descEl) {
                    descEl.innerHTML = cached.description;
                }
            }
        });
    }

    modal.classList.remove('hidden');
}

function drawBooksToGrid(booksList) {
    libraryGrid.innerHTML = '';
    if (booksList.length === 0) {
        libraryGrid.innerHTML = '<div style="color:gray; text-align:center; width:100%; padding:20px;">No books found matching criteria.</div>';
        return;
    }

    initCoverObserver();
    coverQueue.length = 0;

    const fragment = document.createDocumentFragment();

    booksList.forEach((book) => {
        const card = document.createElement('div');
        card.className = 'book-card';
        const uniqueId = `book-card-${Math.random().toString(36).substr(2, 9)}`;
        card.id = uniqueId;
        const fileName = book.url ? book.url.split('/').pop() : '';

        if (book.perc !== undefined && book.perc !== null) {
            try { localStorage.setItem('epub_perc_' + fileName, book.perc); } catch(e){}
        }

        const savedPerc = localStorage.getItem('epub_perc_' + fileName);

        let percHtml = '';
        if (savedPerc) {
            const numPerc = parseFloat(savedPerc) || 0;
            const isCompleted = numPerc >= 99;
            percHtml = `
            <div class="card-progress-overlay${isCompleted ? ' card-progress-completed' : ''}" style="display:flex; justify-content:space-between; align-items:center;">
                ${isCompleted ? `<span style="display:flex; align-items:center; gap:4px;"><span class="completed-check-icon">✓</span>${savedPerc}%</span>` : `<span>${savedPerc}%</span>`}
                <button class="reset-book-btn" title="Reset Progress" style="background:transparent; border:none; color:#ef4444; cursor:pointer; padding:2px; height:18px; width:18px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                </button>
            </div>`;
        }

        const hasServerCover = book.cover && typeof book.cover === 'string' && book.cover.trim() !== '';
        const proceduralHtml = renderProceduralCover(book.title, book.author);

        const coverHtml = `
        <div class="book-cover-container">
            ${proceduralHtml}
            ${hasServerCover ? `<img class="book-real-cover" loading="lazy" src="${escapeHtml(book.cover)}" alt="" onerror="this.remove();">` : ''}
        </div>`;

        const safeTitle = escapeHtml(book.title || 'Untitled');
        const safeAuthor = escapeHtml(book.author || 'Unknown');

        card.innerHTML = `${percHtml}${coverHtml}<div class="book-card-title" title="${safeTitle}">${safeTitle}</div><div class="book-card-author" title="${safeAuthor}">${safeAuthor}</div>`;

        // Desktop Hover (with 450ms intent delay)
        let hoverTimer = null;
        card.addEventListener('mouseenter', () => {
            if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                hoverTimer = setTimeout(() => {
                    openBookInfoModal(book, card, 'hover');
                }, 450);
            }
        });
        card.addEventListener('mouseleave', () => {
            if (hoverTimer) {
                clearTimeout(hoverTimer);
                hoverTimer = null;
            }
        });

        // Mobile Long-Press (Touch & Hold ~500ms)
        let touchTimer = null;
        let touchStartX = 0;
        let touchStartY = 0;
        let isLongPress = false;

        card.addEventListener('touchstart', (e) => {
            if (e.touches.length !== 1) return;
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isLongPress = false;

            touchTimer = setTimeout(() => {
                isLongPress = true;
                if (navigator.vibrate) {
                    try { navigator.vibrate(40); } catch(err){}
                }
                openBookInfoModal(book, card, 'longpress');
            }, 500);
        }, { passive: true });

        card.addEventListener('touchmove', (e) => {
            if (!touchTimer) return;
            const dx = Math.abs(e.touches[0].clientX - touchStartX);
            const dy = Math.abs(e.touches[0].clientY - touchStartY);
            if (dx > 10 || dy > 10) {
                clearTimeout(touchTimer);
                touchTimer = null;
            }
        }, { passive: true });

        card.addEventListener('touchend', (e) => {
            if (touchTimer) {
                clearTimeout(touchTimer);
                touchTimer = null;
            }
            if (isLongPress) {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        card.onclick = (e) => {
            if (isLongPress) {
                isLongPress = false;
                return;
            }
            if (hoverTimer) {
                clearTimeout(hoverTimer);
                hoverTimer = null;
            }
            const btn = e.target.closest('.reset-book-btn');
            if (btn) {
                e.stopPropagation();
                if (!confirm("Are you sure you want to reset reading progress for this book?")) return;

                localStorage.removeItem('epub_progress_' + fileName);
                localStorage.removeItem('epub_idx_' + fileName);
                localStorage.removeItem('epub_perc_' + fileName);

                fetch(`/wp-json/neural/v1/progress?book=${encodeURIComponent(fileName)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ href: '', idx: 0, perc: '0.00' })
                }).catch(err => console.error(err));

                btn.closest('.card-progress-overlay').remove();
                if (window.currentRawEpubFile && window.currentRawEpubFile.name === fileName) {
                    location.reload();
                }
                return;
            }
            loadBookFromUrl(book.url);
        };

        fragment.appendChild(card);

        if (!hasServerCover) {
            card.dataset.bookUrl = book.url;
            card.dataset.cardId = uniqueId;
            getCachedBookMeta(book.url).then(cachedMeta => {
                if (cachedMeta) {
                    if (cachedMeta.coverUrl && cachedMeta.coverUrl.startsWith('blob:')) {
                        cachedMeta.coverUrl = null;
                    }
                    applyMetaToCard(card, cachedMeta);
                } else if (coverObserver) {
                    coverObserver.observe(card);
                }
            });
        }
    });

    libraryGrid.appendChild(fragment);
}

function applyMetaToCard(card, meta) {
    if (!card) return;
    if (meta.author && meta.author.trim()) {
        const authorEl = card.querySelector('.book-card-author');
        if (authorEl) { authorEl.textContent = meta.author; authorEl.title = meta.author; }
        const coverAuthor = card.querySelector('.cover-book-author');
        if (coverAuthor) { coverAuthor.textContent = meta.author; }
    }
    if (meta.title && meta.title.trim()) {
        const titleEl = card.querySelector('.book-card-title');
        if (titleEl) { titleEl.textContent = meta.title; titleEl.title = meta.title; }
        const coverTitle = card.querySelector('.cover-book-title');
        if (coverTitle) { coverTitle.textContent = meta.title; }
    }
    if (meta.coverUrl && typeof meta.coverUrl === 'string' && (meta.coverUrl.startsWith('http') || meta.coverUrl.startsWith('data:') || meta.coverUrl.startsWith('blob:'))) {
        const coverContainer = card.querySelector('.book-cover-container') || card.querySelector('.book-card-cover');
        if (coverContainer) {
            let realCover = coverContainer.querySelector('.book-real-cover');
            if (!realCover) {
                realCover = document.createElement('img');
                realCover.className = 'book-real-cover';
                realCover.loading = 'lazy';
                realCover.alt = '';
                coverContainer.appendChild(realCover);
            }
            realCover.onerror = () => { realCover.remove(); };
            realCover.src = meta.coverUrl;
        }
    }
}

function updateCountBadge(count) {
    const totalCountEl = document.getElementById('total-books-count');
    if (totalCountEl) {
        totalCountEl.textContent = `${count} books`;
        return;
    }
    const modalTitle = document.querySelector('#library-modal h2');
    if (modalTitle) {
        modalTitle.innerHTML = `📚 Library <span style="display: inline-block; background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 99px; border: 1px solid rgba(56, 189, 248, 0.3); margin-left: 12px; vertical-align: middle; letter-spacing: 0.5px;">${count}</span>`;
    }
}

async function extractCoverForCard(bookUrl, cardId) {
    try {
        const card = document.getElementById(cardId);
        if (!card) return;

        const cached = await getCachedBookMeta(bookUrl);
        if (cached) {
            if (cached.coverUrl && cached.coverUrl.startsWith('blob:')) {
                cached.coverUrl = null;
            }
            applyMetaToCard(card, cached);
            return;
        }

        const tempBook = ePub(bookUrl);
        await tempBook.ready;
        const meta = await tempBook.loaded.metadata;
        const packageMeta = tempBook.package ? tempBook.package.metadata : {};
        let finalAuthor = meta.creator || packageMeta.creator || packageMeta["dc:creator"] || "";
        if (Array.isArray(finalAuthor)) finalAuthor = finalAuthor.join(", ");

        const title = meta.title || "";
        let coverUrl = null;
        try {
            const rawCoverUrl = await tempBook.coverUrl();
            if (rawCoverUrl) {
                const blobRes = await fetch(rawCoverUrl);
                const blobData = await blobRes.blob();
                if (blobData && blobData.size > 100) {
                    coverUrl = await new Promise((res) => {
                        const reader = new FileReader();
                        reader.onloadend = () => res(reader.result);
                        reader.readAsDataURL(blobData);
                    });
                }
            }
        } catch (e) {
            coverUrl = null;
        }

        const extractedData = {
            author: finalAuthor || "Unknown Author",
            title: title,
            coverUrl: coverUrl || null
        };

        applyMetaToCard(card, extractedData);
        saveCachedBookMeta(bookUrl, extractedData);
        tempBook.destroy();
    } catch (err) {
        console.warn("Client cover extraction skipped for:", bookUrl);
    }
}

async function loadBookFromUrl(url) {
    libraryModal.classList.add('hidden');
    document.getElementById('book-title-text').textContent = "Loading Book...";
    const fileName = url.split('/').pop();

    try {
        let blob = null;
        // ⚡ Cache Storage API: instant offline/subsequent load in 0ms
        if ('caches' in window) {
            try {
                const cache = await caches.open('neural-books-v1');
                const cachedRes = await cache.match(url);
                if (cachedRes) {
                    blob = await cachedRes.blob();
                    console.log("⚡ Loaded EPUB directly from Cache Storage:", fileName);
                } else {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error("Failed to download book");
                    cache.put(url, response.clone()).catch(() => {});
                    blob = await response.blob();
                }
            } catch(e) {
                console.warn("Cache API fallback to fetch", e);
            }
        }

        if (!blob) {
            const response = await fetch(url);
            if (!response.ok) throw new Error("Failed to download book");
            blob = await response.blob();
        }

        const file = new File([blob], fileName, { type: "application/epub+zip" });
        loadEpub(file);
    } catch (error) {
        console.error("Error loading book:", error);
        alert("Error loading book. Check console.");
        document.getElementById('book-title-text').textContent = "Error";
    }
}
async function sha256(message) { const msgBuffer = new TextEncoder().encode(message); const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer); const hashArray = Array.from(new Uint8Array(hashBuffer)); return hashArray.map(b => b.toString(16).padStart(2, '0')).join(''); }
libraryBtn.onclick = async () => {
    if (!libraryModal.classList.contains('hidden')) {
        libraryModal.classList.add('hidden');
        libraryBtn.classList.remove('active');
        return;
    }
    const CORRECT_HASH = "3ac7e6bf7ea7627138da7b458762c4a8246d3f97b074bc557ea4b531c2e0a686";
    const isUnlocked = sessionStorage.getItem('library_unlocked');
    if (isUnlocked === 'true') {
        renderLibrary();
        libraryModal.classList.remove('hidden');
        libraryBtn.classList.add('active');
    } else {
        const userPass = prompt("🔐 Enter Library Password:");
        if (userPass) {
            const userHash = await sha256(userPass);
            if (userHash === CORRECT_HASH.trim()) {
                sessionStorage.setItem('library_unlocked', 'true');
                renderLibrary();
                libraryModal.classList.remove('hidden');
                libraryBtn.classList.add('active');
            } else {
                alert("⛔ Access Denied! Wrong Password.");
            }
        }
    }
};
closeLibraryBtn.onclick = () => {
    libraryModal.classList.add('hidden');
    libraryBtn.classList.remove('active');
};
libraryModal.onclick = (e) => {
    if(e.target === libraryModal) {
        libraryModal.classList.add('hidden');
        libraryBtn.classList.remove('active');
    }
};

playBtn.onclick = togglePlay;
stopBtn.onclick = stopReading;
nextBtn.onclick = () => navigateSentence(1);
prevBtn.onclick = () => navigateSentence(-1);

settingsBtn.onclick = () => {
    if (settingsModal) {
        settingsModal.classList.toggle('hidden');
        settingsBtn.classList.toggle('active', !settingsModal.classList.contains('hidden'));
    }
};

if (closeSettingsBtn && settingsModal) {
    closeSettingsBtn.onclick = () => {
        settingsModal.classList.add('hidden');
        settingsBtn.classList.remove('active');
    };
}

if (settingsModal) {
    settingsModal.onclick = (e) => {
        if (e.target === settingsModal) {
            settingsModal.classList.add('hidden');
            settingsBtn.classList.remove('active');
        }
    };
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (libraryModal && !libraryModal.classList.contains('hidden')) {
            libraryModal.classList.add('hidden');
            libraryBtn.classList.remove('active');
        }
        if (settingsModal && !settingsModal.classList.contains('hidden')) {
            settingsModal.classList.add('hidden');
            settingsBtn.classList.remove('active');
        }
    }
});

const globalMetaBtn = document.getElementById('book-meta-container');
if(globalMetaBtn) { const newBtn = globalMetaBtn.cloneNode(true); globalMetaBtn.parentNode.replaceChild(newBtn, globalMetaBtn); newBtn.onclick = (e) => { console.log("🔘 Meta Container Clicked - Opening Modal Forcefully"); e.preventDefault(); e.stopPropagation(); handleMetaClick(); }; window.activeMetaBtn = newBtn; }
init();

// --- PWA Installation Handling ---
let pwaDeferredPrompt = null;
const pwaHeaderBtn = document.getElementById('pwa-install-btn');
const pwaSettingsBtn = document.getElementById('settings-pwa-install-btn');

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pwaDeferredPrompt = e;
    if (pwaHeaderBtn) pwaHeaderBtn.classList.remove('hidden');
    if (pwaSettingsBtn) pwaSettingsBtn.classList.remove('hidden');
});

const triggerPwaInstallFlow = async () => {
    if (!pwaDeferredPrompt) {
        alert('To install as an app:\nIn your browser menu (⋮), choose "Install app" or "Add to Home screen".');
        return;
    }
    pwaDeferredPrompt.prompt();
    const { outcome } = await pwaDeferredPrompt.userChoice;
    if (outcome === 'accepted') {
        if (pwaHeaderBtn) pwaHeaderBtn.classList.add('hidden');
        if (pwaSettingsBtn) pwaSettingsBtn.classList.add('hidden');
    }
    pwaDeferredPrompt = null;
};

if (pwaHeaderBtn) pwaHeaderBtn.onclick = triggerPwaInstallFlow;
if (pwaSettingsBtn) pwaSettingsBtn.onclick = triggerPwaInstallFlow;

window.addEventListener('appinstalled', () => {
    pwaDeferredPrompt = null;
    if (pwaHeaderBtn) pwaHeaderBtn.classList.add('hidden');
    if (pwaSettingsBtn) pwaSettingsBtn.classList.add('hidden');
    console.log('✅ Neural Reader PWA successfully installed!');
});


