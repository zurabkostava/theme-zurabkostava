import re

css_path = 'WordEvo/style.css'
with open(css_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Using a non-greedy regex to remove both blocks
content = re.sub(r'#addCardBtn\s*\{.*?\}(?=\s|$)', '', content, flags=re.DOTALL)
content = re.sub(r'#addCardBtn:hover\s*\{.*?\}(?=\s|$)', '', content, flags=re.DOTALL)
# Also remove the comment above it just in case
content = re.sub(r'/\* ღილაკი დამატების მრგვალი სტილისთვის \*/\s*', '', content)

with open(css_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Cleaned up old CSS for #addCardBtn")
