import os

file_path = "WordEvo/script.js"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace block with empty string for card.style.display in filterCardsByTags and search logic
content = content.replace("card.style.display = (matchesTags && matchesProgress) ? 'block' : 'none';",
                          "card.style.display = (matchesTags && matchesProgress) ? '' : 'none';")

content = content.replace("card.style.display = matches ? 'block' : 'none';",
                          "card.style.display = matches ? '' : 'none';")

# Just in case, replace any other 'block' for cards
content = content.replace("card.style.display = 'block';",
                          "card.style.display = '';")

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Fixed display block bug in script.js!")
