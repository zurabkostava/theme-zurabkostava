css_to_add = '''
/* =========================================
   GAMES ECOSYSTEM OVERHAUL STYLES
   ========================================= */

@keyframes slideFadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulseCorrect {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.game-question-animated {
    animation: slideFadeIn 0.35s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

/* Unified Beautiful Results Screen */
.beautiful-results {
    text-align: center;
    padding: 40px 20px;
    animation: slideFadeIn 0.5s ease forwards;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 24px;
    border: 1px solid var(--glass-border);
}

.beautiful-results h3 {
    font-size: 36px !important;
    margin-bottom: 10px !important;
    background: linear-gradient(135deg, var(--accent) 0%, #ff5e62 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.beautiful-results .score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--card-bg-premium);
    border: 4px solid var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 25px auto;
    font-size: 36px;
    font-weight: 900;
    color: var(--text-primary);
    box-shadow: 0 10px 25px rgba(230, 126, 34, 0.2);
}

.beautiful-results p {
    font-size: 18px !important;
    color: var(--text-secondary) !important;
    margin-bottom: 25px !important;
}

.beautiful-results .play-again-btn {
    background: linear-gradient(135deg, var(--accent) 0%, #ff5e62 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 16px !important;
    padding: 16px 36px !important;
    font-size: 20px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 8px 20px rgba(230, 126, 34, 0.3) !important;
}

.beautiful-results .play-again-btn:hover {
    transform: translateY(-3px) !important;
    box-shadow: 0 12px 25px rgba(230, 126, 34, 0.4) !important;
}

/* Keyboard hint styles */
.key-hint {
    display: inline-block;
    background: rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 12px;
    font-family: monospace;
    color: #666;
    margin-right: 10px;
    vertical-align: middle;
}
.dark .key-hint {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
    color: #ccc;
}
.quiz-option .key-hint {
    float: left;
    margin-top: 2px;
}

/* Enter button visual hint */
.enter-hint-btn {
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 14px;
    color: var(--text-secondary);
    margin-top: 15px;
    display: inline-block;
}
.enter-hint-btn.visible {
    opacity: 1;
}

/* MakeWord Game improvements */
.mw-letter {
    display: inline-block;
    width: 45px;
    height: 55px;
    line-height: 55px;
    text-align: center;
    font-size: 28px !important;
    font-weight: bold;
    border-bottom: 3px solid var(--accent);
    margin: 0 4px;
    color: var(--text-primary);
    transition: all 0.2s;
    text-transform: uppercase;
}
.mw-letter.missing {
    border-bottom: 3px solid rgba(128,128,128,0.5);
    background: rgba(0,0,0,0.02);
    border-radius: 6px 6px 0 0;
    cursor: pointer;
}
.dark .mw-letter.missing {
    background: rgba(255,255,255,0.03);
}
.mw-letter.missing.active-typing {
    border-bottom-color: var(--primary-color);
    background: rgba(21, 117, 201, 0.1);
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { background-color: rgba(21, 117, 201, 0.05); }
    50% { background-color: rgba(21, 117, 201, 0.15); }
    100% { background-color: rgba(21, 117, 201, 0.05); }
}

'''

with open('WordEvo/style.css', 'a', encoding='utf-8') as f:
    f.write(css_to_add)

print("CSS appended successfully.")
