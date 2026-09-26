<?php
/* Template Name: Web Reader */

// Protect this custom app from global WordPress theme styles/scripts
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('zk-style');
    wp_dequeue_style('zk-fonts');
    wp_dequeue_script('zk-app');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
}, 999);

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_footer', 'zk_mobile_bottom_nav'); // Remove Mobile Bottom Nav from App
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <title>ReadRoad — Zurab Kostava</title>
    <meta name="description" content="EPUB and voice reading by Zurab Kostava">

    <!-- PWA & Mobile Web App Manifest -->
    <link rel="manifest" href="<?php echo get_template_directory_uri(); ?>/web-reader/manifest.json?v=<?php echo time(); ?>">
    <meta name="theme-color" content="#090d16">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ReadRoad">
    <meta name="application-name" content="ReadRoad">

    <!-- App Icons -->
    <link rel="icon" type="image/svg+xml" href="<?php echo get_template_directory_uri(); ?>/web-reader/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo get_template_directory_uri(); ?>/web-reader/icons/icon-192.png">
    <link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/web-reader/icons/icon-192.png">

    <script>
        // Proactive Speech Engine Wake-up for Mobile Browsers (Edge / Chrome on Android)
        if ('speechSynthesis' in window) {
            try {
                window.speechSynthesis.getVoices();
                if (window.speechSynthesis.paused) window.speechSynthesis.resume();
            } catch(e) {}
        }
    </script>

    <?php wp_head(); ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>
    <script src="https://js.puter.com/v2/"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #09090b; color: #f8fafc; overflow: hidden; height: 100vh;">

<div id="neural-app-root">

    <div class="glow-bg"></div>

    <div id="sidebar" class="sidebar collapsed">
        <div class="sidebar-header">
            <span>Table of Contents</span>
            <button id="close-sidebar-btn" class="icon-btn sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div id="toc-list" class="toc-list"></div>
    </div>
    <div id="sidebar-overlay" class="sidebar-overlay hidden"></div>

    <div class="main-pane">
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
                    <span class="logo-title">ReadRoad</span>
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
                <button id="pwa-install-btn" class="icon-btn hidden" title="Add to Phone (Install App)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span class="action-label">Install</span>
                </button>
                <button id="library-btn" class="icon-btn" title="Library">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    <span class="action-label">Library</span>
                </button>
                <input type="file" id="file-input" accept=".epub" style="display: none;">

                <button id="upload-btn" class="icon-btn" title="Open EPUB">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <span class="action-label">Open EPUB</span>
                </button>
                <button id="edit-btn" class="icon-btn" title="Edit / Paste Text">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span class="action-label">Edit Text</span>
                </button>
                <button id="settings-btn" class="icon-btn" title="Settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l-.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span class="action-label">Settings</span>
                </button>
            </div>
        </div>

        <div id="progress-container">
            <div id="progress-bar"></div>
        </div>

        <div id="reader-stage" class="reader-stage">
        <div id="reader-column" class="reader-column">
        <div id="content-area" class="content-area">
            <div id="welcome-hub" class="welcome-hub">
                <div class="hub-hero">
                    <div class="hub-badge">
                        <span class="pulse-dot"></span>
                        <span>AI Voice &amp; EPUB Reader</span>
                    </div>
                    <h1 class="hub-title">Ready to <span class="gradient-text">Listen &amp; Read?</span></h1>
                    <p class="hub-subtitle">Choose an option below to start your immersive reading experience</p>
                </div>

                <div class="hub-grid">
                    <button id="hub-library-btn" class="hub-card" type="button">
                        <div class="hub-card-icon icon-library">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                        </div>
                        <div class="hub-card-content">
                            <div class="hub-card-title">Library</div>
                            <div class="hub-card-desc">Browse &amp; resume saved books</div>
                        </div>
                        <div class="hub-card-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>

                    <button id="hub-open-epub-btn" class="hub-card" type="button">
                        <div class="hub-card-icon icon-epub">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <div class="hub-card-content">
                            <div class="hub-card-title">Open EPUB</div>
                            <div class="hub-card-desc">Choose or drop an .epub file</div>
                        </div>
                        <div class="hub-card-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>

                    <button id="hub-edit-text-btn" class="hub-card" type="button">
                        <div class="hub-card-icon icon-edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </div>
                        <div class="hub-card-content">
                            <div class="hub-card-title">Edit / Paste Text</div>
                            <div class="hub-card-desc">Type, paste or edit any text</div>
                        </div>
                        <div class="hub-card-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>

                    <button id="hub-settings-btn" class="hub-card" type="button">
                        <div class="hub-card-icon icon-settings">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l-.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </div>
                        <div class="hub-card-content">
                            <div class="hub-card-title">Settings</div>
                            <div class="hub-card-desc">Voices, speed &amp; pause tuning</div>
                        </div>
                        <div class="hub-card-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </button>
                </div>

                <div class="hub-drop-hint">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="12" y2="12"></line>
                        <line x1="15" y1="15" x2="12" y2="12"></line>
                    </svg>
                    <span>Or drag &amp; drop an EPUB file anywhere on this screen</span>
                </div>
            </div>
        </div>
        <button id="content-width-handle" class="content-width-handle" type="button" role="slider"
                aria-orientation="horizontal"
                aria-label="Resize reading area" aria-valuemin="24" aria-valuemax="100"
                aria-valuenow="100" title="Drag to resize the reading area. Double-click to reset.">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 7 4 12l5 5M15 7l5 5-5 5M4 12h16"></path>
            </svg>
        </button>
        </div>
        </div>

        <!-- Floating Edit Mode Bar with Prominent Save Button -->
        <div id="edit-mode-floating-bar" class="edit-mode-floating-bar hidden">
            <div class="edit-mode-pill">
                <div class="edit-mode-status">
                    <span class="edit-mode-indicator"></span>
                    <span class="edit-mode-label">Editing Mode</span>
                </div>
                <div class="edit-mode-actions">
                    <button id="big-save-text-btn" class="big-save-btn" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        <span>Save &amp; Read Text</span>
                    </button>
                    <button id="cancel-edit-text-btn" class="cancel-edit-btn" type="button" title="Cancel Editing">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        <span>Cancel</span>
                    </button>
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
            <div class="info-modal-content premium-modal">
                <button id="close-modal-btn" class="close-modal" title="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>

                <div class="modal-header">
                    <div id="modal-cover-container" class="modal-cover-container">
                        <img id="modal-book-cover" src="" alt="Cover" style="display:none;" onerror="this.style.display='none';">
                    </div>
                    <div class="modal-title-group">
                        <h2 id="modal-book-title">Book Title</h2>
                        <h3 id="modal-book-author">Author Name</h3>
                        <div id="modal-book-publisher" class="publisher-info hidden"></div>
                        <div id="modal-book-date" class="date-info hidden"></div>
                        <div id="modal-book-genre" class="genre-row"></div>
                    </div>
                </div>


                <div class="modal-body">
                    <h4>Description</h4>
                    <div id="modal-book-desc" class="desc-text">
                        No description available.
                    </div>
                </div>

                <div class="modal-footer-actions">

                </div>
            </div>
        </div>
    </div>
