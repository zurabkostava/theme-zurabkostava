import os
import re

# 1. Update style.css
css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# Update primary button sizes
css_content = css_content.replace('font-size: 22px !important;', 'font-size: 18px !important;')
css_content = css_content.replace('padding: 18px 36px !important;', 'padding: 14px 28px !important;')
css_content = css_content.replace('border-radius: 16px !important;', 'border-radius: 14px !important;')

# Update secondary button sizes
css_content = css_content.replace('font-size: 15px !important;', 'font-size: 18px !important;')
css_content = css_content.replace('padding: 10px 15px !important;', 'padding: 14px 24px !important;')
css_content = css_content.replace('border-radius: 12px !important;', 'border-radius: 14px !important;')

# To be absolutely sure they match, let's explicitly make secondary buttons use flex align-items stretch or just height 100% when next to each other
# but since they will have identical padding and font-size, they will be the exact same height.

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css_content)


# 2. Update typegame.js
tg_path = 'WordEvo/games/typegame.js'
with open(tg_path, 'r', encoding='utf-8') as f:
    tg_content = f.read()

tg_content = tg_content.replace('💡 Hint', 'Hint')

with open(tg_path, 'w', encoding='utf-8') as f:
    f.write(tg_content)


# 3. Update puzzle.js
pz_path = 'WordEvo/games/puzzle.js'
with open(pz_path, 'r', encoding='utf-8') as f:
    pz_content = f.read()

pz_content = pz_content.replace('💡 Auto-fill', 'Auto-fill')
pz_content = pz_content.replace('❓ Translation', 'Translation')
pz_content = pz_content.replace('📘 Translation:', 'Translation:')

with open(pz_path, 'w', encoding='utf-8') as f:
    f.write(pz_content)


# 4. Update makeword.js
mw_path = 'WordEvo/games/makeword.js'
with open(mw_path, 'r', encoding='utf-8') as f:
    mw_content = f.read()

mw_content = mw_content.replace('💡 Hint', 'Hint')

with open(mw_path, 'w', encoding='utf-8') as f:
    f.write(mw_content)

print("Button sizes aligned and emojis removed!")
