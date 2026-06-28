//makeword.js
let mwCards = [];
let mwCurrentIndex = 0;
let mwCorrectAnswers = 0;
let mwTotalQuestions = 10;
let mwReverse = false;
let mwFullBlankMode = false;

let mwContainer, mwResultContainer;

document.addEventListener('DOMContentLoaded', () => {
    const tab = document.querySelector('[data-tab-content="tab4"]');
    if (!tab) return;

    tab.innerHTML = `
        <div style="text-align: center; margin-bottom: 15px; width: 100%;">
            <label style="display:inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="mwFullBlankToggle" style="width: 18px; height: 18px;" />
                <span style="font-weight: 500; font-size: 14px;">All letters blank</span>
            </label>
        </div>
        <div id="mwContainer" style="margin-top: 1rem;"></div>
        <div id="mwResultContainer" style="margin-top: 2rem;"></div>
    `;

    mwContainer = document.getElementById('mwContainer');
    mwResultContainer = document.getElementById('mwResultContainer');
    populateMWTags();
});

function populateMWTags() {
    const select = document.getElementById('mwTagSelect');
    if (!select) return;

    const allTags = new Set();
    document.querySelectorAll('.card').forEach(card => {
        card.querySelectorAll('.card-tag').forEach(tagEl => {
            const tag = tagEl.textContent.replace('#', '').trim();
            if (tag) allTags.add(tag);
        });
    });

    select.innerHTML = '<option value="">All</option>';
    [...allTags].sort().forEach(tag => {
        const option = document.createElement('option');
        option.value = tag;
        option.textContent = tag;
        select.appendChild(option);
    });
}



function startMakewordGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    mwReverse = reverse;
    mwTotalQuestions = count;
    mwFullBlankMode = document.getElementById('mwFullBlankToggle')?.checked;

    let allCards = getFilteredTrainingCards();

    if (allCards.length === 0) {
        alert("No cards found with the selected tag.");
        return;
    }

    const shuffled = allCards.sort(() => 0.5 - Math.random());
    mwCards = shuffled.slice(0, mwTotalQuestions);
    mwCurrentIndex = 0;
    mwCorrectAnswers = 0;

    mwResultContainer.innerHTML = '';
    showNextMWQuestion();
}

