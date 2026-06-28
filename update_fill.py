import os

js_path = 'WordEvo/games/makeword.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_block = """    function checkMWAnswer() {
        const result = [...document.querySelectorAll('.mw-letter')].map(el => el.textContent).join('');
        const isComplete = !result.includes('_');
        if (!isComplete) return;

        const isCorrect = result === correctWord;
        const delta = mwFullBlankMode ? 3 : 2;

        incrementStat('TOTAL_TESTS', 1);
        if (isCorrect) {
            incrementStat('TOTAL_CORRECT', 1);
            mwCorrectAnswers++;
        } else {
            incrementStat('TOTAL_WRONG', 1);
        }

        updateRealCardProgress(correctWord, isCorrect ? delta : -delta);
        applyCurrentSort?.();

        document.querySelectorAll('.mw-letter').forEach(el => {
            el.style.color = isCorrect ? 'green' : 'red';
        });

        document.getElementById('mwEnterHint').classList.add('visible');
        window.mwNextReady = true;
        window.mwTimeout = setTimeout(() => {
            if (window.mwNextReady) {
                window.mwNextReady = false;
                mwCurrentIndex++;
                showNextMWQuestion();
            }
        }, 1500);
    }"""

new_block = """    function checkMWAnswer() {
        const result = [...document.querySelectorAll('.mw-letter')].map(el => el.textContent).join('');
        const isComplete = !result.includes('_');
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

            document.getElementById('mwEnterHint').classList.add('visible');
            window.mwNextReady = true;
            window.mwTimeout = setTimeout(() => {
                if (window.mwNextReady) {
                    window.mwNextReady = false;
                    mwCurrentIndex++;
                    showNextMWQuestion();
                }
            }, 1500);
        } else {
            // If incorrect, do not advance and do not subtract progress.
            // Let the user click the red letters to remove them and try again.
        }
    }"""

if old_block in js_content:
    js_content = js_content.replace(old_block, new_block)
    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(js_content)
    print("Updated makeword.js logic")
else:
    print("Could not find block in makeword.js")

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

css_addition = """
/* =========================================
   MW-CHAR CUBES (FILL BUTTONS)
   ========================================= */
.mw-char {
    border-radius: 8px !important;
    border: 2px solid var(--border-color) !important;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    background: var(--card-bg) !important;
    color: var(--text-primary) !important;
    transition: all 0.2s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.mw-char:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15) !important;
    background: var(--card-hover-bg) !important;
}
.mw-char:disabled {
    opacity: 0.3 !important;
    transform: none !important;
    box-shadow: none !important;
}
"""

if ".mw-char" not in css_content:
    with open(css_path, 'w', encoding='utf-8') as f:
        f.write(css_content.strip() + "\n" + css_addition)
    print("Updated style.css with .mw-char cubes")
else:
    print("CSS for .mw-char already exists")
