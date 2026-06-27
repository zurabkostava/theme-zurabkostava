css_path = 'WordEvo/style.css'
with open(css_path, 'a', encoding='utf-8') as f:
    f.write("\nbody.dark .toolbar-divider { background: rgba(255, 255, 255, 0.1) !important; }\n")
    f.write("@media (max-width: 768px) { .toolbar-divider { display: none !important; } }\n")
