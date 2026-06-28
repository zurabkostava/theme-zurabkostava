import os

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# 1. Update .training-modal background to 94% opacity
old_modal = """.training-modal {
    background: rgba(0, 0, 0, 0.7) !important; /* Slightly darker background since blur is removed */
}"""

new_modal = """.training-modal {
    background: rgba(0, 0, 0, 0.94) !important;
}"""

if old_modal in css_content:
    css_content = css_content.replace(old_modal, new_modal)

# 2. Add .no-scroll class and FILL cube styles
additional_css = """
/* =========================================
   SCROLL LOCK
   ========================================= */
body.no-scroll {
    overflow: hidden !important;
}

/* =========================================
   FILL CUBES STYLING
   ========================================= */
.mw-letter.missing {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
    margin: 0 4px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    font-size: 24px;
    font-weight: bold;
    color: var(--text-primary);
    transition: all 0.2s ease;
    vertical-align: middle;
}

.mw-letter.missing.active-typing {
    border-color: var(--accent);
    box-shadow: 0 0 10px rgba(var(--accent-rgb), 0.5);
    transform: translateY(-2px);
}

.mw-letter.missing.inserted-letter {
    border-color: #4caf50;
    color: #4caf50;
    background: rgba(76, 175, 80, 0.1);
}
"""

if "body.no-scroll" not in css_content:
    with open(css_path, 'w', encoding='utf-8') as f:
        f.write(css_content.strip() + '\n' + additional_css)
    print("Updated CSS")
else:
    print("CSS already updated")
