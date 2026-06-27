import os

css_path = 'WordEvo/style.css'

new_css = """
/* =========================================
   REDESIGNED BOTTOM DOCKED PLAYER
   ========================================= */

.fixed-player-wrapper {
    bottom: 0 !important;
    left: 0 !important;
    width: 100% !important;
    transform: none !important;
    display: flex !important;
    justify-content: center !important;
    pointer-events: none !important; /* Let clicks pass through sides */
}

.player {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 12px 20px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
    border-radius: 24px 24px 0 0 !important;
    width: 100% !important;
    max-width: 480px !important;
    margin: 0 auto !important;
    border: none !important;
    box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.15) !important;
    pointer-events: auto !important; /* Catch clicks */
    transition: background 0.3s ease, box-shadow 0.3s ease !important;
}

/* Light Mode Defaults */
body:not(.dark) .player {
    background: rgba(255, 255, 255, 0.85) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border-top: 1px solid rgba(0, 0, 0, 0.06) !important;
}

/* Dark Mode Overrides */
body.dark .player {
    background: rgba(20, 20, 28, 0.85) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.4) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

/* Standard Player Buttons (Smaller & Cleaner) */
.player-btn {
    width: 42px !important;
    height: 42px !important;
    font-size: 16px !important;
    color: #64748b !important; /* Slate 500 */
    border-radius: 50% !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    background: transparent !important;
}

body.dark .player-btn {
    color: #94a3b8 !important; /* Slate 400 */
}

.player-btn:hover {
    color: #0f172a !important; /* Slate 900 */
    background: rgba(0, 0, 0, 0.05) !important;
    transform: scale(1.05) !important;
}

body.dark .player-btn:hover {
    color: #f8fafc !important; /* Slate 50 */
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Highlight the Main Play Button */
#playToggleBtn {
    background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%) !important;
    color: white !important;
    width: 52px !important;
    height: 52px !important;
    font-size: 20px !important;
    box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4) !important;
    margin: 0 8px !important;
    border: none !important;
}

#playToggleBtn:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 6px 20px rgba(236, 72, 153, 0.5) !important;
    color: white !important;
}

/* When playing, change color to indicate Stop/Pause action */
#playToggleBtn.playing {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%) !important;
    box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4) !important;
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(new_css)

print("Player redesigned successfully.")
