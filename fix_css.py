import os

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# Replace any occurrence of :not(.mix-btn) with :not(.mix-btn):not(.puzzle-word):not(.mw-char)
# However, let's be careful not to duplicate if we already did it (though we didn't).

if ":not(.mix-btn):not(.puzzle-word)" not in css_content:
    css_content = css_content.replace(':not(.mix-btn)', ':not(.mix-btn):not(.puzzle-word):not(.mw-char)')
    
with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css_content)
    
print("CSS updated successfully")
