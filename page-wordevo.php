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
    <script src="<?php echo get_template_directory_uri(); ?>/WordEvo/tts.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo get_template_directory_uri(); ?>/WordEvo/notifications.js?v=<?php echo time(); ?>"></script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="auth-container" id="authContainer">
    <div class="auth-box">
        <h2>Wordevo</h2>
        <p>შედი სისტემაში ან გაიარე რეგისტრაცია</p>
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
        <div class="auth-actions">
            <button id="loginBtn">შესვლა</button>
            <button id="registerBtn">რეგისტრაცია</button>
        </div>
        <button id="skipAuthBtn" style="margin-top:12px;padding:8px 24px;background:#e67e22;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">Skip Login (Offline)</button>
        <div class="auth-message" id="authMessage"></div>
    </div>
</div>
<div id="mainAppContainer" style="display: none;">
    <div class="header-wrapper">
        <div class="top">
        <div class="mobile-header" style="display: none;">
            <div class="app-logo">Wordevo</div>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="top-bar">
            <div class="top-left">
                <div class="app-logo">Wordevo</div>
                <select id="dictionarySelect"></select>
                <button id="addDictionaryBtn" title="ახალი ლექსიკონი"><i class="fas fa-plus"></i></button>
                <button id="deleteDictionaryBtn" title="ლექსიკონის წაშლა" style="color: #ff4757; background: rgba(255, 71, 87, 0.1);"><i class="fas fa-trash"></i></button>
            </div>
            <div class="top-center">

                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input class="modern-search-input" id="searchInput" placeholder="მოძებნე სიტყვა..." type="text"/>
                </div>
                <button id="trainingBtn">
                    <i class="fa-solid fa-award"></i>
                    ტრენინგი
                </button>

            </div>
            <div class="top-right">
                <span id="userEmailDisplay" style="font-size: 0.9rem; color: #888;"></span>
                <button id="logoutBtn" title="გასვლა">
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
                <button class="toolbar-btn" id="toggleSidebarBtn" title="თეგები">
                    <i class="fas fa-tags"></i>
                </button>
                <button class="toolbar-btn" id="statsBtn" title="სტატისტიკა">
                    <i class="fas fa-chart-pie"></i>
                </button>
                <button class="toolbar-btn" id="notificationsBtn" title="შეხსენებები">
                    <i class="fas fa-bell"></i>
                </button>
            </div>
            <div class="toolbar-center">
                <div class="sorting">
                    <i class="fas fa-sort-down" id="sortDirectionIcon">
                    </i>
                    <label class="sort-label" for="sortSelect">
                    </label>
                    <select class="toolbar-select" id="sortSelect">
                        <option value="alphabetical">
                            ანბანური
                        </option>
                        <option value="updated">
                            ბოლო
                        </option>
                        <option selected="" value="progress">
                            პროგრესით
                        </option>
                    </select>
                </div>
                <div class="hide-mastered-wrapper">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input id="hideMasteredCheckbox" type="checkbox"/>
                        <span style="font-size: 13px; color: #cdd6f4;">- ნასწავლი</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-container" id="cardContainer">
    </div>
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h2>ახალი სიტყვა</h2>
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
                           value="(ვალიდაცია)"/>
                    <span>
       საწყისი სიტყვა
      </span>
                </label>
            </div>
            <div class="input-container tag-input">
                <label class="material-input">
                    <input id="mainTranslationInput" placeholder=" " type="text"/>
                    <span>
       მთავარი თარგმანი
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
       დამატებითები თარგმანი
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
       ჩაწერე ან აარჩიე თეგი
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
       მნემონიკა (ასოციაცია დასამახსოვრებლად)
      </span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input">
                    <textarea class="form-control" id="englishSentences" placeholder=" " rows="6"></textarea>
                    <span>
       მთავარი ენის ტექსტი (Language 1)
      </span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input">
                    <textarea class="form-control" id="georgianSentences" placeholder=" " rows="5"></textarea>
                    <span>
       თარგმანი / მეორე ენის ტექსტი (Language 2)
      </span>
                </label>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions">
                <button id="saveCardBtn">
                    შენახვა
                </button>
                <button id="cancelBtn">
                    გაუქმება
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
                თეგები
            </h3>
            <button class="clear-tags-btn" id="clearTagFiltersBtn">
                ✖ ფილტრის გასუფთავება
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
                    <h2 id="previewWord" style="margin: 0; padding-right: 20px;">
                    </h2>
                    <button class="close-button" id="closePreviewBtn" style="padding: 0;">
                        ×
                    </button>
                </div>
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
                <h2>პარამეტრები</h2>
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
       აირჩიე მთავარი ენის ხმა (Lang 1)
      </span>
                    <i class="fas fa-chevron-down select-arrow-icon">
                    </i>
                </label>
            </div>
            <div>
                <label class="material-input">
      <span>
       მთავარი ენის ხმის სიჩქარე
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
       აირჩიე მეორე ენის ხმა (Lang 2)
      </span>
                    <i class="fas fa-chevron-down select-arrow-icon">
                    </i>
                </label>
            </div>
            <div>
                <label class="material-input">
      <span>
       მეორე ენის ხმის სიჩქარე
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
                    ფაილების იმპორტი / ექსპორტი
                </h3>
                <div class="button-row">
                    <button class="settings-btn blue" id="exportExcelBtn">
                        📤 ექსპორტი Excel-ში
                    </button>
                    <button class="settings-btn" id="exportMyDictionaryBtn" style="background-color: #6a1b9a; color: white;">
                        📤 ექსპორტი My Dictionary-სთვის
                    </button>
                    <label class="settings-btn settings-btn-force cyan" for="importExcelInput">
                    <label class="settings-btn settings-btn-force cyan" for="importExcelInput">
                        📥 იმპორტი Excel-დან
                    </label>
                    <input accept=".xlsx" id="importExcelInput" style="display: none;" type="file">
                    <button class="settings-btn gray" id="downloadTemplateBtn">
                        🧾 ჩამოტვირთე შაბლონი
                    </button>
                </div>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px;">
                <button id="saveVoiceBtn" style="background-color: #28a745; color: white;">
                    შენახვა
                </button>
            </div>
        </div>
    </div>

    <!-- New Dictionary Modal -->
    <div class="modal-overlay" id="addDictionaryModal" style="display: none; z-index: 2000;">
        <div class="modal">
            <div class="modal-header">
                <h2>ახალი ლექსიკონი</h2>
                <button class="close-button" id="closeAddDictionaryBtn">×</button>
            </div>
            <div class="modal-body">
            <div class="input-container">
                <label class="material-input validation">
                    <input class="form-control" id="newDictName" placeholder=" " required="" type="text" />
                    <span>ლექსიკონის სახელი</span>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="newDictLang1Select" required=""></select>
                    <span>აირჩიე მთავარი ენა (Lang 1)</span>
                    <i class="fas fa-chevron-down select-arrow-icon"></i>
                </label>
            </div>
            <div class="input-container">
                <label class="material-input material-select">
                    <select id="newDictLang2Select" required=""></select>
                    <span>აირჩიე თარგმანის ენა (Lang 2)</span>
                    <i class="fas fa-chevron-down select-arrow-icon"></i>
                </label>
            </div>
            </div> <!-- end modal-body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px;">
                <button id="saveNewDictionaryBtn" style="background-color: #28a745; color: white;">
                    შექმნა
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
                    <button class="training-close">
                        ×
                    </button>
                </div>
                <div id="globalTrainingSettings">
                    <div class="tag-filter">
                        <label for="globalTagSelect">
                            თეგი:
                        </label>
                        <select id="globalTagSelect">
                        </select>
                    </div>
                    <div class="count-filter">
                        <label for="globalQuestionCount">
                            რაოდენობა:
                        </label>
                        <input id="globalQuestionCount" max="100" min="1" type="number" value="10"/>
                    </div>
                    <div class="progress-filter">
                        <label for="globalProgressSelect">პროგრესი:</label>
                        <select id="globalProgressSelect">
                            <option value="">ნებისმიერი (ყველა)</option>
                            <option value="0-99">ყველა (100%-ის გარდა)</option>
                            <option value="0-30">0% - 30%</option>
                            <option value="31-50">31% - 50%</option>
                            <option value="51-70">51% - 70%</option>
                            <option value="71-80">71% - 80%</option>
                            <option value="81-99">81% - 99%</option>
                            <option value="100-100">შესწავლილი (100%)</option>
                        </select>
                    </div>
                    <label>
                        <input id="globalReverseToggle" type="checkbox"/>
                        რევერსი
                    </label>
                </div>
                <div class="global-start-container">
                    <button id="globalStartBtn" class="global-start-btn" onclick="startActiveGame()">დაწყება 🚀</button>
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
    <button class="add-card-btn" id="addCardBtn">
        <i class="fas fa-plus">
        </i>
    </button>
    <div class="toolbar-actions">
        <button id="deleteSelectedBtn">
            <i class="fas fa-trash">
            </i>
            წაშლა
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
        <div class="modal stats-modal-premium" style="max-width:600px;">
            <div class="modal-header">
                <h2>📊 სტატისტიკა</h2>
                <button class="close-button" id="closeStatsBtn">×</button>
            </div>
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fa-solid fa-layer-group stat-icon"></i>
                        <span class="stat-label">სულ სიტყვა</span>
                        <span class="stat-value"><span id="statsMastered">0</span> / <span id="statsTotalWords">0</span></span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-chart-line stat-icon"></i>
                        <span class="stat-label">საშ. პროგრესი</span>
                        <span class="stat-value" id="statsAvgProgress">0%</span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-bolt stat-icon"></i>
                        <span class="stat-label">ტესტირებები</span>
                        <span class="stat-value" id="statsTests">0</span>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-bullseye stat-icon"></i>
                        <span class="stat-label">სიზუსტე</span>
                        <span class="stat-value" id="statsAccuracy">0%</span>
                    </div>
                </div>

                <div class="stats-chart-section">
                    <div class="chart-controls">
                        <button class="chart-btn active" data-period="week">კვირა</button>
                        <button class="chart-btn" data-period="month">თვე</button>
                        <button class="chart-btn" data-period="year">წელი</button>
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
                    გასუფთავება
                </button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="notificationsModal" style="display:none; z-index: 99999;">
        <div class="modal" style="max-width:550px;">
            <div class="modal-header">
                <h2><i class="fas fa-bell"></i> შეხსენებები</h2>
                <button class="close-button" id="closeNotificationsModalBtn">&times;</button>
            </div>
            <div class="modal-body">

            <div id="notificationListContainer">
                <p class="notif-empty" style="text-align: center; color: #888;">შეხსენებები არ გაქვთ.</p>
            </div>

            <div class="notif-add-form" id="notifAddForm" style="display:none;">
                <h3>ახალი შეხსენება</h3>
                <div class="notif-form-row">
                    <label>დრო:</label>
                    <input type="time" id="notifTimeInput" value="12:00">
                </div>
                <div class="notif-form-row">
                    <label>დღეები:</label>
                    <div class="notif-weekdays" id="notifWeekdays">
                        <button type="button" class="weekday-btn" data-day="1">ორშ</button>
                        <button type="button" class="weekday-btn" data-day="2">სამ</button>
                        <button type="button" class="weekday-btn" data-day="3">ოთხ</button>
                        <button type="button" class="weekday-btn" data-day="4">ხუთ</button>
                        <button type="button" class="weekday-btn" data-day="5">პარ</button>
                        <button type="button" class="weekday-btn" data-day="6">შაბ</button>
                        <button type="button" class="weekday-btn" data-day="0">კვი</button>
                    </div>
                </div>
                <div class="notif-form-row">
                    <label>ლექსიკონი:</label>
                    <select id="notifDictSelect"></select>
                </div>
                <div class="notif-form-row">
                    <label>თეგები (არასავალდებულო):</label>
                    <select id="notifTagSelect" multiple></select>
                </div>
                <div class="notif-form-row">
                    <label>პროგრესი:</label>
                    <select id="notifProgressSelect">
                        <option value="">გლობალური (ყველა)</option>
                        <option value="0-99">ყველა (100%-ის გარეშე)</option>
                        <option value="0-30">0% - 30%</option>
                        <option value="31-50">31% - 50%</option>
                        <option value="51-70">51% - 70%</option>
                        <option value="71-80">71% - 80%</option>
                        <option value="81-99">81% - 99%</option>
                        <option value="100-100">ნასწავლი (100%)</option>
                    </select>
                </div>
                <div class="notif-form-actions">
                    <button id="notifSaveBtn"><i class="fas fa-check"></i> შენახვა</button>
                    <button id="notifCancelBtn"><i class="fas fa-times"></i> გაუქმება</button>
                </div>
            </div>

            </div> <!-- end body -->
            <div class="modal-footer modal-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                <button id="openAddNotificationModalBtn" style="background-color: #0077cc; color: white; flex: 1;">
                    <i class="fas fa-plus"></i> ახალი შეხსენების დამატება
                </button>
                <button id="testNotificationBtn" style="background-color: #28a745; color: white; flex: 0 0 auto; padding: 8px 16px;" title="ტესტი">
                    <i class="fas fa-bell"></i> ტესტი
                </button>
            </div>
            <div id="notifDebugInfo" style="margin-top: 10px; font-size: 0.8rem; color: #888;"></div>
        </div>
    </div>

    <h2 class="app-logo mobile-logo">Wordevo</h2>
    <div class="toast-container" id="toastContainer"></div>
</div>

<?php wp_footer(); ?>
</body>
</html>

