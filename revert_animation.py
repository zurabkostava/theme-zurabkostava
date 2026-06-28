import os

# 1. Remove CSS animation
css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

target_css = """/* =========================================
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
}"""

if target_css in css_content:
    css_content = css_content.replace(target_css, "")
    with open(css_path, 'w', encoding='utf-8') as f:
        f.write(css_content.strip() + '\n')
    print("Removed CSS animation")
else:
    print("CSS animation not found")

# 2. Remove JS animation delay
js_path = 'WordEvo/script.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

old_loop = """    cardElements.forEach((cardEl, index) => {
        // Stagger entrance for up to 30 items
        const delay = Math.min(index * 40, 1200);
        cardEl.style.animationDelay = `${delay}ms`;
        fragment.appendChild(cardEl);
    });"""

new_loop = """    cardElements.forEach((cardEl, index) => {
        fragment.appendChild(cardEl);
    });"""

if old_loop in js_content:
    js_content = js_content.replace(old_loop, new_loop)
    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(js_content)
    print("Removed JS animation delay")
else:
    print("JS animation delay not found")
