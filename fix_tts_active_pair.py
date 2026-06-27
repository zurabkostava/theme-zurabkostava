import re

file_path = 'WordEvo/tts.js'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace(
    "if (el.parentElement && el.parentElement.classList.contains('sentence-pair')) {\n                    el.parentElement.classList.add('active-pair');\n                }",
    "const pair = el.closest('.sentence-pair');\n                if (pair) pair.classList.add('active-pair');"
)

content = content.replace(
    "if (el.parentElement) el.parentElement.classList.remove('active-pair');",
    "const pair = el.closest('.sentence-pair');\n                            if (pair) pair.classList.remove('active-pair');"
)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Successfully updated tts.js active-pair handling.")
