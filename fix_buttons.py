import os
import re

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# Add new CSS class for secondary buttons
new_css = """
/* =========================================
   SECONDARY ACTION BUTTONS (Hints, Auto-fill, Repeat)
   ========================================= */
.action-btn-secondary {
    background: rgba(0,0,0,0.05) !important;
    border: 2px solid rgba(0,0,0,0.05) !important;
    color: var(--text-primary) !important;
    border-radius: 12px !important;
    padding: 10px 15px !important;
    font-size: 15px !important;
    font-weight: 500 !important;
    box-shadow: none !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.action-btn-secondary:hover {
    background: rgba(0,0,0,0.1) !important;
    transform: translateY(-1px) !important;
}
.dark .action-btn-secondary {
    background: rgba(255,255,255,0.05) !important;
    border-color: var(--glass-border) !important;
    color: var(--text-primary) !important;
}
.dark .action-btn-secondary:hover {
    background: rgba(255,255,255,0.08) !important;
    border-color: rgba(255,255,255,0.2) !important;
}
"""
if ".action-btn-secondary" not in css_content:
    css_content += new_css

# Exclude .action-btn-secondary from global buttons
css_content = css_content.replace(':not(.mw-char)', ':not(.mw-char):not(.action-btn-secondary)')
# In case it was already replaced:
css_content = css_content.replace(':not(.action-btn-secondary):not(.action-btn-secondary)', ':not(.action-btn-secondary)')

# Change the dark mode primary button color from orange to blue
dark_override_old = """    background: var(--accent) !important;
    color: #111 !important;
    box-shadow: 0 4px 10px rgba(230, 126, 34, 0.2) !important;"""

dark_override_new = """    background: #1575c9 !important;
    color: white !important;
    box-shadow: 0 4px 10px rgba(21, 117, 201, 0.2) !important;"""

if dark_override_old in css_content:
    css_content = css_content.replace(dark_override_old, dark_override_new)

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css_content)

# Update typegame.js
tg_path = 'WordEvo/games/typegame.js'
with open(tg_path, 'r', encoding='utf-8') as f:
    tg_content = f.read()
tg_content = tg_content.replace(
    'style="flex: 1; max-width: 200px; background: rgba(0,0,0,0.08) !important; color: var(--text-primary) !important; border: 2px solid var(--glass-border) !important; box-shadow: none !important;"',
    'class="action-btn-secondary" style="flex: 1; max-width: 200px;"'
)
with open(tg_path, 'w', encoding='utf-8') as f:
    f.write(tg_content)

# Update puzzle.js
pz_path = 'WordEvo/games/puzzle.js'
with open(pz_path, 'r', encoding='utf-8') as f:
    pz_content = f.read()
pz_content = pz_content.replace(
    'style="width: auto; padding: 10px 15px; margin-bottom: 0; font-size: 14px; background: rgba(0,0,0,0.05); border: none; color: var(--text-primary);"',
    'class="action-btn-secondary"'
)
with open(pz_path, 'w', encoding='utf-8') as f:
    f.write(pz_content)

# Update makeword.js
mw_path = 'WordEvo/games/makeword.js'
with open(mw_path, 'r', encoding='utf-8') as f:
    mw_content = f.read()
mw_content = mw_content.replace(
    'class="mix-btn" style="width: auto; padding: 10px 15px; font-size: 14px; margin-bottom: 0;"',
    'class="action-btn-secondary"'
)
with open(mw_path, 'w', encoding='utf-8') as f:
    f.write(mw_content)

# Update wordhear.js
wh_path = 'WordEvo/games/wordhear.js'
with open(wh_path, 'r', encoding='utf-8') as f:
    wh_content = f.read()
wh_content = wh_content.replace(
    'id="repeatWordhearBtn" style="margin-bottom: 10px;"',
    'id="repeatWordhearBtn" class="action-btn-secondary" style="margin-bottom: 15px;"'
)
with open(wh_path, 'w', encoding='utf-8') as f:
    f.write(wh_content)

print("All buttons updated!")
