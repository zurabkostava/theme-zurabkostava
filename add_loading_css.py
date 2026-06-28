css_path = 'WordEvo/style.css'
css_content = """
/* =========================================
   GLOBAL LOADING SCREEN
   ========================================= */
#globalLoadingScreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999999;
    transition: opacity 0.4s ease, visibility 0.4s ease;
}

.dark #globalLoadingScreen {
    background: #0d1117;
}

.wordevo-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(0, 119, 204, 0.2);
    border-top: 4px solid #0077cc;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#globalLoadingScreen.hidden {
    opacity: 0;
    visibility: hidden;
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(css_content)
    
print("Added loading screen CSS")
