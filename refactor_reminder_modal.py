import re

# 1. Refactor HTML in page-wordevo.php
php_path = 'page-wordevo.php'
with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

# We need to extract the notifAddForm from notificationsModal
# and put it into its own modal.
# The original code looks like:
#             <div class="notif-add-form" id="notifAddForm" style="display:none;">
#                 <h3>New Reminder</h3>
# ...
#                 <div class="notif-form-actions">
#                     <button id="notifSaveBtn"><i class="fas fa-check"></i> Save</button>
#                     <button id="notifCancelBtn"><i class="fas fa-times"></i> Cancel</button>
#                 </div>
#             </div>
#             </div> <!-- end body -->

# Let's find the block using regex
pattern = re.compile(r'<div class="notif-add-form" id="notifAddForm".*?</div>\s*</div>', re.DOTALL)
match = pattern.search(content)

if match:
    form_html = match.group(0)
    # Remove the <h3>New Reminder</h3> since we'll put it in modal-header
    form_html_cleaned = re.sub(r'<h3>.*?</h3>', '', form_html)
    
    new_modal_html = f"""
    <div class="modal-overlay" id="notifAddModal" style="display:none; z-index: 100000;">
        <div class="modal" style="max-width:450px;">
            <div class="modal-header">
                <h2 id="notifAddModalTitle">New Reminder</h2>
                <button class="close-button" id="closeNotifAddModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                {form_html_cleaned}
            </div>
        </div>
    </div>
    """
    
    # Remove it from old location
    content = content.replace(form_html, '')
    
    # Append the new modal HTML right before the end of body or near notificationsModal
    # Let's insert it right after the closing of notificationsModal
    modal_end_pattern = '</div>\n    </div>\n\n    <h2 class="app-logo mobile-logo">'
    content = content.replace(modal_end_pattern, '</div>\n    </div>\n' + new_modal_html + '\n    <h2 class="app-logo mobile-logo">')
    
    with open(php_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("HTML refactored.")
else:
    print("Could not find notifAddForm block.")

# 2. Refactor JavaScript in notifications.js
js_path = 'WordEvo/notifications.js'
with open(js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

# Replace openNotifForm logic
js_replace_1 = """function openNotifForm(notif) {
    const form = document.getElementById('notifAddForm');
    const addBtn = document.getElementById('openAddNotificationModalBtn');
    const formTitle = form.querySelector('h3');

    form.style.display = 'block';
    addBtn.style.display = 'none';"""

js_new_1 = """function openNotifForm(notif) {
    const modal = document.getElementById('notifAddModal');
    const formTitle = document.getElementById('notifAddModalTitle');

    modal.style.display = 'flex';"""

js_content = js_content.replace(js_replace_1, js_new_1)

# In initNotificationUI, fix references and cancel button behavior
js_replace_2 = """    notifBtn.onclick = () => {
        renderNotificationList();
        form.style.display = 'none';
        addBtn.style.display = '';
        editingNotifIndex = -1;
        modal.style.display = 'flex';
    };

    closeBtn.onclick = () => {
        modal.style.display = 'none';
        form.style.display = 'none';
        editingNotifIndex = -1;
    };"""

js_new_2 = """    notifBtn.onclick = () => {
        renderNotificationList();
        editingNotifIndex = -1;
        modal.style.display = 'flex';
    };

    closeBtn.onclick = () => {
        modal.style.display = 'none';
        editingNotifIndex = -1;
    };
    
    const notifAddModal = document.getElementById('notifAddModal');
    const closeNotifAddModalBtn = document.getElementById('closeNotifAddModalBtn');
    if (closeNotifAddModalBtn) {
        closeNotifAddModalBtn.onclick = () => {
            notifAddModal.style.display = 'none';
            editingNotifIndex = -1;
        };
    }"""

js_content = js_content.replace(js_replace_2, js_new_2)

# Update cancelBtn.onclick
js_replace_3 = """    cancelBtn.onclick = () => {
        form.style.display = 'none';
        addBtn.style.display = '';
        editingNotifIndex = -1;
    };"""

js_new_3 = """    cancelBtn.onclick = () => {
        const notifAddModal = document.getElementById('notifAddModal');
        if (notifAddModal) notifAddModal.style.display = 'none';
        editingNotifIndex = -1;
    };"""

js_content = js_content.replace(js_replace_3, js_new_3)

# Update save logic to close the new modal
js_replace_4 = """        form.style.display = 'none';
        addBtn.style.display = '';"""

js_new_4 = """        const notifAddModal = document.getElementById('notifAddModal');
        if (notifAddModal) notifAddModal.style.display = 'none';"""

js_content = js_content.replace(js_replace_4, js_new_4)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)
print("JS refactored.")