</div>

<div id="library-modal" class="library-fullscreen-overlay hidden">
    <div class="library-fullscreen-container">
        <div class="library-navbar">
            <div class="library-brand-block">
                <div class="library-brand-icon">📚</div>
                <div class="library-brand-text">
                    <h2>Library <span id="total-books-count" class="count-badge">...</span></h2>
                    <p class="library-subheading">Select a book to start reading</p>
                </div>
            </div>

            <div class="library-search-wrapper">
                <svg class="search-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="library-search-input" placeholder="Search by title or author..." autocomplete="off">
            </div>

            <div class="library-right-actions">
                <div class="library-sort-bar">
                    <span class="sort-prefix">Sort:</span>
                    <button type="button" class="library-sort-btn active" id="sort-title-btn" data-sort="title">
                        <span>🔤 Title (A-Z)</span>
                    </button>
                    <button type="button" class="library-sort-btn" id="sort-author-btn" data-sort="author">
                        <span>👤 Author</span>
                    </button>
                    <button type="button" class="library-sort-btn" id="sort-progress-btn" data-sort="progress">
                        <span>📊 Progress</span>
                    </button>
                </div>

                <button id="close-library-btn" class="library-exit-btn" title="Close Library (Esc)">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    <span>Close</span>
                </button>
            </div>
        </div>

        <div class="library-scroll-area">
            <div id="library-grid" class="library-grid"></div>
        </div>
    </div>
</div>

