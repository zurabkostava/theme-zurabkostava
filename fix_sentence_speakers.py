import re

file_path = "WordEvo/style.css"
with open(file_path, "a", encoding="utf-8") as f:
    f.write("""
/* =========================================
   INLINE SENTENCE SPEAK BUTTONS
   ========================================= */
.sentence-pair .speak-btn {
    padding: 4px 8px !important;
    font-size: 0.85rem !important;
    border-radius: 6px !important;
    margin-left: 6px !important;
    margin-right: 0 !important;
    vertical-align: middle !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.sentence-pair .speak-btn:hover {
    transform: scale(1.05) !important; /* No translateY to avoid jumping */
}

.sentence-pair .speak-btn:active {
    transform: scale(0.95) !important;
}
""")

print("Fixed sentence inline speakers successfully!")
