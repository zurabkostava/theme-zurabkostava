php_path = 'page-wordevo.php'
with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = """            <div id="notificationListContainer">
                <p class="notif-empty" style="text-align: center; color: #888;">No reminders.</p>
            </div>
            </div>

            </div> <!-- end body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px; display: flex; gap: 10px;">"""

replacement = """            <div id="notificationListContainer">
                <p class="notif-empty" style="text-align: center; color: #888;">No reminders.</p>
            </div>
            </div> <!-- end body -->
            
            <div class="modal-footer modal-actions" style="margin-top: 0; display: flex; gap: 10px;">"""

if target in content:
    content = content.replace(target, replacement)
    with open(php_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Fixed extra closing divs in notificationsModal")
else:
    print("Target not found. Doing regex fallback...")
    import re
    # Just remove any `</div>` between notificationListContainer and modal-footer
    # other than the one that closes modal-body
    # Actually let's just make it very precise:
    
    pass
