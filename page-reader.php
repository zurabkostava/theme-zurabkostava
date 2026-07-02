<?php
/* Template Name: Web Reader */
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

<div id="neural-app-root">

    <div class="glow-bg"></div>

    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <span>Table of Contents</span>
            <button id="close-sidebar-btn" class="icon-btn sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div id="toc-list" class="toc-list"></div>
    </div>
    <div id="sidebar-overlay" class="sidebar-overlay hidden"></div>

    <div class="header">
        <div class="header-left">
            <button id="sidebar-toggle-btn" class="icon-btn hidden" title="Table of Contents">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>

            <div class="logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
                Neural Reader <span style="font-size: 0.7em; opacity: 0.5; margin-left: 5px;">PRO</span>
            </div>

            <div id="book-meta-container" class="book-meta hidden">
                <img id="book-cover-img" src="" alt="Cover">
                <div class="book-text-info">
                    <span id="book-title-text">Book Title</span>
                    <span id="book-author-text">Author Name</span>
                    <div id="header-progress-badge" class="progress-badge hidden">0%</div>
                </div>
            </div>
        </div>

        <div class="header-actions">
            <button id="library-btn" class="icon-btn" title="Library">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            </button>
            <input type="file" id="file-input" accept=".epub" style="display: none;">

            <button id="upload-btn" class="icon-btn" title="Upload EPUB">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            </button>
            <button id="edit-btn" class="icon-btn" title="Edit / Paste Text">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </button>
            <button id="settings-btn" class="icon-btn" title="Settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </button>
        </div>
    </div>

    <div id="settings-panel" class="settings-panel hidden">
        <div id="dynamic-voice-settings"></div>
        <button id="refresh-voices-btn" class="ctrl-btn sm" style="width:100%; margin-top:10px;">Refresh Voices ↻</button>
    </div>

    <div id="progress-container">
        <div id="progress-bar"></div>
    </div>

    <div id="content-area" class="content-area">
        <div id="drop-zone" class="drop-zone">
            <div class="drop-content">
                <svg viewBox="0 0 24 24" width="64" height="64" stroke="currentColor" fill="none" stroke-width="1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="12" y2="12"></line><line x1="15" y1="15" x2="12" y2="12"></line></svg>
                <h3>Drag & Drop EPUB here</h3>
                <p>or click the upload button above</p>
                <p class="sub-text">You can also paste text manually via Edit mode</p>
            </div>
        </div>
    </div>

    <div id="tts-status-indicator" class="tts-status-indicator hidden">
        <span class="tts-spinner"></span>
        <span id="tts-status-text">Initializing Neural Voice...</span>
    </div>

    <div class="controls-overlay">
        <div class="controls premium-controls">
            <button id="prev-btn" class="ctrl-btn sm premium-btn" title="Previous"><svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><polygon points="17 20 7 12 17 4 17 20"></polygon><line x1="5" y1="19" x2="5" y2="5"></line></svg></button>
            <button id="play-btn" class="ctrl-btn play premium-play" title="Play/Pause">
                <svg id="play-icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round" style="width:26px;height:26px; margin-left: 4px;"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                <svg id="pause-icon" class="hidden" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="width:26px;height:26px;"><rect x="6" y="4" width="4" height="16" rx="2"></rect><rect x="14" y="4" width="4" height="16" rx="2"></rect></svg>
            </button>
            <button id="stop-btn" class="ctrl-btn stop premium-btn" title="Stop"><svg viewBox="0 0 24 24" fill="currentColor" stroke="none" style="width:20px;height:20px;"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg></button>
            <button id="next-btn" class="ctrl-btn sm premium-btn" title="Next"><svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><polygon points="7 4 17 12 7 20 7 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg></button>
        </div>
    </div>

    <div id="book-info-modal" class="info-modal-overlay hidden">
        <div class="info-modal-content">
            <button id="close-modal-btn" class="icon-btn close-modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>

            <div class="modal-header">
                <img id="modal-book-cover" src="" alt="Book Cover" style="display:none;">
                <div class="modal-title-group">
                    <h2 id="modal-book-title">Book Title</h2>
                    <h3 id="modal-book-author">Author Name</h3>
                    <div id="modal-book-publisher" class="publisher-info hidden"></div>
                    <div id="modal-book-genre" class="genre-row"></div>
                </div>
            </div>

            <div class="modal-body">
                <h4>Description</h4>
                <div id="modal-book-desc" class="desc-text">
                    No description available.
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div id="library-modal" class="info-modal-overlay hidden">
    <div class="info-modal-content premium-modal" style="max-width: 900px;">
        <button id="close-library-btn" class="icon-btn close-modal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>

        <div class="modal-header premium-header">
            <div class="modal-title-group" style="text-align: center; width: 100%;">
                <h2 class="premium-title">📚 Your Library <span id="total-books-count" class="count-badge">...</span></h2>
                <h3 class="premium-subtitle">Select a book to read</h3>
            </div>
        </div>
        <div class="sticky-search-wrapper">
            <div class="search-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
            <input type="text" id="library-search-input" class="premium-search-input" placeholder="Search by title or author...">
        </div>
        <div id="library-grid" class="library-grid">
        </div>
    </div>