<div id="settings-modal" class="info-modal-overlay hidden">
    <div class="info-modal-content settings-modal-card">
        <button id="close-settings-btn" class="close-modal" title="Close Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>

        <div class="modal-header settings-header">
            <div class="settings-header-icon">⚙️</div>
            <div class="modal-title-group">
                <h2>Settings</h2>
                <h3>Configure voice, speech rate, and audio pauses</h3>
            </div>
        </div>

        <div class="settings-modal-body">
            <div id="dynamic-voice-settings"></div>
            <div class="settings-bottom-actions">
                <button id="refresh-voices-btn" class="settings-refresh-btn" title="Refresh available voices">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                    <span>Refresh Voices</span>
                </button>
            </div>
        </div>
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
        flex-direction: row; /* Desktop side-by-side layout */
        overflow: hidden; /* ეს კრძალავს მთლიანი აპლიკაციის სკროლს */
        overscroll-behavior: none; /* ეს კრძალავს "Rubber band" ეფექტს */
    }

    #neural-app-root * { box-sizing: border-box; }

    .main-pane {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        position: relative;
        height: 100%;
    }

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
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-main);
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        letter-spacing: 0.2px;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
        user-select: none;
        cursor: pointer;
    }
    .logo svg { color: var(--primary); flex-shrink: 0; }
    .logo-title {
        font-weight: 700 !important;
        color: #f8fafc !important;
        white-space: nowrap !important;
        display: inline-block !important;
    }
    .logo-pro-badge {
        font-size: 0.62rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.7px !important;
        padding: 1.5px 6px !important;
        border-radius: 6px !important;
        background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%) !important;
        color: #090d16 !important;
        line-height: 1.25 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 1px 6px rgba(56, 189, 248, 0.4) !important;
        flex-shrink: 0 !important;
        text-transform: uppercase;
        margin-left: 2px !important;
    }

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

    /* Specificity defense: .hidden and [hidden] MUST ALWAYS hide elements */
    #neural-app-root .hidden,
    #neural-app-root .icon-btn.hidden,
    #neural-app-root button.hidden,
    #neural-app-root [hidden] {
        display: none !important;
    }

    /* Sidebar toggle button (Burger Menu / Table of Contents): ONLY show when EPUB is opened */
    body:not(.is-epub) #sidebar-toggle-btn,
    body:not(.is-epub) #neural-app-root #sidebar-toggle-btn,
    body:not(.is-reading) #sidebar-toggle-btn,
    body:not(.is-reading) #neural-app-root #sidebar-toggle-btn {
        display: none !important;
    }

    body.is-epub #neural-app-root #sidebar-toggle-btn,
    body.is-reading.is-epub #neural-app-root #sidebar-toggle-btn {
        display: inline-flex !important;
    }

    /* Hide Edit Text and Open EPUB buttons when a book is open */
    body.is-reading #neural-app-root .header-actions #upload-btn,
    body.is-reading #neural-app-root .header-actions #edit-btn {
        display: none !important;
    }


    #neural-app-root #sidebar-toggle-btn {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        max-width: 38px !important;
        padding: 0 !important;
        border-radius: 10px !important;
        align-items: center !important;
        justify-content: center !important;
    }
    #neural-app-root #sidebar-toggle-btn svg {
        width: 19px !important;
        height: 19px !important;
    }

    #neural-app-root .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 8px 14px !important;
        border-radius: 20px !important;
        background: rgba(255, 255, 255, 0.04) !important;
        border: 1px solid var(--border) !important;
        color: var(--text-muted) !important;
        font-size: 0.84rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
    }
    #neural-app-root .icon-btn:hover {
        background: rgba(56, 189, 248, 0.12) !important;
        border-color: rgba(56, 189, 248, 0.4) !important;
        color: var(--text-main) !important;
        box-shadow: 0 4px 14px rgba(56, 189, 248, 0.18);
    }
    #neural-app-root .icon-btn.active,
    #neural-app-root .icon-btn:focus-visible {
        background: rgba(56, 189, 248, 0.2) !important;
        border-color: var(--primary) !important;
        color: var(--primary) !important;
    }
    #neural-app-root .icon-btn.sm {
        padding: 0 !important;
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
    }
    #neural-app-root .icon-btn.close-modal {
        padding: 0 !important;
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
    }
    #neural-app-root .icon-btn svg {
        width: 17px !important;
        height: 17px !important;
        fill: none !important;
        stroke: currentColor !important;
        flex-shrink: 0;
    }
    .action-label {
        font-weight: 500;
        font-size: 0.82rem;
    }
    /* In Active Reading Mode: enhance the top-right toolbar visibility */
    body.is-reading #neural-app-root .header-actions .icon-btn {
        background: rgba(30, 41, 59, 0.8) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        color: #e2e8f0 !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    }
    body.is-reading #neural-app-root .header-actions .icon-btn:hover {
        background: rgba(56, 189, 248, 0.18) !important;
        border-color: #38bdf8 !important;
        color: #38bdf8 !important;
        transform: translateY(-1px);
    }

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
    
    body:not(.is-reading) #progress-container {
        display: none !important;
    }
    body:not(.is-epub) #sidebar,
    body:not(.is-epub) #sidebar-overlay {
        display: none !important;
    }

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

    .reader-stage {
        flex: 1;
        min-height: 0;
        width: 100%;
        display: flex;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .reader-column {
        width: 100%;
        max-width: 100%;
        min-width: min(520px, 100%);
        height: 100%;
        position: relative;
        transition: width 180ms ease;
    }

    .reader-column.is-resizing {
        transition: none;
        user-select: none;
    }

    body:not(.is-reading) .reader-column {
        width: 100% !important;
    }

    body:not(.is-reading) #content-width-handle {
        display: none !important;
    }

    #neural-app-root .content-width-handle {
        position: absolute !important;
        top: 50% !important;
        right: 7px !important;
        transform: translateY(-50%) !important;
        width: 30px !important;
        min-width: 30px !important;
        height: 76px !important;
        min-height: 76px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        border: 1px solid rgba(148, 163, 184, 0.35) !important;
        border-radius: 999px !important;
        background: rgba(15, 23, 42, 0.88) !important;
        color: #cbd5e1 !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(56, 189, 248, 0.06) !important;
        cursor: ew-resize !important;
        touch-action: none;
        opacity: 0.62;
        z-index: 30;
        transition: opacity 160ms ease, border-color 160ms ease, color 160ms ease !important;
    }

    #neural-app-root .content-width-handle:hover,
    #neural-app-root .content-width-handle:focus-visible,
    .reader-column.is-resizing #content-width-handle {
        opacity: 1;
        color: #f8fafc !important;
        border-color: rgba(56, 189, 248, 0.72) !important;
        outline: none;
    }

    #neural-app-root .content-width-handle svg {
        width: 18px;
        height: 18px;
        pointer-events: none;
    }

    #neural-app-root .content-width-handle::after {
        content: attr(data-width);
        position: absolute;
        right: 38px;
        top: 50%;
        transform: translateY(-50%);
        padding: 5px 8px;
        border: 1px solid rgba(148, 163, 184, 0.24);
        border-radius: 7px;
        background: rgba(9, 9, 11, 0.92);
        color: #e2e8f0;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 140ms ease;
    }

    #neural-app-root .content-width-handle:hover::after,
    #neural-app-root .content-width-handle:focus-visible::after,
    .reader-column.is-resizing #content-width-handle::after {
        opacity: 1;
    }

    body.reader-width-resizing,
    body.reader-width-resizing * {
        cursor: ew-resize !important;
    }

    .content-area {
        flex: 1;
        min-height: 0;
        width: 100%;
        height: 100%;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
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
        transition: opacity 0.25s ease, background-color 0.2s ease, color 0.2s ease;
    }
    /* წაკითხული წინადადება - მსუბუქი, მკაფიო და GPU-სთვის 100% თავისუფალი (Blur-ის გარეშე) */
    .sentence.read {
        opacity: 0.35 !important;
        color: #64748b !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    /* აქტიური წინადადება */
    .sentence.active {
        opacity: 1 !important;
        color: var(--text-main) !important;
        background: rgba(56, 189, 248, 0.12) !important;
        box-shadow: inset 3px 0 0 0 var(--primary) !important;
        border-radius: 4px;
    }

    /* EPUB Headers Preservation CSS */
    .epub-header {
        font-weight: 700;
        display: inline;
        line-height: 1.4;
        color: #fff;
    }
    .epub-header-h1 { font-size: 2em; color: var(--primary); }
    .epub-header-h2 { font-size: 1.6em; }
    .epub-header-h3 { font-size: 1.4em; color: #e2e8f0; }
    .epub-header-h4 { font-size: 1.2em; color: #cbd5e1; }
    .epub-header-h5 { font-size: 1.1em; color: #cbd5e1; }
    .epub-header-h6 { font-size: 1.05em; color: #cbd5e1; text-transform: uppercase; }

    .sentence:hover { color: var(--text-main); }

    .word.active {
        color: #fff;
        background: var(--accent);
        border-radius: 4px;
        box-shadow: 0 0 0 2px var(--accent), 0 0 10px var(--accent-glow);
        text-shadow: 0 0 1px white;
        z-index: 2;
        position: relative;
    }

    /* --- FLOATING EDIT MODE BAR --- */
    .edit-mode-floating-bar {
        position: fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 60000;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        pointer-events: auto;
        max-width: 94vw;
        width: auto;
    }

    #neural-app-root .edit-mode-floating-bar.hidden {
        display: none !important;
    }

    .edit-mode-pill {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 10px 8px 16px;
        background: rgba(15, 23, 42, 0.94);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(56, 189, 248, 0.35);
        border-radius: 9999px;
        box-shadow: 0 12px 35px -5px rgba(0, 0, 0, 0.75), 0 0 25px rgba(56, 189, 248, 0.25);
        animation: slideUpEditBar 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUpEditBar {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .edit-mode-status {
        display: flex;
        align-items: center;
        gap: 8px;
        padding-right: 4px;
    }

    .edit-mode-indicator {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #38bdf8;
        box-shadow: 0 0 10px #38bdf8;
        animation: pulseEditDot 1.5s infinite;
    }

    @keyframes pulseEditDot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(1.3); }
    }

    .edit-mode-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #e2e8f0;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .edit-mode-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #neural-app-root .big-save-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%) !important;
        color: #090d16 !important;
        border: none !important;
        border-radius: 9999px !important;
        padding: 10px 22px !important;
        font-size: 0.92rem !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        box-shadow: 0 4px 14px rgba(56, 189, 248, 0.45) !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
        white-space: nowrap !important;
    }

    #neural-app-root .big-save-btn svg {
        width: 18px !important;
        height: 18px !important;
        color: #090d16 !important;
    }

    #neural-app-root .big-save-btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(56, 189, 248, 0.65) !important;
        filter: brightness(1.08) !important;
    }

    #neural-app-root .big-save-btn:active {
        transform: translateY(0) !important;
        box-shadow: 0 2px 8px rgba(56, 189, 248, 0.4) !important;
    }

    #neural-app-root .cancel-edit-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        background: rgba(255, 255, 255, 0.08) !important;
        color: #94a3b8 !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 9999px !important;
        padding: 10px 16px !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        white-space: nowrap !important;
    }

    #neural-app-root .cancel-edit-btn svg {
        width: 15px !important;
        height: 15px !important;
        color: currentColor !important;
    }

    #neural-app-root .cancel-edit-btn:hover {
        background: rgba(239, 68, 68, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.35) !important;
        color: #f87171 !important;
    }

    .edit-mode-active {
        outline: 2px dashed rgba(56, 189, 248, 0.6) !important;
        outline-offset: 4px;
        background: rgba(56, 189, 248, 0.03) !important;
        border-radius: 8px;
        min-height: 250px;
        padding-bottom: 90px !important;
    }

    .edit-mode-active:empty:before {
        content: "Type or paste your text here (ჩაწერეთ ან ჩასვით ტექსტი)...";
        color: rgba(148, 163, 184, 0.5);
        font-style: italic;
        pointer-events: none;
        display: block;
    }

    body.is-editing-text .controls-overlay {
        display: none !important;
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
        position: relative; /* Not absolute on desktop */
        width: 320px;
        max-width: 85vw;
        height: 100%;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(10, 15, 30, 0.98) 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        z-index: 100;
        display: flex;
        flex-direction: column;
        transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        flex-shrink: 0;
    }
    
    @media (min-width: 769px) {
        .sidebar.collapsed {
            width: 0;
            border-right: none;
            overflow: hidden;
            /* No translateX needed because width: 0 hides it smoothly and reflows layout */
        }
        .sidebar-overlay {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        #neural-app-root {
            flex-direction: column;
        }
        .main-pane {
            width: 100%;
        }
        .sidebar {
            position: absolute;
            top: 0;
            left: 0;
            transform: translateX(-100%);
            box-shadow: 20px 0 50px rgba(0,0,0,0.5);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .sidebar.open { transform: translateX(0); }
        .sidebar-overlay {
            display: block;
        }
    }

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

    /* --- WELCOME HUB (Center Interface) --- */
    .welcome-hub {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100%;
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
        text-align: center;
        animation: hubFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        z-index: 2;
    }

    @keyframes hubFadeIn {
        from { opacity: 0; transform: translateY(14px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .hub-hero {
        margin-bottom: 34px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .hub-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 16px;
        border-radius: 20px;
        background: rgba(56, 189, 248, 0.08);
        border: 1px solid rgba(56, 189, 248, 0.25);
        color: #38bdf8;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
        box-shadow: 0 0 20px rgba(56, 189, 248, 0.15);
    }

    .pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #38bdf8;
        box-shadow: 0 0 10px #38bdf8;
        animation: pulseGlow 2s infinite ease-in-out;
    }

    @keyframes pulseGlow {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.85); }
    }

    .hub-title {
        font-size: 2.2rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0 0 12px 0;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }

    .gradient-text {
        background: linear-gradient(135deg, #38bdf8 0%, #818cf8 50%, #f472b6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hub-subtitle {
        font-size: 1rem;
        color: #94a3b8;
        margin: 0;
        max-width: 520px;
        line-height: 1.5;
    }

    .hub-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
        width: 100%;
        max-width: 780px;
        margin-bottom: 26px;
    }

    #neural-app-root .hub-card {
        position: relative;
        display: flex !important;
        align-items: center !important;
        text-align: left !important;
        padding: 20px 22px !important;
        background: rgba(18, 24, 38, 0.65) !important;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 16px !important;
        cursor: pointer !important;
        transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1) !important;
        overflow: hidden;
        gap: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    }

    #neural-app-root .hub-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
    }

    #neural-app-root .hub-card:hover {
        transform: translateY(-4px);
        background: rgba(28, 38, 58, 0.85) !important;
        border-color: rgba(56, 189, 248, 0.4) !important;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.4), 0 0 25px rgba(56, 189, 248, 0.15);
    }

    .hub-card-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
    }

    #neural-app-root .hub-card:hover .hub-card-icon {
        transform: scale(1.08) rotate(2deg);
    }

    .hub-card-icon svg {
        width: 26px;
        height: 26px;
    }

    .icon-library {
        background: linear-gradient(135deg, rgba(56, 189, 248, 0.2), rgba(99, 102, 241, 0.25));
        border: 1px solid rgba(56, 189, 248, 0.35);
        color: #38bdf8;
    }

    .icon-epub {
        background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(236, 72, 153, 0.25));
        border: 1px solid rgba(236, 72, 153, 0.35);
        color: #f472b6;
    }

    .icon-edit {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(52, 211, 153, 0.25));
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #34d399;
    }

    .icon-settings {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(251, 191, 36, 0.25));
        border: 1px solid rgba(245, 158, 11, 0.35);
        color: #fbbf24;
    }

    .hub-card-content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .hub-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: -0.2px;
    }

    .hub-card-desc {
        font-size: 0.82rem;
        color: #94a3b8;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hub-card-tag {
        align-self: flex-start;
        font-size: 0.7rem;
        font-weight: 600;
        color: #cbd5e1;
        background: rgba(255, 255, 255, 0.06);
        padding: 2px 8px;
        border-radius: 6px;
        margin-top: 4px;
    }

    .hub-card-arrow {
        color: #64748b;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }

    .hub-card-arrow svg {
        width: 20px;
        height: 20px;
    }

    #neural-app-root .hub-card:hover .hub-card-arrow {
        color: #38bdf8;
        transform: translateX(4px);
    }

    .hub-drop-hint {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 0.85rem;
        color: #64748b;
        padding: 10px 20px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        transition: all 0.25s;
    }

    .content-area.dragover .welcome-hub {
        border: 2px dashed #38bdf8;
        background: rgba(56, 189, 248, 0.08);
        border-radius: 20px;
    }

    /* Legacy drop-zone fallback */
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

        /* Default: hidden until book or text is loaded */
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    body.is-reading .controls-overlay {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
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
        display: flex !important;
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
        box-shadow: 0 25px 50px rgba(0,0,0,0.6);
        transform: translateY(0) scale(1);
        opacity: 1;
        transition: transform 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), opacity 0.4s ease;
    }
    
    .info-modal-content.premium-modal {
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    .info-modal-overlay.hidden .info-modal-content {
        transform: translateY(40px) scale(0.95);
        opacity: 0;
    }

    .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 34px !important;
        height: 34px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(255,255,255,0.1) !important;
        border-radius: 50% !important;
        border: none !important;
        color: white !important;
        cursor: pointer !important;
        transition: background 0.2s ease !important;
    }
    .close-modal:hover {
        background: rgba(255,255,255,0.2) !important;
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

    .modal-title-group {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding-right: 28px; /* Prevent overlap with X close button */
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

    .publisher-info, .date-info {
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

    .date-info::before {
        content: '📅';
        opacity: 0.7;
        font-size: 0.9em;
    }

    /* Modal Reading Status & Mark Read Toggle */

    .modal-cover-container {
        width: 100px;
        min-width: 100px;
        aspect-ratio: 1 / 1.48;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: #090b11;
        flex-shrink: 0;
    }

    .modal-cover-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .modal-footer-actions {
        margin-top: 18px;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        justify-content: flex-end;
    }

    .modal-action-read-btn {
        all: unset !important;
        box-sizing: border-box !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding: 10px 22px !important;
        background: rgba(56, 189, 248, 0.15) !important;
        border: 1px solid rgba(56, 189, 248, 0.35) !important;
        border-radius: 10px !important;
        color: #38bdf8 !important;
        font-weight: 600 !important;
        font-size: 0.88rem !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }

    .modal-action-read-btn:hover {
        background: #38bdf8 !important;
        color: #090b11 !important;
        box-shadow: 0 0 16px rgba(56, 189, 248, 0.4) !important;
    }

    /* Completed Checkmark Badge for Book Cards */
    .card-progress-overlay.card-progress-completed {
        background: rgba(6, 78, 59, 0.92) !important;
        border-color: rgba(52, 211, 153, 0.6) !important;
        color: #a7f3d0 !important;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45) !important;
        padding: 3px 6px !important;
        gap: 6px !important;
    }

    .completed-check-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #10b981;
        color: #022c22;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
    }

    /* --- FULLSCREEN LIBRARY --- */
    .library-fullscreen-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        height: 100dvh !important;
        z-index: 100050 !important;
        background: rgba(9, 11, 17, 0.98) !important;
        backdrop-filter: blur(25px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: fadeIn 0.25s ease-out;
    }

    .library-fullscreen-overlay.hidden {
        display: none !important;
    }

    .library-fullscreen-container {
        width: 100%;
        height: 100%;
        max-width: 1560px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        padding: 24px 32px 30px 32px;
        overflow: hidden;
        box-sizing: border-box;
    }

    /* --- PREMIUM LIBRARY TOP NAVBAR --- */
    .library-navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 16px 24px;
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        flex-shrink: 0;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }

    .library-brand-block {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .library-brand-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(56, 189, 248, 0.12);
        border: 1px solid rgba(56, 189, 248, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 0 16px rgba(56, 189, 248, 0.15);
        flex-shrink: 0;
    }

    .library-brand-text h2 {
        font-size: 1.35rem !important;
        font-weight: 700 !important;
        margin: 0 !important;
        color: #f8fafc !important;
        display: flex;
        align-items: center;
        gap: 10px;
        line-height: 1.2 !important;
    }

    .library-subheading {
        font-size: 0.8rem !important;
        color: #94a3b8 !important;
        margin: 3px 0 0 0 !important;
        line-height: 1 !important;
    }

    .count-badge {
        display: inline-flex !important;
        align-items: center !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        padding: 2px 10px !important;
        border-radius: 99px !important;
        background: rgba(56, 189, 248, 0.15) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(56, 189, 248, 0.3) !important;
    }

    .library-search-wrapper {
        position: relative;
        flex: 1;
        max-width: 460px;
        min-width: 240px;
    }

    .library-search-wrapper .search-svg {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        pointer-events: none;
        width: 18px;
        height: 18px;
        transition: color 0.2s ease;
    }

    .library-fullscreen-overlay .library-search-wrapper input {
        all: unset !important;
        display: block !important;
        box-sizing: border-box !important;
        width: 100% !important;
        height: 42px !important;
        padding: 0 18px 0 42px !important;
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 24px !important;
        color: #f8fafc !important;
        font-size: 0.88rem !important;
        font-family: inherit !important;
        outline: none !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .library-fullscreen-overlay .library-search-wrapper input:focus {
        border-color: #38bdf8 !important;
        background: rgba(56, 189, 248, 0.07) !important;
        box-shadow: 0 0 20px rgba(56, 189, 248, 0.25) !important;
    }

    .library-fullscreen-overlay .library-search-wrapper input:focus ~ .search-svg {
        color: #38bdf8;
    }

    .library-right-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-shrink: 0;
    }

    .library-sort-bar {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(255, 255, 255, 0.04);
        padding: 4px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sort-prefix {
        font-size: 0.72rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 0 6px 0 10px;
    }

    .library-fullscreen-overlay .library-sort-btn {
        all: unset !important;
        box-sizing: border-box !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        padding: 6px 14px !important;
        height: 32px !important;
        background: transparent !important;
        border: 1px solid transparent !important;
        border-radius: 20px !important;
        color: #94a3b8 !important;
        font-size: 0.8rem !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        white-space: nowrap !important;
        user-select: none !important;
    }

    .library-fullscreen-overlay .library-sort-btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #f8fafc !important;
    }

    .library-fullscreen-overlay .library-sort-btn.active {
        background: rgba(56, 189, 248, 0.2) !important;
        border-color: #38bdf8 !important;
        color: #38bdf8 !important;
        font-weight: 600 !important;
        box-shadow: 0 0 14px rgba(56, 189, 248, 0.3) !important;
    }

    .library-fullscreen-overlay .library-exit-btn {
        all: unset !important;
        box-sizing: border-box !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding: 0 18px !important;
        height: 40px !important;
        background: rgba(239, 68, 68, 0.12) !important;
        border: 1px solid rgba(239, 68, 68, 0.3) !important;
        border-radius: 20px !important;
        color: #f87171 !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        white-space: nowrap !important;
        user-select: none !important;
    }

    .library-fullscreen-overlay .library-exit-btn:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 18px rgba(239, 68, 68, 0.45) !important;
    }

    .library-scroll-area {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 10px 8px 50px 8px;
    }

    .library-scroll-area::-webkit-scrollbar { width: 8px; }
    .library-scroll-area::-webkit-scrollbar-track { background: transparent; }
    .library-scroll-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
    .library-scroll-area::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    /* --- SETTINGS MODAL STYLES --- */
    .settings-modal-card {
        max-width: 540px !important;
        width: 92% !important;
        max-height: 88vh !important;
        overflow-y: auto !important;
        background: linear-gradient(170deg, rgba(15, 23, 42, 0.98) 0%, rgba(10, 15, 29, 0.98) 100%) !important;
        border: 1px solid rgba(56, 189, 248, 0.22) !important;
        border-radius: 22px !important;
        padding: 26px 24px !important;
        box-shadow: 0 28px 70px rgba(0, 0, 0, 0.7), 0 0 35px rgba(56, 189, 248, 0.12) !important;
        backdrop-filter: blur(25px) !important;
        -webkit-backdrop-filter: blur(25px) !important;
        position: relative !important;
    }

    .settings-modal-card::-webkit-scrollbar { width: 6px; }
    .settings-modal-card::-webkit-scrollbar-track { background: transparent; }
    .settings-modal-card::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 3px; }
    .settings-modal-card::-webkit-scrollbar-thumb:hover { background: rgba(56, 189, 248, 0.3); }

    /* Close Button */
    #neural-app-root #close-settings-btn,
    .settings-modal-card .close-modal {
        all: unset !important;
        box-sizing: border-box !important;
        position: absolute !important;
        top: 20px !important;
        right: 20px !important;
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #94a3b8 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        transition: all 0.25s ease !important;
        z-index: 10 !important;
    }

    #neural-app-root #close-settings-btn:hover,
    .settings-modal-card .close-modal:hover {
        background: rgba(239, 68, 68, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.5) !important;
        color: #ef4444 !important;
        transform: rotate(90deg) !important;
        box-shadow: 0 0 16px rgba(239, 68, 68, 0.3) !important;
    }

    #neural-app-root #close-settings-btn svg,
    .settings-modal-card .close-modal svg {
        width: 18px !important;
        height: 18px !important;
        stroke: currentColor !important;
        stroke-width: 2.2 !important;
        display: block !important;
    }

    /* Header */
    .settings-header {
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        margin-bottom: 22px !important;
        padding-bottom: 18px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    .settings-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(56, 189, 248, 0.1);
        border: 1px solid rgba(56, 189, 248, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 16px rgba(56, 189, 248, 0.15);
        flex-shrink: 0;
    }

    .settings-header .modal-title-group h2 {
        font-size: 1.35rem !important;
        font-weight: 700 !important;
        color: #f8fafc !important;
        margin: 0 0 3px 0 !important;
        letter-spacing: -0.3px !important;
    }

    .settings-header .modal-title-group h3 {
        font-size: 0.82rem !important;
        color: #94a3b8 !important;
        margin: 0 !important;
        font-weight: 400 !important;
    }

    /* Section Cards */
    .settings-card-section {
        background: rgba(30, 41, 59, 0.3) !important;
        border: 1px solid rgba(255, 255, 255, 0.07) !important;
        border-radius: 16px !important;
        padding: 18px !important;
        margin-bottom: 16px !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05) !important;
    }

    .settings-section-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .settings-section-icon {
        font-size: 1rem;
    }

    .settings-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #38bdf8;
    }

    /* Form Groups & Labels */
    .settings-modal-card .setting-group {
        margin-bottom: 16px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 6px !important;
    }

    .settings-modal-card .setting-group:last-child {
        margin-bottom: 0 !important;
    }

    .settings-modal-card .setting-group > label {
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        color: #cbd5e1 !important;
        letter-spacing: 0.3px !important;
        margin-bottom: 2px !important;
    }

    /* Custom Dropdown (Select) */
    .settings-modal-card .select-wrapper {
        position: relative !important;
        width: 100% !important;
    }

    .settings-modal-card .settings-select {
        appearance: none !important;
        -webkit-appearance: none !important;
        width: 100% !important;
        background: rgba(15, 23, 42, 0.85) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        color: #f8fafc !important;
        border-radius: 10px !important;
        padding: 10px 40px 10px 14px !important;
        font-size: 0.88rem !important;
        outline: none !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25) !important;
    }

    .settings-modal-card .settings-select:hover {
        border-color: rgba(56, 189, 248, 0.4) !important;
    }

    .settings-modal-card .settings-select:focus {
        border-color: #38bdf8 !important;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.18) !important;
    }

    .settings-modal-card .settings-select option,
    .settings-modal-card .settings-select optgroup {
        background: #0f172a !important;
        color: #f8fafc !important;
        padding: 8px !important;
    }

    .settings-modal-card .select-chevron {
        position: absolute !important;
        right: 14px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        pointer-events: none !important;
        color: #94a3b8 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* Sliders */
    .slider-header-row {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 6px !important;
    }

    .slider-val-badge {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #38bdf8 !important;
        background: rgba(56, 189, 248, 0.12) !important;
        border: 1px solid rgba(56, 189, 248, 0.28) !important;
        padding: 2px 10px !important;
        border-radius: 99px !important;
        letter-spacing: 0.5px !important;
        min-width: 44px !important;
        text-align: center !important;
    }

    .settings-modal-card input[type="range"].settings-slider {
        -webkit-appearance: none !important;
        appearance: none !important;
        width: 100% !important;
        height: 6px !important;
        background: rgba(255, 255, 255, 0.12) !important;
        border-radius: 99px !important;
        outline: none !important;
        cursor: pointer !important;
        transition: background 0.2s ease !important;
        margin: 8px 0 !important;
    }

    .settings-modal-card input[type="range"].settings-slider::-webkit-slider-thumb {
        -webkit-appearance: none !important;
        appearance: none !important;
        width: 18px !important;
        height: 18px !important;
        border-radius: 50% !important;
        background: #38bdf8 !important;
        border: 2.5px solid #ffffff !important;
        box-shadow: 0 0 10px rgba(56, 189, 248, 0.7) !important;
        cursor: pointer !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease !important;
    }

    .settings-modal-card input[type="range"].settings-slider::-webkit-slider-thumb:hover {
        transform: scale(1.2) !important;
        box-shadow: 0 0 16px rgba(56, 189, 248, 0.9) !important;
    }

    .settings-modal-card input[type="range"].settings-slider::-moz-range-thumb {
        width: 18px !important;
        height: 18px !important;
        border-radius: 50% !important;
        background: #38bdf8 !important;
        border: 2.5px solid #ffffff !important;
        box-shadow: 0 0 10px rgba(56, 189, 248, 0.7) !important;
        cursor: pointer !important;
    }

    /* Toggle Switch for Parentheses */
    .settings-modal-card .settings-toggle-label {
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        cursor: pointer !important;
        user-select: none !important;
        padding: 10px 12px !important;
        border-radius: 12px !important;
        background: rgba(255, 255, 255, 0.02) !important;
        border: 1px solid rgba(255, 255, 255, 0.06) !important;
        transition: all 0.2s ease !important;
    }

    .settings-modal-card .settings-toggle-label:hover {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    .settings-modal-card .settings-toggle-checkbox {
        position: absolute !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .settings-modal-card .settings-toggle-custom {
        width: 42px !important;
        height: 24px !important;
        background: rgba(255, 255, 255, 0.15) !important;
        border-radius: 99px !important;
        position: relative !important;
        transition: all 0.25s ease !important;
        flex-shrink: 0 !important;
    }

    .settings-modal-card .settings-toggle-custom::after {
        content: '' !important;
        position: absolute !important;
        top: 3px !important;
        left: 3px !important;
        width: 18px !important;
        height: 18px !important;
        border-radius: 50% !important;
        background: #ffffff !important;
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3) !important;
    }

    .settings-modal-card .settings-toggle-checkbox:checked + .settings-toggle-custom {
        background: #38bdf8 !important;
        box-shadow: 0 0 12px rgba(56, 189, 248, 0.4) !important;
    }

    .settings-modal-card .settings-toggle-checkbox:checked + .settings-toggle-custom::after {
        transform: translateX(18px) !important;
    }

    .toggle-text-block {
        display: flex !important;
        flex-direction: column !important;
        gap: 2px !important;
    }

    .toggle-title {
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        color: #f1f5f9 !important;
    }

    .toggle-desc {
        font-size: 0.74rem !important;
        color: #94a3b8 !important;
        line-height: 1.3 !important;
    }

    /* Download Voice Button */
    .settings-download-btn {
        all: unset !important;
        box-sizing: border-box !important;
        width: 100% !important;
        margin-top: 10px !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        padding: 10px !important;
        border-radius: 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(56, 189, 248, 0.1) !important;
        border: 1px solid rgba(56, 189, 248, 0.3) !important;
        color: #38bdf8 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }

    .settings-download-btn:hover {
        background: rgba(56, 189, 248, 0.2) !important;
        border-color: #38bdf8 !important;
        box-shadow: 0 0 16px rgba(56, 189, 248, 0.25) !important;
    }

    /* Bottom Actions & Refresh Voices */
    .settings-bottom-actions {
        margin-top: 20px !important;
        padding-top: 16px !important;
        border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    #neural-app-root .settings-refresh-btn,
    .settings-modal-card .settings-refresh-btn {
        all: unset !important;
        box-sizing: border-box !important;
        width: 100% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 9px !important;
        padding: 12px 20px !important;
        background: rgba(30, 41, 59, 0.5) !important;
        border: 1px solid rgba(56, 189, 248, 0.28) !important;
        border-radius: 12px !important;
        color: #38bdf8 !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
    }

    #neural-app-root .settings-refresh-btn:hover,
    .settings-modal-card .settings-refresh-btn:hover {
        background: rgba(56, 189, 248, 0.15) !important;
        border-color: #38bdf8 !important;
        color: #ffffff !important;
        box-shadow: 0 0 20px rgba(56, 189, 248, 0.3) !important;
        transform: translateY(-1px) !important;
    }

    #neural-app-root .settings-refresh-btn svg,
    .settings-modal-card .settings-refresh-btn svg {
        width: 16px !important;
        height: 16px !important;
        stroke: currentColor !important;
        transition: transform 0.4s ease !important;
    }

    #neural-app-root .settings-refresh-btn:hover svg,
    .settings-modal-card .settings-refresh-btn:hover svg {
        transform: rotate(180deg) !important;
    }

    /* --- LIBRARY GRID & COVERS --- */
    .library-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
        gap: 24px;
        padding-top: 4px;
    }

    .book-card {
        background: rgba(30, 41, 59, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 14px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        content-visibility: auto;
        contain-intrinsic-size: 160px 280px;
    }

    .book-card:hover {
        background: rgba(30, 41, 59, 0.75);
        border-color: rgba(56, 189, 248, 0.35);
        transform: translateY(-5px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(56, 189, 248, 0.12);
    }

    .book-cover-container {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1.48;
        border-radius: 9px;
        margin-bottom: 12px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #090b11;
    }

    .book-real-cover {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: 2;
        border-radius: 8px;
        display: block;
    }

    /* --- PROCEDURAL BOOK COVERS --- */
    .procedural-book-cover {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 14px 10px 10px 14px;
        box-sizing: border-box;
        border-radius: 8px;
        user-select: none;
        z-index: 1;
        overflow: hidden;
        text-align: center;
        box-shadow: inset 6px 0 12px -2px rgba(0, 0, 0, 0.7), inset -1px 0 2px rgba(255, 255, 255, 0.12);
    }

    .cover-spine-highlight {
        position: absolute;
        left: 7px;
        top: 0;
        bottom: 0;
        width: 1px;
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 1px 0 2px rgba(0, 0, 0, 0.4);
        pointer-events: none;
    }

    .cover-top-accent {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .cover-emblem {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.18);
        font-size: 0.95rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .cover-center-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 6px 2px;
        min-height: 0;
    }

    .cover-book-title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #f8fafc;
        line-height: 1.25;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.85);
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
        letter-spacing: -0.2px;
    }

    .cover-accent-divider {
        width: 24px;
        height: 2px;
        background: rgba(255, 255, 255, 0.35);
        margin: 6px auto;
        border-radius: 2px;
    }

    .cover-book-author {
        font-size: 0.62rem;
        color: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: 500;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
    }

    .cover-bottom-tag {
        font-size: 0.5rem;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.45);
        font-weight: 600;
    }

    /* Procedural Palette Gradients */
    .procedural-palette-0 { background: linear-gradient(150deg, #0b192c 0%, #172554 50%, #1e40af 100%); }
    .procedural-palette-1 { background: linear-gradient(150deg, #022c22 0%, #064e3b 50%, #059669 100%); }
    .procedural-palette-2 { background: linear-gradient(150deg, #2e1065 0%, #581c87 50%, #7e22ce 100%); }
    .procedural-palette-3 { background: linear-gradient(150deg, #4c0519 0%, #881337 50%, #be123c 100%); }
    .procedural-palette-4 { background: linear-gradient(150deg, #451a03 0%, #78350f 50%, #b45309 100%); }
    .procedural-palette-5 { background: linear-gradient(150deg, #082f49 0%, #075985 50%, #0284c7 100%); }
    .procedural-palette-6 { background: linear-gradient(150deg, #3b0764 0%, #6b21a8 50%, #9333ea 100%); }
    .procedural-palette-7 { background: linear-gradient(150deg, #18181b 0%, #27272a 50%, #3f3f46 100%); }

    .book-card-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #f8fafc;
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
        width: 100%;
    }

    .book-card-author {
        color: #94a3b8;
        font-size: 0.78rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        width: 100%;
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
    .progress-badge.completed {
        background: rgba(16, 185, 129, 0.2) !important;
        color: #6ee7b7 !important;
        border-color: rgba(16, 185, 129, 0.4) !important;
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
        .header {
            padding: 8px 12px !important;
            min-height: 54px !important;
            height: 54px !important;
            gap: 8px !important;
            background: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.45) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }

        .header-left {
            flex: 0 0 auto !important;
            min-width: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        body.is-reading .header-left {
            flex: 1 1 auto !important;
        }

        .logo {
            font-size: 0.88rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 5px !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }

        .logo-title {
            font-size: 0.88rem !important;
            white-space: nowrap !important;
        }

        .logo-pro-badge {
            font-size: 0.58rem !important;
            padding: 1px 5px !important;
        }

        /* Hide Logo when reading */
        body.is-reading .logo {
            display: none !important;
        }

        /* Show Book Meta in place of Logo */
        body.is-reading .book-meta {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 0 !important;
            padding: 0 !important;
            border-left: none !important;
            flex: 1 1 auto !important;
            min-width: 0 !important;
            width: auto !important;
            cursor: pointer !important;
        }

        body.is-reading #book-cover-img {
            height: 36px !important;
            width: 25px !important;
            border-radius: 4px !important;
            object-fit: cover !important;
            flex-shrink: 0 !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.45) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
        }

        body.is-reading .book-text-info {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            gap: 2px !important;
            overflow: hidden !important;
        }

        body.is-reading #book-title-text {
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            color: #f8fafc !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: 100% !important;
            display: block !important;
            line-height: 1.2 !important;
        }

        body.is-reading #book-author-text {
            display: none !important;
        }

        body.is-reading #header-progress-badge {
            font-size: 0.65rem !important;
            padding: 1px 6px !important;
            border-radius: 99px !important;
            margin-top: 1px !important;
            align-self: flex-start !important;
            line-height: 1.25 !important;
            font-weight: 700 !important;
        }

        /* Right actions in reading mode on mobile */
        .header-actions {
            flex: 0 0 auto !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        /* Hide non-essential buttons during reading mode to prevent header overflow */
        body.is-reading #neural-app-root .header-actions #upload-btn,
        body.is-reading #neural-app-root .header-actions #edit-btn,
        body.is-reading #neural-app-root .header-actions #pwa-install-btn {
            display: none !important;
        }

        /* Also on mobile Welcome Hub screen, hide upload and edit buttons from the header,
           because huge interactive cards are already prominently featured in the hub grid */
        body:not(.is-reading) #neural-app-root .header-actions #upload-btn,
        body:not(.is-reading) #neural-app-root .header-actions #edit-btn {
            display: none !important;
        }

        .action-label {
            display: none !important;
        }

        body:not(.is-epub) #neural-app-root #sidebar-toggle-btn {
            display: none !important;
        }

        #neural-app-root .header-actions .icon-btn,
        #neural-app-root #sidebar-toggle-btn {
            padding: 0 !important;
            border-radius: 10px !important;
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            max-width: 36px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            background: rgba(30, 41, 59, 0.75) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #e2e8f0 !important;
        }

        #neural-app-root .header-actions .icon-btn svg,
        #neural-app-root #sidebar-toggle-btn svg {
            width: 17px !important;
            height: 17px !important;
        }

        #content-area {
            padding: 20px 16px 125px 16px !important;
            scroll-padding-top: 25px !important;
            font-size: 1rem;
        }

        .reader-column {
            width: 100% !important;
            min-width: 0;
        }

        #neural-app-root .content-width-handle {
            display: none !important;
        }

        .edit-mode-floating-bar {
            bottom: 20px !important;
            width: calc(100% - 24px) !important;
            max-width: 400px !important;
        }
        .edit-mode-pill {
            width: 100% !important;
            justify-content: space-between !important;
            padding: 8px 10px 8px 14px !important;
            box-sizing: border-box !important;
        }
        .edit-mode-label {
            display: none !important;
        }
        .big-save-btn {
            flex: 1 !important;
            justify-content: center !important;
            padding: 10px 16px !important;
            font-size: 0.88rem !important;
        }
        .cancel-edit-btn {
            padding: 10px 14px !important;
        }

        .settings-panel { padding: 15px; }
        .setting-row { flex-direction: column; gap: 15px; }
        .setting-group.half { width: 100%; }
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
        .hub-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .hub-title {
            font-size: 1.6rem;
        }
        .hub-subtitle {
            font-size: 0.9rem;
        }
        #neural-app-root .hub-card {
            padding: 16px 18px !important;
        }
        .hub-drop-hint {
            font-size: 0.75rem;
        }
        .library-navbar {
            flex-direction: column;
            align-items: stretch;
            gap: 14px;
            padding: 14px 16px;
        }
        .library-brand-block {
            width: 100%;
            justify-content: flex-start;
        }
        .library-search-wrapper {
            max-width: 100%;
            min-width: 100%;
            width: 100%;
        }
        .library-right-actions {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .library-sort-bar {
            width: 100%;
            overflow-x: auto;
            justify-content: space-between;
        }
        .library-fullscreen-overlay .library-exit-btn {
            width: 100%;
        }
        .library-fullscreen-container {
            padding: 16px 14px;
        }
    }

</style>

<script>
window.THEME_URI = '<?php echo get_template_directory_uri(); ?>';
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?php echo get_template_directory_uri(); ?>/web-reader/sw.js')
            .then(reg => console.log('ReadRoad PWA SW registered:', reg.scope))
            .catch(err => console.log('ReadRoad PWA SW registration note:', err));
    });
}
</script>
<script src="<?php echo get_template_directory_uri(); ?>/web-reader/english-phonetics.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo get_template_directory_uri(); ?>/web-reader/scriptreader.js?v=<?php echo time(); ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
