php_path = 'page-wordevo.php'
with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

# The end of the file looks like:
#                 <div class="notif-form-actions">
#                     <button id="notifSaveBtn"><i class="fas fa-check"></i> Save</button>
#                     <button id="notifCancelBtn"><i class="fas fa-times"></i> Cancel</button>
#                 </div>
#             </div>
#         </div>
#     </div>
#     
#     <h2 class="app-logo mobile-logo">Wordevo</h2>

target = '</div>\n        </div>\n    </div>\n    \n    <h2 class="app-logo mobile-logo">Wordevo</h2>'
replacement = '</div>\n        </div>\n    </div>\n</div>\n    \n    <h2 class="app-logo mobile-logo">Wordevo</h2>'

content = content.replace(target, replacement)

with open(php_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Fixed missing closing div in page-wordevo.php")
