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
        <h2>Quiz</h2>
        <div id="quizQuestionContainer"></div>
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
        <div class="quiz-question">
            <h3>Question ${currentQuestionIndex + 1} / ${quizCards.length}</h3>
            <p><strong>${questionText}</strong></p>
            <div class="quiz-options">
                ${options.map(opt => `<button class="quiz-option" data-answer="${opt}">${opt}</button>`).join('')}
            </div>
        </div>
    `;

    const buttons = document.querySelectorAll('.quiz-option');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.disabled = true);
            const isCorrect = correctChoices.includes(btn.textContent);

            // === სტატისტიკის დაუყოვნებლივ მიწოდება ===
            incrementStat('TOTAL_TESTS', 1);
            incrementStat(isCorrect ? 'TOTAL_CORRECT' : 'TOTAL_WRONG', 1);

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
                    if (correctChoices.includes(b.textContent)) {
                        b.classList.add('correct');
                    }
                });
            }

            applyCurrentSort?.();

            setTimeout(() => {
                currentQuestionIndex++;
                renderNextQuestion();
            }, 1000);
        });
    });
}

function showQuizResult() {
    questionContainer.innerHTML = '';
    resultContainer.innerHTML = `
        <h3>Results</h3>
        <p>Correct answers: ${correctAnswers} / ${quizCards.length}</p>
        <p>არაCorrect answers: ${quizCards.length - correctAnswers}</p>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    if (quizTab) {
        createQuizUI();
        populateQuizTags();
    }
});




