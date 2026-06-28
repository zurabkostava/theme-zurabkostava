import os
import re

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

# Replace border: none with border: 2px solid transparent and add display: inline-flex
css_content = css_content.replace('border: none !important;', 'border: 2px solid transparent !important;\n    display: inline-flex !important;\n    align-items: center !important;\n    justify-content: center !important;')

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(css_content)

print("CSS updated successfully!")
