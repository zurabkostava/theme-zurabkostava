//puzzle.js
let puzzleCards = [], puzzleCurrent = 0, puzzleCorrect = 0;
let puzzleReverse = false;
let puzzleCount = 10;

function initPuzzleGame() {
    const tab = document.querySelector('[data-tab-content="tab7"]');
    tab.innerHTML = `
<div id="puzzleGame" style="margin-top: 1rem;"></div>
    `;
}

function startPuzzleGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    puzzleReverse = reverse;
    puzzleCount = count;

    let cards = getFilteredTrainingCards();

    cards = shuffleArray(cards).filter(card => {
        const en = JSON.parse(card.dataset.english || '[]');
        const ge = JSON.parse(card.dataset.georgian || '[]');
        return (puzzleReverse ? ge : en).length > 0;
    }).slice(0, puzzleCount);

    puzzleCards = cards;
    puzzleCurrent = 0;
    puzzleCorrect = 0;

    showNextPuzzle();
}

function showNextPuzzle() {
    if (puzzleCurrent >= puzzleCards.length) return showPuzzleResults();

    const card = puzzleCards[puzzleCurrent];
    const word = card.querySelector('.word').textContent.trim();
    const sentences = JSON.parse(card.dataset[puzzleReverse ? 'georgian' : 'english'] || '[]');
    const oppositeSentences = JSON.parse(card.dataset[puzzleReverse ? 'english' : 'georgian'] || '[]');

    const originalSentence = sentences[Math.floor(Math.random() * sentences.length)];
    const words = originalSentence.split(/\s+/).filter(Boolean);
    const shuffled = shuffleArray(words);

    const container = document.getElementById('puzzleGame');
    container.innerHTML = `
        <div class="game-question-animated">
            <h3>Question ${puzzleCurrent + 1} / ${puzzleCards.length}</h3>
            <p style="font-size:18px; margin-bottom: 15px;">Click the words in the correct order:</p>
            
            <div id="puzzleAnswer" style="min-height: 70px; background: rgba(0,0,0,0.03); border: 2px dashed var(--glass-border); border-radius: 12px; padding: 15px; margin-bottom: 20px;"></div>
            
            <div id="puzzleWords" style="margin: 10px 0 25px 0;"></div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button id="puzzleSubmit" disabled style="background: var(--primary-color);">Check <span class="key-hint" style="margin-left:5px; margin-right:0;">↵</span></button>
                <button id="puzzleAutoHintBtn" class="action-btn-secondary">💡 Auto-fill</button>
                <button id="puzzleHintBtn" class="action-btn-secondary">❓ Translation</button>
            </div>
            
            <div id="puzzleHint" style="margin-top: 15px; font-weight: bold; font-size: 18px; color: var(--accent);"></div>
            <div id="puzzleFeedback" style="margin-top: 15px; font-size: 20px; font-weight: bold;"></div>
            <div id="puzzleEnterHint" class="enter-hint-btn">Press ↵ Enter to continue</div>
        </div>
    `;

    const puzzleWords = document.getElementById('puzzleWords');
    const puzzleAnswer = document.getElementById('puzzleAnswer');
    const puzzleSubmit = document.getElementById('puzzleSubmit');
    const hintBtn = document.getElementById('puzzleHintBtn');

    const originalClickMap = new Map();

    shuffled.forEach((word, index) => {
        const btn = document.createElement('button');
        btn.textContent = word;
        btn.className = 'puzzle-word';
        btn.dataset.index = index;

        const clickFn = () => {
            btn.remove();
            puzzleAnswer.appendChild(btn);

            btn.onclick = () => {
                btn.remove();
                const all = [...puzzleWords.children];
                const i = parseInt(btn.dataset.index);
                puzzleWords.insertBefore(btn, all[i] || null);
                btn.onclick = originalClickMap.get(btn);
                checkSubmitEnabled();
            };

            checkSubmitEnabled();
        };

        btn.onclick = clickFn;
        originalClickMap.set(btn, clickFn);
        puzzleWords.appendChild(btn);
    });

    document.getElementById('puzzleAutoHintBtn').onclick = () => {
        const currentWords = [...puzzleAnswer.querySelectorAll('button')].map(b => b.textContent);
        let mismatchIndex = currentWords.findIndex((w, i) => w !== words[i]);
        if (mismatchIndex === -1 && currentWords.length < words.length) {
            mismatchIndex = currentWords.length;
        }

        const allSelected = [...puzzleAnswer.querySelectorAll('button')];
        for (let i = allSelected.length - 1; i >= mismatchIndex; i--) {
            const btn = allSelected[i];
            btn.remove();
            const allPool = [...puzzleWords.children];
            const originalIndex = parseInt(btn.dataset.index);
            puzzleWords.insertBefore(btn, allPool[originalIndex] || null);
            btn.onclick = originalClickMap.get(btn);
        }

        const nextWord = words[mismatchIndex];
        const btnToUse = [...puzzleWords.querySelectorAll('button')].find(b => b.textContent === nextWord);
        if (!btnToUse) return;

        btnToUse.remove();
        puzzleAnswer.appendChild(btnToUse);
        btnToUse.onclick = () => {
            btnToUse.remove();
            const all = [...puzzleWords.children];
            const i = parseInt(btnToUse.dataset.index);
            puzzleWords.insertBefore(btnToUse, all[i] || null);
            btnToUse.onclick = originalClickMap.get(btnToUse);
            checkSubmitEnabled();
        };

        updateCardByText(word, -0.4);
        applyCurrentSort?.();
        checkSubmitEnabled();
    };

    puzzleSubmit.onclick = () => {
        const given = [...puzzleAnswer.querySelectorAll('button')].map(b => b.textContent);
        const isCorrect = given.join(' ') === words.join(' ');
        const feedback = document.getElementById('puzzleFeedback');

        incrementStat('TOTAL_TESTS', 1);
        if (isCorrect) {
            incrementStat('TOTAL_CORRECT', 1);
        } else {
            incrementStat('TOTAL_WRONG', 1);
        }

        if (isCorrect) {
            feedback.innerHTML = `<span style="color: green;">Correct!</span>`;
            updateCardByText(word, 3);
            puzzleCorrect++;
            puzzleAnswer.querySelectorAll('button').forEach(b => {
                b.style.backgroundColor = '#4caf50';
                b.style.color = 'white';
                b.disabled = true;
            });
        } else {
            feedback.innerHTML = `<span style="color: red;">Incorrect. The correct sentence is:<br><strong>${originalSentence}</strong></span>`;
            updateCardByText(word, -3);
            puzzleAnswer.querySelectorAll('button').forEach(b => b.disabled = true);
        }

        applyCurrentSort?.();
        puzzleWords.querySelectorAll('button').forEach(b => b.disabled = true);
        puzzleSubmit.disabled = true;

        document.getElementById('puzzleEnterHint').classList.add('visible');
        window.puzzleNextReady = true;
        window.puzzleTimeout = setTimeout(() => {
            if (window.puzzleNextReady) {
                window.puzzleNextReady = false;
                puzzleCurrent++;
                showNextPuzzle();
            }
        }, 2500);
    };

    hintBtn.onclick = () => {
        const alt = oppositeSentences[0] || "(No translation found)";
        document.getElementById('puzzleHint').textContent = `📘 Translation: ${alt}`;
        updateCardByText(word, -0.4);
        hintBtn.disabled = true;
        applyCurrentSort?.();
    };

    function checkSubmitEnabled() {
        puzzleSubmit.disabled = puzzleAnswer.querySelectorAll('button').length !== words.length;
    }
}

