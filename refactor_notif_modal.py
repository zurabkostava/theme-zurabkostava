import re

html_path = 'page-wordevo.php'
with open(html_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Extract the form fields from #notificationsModal
form_fields_regex = r'(\s*<div class="notif-form-row">\s*<label>Dictionary:</label>.*?<button id="notifCancelBtn"><i class="fas fa-times"></i> Cancel</button>\s*</div>)'
match = re.search(form_fields_regex, content, re.DOTALL)

if match:
    form_fields_html = match.group(1)
    
    # Remove it from the original location
    content = content.replace(form_fields_html, '')
    
    # Clean up the #notifAddForm display:none
    content = content.replace('<div class="notif-add-form" id="notifAddForm" style="display:none;">', '<div class="notif-add-form" id="notifAddForm">')
    
    # 2. Insert it into #notifAddModal right after the weekdays div closes
    insert_target = '</div>\n                </div>\n            </div>\n        </div>\n    </div>\n    \n    <h2 class="app-logo mobile-logo">Wordevo</h2>'
    # We want to insert it right before the last two closing divs of notifAddForm/modal-body
    # Let's use a more precise insertion.
    
    # In #notifAddModal, we have:
    #                 <div class="notif-weekdays" id="notifWeekdays">
    #                     ...
    #                 </div>
    #             </div>
    #         </div> <!-- closes notifAddForm -->
    #     </div> <!-- closes modal-body -->
    # </div> <!-- closes modal -->
    
    insert_regex = r'(<div class="notif-weekdays" id="notifWeekdays">.*?</div>\s*</div>)'
    match_insert = re.search(insert_regex, content, re.DOTALL)
    if match_insert:
        new_insert = match_insert.group(1) + form_fields_html
        content = content.replace(match_insert.group(1), new_insert)
    
    with open(html_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Refactored page-wordevo.php HTML")
else:
    print("Could not find the form fields to extract.")
