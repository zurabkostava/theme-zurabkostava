let synthesis = window.speechSynthesis;
let piperVoicesList = [];
let detectedBookLanguages = new Set();
let piperWorkers = {};
let kokoroInstance = null;
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

const PIPER_FALLBACK_VOICES = [
    { isPiper: true, key: 'ka_GE-natia-medium', name: '☁️ Piper — Georgian (Natia, medium)', lang: 'ka_GE', path: 'ka/ka_GE/natia/medium/ka_GE-natia-medium' },
    { isPiper: true, key: 'en_US-lessac-medium', name: '☁️ Piper — English (Lessac, medium)', lang: 'en_US', path: 'en/en_US/lessac/medium/en_US-lessac-medium' }
];

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
                if (Array.isArray(parsed) && parsed.length > 0 && parsed.every(isValidPiperEntry) && parsed[0].lang.includes('_')) {
                    piperVoicesList = parsed;
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
                name: `☁️ Piper — ${v.language.name_english || v.language.code} (${v.name || k}, ${v.quality || 'medium'})`,
                lang: String(v.language.code),
                path: onnxFile.replace('.onnx', '')
            };
        }).filter(isValidPiperEntry);
        if (mapped.length === 0) throw new Error('voices.json parsed to 0 usable voices');

        piperVoicesList = mapped.sort((a, b) => a.name.localeCompare(b.name));

        const hasGeorgian = piperVoicesList.some(v => v.key === 'ka_GE-natia-medium');
        if (!hasGeorgian) {
            piperVoicesList.push(PIPER_FALLBACK_VOICES[0]);
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
const settingsPanel = document.getElementById('settings-panel');
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
if (refreshVoicesBtn) refreshVoicesBtn.onclick = async () => { await fetchPiperVoices(true); loadVoices(); };

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
contentArea.addEventListener('dragover', (e) => { e.preventDefault(); if(dropZone) dropZone.classList.add('dragover'); });
contentArea.addEventListener('dragleave', (e) => { e.preventDefault(); if(dropZone) dropZone.classList.remove('dragover'); });
contentArea.addEventListener('drop', (e) => {
    e.preventDefault();
    if(dropZone) dropZone.classList.remove('dragover');
    if (e.dataTransfer.items) {
        [...e.dataTransfer.items].forEach((item, i) => {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if(file.name.endsWith('.epub')) loadEpub(file);
            }
        });
    }
});

