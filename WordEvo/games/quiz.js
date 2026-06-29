//quiz.js

const quizTab = document.getElementById('quizTab');

let quizCards = [];
let currentQuestionIndex = 0;
let correctAnswers = 0;
let totalQuestions = 10;
let reverseMode = false;

// UI ელემენტები
let questionContainer, resultContainer;

function populateQuizTags() {
    const tagSelect = document.getElementById('quizTagSelect');
    if (!tagSelect) return;

    tagSelect.innerHTML = '<option value="">All</option>';

    const allTags = new Set();
    document.querySelectorAll('.card').forEach(card => {
        card.querySelectorAll('.tags .card-tag').forEach(tagSpan => {
            const tag = tagSpan.textContent.trim().replace('#', '');
            if (tag) allTags.add(tag);
        });
    });

    [...allTags].sort().forEach(tag => {
        const option = document.createElement('option');
        option.value = tag;
        option.textContent = tag;
        tagSelect.appendChild(option);
    });
}

function createQuizUI() {
    quizTab.innerHTML = `
<div id="quizQuestionContainer">
            ${window.getGamePlaceholderHTML('quiz')}
        </div>
        <div id="quizResultContainer" style="margin-top: 2rem;"></div>
    `;

    questionContainer = document.getElementById('quizQuestionContainer');
    resultContainer = document.getElementById('quizResultContainer');
}



function getStat(key) {
    return parseInt(localStorage.getItem(key) || '0');
}

function startQuiz() {
    const { count: requestedCount, reverse } = getGlobalTrainingSettings();
    reverseMode = reverse;

    let allCards = getFilteredTrainingCards();

    if (allCards.length === 0) {
        alert("არჩეული თეგით ბარათები ვერ მოიძებნა.");
        return;
    }

    totalQuestions = Math.min(requestedCount, allCards.length);
    const shuffled = allCards.sort(() => 0.5 - Math.random());
    quizCards = shuffled.slice(0, totalQuestions);
    currentQuestionIndex = 0;
    correctAnswers = 0;

    resultContainer.innerHTML = '';
    renderNextQuestion();
}

function renderNextQuestion() {
    if (currentQuestionIndex >= quizCards.length) {
        showQuizResult();
        return;
    }

    const currentCard = quizCards[currentQuestionIndex];
    const word = currentCard.querySelector('.word').textContent.trim().toLowerCase();
    const realCard = [...document.querySelectorAll('.card')].find(c =>
        c.querySelector('.word').textContent.trim().toLowerCase() === word
    );
    const correctWord = currentCard.querySelector('.word').textContent.trim();
    const mainText = currentCard.querySelector('.translation')?.childNodes[0]?.textContent?.trim() || '';
    const mainTranslations = mainText.split(',').map(t => t.trim()).filter(Boolean);
    const correctTranslation = mainTranslations[0];

    if (!correctWord || !correctTranslation) {
        currentQuestionIndex++;
        renderNextQuestion();
        return;
    }

    const questionText = reverseMode ? correctTranslation : correctWord;
    const correctChoices = reverseMode ? [correctWord] : mainTranslations;
    const allCards = [...document.querySelectorAll('.card')];

    const allOptions = allCards
        .flatMap(card => {
            const word = card.querySelector('.word').textContent.trim();
            const trText = card.querySelector('.translation')?.childNodes[0]?.textContent?.trim() || '';
            const trList = trText.split(',').map(t => t.trim());
            return reverseMode ? [word] : trList;
        })
        .filter(opt => opt && !correctChoices.includes(opt));

    const shuffledOptions = allOptions.sort(() => 0.5 - Math.random()).slice(0, 5);
    const randomCorrect = correctChoices[Math.floor(Math.random() * correctChoices.length)];
    const options = [...shuffledOptions, randomCorrect].sort(() => 0.5 - Math.random());

    questionContainer.innerHTML = `
        <div class="quiz-question game-question-animated">
            <h3>Question ${currentQuestionIndex + 1} / ${quizCards.length}</h3>
            <p><strong>${questionText}</strong></p>
            <div class="quiz-options">
                ${options.map((opt, i) => `<button class="quiz-option" data-answer="${opt}"><span class="key-hint">${i + 1}</span>${opt}</button>`).join('')}
            </div>
            ${getAutoAdvanceHTML("quiz")}
        </div>
    `;

    const buttons = document.querySelectorAll('.quiz-option');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.disabled = true);
            const isCorrect = correctChoices.includes(btn.dataset.answer);

            // === სტატისტიკის დაუყოვნებლივ მიწოდება ===
            incrementStat('TOTAL_TESTS', 1);
            incrementStat(isCorrect ? 'TOTAL_CORRECT' : 'TOTAL_WRONG', 1);

            document.getElementById('quizActionArea').style.display = 'flex';
            window.quizNextReady = true;

            if (isCorrect) {
                btn.classList.add('correct');
                correctAnswers++;
                const word = currentCard.querySelector('.word').textContent.trim().toLowerCase();
                const realCard = [...document.querySelectorAll('.card')].find(c =>
                    c.querySelector('.word').textContent.trim().toLowerCase() === word
                );
                if (realCard) updateCardProgress(realCard, +1);
            } else {
                btn.classList.add('incorrect');
                if (realCard) updateCardProgress(realCard, -1);
                buttons.forEach(b => {
                    if (correctChoices.includes(b.dataset.answer)) {
                        b.classList.add('correct');
                    }
                });
            }

            applyCurrentSort?.();

            document.getElementById('quizNextBtn').onclick = () => {
                if (window.quizTimeout) clearTimeout(window.quizTimeout);
                if (window.quizNextReady) {
                    window.quizNextReady = false;
                    currentQuestionIndex++;
                    renderNextQuestion();
                }
            };
            if (document.getElementById('quizAutoAdvance').checked) {
                window.quizTimeout = setTimeout(() => {
                    if (window.quizNextReady) {
                        window.quizNextReady = false;
                        currentQuestionIndex++;
                        renderNextQuestion();
                    }
                }, 1500);
            }
        });
    });
}

function showQuizResult() {
    questionContainer.innerHTML = '';
    const percentage = quizCards.length > 0 ? Math.round((correctAnswers / quizCards.length) * 100) : 0;
    resultContainer.innerHTML = `
        <div class="beautiful-results">
            <h3>Quiz Completed! 🎉</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Correct answers: <strong>${correctAnswers} / ${quizCards.length}</strong></p>
            <button class="play-again-btn" onclick="startQuiz()">Play Again 🔄</button>
        </div>
    `;
    window.quizNextReady = false;
}

document.addEventListener('DOMContentLoaded', () => {
    if (quizTab) {
        createQuizUI();
        populateQuizTags();
    }
});






// Global Keyboard shortcuts for Quiz
document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'quiz') return;

    if (e.key >= '1' && e.key <= '9') {
        const index = parseInt(e.key) - 1;
        const btns = document.querySelectorAll('.quiz-option');
        if (btns[index] && !btns[index].disabled) {
            btns[index].click();
        }
    } else if (e.key === 'Enter') {
        if (window.quizNextReady) {
            clearTimeout(window.quizTimeout);
            window.quizNextReady = false;
            currentQuestionIndex++;
            renderNextQuestion();
        }
    }
});
