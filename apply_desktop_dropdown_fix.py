import re

css_path = 'WordEvo/style.css'
with open(css_path, 'a', encoding='utf-8') as f:
    f.write("""
/* =========================================
   DESKTOP VS MOBILE FOR TOOLBAR DROPDOWN
   ========================================= */
@media (min-width: 769px) {
    #toolbarMoreBtn {
        display: none !important;
    }
    #toolbarDropdownContent {
        display: flex !important;
        position: static !important;
        flex-direction: row !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-top: 0 !important;
        gap: 12px !important; /* Match toolbar gap */
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }
    /* Let the inner buttons look like normal toolbar buttons */
    #toolbarDropdownContent .toolbar-btn {
        margin: 0 !important;
    }
}
""")
print("Added desktop specific rules for dropdown")