function showNextMWQuestion() {
    if (mwCurrentIndex >= mwCards.length) {
        showMakewordResults();
        return;
    }

    const card = mwCards[mwCurrentIndex];
    const word = card.querySelector('.word').textContent.trim();
    const mainText = card.querySelector('.translation').childNodes[0]?.textContent?.trim() || '';
    const mainTranslation = mainText.split(',')[0]?.trim();
    const correctWord = mwReverse ? mainTranslation : word;




    const allIndices = Array.from({ length: correctWord.length }, (_, i) => i);
    const missingIndices = mwFullBlankMode
        ? allIndices
        : generateRandomMissingIndices(correctWord);

    const blanks = correctWord.split('').map((ch, i) =>
        missingIndices.includes(i) ? '_' : ch
    );

    const missingLetters = missingIndices.map(i => correctWord[i]);
    const allChars = mwReverse
        ? "აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ".split('')
        : "abcdefghijklmnopqrstuvwxyz".split('');
    const extra = [];

    while (missingLetters.length + extra.length < 8) {
        const rand = allChars[Math.floor(Math.random() * allChars.length)];
        if (!missingLetters.includes(rand) && !extra.includes(rand)) {
            extra.push(rand);
        }
    }

    const buttons = shuffleArray([...missingLetters, ...extra]);
    const helperWord = mwReverse ? word : mainTranslation;

    mwContainer.innerHTML = `
        <div class="game-question-animated" style="text-align: center;">
            <h3>Question ${mwCurrentIndex + 1} / ${mwCards.length}</h3>
            
            <div class="mw-word" style="font-size: 2rem; margin: 30px 0;">
                ${blanks.map((ch, i) =>
            `<span class="mw-letter ${ch === '_' ? 'missing' : ''}" data-index="${i}">${ch === '_' ? '' : ch}</span>`
        ).join('')}
            </div>
            
            <div class="mw-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 20px;">
                ${buttons.map(ch =>
            `<button class="mw-char mix-btn" data-char="${ch}" style="width: 50px; height: 50px; font-size: 24px; text-transform: uppercase; padding: 0; margin-bottom: 0;">${ch}</button>`
        ).join('')}
            </div>
            
            <div id="mwHintSection" style="min-height: 45px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                <button id="showHintBtn" class="action-btn-secondary" style="display: ${mwFullBlankMode ? 'none' : 'block'}; margin: 0;">
                    Help
                </button>
                <div id="hintWord" style="display: ${mwFullBlankMode ? 'block' : 'none'};">
                    <span style="background-color: rgba(63, 131, 248, 0.1); color: #3f83f8; padding: 8px 15px; border-radius: 8px; font-weight: bold; font-size: 18px; border: 1px solid rgba(63, 131, 248, 0.3); display: inline-block;">
                        💡 ${helperWord}
                    </span>
                </div>
            </div>
            
            <div id="mwEnterHint" style="margin-top: 15px; font-size: 18px;"></div>
            
            ${getAutoAdvanceHTML("mw")}
        </div>
    `;
    
    // Auto-focus logic for active typing slot
    const updateActiveSlot = () => {
        document.querySelectorAll('.mw-letter.missing').forEach(el => el.classList.remove('active-typing'));
        const emptySpan = document.querySelector('.mw-letter.missing:not(.inserted-letter)');
        if (emptySpan) emptySpan.classList.add('active-typing');
    };
    updateActiveSlot();


    const used = new Map();

    document.querySelectorAll('.mw-char').forEach(btn => {
        btn.addEventListener('click', () => {
            const emptySpan = document.querySelector('.mw-letter.missing:not(.inserted-letter)');
            if (!emptySpan) return;

            const letter = btn.dataset.char;
            emptySpan.textContent = letter;
            emptySpan.classList.add('inserted-letter');
            used.set(emptySpan.dataset.index, btn);
            btn.disabled = true;
            btn.style.opacity = '0.5';
            updateActiveSlot();

            checkMWAnswer();
        });
    });

    document.querySelectorAll('.mw-letter.missing').forEach(span => {
        span.addEventListener('click', () => {
            const idx = span.dataset.index;
            if (!used.has(idx)) return;

            const btn = used.get(idx);
            btn.disabled = false;
            btn.style.opacity = '1';
            span.textContent = '_';
            span.classList.remove('inserted-letter');
            used.delete(idx);
            updateActiveSlot();
        });
    });

    const showHintBtn = document.getElementById('showHintBtn');
    const hintWordEl = document.getElementById('hintWord');
    let hintUsed = mwFullBlankMode;

    showHintBtn?.addEventListener('click', () => {
        hintWordEl.style.display = 'block';
        showHintBtn.remove();

        if (!hintUsed) {
            updateRealCardProgress(correctWord, -0.5);
            applyCurrentSort?.();
            hintUsed = true;
        }
    });

    function checkMWAnswer() {
        const result = [...document.querySelectorAll('.mw-letter')].map(el => el.textContent.trim()).join('');
        const isComplete = document.querySelectorAll('.mw-letter.missing:not(.inserted-letter)').length === 0;
        if (!isComplete) {
            // Reset color when user removes a letter to try again
            document.querySelectorAll('.mw-letter').forEach(el => el.style.color = '');
            return;
        }

        const isCorrect = result === correctWord;
        const delta = mwFullBlankMode ? 3 : 2;

        document.querySelectorAll('.mw-letter').forEach(el => {
            el.style.color = isCorrect ? 'green' : 'red';
        });

        if (isCorrect) {
            incrementStat('TOTAL_TESTS', 1);
            incrementStat('TOTAL_CORRECT', 1);
            mwCorrectAnswers++;
            
            updateRealCardProgress(correctWord, delta);
            applyCurrentSort?.();

            const hintEl = document.getElementById('mwEnterHint');
            if (hintEl) {
                hintEl.innerHTML = '✅ Correct! Moving to next...';
                hintEl.style.color = '#4caf50';
                hintEl.style.fontWeight = 'bold';
            }
            document.getElementById('mwActionArea').style.display = 'flex';

            window.mwNextReady = true;
            
            document.getElementById('mwNextBtn').onclick = () => {
                if (window.mwTimeout) clearTimeout(window.mwTimeout);
                if (window.mwNextReady) {
                    window.mwNextReady = false;
                    mwCurrentIndex++;
                    showNextMWQuestion();
                }
            };
            if (document.getElementById('mwAutoAdvance').checked) {
                window.mwTimeout = setTimeout(() => {
                    if (window.mwNextReady) {
                    window.mwNextReady = false;
                    mwCurrentIndex++;
                    showNextMWQuestion();
                }
                }, 1500);
            }
        } else {
            incrementStat('TOTAL_TESTS', 1);
            incrementStat('TOTAL_WRONG', 1);
            updateRealCardProgress(correctWord, -delta);
            applyCurrentSort?.();
            
            // Disable all buttons so user can't keep clicking while waiting
            document.querySelectorAll('.mw-char').forEach(btn => btn.disabled = true);

            const hintEl = document.getElementById('mwEnterHint');
            if (hintEl) {
                hintEl.innerHTML = '❌ Incorrect! Moving to next...';
                hintEl.style.color = '#ff5252';
                hintEl.style.fontWeight = 'bold';
            }
            document.getElementById('mwActionArea').style.display = 'flex';

            window.mwNextReady = true;
            
            document.getElementById('mwNextBtn').onclick = () => {
                if (window.mwTimeout) clearTimeout(window.mwTimeout);
                if (window.mwNextReady) {
                    window.mwNextReady = false;
                    mwCurrentIndex++;
                    showNextMWQuestion();
                }
            };
            if (document.getElementById('mwAutoAdvance').checked) {
                window.mwTimeout = setTimeout(() => {
                    if (window.mwNextReady) {
                    window.mwNextReady = false;
                    mwCurrentIndex++;
                    showNextMWQuestion();
                }
                }, 1500);
            }
        }
    }
}

