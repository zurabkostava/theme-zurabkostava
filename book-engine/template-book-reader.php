<?php
/*
Template Name: Book Reader
*/
// The language manager removes the URL prefix before WordPress resolves the
// page, but preserves the detected language in ZK_REQUEST_LANGUAGE.
$page_language = function_exists( 'zk_get_current_language' )
    ? zk_get_current_language()
    : ( 0 === strpos( (string) get_locale(), 'ka' ) ? 'ka' : 'en' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $page_language ); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <link rel="preconnect" href="https://cblxbanbssnflgyrzhah.supabase.co" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.quilljs.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined&display=swap" rel="stylesheet">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,700;1,300&family=Noto+Sans+Georgian:wght@300;400;700;900&family=Noto+Serif+Georgian:wght@400;700;900&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,700;1,300&family=Noto+Sans+Georgian:wght@300;400;700;900&family=Noto+Serif+Georgian:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="<?php echo esc_url( get_template_directory_uri() . '/book-engine/style-book.min.css?v=' . filemtime( get_template_directory() . '/book-engine/style-book.min.css' ) ); ?>" rel="stylesheet">
    <script>
        (function(){const t=localStorage.getItem('book_theme');if(t==='light'||(t===null&&window.matchMedia('(prefers-color-scheme: light)').matches))document.documentElement.classList.add('light-mode');})();
    </script>
    <style>
        #book-loader{position:fixed;inset:0;background:#111111;z-index:999999999;display:flex;justify-content:center;align-items:center;}
        html.light-mode #book-loader{background:#ccced4;}
        html, body, * {
            -webkit-tap-highlight-color: transparent !important;
            -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
        }
        a, button, input, select, textarea {
            outline: none !important;
        }
    </style>
    <link rel="manifest" href="/wp-content/themes/zurabkostava/book-engine/manifest.json">
<?php wp_head(); ?>
</head>

<body>
<?php
// Complete SEO content is refreshed in the background. Never make the page
// wait for Supabase before returning HTML to the visitor.
$book_slug = $post->post_name;
$is_georgian = 'ka' === $page_language;
$seo_content = function_exists( 'zk_get_book_seo_content' )
    ? zk_get_book_seo_content( $book_slug, $is_georgian ? 'ka' : 'en' )
    : '';
?>

<?php if ( ! empty( $seo_content ) ) : ?>
<article id="seo-book-content" class="sr-only">
    <?php echo $seo_content; ?>
</article>
<?php endif; ?>

<div class="global-header-ui">
    <button id="theme-toggle-btn" class="lang-portal-btn theme-btn-override" title="<?php echo $is_georgian ? 'თემა' : 'Theme'; ?>">
        <span class="material-icons-outlined">light_mode</span>
    </button>
    <button id="lang-switcher-btn" class="lang-portal-btn"></button>
    <button id="user-auth-btn" class="lang-portal-btn" title="<?php echo $is_georgian ? 'შესვლა' : 'Sign In'; ?>">
        <span class="material-icons-outlined">person</span>
    </button>
</div>

<div id="digital-library-root">

    <div id="book-loader">
        <div class="book-animation">
            <div class="book-spine"></div>
            <div class="book-page page-1"></div>
            <div class="book-page page-2"></div>
            <div class="book-page page-3"></div>
        </div>
    </div>

    <div id="book-engine-wrapper" data-force-slug="<?php echo esc_attr( $post->post_name ); ?>"></div>

    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h2 id="sidebar-main-title"><?php echo $is_georgian ? 'სარჩევი' : 'CONTENTS'; ?></h2>
            <button id="toggle-btn" class="toggle-btn"><span>&times;</span></button>
        </div>
        <ul class="chapter-list" id="chapter-list-ui"></ul>
        <div class="sidebar-controls">
            <button class="font-control-btn" id="font-size-minus">−</button>
            <span class="font-display-label"><?php echo $is_georgian ? 'შრიფტის ზომა' : 'FONT SIZE'; ?></span>
            <button class="font-control-btn" id="font-size-plus">+</button>
        </div>
    </nav>

    <div class="nav-toolbar">
        <button id="open-sidebar-btn" class="tool-btn" title="<?php echo $is_georgian ? 'მენიუ' : 'Menu'; ?>">
            <span class="material-icons-outlined">menu</span>
        </button>

        <button id="open-glossary-btn" class="tool-btn" title="<?php echo $is_georgian ? 'განმარტებები' : 'Glossary'; ?>">
            <span class="material-icons-outlined">auto_stories</span>
        </button>

        <button id="open-desc-btn" class="tool-btn" title="<?php echo $is_georgian ? 'წიგნის შესახებ' : 'About Book'; ?>">
            <span class="material-icons-outlined">info</span>
        </button>

        <a id="lib-home-btn" class="lib-home-btn notranslate skiptranslate"
           href="<?php echo esc_url( home_url( zk_get_language_path( '/books/', $page_language ) ) ); ?>"
           title="<?php echo $is_georgian ? 'მთავარ საიტზე' : 'Go to main site'; ?>"
           aria-label="<?php echo $is_georgian ? 'წიგნების ბიბლიოთეკაში დაბრუნება' : 'Return to the book library'; ?>"
           translate="no" data-no-translation data-no-dynamic-translation rel="noopener"
           style="gap: 8px;">
            <span class="material-icons-outlined" aria-hidden="true" style="font-size: 18px; color: inherit; display: block;">open_in_new</span>
            <span style="line-height: 1; color: inherit; font-weight: inherit;">LIB</span>
        </a>

    </div>

    <main id="main-content">
        <div class="site-title" translate="no">
            <h1 id="site-main-title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
            <p id="site-sub-title"></p>
        </div>


        <div id="measure-container"></div>

        <div class="book-scene"><div id="book" class="book"></div></div>
    </main>

    <div id="reading-progress-container">
        <div id="reading-progress-bar">
            <div id="reading-progress-glow"></div>
            <div id="reading-progress-label">0%</div>
        </div>
    </div>

