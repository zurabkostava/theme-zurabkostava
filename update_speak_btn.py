import re

file_path = "WordEvo/style.css"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# I'll just append high specificity styles to override everything, it's safer and easier
# and it will perfectly override any previous rules.

css_to_add = '''
/* =========================================
   PREMIUM SPEAK BUTTON REDESIGN
   ========================================= */

.card-container .speak-btn,
.preview-modal .speak-btn,
.word-preview-section .speak-btn,
button.speak-btn {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid var(--glass-border, rgba(255,255,255,0.08)) !important;
    color: var(--text-secondary, #888) !important;
    padding: 8px 12px !important;
    border-radius: 12px !important;
    font-size: 1.1rem !important;
    cursor: pointer !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2) !important;
    margin-right: 12px !important;
    backdrop-filter: blur(4px) !important;
}

body:not(.dark) button.speak-btn {
    background: rgba(0, 0, 0, 0.04) !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
    color: #666 !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
}

button.speak-btn:hover {
    background: rgba(230, 126, 34, 0.15) !important;
    border-color: rgba(230, 126, 34, 0.4) !important;
    color: var(--accent, #e67e22) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 15px rgba(230, 126, 34, 0.25) !important;
}

body:not(.dark) button.speak-btn:hover {
    background: rgba(230, 126, 34, 0.1) !important;
    box-shadow: 0 4px 10px rgba(230, 126, 34, 0.15) !important;
}

button.speak-btn:active {
    transform: translateY(0) scale(0.95) !important;
    box-shadow: 0 2px 5px rgba(230, 126, 34, 0.15) !important;
}

/* Active playing state */
button.speak-btn.active {
    background: linear-gradient(135deg, var(--accent, #e67e22) 0%, #ff5e62 100%) !important;
    border-color: transparent !important;
    color: white !important;
    box-shadow: 0 0 15px rgba(230, 126, 34, 0.5) !important;
    animation: pulseSpeaker 1.5s infinite !important;
}

@keyframes pulseSpeaker {
    0% { box-shadow: 0 0 0 0 rgba(230, 126, 34, 0.6); }
    70% { box-shadow: 0 0 0 8px rgba(230, 126, 34, 0); }
    100% { box-shadow: 0 0 0 0 rgba(230, 126, 34, 0); }
}
'''

with open(file_path, "a", encoding="utf-8") as f:
    f.write(css_to_add)

print("Updated style.css with premium speak button design successfully!")
