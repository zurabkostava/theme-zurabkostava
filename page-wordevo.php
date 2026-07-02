<?php
/*
Template Name: App - WordEvo
*/

// Protect this custom app from global WordPress styles and scripts
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('zk-style');
    wp_dequeue_style('zk-fonts');
    wp_dequeue_script('zk-app');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
    
    // Enqueue WordEvo App Styles
    wp_enqueue_style('wordevo-app-style', get_template_directory_uri() . '/WordEvo/style.css', array(), time());
}, 999);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_footer', 'zk_mobile_bottom_nav'); // Remove Mobile Bottom Nav from App
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Wordevo</title>
    
    <?php wp_head(); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;900&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?php echo get_template_directory_uri(); ?>/WordEvo/manifest.json">
    <meta name="theme-color" content="#ffffff">

    <script>
        window.WORDEVO_ASSET_PATH = '<?php echo get_template_directory_uri(); ?>/WordEvo';
        // Apply dark mode immediately to prevent flash
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2" onerror="console.error('Supabase CDN failed to load')"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/supabase-client.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/script.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/quiz.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/wordhear.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/makeword.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/utils.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/mix.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/typegame.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/sentence.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/puzzle.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/games/speakgame.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo get_template_directory_uri(); ?>/WordEvo/tts.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/notifications.js?v=<?php echo time(); ?>"></script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div id="globalLoadingScreen">
    <div class="wordevo-spinner"></div>
</div>
<div class="auth-container" id="authContainer" style="display: none;">
    <div class="auth-box" id="loginBox">
        <h2>Wordevo</h2>
        <p>Welcome Back</p>
        <div class="input-container">
            <label class="material-input">
                <input class="form-control" id="authEmail" placeholder=" " type="email"/>
                <span>Email</span>
            </label>
        </div>
        <div class="input-container">
            <label class="material-input">
                <input class="form-control" id="authPassword" placeholder=" " type="password"/>
                <span>Password</span>
            </label>
        </div>
        <div class="auth-actions" style="justify-content: center;">
            <button id="loginBtn" style="width: 100%;">Login</button>
        </div>
        <div style="text-align: center; margin-top: 15px;">
            <a href="#" id="showRegisterLink" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">Don't have an account? Sign up</a>
        </div>
        <button id="skipAuthBtn" style="margin-top:20px; width:100%; padding:10px 24px; background:var(--glass-bg); color:var(--text-color); border:1px solid var(--glass-border); border-radius:12px; cursor:pointer; font-size:14px; font-weight: 500; transition: all 0.3s ease;">Skip Login (Offline)</button>
        <div class="auth-message" id="authMessage"></div>
    </div>

    <div class="auth-box" id="registerBox" style="display: none;">
        <h2>Wordevo</h2>
        <p>Create an Account</p>
        <div class="input-container">
            <label class="material-input">
                <input class="form-control" id="regEmail" placeholder=" " type="email"/>
                <span>Email</span>
            </label>
        </div>
        <div class="input-container">
            <label class="material-input">
                <input class="form-control" id="regPassword" placeholder=" " type="password"/>
                <span>Password</span>
            </label>
        </div>
        <div class="input-container">
            <label class="material-input">
                <input class="form-control" id="regPasswordConfirm" placeholder=" " type="password"/>
                <span>Confirm Password</span>
            </label>
        </div>
        <div class="auth-actions" style="justify-content: center;">
            <button id="registerBtn" style="width: 100%;">Create Account</button>
        </div>
        <div style="text-align: center; margin-top: 15px;">
            <a href="#" id="showLoginLink" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">Already have an account? Log in</a>
        </div>
        <div class="auth-message" id="regMessage"></div>
    </div>