</div> <div id="glossary-modal" class="glossary-overlay">
    <div class="glossary-content">
        <div class="glossary-header">
            <h3><?php echo $is_georgian ? 'წიგნის განმარტებები' : 'Glossary'; ?></h3>
            <button id="close-glossary-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div id="glossary-list" class="glossary-body"></div>
    </div>
</div>

<div id="description-modal" class="glossary-overlay">
    <div class="glossary-content desc-modal-content">
        <div class="glossary-header">
            <h3><?php echo $is_georgian ? 'სინოპსისი' : 'Synopsis'; ?></h3>
            <button id="close-desc-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div id="description-body" class="glossary-body description-text"></div>
    </div>
</div>
<div id="auth-modal" class="glossary-overlay">
    <div class="glossary-content auth-modal-content notranslate skiptranslate" translate="no" data-no-translation>
        <div class="glossary-header">
            <h3 id="auth-modal-title"><?php echo $is_georgian ? 'შესვლა' : 'Sign In'; ?></h3>
            <button id="close-auth-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div class="glossary-body auth-body">
            <div class="form-group" id="auth-name-group" style="display: none !important;">
                <label id="auth-name-label"><?php echo $is_georgian ? 'სრული სახელი' : 'Full Name'; ?></label>
                <input type="text" id="auth-name" placeholder="<?php echo $is_georgian ? 'ზურაბ კოსტავა' : 'John Doe'; ?>">
            </div>
            <div class="form-group">
                <label id="auth-email-label"><?php echo $is_georgian ? 'ელ. ფოსტა' : 'Email Address'; ?></label>
                <input type="email" id="auth-email" placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label id="auth-pass-label"><?php echo $is_georgian ? 'პაროლი' : 'Password'; ?></label>
                <input type="password" id="auth-password" placeholder="••••••••">
            </div>
            <div id="auth-error" class="auth-error-msg"></div>

            <button id="auth-submit-btn" class="primary-btn auth-submit"><?php echo $is_georgian ? 'შესვლა' : 'Sign In'; ?></button>

            <div class="auth-divider"><span><?php echo $is_georgian ? 'ან შედიხართ' : 'or continue with'; ?></span></div>
            <button id="auth-google-btn" class="oauth-btn">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google Logo">
                <span id="auth-google-text"><?php echo $is_georgian ? 'Google - ით გაგრძელება' : 'Continue with Google'; ?></span>
            </button>

            <div class="auth-toggle-wrap">
                <span id="auth-toggle-text"><?php echo $is_georgian ? 'არ გაქვთ ანგარიში?' : "Don't have an account?"; ?></span>
                <a href="#" id="auth-toggle-link"><?php echo $is_georgian ? 'რეგისტრაცია' : 'Register'; ?></a>
            </div>
        </div>
    </div>
</div>

<div id="glossary-portal-popup" class="portal-overlay">
    <div id="footnote-tooltip" class="footnote-tooltip">
        <div id="footnote-title" class="footnote-title"></div>
        <div class="footnote-content" id="footnote-text"></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script src="<?php echo esc_url( get_template_directory_uri() . '/book-engine/script-book.min.js?v=' . filemtime( get_template_directory() . '/book-engine/script-book.min.js' ) ); ?>"></script>

<!-- Standalone Analytics Tracking for Book Engine -->
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
            var payload = JSON.stringify({ 
                url: window.location.pathname, 
                country: country || '', 
                city: city || '',
                visitor_id: visitorId,
                session_id: sessionId
            });
            var sent = false;
            if (navigator.sendBeacon) {
                try {
                    sent = navigator.sendBeacon(apiRoute, payload);
                } catch(e) {}
            }
            if (!sent) {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', apiRoute, true);
                    xhr.setRequestHeader('Content-Type', 'text/plain');
                    xhr.send(payload);
                } catch(err) {}
            }
        }

        var geoResolved = false;
        var geoTimeout = setTimeout(function() {
            if (!geoResolved) {
                geoResolved = true;
                sendTrack('', '');
            }
        }, 1500);

        try {
            var cachedGeo = sessionStorage.getItem('zk_geo');
            if (cachedGeo) {
                var geo = JSON.parse(cachedGeo);
                geoResolved = true;
                clearTimeout(geoTimeout);
                sendTrack(geo.country, geo.city);
                return;
            }
        } catch (e) {}

        fetch('https://ipapi.co/json/')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (geoResolved) return;
                geoResolved = true;
                clearTimeout(geoTimeout);
                var cty = data.country_name || data.country;
                var ctyName = data.city || '';
                try { sessionStorage.setItem('zk_geo', JSON.stringify({ country: cty, city: ctyName })); } catch (e) {}
                sendTrack(cty, ctyName);
            })
            .catch(function() {
                if (geoResolved) return;
                fetch('https://get.geojs.io/v1/ip/geo.json')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (geoResolved) return;
                        geoResolved = true;
                        clearTimeout(geoTimeout);
                        try { sessionStorage.setItem('zk_geo', JSON.stringify({ country: data.country, city: data.city || '' })); } catch (e) {}
                        sendTrack(data.country, data.city || '');
                    })
                    .catch(function() {
                        if (geoResolved) return;
                        geoResolved = true;
                        clearTimeout(geoTimeout);
                        sendTrack('', '');
                    });
            });
    } catch(e) {}
})();
</script>

</body>
</html>
