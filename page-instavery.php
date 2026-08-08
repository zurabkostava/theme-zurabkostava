<?php
/*
Template Name: App - Instavery
*/

// Protect this custom app from global WordPress styles and scripts
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('zk-style');
    wp_dequeue_style('zk-fonts');
    wp_dequeue_script('zk-app');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
    
    $uri = get_template_directory_uri() . '/instavery';
    
    // Enqueue Instavery App Styles
    wp_enqueue_style('instavery-fa', $uri . '/fonts/fontawesome.min.css', array(), null);
    wp_enqueue_style('instavery-font', $uri . '/fonts/montserrat.css', array(), null);
    wp_enqueue_style('instavery-style', $uri . '/style.css', array(), time());

    // Enqueue Instavery Scripts
    wp_enqueue_script('instavery-mock', $uri . '/chrome-mock.js', array(), null, true);
    wp_enqueue_script('instavery-supabase', $uri . '/supabase.js', array(), null, true);
    wp_enqueue_script('instavery-xlsx', $uri . '/xlsx.full.min.js', array(), null, true);
    wp_enqueue_script('instavery-app', $uri . '/app.js', array(), time(), true);
}, 999);

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_footer', 'zk_mobile_bottom_nav'); // Remove Mobile Bottom Nav from App

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>InstaDiscovery</title>
    <!-- Choices.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <?php wp_head(); ?>
</head>
<body>

