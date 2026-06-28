// wordhear.js

let wordhearCards = [];
let wordhearCurrentIndex = 0;
let wordhearCorrectAnswers = 0;
let wordhearReverse = false;
let wordhearCount = 10;

let whQuestionContainer, whResultContainer, whTagSelect, whCountInput, whReverseToggle;

// === სტატისტიკის დამხმარე ფუნქციები ===


function getStat(key) {
    return parseInt(localStorage.getItem(key) || '0');
}

document.addEventListener('DOMContentLoaded', () => {
    const tab = document.querySelector('[data-tab-content="tab2"]');
    if (!tab) return;

    tab.innerHTML = `
<div id="whQuestionContainer"></div>
        <div id="whResultContainer" style="margin-top: 2rem;"></div>
    `;

    whQuestionContainer = document.getElementById('whQuestionContainer');
    whResultContainer = document.getElementById('whResultContainer');
    whTagSelect = document.getElementById('whTagSelect');
    whCountInput = document.getElementById('whCountInput');
    whReverseToggle = document.getElementById('whReverseToggle');

    populateWordhearTags();
});

function populateWordhearTags() {
    if (!whTagSelect) return;

    const allTags = new Set();
    document.querySelectorAll('.card').forEach(card => {
        card.querySelectorAll('.card-tag').forEach(tagEl => {
            const tag = tagEl.textContent.replace('#', '').trim();
            if (tag) allTags.add(tag);
        });
    });

    whTagSelect.innerHTML = '<option value="">All</option>';
    [...allTags].sort().forEach(tag => {
        const option = document.createElement('option');
        option.value = tag;
        option.textContent = tag;
        whTagSelect.appendChild(option);
    });
}

function startWordhearGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    wordhearReverse = reverse;
    wordhearCount = count;

    let allCards = getFilteredTrainingCards();

    if (allCards.length === 0) {
        alert("No cards found with the selected tag.");
        return;
    }

    const shuffled = allCards.sort(() => 0.5 - Math.random());
    wordhearCards = shuffled.slice(0, wordhearCount);
    wordhearCurrentIndex = 0;
    wordhearCorrectAnswers = 0;

    whResultContainer.innerHTML = '';
    showNextWordhear();
}

async function showNextWordhear() {
    if (wordhearCurrentIndex >= wordhearCards.length) {
        showWordhearResults();
        return;
    }

    const currentCard = wordhearCards[wordhearCurrentIndex];
    const word = currentCard.querySelector('.word').textContent.trim();
    const mainText = currentCard.querySelector('.translation').childNodes[0]?.textContent?.trim() || '';
    const allTranslations = mainText.split(',').map(t => t.trim()).filter(Boolean);

    const mainTranslation = allTranslations[0];
    const randomTranslation = allTranslations[Math.floor(Math.random() * allTranslations.length)];

    const question = wordhearReverse ? randomTranslation : word;
    const answer = wordhearReverse ? word : randomTranslation;

    const questionVoice = wordhearReverse ? selectedGeorgianVoice : selectedVoice;
    const answerVoice = wordhearReverse ? selectedVoice : selectedGeorgianVoice;

    window._wordhear_repeat_question = () => speakWithVoice(question, questionVoice);

    const allOptions = [...document.querySelectorAll('.card')]
        .map(card => {
            const w = card.querySelector('.word').textContent.trim();
            const tText = card.querySelector('.translation').childNodes[0]?.textContent?.trim() || '';
            const translations = tText.split(',').map(txt => txt.trim()).filter(Boolean);
            const randTrans = translations[Math.floor(Math.random() * translations.length)];
            return wordhearReverse ? w : randTrans;
        })
        .filter(opt => opt && opt !== answer);

    const options = [...allOptions.sort(() => 0.5 - Math.random()).slice(0, 5), answer]
        .sort(() => 0.5 - Math.random());

    whQuestionContainer.innerHTML = `
        <div class="quiz-question">
            <h3>Question ${wordhearCurrentIndex + 1} / ${wordhearCards.length}</h3>
            <button id="repeatWordhearBtn" class="action-btn-secondary" style="margin-bottom: 15px;">🔁 Repeat Question</button>
            <div class="quiz-options">
                ${options.map(opt => `<button class="quiz-option">${opt}</button>`).join('')}
            </div>
            <div id="correctAnswerReveal" style="margin-top: 1rem; font-weight: bold;"></div>
            ${getAutoAdvanceHTML('wh')}
        </div>
    `;
    
    // Speak AFTER rendering HTML so the question appears immediately
    speakWithVoice(question, questionVoice);

    document.getElementById('repeatWordhearBtn').onclick = () => {
        if (typeof window._wordhear_repeat_question === 'function') {
            window._wordhear_repeat_question();
        }
    };

    document.querySelectorAll('.quiz-option').forEach(btn => {
        btn.addEventListener('click', () => {
            const isCorrect = btn.textContent === answer;
            btn.classList.add(isCorrect ? 'correct' : 'incorrect');
            document.querySelectorAll('.quiz-option').forEach(b => b.disabled = true);

            // ✅ ინკრემენტი თითოეულ პასუხზე
            incrementStat('TOTAL_TESTS', 1);
            const word = currentCard.querySelector('.word').textContent.trim().toLowerCase();
            const realCard = [...document.querySelectorAll('.card')].find(c =>
                c.querySelector('.word').textContent.trim().toLowerCase() === word
            );

            if (isCorrect) {
                wordhearCorrectAnswers++;
                incrementStat('TOTAL_CORRECT', 1);
                if (realCard) updateCardProgress(realCard, +1);
            } else {
                incrementStat('TOTAL_WRONG', 1);
                if (realCard) updateCardProgress(realCard, -1);
            }

            applyCurrentSort?.();

            const reveal = document.getElementById('correctAnswerReveal');
            reveal.innerHTML = `✔ სწორი პასუხი იყო: <span style="color: green;">${answer}</span>`;
            speakWithVoice(answer, answerVoice);

            document.getElementById('whActionArea').style.display = 'flex';
            window.whNextReady = true;
            document.getElementById('whNextBtn').onclick = () => {
                if (window.whTimeout) clearTimeout(window.whTimeout);
                if (window.whNextReady) {
                    window.whNextReady = false;
                    wordhearCurrentIndex++;
                    showNextWordhear();
                }
            };

            if (document.getElementById('whAutoAdvance').checked) {
                window.whTimeout = setTimeout(() => {
                    if (window.whNextReady) {
                        window.whNextReady = false;
                        wordhearCurrentIndex++;
                        showNextWordhear();
                    }
                }, 2000);
            }
        });
    });
}

function showWordhearResults() {
    whQuestionContainer.innerHTML = '';
    whResultContainer.innerHTML = `
        <h3>Results</h3>
        <p>Correct answers: ${wordhearCorrectAnswers} / ${wordhearCards.length}</p>
        <p>არაCorrect answers: ${wordhearCards.length - wordhearCorrectAnswers}</p>
    `;
}





// Global Keyboard shortcuts for Wordhear
document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'wordhear') return;

    if (e.key >= '1' && e.key <= '9') {
        const index = parseInt(e.key) - 1;
        const container = document.getElementById('whQuestionContainer');
        if (container) {
            const btns = container.querySelectorAll('.quiz-option');
            if (btns[index] && !btns[index].disabled) {
                btns[index].click();
            }
        }
    } else if (e.key === 'Enter') {
        if (window.whNextReady) {
            const btn = document.getElementById('whNextBtn');
            if (btn) btn.click(); // Using click triggers the existing handler properly
        }
    }
});
