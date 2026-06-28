//typegame.js
let tiCards = [], tiCurrent = 0, tiCorrect = 0;
let tiReverse = false;
let tiCount = 10;
let tiProgressPenalty = 0.3;
let tiCurrentCard = null;
let tiHintIndex = 0;

function initTypingGame() {
    const tab = document.querySelector('[data-tab-content="tab5"]');
    tab.innerHTML = `
<div id="tiGame"></div>
    `;

    const tagSelect = document.getElementById('tiTag');
    tagSelect.innerHTML = '<option value="">All</option>';
    [...allTags].forEach(tag => {
        const opt = document.createElement('option');
        opt.value = tag;
        opt.textContent = tag;
        tagSelect.appendChild(opt);
    });
}

function startTypingGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    tiReverse = reverse;
    tiCount = count;

    let cards = getFilteredTrainingCards();

    cards = shuffleArray(cards).slice(0, tiCount);
    tiCards = cards;
    tiCurrent = 0;
    tiCorrect = 0;
    showNextTyping();
}

function showNextTyping() {
    if (tiCurrent >= tiCards.length) return showTypingResult();

    const card = tiCards[tiCurrent];
    tiCurrentCard = card;
    tiHintIndex = 0;

    const word = card.querySelector('.word').textContent.trim();
    const main = card.querySelector('.translation').childNodes[0]?.textContent?.trim() || '';
    const extra = card.querySelector('.translation .extra')?.textContent?.trim() || '';
    const correctAnswers = tiReverse
        ? [word]
        : [...main.split(','), ...extra.split(',')].map(t => t.trim()).filter(Boolean);
    const shown = tiReverse ? (main.split(',')[0]?.trim() || '') : word;

    const game = document.getElementById('tiGame');
    game.innerHTML = `
        <div class="game-question-animated" style="max-width: 600px; margin: 0 auto;">
            <h3>Question ${tiCurrent + 1} / ${tiCards.length}</h3>
            <p style="font-size:26px; margin-bottom: 20px; text-align: center;"><strong>${shown}</strong></p>
            <div class="input-container" style="margin-bottom: 20px;">
                <label class="material-input type-word-test">
                    <input type="text" id="tiInput" placeholder=" " autocomplete="off">
                    <span style="font-size: 18px;">პასუხი</span>
                </label>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; align-items: stretch;">
                <button id="tiCheck" style="flex: 1; max-width: 200px;">Check <span class="key-hint" style="margin-left:5px; margin-right:0;">↵</span></button>
                <button id="tiHint" style="flex: 1; max-width: 200px; background: rgba(0,0,0,0.08) !important; color: var(--text-primary) !important; border: 2px solid var(--glass-border) !important; box-shadow: none !important;">💡 Hint</button>
            </div>
            <div id="tiFeedback" style="margin-top: 20px; font-size: 20px; font-weight: bold; text-align: center;"></div>
            <div id="tiEnterHint" class="enter-hint-btn">Press ↵ Enter to continue</div>
        </div>
    `;

    document.getElementById('tiCheck').onclick = () => {
        const val = document.getElementById('tiInput').value.trim();
        const feedback = document.getElementById('tiFeedback');
        const isCorrect = correctAnswers.some(ans => ans.toLowerCase() === val.toLowerCase());

        incrementStat('TOTAL_TESTS', 1);
        incrementStat(isCorrect ? 'TOTAL_CORRECT' : 'TOTAL_WRONG', 1);

        if (isCorrect) {
            feedback.innerHTML = `<span style="color:green;">Correct!</span>`;
            updateCardByText(word, 3);
            tiCorrect++;
        } else {
            feedback.innerHTML = `<span style="color:red;">არაCorrect. სწორი პასუხია: <strong>${correctAnswers[0]}</strong></span>`;
            updateCardByText(word, -3);
        }
        applyCurrentSort?.();

        document.getElementById('tiEnterHint').classList.add('visible');
        window.tiNextReady = true;
        window.tiTimeout = setTimeout(() => {
            if (window.tiNextReady) {
                window.tiNextReady = false;
                tiCurrent++;
                showNextTyping();
            }
        }, 1500);
    };

    document.getElementById('tiHint').onclick = () => {
        const input = document.getElementById('tiInput');
        const currentVal = input.value;
        const target = correctAnswers[0];

        let i = tiHintIndex;
        while (i < target.length && currentVal[i]?.toLowerCase() === target[i]?.toLowerCase()) {
            i++;
        }

        if (i < target.length) {
            input.value = target.substring(0, i + 1);
            tiHintIndex = i + 1;
            updateCardByText(word, -tiProgressPenalty);
            applyCurrentSort?.();
        }
    };

    const inputEl = document.getElementById('tiInput');
    inputEl.focus();
    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (window.tiNextReady) {
                clearTimeout(window.tiTimeout);
                window.tiNextReady = false;
                tiCurrent++;
                showNextTyping();
            } else {
                document.getElementById('tiCheck').click();
            }
        }
    });
}

function updateCardByText(wordText, delta) {
    const card = [...document.querySelectorAll('.card')].find(c =>
        c.querySelector('.word')?.textContent.trim().toLowerCase() === wordText.toLowerCase()
    );
    if (card) updateCardProgress(card, delta);
}

function showTypingResult() {
    const game = document.getElementById('tiGame');
    const percentage = tiCards.length > 0 ? Math.round((tiCorrect / tiCards.length) * 100) : 0;
    game.innerHTML = `
        <div class="beautiful-results">
            <h3>Typing Completed! ⌨️</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Correct answers: <strong>${tiCorrect} / ${tiCards.length}</strong></p>
            <button class="play-again-btn" onclick="startTypingGame()">Play Again 🔄</button>
        </div>
    `;
    window.tiNextReady = false;
}



function getStat(key) {
    return parseInt(localStorage.getItem(key) || '0');
}

function shuffleArray(arr) {
    return [...arr].sort(() => 0.5 - Math.random());
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('[data-tab="tab5"]');
    if (btn) btn.addEventListener('click', initTypingGame);
});