</div>
<style>
    /* --- WP OVERRIDES & ISOLATION --- */
    #wpadminbar { display: none !important; }
    /* მთლიანი გვერდის "დაბლოკვა", რომ არ იხტუნავოს */
    body, html {
        overflow: hidden !important;
        height: 100% !important;
        width: 100% !important;
        position: fixed !important; /* ეს არის მთავარი წამალი */
    }
    /* The Isolator Container */
    #neural-app-root {
        position: fixed !important; /* !important კრიტიკულია */
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        z-index: 100000;
        background-color: #09090b;
        color: #f8fafc;
        font-family: 'Inter', system-ui, sans-serif;
        display: flex;
        flex-direction: column;
        overflow: hidden; /* ეს კრძალავს მთლიანი აპლიკაციის სკროლს */
        overscroll-behavior: none; /* ეს კრძალავს "Rubber band" ეფექტს */
    }

    #neural-app-root * { box-sizing: border-box; }

    /* --- CRITICAL BUTTON RESET FOR WP THEMES --- */
    #neural-app-root button {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        border-radius: 0 !important;
        min-width: unset !important;
        min-height: unset !important;
        line-height: 1 !important;
        color: inherit !important;
        font-family: inherit !important;
        letter-spacing: normal !important;
        text-transform: none !important;
    }

    /* --- MAIN CSS VARIABLES --- */
    :root {
        --bg-dark: #09090b;
        --bg-panel: rgba(24, 24, 27, 0.85);
        --primary: #38bdf8;
        --primary-glow: rgba(56, 189, 248, 0.4);
        --accent: #f472b6;
        --accent-glow: rgba(244, 114, 182, 0.4);
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
        --border: rgba(255, 255, 255, 0.1);
        --font-main: 'Inter', system-ui, sans-serif;
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glow-bg {
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at 50% 50%, rgba(56, 189, 248, 0.05), transparent 60%);
        pointer-events: none;
        z-index: 0;
    }

    /* --- HEADER --- */
    .header {
        flex: 0 0 auto;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-panel);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--border);
        z-index: 40000;
    }

    .logo {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.5px;
    }
    .logo svg { color: var(--primary); }

    .header-actions { display: flex; gap: 8px; }
    .header-left { display: flex; align-items: center; gap: 15px; }

    /* --- BOOK META HEADER --- */
    .book-meta {
        cursor: pointer; /* ეს აუცილებელია */
        display: flex;
        align-items: center;
        gap: 12px;
        margin-left: 20px;
        padding-left: 20px;
        border-left: 1px solid var(--border);
        animation: fadeIn 0.5s ease;
    }
    .book-meta:hover {
        opacity: 0.8;
    }

    .book-meta.hidden { display: none; }

    #book-cover-img {
        height: 40px;
        width: auto;
        border-radius: 4px;
        border: 1px solid var(--border);
        object-fit: cover;
    }

    .book-text-info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        line-height: 1.2;
    }

    #book-title-text {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-main);
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #book-author-text {
        font-size: 0.75rem;
        color: var(--text-muted);
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    /* მობილურზე დავმალოთ, რომ ადგილი არ წაიღოს */
    @media (max-width: 600px) {
        /* დეფოლტად (როცა წიგნი არ არის) მეტა დამალულია */
        .book-meta { display: none; }

        /* როცა წიგნი იხსნება (is-reading კლასი) */
        body.is-reading .logo {
            display: none !important; /* ლოგოს დამალვა */
        }

        body.is-reading .book-meta {
            display: flex !important; /* სათაურის გამოჩენა */
            margin-left: 0;
            padding-left: 0;
            border-left: none;
            width: 100%;
        }

        /* მობილურზე ავტორი დავმალოთ, რომ სათაური კარგად დაეტიოს */
        body.is-reading #book-author-text {
            display: none;
        }

        body.is-reading #book-title-text {
            font-size: 0.95rem; /* ოდნავ გავადიდოთ */
            max-width: 220px; /* მეტი ადგილი სათაურს */
        }
    }
    #neural-app-root .icon-btn:hover { background: rgba(255,255,255,0.05) !important; color: var(--text-main) !important; }
    #neural-app-root .icon-btn svg { width: 20px !important; height: 20px !important; fill: none !important; stroke: currentColor !important; }

    /* --- SETTINGS --- */
    .settings-panel {
        background: rgba(15, 23, 42, 0.98);
        padding: 20px;
        border-bottom: 1px solid var(--primary-glow);
        display: flex;
        flex-direction: column;
        gap: 16px;
        transition: var(--transition);
        z-index: 15;
    }
    .settings-panel.hidden { display: none; }

    .setting-group label {
        display: block;
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .select-wrapper { position: relative; }
    .select-wrapper::after {
        content: '▼';
        font-size: 0.7rem;
        color: var(--text-muted);
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    #neural-app-root select {
        width: 100%;
        padding: 10px 12px;
        background: rgba(0,0,0,0.3);
        border: 1px solid var(--border);
        color: var(--text-main);
        border-radius: 8px;
        font-family: inherit;
        appearance: none;
        cursor: pointer;
        font-size: 0.9rem;
    }
    #neural-app-root select:focus { outline: none; border-color: var(--primary); }

    .setting-row { display: flex; gap: 12px; }
    .setting-group.half { flex: 1; }

    #neural-app-root input[type="range"] {
        width: 100%;
        height: 4px;
        background: rgba(255,255,255,0.1);
        border-radius: 2px;
        appearance: none;
        margin: 10px 0;
    }
    #neural-app-root input[type="range"]::-webkit-slider-thumb {
        appearance: none;
        width: 14px;
        height: 14px;
        background: var(--primary);
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 0 10px var(--primary-glow);
    }

    /* --- PROGRESS & CONTENT --- */
    @keyframes spin { 100% { transform: rotate(360deg); } }
    
    #progress-container {
        flex: 0 0 auto;
        width: 100%;
        height: 3px;
        background: rgba(255,255,255,0.05);
        z-index: 10;
    }
    #progress-bar {
        width: 0%;
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--accent));
        box-shadow: 0 0 10px var(--primary-glow);
        transition: width 0.3s ease-out;
    }

    .content-area {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 24px;
        position: relative; /* კრიტიკულია offsetTop-ის სწორად დასათვლელად */

        /* გავზარდეთ 100px-დან 140px-მდე მობილურის უსაფრთხოებისთვის */
        padding-bottom: 115px;

        font-size: 1.05rem;
        line-height: 1.8;
        color: var(--text-muted);
        position: relative;
        z-index: 1;
    }
    .trp-language-switcher{
        display: none;
    }
    .content-area::-webkit-scrollbar { width: 6px; }
    .content-area::-webkit-scrollbar-track { background: transparent; }
    .content-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
    .content-area::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        opacity: 0.3;
        text-align: center;
    }
    .placeholder svg { margin-bottom: 10px; }

    .paragraph { margin-bottom: 24px; }

    .sentence {
        cursor: pointer;
        border-radius: 6px;
        padding: 2px 4px;
        transition: background 0.2s, color 0.2s;
    }
    /* ეს არის მთავარი - მთლიან წინადადებას ამკრთალებს */
    .sentence.read {
        opacity: 0.4; /* 40% გამჭვირვალობა */
        color: #64748b; /* მონაცრისფრო ფერი */
        transition: opacity 0.3s ease, color 0.3s ease;
    }

    /* აქტიურ წინადადებას მკვეთრად ვტოვებთ */
    .sentence.active {
        opacity: 1;
        color: inherit;
        background-color: rgba(56, 189, 248, 0.1); /* ოდნავ ცისფერი ფონი */
        border-radius: 4px;
    }

    .sentence:hover { color: var(--text-main); }
    .sentence.active {
        background: rgba(56, 189, 248, 0.1);
        color: var(--text-main);
        box-shadow: inset 3px 0 0 0 var(--primary);
    }

    .word.active {
        color: #fff;
        background: var(--accent);
        border-radius: 4px;
        box-shadow: 0 0 0 2px var(--accent), 0 0 10px var(--accent-glow);
        text-shadow: 0 0 1px white;
        z-index: 2;
        position: relative;
    }

    /* 0.7 Opacity (უფრო მკაფიო) */
    .sentence.read {
        opacity: 0.3 !important; /* უფრო მეტად ჩავაქროთ */
        filter: blur(0.2px); /* ოდნავ ბუნდოვანი, რომ თვალმა აქტიურზე მოახდინოს ფოკუსი */
        transition: opacity 0.5s ease, filter 0.5s ease;
    }

    .word.read {
        opacity: 0.7 !important;
        transition: opacity 0.2s ease;
        background: transparent !important;
        box-shadow: none !important;
        color: inherit !important; /* ფერიც ჩვეულებრივი გახდეს */
    }

    /* აქტიური წინადადება */
    .sentence.active {
        opacity: 1 !important;
        background: rgba(56, 189, 248, 0.1); /* ოდნავ განათებული ფონი */
    }
    .edit-mode-active {
        outline: 2px dashed var(--primary);
        outline-offset: -2px;
        background: rgba(56, 189, 248, 0.02);
    }




    /* --- CHAPTER NAVIGATION FOOTER --- */
    .chapter-nav-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 60px; /* დაშორება ტექსტიდან */
        padding-top: 20px;
        border-top: 1px solid var(--border);
        padding-bottom: 20px;
        gap: 15px;
    }

    .nav-chapter-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-muted);
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        flex: 1; /* რომ თანაბარი ზომის იყვნენ */
        justify-content: center;
    }

    .nav-chapter-btn:hover {
        background: rgba(56, 189, 248, 0.1); /* Primary ფერის მკრთალი ფონი */
        border-color: var(--primary);
        color: var(--text-main);
        transform: translateY(-2px);
    }

    .nav-chapter-btn.hidden {
        visibility: hidden; /* ვიყენებთ visibility-ს და არა display:none-ს, რომ ლეიაუტი არ აირიოს */
        pointer-events: none;
    }







    /* --- SIDEBAR --- */
    .sidebar {
        position: absolute;
        top: 0;
        left: 0;
        width: 320px;
        max-width: 85vw;
        height: 100%;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(10, 15, 30, 0.98) 100%);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 20px 0 50px rgba(0,0,0,0.5);
        transform: translateX(-100%);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: 100;
        display: flex;
        flex-direction: column;
    }
    .sidebar.open { transform: translateX(0); }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        color: var(--primary);
    }

    .toc-list { 
        flex: 1; 
        overflow-y: auto; 
        padding: 15px 10px; 
    }
    
    /* Custom Scrollbar for TOC */
    .toc-list::-webkit-scrollbar { width: 6px; }
    .toc-list::-webkit-scrollbar-track { background: transparent; }
    .toc-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .toc-list::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .toc-item {
        padding: 14px 18px;
        margin-bottom: 4px;
        cursor: pointer;
        border-radius: 12px;
        color: var(--text-muted);
        transition: all 0.3s ease;
        font-size: 0.95rem;
        line-height: 1.4;
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .toc-item:hover {
        background: rgba(255,255,255,0.05);
        color: var(--text-main);
        transform: translateX(4px);
    }
    .toc-item.active {
        background: rgba(56, 189, 248, 0.15);
        color: var(--primary);
        font-weight: 600;
        box-shadow: inset 3px 0 0 var(--primary);
    }
    
    /* ოდნავ ჩამქრალი განვლილი თავები */
    .toc-item.read-chapter {
        opacity: 0.4;
    }
    /* თუ განვლილი თავი თან აქტიურია (იშვიათია, მაგრამ მაინც), ფერი შეინარჩუნოს */
    .toc-item.read-chapter.active {
        opacity: 1;
        color: #38bdf8;
    }
    .sidebar-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 90;
        backdrop-filter: blur(2px);
        opacity: 1;
        transition: opacity 0.3s;
    }
    .sidebar-overlay.hidden { opacity: 0; pointer-events: none; }

    /* --- DROP ZONE --- */
    .drop-zone {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px dashed var(--border);
        border-radius: 12px;
        background: rgba(255,255,255,0.02);
        transition: all 0.2s;
    }
    .drop-zone.dragover { border-color: var(--primary); background: rgba(56, 189, 248, 0.1); transform: scale(0.99); }
    .drop-content { text-align: center; color: var(--text-muted); pointer-events: none; }
    .drop-content svg { color: var(--primary); margin-bottom: 15px; opacity: 0.8; }
    .drop-content h3 { margin: 0 0 8px 0; color: var(--text-main); font-weight: 400; }
    .sub-text { font-size: 0.85rem; opacity: 0.6; margin-top: 20px; }

    /* --- CONTROLS OVERLAY --- */
    .controls-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: linear-gradient(to top, var(--bg-dark) 40%, transparent);

        padding: 20px;
        /* ეს უზრუნველყოფს, რომ ღილაკები ოდნავ ზემოთ იყოს, თუ ტელეფონს ქვედა ბარი აქვს */
        padding-bottom: calc(20px + env(safe-area-inset-bottom));

        display: flex;
        justify-content: center;
        z-index: 20;
        pointer-events: none;
    }

    .controls {
        pointer-events: auto;
        display: flex;
        align-items: center;
        gap: 20px;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        padding: 12px 32px;
        border-radius: 40px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-top: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .controls:hover {
        background: rgba(15, 23, 42, 0.75);
        box-shadow: 0 25px 50px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    /* Control Buttons Override */
    #neural-app-root .ctrl-btn {
        background: transparent !important;
        border: none !important;
        color: var(--text-main) !important;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 50% !important;
        margin: 0 !important;
        position: relative;
        overflow: hidden;
    }

    #neural-app-root .ctrl-btn.sm.premium-btn {
        width: 44px !important;
        height: 44px !important;
        color: rgba(255, 255, 255, 0.7) !important;
        background: rgba(255, 255, 255, 0.05) !important;
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.05) !important;
    }
    #neural-app-root .ctrl-btn.sm.premium-btn:hover {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #fff !important;
        transform: scale(1.05);
    }
    #neural-app-root .ctrl-btn.sm.premium-btn:active {
        transform: scale(0.95);
    }

    /* Play Button */
    #neural-app-root .ctrl-btn.play.premium-play {
        width: 64px !important;
        height: 64px !important;
        background: linear-gradient(135deg, #38bdf8, #0284c7) !important;
        color: #fff !important;
        border: none !important;
        box-shadow: 0 10px 25px rgba(2, 132, 199, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.3) !important;
        z-index: 2;
    }
    #neural-app-root .ctrl-btn.play.premium-play:hover {
        transform: scale(1.08) translateY(-2px);
        background: linear-gradient(135deg, #7dd3fc, #0ea5e9) !important;
        box-shadow: 0 15px 35px rgba(2, 132, 199, 0.5), inset 0 2px 4px rgba(255, 255, 255, 0.4) !important;
    }
    #neural-app-root .ctrl-btn.play.premium-play:active {
        transform: scale(0.98);
    }

    /* Stop Button */
    #neural-app-root .ctrl-btn.stop.premium-btn {
        width: 44px !important;
        height: 44px !important;
        color: #fca5a5 !important;
        background: rgba(239, 68, 68, 0.1) !important;
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.05) !important;
    }
    #neural-app-root .ctrl-btn.stop.premium-btn:hover {
        background: rgba(239, 68, 68, 0.25) !important;
        color: #ef4444 !important;
        transform: scale(1.05);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2), inset 0 1px 1px rgba(255, 255, 255, 0.1) !important;
    }
    #neural-app-root .ctrl-btn.stop.premium-btn:active {
        transform: scale(0.95);
    }

    .hidden { display: none !important; }

    /* --- TTS LOADING INDICATOR --- */
    .tts-status-indicator {
        position: absolute;
        bottom: 115px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(56, 189, 248, 0.3);
        color: #7dd3fc;
        padding: 10px 18px;
        border-radius: 99px;
        font-size: 0.85rem;
        max-width: 90vw;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        z-index: 30;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    }
    .tts-spinner {
        width: 14px;
        height: 14px;
        border: 2px solid rgba(56, 189, 248, 0.25);
        border-top-color: #38bdf8;
        border-radius: 50%;
        animation: tts-spin 0.8s linear infinite;
        flex: 0 0 auto;
    }
    @keyframes tts-spin { to { transform: rotate(360deg); } }
    /* --- INFO MODAL --- */
    .info-modal-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.85) !important;
        backdrop-filter: blur(8px) !important;
        z-index: 2147483647 !important; /* მაქსიმალური შესაძლო რიცხვი */
        display: flex !important;
        justify-content: center;
        align-items: center;
        opacity: 1;
        transition: opacity 0.2s ease;
        visibility: visible;
    }

    /* როცა hidden კლასი აქვს */
    .info-modal-overlay.hidden {
        opacity: 0 !important;
        pointer-events: none !important;
        visibility: hidden !important;
    }

    .info-modal-content {
        background: #1e293b;
        border: 1px solid var(--border);
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
        padding: 24px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        transform: scale(1);
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .info-modal-content.premium-modal {
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    .info-modal-overlay.hidden .info-modal-content {
        transform: scale(0.95);
    }

    .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(255,255,255,0.1) !important;
        border-radius: 50% !important;
    }

    .modal-header {
        display: flex;
        gap: 20px;
        margin-bottom: 24px;
        align-items: flex-start;
    }

    #modal-book-cover {
        width: 100px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }

    .modal-title-group h2 {
        font-size: 1.2rem;
        margin: 0 0 5px 0;
        line-height: 1.3;
        color: var(--text-main);
    }

    .modal-title-group h3 {
        font-size: 0.95rem;
        margin: 0 0 10px 0;
        color: var(--primary);
        font-weight: 400;
    }

    .genre-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px; /* დაშორება ტეგებს შორის */
        margin-top: 5px;
    }

    .genre-tag {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        background: rgba(244, 114, 182, 0.1); /* ვარდისფერი ფონი */
        color: var(--accent);
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        border: 1px solid rgba(244, 114, 182, 0.2);
        white-space: nowrap;
    }

    .modal-body h4 {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 10px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 5px;
    }

    .desc-text {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #cbd5e1;
        white-space: pre-wrap; /* ინარჩუნებს აბზაცებს */
    }

    .publisher-info {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .publisher-info::before {
        content: '🏢'; /* პატარა შენობის აიკონი */
        opacity: 0.7;
        font-size: 0.9em;
    }
    /* Premium Modal Header */
    .premium-header {
        margin-bottom: 30px;
        flex-direction: column;
        align-items: center;
        border-bottom: none;
    }
    .premium-title {
        font-size: 2rem !important;
        font-weight: 700 !important;
        margin-bottom: 8px !important;
        background: linear-gradient(135deg, #f8fafc, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.5px;
    }
    .premium-subtitle {
        color: #94a3b8 !important;
        font-size: 1rem !important;
        font-weight: 400 !important;
        margin: 0 !important;
    }

    /* Premium Search */
    .sticky-search-wrapper {
        position: sticky;
        top: 0;
        z-index: 20;
        margin-bottom: 24px;
        padding: 10px 0 20px 0;
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.95) 60%, rgba(15, 23, 42, 0.5) 85%, transparent);
    }
    .search-icon-wrapper {
        position: absolute;
        left: 16px;
        top: 23px;
        color: #94a3b8;
        width: 18px;
        height: 18px;
        pointer-events: none;
    }
    .premium-search-input {
        width: 100%;
        padding: 14px 20px 14px 44px;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        background: rgba(0, 0, 0, 0.4) !important;
        color: #f8fafc !important;
        outline: none;
        font-size: 1rem;
        transition: all 0.3s;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
    }
    .premium-search-input:focus {
        border-color: var(--primary) !important;
        background: rgba(0, 0, 0, 0.6) !important;
        box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.1), inset 0 2px 4px rgba(0,0,0,0.2);
    }

    /* --- LIBRARY GRID STYLES --- */
    .library-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 24px;
        padding-top: 10px;
    }

    .book-card {
        background: rgba(30, 41, 59, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 12px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
    }

    .book-card:hover {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(56, 189, 248, 0.3);
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 20px rgba(56, 189, 248, 0.1);
    }

    .book-card-cover {
        width: 100%;
        aspect-ratio: 2/3;
        background: #0f172a !important;
        border-radius: 8px;
        margin-bottom: 16px;
        object-fit: cover;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-size: 2rem;
        border: 1px solid rgba(255,255,255,0.05) !important;
        box-shadow: 0 8px 16px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .book-card:hover .book-card-cover {
        box-shadow: 0 12px 24px rgba(0,0,0,0.5);
    }
    
    .book-card-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
    }

    .book-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: #f8fafc;
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    .book-card-author {
        color: #94a3b8;
        font-size: 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    /* Removed duplicate typography to avoid overriding premium card styles */

    .publisher-info.hidden {
        display: none;
    }

    /* პროგრესის ნიშნული ჰედერში */
    .progress-badge {
        font-size: 0.7rem;
        background: rgba(56, 189, 248, 0.2);
        color: var(--primary);
        padding: 2px 8px;
        border-radius: 10px;
        border: 1px solid rgba(56, 189, 248, 0.3);
        margin-top: 4px;
        align-self: flex-start;
        font-weight: 600;
    }

    /* პროგრესი ბიბლიოთეკის ბარათზე */
    .card-progress-overlay {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(15, 23, 42, 0.85);
        color: #38bdf8;
        font-weight: 700;
        font-size: 0.7rem;
        padding: 4px 8px;
        border-radius: 6px;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 5;
        border: 1px solid rgba(56, 189, 248, 0.3);
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    }

    .book-card { position: relative; } /* აუცილებელია პოზიციონირებისთვის */

    /* --- MOBILE RESPONSIVENESS --- */
    @media (max-width: 768px) {
        .header { padding: 12px 16px; }
        .logo { font-size: 0.85rem; }
        .settings-panel { padding: 15px; }
        .setting-row { flex-direction: column; gap: 15px; }
        .setting-group.half { width: 100%; }
        #content-area { padding: 15px; padding-bottom: 120px; font-size: 1rem; }
        .paragraph { margin-bottom: 16px; }
        .controls-overlay {
            padding: 15px;
            padding-bottom: calc(15px + env(safe-area-inset-bottom));
        }
        .controls {
            padding: 10px 20px;
            gap: 15px;
            width: 100%;
            justify-content: space-evenly;
        }
        .info-modal-content {
            width: 95%;
            margin: 20px auto;
            padding: 20px;
            max-height: 80vh;
        }
    }

</style>

<script>window.THEME_URI = '<?php echo get_template_directory_uri(); ?>';</script>
<script src="<?php echo get_template_directory_uri(); ?>/web-reader/scriptreader.js?v=<?php echo time(); ?>"></script>
