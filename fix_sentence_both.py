import re

# Fix script.js
with open('WordEvo/script.js', 'r', encoding='utf-8') as f:
    content = f.read()

# For pEn
old_pen = r'pEn\.innerHTML = `<span class="prefix">\$\{i \+ 1\}\. <\/span>\$\{englishSentences\[i\]\} <button class="speak-btn" title="Read English" data-text="\$\{englishSentences\[i\]\}" data-lang="en"><i class="fas fa-volume-up"><\/i><\/button>`;'
new_pen = """const safeEn = (englishSentences[i] || '').replace(/"/g, '&quot;');
                    const safeGe = (georgianSentences[i] || '').replace(/"/g, '&quot;');
                    pEn.innerHTML = `<span class="prefix">${i + 1}. </span>${englishSentences[i]} <button class="speak-btn" title="წაიკითხე ორივე ენაზე" data-read-both="true" data-text-en="${safeEn}" data-text-ge="${safeGe}"><i class="fas fa-volume-up"></i></button>`;"""
content = re.sub(old_pen, new_pen, content)

# For pGe
old_pge = r'pGe\.innerHTML = `\$\{georgianSentences\[i\]\} <button class="speak-btn" title="წაიკითხე ქართულად" data-text="\$\{georgianSentences\[i\]\}" data-lang="ka"><i class="fas fa-volume-up"><\/i><\/button>`;'
new_pge = "pGe.innerHTML = `${georgianSentences[i]}`;"
content = re.sub(old_pge, new_pge, content)

with open('WordEvo/script.js', 'w', encoding='utf-8') as f:
    f.write(content)


# Fix tts.js
with open('WordEvo/tts.js', 'r', encoding='utf-8') as f:
    tts = f.read()

old_tts = """document.addEventListener('click', (e) => {
    const speakBtn = e.target.closest('.speak-btn');
    if (!speakBtn) return;

    e.stopPropagation();

    const text = speakBtn.dataset.text || speakBtn.dataset.word;"""

new_tts = """document.addEventListener('click', async (e) => {
    const speakBtn = e.target.closest('.speak-btn');
    if (!speakBtn) return;

    e.stopPropagation();

    if (speakBtn.dataset.readBoth === 'true') {
        const textEn = speakBtn.dataset.textEn;
        const textGe = speakBtn.dataset.textGe;
        if (textEn) await speakWithVoice(textEn, selectedVoice, speakBtn);
        if (textEn && textGe) await delay(800);
        if (textGe) await speakWithVoice(textGe, selectedGeorgianVoice, speakBtn);
        return;
    }

    const text = speakBtn.dataset.text || speakBtn.dataset.word;"""

tts = tts.replace(old_tts, new_tts)

with open('WordEvo/tts.js', 'w', encoding='utf-8') as f:
    f.write(tts)


# Fix style.css for jumping
with open('WordEvo/style.css', 'a', encoding='utf-8') as f:
    f.write("""
/* FIX JUMPING ISSUE ABSOLUTELY */
.sentence-pair .speak-btn {
    transform: translateZ(0) !important;
    vertical-align: baseline !important;
    position: relative;
    top: 2px;
}
.sentence-pair .speak-btn:hover {
    transform: translateZ(0) !important;
    vertical-align: baseline !important;
    margin: 0 0 0 6px !important;
    padding: 4px 8px !important;
    border-width: 1px !important;
    box-shadow: 0 0 8px rgba(230, 126, 34, 0.4) !important;
}
""")

print("Successfully applied dual-language button and jumping fix.")