function updateRealCardProgress(wordText, delta) {
    const card = [...document.querySelectorAll('.card')].find(c =>
        c.querySelector('.word')?.textContent.trim().toLowerCase() === wordText.toLowerCase()
    );
    if (card) updateCardProgress(card, delta);
}

function generateRandomMissingIndices(word) {
    const count = Math.min(3, Math.max(1, Math.floor(word.length / 3)));
    const indices = [];
    while (indices.length < count) {
        const i = Math.floor(Math.random() * word.length);
        if (!indices.includes(i) && /[ა-ჰa-zA-Z]/.test(word[i])) {
            indices.push(i);
        }
    }
    return indices;
}

function showMakewordResults() {
    const percentage = mwCards.length > 0 ? Math.round((mwCorrectAnswers / mwCards.length) * 100) : 0;
    mwContainer.innerHTML = `
        <div class="beautiful-results">
            <h3>Fill Completed! ✍️</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Correct answers: <strong>${mwCorrectAnswers} / ${mwCards.length}</strong></p>
            <button class="play-again-btn" onclick="startMakewordGame()">Play Again 🔄</button>
        </div>
    `;
    window.mwNextReady = false;
}

function shuffleArray(arr) {
    return [...arr].sort(() => 0.5 - Math.random());
}





document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'tab4') return;

    if (e.key === 'Enter') {
        if (window.mwNextReady) {
            clearTimeout(window.mwTimeout);
            window.mwNextReady = false;
            mwCurrentIndex++;
            showNextMWQuestion();
        }
    } else if (/^[a-zA-Zა-ჰ]$/.test(e.key)) {
        let char = e.key.toLowerCase();
        
        // Map English keys to Georgian letters in case user's layout is EN but they type Georgian words
        const geoMap = {
            'a': 'ა', 'b': 'ბ', 'c': 'ც', 'd': 'დ', 'e': 'ე', 'f': 'ფ', 'g': 'გ', 'h': 'ჰ', 'i': 'ი', 'j': 'ჯ', 'k': 'კ', 'l': 'ლ', 'm': 'მ', 'n': 'ნ', 'o': 'ო', 'p': 'პ', 'q': 'ქ', 'r': 'რ', 's': 'ს', 't': 'ტ', 'u': 'უ', 'v': 'ვ', 'w': 'წ', 'x': 'ხ', 'y': 'ყ', 'z': 'ზ'
        };
        const geoShiftMap = {
            'w': 'ჭ', 'r': 'ღ', 't': 'თ', 'y': 'ყ', 'u': 'უ', 'i': 'ი', 'o': 'ო', 'p': 'პ', 's': 'შ', 'd': 'დ', 'f': 'ფ', 'g': 'გ', 'h': 'ჰ', 'j': 'ჟ', 'k': 'კ', 'l': 'ლ', 'z': 'ძ', 'x': 'ხ', 'c': 'ჩ', 'v': 'ვ', 'b': 'ბ', 'n': 'ნ', 'm': 'მ'
        };
        
        // Check if the actual character exists in the buttons
        let btn = [...document.querySelectorAll('.mw-char')].find(b => b.dataset.char.toLowerCase() === char && !b.disabled);
        
        // If not found, try mapping
        if (!btn && /[a-z]/i.test(e.key)) {
            const mappedChar = e.shiftKey ? (geoShiftMap[e.key.toLowerCase()] || geoMap[e.key.toLowerCase()]) : geoMap[e.key.toLowerCase()];
            if (mappedChar) {
                btn = [...document.querySelectorAll('.mw-char')].find(b => b.dataset.char.toLowerCase() === mappedChar && !b.disabled);
            }
        }
        
        if (btn) btn.click();
    } else if (e.key === 'Backspace') {
        const inserted = [...document.querySelectorAll('.mw-letter.inserted-letter')];
        if (inserted.length > 0) {
            inserted[inserted.length - 1].click();
        }
    }
});