</div>
<div id="mainAppContainer" style="display: none;">
    <div class="header-wrapper">
        <div class="mobile-header" style="display: none;">
            <button class="mobile-tags-btn" id="mobileToggleSidebarBtn" title="Tags">
                <i class="fas fa-tags"></i>
            </button>
            <div class="app-logo">Wordevo</div>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="top">
        <div class="top-bar">
            <div class="top-left" style="display: flex; align-items: center; gap: 15px;">
                <div class="app-logo">Wordevo</div>
                <div class="library-selector-wrapper">
                    <button id="libraryManagerBtn" class="library-manager-btn premium-library-btn" title="Manage Libraries">
                        <i class="fas fa-book library-icon"></i>
                        <span id="currentLibraryName">EN-GE</span>
                        <div class="chevron-wrapper">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </button>
                </div>
            </div>
            <div class="top-center" style="display: flex !important; align-items: center !important; justify-content: center !important; gap: 20px !important;">

                <div class="search-input-wrapper" style="position: relative !important; width: 350px !important; flex-shrink: 0 !important; margin: 0 !important;">
                    <i class="fas fa-search search-icon"></i>
                    <input class="modern-search-input" id="searchInput" placeholder="Search word..." type="text" style="width: 100% !important; box-sizing: border-box !important; min-width: 0 !important; margin: 0 !important;"/>
                </div>
                <button id="trainingBtn" style="margin: 0 !important; flex-shrink: 0 !important; position: relative !important; z-index: 2 !important;">
                    <i class="fa-solid fa-award"></i>
                    Training
                </button>


            </div>
            <div class="top-right">
                <span id="userEmailDisplay" style="font-size: 0.9rem; color: #888;"></span>
                <button id="logoutBtn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
                <button id="toggleDarkModeBtn" title="Dark Mode Toggle">
                    <i class="fas fa-moon">
                    </i>
                </button>
                <button id="settingsBtn">
                    <i class="fas fa-cog">
                    </i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-toolbar" id="cardToolbar">
            <div class="toolbar-left">
                <button class="toolbar-btn" id="toggleSidebarBtn" title="Tags">
                    <i class="fas fa-tags"></i>
                </button>
            </div>
            <div class="toolbar-right" style="display: flex; align-items: center; margin-left: auto; gap: 12px; flex-wrap: wrap;">
                <div class="sorting">
                    <i class="fas fa-sort-down" id="sortDirectionIcon"></i>
                    <label class="sort-label" for="sortSelect"></label>
                    <select class="toolbar-select" id="sortSelect">
                        <option value="alphabetical">Alphabetical</option>
                        <option value="updated">Recent</option>
                        <option selected="" value="progress">By Progress</option>
                    </select>
                </div>
                <div class="hide-mastered-wrapper">
                    <select id="mainProgressSelect" class="toolbar-select">
                        <option value="">Any</option>
                        <option value="0-99" selected>- Learned</option>
                        <option value="0-30">0% - 30%</option>
                        <option value="31-50">31% - 50%</option>
                        <option value="51-70">51% - 70%</option>
                        <option value="71-80">71% - 80%</option>
                        <option value="81-99">81% - 99%</option>
                        <option value="100-100">100%</option>
                    </select>
                </div>
                <div class="view-toggle-wrapper">
                    <button id="viewToggleBtn" class="toolbar-btn" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(30, 30, 46, 0.7); color: #cdd6f4; border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer; transition: all 0.2s ease;" title="Toggle View">
                        <i class="fas fa-th-large" id="viewToggleIcon"></i>
                    </button>
                </div>
                
                <div class="toolbar-divider" style="width: 1px; height: 24px; background: rgba(0,0,0,0.1); margin: 0 4px;"></div>
                
                <div class="toolbar-dropdown" style="position: relative; display: inline-block;">
                    <button class="toolbar-btn" id="toolbarMoreBtn" title="More Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="toolbar-dropdown-content" id="toolbarDropdownContent" style="display: none;">
                        <button class="toolbar-btn" id="statsBtn" title="Statistics">
                            <i class="fas fa-chart-pie"></i>
                        </button>
                        <button class="toolbar-btn" id="notificationsBtn" title="Reminders">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </div>
                
                <button id="addCardBtn" class="primary-add-btn">
                    <i class="fas fa-plus"></i>
                    <span class="add-btn-text">Add Word</span>
                </button>
            </div>
        </div>
    </div>
    <div class="card-container" id="cardContainer">
    </div>

    <div class="modal-overlay" id="libraryModalOverlay" style="display: none;">
        <div class="modal library-modal" style="max-width: 500px; padding: 25px;">
            <div class="modal-header" style="margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
                <h2 style="font-size: 20px; margin: 0;"><i class="fas fa-book" style="margin-right: 8px; color: var(--primary-color);"></i> Manage Libraries</h2>
                <button class="close-button" id="closeLibraryModalBtn">×</button>
            </div>
            <div class="modal-body">
                <div id="libraryListContainer" style="display: flex; flex-direction: column; gap: 10px; max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                    <!-- Library items injected here -->
                </div>
                <div class="add-library-section" style="display: flex; flex-direction: column; gap: 8px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <div style="font-size: 14px; color: var(--text-secondary); font-weight: 500;">Create New Library</div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="newLibraryNameInput" class="form-control" placeholder="New Library Name" style="flex: 1; padding: 10px; border-radius: 8px; min-width: 150px;" />
                        <select id="newDictLang1Select" class="form-control" style="padding: 10px; border-radius: 8px; max-width: 120px;" title="Target Language"></select>
                        <select id="newDictLang2Select" class="form-control" style="padding: 10px; border-radius: 8px; max-width: 120px;" title="Native Language"></select>
                        <button id="createLibrarySubmitBtn" class="primary-btn" style="padding: 10px 20px; white-space: nowrap; border-radius: 8px;"><i class="fas fa-plus"></i> Add</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h2>New Word</h2>
                <button class="close-button" id="closeAddModalBtn">×</button>
            </div>
            <div class="modal-body">
            <details style="margin-bottom: 15px; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                <summary style="cursor: pointer; font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-import"></i> Quick Import JSON
                </summary>
                <div style="margin-top: 10px;">
                    <textarea id="jsonImportInput" class="form-control" rows="4" placeholder='{"word": "apple", "main": ["ვაშლი"], "extra": ["ხილის სახეობა"], "tags": ["ხილი"], "english": ["I eat an apple."], "georgian": ["მე ვჭამ ვაშლს."], "mnemonic": "A is for Apple"}'></textarea>
                    <button type="button" id="importJsonBtn" style="margin-top: 10px; padding: 8px 16px; font-size: 14px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer;">Import Data</button>
                </div>
            </details>
            <div class="input-container">
                <label class="material-input validation">
                    <input class="form-control" id="wordInput" placeholder=" " required="" type="text"
                           value="(Validation)"/>
                    <span>
       Word
      </span>
                </label>
            </div>
            <div class="input-container tag-input">
                <label class="material-input">
                    <input id="mainTranslationInput" placeholder=" " type="text"/>
                    <span>
       Main Translation
      </span>
                </label>
                <button id="addMainTranslationBtn">
                    +
                </button>
            </div>
            <div class="tags-display" id="mainTranslationTags">
            </div>
            <div class="input-container tag-input">
                <label class="material-input">
                    <input id="extraTranslationInput" placeholder=" " type="text"/>
                    <span>
       Extra Translations
      </span>
                </label>
                <button id="addExtraTranslationBtn">
                    +
                </button>
            </div>
            <div class="tags-display" id="extraTranslationTags">
            </div>
            <div class="input-container tag-input">
                <label class="material-input">
                    <input id="tagInput" placeholder=" " type="text"/>
                    <span>
       Type or select tag
      </span>
                </label>
                <button id="addTagBtn">
                    +
                </button>
                <div class="dropdown" id="tagDropdown">
                </div>
            </div>
            <div class="tags-display" id="tagList">
            </div>
            <div class="input-container">
                <label class="material-input">
                    <textarea class="form-control" id="mnemonicInput" placeholder=" " rows="2"></textarea>
                    <span>
       Mnemonic (Association)
      </span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input">
                    <textarea class="form-control" id="englishSentences" placeholder=" " rows="6"></textarea>
                    <span>
       Main Language Text (Lang 1)
      </span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input">
                    <textarea class="form-control" id="georgianSentences" placeholder=" " rows="5"></textarea>
                    <span>
       Translation / Second Lang Text (Lang 2)
      </span>
                </label>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions">
                <button id="saveCardBtn">
                    Save
                </button>
                <button id="cancelBtn">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <button class="mobile-sidebar-btn" id="mobileSidebarBtn">
        <i class="fas fa-filter">
        </i>
    </button>
    <div class="sidebar" id="sidebar">
        <div class="tags-header">
            <button id="closeSidebarBtn" style="float:right;">
                ✖
            </button>
            <h3>
                Tags
            </h3>
            <button class="clear-tags-btn" id="clearTagFiltersBtn">
                ✖ Clear Filter
            </button>
        </div>
        <ul id="sidebarTagList">
        </ul>
    </div>
    <div class="modal-overlay" id="cardPreviewModal" style="display: none;">
        <button class="nav-btn inside-nav left-nav fas fa-angle-left" id="prevCardBtn">
        </button>
        <button class="nav-btn inside-nav right-nav fas fa-angle-right" id="nextCardBtn">
        </button>
        <div class="modal preview-modal">
            <div class="preview-sticky">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                      <h2 id="previewWord" style="margin: 0; padding-right: 20px; flex: 1; line-height: 1.3; word-break: break-word;">
                      </h2>
                      <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
                          <div id="previewWordSpeakerContainer"></div>
                          <button class="close-button" id="closePreviewBtn" style="padding: 0;">
                              ×
                          </button>
                      </div>
                  </div>
                <div id="previewDate" style="font-size: 13px; color: #888; margin-bottom: 10px;"></div>
                <hr/>
                <p id="previewTranslation">
                </p>
                <div id="previewMnemonic" class="mnemonic-display" style="display: none;"></div>
                <div class="tags" id="previewTags">
                </div>
            </div>
            <div class="modal-section sentence-preview">
                  <h3>
                      Examples
                  </h3>
                  <div class="sentence-list" id="previewCombinedSentences">
                  </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="settingsModal" style="display: none; gap: 10px; flex-wrap: wrap;">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Settings</h2>
                <button class="close-button" id="closeSettingsBtn">×</button>
            </div>
            <div class="modal-body">
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="voiceSelect" required="">
                        <option disabled="" hidden="" selected="" value="">
                        </option>
                        <option value="Libby">
                            Microsoft Libby Online
                        </option>
                        <option value="Maisie">
                            Microsoft Maisie Online
                        </option>
                        <option value="Ryan">
                            Microsoft Ryan Online
                        </option>
                        <option value="Sonia">
                            Microsoft Sonia Online
                        </option>
                        <option value="Thomas">
                            Microsoft Thomas Online
                        </option>
                        <option value="Ana">
                            Microsoft Ana Online
                        </option>
                    </select>
                    <span>
       Select Main Lang Voice (Lang 1)
      </span>
                    <i class="fas fa-chevron-down select-arrow-icon">
                    </i>
                </label>
            </div>
            <div>
                <label class="material-input">
      <span>
       Main Voice Speed
      </span>
                    <input id="englishRateSlider" max="2" min="0.5" step="0.1" type="range" value="1"/>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="georgianVoiceSelect" required="">
                        <option disabled="" hidden="" selected="" value="">
                        </option>
                        <option value="Microsoft Eka Online (Natural)">
                            Microsoft Eka Online (Natural) - Georgian (Georgia)
                        </option>
                        <option value="Microsoft Giorgi Online (Natural)">
                            Microsoft Giorgi Online (Natural) - Georgian (Georgia)
                        </option>
                    </select>
                    <span>
       Select Second Lang Voice (Lang 2)
      </span>
                    <i class="fas fa-chevron-down select-arrow-icon">
                    </i>
                </label>
            </div>
            <div>
                <label class="material-input">
      <span>
       Second Voice Speed
      </span>
                    <input id="georgianRateSlider" max="2" min="0.5" step="0.1" type="range" value="1"/>
                </label>
            </div>
            <div class="input-container checkbox-container" style="margin-top: 15px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="skipExtraTranslationCheckbox" style="width: 20px; height: 20px;" />
                    <span style="font-weight: 500;">Don't read additional translation</span>
                </label>
            </div>
            <div class="input-container checkbox-container" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="shuffleExamplesCheckbox" style="width: 20px; height: 20px;" />
                    <span style="font-weight: 500;">Shuffle examples</span>
                </label>
            </div>
            <div class="input-container checkbox-container" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="skipMnemonicCheckbox" style="width: 20px; height: 20px;" />
                    <span style="font-weight: 500;">Don't read mnemonic</span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="readExamplesSelect" required="">
                        <option value="all" selected>Read all</option>
                        <option value="0">Don't read examples</option>
                        <option value="1">Read 1</option>
                        <option value="2">Read 2</option>
                        <option value="3">Read 3</option>
                        <option value="4">Read 4</option>
                        <option value="5">Read 5</option>
                    </select>
                    <span>
         Examples Limit
        </span>
                    <i class="fas fa-chevron-down select-arrow-icon">
                    </i>
                </label>
            </div>
            <div class="import-export-group">
                <h3>
                    Import / Export Files
                </h3>
                <div class="button-row">
                    <button class="settings-btn blue" id="exportExcelBtn">
                        📤 Export to Excel
                    </button>

                    <label class="settings-btn settings-btn-force cyan" for="importExcelInput">
                    <label class="settings-btn settings-btn-force cyan" for="importExcelInput">
                        📥 Import from Excel
                    </label>
                    <input accept=".xlsx" id="importExcelInput" style="display: none;" type="file">
                    <button class="settings-btn gray" id="downloadTemplateBtn">
                        🧾 Download Template
                    </button>
                </div>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px;">
                <button id="saveVoiceBtn" style="background-color: #28a745; color: white;">
                    Save
                </button>
            </div>
        </div>
    </div>

    <!-- New Dictionary Modal -->
    <div class="modal-overlay" id="addDictionaryModal" style="display: none; z-index: 2000;">
        <div class="modal">
            <div class="modal-header">
                <h2>New Dictionary</h2>
                <button class="close-button" id="closeAddDictionaryBtn">×</button>
            </div>
            <div class="modal-body">
            <div class="input-container">
                <label class="material-input validation">
                    <input class="form-control" id="newDictName" placeholder=" " required="" type="text" />
                    <span>Dictionary Name</span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="newDictLang1Select" required=""></select>
                    <span>Select Main Language (Lang 1)</span>
                    <i class="fas fa-chevron-down select-arrow-icon"></i>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="newDictLang2Select" required=""></select>
                    <span>Select Translation Language (Lang 2)</span>
                    <i class="fas fa-chevron-down select-arrow-icon"></i>
                </label>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px;">
                <button id="saveNewDictionaryBtn" style="background-color: #28a745; color: white;">
                    Create
                </button>
            </div>
        </div>
    </div>
    <div class="training-modal hidden" id="trainingModal">
        <div class="training-modal-content">
            <div class="training-wrapper">
                <div class="training-tabs">
                    <button class="training-tab active" data-tab="quiz">
                        QUIZ
                    </button>
                    <button class="training-tab" data-tab="tab2">
                        HEAR
                    </button>
                    <button class="training-tab" data-tab="tab3">
                        MIX
                    </button>
                    <button class="training-tab" data-tab="tab4">
                        FILL
                    </button>
                    <button class="training-tab" data-tab="tab5">
                        TYPE
                    </button>
                    <button class="training-tab" data-tab="tab6">
                        SENTENCE
                    </button>
                    <button class="training-tab" data-tab="tab7">
                        PUZZLE
                    </button>
                    <button class="training-tab" data-tab="tab8">
                        SPEAK
                    </button>
                    <button class="training-close">
                        ×
                    </button>
                </div>
                <div id="globalTrainingSettings">
                    <div class="tag-filter">
                        <label for="globalTagSelect">
                            Tag:
                        </label>
                        <select id="globalTagSelect">
                        </select>
                    </div>
                    <div class="count-filter">
                        <label for="globalQuestionCount">
                            Count:
                        </label>
                        <input id="globalQuestionCount" max="100" min="1" type="number" value="10"/>
                    </div>
                    <div class="progress-filter">
                        <label for="globalProgressSelect">Progress:</label>
                        <select id="globalProgressSelect">
                            <option value="">Any (All)</option>
                            <option value="0-99">All (Except 100%)</option>
                            <option value="0-30">0% - 30%</option>
                            <option value="31-50">31% - 50%</option>
                            <option value="51-70">51% - 70%</option>
                            <option value="71-80">71% - 80%</option>
                            <option value="81-99">81% - 99%</option>
                            <option value="100-100">Learned (100%)</option>
                        </select>
                    </div>
                    <div class="checkbox-start-group" style="display: flex; align-items: center; gap: 15px; margin-left: auto;">
                        <label>
                            <input id="globalReverseToggle" type="checkbox"/>
                            Reverse
                        </label>
                        <button id="globalStartBtn" class="global-start-btn" onclick="startActiveGame()">Start <i class="fas fa-play"></i></button>
                    </div>
                </div>
            <div class="training-tab-content" data-tab-content="quiz" id="quizTab">
                <h2>
                    Quiz სექცია
                </h2>
                <div class="quiz-settings">
                </div>
                <div class="quiz-container" id="quizContainer">
                </div>
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab2">
                <h2>
                    ტაბი 2 შინაარსი
                </h2>
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab3">
                <h2>
                    ტაბი 3 შინაარსი
                </h2>
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab4">
                <h2>
                    ტაბი 4 შინაარსი
                </h2>
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab5">
                <h2>
                    ტაბი 5 შინაარსი
                </h2>
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab6">
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab7">
            </div>
            <div class="training-tab-content hidden" data-tab-content="tab8" id="speakTab">
            </div>
        </div>
    </div>
