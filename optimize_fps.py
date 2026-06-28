import os

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# 1. Remove backdrop-filter from .training-modal
old_modal = """.training-modal {
    background: rgba(0, 0, 0, 0.4) !important;
    backdrop-filter: blur(10px) !important;
    -webkit-backdrop-filter: blur(10px) !important;
}"""

new_modal = """.training-modal {
    background: rgba(0, 0, 0, 0.7) !important; /* Slightly darker background since blur is removed */
}"""

if old_modal in css_content:
    css_content = css_content.replace(old_modal, new_modal)

# 2. Add an override for .training-modal-content to ensure no glass effect kills FPS
override_css = """
/* =========================================
   PERFORMANCE OPTIMIZATION FOR TRAINING
   ========================================= */
.training-modal-content {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}
.dark .training-modal-content {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    background: var(--card-bg-premium) !important;
}
"""

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css_content.strip() + '\n' + override_css)

print("Applied FPS optimizations to training modal")
