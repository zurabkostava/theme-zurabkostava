import os

js_path = 'WordEvo/script.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_block = """// 5. ვხატავთ ბარათებს და ვავსებთ UI-ს
    const fragment = document.createDocumentFragment();
    cardsWithTags.forEach((cardData, index) => {
        const cardEl = renderCardFromData(cardData, false);
        // Stagger entrance for up to 30 items
        const delay = Math.min(index * 40, 1200);
        cardEl.style.animationDelay = `${delay}ms`;
        fragment.appendChild(cardEl);
    });
    document.getElementById('cardContainer').appendChild(fragment);
    sortCards();"""

new_block = """// 5. ვხატავთ ბარათებს და ვავსებთ UI-ს
    const cardElements = cardsWithTags.map(cardData => renderCardFromData(cardData, false));
    
    // Sort the elements in memory before appending
    cardElements.sort((a, b) => {
        let valA, valB;
        if (currentSortMode === 'alphabetical') {
            valA = a.querySelector('.word').textContent.trim().toLowerCase();
            valB = b.querySelector('.word').textContent.trim().toLowerCase();
        } else if (currentSortMode === 'updated') {
            valA = parseInt(a.dataset.updated || 0);
            valB = parseInt(b.dataset.updated || 0);
        } else if (currentSortMode === 'progress') {
            valA = parseFloat(a.dataset.progress || 0);
            valB = parseFloat(b.dataset.progress || 0);
        }
        const result = valA > valB ? 1 : valA < valB ? -1 : 0;
        return sortOrder === 'asc' ? result : -result;
    });

    const fragment = document.createDocumentFragment();
    cardElements.forEach((cardEl, index) => {
        // Stagger entrance for up to 30 items
        const delay = Math.min(index * 40, 1200);
        cardEl.style.animationDelay = `${delay}ms`;
        fragment.appendChild(cardEl);
    });
    document.getElementById('cardContainer').appendChild(fragment);
    // Removed sortCards() call to prevent detaching/re-attaching which restarts the CSS animation"""

if old_block in js_content:
    js_content = js_content.replace(old_block, new_block)
    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(js_content)
    print("Fixed double animation issue")
else:
    print("Could not find the target block in script.js")
