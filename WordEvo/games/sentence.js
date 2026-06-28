//sentence.js
let senCards = [], senCurrent = 0, senCorrect = 0;
let senReverse = false;
let senCount = 10;

function initSentenceGame() {
    const tab = document.querySelector('[data-tab-content="tab6"]');
    tab.innerHTML = `
<div id="senGame"></div>
    `;
}

function startSentenceGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    senReverse = reverse;
    senCount = count;

    let cards = getFilteredTrainingCards();

    cards = shuffleArray(cards).filter(card => {
        const en = JSON.parse(card.dataset.english || '[]');
        const ge = JSON.parse(card.dataset.georgian || '[]');
        return en.length > 0 || ge.length > 0;
    }).slice(0, senCount);

    senCards = cards;
    senCurrent = 0;
    senCorrect = 0;

    showNextSentence();
}

function showNextSentence() {
    if (senCurrent >= senCards.length) return showSentenceResult();

    const card = senCards[senCurrent];
    const word = card.querySelector('.word').textContent.trim();
    const correctWord = word;
    const enSentences = JSON.parse(card.dataset.english || '[]');
    const geSentences = JSON.parse(card.dataset.georgian || '[]');
    const sentences = senReverse ? geSentences : enSentences;

    const matchedWords = [];
    const base = word.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`\\b${base}(es|s|ed|d|ing|er|est)?\\b`, 'gi');

    const displayedSentences = sentences.map(s =>
        s.replace(regex, (match) => {
            matchedWords.push(match);
            return '_____';
        })
    );

    const allCards = [...document.querySelectorAll('.card')];
    const options = shuffleArray(
        [correctWord, ...allCards
            .filter(c => c !== card)
            .map(c => c.querySelector('.word').textContent.trim())
            .filter(w => w.toLowerCase() !== correctWord.toLowerCase())
            .slice(0, 5)
        ]
    ).slice(0, 6);

    const game = document.getElementById('senGame');
    game.innerHTML = `
        <div class="game-question-animated">
            <h3>Question ${senCurrent + 1} / ${senCards.length}</h3>
            <div id="senSentences" style="margin: 20px 0; font-size: 20px; line-height: 1.6; color: var(--accent);">
                ${displayedSentences.map((s, i) => `<p style="margin-bottom: 10px;"><strong>${i + 1}.</strong> ${s}</p>`).join('')}
            </div>
            <div id="senOptions" class="quiz-options" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                ${options.map((opt, i) => `<button class="sen-option quiz-option" data-ans="${opt}" style="margin: 0; width: 100%;"><span class="key-hint">${i + 1}</span>${opt}</button>`).join('')}
            </div>
            <div id="senFeedback" style="margin-top: 20px; font-size: 20px; font-weight: bold;"></div>
            ${getAutoAdvanceHTML("sen")}
        </div>
    `;

    const buttons = document.querySelectorAll('.sen-option');
    buttons.forEach(btn => {
        btn.onclick = () => {
            buttons.forEach(b => b.disabled = true);
            const val = btn.dataset.ans.trim();
            const feedback = document.getElementById('senFeedback');
            const isCorrect = val.toLowerCase() === correctWord.toLowerCase();

            incrementStat('TOTAL_TESTS', 1);
            incrementStat(isCorrect ? 'TOTAL_CORRECT' : 'TOTAL_WRONG', 1);

            if (isCorrect) {
                feedback.innerHTML = `<span style="color:green;">Correct!</span>`;
                updateCardByText(correctWord, 4);
                senCorrect++;
            } else {
                feedback.innerHTML = `<span style="color:red;">არაCorrect. სწორი იყო: <strong>${correctWord}</strong></span>`;
                updateCardByText(correctWord, -4);
            }

            let i = 0;
            document.querySelectorAll('#senSentences p').forEach(p => {
                p.innerHTML = p.innerHTML.replace(/_____+/g, () => {
                    const original = matchedWords[i++] || correctWord;
                    return `<strong style="color: orange;">${original}</strong>`;
                });
            });

            applyCurrentSort?.();
            buttons.forEach(b => {
                const bText = b.dataset.ans.trim().toLowerCase();
                const correctText = correctWord.toLowerCase();

                if (bText === correctText) b.classList.add('correct');
                if (b === btn && bText !== correctText) b.classList.add('incorrect');
            });

            document.getElementById('senActionArea').style.display = 'flex';
            window.senNextReady = true;
            
            document.getElementById('senNextBtn').onclick = () => {
                if (window.senTimeout) clearTimeout(window.senTimeout);
                if (window.senNextReady) {
                    window.senNextReady = false;
                    senCurrent++;
                    showNextSentence();
                }
            };
            if (document.getElementById('senAutoAdvance').checked) {
                window.senTimeout = setTimeout(() => {
                    if (window.senNextReady) {
                    window.senNextReady = false;
                    senCurrent++;
                    showNextSentence();
                }
                }, 1500);
            }
        };
    });
}

function updateCardByText(wordText, delta) {
    const card = [...document.querySelectorAll('.card')].find(c =>
        c.querySelector('.word')?.textContent.trim().toLowerCase() === wordText.toLowerCase()
    );
    if (card) updateCardProgress(card, delta);
}

function showSentenceResult() {
    const game = document.getElementById('senGame');
    const percentage = senCards.length > 0 ? Math.round((senCorrect / senCards.length) * 100) : 0;
    game.innerHTML = `
        <div class="beautiful-results">
            <h3>Sentences Completed! 📝</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Correct answers: <strong>${senCorrect} / ${senCards.length}</strong></p>
            <button class="play-again-btn" onclick="startSentenceGame()">Play Again 🔄</button>
        </div>
    `;
    window.senNextReady = false;
}

function shuffleArray(arr) {
    return [...arr].sort(() => 0.5 - Math.random());
}



document.addEventListener('DOMContentLoaded', () => {
    const tabBtn = document.querySelector('[data-tab="tab6"]');
    if (tabBtn) {
        tabBtn.addEventListener('click', initSentenceGame);
    }
});





document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'tab6') return;

    if (e.key >= '1' && e.key <= '9') {
        const index = parseInt(e.key) - 1;
        const btns = document.querySelectorAll('.sen-option');
        if (btns[index] && !btns[index].disabled) {
            btns[index].click();
        }
    } else if (e.key === 'Enter') {
        if (window.senNextReady) {
            clearTimeout(window.senTimeout);
            window.senNextReady = false;
            senCurrent++;
            showNextSentence();
        }
    }
});