</div>
    <div class="fixed-player-wrapper">
        <div class="player-minimized-display" id="playerMinimizedDisplay" style="display: none;" title="გახსნა">
        </div>
        <div class="player">
            <button class="player-btn" title="Previous">
                <i class="fas fa-backward-step">
                </i>
            </button>
            <button class="player-btn" id="playToggleBtn" title="Play">
                <i class="fas fa-play">
                </i>
            </button>

            <button class="player-btn" title="Next">
                <i class="fas fa-forward-step">
                </i>
            </button>
            <button class="player-btn" id="shuffleCardBtn" title="Shuffle">
                <i class="fas fa-shuffle">
                </i>
            </button>
        </div>
    </div>
    <button class="mobile-toggle-btn" id="showTopBtn">
        <i class="fas fa-sliders">
        </i>
    </button>
    <div class="toolbar-actions">
        <button id="deleteSelectedBtn">
            <i class="fas fa-trash">
            </i>
            Delete
        </button>
        <button id="selectAllBtn">
            <i class="fa-solid fa-check-double">
            </i>
        </button>
        <button id="cancelSelectionBtn">
            <i class="fas fa-xmark">
            </i>
        </button>
    </div>
    <div class="modal-overlay" id="statsModal" style="display:none;">
        <div class="modal" style="max-width:600px;">
            <div class="modal-header">
                <h2>📊 Statistics</h2>
                <button class="close-button" id="closeStatsBtn">×</button>
            </div>
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fa-solid fa-layer-group stat-icon"></i>
                        <span class="stat-label">Total Words</span>
                        <span class="stat-value"><span id="statsMastered">0</span> / <span id="statsTotalWords">0</span></span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-chart-line stat-icon"></i>
                        <span class="stat-label">Avg. Progress</span>
                        <span class="stat-value" id="statsAvgProgress">0%</span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-bolt stat-icon"></i>
                        <span class="stat-label">Tests</span>
                        <span class="stat-value" id="statsTests">0</span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-bullseye stat-icon"></i>
                        <span class="stat-label">Accuracy</span>
                        <span class="stat-value" id="statsAccuracy">0%</span>
                    </div>
                </div>

                <div class="stats-chart-section">
                    <div class="chart-controls">
                        <button class="chart-btn active" data-period="week">Week</button>
                        <button class="chart-btn" data-period="month">Month</button>
                        <button class="chart-btn" data-period="year">Year</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
            </div> <!-- end body -->
            <div class="modal-footer modal-actions" style="justify-content: space-between;">
                <div style="font-size: 13px; color: #a6adc8;" id="statsCorrectWrong">0 - 0 (0% - 0%)</div>
                <button id="resetStatsBtn"
                        style="background-color: crimson; color: white; padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fa-solid fa-broom">
                    </i>
                    Clear
                </button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="notificationsModal" style="display:none; z-index: 99999;">
        <div class="modal" style="max-width:550px;">
            <div class="modal-header">
                <h2><i class="fas fa-bell"></i> Reminders</h2>
                <button class="close-button" id="closeNotificationsModalBtn">&times;</button>
            </div>
            <div class="modal-body">

            <div id="notificationListContainer">
                <p class="notif-empty" style="text-align: center; color: #888;">No reminders.</p>
            </div>
            </div> <!-- end body -->
            
            <div class="modal-footer modal-actions" style="margin-top: 0; display: flex; gap: 10px;">
                <button id="openAddNotificationModalBtn" style="background-color: #0077cc; color: white; flex: 1;">
                    <i class="fas fa-plus"></i> Add New Reminder
                </button>
                <button id="testNotificationBtn" style="background-color: #28a745; color: white; flex: 0 0 auto; padding: 8px 16px;" title="Test">
                    <i class="fas fa-bell"></i> Test
                </button>
            </div>
            <div id="notifDebugInfo" style="margin-top: 10px; font-size: 0.8rem; color: #888;"></div>
        </div>
    </div>

    <div class="modal-overlay" id="notifAddModal" style="display:none; z-index: 100000;">
        <div class="modal" style="max-width:450px;">
            <div class="modal-header">
                <h2 id="notifAddModalTitle">New Reminder</h2>
                <button class="close-button" id="closeNotifAddModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="notif-add-form" id="notifAddForm">
                
                <div class="notif-form-row">
                    <label>Time:</label>
                    <input type="time" id="notifTimeInput" value="12:00">
                </div>
                <div class="notif-form-row">
                    <label>Days:</label>
                    <div class="notif-weekdays" id="notifWeekdays">
                        <button type="button" class="weekday-btn" data-day="1">Mon</button>
                        <button type="button" class="weekday-btn" data-day="2">Tue</button>
                        <button type="button" class="weekday-btn" data-day="3">Wed</button>
                        <button type="button" class="weekday-btn" data-day="4">Thu</button>
                        <button type="button" class="weekday-btn" data-day="5">Fri</button>
                        <button type="button" class="weekday-btn" data-day="6">Sat</button>
                        <button type="button" class="weekday-btn" data-day="0">Sun</button>
                    </div>
                </div>

            
                <div class="notif-form-row">
                    <label>Dictionary:</label>
                    <select id="notifDictSelect"></select>
                </div>
                <div class="notif-form-row">
                    <label>Tags (Optional):</label>
                    <select id="notifTagSelect" multiple></select>
                </div>
                <div class="notif-form-row">
                    <label>Progress:</label>
                    <select id="notifProgressSelect">
                        <option value="">Global (All)</option>
                        <option value="0-99">All (Except 100%)</option>
                        <option value="0-30">0% - 30%</option>
                        <option value="31-50">31% - 50%</option>
                        <option value="51-70">51% - 70%</option>
                        <option value="71-80">71% - 80%</option>
                        <option value="81-99">81% - 99%</option>
                        <option value="100-100">Learned (100%)</option>
                    </select>
                </div>
                <div class="notif-form-actions">
                    <button id="notifSaveBtn"><i class="fas fa-check"></i> Save</button>
                    <button id="notifCancelBtn"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <h2 class="app-logo mobile-logo">Wordevo</h2>
    <div class="toast-container" id="toastContainer"></div>
</div>

<?php wp_footer(); ?>
</body>
</html>


