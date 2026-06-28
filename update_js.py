import os

# 1. Update script.js for no-scroll
js_path = 'WordEvo/script.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

open_old = "document.getElementById('trainingModal').classList.remove('hidden');"
open_new = "document.getElementById('trainingModal').classList.remove('hidden');\n        document.body.classList.add('no-scroll');"

close_old = "document.getElementById('trainingModal').classList.add('hidden');"
close_new = "document.getElementById('trainingModal').classList.add('hidden');\n        document.body.classList.remove('no-scroll');"

if open_old in js_content and "document.body.classList.add('no-scroll')" not in js_content:
    js_content = js_content.replace(open_old, open_new)
if close_old in js_content and "document.body.classList.remove('no-scroll')" not in js_content:
    js_content = js_content.replace(close_old, close_new)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)

# 2. Update typegame.js for "Hint" text
type_path = 'WordEvo/games/typegame.js'
with open(type_path, 'r', encoding='utf-8') as f:
    type_content = f.read()

type_old = "💡 მინიშნება"
type_new = "💡 Hint"
if type_old in type_content:
    type_content = type_content.replace(type_old, type_new)
    with open(type_path, 'w', encoding='utf-8') as f:
        f.write(type_content)

# 3. Update puzzle.js for layout inline flow
puzzle_path = 'WordEvo/games/puzzle.js'
with open(puzzle_path, 'r', encoding='utf-8') as f:
    puzzle_content = f.read()

puzzle_old1 = 'style="min-height: 70px; background: rgba(0,0,0,0.03); border: 2px dashed var(--glass-border); border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 8px;"'
puzzle_new1 = 'style="min-height: 70px; background: rgba(0,0,0,0.03); border: 2px dashed var(--glass-border); border-radius: 12px; padding: 15px; margin-bottom: 20px;"'

puzzle_old2 = 'style="margin: 10px 0 25px 0; display: flex; flex-wrap: wrap; gap: 8px;"'
puzzle_new2 = 'style="margin: 10px 0 25px 0;"'

puzzle_old3 = "btn.className = 'puzzle-word mix-btn';"
puzzle_new3 = "btn.className = 'puzzle-word mix-btn';\n        btn.style.display = 'inline-block';\n        btn.style.margin = '4px';"

if puzzle_old1 in puzzle_content:
    puzzle_content = puzzle_content.replace(puzzle_old1, puzzle_new1)
if puzzle_old2 in puzzle_content:
    puzzle_content = puzzle_content.replace(puzzle_old2, puzzle_new2)
if puzzle_old3 in puzzle_content and "'inline-block'" not in puzzle_content:
    puzzle_content = puzzle_content.replace(puzzle_old3, puzzle_new3)

with open(puzzle_path, 'w', encoding='utf-8') as f:
    f.write(puzzle_content)

print("JS updates applied.")
