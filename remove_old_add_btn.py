import re

# 1. Update page-wordevo.php
php_path = 'page-wordevo.php'
with open(php_path, 'r', encoding='utf-8') as f:
    php_content = f.read()

# We want to remove the old addCardBtn, which looks like:
#     <button class="add-card-btn" id="addCardBtn">
#         <i class="fas fa-plus">
#         </i>
#     </button>
# This occurs after <button class="mobile-toggle-btn" id="showTopBtn">
old_btn_pattern = r'    <button class="add-card-btn" id="addCardBtn">\s*<i class="fas fa-plus">\s*</i>\s*</button>\n'
php_content = re.sub(old_btn_pattern, '', php_content)

with open(php_path, 'w', encoding='utf-8') as f:
    f.write(php_content)

# 2. Update style.css
css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# Remove old #addCardBtn rules
css_content = re.sub(r'#addCardBtn\s*\{[^}]*\}\s*#addCardBtn:hover\s*\{[^}]*\}', '', css_content, flags=re.DOTALL)
# It might appear again in media queries? I will just append a more specific class .primary-add-btn

new_css = """
/* =========================================
   PRIMARY ADD BUTTON (IN TOOLBAR)
   ========================================= */
.primary-add-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    background: linear-gradient(135deg, #0077cc 0%, #005fa3 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 4px 15px rgba(0, 119, 204, 0.3) !important;
    height: 38px !important;
    width: auto !important; /* Override toolbar-btn square constraint if applied */
}

.primary-add-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(0, 119, 204, 0.5) !important;
    filter: brightness(1.1) !important;
}

body.dark .primary-add-btn {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3) !important;
}

body.dark .primary-add-btn:hover {
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5) !important;
}

/* Mobile optimizations for the new toolbar-based Add button */
@media (max-width: 768px) {
    .add-btn-text {
        display: none !important; /* Hide "Add Word" text on small screens */
    }
    .primary-add-btn {
        padding: 0 !important;
        width: 38px !important;
        height: 38px !important;
        border-radius: 50% !important; /* Make it a circle */
    }
    .toolbar-right {
        margin-left: 0 !important; /* Let it wrap or flex nicely */
    }
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(new_css)

print("Removed old floating button and appended new CSS styles.")