function updateCardByText(wordText, delta) {
    const card = [...document.querySelectorAll('.card')].find(c =>
        c.querySelector('.word')?.textContent.trim().toLowerCase() === wordText.toLowerCase()
    );
    if (card) updateCardProgress(card, delta);
}

function showPuzzleResults() {
    const container = document.getElementById('puzzleGame');
    const percentage = puzzleCards.length > 0 ? Math.round((puzzleCorrect / puzzleCards.length) * 100) : 0;
    container.innerHTML = `
        <div class="beautiful-results">
            <h3>Puzzle Completed! 🧩</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Correct answers: <strong>${puzzleCorrect} / ${puzzleCards.length}</strong></p>
            <button class="play-again-btn" onclick="startPuzzleGame()">Play Again 🔄</button>
        </div>
    `;
    window.puzzleNextReady = false;
}

function shuffleArray(arr) {
    return [...arr].sort(() => 0.5 - Math.random());
}



document.addEventListener('DOMContentLoaded', () => {
    const tabBtn = document.querySelector('[data-tab="tab7"]');
    if (tabBtn) {
        tabBtn.addEventListener('click', initPuzzleGame);
    }
});





document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'tab7') return;

    if (e.key === 'Enter') {
        if (window.puzzleNextReady) {
            clearTimeout(window.puzzleTimeout);
            window.puzzleNextReady = false;
            puzzleCurrent++;
            showNextPuzzle();
        } else {
            const submitBtn = document.getElementById('puzzleSubmit');
            if (submitBtn && !submitBtn.disabled) submitBtn.click();
        }
    }
});
