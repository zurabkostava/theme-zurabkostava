css_path = 'WordEvo/style.css'
with open(css_path, 'a', encoding='utf-8') as f:
    f.write("""
/* =========================================
   CARD ENTRANCE ANIMATION
   ========================================= */
@keyframes cardEntrance {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    opacity: 0;
    animation: cardEntrance 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}
""")

js_path = 'WordEvo/script.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_loop = """    const fragment = document.createDocumentFragment();
    cardsWithTags.forEach(cardData => {
        const cardEl = renderCardFromData(cardData, false);
        fragment.appendChild(cardEl);
    });"""

new_loop = """    const fragment = document.createDocumentFragment();
    cardsWithTags.forEach((cardData, index) => {
        const cardEl = renderCardFromData(cardData, false);
        // Stagger entrance for up to 30 items
        const delay = Math.min(index * 40, 1200);
        cardEl.style.animationDelay = `${delay}ms`;
        fragment.appendChild(cardEl);
    });"""

if old_loop in js_content:
    js_content = js_content.replace(old_loop, new_loop)
    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(js_content)
    print("Added card animation and stagger delay to script.js")
else:
    print("Could not find the target loop in script.js")