async function handleMetaClick() {
    const modal = document.getElementById('book-info-modal');
    if (!modal) return;
    const safeSetText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    const title = document.getElementById('book-title-text')?.textContent || "Unknown Title";
    const author = document.getElementById('book-author-text')?.textContent || "Unknown Author";
    const currentCoverSrc = document.getElementById('book-cover-img')?.src;
    safeSetText('modal-book-title', title);
    safeSetText('modal-book-author', author);
    const modalCover = document.getElementById('modal-book-cover');
    if (modalCover) {
        if (currentCoverSrc && !currentCoverSrc.includes(window.location.host + '/#') && currentCoverSrc !== window.location.href) {
            modalCover.src = currentCoverSrc;
            modalCover.style.display = 'block';
        } else {
            modalCover.style.display = 'none';
        }
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

        // 🏗️ 1. Locations დაგენერირება - შეცვლილია ჩვენი ზუსტი მთვლელით
        currentBook.ready.then(() => {
            return calculateGlobalChapterWeights();
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
    
    console.log("📍 Calculating true global chapter weights...");
    const weights = [];
    let totalLength = 0;
    
    // Process sequentially so we don't crash or block
    for (let i = 0; i < currentBook.spine.length; i++) {
        const item = currentBook.spine.get(i);
        if (!item) {
            weights.push(0);
            continue;
        }
        try {
            const doc = await currentBook.load(item.href);
            const text = extractTextFromDoc(doc);
            const len = text ? text.replace(/<[^>]+>/g, '').length : 0;
            weights.push(len);
            totalLength += len;
        } catch(e) {
            weights.push(100);
            totalLength += 100;
        }
    }
    
    window.chapterWeights = { weights, totalLength };
    try { localStorage.setItem(cacheKey, JSON.stringify(window.chapterWeights)); } catch(e) {}
    console.log("✅ Global weights calculated!");
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
            let totalCharsInChapter = 0;
            for (let i = 0; i < parsedContent.length; i++) {
                const len = parsedContent[i].textForUI ? parsedContent[i].textForUI.length : 1;
                if (i < activeSentenceIdx) charsUpToActive += len;
                totalCharsInChapter += len;
            }
            progressInChapter = totalCharsInChapter > 0 ? (charsUpToActive / totalCharsInChapter) : 0;
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
                let totalCharsInChapter = 0;
                for (let i = 0; i < parsedContent.length; i++) {
                    const len = parsedContent[i].textForUI ? parsedContent[i].textForUI.length : 1;
                    if (i < activeSentenceIdx) charsUpToActive += len;
                    totalCharsInChapter += len;
                }
                const progressInChapter = totalCharsInChapter > 0 ? (charsUpToActive / totalCharsInChapter) : 0;
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
        badge.textContent = displayPercentage.toFixed(2) + '%';
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
function displayChapter(href, delay = 0) {
    if (!currentBook) return;
    let spineItem = currentBook.spine.get(href);
    if (spineItem) { currentSpineIndex = spineItem.index; }

    // 🔥 ამოღებულია: localStorage.setItem('epub_progress_'...)
    // დათვალიერება არ ინახავს პროგრესს!

    // განვლილი თავების ვიზუალური ჩაქრობა (Sidebar)
    updateSidebarStyling();

    window.scrollTo(0, 0);
    document.querySelectorAll('.toc-item').forEach(el => {
        el.classList.remove('active');
        if (href.includes(el.dataset.href) || el.dataset.href.includes(href)) {
            el.classList.add('active');
        }
    });

    currentBook.load(href).then(doc => {
        const bodyText = extractTextFromDoc(doc);
        processText(bodyText);

        // 🔥 GHOST DIMMING: თუ ვათვალიერებთ უკვე წაკითხულ თავს
        // ვამოწმებთ რეალურ შენახულ ინდექსთან
        const savedRealIndex = getRealSavedIndex();

        if (savedRealIndex !== -1 && currentSpineIndex < savedRealIndex) {
            // თუ ეს თავი რეალურ პოზიციაზე ნაკლებია -> სრულად ჩავაქროთ
            document.querySelectorAll('.sentence').forEach(el => {
                el.classList.add('read');
                el.classList.remove('active');
                el.querySelectorAll('.word').forEach(w => w.classList.add('read'));
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
    });
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

function extractTextFromDoc(doc) {
    const paragraphs = doc.querySelectorAll('p, div, h1, h2, h3, h4, h5, h6, li, blockquote');
    let textArray = [];
    if (paragraphs.length > 0) {
        paragraphs.forEach(p => {
            let html = p.innerHTML;
            html = html.replace(/<br\s*\/?>/gi, ' ');

            let tagName = p.tagName.toLowerCase();
            let isHeader = /^h[1-6]$/.test(tagName);

            // Whole-paragraph internal header: ≥70% of the visible text is bold
            if (!isHeader) {
                let boldLen = 0;
                p.querySelectorAll('*').forEach(n => {
                    if (isBoldNode(n)) boldLen += n.textContent.trim().length;
                });
                const totalLen = p.textContent.trim().length;
                if (totalLen > 0 && boldLen >= totalLen * 0.7) {
                    isHeader = true;
                    tagName = 'strong';
                }
            }

            // Leading-bold internal header mixed with normal text: stamp the
            // marker class, protect its punctuation, and force a sentence
            // split right after the closing tag via ___SPLIT___.
            if (!isHeader && hasLeadingBoldHeader(p)) {
                html = html.replace(/^(\s*)<(b|strong)\b([^>]*)>([\s\S]*?)<\/\2\s*>/i,
                    (m, ws, tag, attrs, inner) =>
                        `${ws}<${tag}${addHeaderClassToAttrs(attrs)}>${protectHeaderPunct(inner)}</${tag}>___SPLIT___`);
            }

            let text = html.replace(/<\/?(?!(b|strong)\b)[^>]+>/gi, '').trim();
            if (text.length > 0) {
                if (isHeader) {
                    // Flatten inner tags — the whole line is one header
                    let safeText = protectHeaderPunct(text.replace(/<[^>]+>/g, ''));
                    text = `<b class="epub-header epub-header-${tagName}">${safeText}</b>`;
                }
                // Restore placeholders before the terminal-punctuation check so a
                // header ending in a protected "." doesn't get a second dot appended
                const cleanText = text.replace(/<[^>]+>/g, '').replace(/___SPLIT___/g, '')
                    .replace(/___DOT___/g, '.').replace(/___EXCL___/g, '!').replace(/___QUEST___/g, '?').trim();
                if (cleanText.length > 0) {
                    const lastChar = cleanText.slice(-1);
                    const punctuation = ['.', '!', '?', ':', ';', '…', '"', '»', '”'];
                    if (!punctuation.includes(lastChar)) { text += '.'; }
                    textArray.push(text);
                }
            }
        });
        return textArray.join('\n\n');
    } else {
        let html = doc.body.innerHTML;
        html = html.replace(/<br\s*\/?>/gi, ' ');
        html = html.replace(/<(h[1-6])[^>]*>([\s\S]*?)<\/\1>/gi, (m, tag, inner) =>
            `<b class="epub-header epub-header-${tag.toLowerCase()}">${protectHeaderPunct(inner.replace(/<[^>]+>/g, ''))}</b>`);
        return html.replace(/<\/?(?!(b|strong)\b)[^>]+>/gi, '').trim();
    }
}
function handleNextChapterLogic() {
    // აქ ვშლით ინდექსს, რადგან გადავდივართ "წასაკითხად"
    if (window.currentRawEpubFile) {
        localStorage.removeItem('epub_idx_' + window.currentRawEpubFile.name);
    }
    if (!currentBook) return;
    const nextIndex = currentSpineIndex + 1;
    if (nextIndex < currentBook.spine.length) {
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
        editBtn.innerHTML = iconSave;
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
        editBtn.innerHTML = iconEdit;
        editBtn.style.backgroundColor = '';
        editBtn.style.color = '';
        if(updatedText.length > 0) { processText(updatedText); }
        else { showDropZone(); }
    }
};
function showDropZone() {
    contentArea.innerHTML = `
<div id="drop-zone" class="drop-zone">
<div class="drop-content">
<svg viewBox="0 0 24 24" width="64" height="64" stroke="currentColor" fill="none" stroke-width="1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="12" y2="12"></line><line x1="15" y1="15" x2="12" y2="12"></line></svg>
<h3>Drag & Drop EPUB here</h3>
<p>or click the upload button above</p>
<p class="sub-text">You can also paste text manually via Edit mode</p>
</div>
</div>`;
}
// Helpers


function getPiperWorkerUrl() {
    // Same resolution strategy as WordEvo (WORDEVO_ASSET_PATH): PHP injects THEME_URI,
    // hardcoded path only as a last-resort fallback
    const base = window.THEME_URI || '/wp-content/themes/zurabkostava';
    return base + '/WordEvo/piper-worker.js';
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
        }
        else if (msg.kind === 'ready') {
            state.ready = true;
            state.initializing = false;
            setTtsStatus(null);

            const unifiedDlBtn = document.getElementById('unified-download-btn');
            const unifiedLangSelect = document.getElementById('unified-lang-select');
            if (unifiedDlBtn && unifiedLangSelect && unifiedLangSelect.value === langCode) {
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
        return a.localeCompare(b);
    });

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '16px';
    wrapper.style.padding = '12px';
    wrapper.style.background = 'rgba(255,255,255,0.02)';
    wrapper.style.borderRadius = '12px';
    wrapper.style.border = '1px solid rgba(255,255,255,0.05)';

    wrapper.innerHTML = `
        <div class="setting-group" style="margin-bottom: 12px;">
            <label style="color: #38bdf8;">🌐 Select Language to Configure</label>
            <div class="select-wrapper">
                <select id="unified-lang-select"></select>
            </div>
        </div>
        <div class="setting-group" style="margin-bottom: 12px;">
            <label>Selected Voice</label>
            <div class="select-wrapper">
                <select id="unified-voice-select"></select>
            </div>
            <button id="unified-download-btn" class="hidden" style="width: 100%; margin-top: 10px; font-size: 0.95rem; padding: 10px; border-radius: 8px; justify-content: center; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; cursor: pointer; transition: all 0.2s;">📥 Download / Init Voice</button>
        </div>
        <div class="setting-group">
            <label>Reading Speed <span id="unified-rate-val">1x</span></label>
            <input type="range" id="unified-rate-input" min="0.5" max="4" step="0.1" value="1">
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

    const savedUiLang = localStorage.getItem('unified-ui-lang');
    if (savedUiLang && Array.from(langSelect.options).some(o => o.value === savedUiLang)) {
        langSelect.value = savedUiLang;
    } else if (langSelect.options.length > 0) {
        langSelect.selectedIndex = 0;
    }

    function updateVoiceDropdown() {
        const currentLang = langSelect.value;
        if (!currentLang) return;
        localStorage.setItem('unified-ui-lang', currentLang);

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

        const piperForLang = piperList.filter(v => langMatches(v.lang, currentLang));
        if (piperForLang.length > 0) {
            const optGroup = document.createElement('optgroup');
            optGroup.label = "Piper Offline Voices";
            piperForLang.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.name; opt.textContent = v.name;
                optGroup.appendChild(opt);
            });
            voiceSelect.appendChild(optGroup);
        }
        
        if (currentLang.startsWith('en')) {
            const kokoroVoices = [
                { name: 'af_heart (Female, US)', val: 'af_heart' },
                { name: 'af_bella (Female, US)', val: 'af_bella' },
                { name: 'am_adam (Male, US)', val: 'am_adam' },
                { name: 'am_michael (Male, US)', val: 'am_michael' },
                { name: 'bf_emma (Female, UK)', val: 'bf_emma' },
                { name: 'bm_george (Male, UK)', val: 'bm_george' }
            ];
            const kokoroOptGroup = document.createElement('optgroup');
            kokoroOptGroup.label = "Kokoro Offline (Ultra High Quality)";
            kokoroVoices.forEach(v => {
                const opt = document.createElement('option');
                opt.value = 'kokoro:' + v.val;
                opt.textContent = `✨ ${v.name}`;
                kokoroOptGroup.appendChild(opt);
            });
            voiceSelect.appendChild(kokoroOptGroup);
        }

        const puterVoices = [
            { name: '☁️ Puter (OpenAI Nova - Female)', val: 'nova' },
            { name: '☁️ Puter (OpenAI Alloy - Neutral)', val: 'alloy' },
            { name: '☁️ Puter (OpenAI Echo - Male)', val: 'echo' },
            { name: '☁️ Puter (OpenAI Onyx - Male)', val: 'onyx' },
            { name: '☁️ Puter (OpenAI Fable - British)', val: 'fable' },
            { name: '☁️ Puter (OpenAI Shimmer - Female)', val: 'shimmer' }
        ];
        const puterOptGroup = document.createElement('optgroup');
        puterOptGroup.label = "Premium Cloud (Puter)";
        puterVoices.forEach(v => {
            const opt = document.createElement('option');
            opt.value = 'puter:' + v.val;
            opt.textContent = v.name;
            puterOptGroup.appendChild(opt);
        });
        voiceSelect.appendChild(puterOptGroup);

        const googleOptGroup = document.createElement('optgroup');
        googleOptGroup.label = "☁️ Free Cloud (Google)";
        const gOpt = document.createElement('option');
        gOpt.value = 'google:standard';
        gOpt.textContent = "☁️ Google Translate TTS (Standard)";
        googleOptGroup.appendChild(gOpt);

        voiceSelect.appendChild(googleOptGroup);

        if (voiceSelect.options.length === 0) {
            const opt = document.createElement('option');
            opt.value = ''; opt.textContent = `⚠️ No voices found`; opt.disabled = true; opt.selected = true;
            voiceSelect.appendChild(opt);
        } else {
            const savedVoice = localStorage.getItem(`voice-${currentLang}`);
            if (savedVoice && Array.from(voiceSelect.options).some(o => o.value === savedVoice)) {
                voiceSelect.value = savedVoice;
            } else {
                const piperOpt = Array.from(voiceSelect.options).find(o => Array.from(voiceSelect.options).some(x => x.parentElement.label === "Piper Offline Voices" && x === o));
                if (piperOpt) {
                    voiceSelect.value = piperOpt.value;
                } else {
                    voiceSelect.selectedIndex = 0;
                }
            }
        }

        const savedRate = localStorage.getItem(`rate-${currentLang}`) || '1';
        rateInput.value = savedRate;
        rateVal.textContent = savedRate + 'x';
        updateDlBtn();
    }

    function updateDlBtn() {
        const currentLang = langSelect.value;
        const chosen = piperList.find(v => v.name === voiceSelect.value);
        if (chosen) {
            dlBtn.classList.remove('hidden');
            const state = piperWorkers[currentLang];
            if (state && state.voicePath === chosen.path && state.ready) {
                dlBtn.textContent = "✅ Voice Ready";
                dlBtn.style.opacity = "0.5"; dlBtn.disabled = true;
                dlBtn.style.background = "transparent"; dlBtn.style.border = "1px solid rgba(255,255,255,0.1)"; dlBtn.style.color = "var(--text-muted)";
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
        const chosen = piperList.find(v => v.name === voiceSelect.value);
        if (currentLang && chosen) {
            initPiperWorker(currentLang, chosen.path);
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

    updateVoiceDropdown();

    // Add Global Pause Settings
    const pauseWrapper = document.createElement('div');
    pauseWrapper.style.marginTop = '16px';
    pauseWrapper.style.padding = '12px';
    pauseWrapper.style.background = 'rgba(255,255,255,0.02)';
    pauseWrapper.style.borderRadius = '12px';
    pauseWrapper.style.border = '1px solid rgba(255,255,255,0.05)';
    pauseWrapper.innerHTML = `
        <h4 style="margin: 0 0 12px 0; font-size: 0.9rem; color: var(--text-main); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px;">⏱️ Pause Durations</h4>
        <div class="setting-group" style="margin-bottom: 10px;">
            <label>Main Header (ms) <span id="val-pause-main">${pauseSettings.mainHeader}</span></label>
            <input type="range" id="input-pause-main" min="0" max="10000" step="50" value="${pauseSettings.mainHeader}">
        </div>
        <div class="setting-group" style="margin-bottom: 10px;">
            <label>Internal Header (ms) <span id="val-pause-internal">${pauseSettings.internalHeader}</span></label>
            <input type="range" id="input-pause-internal" min="0" max="10000" step="50" value="${pauseSettings.internalHeader}">
        </div>
        <div class="setting-group" style="margin-bottom: 10px;">
            <label>Post-Header (ms) <span id="val-pause-post">${pauseSettings.postHeader}</span></label>
            <input type="range" id="input-pause-post" min="0" max="10000" step="50" value="${pauseSettings.postHeader}">
        </div>
        <div class="setting-group" style="margin-bottom: 0;">
            <label>Paragraph (ms) <span id="val-pause-paragraph">${pauseSettings.paragraph}</span></label>
            <input type="range" id="input-pause-paragraph" min="0" max="10000" step="50" value="${pauseSettings.paragraph}">
        </div>
    `;
    container.appendChild(pauseWrapper);

    const bindPauseInput = (id, key, valId) => {
        const input = pauseWrapper.querySelector('#' + id);
        const valSpan = pauseWrapper.querySelector('#' + valId);
        if (input) {
            input.addEventListener('input', (e) => {
                const v = parseInt(e.target.value);
                if (valSpan) valSpan.textContent = v;
                pauseSettings[key] = v;
                try { localStorage.setItem('ttsPauseSettings', JSON.stringify(pauseSettings)); } catch(err) {}
            });
        }
    };
    bindPauseInput('input-pause-main', 'mainHeader', 'val-pause-main');
    bindPauseInput('input-pause-internal', 'internalHeader', 'val-pause-internal');
    bindPauseInput('input-pause-post', 'postHeader', 'val-pause-post');
    bindPauseInput('input-pause-paragraph', 'paragraph', 'val-pause-paragraph');
}

let voiceLoadAttempts = 0;
function loadVoices() {
    let list = [];
    try { list = synthesis ? synthesis.getVoices() : []; } catch (e) { console.warn('getVoices failed', e); }
    voices = Array.from(list || []).filter(v => v && typeof v.name === 'string');
    rebuildDynamicSettings();
    // Chrome loads native voices async; retry a few times, but never loop forever —
    // Piper voices already populate the dropdowns even with zero native voices.
    if (voices.length === 0 && voiceLoadAttempts < 10) {
        voiceLoadAttempts++;
        setTimeout(loadVoices, 500);
    }
}

function numToGeorgian(num) {
    if (parseInt(num) === 0) return "ნული";
    const units = ["", "ერთ", "ორ", "სამ", "ოთხ", "ხუთ", "ექვს", "შვიდ", "რვ", "ცხრ"];
    const baseTens = { 20: "ოც", 40: "ორმოც", 60: "სამოც", 80: "ოთხმოც" };
    function convertUnder20(n, isFinal) { n = parseInt(n); if (n === 0) return ""; if (n < 10) { let suffix = (n === 8 || n === 9) ? "ა" : "ი"; return units[n] + (isFinal ? suffix : ""); } const teens = ["ათ", "თერთმეტ", "თორმეტ", "ცამეტ", "თოთხმეტ", "თხუთმეტ", "თექვსმეტ", "ჩვიდმეტ", "თვრამეტ", "ცხრამეტ"]; return teens[n - 10] + (isFinal ? "ი" : ""); }
    function convertUnder100(n, isFinal) { if (n < 20) return convertUnder20(n, isFinal); let remainder = n % 20; let tenKey = n - remainder; let prefix = baseTens[tenKey]; if (remainder === 0) return prefix + (isFinal ? "ი" : ""); return prefix + "და" + convertUnder20(remainder, isFinal); }
    function convertUnder1000(n, isFinal) { if (n < 100) return convertUnder100(n, isFinal); let hundreds = Math.floor(n / 100); let remainder = n % 100; let prefix = (hundreds === 1 ? "" : units[hundreds]) + "ას"; if (remainder === 0) return prefix + (isFinal ? "ი" : ""); return prefix + " " + convertUnder100(remainder, isFinal); }
    let n = parseInt(num); let result = "";
    if (n >= 1000000000) { let bill = Math.floor(n / 1000000000); n %= 1000000000; result += (bill === 1 ? "" : convertUnder1000(bill, false)) + (n === 0 ? " მილიარდი " : " მილიარდ "); }
    if (n >= 1000000) { let mill = Math.floor(n / 1000000); n %= 1000000; result += (mill === 1 ? "" : convertUnder1000(mill, false)) + (n === 0 ? " მილიონი " : " მილიონ "); }
    if (n >= 1000) { let thou = Math.floor(n / 1000); n %= 1000; result += (thou === 1 ? "" : convertUnder1000(thou, false)) + (n === 0 ? " ათასი " : " ათას "); }
    if (n > 0) { result += convertUnder1000(n, true); }
    return result.trim();
}
function preprocessGeorgianText(text) {
    let t = text; const decimalMap = new Map(); let decimalCounter = 0;
    t = t.replace(/(\d+)\.(\d+)/g, (match, whole, frac) => { const wholeNum = parseInt(whole); const fracNum = parseInt(frac); const wholeStr = numToGeorgian(wholeNum); const fracStr = numToGeorgian(fracNum); const precision = frac.length; const precisionMap = { 1: "მეათედი", 2: "მეასედი", 3: "მეათასედი", 4: "მეათიათასედი", 5: "ასათასედი", 6: "მილიონედი" }; const unit = precisionMap[precision] || "ნაწილი"; const result = `${wholeStr} მთელი ${fracStr} ${unit}`; const placeholder = `___PROCESSED_DECIMAL_${decimalCounter}___`; decimalMap.set(placeholder, result); decimalCounter++; return placeholder; });
    t = t.replace(/(\d+)\/(\d+)/g, (match, num, den) => { const numerator = parseInt(num); const denominator = parseInt(den); const numStr = numToGeorgian(numerator); let denStr = ""; if (denominator === 2) { denStr = (numerator === 1) ? "ნახევარი" : "მეორედი"; } else if (denominator === 4) { denStr = "მეოთხედი"; } else { let tempDen = numToGeorgian(denominator); if (tempDen.endsWith("ი")) { denStr = "მე" + tempDen.slice(0, -1) + "ედი"; } else { denStr = "მე" + tempDen + "ედი"; } } return `${numStr} ${denStr}`; });
    t = t.replace(/(\d)[\s,]+(?=\d)/g, '$1');
    t = t.replace(/\d+/g, (match) => { const num = parseInt(match); if (num > 999999) { return numToGeorgian(num); } return match; });
    const romanMap = { 'XXI': 'ოცდამეერთე', 'XX': 'მეოცე', 'XIX': 'მეცხრამეტე', 'XVIII': 'მეთვრამეტე', 'XVII': 'მეჩვიდმეტე', 'XVI': 'მეთექვსმეტე', 'XV': 'მეთხუთმეტე', 'XIV': 'მეთოთხმეტე', 'XIII': 'მეცამეტე', 'XII': 'მეთორმეტე', 'XI': 'მეთერთმეტე', 'X': 'მეათე', 'I': 'პირველი' }; for (const [r, v] of Object.entries(romanMap)) { t = t.replace(new RegExp(`\\b${r}\\b`, 'g'), v); }
    const abbrList = [ { k: 'ძვ.წ.', v: 'ძველი წელთაღრიცხვით' }, { k: 'ახ.წ.', v: 'ახალი წელთაღრიცხვით' }, { k: 'ე.ი.', v: 'ესე იგი,' }, { k: 'ე.წ.', v: 'ეგრეთ წოდებული' }, { k: 'ა.შ.', v: 'ასე შემდეგ.' }, { k: 'ა.შ', v: 'ასე შემდეგ.' }, { k: 'აშშ', v: 'ამერიკის შეერთებული შტატები' }, { k: 'შ.პ.ს.', v: 'შეპესე' }, { k: 'კმ/სთ', v: 'კილომეტრი საათში' }, { k: 'კმ', v: 'კილომეტრი' }, { k: 'მ/წმ', v: 'მეტრი წამში' }, { k: 'წმ', v: 'წამში' }, { k: 'კვ.მ', v: 'კვადრატული მეტრი' }, { k: 'დნმ', v: 'დეენემი' }, { k: 'კგ', v: 'კილოგრამი' }, { k: 'გრ', v: 'გრამი' } ]; abbrList.forEach(item => { let pattern = item.k.replace(/\./g, '\\.\\s*'); t = t.replace(new RegExp(`(^|[\\s(])${pattern}(?=[\\s.,:;)]|$)`, 'gi'), `$1${item.v}`); });
    t = t.replace(/%/g, ' პროცენტი').replace(/№/g, ' ნომერი ').replace(/₾/g, ' ლარი '); decimalMap.forEach((value, key) => { t = t.replace(key, value); }); return t.replace(/\s+/g, ' ').trim();
}
function transliterateToGeorgian(text) {
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
    lastLoadedText = rawHtml.trim();
    contentArea.innerHTML = '';
    contentArea.scrollTop = 0;
    parsedContent = [];
    let sCounter = 0;
    
    const tags = [];
    let textWithPlaceholders = rawHtml.replace(/<\/?(b|strong)[^>]*>/gi, (match) => {
        const index = tags.length;
        tags.push(match);
        return `___HTML_${index}___`;
    });
    
    const paragraphs = textWithPlaceholders.split(/\n\s*\n+/).map(p => p.trim()).filter(p => p.length > 0);
    paragraphs.forEach((paraText, pIdx) => {
        const pDiv = document.createElement('div');
        pDiv.className = 'paragraph';
        const isParaPrimarilyGeorgian = /[ა-ჰ]/.test(paraText);
        let protectedText = paraText;
        const decimalPlaceholders = [];
        protectedText = protectedText.replace(/(\d+)\.(\d+)/g, (match) => { const placeholder = `___DECIMAL_${decimalPlaceholders.length}___`; decimalPlaceholders.push(match); return placeholder; });
        const abbrs = ['ე.წ.', 'ე.ი.', 'ა.შ.', 'მ.შ.', 'ე.უ.', 'შ.პ.ს.', 'ს.ს.'];
        abbrs.forEach(abbr => { let reg = new RegExp(abbr.replace(/\./g, '\\.\\s*'), 'gi'); protectedText = protectedText.replace(reg, m => m.replace(/\./g, '___DOT___')); });
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
                let closingTag = tagStr.toLowerCase().startsWith('<strong') ? '</strong>' : '</b>';
                let newIdx = tags.length;
                tags.push(closingTag);
                appendedTags += `___HTML_${newIdx}___`;
            }

            originalDisplay = prependedTags + originalDisplay + appendedTags;

            const words = originalDisplay.split(/(\s+|—|–)/g).filter(w => w.trim().length > 0 || w === '—' || w === '–');
            let detectedLang = 'en';
            if (/[ა-ჰ]/.test(originalDisplay)) detectedLang = 'ka';
            else if (/[А-Яа-я]/.test(originalDisplay)) detectedLang = 'ru';
            else if (/[A-Za-z]/.test(originalDisplay)) detectedLang = 'en';
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
            parsedContent.push({ index: sCounter, pIndex: pIdx, textForUI: originalDisplay, lang: detectedLang, element: sSpan });
            sCounter++;
        });
        contentArea.appendChild(pDiv);
    });

    const footerDiv = document.createElement('div');
    footerDiv.className = 'chapter-nav-footer';

    const prevBtnEl = document.createElement('button');
    prevBtnEl.className = 'nav-chapter-btn';
    prevBtnEl.innerHTML = `<span>←</span> Previous Chapter`;

    if (currentSpineIndex <= 0) prevBtnEl.classList.add('hidden');

    prevBtnEl.onclick = () => {
        if (currentBook && currentSpineIndex > 0) {
            const prevItem = currentBook.spine.get(currentSpineIndex - 1);
            // გადასვლა არ ინახავს!
            const delay = isPlaying ? -1 : 0;
            if (prevItem) displayChapter(prevItem.href, delay);
        }
    };

    const nextBtnEl = document.createElement('button');
    nextBtnEl.className = 'nav-chapter-btn';
    nextBtnEl.innerHTML = `Next Chapter <span>→</span>`;

    if (currentBook && currentSpineIndex >= currentBook.spine.length - 1) nextBtnEl.classList.add('hidden');

    nextBtnEl.onclick = () => {
        handleNextChapterLogic();
    };

    footerDiv.appendChild(prevBtnEl);
    footerDiv.appendChild(nextBtnEl);
    contentArea.appendChild(footerDiv);

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
    const visualWords = sent.element.querySelectorAll('.word');
    const wordRanges = [];
    let text = "";
    visualWords.forEach(wordEl => {
        let raw = wordEl.innerText.trim();
        let spoken = raw;
        if (raw === '—' || raw === '–') { spoken = ","; }
        else if (EMOJI_TEST_RE.test(raw)) { spoken = "."; }
        else if (lang === 'ka') {
            spoken = preprocessGeorgianText(raw);
            if (/[a-zA-Z]/.test(spoken)) { spoken = transliterateToGeorgian(spoken); }
        }
        const start = text.length;
        text += spoken + " ";
        wordRanges.push({ el: wordEl, start: start, end: text.length });
    });
    
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
    
    return { text: resultText, raw: resultText + " ", wordRanges: wordRanges, totalChars: resultText.length };
}

function piperSynthesize(state, text) {
    return new Promise((resolve, reject) => {
        if (!text) { resolve(null); return; }
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
    const state = piperWorkers[chunk.lang];
    const ready = await waitForPiperReady(state, token);
    if (!ready) return false;

    const spokenList = chunk.sentences.map(s => buildSpokenSentence(s, chunk.lang));
    let wavPromise = synthesizeSentence(chunk.lang, spokenList[0].text, token);

    for (let i = 0; i < chunk.sentences.length; i++) {
        if (token !== playbackToken || !isPlaying) return false;
        const wav = await wavPromise; // synthesizeSentence never rejects, returns null on failure

        // შესვენებების ლოგიკა სათაურებზე:
        // - 5s BEFORE main headers (h1-h6), 3s BEFORE internal bold headers
        // - 3s AFTER any header, before whatever follows
        // Header→Header transitions take the LARGER of the two pauses (never
        // stacked), and paragraph (pIndex) boundaries are deliberately ignored.
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

        // Prefetch the next sentence's audio while the current one plays — no gaps
        if (i + 1 < chunk.sentences.length) {
            wavPromise = synthesizeSentence(chunk.lang, spokenList[i + 1].text, token);
        }
        if (token !== playbackToken || !isPlaying) return false;

        currentIdx = chunk.sentences[i].index;
        highlightSentence(currentIdx, true);
        updateMediaPosition();

        if (wav) await playPiperAudio(piperWorkers[chunk.lang], wav, rate, spokenList[i], token);
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
                if (bUrl) await playGoogleAudio(bUrl, rate, null, token);
            }
        } else {
            const bUrl = await fetchBlobUrl(textToSpeak);
            if (bUrl) await playGoogleAudio(bUrl, rate, spokenList[i], token);
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

async function playKokoroChunk(chunk, rate, token, voiceId) {
    if (!kokoroInstance) {
        const playToggleBtn = document.getElementById('play-btn');
        const originalText = playToggleBtn.innerHTML;
        playToggleBtn.innerHTML = '<span class="icon">⏳</span><span class="text">Downloading (~86MB)...</span>';
        try {
            const { KokoroTTS } = await import('https://cdn.jsdelivr.net/npm/kokoro-js/+esm');
            kokoroInstance = await KokoroTTS.from_pretrained("onnx-community/Kokoro-82M-v1.0-ONNX", {
                dtype: "q8",
                device: "wasm"
            });
        } catch (e) {
            console.error("Kokoro load error", e);
            playToggleBtn.innerHTML = originalText;
            return false;
        }
        playToggleBtn.innerHTML = originalText;
    }

    if (token !== playbackToken || !isPlaying) return false;
    
    let currentBatch = [];
    let currentBatchText = "";

    const playBatch = async () => {
        if (currentBatch.length === 0) return true;
        
        currentIdx = currentBatch[0].idx;
        highlightSentence(currentIdx, true);
        updateMediaPosition();

        const ok = await new Promise(async (resolve) => {
            const rawText = currentBatch.map(s => s.element.innerText.trim()).join(' ');
            try {
                const audioData = await kokoroInstance.generate(rawText, { voice: voiceId });
                if (token !== playbackToken || !isPlaying) { resolve(false); return; }

                const blob = audioData.toBlob();
                const audioUrl = URL.createObjectURL(blob);
                
                const audio = new Audio(audioUrl);
                audio.playbackRate = Math.max(0.5, Math.min(rate, 4));
                
                let done = false;
                const finish = () => {
                    if (done) return;
                    done = true;
                    clearInterval(guard);
                    URL.revokeObjectURL(audioUrl);
                    resolve(token === playbackToken && isPlaying);
                };
                const guard = setInterval(() => {
                    if (token !== playbackToken || !isPlaying) { audio.pause(); finish(); }
                }, 100);
                audio.onended = finish;
                audio.onerror = finish;
                
                const consolidatedSpoken = {
                    totalChars: currentBatchText.length,
                    wordRanges: currentBatch.flatMap(s => s.wordRanges)
                };
                runWordHighlights(audio, consolidatedSpoken, token, currentBatch);
                audio.play().catch(finish);
            } catch (e) {
                console.error("Kokoro synth error", e);
                resolve(token === playbackToken && isPlaying);
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
        const offset = currentBatchText.length;
        const wordRanges = spoken.wordRanges.map(r => ({ el: r.el, start: r.start + offset, end: r.end + offset }));
        currentBatchText += spoken.raw;
        currentBatch.push({ idx: currentItem.index, element: currentItem.element, wordRanges: wordRanges, sentenceEndChar: currentBatchText.length });
    }
    
    return await playBatch();
}

function runWordHighlights(audio, spoken, token, sentenceRanges) {
    let lastEl = null;
    let lastSentIdx = currentIdx;
    const step = () => {
        if (token !== playbackToken || audio.ended) {
            if (lastEl) { lastEl.classList.remove('active'); lastEl.classList.add('read'); }
            return;
        }
        const dur = audio.duration;
        if (isFinite(dur) && dur > 0 && spoken.totalChars > 0) {
            const pos = (audio.currentTime / dur) * spoken.totalChars;
            let range = null;
            for (const r of spoken.wordRanges) { if (pos >= r.start && pos < r.end) { range = r; break; } }
            if (range && lastEl !== range.el) {
                if (lastEl) { lastEl.classList.remove('active'); lastEl.classList.add('read'); }
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

function playPuterAudio(audio, rate, spoken, token, sentenceRanges) {
    return new Promise((resolve) => {
        audio.playbackRate = Math.max(0.5, Math.min(rate, 4));
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            clearInterval(guard);
            resolve();
        };
        const guard = setInterval(() => {
            if (token !== playbackToken || !isPlaying) { audio.pause(); finish(); }
        }, 100);
        audio.onended = finish;
        audio.onerror = finish;
        runWordHighlights(audio, spoken, token, sentenceRanges);
        audio.play().catch(finish);
    });
}

async function playPuterChunk(chunk, rate, token, puterVoiceId) {
    if (typeof puter === 'undefined') return false;
    
    let currentBatch = [];
    let currentBatchText = "";

    const playBatch = async () => {
        if (currentBatch.length === 0) return true;
        
        currentIdx = currentBatch[0].idx;
        highlightSentence(currentIdx, true);
        updateMediaPosition();

        const ok = await new Promise((resolve) => {
            // Send entire raw text for natural OpenAI prosody, bypassing Piper's transliterations which break rhythm
            const rawText = currentBatch.map(s => s.element.innerText.trim()).join(' ');
            puter.ai.txt2speech(rawText, puterVoiceId ? { provider: 'openai', voice: puterVoiceId } : undefined)
                .then(audio => {
                    if (token !== playbackToken || !isPlaying) { resolve(false); return; }
                    audio.playbackRate = Math.max(0.5, Math.min(rate, 4));
                    let done = false;
                    const finish = () => {
                        if (done) return;
                        done = true;
                        clearInterval(guard);
                        resolve(token === playbackToken && isPlaying);
                    };
                    const guard = setInterval(() => {
                        if (token !== playbackToken || !isPlaying) { audio.pause(); finish(); }
                    }, 100);
                    audio.onended = finish;
                    audio.onerror = finish;
                    
                    const consolidatedSpoken = {
                        totalChars: currentBatchText.length,
                        wordRanges: currentBatch.flatMap(s => s.wordRanges)
                    };
                    runWordHighlights(audio, consolidatedSpoken, token, currentBatch);
                    audio.play().catch(finish);
                })
                .catch(e => {
                    console.error("Puter synth error", e);
                    resolve(token === playbackToken && isPlaying);
                });
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
        const offset = currentBatchText.length;
        const wordRanges = spoken.wordRanges.map(r => ({ el: r.el, start: r.start + offset, end: r.end + offset }));
        currentBatchText += spoken.raw;
        currentBatch.push({ idx: currentItem.index, element: currentItem.element, wordRanges: wordRanges, sentenceEndChar: currentBatchText.length });
    }
    
    return await playBatch();
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
            const utt = new SpeechSynthesisUtterance(currentBatchText);
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
            const settle = () => {
                if (lastActiveWord) {
                    lastActiveWord.classList.remove('active');
                    lastActiveWord.classList.add('read');
                }
                resolve(token === playbackToken && isPlaying);
            };
            utt.onend = settle;
            utt.onerror = settle;
            window.utterances.push(utt);
            synthesis.speak(utt);
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

        const piperVoice = piperVoicesList.find(v => v && v.name === selectedVoiceName);

        if (selectedVoiceName && selectedVoiceName.startsWith('puter:')) {
            const puterVoiceId = selectedVoiceName.split(':')[1];
            const ok = await playPuterChunk(chunk, rate, token, puterVoiceId);
            if (!ok) return;
        } else if (selectedVoiceName && selectedVoiceName.startsWith('kokoro:')) {
            const kokoroVoiceId = selectedVoiceName.split(':')[1];
            const ok = await playKokoroChunk(chunk, rate, token, kokoroVoiceId);
            if (!ok) return;
        } else if (selectedVoiceName && selectedVoiceName.startsWith('google:')) {
            const ok = await playGoogleChunk(chunk, rate, token);
            if (!ok) return;
        } else if (piperVoice) {
            const state = piperWorkers[chunk.lang];
            if (!state || state.voicePath !== piperVoice.path) {
                stopReading();
                alert(`გთხოვთ, პარამეტრებიდან ჯერ ჩამოტვირთოთ/გაააქტიუროთ ხმა:\n"${piperVoice.name}"`);
                return;
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

// 🔥 განახლებული Highlight Sentence - შენახვის ცენტრი
function highlightSentence(idx, saveToStorage = false) {
    if (!parsedContent || parsedContent.length === 0) return;

    // ვიზუალი
    parsedContent.forEach((item, i) => {
        const el = item.element;
        const words = el.querySelectorAll('.word');

        if (i < idx) {
            el.classList.add('read');
            el.classList.remove('active');
            words.forEach(w => { w.classList.add('read'); w.classList.remove('active'); });
        }
        else if (i === idx) {
            el.classList.remove('read');
            el.classList.add('active');
            words.forEach(w => { w.classList.remove('read', 'active'); });
            scrollToCenter(contentArea, el);
        }
        else {
            el.classList.remove('read', 'active');
            words.forEach(w => { w.classList.remove('read', 'active'); });
        }
    });

    // 📍 შენახვის ლოგიკა - მხოლოდ თუ saveToStorage არის True
    if (saveToStorage && window.currentRawEpubFile && currentBook) {
        // 1. ვინახავთ წინადადების ნომერს
        localStorage.setItem('epub_idx_' + window.currentRawEpubFile.name, idx);

        // 2. ვინახავთ თავის მისამართს (HREF) - ეს არის მთავარი!
        // ეს ხდება "Max Chapter"-ის მსგავსად, ახლა ესაა აქტიური თავი.
        const currentItem = currentBook.spine.get(currentSpineIndex);
        if (currentItem) {
            localStorage.setItem('epub_progress_' + window.currentRawEpubFile.name, currentItem.href);
        }

        // 3. ვაახლებთ პროცენტებს და საიდბარს (რადგან რეალური პოზიცია შეიცვალა)
        updateProgressPercentage();
        updateSidebarStyling();
        syncProgressToCloud(true);
    }

    updateProgressBar();
}

function scrollToCenter(container, element) { const elementTop = element.offsetTop; const elementHeight = element.offsetHeight; const containerHeight = container.clientHeight; let targetScroll = elementTop - (containerHeight / 2) + (elementHeight / 2); if (targetScroll < 0) { targetScroll = 0; } container.scrollTo({ top: targetScroll, behavior: 'smooth' }); }
function clearHighlights() { document.querySelectorAll('.sentence.active').forEach(el => el.classList.remove('active')); document.querySelectorAll('.word.active').forEach(el => el.classList.remove('active')); document.querySelectorAll('.word.read').forEach(el => el.classList.remove('read')); }
function setupModalClosing() {
    const modalOverlay = document.getElementById('book-info-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    if (closeBtn && modalOverlay) {
        closeBtn.onclick = (e) => { e.preventDefault(); e.stopPropagation(); modalOverlay.classList.add('hidden'); };
        modalOverlay.onclick = (e) => { if (e.target === modalOverlay) { modalOverlay.classList.add('hidden'); } };
    }
}
setupModalClosing();
function init() {
    // Build the panel immediately so selects are never blank while Piper voices download
    rebuildDynamicSettings();
    fetchPiperVoices().then(() => loadVoices()).catch(e => { console.error('init voices failed', e); loadVoices(); });
    if (typeof speechSynthesis !== 'undefined' && speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = loadVoices;
    }
    initMediaSession();
}
// ... (LIBRARY LOGIC იგივე რჩება) ...
// Library Logic-ის ქვემოთ კოდი იგივეა, არაფერი შეცვლილა.
// უბრალოდ სრული კოდის გამო აქაც იყოს:
// (LIBRARY LOGIC)
async function renderLibrary() {
    libraryGrid.innerHTML = '<div style="color:white; text-align:center; padding:20px;">Scanning bookshelf... 📚</div>';
    try {
        const response = await fetch('/wp-json/neural/v1/books');
        if (!response.ok) throw new Error("Scanner failed");
        allBooksCache = await response.json();
        updateCountBadge(allBooksCache.length);
        drawBooksToGrid(allBooksCache);
        const searchInput = document.getElementById('library-search-input');
        if (searchInput) {
            searchInput.value = "";
            searchInput.oninput = (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const filteredBooks = allBooksCache.filter(book => book.title.toLowerCase().includes(searchTerm) || book.author.toLowerCase().includes(searchTerm));
                drawBooksToGrid(filteredBooks);
            };
        }
    } catch (error) {
        console.error("Library Error:", error);
        libraryGrid.innerHTML = '<div style="color:red;">Error loading library.</div>';
    }
}
function drawBooksToGrid(booksList) {
    libraryGrid.innerHTML = '';
    if (booksList.length === 0) { libraryGrid.innerHTML = '<div style="color:gray; text-align:center; width:100%;">No books found matching criteria.</div>'; return; }
    booksList.forEach((book, index) => {
        const card = document.createElement('div');
        card.className = 'book-card';
        const uniqueId = `book-card-${Math.random().toString(36).substr(2, 9)}`;
        card.id = uniqueId;
        const randomHue = Math.floor(Math.random() * 360);
        const fileName = book.url.split('/').pop();
        
        if (book.perc !== undefined && book.perc !== null) {
            localStorage.setItem('epub_perc_' + fileName, book.perc);
        }
        
        const savedPerc = localStorage.getItem('epub_perc_' + fileName);
        
        let percHtml = '';
        if (savedPerc) {
            percHtml = `
            <div class="card-progress-overlay" style="display:flex; justify-content:space-between; align-items:center;">
                <span>${savedPerc}%</span>
                <button class="reset-book-btn" title="Reset Progress" style="background:transparent; border:none; color:#ef4444; cursor:pointer; padding:2px; height:18px; width:18px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                </button>
            </div>`;
        }

        const placeholderHtml = `${percHtml}<div class="book-card-cover placeholder" style="background: hsl(${randomHue}, 30%, 20%); border: 1px solid hsl(${randomHue}, 40%, 30%); display:flex; align-items:center; justify-content:center;"><span style="font-size: 2rem; opacity:0.5;">📖</span></div>`;
        card.innerHTML = `${placeholderHtml}<div class="book-card-title" title="${book.title}">${book.title}</div><div class="book-card-author" title="${book.author}">${book.author}</div>`;
        
        card.onclick = (e) => {
            const btn = e.target.closest('.reset-book-btn');
            if (btn) {
                e.stopPropagation();
                if (!confirm("ნამდვილად გსურთ ამ წიგნის პროგრესის განულება?")) return;
                
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
        
        libraryGrid.appendChild(card);
        extractCoverForCard(book.url, uniqueId);
    });
}
function updateCountBadge(count) {
    const modalTitle = document.querySelector('#library-modal h2');
    if (modalTitle) { modalTitle.innerHTML = `📚 Library <span style="display: inline-block; background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 99px; border: 1px solid rgba(56, 189, 248, 0.3); margin-left: 12px; vertical-align: middle; letter-spacing: 0.5px;">${count}</span>`; }
}
async function extractCoverForCard(bookUrl, cardId) {
    try {
        const tempBook = ePub(bookUrl);
        await tempBook.ready;
        const meta = await tempBook.loaded.metadata;
        const packageMeta = tempBook.package ? tempBook.package.metadata : {};
        let finalAuthor = "";
        if (meta.creator) finalAuthor = meta.creator;
        else if (packageMeta.creator) finalAuthor = packageMeta.creator;
        else if (packageMeta["dc:creator"]) finalAuthor = packageMeta["dc:creator"];
        if (Array.isArray(finalAuthor)) { finalAuthor = finalAuthor.join(", "); }
        console.log(`Book: ${meta.title} | Author Found: ${finalAuthor}`);
        const card = document.getElementById(cardId);
        if (!card) { tempBook.destroy(); return; }
        if (finalAuthor && finalAuthor.trim() !== "") { const authorEl = card.querySelector('.book-card-author'); if (authorEl) { authorEl.textContent = finalAuthor; authorEl.title = finalAuthor; } } else { const authorEl = card.querySelector('.book-card-author'); if(authorEl) authorEl.textContent = "უცნობი ავტორი"; }
        if (meta.title) { const titleEl = card.querySelector('.book-card-title'); if (titleEl) { titleEl.textContent = meta.title; titleEl.title = meta.title; } }
        const coverUrl = await tempBook.coverUrl();
        if (coverUrl) {
            const coverContainer = card.querySelector('.book-card-cover');
            coverContainer.innerHTML = `<img src="${coverUrl}" style="width:100%; height:100%; object-fit:cover; border-radius:4px;" alt="Cover">`;
            coverContainer.classList.remove('placeholder');
            coverContainer.style.background = 'transparent';
            coverContainer.style.border = 'none';
        }
        tempBook.destroy();
    } catch (err) { console.error("Metadata Error for:", bookUrl, err); }
}
async function loadBookFromUrl(url) {
    libraryModal.classList.add('hidden');
    document.getElementById('book-title-text').textContent = "Downloading Book...";
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error("Failed to download");
        const blob = await response.blob();
        const fileName = url.split('/').pop();
        const file = new File([blob], fileName, { type: "application/epub+zip" });
        loadEpub(file);
    } catch (error) { console.error("Error loading book:", error); alert("Error loading book. Check console."); document.getElementById('book-title-text').textContent = "Error"; }
}
async function sha256(message) { const msgBuffer = new TextEncoder().encode(message); const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer); const hashArray = Array.from(new Uint8Array(hashBuffer)); return hashArray.map(b => b.toString(16).padStart(2, '0')).join(''); }
libraryBtn.onclick = async () => {
    const CORRECT_HASH = "3ac7e6bf7ea7627138da7b458762c4a8246d3f97b074bc557ea4b531c2e0a686";
    const isUnlocked = sessionStorage.getItem('library_unlocked');
    if (isUnlocked === 'true') { renderLibrary(); libraryModal.classList.remove('hidden'); } else {
        const userPass = prompt("🔐 Enter Library Password:");
        if (userPass) {
            const userHash = await sha256(userPass);
            console.log("---------------- დიაგნოსტიკა ----------------"); console.log("შენახული ჰეში (კოდში):", CORRECT_HASH); console.log("შენი შეყვანილი პაროლი:", userPass); console.log("შენი შეყვანილი პაროლის ჰეში:", userHash); console.log("ემთხვევა თუ არა?", userHash === CORRECT_HASH.trim()); console.log("---------------------------------------------");
            if (userHash === CORRECT_HASH.trim()) { sessionStorage.setItem('library_unlocked', 'true'); renderLibrary(); libraryModal.classList.remove('hidden'); } else { alert("⛔ Access Denied! Wrong Password. (Check Console F12)"); }
        }
    }
};
closeLibraryBtn.onclick = () => libraryModal.classList.add('hidden');
libraryModal.onclick = (e) => { if(e.target === libraryModal) libraryModal.classList.add('hidden'); };

playBtn.onclick = togglePlay;
stopBtn.onclick = stopReading;
nextBtn.onclick = () => navigateSentence(1);
prevBtn.onclick = () => navigateSentence(-1);
settingsBtn.onclick = () => settingsPanel.classList.toggle('hidden');
const globalMetaBtn = document.getElementById('book-meta-container');
if(globalMetaBtn) { const newBtn = globalMetaBtn.cloneNode(true); globalMetaBtn.parentNode.replaceChild(newBtn, globalMetaBtn); newBtn.onclick = (e) => { console.log("🔘 Meta Container Clicked - Opening Modal Forcefully"); e.preventDefault(); e.stopPropagation(); handleMetaClick(); }; window.activeMetaBtn = newBtn; }
init();


