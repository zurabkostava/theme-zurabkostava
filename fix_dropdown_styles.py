import re

# 1. Update page-wordevo.php to remove inline background styling for the dropdown
php_path = 'page-wordevo.php'
with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

# The inline style we injected was:
# style="display: none; position: absolute; top: 100%; left: 0; background: rgba(30, 30, 46, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 1000; flex-direction: column; gap: 8px; margin-top: 4px;"
# Let's replace it with a cleaner style attribute
old_style = 'style="display: none; position: absolute; top: 100%; left: 0; background: rgba(30, 30, 46, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 1000; flex-direction: column; gap: 8px; margin-top: 4px;"'
new_style = 'style="display: none;"'
content = content.replace(old_style, new_style)

with open(php_path, 'w', encoding='utf-8') as f:
    f.write(content)

# 2. Append CSS to style.css
css_path = 'WordEvo/style.css'
with open(css_path, 'a', encoding='utf-8') as f:
    f.write("""
/* =========================================
   TOOLBAR DROPDOWN (MORE OPTIONS)
   ========================================= */
.toolbar-dropdown {
    position: relative;
    display: inline-block;
}

.toolbar-dropdown-content {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 4px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    flex-direction: column;
    gap: 8px;
}

body.dark .toolbar-dropdown-content {
    background: rgba(30, 30, 46, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}
""")
print("Fixed dropdown styles.")
