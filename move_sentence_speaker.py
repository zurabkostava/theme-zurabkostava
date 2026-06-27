import re

# 1. Update script.js
file_path = 'WordEvo/script.js'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

old_pen = 'pEn.innerHTML = `<span class="prefix">${i + 1}. </span>${englishSentences[i]} <button class="speak-btn" title="წაიკითხე ორივე ენაზე" data-read-both="true" data-text-en="${safeEn}" data-text-ge="${safeGe}"><i class="fas fa-volume-up"></i></button>`;'
new_pen = 'pEn.innerHTML = `<span class="prefix">${i + 1}. </span><button class="speak-btn" title="წაიკითხე ორივე ენაზე" data-read-both="true" data-text-en="${safeEn}" data-text-ge="${safeGe}"><i class="fas fa-volume-up"></i></button> ${englishSentences[i]}`;'

content = content.replace(old_pen, new_pen)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)


# 2. Update style.css
css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css = f.read()

# Replace margins in .sentence-pair .speak-btn
css = css.replace('margin-left: 6px !important;\n    margin-right: 0 !important;', 'margin-left: 4px !important;\n    margin-right: 8px !important;')
css = css.replace('margin: 0 0 0 6px !important;', 'margin: 0 8px 0 4px !important;')

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css)

print("Successfully moved speaker button to the beginning.")
