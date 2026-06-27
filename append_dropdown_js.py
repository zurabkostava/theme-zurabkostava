js_path = 'WordEvo/script.js'
js_code = """
// Setup Toolbar Dropdown logic
document.addEventListener('DOMContentLoaded', () => {
    const moreBtn = document.getElementById('toolbarMoreBtn');
    const dropdown = document.getElementById('toolbarDropdownContent');
    
    if (moreBtn && dropdown) {
        moreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'flex';
            } else {
                dropdown.style.display = 'none';
            }
        });
        
        document.addEventListener('click', (e) => {
            if (dropdown.style.display === 'flex' && !dropdown.contains(e.target) && !moreBtn.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }
});
"""

with open(js_path, 'a', encoding='utf-8') as f:
    f.write(js_code)