<div id="insta-discovery-app">
    <!-- FAKE INPUTS TO ABSORB CHROME AUTOFILL -->
    <input type="text" style="position:absolute; top:-9999px; left:-9999px;" name="fakeusernameremembered" tabindex="-1" />
    <input type="password" style="position:absolute; top:-9999px; left:-9999px;" name="fakepasswordremembered" tabindex="-1" />


    <div id="authWidget" class="auth-widget">

    </div>
    <button id="adminBtn" class="header-btn-left">
        <i class="fa-solid fa-database"></i>
    </button>
    <div class="background-wrapper">
        <div class="bg-blobs">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>
        </div>
        <div class="bg-blur"></div>
    </div>

    <div class="main-container">
        <header>
            <div class="logo-box">
                <img src="<?php echo get_template_directory_uri(); ?>/instavery/icon48.png" alt="logo" style="width: 40px; height: 40px;">
                <h1>Insta<span class="gradient-text">Very</span></h1>
            </div>

            <div class="platform-bar">
                <button class="plat-btn active" data-platform="instagram" title="Instagram">
                    <i class="fa-brands fa-instagram"></i>
                </button>
                <button class="plat-btn" data-platform="facebook" title="Facebook">
                    <i class="fa-brands fa-facebook"></i>
                </button>
                <button class="plat-btn" data-platform="youtube" title="YouTube">
                    <i class="fa-brands fa-youtube"></i>
                </button>
            </div>

            <div class="global-settings" style="margin-bottom: 20px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                <div class="checkbox-wrapper" style="background: rgba(255,255,255,0.05); padding: 8px 15px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin: 0!important;">
                    <input type="checkbox" id="autoNavCheck" class="glass-checkbox">
                    <label for="autoNavCheck" style="font-size: 0.85rem; font-weight: 600; color: #fff;">🚀 Auto-Redirect</label>
                </div>
            </div>
        </header>



        <div class="cards-grid">
            <div id="cardTag" class="glass-card">
                <div class="card-content">
                    <div class="card-header-row">
                        <div class="icon-wrapper tag-gradient"><i class="fa-solid fa-hashtag"></i></div>
                        <h2>Tags</h2>
                    </div>
                    <div class="input-wrapper">
                        <input type="text" id="tagInput" class="glass-input" placeholder="Type tag..." autocomplete="new-password" spellcheck="false" readonly onfocus="this.removeAttribute('readonly');">
                        <button id="clearTag" class="clear-input hidden"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="select-wrapper">
                        <select id="tagCategory" class="glass-select" multiple></select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="noHashCheck" class="glass-checkbox">
                        <label for="noHashCheck">Keyword Mode</label>
                    </div>
                    <div id="recentWrapperTag" class="checkbox-wrapper">
                        <input type="checkbox" id="recentCheckTag" class="glass-checkbox">
                        <label for="recentCheckTag">🔥 Show Recent</label>
                    </div>

                    <div id="ytDateWrapper" class="select-wrapper hidden" style="margin-bottom: 15px;">
                        <select id="ytDateSelect" class="glass-select">
                            <option value="">📅 Any Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <button id="btnTag" class="primary-btn">Go</button>
                </div>
            </div>

            <div id="cardLoc" class="glass-card">
                <div class="card-content">

                    <div class="card-header-row">
                        <div class="icon-wrapper tag-gradient"><i class="fa-solid fa-earth-americas"></i></div>
                        <h2>Places</h2>
                    </div>
                    <div class="input-wrapper">
                        <input type="text" id="locInput" name="search_query_loc" class="glass-input" placeholder="City..." autocomplete="new-password" spellcheck="false" readonly onfocus="this.removeAttribute('readonly');">
                        <button id="clearLoc" class="clear-input hidden"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="select-wrapper">
                        <select id="locCategory" class="glass-select" multiple></select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <div id="recentWrapperLoc" class="checkbox-wrapper">
                        <input type="checkbox" id="recentCheckLoc" class="glass-checkbox">
                        <label for="recentCheckLoc">🔥 Show Recent</label>
                    </div>

                    <div id="ytDateWrapperLoc" class="select-wrapper hidden" style="margin-bottom: 15px;">
                        <select id="ytDateSelectLoc" class="glass-select">
                            <option value="">📅 Any Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <button id="btnLoc" class="primary-btn">Go</button>
                </div>
            </div>

            <div id="cardPerson" class="glass-card">
                <div class="card-content">

                    <div class="card-header-row">
                        <div class="icon-wrapper tag-gradient"><i class="fa-solid fa-user-astronaut"></i></div>
                        <h2>People</h2>
                    </div>
                    <div class="input-wrapper">
                        <input type="text" id="personInput" name="search_query_person" class="glass-input" placeholder="Profile username..." autocomplete="new-password" spellcheck="false" data-lpignore="true" readonly onfocus="this.removeAttribute('readonly');">
                        <button id="clearPerson" class="clear-input hidden"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="select-wrapper">
                        <select id="personCategory" class="glass-select" multiple></select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <div id="ytSortWrapperPerson" class="select-wrapper hidden" style="margin-bottom: 15px;">
                        <select id="ytSortSelectPerson" class="glass-select">
                            <option value="relevance">🎯 Relevance</option>
                            <option value="popularity">🔥 Popularity</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    <button id="btnPerson" class="primary-btn">Go</button>
                </div>
            </div>
        </div>

        <div id="resultArea" class="result-container hidden">
            <div class="result-header">
                <span class="result-label">Result:</span>
                <button id="closeBtn" class="mini-close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p id="resultText">loading...</p>
            <div class="actions-stack">
                <button id="btnPrimary" class="action-btn btn-main hidden"></button>
                <button id="btnGoogle" class="action-btn btn-white hidden"></button>
                <button id="btnSecondary" class="action-btn btn-outline hidden"></button>
                <button id="btnLite" class="action-btn btn-lite hidden"></button>
            </div>
        </div>
    </div>


    <div id="adminModal" class="admin-modal hidden">
        <div class="admin-container">
            <div class="admin-header">
                <div class="admin-header-left">
                    <h3>Manager</h3>
                                    </div>
                <div class="admin-header-right">
                    <button id="btnOpenSettings" class="header-icon-btn" title="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button id="closeAdmin" class="header-icon-btn header-icon-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div class="admin-tabs">
                <button class="tab-btn active" data-target="panelTags">Tags <span id="badgeTags" class="tab-badge">0</span></button>
                <button class="tab-btn" data-target="panelLocs">Locs <span id="badgeLocs" class="tab-badge">0</span></button>
                <button class="tab-btn" data-target="panelPeople">Ppl <span id="badgePeople" class="tab-badge">0</span></button>
            </div>

            <div id="panelSettings" class="admin-panel hidden">
                <div class="admin-toolbar" style="margin-bottom:15px;">
                    <button id="btnBackToData" class="btn-csv" style="width:auto; padding:0 10px; font-size:0.8rem;">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <h4 style="margin-left:10px; color:#fff;">Edit Categories</h4>
                </div>

                <div class="admin-tabs" id="settingsTabs">
                    <button class="tab-btn active" data-section="tags">Tags</button>
                    <button class="tab-btn" data-section="locations">Locs</button>
                    <button class="tab-btn" data-section="people">Ppl</button>
                </div>
                <div class="input-wrapper" style="display:flex; gap:5px;">
                    <input type="text" id="newCatLabel" class="admin-search" placeholder="Label (e.g. 🤖 Cyber)">
                    <input type="text" id="newCatKey" class="admin-search" placeholder="Key (cyber)" style="width:80px;">
                    <button id="btnAddCategory" class="admin-btn-add">Add</button>
                </div>

                <div id="settingsList" class="admin-list" style="margin-top:10px;">Loading...</div>

            </div>

            <div id="panelTags" class="admin-panel active">
                <div class="admin-toolbar">
                    <select id="adminTagFilter" class="admin-select" multiple><option value="all">All</option></select>
                    <select id="playlistTagFilter" class="playlist-select"><option value="">☆ All</option></select>
                    <input type="text" id="searchTags" class="admin-search" placeholder="Search...">

                    <div class="csv-actions">
                        <button class="btn-csv" onclick="exportExcel(this)"><i class="fa-solid fa-file-excel"></i></button>
                        <button class="btn-csv" onclick="triggerImport()"><i class="fa-solid fa-file-import"></i></button>
                        <input type="file" id="csvFileInput" accept=".xlsx, .xls" style="display: none;" onchange="importXLSX(this)">
                    </div>
                    <div class="select-all-box">
                        <input type="checkbox" class="master-checkbox">
                    </div>
                    <button id="btnAddTag" class="admin-btn-add">+ New</button>
                </div>
                <div class="list-meta">Found: <span id="countTags">...</span></div>
                <div id="listTags" class="admin-list">Loading...</div>
            </div>

            <div id="panelLocs" class="admin-panel">
                <div class="admin-toolbar">
                    <select id="adminLocFilter" class="admin-select" multiple><option value="all">All</option></select>
                    <select id="playlistLocFilter" class="playlist-select"><option value="">☆ All</option></select>
                    <input type="text" id="searchLocs" class="admin-search" placeholder="Search...">
                    <div class="csv-actions">
                        <button class="btn-csv" onclick="exportExcel(this)"><i class="fa-solid fa-file-excel"></i></button>
                        <button class="btn-csv" onclick="triggerImport()"><i class="fa-solid fa-file-import"></i></button>
                    </div>
                    <div class="select-all-box">
                        <input type="checkbox" class="master-checkbox">
                    </div>
                    <button id="btnAddLoc" class="admin-btn-add">+ New</button>
                </div>
                <div class="list-meta">
                    Found: <span id="countLocs">...</span>
                    <div class="sort-controls">
                        <button class="btn-sort active" id="sortLocDefault" data-sort="default">Default</button>
                        <button class="btn-sort" id="sortLocName" data-sort="name">Name</button>
                        <button class="btn-sort" id="sortLocPosts" data-sort="posts">Posts</button>
                    </div>
                </div>
                <div id="listLocs" class="admin-list">Loading...</div>
            </div>

            <div id="panelPeople" class="admin-panel">
                <div class="admin-toolbar">
                    <select id="adminPersonFilter" class="admin-select" multiple><option value="all">All</option></select>
                    <select id="playlistPersonFilter" class="playlist-select"><option value="">☆ All</option></select>
                    <input type="text" id="searchPeople" class="admin-search" placeholder="Search...">
                    <div class="csv-actions">
                        <button class="btn-csv" onclick="exportExcel(this)"><i class="fa-solid fa-file-excel"></i></button>
                        <button class="btn-csv" onclick="triggerImport()"><i class="fa-solid fa-file-import"></i></button>
                    </div>
                    <div class="select-all-box">
                        <input type="checkbox" class="master-checkbox">
                    </div>
                    <button id="btnAddPerson" class="admin-btn-add">+ New</button>
                </div>
                <div class="list-meta">
                    Found: <span id="countPeople">...</span>
                    <div class="sort-controls">
                        <button class="btn-sort-people active" data-sort="default">Default</button>
                        <button class="btn-sort-people" data-sort="name">Name</button>
                        <button class="btn-sort-people" data-sort="followers">Followers</button>
                        <button class="btn-sort-people" data-sort="following">Following</button>
                    </div>
                </div>
                <div id="listPeople" class="admin-list">Loading...</div>
            </div>

            <div id="bulkDeleteBar" class="bulk-bar hidden">
                <span id="bulkCount">0 selected</span>

                <select id="bulkMoveDest" class="bulk-select">
                    <option value="">Move to...</option>
                </select>

                <button id="btnBulkMoveAction" class="bulk-btn btn-move">
                    <i class="fa-solid fa-folder-open"></i> Move
                </button>

                <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.1); margin: 0 5px;"></div>

                <button id="btnBulkDeleteAction" class="bulk-btn btn-delete">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>

        </div>
    </div>

    <div id="resModalOverlay" class="modal-overlay">
        <div class="result-card" id="resCardBox">
            <div class="modal-loader"></div>
            <div class="modal-content" id="resContent">
                <div id="resIcon" class="res-icon"></div>
                <div id="resType" class="res-type"></div>
                <div id="resValue" class="res-value"></div>

                <div class="modal-actions">
                    <a href="#" id="btnModalGo" target="_blank" class="btn-modal-action btn-go">
                        <i class="fa-brands fa-instagram"></i> Open
                    </a>
                    <a href="#" id="btnModalLite" class="btn-modal-action btn-lite hidden">
                        <i class="fa-solid fa-bolt"></i> Lite Mode
                    </a>
                    <a href="#" id="btnModalMap" target="_blank" class="btn-modal-action btn-map hidden">
                        <i class="fa-solid fa-map-location-dot"></i> Google Maps
                    </a>
                    <div class="modal-row">
                        <button id="btnModalRefresh" class="btn-modal-action btn-refresh">
                            <i class="fa-solid fa-rotate-right"></i> Another
                        </button>
                        <button id="btnModalClose" class="btn-modal-action btn-close-modal">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="inputModal" class="admin-modal hidden" style="z-index: 20000 !important;">
        <div class="input-box">
            <h3 id="inputTitle">Edit Item</h3>
            <input type="text" id="inputName" class="admin-input-field" placeholder="Name..." autocomplete="off">

            <div id="categoryFieldWrapper" class="input-wrapper hidden" style="margin-top:10px;">
                <select id="inputCategory" class="admin-select" style="width:100%;"></select>
            </div>

            <div id="countFieldWrapper" class="input-wrapper hidden" style="margin-top:10px;">
                <input type="text" id="inputCount" class="admin-input-field" placeholder="Post Count (e.g. 10M)" autocomplete="off">
            </div>

            <div id="idFieldWrapper" class="input-wrapper hidden" style="margin-top:10px;">
                <input type="text" id="inputID" class="admin-input-field" placeholder="Instagram ID (Optional)" autocomplete="off">
            </div>
            <div id="followersFieldWrapper" class="input-wrapper hidden" style="margin-top:10px; display:flex; gap:8px;">
                <input type="text" id="inputFollowers" class="admin-input-field" placeholder="Followers (e.g. 10K)" autocomplete="off" style="flex:1;">
                <input type="text" id="inputFollowing" class="admin-input-field" placeholder="Following (e.g. 500)" autocomplete="off" style="flex:1;">
            </div>
            <div class="input-actions">
                <button id="btnCancelInput" class="input-btn btn-cancel">Cancel</button>
                <button id="btnSaveInput" class="input-btn btn-save">Save</button>
            </div>
        </div>
    </div>

    <div id="playlistModal" class="admin-modal hidden" style="z-index: 20000 !important;">
        <div class="input-box" style="max-width: 340px;">
            <h3 id="playlistModalTitle">Add to Playlist</h3>
            <div id="playlistList" class="playlist-list"></div>
            <div style="display:flex; gap:6px; margin-top:12px;">
                <input type="text" id="newPlaylistName" class="admin-input-field" placeholder="New playlist name..." style="flex:1; margin-bottom:0;">
                <button id="btnCreatePlaylist" class="input-btn btn-save" style="padding:8px 14px;">+</button>
            </div>
            <div class="input-actions" style="margin-top:12px;">
                <button id="btnPlaylistClose" class="input-btn btn-cancel">Close</button>
            </div>
        </div>
    </div>

    <div id="playlistManageModal" class="admin-modal hidden" style="z-index: 20002 !important;">
        <div class="input-box" style="max-width: 340px;">
            <h3>Manage Playlists</h3>
            <div id="playlistManageList" class="playlist-list"></div>
            <div class="input-actions" style="margin-top:12px;">
                <button id="btnPlaylistManageClose" class="input-btn btn-cancel">Close</button>
            </div>
        </div>
    </div>

    <div id="loginModal" class="admin-modal hidden" style="z-index: 20001 !important;">
        <div class="input-box">
            <div style="font-size: 3rem; margin-bottom: 10px;">🔐</div>
            <h3 style="margin-bottom: 20px;">System Access</h3>
            <input type="email" id="loginEmail" class="admin-input-field" placeholder="Email..." autocomplete="email" style="margin-bottom: 10px;">
            <input type="password" id="loginPass" class="admin-input-field" placeholder="Password..." autocomplete="current-password">
            <div id="loginError" class="hidden" style="color: #ff4d4d; font-size: 0.8rem; margin-top: 10px;">Error</div>
            <div class="input-actions" style="margin-top: 20px;">
                <button id="btnLoginCancel" class="input-btn btn-cancel">Cancel</button>
                <button id="btnLoginConfirm" class="input-btn btn-save">Sign In</button>
            </div>
            <p style="margin-top: 15px; font-size: 0.8rem; color: #888;">
                <span id="authMsg">No account?</span> <a href="#" id="btnToggleAuth" style="color: #fff;">Sign Up</a>
            </p>
        </div>
    </div>

    <div id="customConfirmModal" class="admin-modal hidden" style="z-index: 30000 !important;">
        <div class="input-box" style="text-align: center; max-width: 320px;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🤔</div>
            <h3 id="confirmTitle">Confirm</h3>
            <p id="confirmText" style="color: #aaa; margin-bottom: 20px; font-size: 0.9rem;">Are you sure?</p>
            <div class="input-actions">
                <button id="btnConfirmNo" class="input-btn btn-cancel">Cancel</button>
                <button id="btnConfirmYes" class="input-btn btn-save">Yes</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>

</div>

<!-- Choices.js JS -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<?php wp_footer(); ?>
</body>
</html>
