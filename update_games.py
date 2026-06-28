import os
import re

base_dir = r"d:\PROJECTS\CAREER\Website\wp-content\themes\zurabkostava\app\public\wp-content\themes\zurabkostava\WordEvo"

# 1. Update script.js with getAutoAdvanceHTML
script_js_path = os.path.join(base_dir, 'script.js')
with open(script_js_path, 'r', encoding='utf-8') as f:
    script_content = f.read()

helper_fn = """
function getAutoAdvanceHTML(prefix) {
    const isChecked = localStorage.getItem('autoAdvance') !== 'false' ? 'checked' : '';
    return `
    <div id="${prefix}ActionArea" style="display: none; justify-content: flex-end; align-items: center; margin-top: 20px; background: rgba(0,0,0,0.03); padding: 10px 20px; border-radius: 14px; border: 2px dashed var(--glass-border); gap: 15px;">
        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; margin: 0;">
            <input type="checkbox" id="${prefix}AutoAdvance" onchange="localStorage.setItem('autoAdvance', this.checked)" ${isChecked}> 
            <span style="font-weight: 500; font-size: 16px;">Auto-advance</span>
        </label>
        <button id="${prefix}NextBtn" class="action-btn-secondary" style="margin: 0; padding: 8px 24px;">Next ↵</button>
    </div>
    `;
}
"""

if "function getAutoAdvanceHTML" not in script_content:
    script_content += "\n" + helper_fn
    with open(script_js_path, 'w', encoding='utf-8') as f:
        f.write(script_content)
    print("Updated script.js")

games = [
    ('typegame.js', 'ti'),
    ('speakgame.js', 'speak'),
    ('wordhear.js', 'wh'),
    ('makeword.js', 'mw'),
    ('sentence.js', 'sen'),
    ('puzzle.js', 'puzzle'),
    ('quiz.js', 'quiz'),
    ('mix.js', 'mix')
]

for game_file, prefix in games:
    path = os.path.join(base_dir, 'games', game_file)
    if not os.path.exists(path):
        print(f"File not found: {path}")
        continue
        
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Replace *EnterHint in HTML template
    hint_pattern = re.compile(r'<div id="' + prefix + r'EnterHint"[^>]*>.*?</div>', re.IGNORECASE)
    if hint_pattern.search(content):
        content = hint_pattern.sub(f'${{getAutoAdvanceHTML("{prefix}")}}', content)
    else:
        print(f"Could not find EnterHint in {game_file}")

    # 2. Add onclick handler and modify setTimeout
    content = content.replace(f"document.getElementById('{prefix}EnterHint').classList.add('visible');", 
                              f"document.getElementById('{prefix}ActionArea').style.display = 'flex';")
                              
    timeout_pattern = re.compile(
        r'(window\.' + prefix + r'NextReady\s*=\s*true;\s*)' +
        r'(?:window\.' + prefix + r'Timeout\s*=\s*)?setTimeout\(\(\)\s*=>\s*\{' +
        r'(.*?)' +
        r'\}\s*,\s*\d+\);', re.DOTALL
    )

    def replace_timeout(m):
        ready_assignment = m.group(1)
        inner_code = m.group(2)
        
        return f"""{ready_assignment}
            document.getElementById('{prefix}NextBtn').onclick = () => {{
                if (window.{prefix}Timeout) clearTimeout(window.{prefix}Timeout);
                {inner_code.strip()}
            }};
            if (document.getElementById('{prefix}AutoAdvance').checked) {{
                window.{prefix}Timeout = setTimeout(() => {{
                    {inner_code.strip()}
                }}, 1500);
            }}"""
        
    content = timeout_pattern.sub(replace_timeout, content)

    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Processed {game_file}")
