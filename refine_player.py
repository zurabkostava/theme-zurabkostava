import re

css_path = 'WordEvo/style.css'

with open(css_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Find the block we appended last time and remove it
start_marker = "/* =========================================\n   REDESIGNED BOTTOM DOCKED PLAYER"
if start_marker in content:
    content = content[:content.find(start_marker)]

# Append new refined design
new_css = """
/* =========================================
   REFINED BOTTOM DOCKED PLAYER
   ========================================= */

.fixed-player-wrapper {
    bottom: 0 !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: fit-content !important;
    display: flex !important;
    justify-content: center !important;
    pointer-events: none !important; 
    z-index: 999999999 !important;
}

.player {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 16px !important;
    padding: 10px 24px calc(10px + env(safe-area-inset-bottom, 0px)) !important;
    border-radius: 24px 24px 0 0 !important;
    width: max-content !important;
    margin: 0 !important;
    border: none !important;
    box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.15) !important;
    pointer-events: auto !important;
    transition: background 0.3s ease, box-shadow 0.3s ease !important;
}

/* Light Mode Defaults */
body:not(.dark) .player {
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
    border-left: 1px solid rgba(0, 0, 0, 0.04) !important;
    border-right: 1px solid rgba(0, 0, 0, 0.04) !important;
}

/* Dark Mode Overrides */
body.dark .player {
    background: rgba(25, 25, 35, 0.9) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.4) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-left: 1px solid rgba(255, 255, 255, 0.04) !important;
    border-right: 1px solid rgba(255, 255, 255, 0.04) !important;
}

/* Standard Player Buttons (Smaller & Cleaner) */
.player-btn {
    width: 40px !important;
    height: 40px !important;
    font-size: 16px !important;
    color: #64748b !important;
    border-radius: 50% !important;
    transition: all 0.2s ease !important;
    background: transparent !important;
}

body.dark .player-btn {
    color: #94a3b8 !important;
}

.player-btn:hover {
    color: #0f172a !important;
    background: rgba(0, 0, 0, 0.05) !important;
}

body.dark .player-btn:hover {
    color: #f8fafc !important;
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Highlight the Main Play Button (Subtle & Elegant) */
#playToggleBtn {
    background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%) !important;
    color: white !important;
    width: 46px !important;
    height: 46px !important;
    font-size: 18px !important;
    box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3) !important;
    margin: 0 4px !important;
    border: none !important;
    transition: all 0.2s ease !important;
}

/* Refined Hover - No Scale, just brightness/shadow */
#playToggleBtn:hover {
    box-shadow: 0 6px 16px rgba(236, 72, 153, 0.45) !important;
    filter: brightness(1.1) !important;
    transform: translateY(-1px) !important;
}

/* When playing, change color to indicate Stop/Pause action */
#playToggleBtn.active {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%) !important;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3) !important;
}
"""

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(content + new_css)

print("Player refined successfully.")
