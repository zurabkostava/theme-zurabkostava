// mix.js ====


// ==== EXISTING CODE ====
let mixPairs = [];
let selectedLeft = null;
let selectedRight = null;
let matchedPairs = 0;
let totalPairs = 0;
let mixReverse = false;

let mixContainer, mixResultContainer, mixTagSelect, mixCountInput, mixReverseToggle;

document.addEventListener('DOMContentLoaded', () => {
    const tab = document.querySelector('[data-tab-content="tab3"]');
    if (!tab) return;

    tab.innerHTML = `
<div id="mixContainer" class="mix-columns" style="display: flex; gap: 40px; flex-wrap: wrap; justify-content: center;">
            ${window.getGamePlaceholderHTML('mix')}
        </div>
        <div id="mixResultContainer" style="margin-top: 2rem;"></div>
    `;

    mixContainer = document.getElementById('mixContainer');
    mixResultContainer = document.getElementById('mixResultContainer');
});

function startMixGame() {
    const { count, reverse } = getGlobalTrainingSettings();
    mixReverse = reverse;

    let allCards = getFilteredTrainingCards();

    if (allCards.length === 0) {
        alert("No cards found with the selected tag.");
        return;
    }

    const shuffled = allCards.sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, count);
    mixPairs = selected.map(card => {
        const word = card.querySelector('.word').textContent.trim();
        const translationText = card.querySelector('.translation').childNodes[0]?.textContent?.trim() || '';
        const translations = translationText.split(',').map(t => t.trim()).filter(Boolean);
        const randomTranslation = translations[Math.floor(Math.random() * translations.length)];
        return {
            card,
            en: word,
            ka: randomTranslation
        };
    });

    totalPairs = mixPairs.length;
    matchedPairs = 0;
    selectedLeft = null;
    selectedRight = null;

    renderMixUI();
}

function renderMixUI() {
    mixResultContainer.innerHTML = '';
    mixContainer.innerHTML = '';

    const leftWords = mixPairs.map(p => mixReverse ? p.ka : p.en);
    const rightWords = mixPairs.map(p => mixReverse ? p.en : p.ka);

    const shuffledLeft = [...leftWords].sort(() => 0.5 - Math.random());
    const shuffledRight = [...rightWords].sort(() => 0.5 - Math.random());

    const leftCol = document.createElement('div');
    const rightCol = document.createElement('div');
    leftCol.style.flex = '1';
    rightCol.style.flex = '1';

    leftCol.innerHTML = `<h4>${mixReverse ? 'ქართული' : 'English'}</h4>`;
    rightCol.innerHTML = `<h4>${mixReverse ? 'English' : 'ქართული'}</h4>`;

    shuffledLeft.forEach(text => {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = 'mix-btn';
        btn.dataset.side = 'left';
        leftCol.appendChild(btn);
    });

    shuffledRight.forEach(text => {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = 'mix-btn';
        btn.dataset.side = 'right';
        rightCol.appendChild(btn);
    });

    mixContainer.appendChild(leftCol);
    mixContainer.appendChild(rightCol);

    mixContainer.querySelectorAll('.mix-btn').forEach(btn => {
        btn.onclick = () => {
            const side = btn.dataset.side;
            const isSelected = btn.classList.contains('selected');

            if (isSelected) {
                btn.classList.remove('selected');
                if (side === 'left') selectedLeft = null;
                else selectedRight = null;
                return;
            }

            if (side === 'left') {
                if (selectedLeft) selectedLeft.button.classList.remove('selected');
                selectedLeft = { text: btn.textContent, button: btn };
            } else {
                if (selectedRight) selectedRight.button.classList.remove('selected');
                selectedRight = { text: btn.textContent, button: btn };
            }

            btn.classList.add('selected');

            if (selectedLeft && selectedRight) {
                const isMatch = mixPairs.some(p => {
                    const l = mixReverse ? p.ka : p.en;
                    const r = mixReverse ? p.en : p.ka;
                    return l === selectedLeft.text && r === selectedRight.text;
                });

                // === STATISTICS (1 per attempt) ===
                incrementStat('TOTAL_TESTS', 1);

                if (isMatch) {
                    selectedLeft.button.classList.remove('selected');
                    selectedRight.button.classList.remove('selected');
                    selectedLeft.button.classList.add('correct');
                    selectedRight.button.classList.add('correct');
                    selectedLeft.button.disabled = true;
                    selectedRight.button.disabled = true;
                    matchedPairs++;

                    updateCardProgressFromText(selectedLeft.text, selectedRight.text, +1);
                    applyCurrentSort?.();
                    incrementStat('TOTAL_CORRECT', 1);

                    const prevLeft = selectedLeft;
                    const prevRight = selectedRight;
                    selectedLeft = null;
                    selectedRight = null;

                    if (matchedPairs === totalPairs) {
                        setTimeout(showMixResults, 300);
                    }
                } else {
                    selectedLeft.button.classList.remove('selected');
                    selectedRight.button.classList.remove('selected');
                    selectedLeft.button.classList.add('incorrect');
                    selectedRight.button.classList.add('incorrect');
                    updateCardProgressFromText(selectedLeft.text, selectedRight.text, -1);
                    applyCurrentSort?.();
                    incrementStat('TOTAL_WRONG', 1);

                    const prevLeft = selectedLeft;
                    const prevRight = selectedRight;
                    selectedLeft = null;
                    selectedRight = null;

                    setTimeout(() => {
                        prevLeft.button.classList.remove('incorrect');
                        prevRight.button.classList.remove('incorrect');
                    }, 1000);
                }
            }
        };
    });
}

function updateCardProgressFromText(left, right, delta) {
    mixPairs.forEach(p => {
        const l = mixReverse ? p.ka : p.en;
        const r = mixReverse ? p.en : p.ka;
        if (l === left || r === right) {
            // მოძებნე რეალური ბარათი სიტყვით
            const word = p.en.toLowerCase();
            const realCard = [...document.querySelectorAll('.card')].find(c =>
                c.querySelector('.word').textContent.trim().toLowerCase() === word
            );
            if (realCard) updateCardProgress(realCard, delta);
        }
    });
}



function showMixResults() {
    if(window.setGlobalGameRunning) window.setGlobalGameRunning(false);
    mixResultContainer.innerHTML = `
        <h3>Results</h3>
        <p>წყვილები: ${matchedPairs} / ${totalPairs}</p>
    `;
}




