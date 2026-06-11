<?php
/*
Template Name: App - Encrolib
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php the_title(); ?> — <?php bloginfo('name'); ?></title>

    <style>
        /* --- 💎 ZK APP ESCAPE BUTTON --- */
        .zk-app-back {
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 9999;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            color: rgba(255, 255, 255, 0.6);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }
        .zk-app-back:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(-3px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* --- 💎 ZK PREMIUM UI --- */
        :root {
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: 'SF Mono', Consolas, "Courier New", monospace;
            --bg: #0a0a0c;
            --glass-bg: rgba(16, 16, 20, 0.72);
            --hairline: rgba(255, 255, 255, 0.09);
            --hairline-strong: rgba(255, 255, 255, 0.14);
            --text: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.62);
            --text-dim: rgba(255, 255, 255, 0.40);
            --accent-primary: #6200EE;
            --accent-secondary: #007aff;
        }

        body {
            font-family: var(--font-main);
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 80px 30px 30px 30px;
            box-sizing: border-box;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Ambient Orbs */
        .hero-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .hero-orb { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.3; animation: orbFloat 14s ease-in-out infinite alternate; }
        .hero-orb-1 { width: 60vw; height: 60vw; max-width: 800px; max-height: 800px; background: radial-gradient(circle, rgba(255, 42, 133, 0.12) 0%, transparent 70%); top: -20%; left: -10%; }
        .hero-orb-2 { width: 50vw; height: 50vw; max-width: 600px; max-height: 600px; background: radial-gradient(circle, rgba(0, 240, 255, 0.12) 0%, transparent 70%); bottom: -10%; right: -10%; animation-delay: -5s; animation-duration: 18s; }
        @keyframes orbFloat { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(10%, 15%) scale(1.1); } }

        .container { position: relative; z-index: 2; width: 100%; max-width: 1200px; margin: 0 auto; text-align: center; background: var(--glass-bg); backdrop-filter: blur(22px) saturate(170%); -webkit-backdrop-filter: blur(22px) saturate(170%); border-radius: 16px; border: 1px solid var(--hairline); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4); padding: 2.5rem; }
        .encrolib-title {
            position: relative;
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.05;
            margin: 0 0 2rem 0;
            color: #fff;
            text-transform: uppercase;
            text-shadow: 
                -4px -4px 16px rgba(255, 42, 133, 0.4), 
                4px 4px 16px rgba(0, 240, 255, 0.4);
            animation: glowPulse 4s infinite alternate;
        }
        @keyframes glowPulse {
            0% { text-shadow: -4px -4px 16px rgba(255, 42, 133, 0.4), 4px 4px 16px rgba(0, 240, 255, 0.4); }
            100% { text-shadow: -6px -6px 24px rgba(255, 42, 133, 0.6), 6px 6px 24px rgba(0, 240, 255, 0.6); }
        }

        .encrolib-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: -1.5rem;
            margin-bottom: 2.5rem;
            font-weight: 500;
        }

        /* DB Status Bar */
        .enc-tabs-nav { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .enc-tab-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--hairline-strong); color: var(--text-muted); padding: 12px 28px; border-radius: 30px; font-size: 0.85rem; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; width: auto; margin: 0; }
        .enc-tab-btn:hover { background: rgba(255, 255, 255, 0.08); color: var(--text); border-color: rgba(255, 255, 255, 0.28); transform: translateY(-2px); }
        .enc-tab-btn.active { background: rgba(255, 255, 255, 0.1); color: var(--text); border-color: rgba(255, 255, 255, 0.4); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); }

        /* Tabs Content */
        .enc-tab-panel { display: none; text-align: left; animation: fadeIn 0.4s ease forwards; }
        .enc-tab-panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Step Cards */
        .step-card { background: rgba(255, 255, 255, 0.015); padding: 2rem; border-radius: 12px; border: 1px solid var(--hairline); margin-bottom: 24px; position: relative; }
        .step-title { margin-top: 0; margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 500; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--hairline); padding-bottom: 12px; text-transform: uppercase; letter-spacing: 0.1em; }
        
        label { display: block; margin-bottom: 0.75rem; font-weight: 500; text-align: left; color: var(--text-dim); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
        
        /* Inputs */
        #sentenceInput, #colorsInput, #colorsOutput, #sentenceOutput, #customTitleInput, #customIndexInput, #customProjectInput, #customAuthorInput { width: 100%; padding: 14px 16px; border-radius: 10px; font-size: 0.95rem; font-family: var(--font-mono); resize: vertical; color: var(--text); background-color: rgba(255, 255, 255, 0.02); border: 1px solid var(--hairline); transition: all 0.3s ease; box-sizing: border-box; outline: none; margin-bottom: 15px; }
        #sentenceInput:focus, #colorsInput:focus, #colorsOutput:focus, #sentenceOutput:focus, #customTitleInput:focus, #customIndexInput:focus, #customProjectInput:focus, #customAuthorInput:focus { border-color: rgba(255, 255, 255, 0.25); background-color: rgba(255, 255, 255, 0.04); box-shadow: 0 0 20px rgba(255, 255, 255, 0.02); }
        
        #sentenceInput, #sentenceOutput { min-height: 180px; margin-bottom: 0; }
        #colorsInput, #colorsOutput { min-height: 120px; margin-bottom: 0; }
        .copy-group textarea { min-height: 48px; margin: 0; }
        
        /* Buttons */
        button, .import-label { display: inline-flex; justify-content: center; align-items: center; gap: 8px; color: var(--text); background: rgba(255, 255, 255, 0.04); border: 1px solid var(--hairline-strong); padding: 14px 26px; border-radius: 30px; font-size: 0.8rem; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; text-align: center; width: 100%; margin-top: 15px; box-sizing: border-box; }
        button:not(:disabled):hover, .import-label:hover { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.28); transform: translateY(-2px); }
        button:disabled { opacity: 0.4; cursor: not-allowed; }
        
        /* Primary Action Buttons */
        #toColorsButton, #toSentenceButton { background: rgba(98, 0, 238, 0.1); border-color: rgba(98, 0, 238, 0.4); color: #fff; box-shadow: 0 4px 20px rgba(98, 0, 238, 0.15); }
        #toColorsButton:not(:disabled):hover, #toSentenceButton:not(:disabled):hover { background: rgba(98, 0, 238, 0.2); border-color: rgba(98, 0, 238, 0.6); box-shadow: 0 6px 24px rgba(98, 0, 238, 0.3); }
        
        .export-container { display: flex; flex-direction: column; gap: 24px; margin-top: 15px; }
        .export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; }
        .export-grid .input-group { display: flex; flex-direction: column; }
        .export-grid .input-group label { margin-bottom: 8px; }
        .export-grid .input-group input { margin-bottom: 0; }
        .export-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .export-actions button { margin-top: 0; border-radius: 12px; font-size: 0.75rem; padding: 12px; }
        
        button.toggle-swatches { width: auto; display: block; margin: 15px auto 0 auto; padding: 10px 20px; font-size: 0.75rem; border-radius: 30px; }
        label.import-label { display: flex; margin-bottom: 0; }
        
        .copy-group { display: flex; align-items: stretch; gap: 12px; }
        .copy-group textarea { flex-grow: 1; }
        #copyHexButton { width: 140px; min-width: 140px; margin: 0; padding: 0 15px; border-radius: 10px; }
        #copyHexButton.copied-success { background: rgba(40, 167, 69, 0.15); border-color: rgba(40, 167, 69, 0.5); color: #4ade80; }
        
        /* DB Status Bar - Top Right Position */
        .db-status-bar { position: absolute; top: 24px; right: 24px; display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin: 0; z-index: 10; }
        .db-status-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(40, 167, 69, 0.1); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.2); padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; letter-spacing: 0.05em; font-family: var(--font-mono, monospace); transition: all 0.3s ease; }
        .db-status-badge.loading { background: rgba(0, 188, 212, 0.1); color: #00bcd4; border-color: rgba(0, 188, 212, 0.2); }
        .db-status-badge.error { background: rgba(220, 53, 69, 0.1); color: #dc3545; border-color: rgba(220, 53, 69, 0.2); }
        .db-status-badge.loaded { background: rgba(255, 255, 255, 0.03); color: var(--text-muted); border-color: var(--hairline-strong); }
        .subtle-icon-btn { background: rgba(255,255,255,0.03); border: 1px solid var(--hairline-strong); color: var(--text-muted); border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; padding: 0; }
        .subtle-icon-btn:hover { background: rgba(255,255,255,0.08); color: var(--text); transform: rotate(15deg); }
        .subtle-icon-btn.spin svg { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }        
        
        .switcher-container { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .switcher-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }
        .load-switcher { position: relative; display: inline-block; width: 44px; height: 24px; }
        .load-switcher input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.1); border: 1px solid var(--hairline-strong); transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: var(--text-muted); transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: rgba(255, 255, 255, 0.2); border-color: rgba(255, 255, 255, 0.4); }
        input:checked + .slider:before { transform: translateX(20px); background-color: #fff; }
        
        #colorSwatchesContainer { display: flex; flex-wrap: wrap; gap: 8px; padding: 20px; border-radius: 12px; min-height: 40px; align-items: flex-start; max-height: 85px; overflow-y: hidden; transition: max-height 0.3s ease-in-out; background-color: rgba(255, 255, 255, 0.015); border: 1px solid var(--hairline); }
        #colorSwatchesContainer.expanded { max-height: 1000px; }
        .color-swatch, .color-swatch-punctuation, .color-swatch-number, .color-swatch-linebreak, .color-swatch-paragraphbreak { flex-shrink: 0; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
        .color-swatch:hover, .color-swatch-punctuation:hover, .color-swatch-number:hover, .color-swatch-linebreak:hover, .color-swatch-paragraphbreak:hover { transform: scale(1.2) translateY(-2px); z-index: 10; box-shadow: 0 8px 16px rgba(0,0,0,0.5); }
        .color-swatch { width: 35px; height: 35px; border-radius: 8px; }
        .color-swatch-punctuation { width: 8.75px; height: 8.75px; border-radius: 50%; margin: 13.125px 0; }
        .color-swatch-number { width: 35px; height: 35px; border-radius: 50%; }
        .color-swatch-linebreak { width: 2px; height: 35px; background-color: #ff4500 !important; border-radius: 1px; }
        .color-swatch-paragraphbreak { width: 5px; height: 35px; background-color: #ff0000 !important; border-radius: 2px; }
        
        @media (max-width: 900px) {
            body { padding: 60px 15px 20px 15px; }
            .container { padding: 4.5rem 1rem 1.5rem 1rem; border-radius: 12px; } /* More top padding for absolute bar */
            .db-status-bar { top: 16px; right: 16px; }
            .enc-tabs-nav { gap: 10px; }
            .enc-tab-btn { width: 100%; text-align: center; }
            .copy-group { flex-direction: column; }
            .copy-group textarea { min-height: 100px; }
            .enc-tab-btn { width: 100%; text-align: center; }
            .zk-app-back { top: 16px; left: 16px; }
            .export-grid { grid-template-columns: 1fr; }
            .export-actions { grid-template-columns: 1fr; }
        }
    </style>
    <script>
        var zkApiSettings = {
            "root": "<?php echo esc_url_raw(rest_url()); ?>",
            "nonce": "<?php echo wp_create_nonce('wp_rest'); ?>"
        };
    </script>
</head>
<body>

<!-- Ambient Orbs from Main Site -->
<div class="hero-ambient">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
</div>

<a href="<?php echo esc_url(home_url('/')); ?>" class="zk-app-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
    Exit App
</a>

<div class="container">
    <div class="db-status-bar">
        <div class="db-status-badge loading" id="masterKeyBadge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
            <span id="statusText">Loading DB...</span>
        </div>
        <button id="forceReloadBtn" class="subtle-icon-btn" title="Force Reload (Bypass Cache)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"></path><polyline points="21 3 21 8 16 8"></polyline></svg>
        </button>
    </div>

    <h1 class="encrolib-title">EncroLib</h1>
    <p class="encrolib-subtitle">Where Words Sink In Colors</p>

    <div class="enc-tabs-nav">
        <button class="enc-tab-btn active" data-tab="tab-encode">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            Encode (Text to Art)
        </button>
        <button class="enc-tab-btn" data-tab="tab-decode">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            Decode (Art to Text)
        </button>
    </div>

    <div class="enc-tabs-content">
        <!-- ENCODE TAB -->
        <div class="enc-tab-panel active" id="tab-encode">
            <div class="step-card">
                <h3 class="step-title">Step 1: Encode Text</h3>
                <textarea id="sentenceInput" placeholder="Enter your text..."></textarea>
                <button id="toColorsButton" disabled>Convert to Colors</button>
            </div>

            <div class="step-card" id="step2-card" style="display: none;">
                <h3 class="step-title">Step 2: Visualization & Codes</h3>
                <label for="colorSwatchesContainer">Color Visualization:</label>
                <div id="colorSwatchesContainer"></div>
                <button id="toggleSwatchesButton" class="toggle-swatches" style="display: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    Expand View
                </button>

                <label for="colorsOutput" style="margin-top:15px;">Color Codes (for export):</label>
                <div class="copy-group">
                    <textarea id="colorsOutput" readonly></textarea>
                    <button id="copyHexButton" title="Copy Hex Codes">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16" style="flex-shrink: 0;">
                            <path fill-rule="evenodd" d="M14.5 1.5a.5.5 0 0 1 .5.5v12.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-12.5a.5.5 0 0 1 .5-.5h10ZM14 2H4v12h10V2Z"/>
                            <path d="M2 0a2 2 0 0 0-2 2v12.5a.5.5 0 0 0 .5.5h.5v-1H1v-12a1 1 0 0 1 1-1h10.5a.5.5 0 0 0 0-1H2Z"/>
                        </svg>
                        <span>Copy</span>
                    </button>
                </div>
            </div>

            <div class="step-card" id="step3-card" style="display: none;">
                <h3 class="step-title">Step 3: Export Studio</h3>
                <div class="export-container">
                    <div class="export-grid">
                        <div class="input-group">
                            <label for="customTitleInput">Optional Export Title:</label>
                            <input type="text" id="customTitleInput" placeholder="Enter custom subtitle...">
                        </div>
                        <div class="input-group">
                            <label for="customIndexInput">Artwork Index / Number:</label>
                            <input type="text" id="customIndexInput" placeholder="e.g., NO. 01/100, REF_001, SEQ_01">
                        </div>
                        <div class="input-group">
                            <label for="customAuthorInput">Author Name (Bottom Center):</label>
                            <input type="text" id="customAuthorInput" placeholder="e.g., BY ZURAB KOSTAVA">
                        </div>
                        <div class="input-group">
                            <label for="customProjectInput">Project Name (Bottom Center):</label>
                            <input type="text" id="customProjectInput" placeholder="e.g., Encrypted Nocturnes">
                        </div>
                    </div>

                    <div class="export-actions">
                        <button id="exportSVGButton" class="export-button" disabled>Export SVG (Vector)</button>
                        <button id="exportJPGButton" class="export-jpg-button" disabled>Export JPG (Main Cover)</button>
                        <button id="exportSlide2Button" onclick="generateAndDownloadSecondSlide()" disabled>Export Slide 2 (Wallpaper)</button>
                        <button id="exportSlide3Button" onclick="generateAndDownloadThirdSlide()" disabled>Export Slide 3 (Macro Detail)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DECODE TAB -->
        <div class="enc-tab-panel" id="tab-decode">
            <div class="step-card">
                <h3 class="step-title">Step 1: Source</h3>
                <label for="svgUploader" class="import-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    Import SVG
                </label>
                <input type="file" id="svgUploader" accept=".svg" style="display: none;">
                <p style="font-weight: 600; margin: 15px 0; color: #bdbdbd; text-align: center;">-- OR --</p>
                <label for="colorsInput">Enter color codes (space-separated):</label>
                <textarea id="colorsInput" placeholder="#... #... #..."></textarea>
                <button id="toSentenceButton" disabled>Convert to Text</button>
            </div>
            
            <div class="step-card">
                <h3 class="step-title">Step 2: Result</h3>
                <label for="sentenceOutput">Decoded Text:</label>
                <textarea id="sentenceOutput" readonly></textarea>
            </div>
        </div>
    </div>
</div>

<script>
    let wordDatabaseMap = new Map();
    let wordDatabase = [];
    const LIGATURE_SYMBOL = '⫟';
    const SEPARATOR_SYMBOL = '◡';
    const LEADING_JOINER = '◟';
    const PARAGRAPH_BREAK = '⌋';

    const IS_LOCAL_ENV = false; // უკვე ლაივზე ვართ
    const SAVE_WORD_URL = zkApiSettings.root + 'zk/v1/save-word';

    const symbols = [ '\n', PARAGRAPH_BREAK, LIGATURE_SYMBOL, SEPARATOR_SYMBOL, LEADING_JOINER,
        '!', '"', '#', '$', '%', '&', "'", '’', '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '<', '=', '>', '?', '@', '[', '\\', ']', '^', '_', '`', '{', '|', '}', '~',
        '“', '”', '‘', '’', '«', '»', '„', '“' ];

    const topEmojis = [ '😂','❤️','👍','😭','🙏','😊','🤣','🥰','😍','✔','✨','🥺','🔥','🤔','❤','💀','💯','🎉','😉','😁', '👀','😢','👏','🙌','✅','💔','👌','🤞','😔','😬','😎','😅','🙃','🙂','😍','😘','😗','😋','😛', '😜','🤪','🤨','🧐','🤓','🤩','🥳','😏','😒','😞','😟','😠','😡','🤬','🤯','😳','🥵','🥶','😱', '😨','😰','😥','😓','🤗','🤭','🤫','🤥','😶','😐','😑','🙄','😯','😦','😧','😮','😲','🥱','😴', '🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩', '👻','👽','👾','🤖','🎃','😺','😸','😹','😻' ];
    const numbers = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];
    const punctuationRegex = /[!"#$%&'()*+,-./:;<=>?@[\\\]^_`{|}~’“’”«»„]/;
    const leadingPunctuation = [ '(', '[', '{', '"', '“', '‘', '«' ];

    const wordRegex = /^[\wა-ჰ]+(?:['’][\wა-ჰ]+)?$/;

    let lastTranslation = { tokens: [], hexCodes: [] };

    function toggleColorSwatches() {
        const container = document.getElementById('colorSwatchesContainer');
        const button = document.getElementById('toggleSwatchesButton');
        const isExpanded = container.classList.toggle('expanded');

        if (isExpanded) {
            button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg> Collapse View';
        } else {
            button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg> Expand View';
        }
    }

    function copyHexCodes() {
        const outputArea = document.getElementById('colorsOutput');
        const copyButton = document.getElementById('copyHexButton');
        const buttonText = copyButton.querySelector('span');

        navigator.clipboard.writeText(outputArea.value)
            .then(() => {
                const originalText = buttonText.textContent;
                buttonText.textContent = 'Copied!';
                copyButton.classList.add('copied-success');
                copyButton.disabled = true;

                setTimeout(() => {
                    buttonText.textContent = originalText;
                    copyButton.classList.remove('copied-success');
                    copyButton.disabled = false;
                }, 1500);
            })
            .catch(err => {
                console.error('Copy failed:', err);
                alert('Copy failed. Please try to copy manually.');
            });
    }

    async function saveWordToDatabase(word) {
        console.log(`[Cloud Save] Attempting to save word: "${word}"`);
        try {
            const response = await fetch(SAVE_WORD_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': zkApiSettings.nonce // ვადასტურებთ, რომ შენ ხარ ადმინი
                },
                body: JSON.stringify({ word: word })
            });

            const data = await response.json();

            if (data.status === 'ok') {
                console.log(`✅ [Cloud Save] Successfully saved: "${data.added}"`);
            } else if (data.status === 'exists') {
                console.log(`ℹ️ [Cloud Save] Word already exists: "${data.word}"`);
            } else {
                console.error(`❌ [Cloud Save] Server Error: ${data.message || 'Unknown'}`);
            }
        } catch (error) {
            console.error(`❌ [Cloud Save] Network Error.`, error);
        }
    }

    function translateSentenceToColors() {
        const sentence = document.getElementById('sentenceInput').value;
        const swatchesContainer = document.getElementById('colorSwatchesContainer');
        const toggleButton = document.getElementById('toggleSwatchesButton');
        const convertButton = document.getElementById('toColorsButton');

        convertButton.disabled = true;
        convertButton.innerHTML = 'Generating...';
        swatchesContainer.innerHTML = '<span style="color: #999;">Loading visualization...</span>';

        setTimeout(() => {
            const normalizedSentence = sentence.replace(/’/g, "'");
            const paragraphNormalizedSentence = normalizedSentence.replace(/(\r?\n\s*\r?\n)+/g, ` ${PARAGRAPH_BREAK} `);

            let rawTokens = [];
            const numberRegex = /(\d+)/g;
            const splitByNumbers = paragraphNormalizedSentence.split(numberRegex).filter(t => t.length > 0);

            for (const part of splitByNumbers) {
                if (part.match(/^\d+$/)) {
                    rawTokens.push(...part.split(''));
                } else {
                    const subTokens = part.match(/\s+|([\wა-ჰ]+(?:['’][\wა-ჰ]+)?|[^\wა-ჰ\s])/g) || [];
                    rawTokens.push(...subTokens);
                }
            }

            let tokensWithControlSymbols = [];
            const tokenRegex = /[\wა-ჰ]+(?:['’][\wა-ჰ]+)?/;

            for (let i = 0; i < rawTokens.length; i++) {
                const current = rawTokens[i];
                const next = rawTokens[i + 1];
                const prev = rawTokens[i - 1];
                const prevActual = tokensWithControlSymbols[tokensWithControlSymbols.length - 1];
                const isCurrentPunctuation = punctuationRegex.test(current) && current !== '\n';

                if (current === PARAGRAPH_BREAK) { tokensWithControlSymbols.push(PARAGRAPH_BREAK); continue; }
                if (current.match(/^\s+$/)) continue;

                if (leadingPunctuation.includes(current) && prev && prev.match(/^\s+$/) && prevActual && tokenRegex.test(prevActual)) {
                    tokensWithControlSymbols.push(LEADING_JOINER);
                    tokensWithControlSymbols.push(current.toLowerCase());
                    continue;
                }
                if (isCurrentPunctuation && prevActual && tokenRegex.test(prevActual) && next && tokenRegex.test(next) && !next.match(/^\s+$/)) {
                    tokensWithControlSymbols.push(LIGATURE_SYMBOL);
                    tokensWithControlSymbols.push(current.toLowerCase());
                    tokensWithControlSymbols.push(LIGATURE_SYMBOL);
                    continue;
                }
                if (current === '-' && prev && prev.match(/^\s+$/) && next && next.match(/^\s+$/)) {
                    tokensWithControlSymbols.push(SEPARATOR_SYMBOL);
                    tokensWithControlSymbols.push(current.toLowerCase());
                    tokensWithControlSymbols.push(SEPARATOR_SYMBOL);
                    continue;
                }
                tokensWithControlSymbols.push(current);
            }

            let outputHexCodes = [];
            let processedTokens = [];
            const fragment = document.createDocumentFragment();

            for (const token of tokensWithControlSymbols) {
                const lowerToken = (token === '\n' || token === LIGATURE_SYMBOL || token === SEPARATOR_SYMBOL || token === LEADING_JOINER || token === PARAGRAPH_BREAK) ? token : token.toLowerCase();
                let finalIndex = wordDatabaseMap.get(lowerToken);

                if (finalIndex === undefined) {
                    wordDatabase.push(lowerToken);
                    finalIndex = wordDatabase.length - 1;
                    wordDatabaseMap.set(lowerToken, finalIndex);

                    console.log(`📝 [System] New word detected: "${lowerToken}". Sending to API...`);

                    // პირდაპირ ვუშვებთ ფუნქციას, რადგან API-ს უკვე აქვს ადმინის დაცვა (permission_callback)
                    saveWordToDatabase(lowerToken);
                }

                const swatch = document.createElement('div');
                let hex = '#DDDDDD';

                if (finalIndex !== -1) { hex = '#' + finalIndex.toString(16).padStart(6, '0'); }

                outputHexCodes.push(hex);
                processedTokens.push(token);

                swatch.style.backgroundColor = hex;
                let tooltipToken = (token === '\n') ? "LINE BREAK" : (token === PARAGRAPH_BREAK ? "PARAGRAPH BREAK (\n\n)" : (token === LIGATURE_SYMBOL ? "LIGATURE (NO SPACE)" : (token === SEPARATOR_SYMBOL ? "SEPARATOR (SPACE BOTH SIDES)" : (token === LEADING_JOINER ? "LEADING JOINER (SPACE BEFORE)" : token))));
                swatch.title = `Token: "${tooltipToken}"\nHex: ${hex}\nIndex: ${finalIndex}`;

                let cssClass = 'color-swatch';
                if (token === '\n') cssClass = 'color-swatch-linebreak';
                else if (token === PARAGRAPH_BREAK) cssClass = 'color-swatch-paragraphbreak';
                else if (numbers.includes(lowerToken)) cssClass = 'color-swatch-number';
                else if (symbols.includes(lowerToken) || lowerToken === LIGATURE_SYMBOL || lowerToken === SEPARATOR_SYMBOL || lowerToken === LEADING_JOINER) cssClass = 'color-swatch-punctuation';

                swatch.className = cssClass;
                fragment.appendChild(swatch);
            }

            swatchesContainer.innerHTML = '';
            swatchesContainer.appendChild(fragment);

            document.getElementById('colorsOutput').value = outputHexCodes.join(' ');
            lastTranslation = { tokens: processedTokens, hexCodes: outputHexCodes };

            document.getElementById('exportSVGButton').disabled = false;
            document.getElementById('exportJPGButton').disabled = false;
            if(document.getElementById('exportSlide2Button')) {
                document.getElementById('exportSlide2Button').disabled = false;
            }
            // 🎯 აი ეს 3 ხაზი ჩაამატე ზუსტად აქ:
            if(document.getElementById('exportSlide3Button')) {
                document.getElementById('exportSlide3Button').disabled = false;
            }

            convertButton.disabled = false;
            convertButton.innerHTML = 'Convert to Colors';
            document.getElementById('step2-card').style.display = 'block';
            document.getElementById('step3-card').style.display = 'block';

            setTimeout(() => {
                if (swatchesContainer.scrollHeight > 80) {
                    toggleButton.style.display = 'block';
                } else {
                    toggleButton.style.display = 'none';
                    swatchesContainer.classList.remove('expanded');
                    toggleButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg> Expand View';
                }
            }, 50);

        }, 0);
    }

    function translateColorsToSentence() {
        const colorString = document.getElementById('colorsInput').value.trim();
        const colorTokens = colorString.split(/\s+/);
        let outputWords = [];

        for (let i = 0; i < colorTokens.length; i++) {
            const hex = colorTokens[i];
            let decodedToken = "[?]";
            let isDecodedTokenNumber = false;
            let isDecodedTokenLigature = false;
            let isDecodedTokenSeparator = false;
            let isDecodedTokenLeadingJoiner = false;
            let isDecodedTokenParagraphBreak = false;

            if (hex.startsWith('#') && hex.length === 7) {
                const index = parseInt(hex.substring(1), 16);
                if (index >= 0 && index < wordDatabase.length) {
                    decodedToken = wordDatabase[index];
                    if (numbers.includes(decodedToken)) isDecodedTokenNumber = true;
                    if (decodedToken === LIGATURE_SYMBOL) isDecodedTokenLigature = true;
                    if (decodedToken === SEPARATOR_SYMBOL) isDecodedTokenSeparator = true;
                    if (decodedToken === LEADING_JOINER) isDecodedTokenLeadingJoiner = true;
                    if (decodedToken === PARAGRAPH_BREAK) isDecodedTokenParagraphBreak = true;
                } else {
                    decodedToken = "[?]";
                }
            }

            if (!isDecodedTokenLigature && !isDecodedTokenSeparator && !isDecodedTokenLeadingJoiner && !isDecodedTokenParagraphBreak) {
                outputWords.push(decodedToken);
            }

            if (isDecodedTokenParagraphBreak) {
                outputWords.push('\n\n');
                continue;
            }

            if (isDecodedTokenSeparator) {
                if (i < colorTokens.length - 1) outputWords.push(' ');
                continue;
            }

            if (isDecodedTokenLeadingJoiner) {
                if (i < colorTokens.length - 1) outputWords.push(' ');
                continue;
            }

            if (decodedToken === '\n') continue;
            if (isDecodedTokenLigature) continue;

            if (isDecodedTokenNumber) {
                if (i < colorTokens.length - 1) {
                    const nextHex = colorTokens[i+1];
                    let nextIndex = -1;
                    if (nextHex.startsWith('#') && hex.length === 7) {
                        nextIndex = parseInt(nextHex.substring(1), 16);
                    }
                    if (nextIndex !== -1 && numbers.includes(wordDatabase[nextIndex])) {
                        continue;
                    }
                }
            }

            let nextIsControlSymbol = false;
            if (i < colorTokens.length - 1) {
                const nextHex = colorTokens[i+1];
                let nextIndex = -1;
                if (nextHex.startsWith('#') && hex.length === 7) {
                    nextIndex = parseInt(nextHex.substring(1), 16);
                }
                if (nextIndex !== -1) {
                    const nextToken = wordDatabase[nextIndex];
                    if (nextToken === LIGATURE_SYMBOL || nextToken === SEPARATOR_SYMBOL || nextToken === LEADING_JOINER || nextToken === PARAGRAPH_BREAK) {
                        nextIsControlSymbol = true;
                    }
                }
            }
            if (nextIsControlSymbol) continue;

            if (i < colorTokens.length - 1) {
                const nextHex = colorTokens[i+1];
                let nextIndex = -1;
                if (nextHex.startsWith('#') && hex.length === 7) {
                    nextIndex = parseInt(nextHex.substring(1), 16);
                }
                if (nextIndex !== -1) {
                    const nextToken = wordDatabase[nextIndex];
                    if (punctuationRegex.test(nextToken) && !wordRegex.test(nextToken) && !numbers.includes(nextToken) && !topEmojis.includes(nextToken)) {
                        if (!leadingPunctuation.includes(nextToken)) {
                            continue;
                        }
                    }
                }
            }

            if (leadingPunctuation.includes(decodedToken)) {
                continue;
            }

            outputWords.push(' ');
        }

        document.getElementById('sentenceOutput').value = outputWords.join('').trimEnd();
    }

    // --- ⚖️ დამხმარე მათემატიკური ფუნქცია ოპტიმალური სვეტების საპოვნელად ---
    function getOptimalSquareCols(tokens, hexCodes) {
        const tokenCount = hexCodes.length;
        if (tokenCount === 0) return 1;

        let bestCols = 1;
        let minDiff = Infinity;
        const scanLimit = Math.min(tokenCount, 500);

        const cellSize = 30;
        const margin = 6;

        for (let c = 1; c <= scanLimit; c++) {
            let currentColumn = 0;
            let actualRows = 0;
            let maxCols = 0;

            for (let i = 0; i < hexCodes.length; i++) {
                const token = tokens[i];
                if (token === '\n' || token === PARAGRAPH_BREAK) {
                    actualRows++;
                    currentColumn = 0;
                    continue;
                }
                if (currentColumn >= c) {
                    currentColumn = 0;
                    actualRows++;
                }
                currentColumn++;
                if (currentColumn > maxCols) maxCols = currentColumn;
            }

            const totalCols = Math.max(c, maxCols);
            const totalRows = actualRows + 1;

            const gWidth = totalCols * cellSize + (totalCols - 1) * margin;
            const gHeight = totalRows * cellSize + (totalRows - 1) * margin;

            const diff = Math.abs(gWidth - gHeight);
            if (diff < minDiff) {
                minDiff = diff;
                bestCols = c;
            }
        }
        return bestCols;
    }

    // --- Function: Generate SVG Content (Strict Bounding Square & Auto-Balancing Columns) ---
    function generateSVGContent(overlayOptions = null) {
        const { tokens, hexCodes } = lastTranslation;
        if (!hexCodes || hexCodes.length === 0) return null;

        // ვპოულობთ სვეტების უზუსტეს რაოდენობას, რომელიც გადმოცემულ ტექსტს კვადრატად აქცევს
        let cols = getOptimalSquareCols(tokens, hexCodes);

        let currentColumn = 0;
        let actualRows = 0;
        let maxCols = 0;

        for (let i = 0; i < hexCodes.length; i++) {
            const token = tokens[i];
            if (token === '\n' || token === PARAGRAPH_BREAK) {
                actualRows++;
                currentColumn = 0;
                continue;
            }
            if (currentColumn >= cols) {
                currentColumn = 0;
                actualRows++;
            }
            currentColumn++;
            if (currentColumn > maxCols) maxCols = currentColumn;
        }

        const totalCols = Math.max(cols, maxCols);
        const totalRows = actualRows + 1;

        const cellSize = 30;
        const margin = 6;

        const wordRadius = cellSize * 0.1;
        const punctSize = cellSize * 0.4;
        const punctRadius = punctSize / 2;

        const lineBreakWidth = cellSize * 0.125;
        const paragraphBreakWidth = cellSize * 0.25;
        const lineBreakHeight = cellSize;

        const gridWidth = totalCols * cellSize + (totalCols - 1) * margin;
        const gridHeight = totalRows * cellSize + (totalRows - 1) * margin;

        // კვადრატული საბაზისო ჩარჩოს შექმნა
        const maxDim = Math.max(gridWidth, gridHeight) + margin * 2;
        const SVG_WIDTH = maxDim;
        const SVG_HEIGHT = maxDim;

        const startX = (maxDim - gridWidth) / 2;
        const startY = (maxDim - gridHeight) / 2;

        const defsBlock = `
<defs>
    <style>
        .w { width:${cellSize}px; height:${cellSize}px; rx:${wordRadius}px; }
        .n { width:${cellSize}px; height:${cellSize}px; rx:${cellSize / 2}px; }
        .p { width:${punctSize}px; height:${punctSize}px; rx:${punctRadius}px; }
    </style>
    <rect id="lb" width="${lineBreakWidth}" height="${lineBreakHeight}" fill="#ff4500" />
    <rect id="pb" width="${paragraphBreakWidth}" height="${lineBreakHeight}" fill="#ff0000" />
</defs>`;

        const colorGroups = {};
        const specialElements = [];

        let currentX = startX;
        let currentY = startY;
        currentColumn = 0;

        for (let i = 0; i < hexCodes.length; i++) {
            const hex = hexCodes[i];
            const token = tokens[i];

            if (token === '\n' || token === PARAGRAPH_BREAK) {
                const symbolId = (token === '\n') ? '#lb' : '#pb';
                currentX = startX;
                currentY += cellSize + margin;
                currentColumn = 0;

                specialElements.push(`<use href="${symbolId}" x="${startX - margin}" y="${currentY}" />`);
                continue;
            }

            if (currentColumn >= cols) {
                currentX = startX;
                currentY += cellSize + margin;
                currentColumn = 0;
            }

            let shapeClass = 'w';
            let elX = currentX;
            let elY = currentY;

            const isPunctuation = symbols.includes(token) || token === LIGATURE_SYMBOL || token === SEPARATOR_SYMBOL || token === LEADING_JOINER;
            const isNumberToken = numbers.includes(token);

            if (isPunctuation) {
                shapeClass = 'p';
                elX = currentX + (cellSize - punctSize) / 2;
                elY = currentY + (cellSize - punctSize) / 2;
            } else if (isNumberToken) {
                shapeClass = 'n';
            }

            if (!colorGroups[hex]) colorGroups[hex] = [];
            colorGroups[hex].push(`<rect class="${shapeClass}" x="${elX}" y="${elY}" />`);

            currentX += cellSize + margin;
            currentColumn++;
        }

        let svgContent = `<svg xmlns="http://www.w3.org/2000/svg" width="${SVG_WIDTH}" height="${SVG_HEIGHT}" viewBox="0 0 ${SVG_WIDTH} ${SVG_HEIGHT}">`;
        svgContent += defsBlock;

        for (const hex in colorGroups) {
            svgContent += `<g fill="${hex}">\n`;
            svgContent += colorGroups[hex].join('\n');
            svgContent += `\n</g>\n`;
        }

        svgContent += specialElements.join('\n');

        if (overlayOptions && overlayOptions.color) {
            const opacity = overlayOptions.opacity || 1.0;
            svgContent += `<rect width="100%" height="100%" fill="${overlayOptions.color}" opacity="${opacity}" x="0" y="0" />\n`;
        }

        const dataString = hexCodes.join(' ');
        svgContent += `<desc id="color-translator-data">${dataString}</desc>`;
        svgContent += `</svg>`;

        return { svg: svgContent, width: SVG_WIDTH, height: SVG_HEIGHT };
    }

    function generateAndDownloadSVG() {
        const result = generateSVGContent();
        if (!result) return;

        const blob = new Blob([result.svg], {type: 'image/svg+xml'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `color_translation_${Date.now()}.svg`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function generateAndDownloadJPG() {
        const result = generateSVGContent();
        if (!result) return;

        const svgData = result.svg;
        const svgWidth = result.width;

        const customTitle = document.getElementById('customTitleInput') ? document.getElementById('customTitleInput').value.trim() : "";
        const customIndex = document.getElementById('customIndexInput') ? document.getElementById('customIndexInput').value.trim() : "";

        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const svgUrl = URL.createObjectURL(svgBlob);
        const img = new Image();

        img.onload = function() {
            URL.revokeObjectURL(svgUrl);

            const canvas = document.createElement('canvas');
            const CANVAS_WIDTH = 1080;
            const CANVAS_HEIGHT = 1350;
            canvas.width = CANVAS_WIDTH;
            canvas.height = CANVAS_HEIGHT;
            const ctx = canvas.getContext('2d');

            const bgGradient = ctx.createLinearGradient(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
            bgGradient.addColorStop(0, '#060e29');
            bgGradient.addColorStop(0.5, '#020617');
            bgGradient.addColorStop(1, '#01030a');
            ctx.fillStyle = bgGradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const cx = CANVAS_WIDTH / 2;
            const cy = CANVAS_HEIGHT / 2;
            const glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, CANVAS_WIDTH * 0.8);
            glow.addColorStop(0, 'rgba(30, 41, 59, 0.4)');
            glow.addColorStop(1, 'rgba(2, 6, 23, 0)');
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const pinkGlow = ctx.createLinearGradient(0, CANVAS_HEIGHT, 0, CANVAS_HEIGHT * 0.5);
            pinkGlow.addColorStop(0, 'rgba(229, 3, 216, 0.05)');
            pinkGlow.addColorStop(1, 'rgba(229, 3, 216, 0)');
            ctx.fillStyle = pinkGlow;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.save();
            ctx.font = '10px "SF Mono", "Consolas", "Monaco", monospace';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.02)';
            ctx.textAlign = 'left';

            const charWidth = 14;
            const rowHeight = 22;
            const matrixChars = "010101010101ABCDEFUX";

            for (let y = rowHeight; y < CANVAS_HEIGHT; y += rowHeight) {
                for (let x = 0; x < CANVAS_WIDTH; x += charWidth) {
                    if (Math.random() > 0.25) {
                        const randomChar = matrixChars[Math.floor(Math.random() * matrixChars.length)];
                        ctx.fillText(randomChar, x, y);
                    }
                }
            }
            ctx.restore();

            const targetSize = 700;
            const scale = targetSize / svgWidth;
            const offsetX = (CANVAS_WIDTH - targetSize) / 2;
            const offsetY = (CANVAS_HEIGHT - targetSize) / 2;

            const haloScale = 1.3;
            const haloOffsetX = (CANVAS_WIDTH - (targetSize * haloScale)) / 2;
            const haloOffsetY = (CANVAS_HEIGHT - (targetSize * haloScale)) / 2;

            ctx.save();
            ctx.translate(haloOffsetX, haloOffsetY);
            ctx.scale(scale * haloScale, scale * haloScale);
            ctx.filter = 'blur(80px) opacity(15%)';
            ctx.drawImage(img, 0, 0);
            ctx.restore();
            ctx.filter = 'none';

            ctx.shadowColor = 'rgba(229, 3, 216, 0.15)';
            ctx.shadowBlur = 100;
            ctx.shadowOffsetY = 0;

            ctx.save();
            ctx.translate(offsetX, offsetY);
            ctx.scale(scale, scale);
            ctx.drawImage(img, 0, 0);
            ctx.restore();

            ctx.shadowColor = 'rgba(0, 0, 0, 0.95)';
            ctx.shadowBlur = 60;
            ctx.shadowOffsetY = 35;

            ctx.save();
            ctx.translate(offsetX, offsetY);
            ctx.scale(scale, scale);
            ctx.drawImage(img, 0, 0);
            ctx.restore();

            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            ctx.shadowOffsetY = 0;

            ctx.textAlign = 'center';

            ctx.font = '900 40px "Montserrat", "Inter", "Helvetica Neue", sans-serif';
            ctx.letterSpacing = "6px";
            ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.fillText('ENCROLIB', cx, 235);

            if (customTitle !== "") {
                ctx.font = '500 19px "Montserrat", "Inter", "Helvetica Neue", sans-serif';
                ctx.letterSpacing = "5px";
                ctx.fillStyle = '#16eb99';
                ctx.fillText(customTitle.toUpperCase(), cx, 280);
            }

            const customProject = document.getElementById('customProjectInput') ? document.getElementById('customProjectInput').value.trim() : "";
            if (customProject !== "") {
                ctx.font = '400 24px "SF Mono", "Consolas", "Monaco", monospace';
                ctx.letterSpacing = "6px";
                ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
                ctx.fillText(customProject, cx, 1090);
            }

            const customAuthor = document.getElementById('customAuthorInput') ? document.getElementById('customAuthorInput').value.trim() : "";
            if (customAuthor !== "") {
                ctx.font = '600 16px "SF Mono", "Consolas", "Monaco", monospace';
                ctx.letterSpacing = "4px";
                ctx.fillStyle = 'rgba(255, 255, 255, 0.18)';
                ctx.fillText(customAuthor.toUpperCase(), cx, 1126);
            }

            if (customIndex !== "") {
                ctx.font = '400 14px "SF Mono", "Consolas", "Monaco", monospace';
                ctx.letterSpacing = "4px";
                ctx.fillStyle = 'rgba(255, 255, 255, 0.14)';
                ctx.fillText(customIndex.toUpperCase(), cx, 1158);
            }

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            for (let i = 0; i < data.length; i += 4) {
                const noise = (Math.random() - 0.5) * 12;
                data[i] = Math.min(255, Math.max(0, data[i] + noise));
                data[i+1] = Math.min(255, Math.max(0, data[i+1] + noise));
                data[i+2] = Math.min(255, Math.max(0, data[i+2] + noise));
            }
            ctx.putImageData(imageData, 0, 0);

            const jpgUrl = canvas.toDataURL('image/jpeg', 0.98);
            const a = document.createElement('a');
            a.href = jpgUrl;
            const refNum = customIndex || "REF_001";
            a.download = `1-${refNum} - main.jpg`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        };

        img.onerror = function(err) {
            console.error("Error converting SVG to Canvas:", err);
            alert("JPG export failed.");
        };

        img.src = svgUrl;
    }


    function handleSVGImport(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = e.target.result;
            const match = text.match(/<desc id="color-translator-data">(.*?)<\/desc>/);

            if (match && match[1]) {
                const hexCodes = match[1];
                document.getElementById('colorsInput').value = hexCodes;
                document.getElementById('toSentenceButton').click();
            } else {
                alert("This SVG file does not contain 'color-translator-data'. Import failed.");
            }
        };
        reader.readAsText(file);
    }

    async function loadWordList(isForceReload = false) {
        if (isForceReload && typeof isForceReload !== 'boolean') {
            isForceReload = false; // in case event object is passed
        }
        const buttons = document.querySelectorAll('button:not(#copyHexButton):not(.enc-tab-btn):not(#forceReloadBtn)');
        buttons.forEach(button => button.disabled = true);

        const statusText = document.getElementById('statusText');
        const statusBadge = document.getElementById('masterKeyBadge');
        const reloadBtn = document.getElementById('forceReloadBtn');

        if(reloadBtn) reloadBtn.classList.add('spin');

        let BASE_KEY_URL = '/wp-content/Encrolib/words_monster.txt';
        let masterKeyUrl = BASE_KEY_URL;

        if (isForceReload) {
            const timestamp = new Date().getTime();
            masterKeyUrl = `${BASE_KEY_URL}?v=${timestamp}`;
            console.log('Cache Busting Enabled: Fetching from', masterKeyUrl);
        } else {
            console.log('Cache Busting Disabled: Fetching from (cache)', masterKeyUrl);
        }

        try {
            wordDatabase = symbols.concat(topEmojis).concat(numbers);
            statusText.textContent = `Loading DB...`;
            if (statusBadge) statusBadge.className = 'db-status-badge loading';

            const response = await fetch(masterKeyUrl);

            if (!response.ok) {
                throw new Error(`Master Key download failed! (HTTP: ${response.status})`);
            }

            const text = await response.text();
            const words = text.split('\n')
                .map(word => word.trim().toLowerCase())
                .filter(word => word.length > 0);
            wordDatabase = wordDatabase.concat(words);

            console.log('Creating word-to-index map...');
            wordDatabaseMap.clear();
            for (let i = 0; i < wordDatabase.length; i++) {
                wordDatabaseMap.set(wordDatabase[i], i);
            }
            console.log(`Map created with ${wordDatabaseMap.size} entries.`);

            statusText.textContent = `${wordDatabaseMap.size.toLocaleString()} entries`;
            if (statusBadge) statusBadge.className = 'db-status-badge loaded';

            document.getElementById('toColorsButton').disabled = false;
            document.getElementById('toSentenceButton').disabled = false;
            document.getElementById('toggleSwatchesButton').disabled = false;

        } catch (error) {
            console.error('Master Key load error:', error);
            statusText.textContent = `DB Error: ${error.message}`;
            if (statusBadge) statusBadge.className = 'db-status-badge error';
        } finally {
            if(reloadBtn) reloadBtn.classList.remove('spin');
        }
    }

    // --- Function: JPG Export for CAROUSEL SLIDE 2 (Synced 1:1 Matrix stretched to Full-Bleed Layout) ---
    // --- Function: JPG Export for CAROUSEL SLIDE 2 (Strict 40px Padding & Symmetric Data-Grid) ---
    function generateAndDownloadSecondSlide() {
        const { tokens, hexCodes } = lastTranslation;
        if (!hexCodes || hexCodes.length === 0) {
            alert("გთხოვთ, ჯერ დააკონვერტიროთ ტექსტი ფერებად!");
            return;
        }

        const tokenCount = hexCodes.length;
        const CANVAS_WIDTH = 1080;
        const CANVAS_HEIGHT = 1350;
        const TARGET_PADDING = 40; // 🎯 ზუსტი 60px პადინგი კიდეებიდან

        const canvas = document.createElement('canvas');
        canvas.width = CANVAS_WIDTH;
        canvas.height = CANVAS_HEIGHT;
        const ctx = canvas.getContext('2d');

        // --- 1. პრემიუმ ფონი (Deep Space Ambient Glow) ---
        const bgGradient = ctx.createLinearGradient(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        bgGradient.addColorStop(0, '#060e29');
        bgGradient.addColorStop(0.5, '#020617');
        bgGradient.addColorStop(1, '#01030a');
        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        const cx = CANVAS_WIDTH / 2;
        const cy = CANVAS_HEIGHT / 2;
        const glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, CANVAS_WIDTH * 0.9);
        glow.addColorStop(0, 'rgba(30, 41, 59, 0.45)');
        glow.addColorStop(1, 'rgba(2, 6, 23, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        // --- 2. ᲖᲣᲡᲢᲘ ᲞᲐᲓᲘᲜᲒᲘᲡ ᲛᲐᲗᲔᲛᲐᲢᲘᲙᲐ (60px ᲡᲐᲤᲣᲫᲕᲔᲚᲖᲔ) ---
        const availWidth = CANVAS_WIDTH - (TARGET_PADDING * 2);  // 960px სამუშაო სიგანე
        const availHeight = CANVAS_HEIGHT - (TARGET_PADDING * 2); // 1230px სამუშაო სიმაღლე
        const innerMargin = 7; // დაშორება კუბიკებს შორის

        // ვეძებთ სვეტების საუკეთესო რაოდენობას 960x1230 ფარგლებში
        let bestCols = 1;
        let maxCellSize = 0;

        for (let c = 1; c <= tokenCount; c++) {
            let currentColumn = 0;
            let actualRows = 0;
            for (let i = 0; i < hexCodes.length; i++) {
                const token = tokens[i];
                if (token === '\n' || token === PARAGRAPH_BREAK) { actualRows++; currentColumn = 0; continue; }
                if (currentColumn >= c) { currentColumn = 0; actualRows++; }
                currentColumn++;
            }
            const totalRows = actualRows + 1;

            // გამოთვლა თითოეული კონფიგურაციისთვის
            const cellSizeW = (availWidth - (c - 1) * innerMargin) / c;
            const cellSizeH = (availHeight - (totalRows - 1) * innerMargin) / totalRows;
            const cellSize = Math.min(cellSizeW, cellSizeH);

            if (cellSize > maxCellSize) {
                maxCellSize = cellSize;
                bestCols = c;
            }
        }

        const simCols = bestCols;
        const cellSize = maxCellSize;

        // რეალური რიგების ხელახალი გადამოწმება ოპტიმალური სვეტებით
        let curCol = 0, actualRows = 0;
        for (let i = 0; i < hexCodes.length; i++) {
            const t = tokens[i];
            if (t === '\n' || t === PARAGRAPH_BREAK) { actualRows++; curCol = 0; continue; }
            if (curCol >= simCols) { curCol = 0; actualRows++; }
            curCol++;
        }
        const totalRows = actualRows + 1;

        // ბადის საბოლოო ფიზიკური ზომები
        const gridWidth = simCols * cellSize + (simCols - 1) * innerMargin;
        const gridHeight = totalRows * cellSize + (totalRows - 1) * innerMargin;

        // გეომეტრიულად სუფთა ცენტრირება (ოთხივე მხარეს გარანტირებული >= 60px პადინგი)
        const startX = (CANVAS_WIDTH - gridWidth) / 2;
        const startY = (CANVAS_HEIGHT - gridHeight) / 2;

        const wordRadius = cellSize * 0.12;
        const punctSize = cellSize * 0.4;
        const lineBreakWidth = cellSize * 0.15;

        // --- 3. რენდერინგის ფუნქცია ---
        function renderShapes(c) {
            let currX = startX;
            let currY = startY;
            let currCol = 0;

            for (let i = 0; i < hexCodes.length; i++) {
                const hex = hexCodes[i];
                const token = tokens[i];

                if (token === '\n' || token === PARAGRAPH_BREAK) {
                    c.fillStyle = (token === '\n') ? '#ff4500' : '#ff0000';
                    c.fillRect(startX - lineBreakWidth - innerMargin, currY + (cellSize/2) - (cellSize/4), lineBreakWidth, cellSize/2);

                    currX = startX;
                    currY += cellSize + innerMargin;
                    currCol = 0;
                    continue;
                }

                if (currCol >= simCols) {
                    currX = startX;
                    currY += cellSize + innerMargin;
                    currCol = 0;
                }

                c.fillStyle = hex;
                const isPunct = symbols.includes(token) || token === LIGATURE_SYMBOL || token === SEPARATOR_SYMBOL || token === LEADING_JOINER;
                const isNum = numbers.includes(token);

                if (isPunct) {
                    const px = currX + (cellSize - punctSize) / 2;
                    const py = currY + (cellSize - punctSize) / 2;
                    c.beginPath();
                    c.arc(px + punctSize/2, py + punctSize/2, punctSize/2, 0, Math.PI*2);
                    c.fill();
                } else if (isNum) {
                    c.beginPath();
                    c.arc(currX + cellSize/2, currY + cellSize/2, cellSize/2, 0, Math.PI*2);
                    c.fill();
                } else {
                    const r = wordRadius;
                    c.beginPath();
                    c.moveTo(currX + r, currY);
                    c.lineTo(currX + cellSize - r, currY);
                    c.quadraticCurveTo(currX + cellSize, currY, currX + cellSize, currY + r);
                    c.lineTo(currX + cellSize, currY + cellSize - r);
                    c.quadraticCurveTo(currX + cellSize, currY + cellSize, currX + cellSize - r, currY + cellSize);
                    c.lineTo(currX + r, currY + cellSize);
                    c.quadraticCurveTo(currX, currY + cellSize, currX, currY + cellSize - r);
                    c.lineTo(currX, currY + r);
                    c.quadraticCurveTo(currX, currY, currX + r, currY);
                    c.closePath();
                    c.fill();
                }

                currX += cellSize + innerMargin;
                currCol++;
            }
        }

        // --- 4. ეფექტები და ფილტრები ---
        ctx.save();
        ctx.filter = 'blur(115px) opacity(32%)';
        renderShapes(ctx);
        ctx.restore();

        ctx.save();
        ctx.shadowColor = 'rgba(0, 0, 0, 0.95)';
        ctx.shadowBlur = 60;
        ctx.shadowOffsetY = 30;
        renderShapes(ctx);
        ctx.restore();

        // კინემატოგრაფიული ხმაური (Film Grain)
        const imgData = ctx.getImageData(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        const data = imgData.data;
        for (let i = 0; i < data.length; i += 4) {
            const n = (Math.random() - 0.5) * 12;
            data[i] += n; data[i+1] += n; data[i+2] += n;
        }
        ctx.putImageData(imgData, 0, 0);

        // --- 5. მყისიერი ჩამოტვირთვა ---
        const jpgUrl = canvas.toDataURL('image/jpeg', 0.98);
        const link = document.createElement('a');
        link.href = jpgUrl;
        const customIndex = document.getElementById('customIndexInput') ? document.getElementById('customIndexInput').value.trim() : "";
        const refNum = customIndex || "REF_001";
        link.download = `2-${refNum} - full.jpg`;
        link.click();
    }

    // --- Function: JPG Export for CAROUSEL SLIDE 3 (Symmetric Macro Detail View with DOM Fix) ---
    function generateAndDownloadThirdSlide() {
        const { tokens, hexCodes } = lastTranslation;
        if (!hexCodes || hexCodes.length === 0) {
            alert("გთხოვთ, ჯერ დააკონვერტიროთ ტექსტი ფერებად!");
            return;
        }

        const CANVAS_WIDTH = 1080;
        const CANVAS_HEIGHT = 1350;
        const canvas = document.createElement('canvas');
        canvas.width = CANVAS_WIDTH;
        canvas.height = CANVAS_HEIGHT;
        const ctx = canvas.getContext('2d');

        // --- 1. ფონის მომზადება (Deep Space Ambient Glow) ---
        const bgGradient = ctx.createLinearGradient(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        bgGradient.addColorStop(0, '#060e29');
        bgGradient.addColorStop(0.5, '#020617');
        bgGradient.addColorStop(1, '#01030a');
        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        const cx = CANVAS_WIDTH / 2;
        const cy = CANVAS_HEIGHT / 2;
        const glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, CANVAS_WIDTH * 0.9);
        glow.addColorStop(0, 'rgba(30, 41, 59, 0.45)');
        glow.addColorStop(1, 'rgba(2, 6, 23, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        // --- 2. ბადის არქიტექტურა (სლაიდ 1-ის იდენტური სვეტები) ---
        let cols = getOptimalSquareCols(tokens, hexCodes);

        const zoomFactor = 5.5; // 🎯 550% გადიდება კინემატოგრაფიული ეფექტისთვის
        const baseCellSize = 30;
        const baseMargin = 6;
        const macroCellSize = baseCellSize * zoomFactor;
        const macroMargin = baseMargin * zoomFactor;

        // --- 3. 🎯 ᲘᲓᲔᲐᲚᲣᲠᲘ ᲪᲔᲜᲢᲠᲘᲠᲔᲑᲘᲡ ᲐᲚᲒᲝᲠᲘᲗᲛᲘ ---
        // წინასწარ ვითვლით ყველა ელემენტის ზუსტ Row და Col პოზიციას
        let positions = [];
        let curC = 0, curR = 0;
        for (let i = 0; i < hexCodes.length; i++) {
            const token = tokens[i];
            if (token === '\n' || token === PARAGRAPH_BREAK) {
                curR++; curC = 0;
                positions.push({ row: curR, col: curC, isControl: true });
                continue;
            }
            if (curC >= cols) {
                curC = 0; curR++;
            }
            positions.push({ row: curR, col: curC, isControl: false });
            curC++;
        }

        // ვფილტრავთ მხოლოდ რეალურ ფერად კუბიკებს (გამომტოვებლების გარეშე)
        let validTokens = positions.filter(p => !p.isControl);
        if (validTokens.length === 0) validTokens = positions;

        // შემთხვევითობის პრინციპით ვირჩევთ სამიზნე კუბიკს, რომელიც დაჯდება პოსტერის ცენტრში
        const target = validTokens[Math.floor(Math.random() * validTokens.length)];

        // გამოვიანგარიშებთ ამ კუბიკის მაკრო-კოორდინატებს
        const targetXMacro = target.col * (macroCellSize + macroMargin) + macroCellSize / 2;
        const targetYMacro = target.row * (macroCellSize + macroMargin) + macroCellSize / 2;

        // გენერირდება საწყისი წერტილები ისე, რომ სამიზნე ობიექტი მოხვდეს ზუსტად (540, 675) კოორდინატზე
        const startX = (CANVAS_WIDTH / 2) - targetXMacro;
        const startY = (CANVAS_HEIGHT / 2) - targetYMacro;

        // --- 4. მაკრო რენდერინგის ციკლი ---
        function renderMacro(c) {
            let currCol = 0;
            let currRow = 0;
            let x = startX;
            let y = startY;

            for (let i = 0; i < hexCodes.length; i++) {
                const hex = hexCodes[i];
                const token = tokens[i];

                if (token === '\n' || token === PARAGRAPH_BREAK) {
                    currRow++; currCol = 0;
                    x = startX;
                    y = startY + currRow * (macroCellSize + macroMargin);
                    continue;
                }

                if (currCol >= cols) {
                    currCol = 0; currRow++;
                    x = startX;
                    y = startY + currRow * (macroCellSize + macroMargin);
                }

                // ვხატავთ მხოლოდ იმ ფიგურებს, რომლებიც ხილვადობის არეშია (Performance Optimization)
                if (x > -macroCellSize && x < CANVAS_WIDTH && y > -macroCellSize && y < CANVAS_HEIGHT) {
                    c.fillStyle = hex;
                    const isPunct = symbols.includes(token) || token === LIGATURE_SYMBOL || token === SEPARATOR_SYMBOL || token === LEADING_JOINER;
                    const isNum = numbers.includes(token);

                    if (isPunct || isNum) {
                        const r = isPunct ? macroCellSize * 0.2 : macroCellSize / 2;
                        c.beginPath();
                        c.arc(x + macroCellSize/2, y + macroCellSize/2, r, 0, Math.PI*2);
                        c.fill();
                    } else {
                        const r = macroCellSize * 0.12;
                        c.beginPath();
                        c.moveTo(x+r, y); c.lineTo(x+macroCellSize-r, y);
                        c.quadraticCurveTo(x+macroCellSize, y, x+macroCellSize, y+r);
                        c.lineTo(x+macroCellSize, y+macroCellSize-r);
                        c.quadraticCurveTo(x+macroCellSize, y+macroCellSize, x+macroCellSize-r, y+macroCellSize);
                        c.lineTo(x+r, y+macroCellSize);
                        c.quadraticCurveTo(x, y+macroCellSize, x, y+macroCellSize-r);
                        c.lineTo(x, y+r); c.quadraticCurveTo(x, y, x+r, y);
                        c.closePath();
                        c.fill();
                    }
                }
                x += macroCellSize + macroMargin;
                currCol++;
            }
        }

        // --- 5. ვიზუალური ფენები (Glow & Depth Shadows) ---
        ctx.save();
        ctx.filter = 'blur(135px) opacity(35%)';
        renderMacro(ctx);
        ctx.restore();

        ctx.save();
        ctx.shadowColor = 'rgba(0, 0, 0, 0.95)';
        ctx.shadowBlur = 75;
        ctx.shadowOffsetY = 35;
        renderMacro(ctx);
        ctx.restore();

        // --- 6. საგამოფენო UI Overlay ---
        ctx.font = '500 12px "SF Mono", monospace';
        ctx.letterSpacing = "3px";
        ctx.fillStyle = 'rgba(255, 255, 255, 0.28)';
        ctx.textAlign = 'left';
        ctx.fillText(`[ DETAIL_SCAN_LOC: R${target.row}:C${target.col} ]`, 60, 60);
        ctx.fillText(`[ MAGNIFICATION: ${zoomFactor}X ]`, 60, 85);

        const customIndex = document.getElementById('customIndexInput') ? document.getElementById('customIndexInput').value.trim() : "REF_001";
        ctx.textAlign = 'right';
        ctx.fillText(`${customIndex.toUpperCase()} // NCTRNS_MACRO`, CANVAS_WIDTH - 60, CANVAS_HEIGHT - 60);

        // მარცვლოვნება (Film Grain)
        const imgData = ctx.getImageData(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        const data = imgData.data;
        for (let i = 0; i < data.length; i += 4) {
            const n = (Math.random() - 0.5) * 14;
            data[i] += n; data[i+1] += n; data[i+2] += n;
        }
        ctx.putImageData(imgData, 0, 0);

        // --- 7. 🛠️ ᲒᲐᲡᲬᲝᲠᲔᲑᲣႪᲘ ᲔᲥᲡᲞᲝᲠᲢᲘ (DOM Attachment Fix) ---
        const jpgUrl = canvas.toDataURL('image/jpeg', 0.98);
        const link = document.createElement('a');
        link.href = jpgUrl;
        const refNum = customIndex || "REF_001";
        link.download = `3-${refNum} - macro.jpg`;
        document.body.appendChild(link); // 💥 ლინკი ემატება DOM-ში, რომ ბრაუზერმა არ დაბლოკოს
        link.click();
        document.body.removeChild(link);  // ჩამოტვირთვის შემდეგ მყისვე იშლება
    }

    // --- Event Listeners ---
    document.getElementById('toColorsButton').addEventListener('click', translateSentenceToColors);
    document.getElementById('toSentenceButton').addEventListener('click', translateColorsToSentence);
    document.getElementById('exportSVGButton').addEventListener('click', generateAndDownloadSVG);
    document.getElementById('exportJPGButton').addEventListener('click', generateAndDownloadJPG);
    document.getElementById('svgUploader').addEventListener('change', handleSVGImport);
    document.getElementById('toggleSwatchesButton').addEventListener('click', toggleColorSwatches);
    document.getElementById('copyHexButton').addEventListener('click', copyHexCodes);
    document.getElementById('forceReloadBtn').addEventListener('click', () => loadWordList(true));

    // Tabs Logic
    document.addEventListener('DOMContentLoaded', () => {
        const tabBtns = document.querySelectorAll('.enc-tab-btn');
        const tabPanels = document.querySelectorAll('.enc-tab-panel');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanels.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab).classList.add('active');
            });
        });
    });

    window.addEventListener('load', loadWordList);
</script>

<!-- Standalone Analytics Tracking for Encrolib -->
<script>
(function() {
    try {
        if (window.zkIsAdmin) return;
        try { 
            if (localStorage.getItem('zk_ignore_tracking') === 'true' && window.location.search.indexOf('force_track') === -1) return; 
        } catch(e) {}
        
        var apiRoute = '<?php echo esc_url(rest_url("zk/v1/sync")); ?>';
        
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        var visitorId = localStorage.getItem('zk_visitor_id');
        if (!visitorId) {
            visitorId = generateUUID();
            localStorage.setItem('zk_visitor_id', visitorId);
        }

        var sessionId = sessionStorage.getItem('zk_session_id');
        if (!sessionId) {
            sessionId = generateUUID();
            sessionStorage.setItem('zk_session_id', sessionId);
        }

        function sendTrack(country, city) {
            fetch(apiRoute, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    url: window.location.pathname, 
                    country: country || '', 
                    city: city || '',
                    visitor_id: visitorId,
                    session_id: sessionId
                }),
                keepalive: true
            }).catch(function(){});
        }

        var cachedGeo = sessionStorage.getItem('zk_geo');
        if (cachedGeo) {
            var geo = JSON.parse(cachedGeo);
            sendTrack(geo.country, geo.city);
            return;
        }

        fetch('https://ipapi.co/json/')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var cty = data.country_name || data.country;
                var ctyName = data.city || '';
                sessionStorage.setItem('zk_geo', JSON.stringify({ country: cty, city: ctyName }));
                sendTrack(cty, ctyName);
            })
            .catch(function() {
                fetch('https://get.geojs.io/v1/ip/geo.json')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        sessionStorage.setItem('zk_geo', JSON.stringify({ country: data.country, city: data.city || '' }));
                        sendTrack(data.country, data.city || '');
                    })
                    .catch(function() { sendTrack('', ''); });
            });
    } catch(e) {}
})();
</script>

</body>
</html>