<?php
/*
Template Name: Book Reader
*/
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <link rel="preconnect" href="https://cblxbanbssnflgyrzhah.supabase.co" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.quilljs.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdn.web-fonts.ge">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined&display=swap" rel="stylesheet">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,700;1,300&family=Noto+Sans+Georgian:wght@300;400;700;900&family=Noto+Serif+Georgian:wght@400;700;900&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,700;1,300&family=Noto+Sans+Georgian:wght@300;400;700;900&family=Noto+Serif+Georgian:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="preload" href="//cdn.web-fonts.ge/fonts/bpg-arial-caps/css/bpg-arial-caps.min.css" as="style">
    <link rel="stylesheet" href="//cdn.web-fonts.ge/fonts/bpg-arial-caps/css/bpg-arial-caps.min.css">

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
<?php wp_head(); ?>
</head>

<body>
<?php
// SEO Pre-render Logic
$book_slug = $post->post_name;
$current_locale = get_locale();
$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$is_georgian = ( strpos( $request_uri, '/ka/' ) !== false || strpos( $request_uri, 'lang=ka' ) !== false || strpos( $current_locale, 'ka' ) === 0 );
$transient_key = 'zk_book_seo_v4_' . md5( $book_slug . '_' . ( $is_georgian ? 'ka' : 'en' ) );
$seo_content = get_transient( $transient_key );

echo "<!-- SEO DEBUG: Locale={$current_locale}, URI={$request_uri}, IsGeorgian=" . ($is_georgian ? 'YES' : 'NO') . " -->\n";

if ( false === $seo_content ) {
    $supabase_url = 'https://cblxbanbssnflgyrzhah.supabase.co/rest/v1/book_projects?slug=eq.' . $book_slug . '&select=chapters';
    $supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImNibHhiYW5ic3NuZmxneXJ6aGFoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjM2Mzk0NDYsImV4cCI6MjA3OTIxNTQ0Nn0.36w4C_Y8TsTJ2ifORlE5vQu-yMHYCCD-Ebetz8CpQ9A';
    
    $args = array(
        'headers' => array(
            'apikey'        => $supabase_key,
            'Authorization' => 'Bearer ' . $supabase_key,
            'Accept'        => 'application/json'
        ),
        'timeout' => 5
    );
    
    $response = wp_remote_get( $supabase_url, $args );
    
    $seo_html = '';
    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! empty( $data ) && isset( $data[0]['chapters'] ) ) {
            $chapters = $data[0]['chapters'];
            foreach ( $chapters as $chapter ) {
                $title_to_use = $is_georgian ? ( isset( $chapter['title'] ) ? $chapter['title'] : '' ) : ( isset( $chapter['title_en'] ) ? $chapter['title_en'] : ( isset( $chapter['title'] ) ? $chapter['title'] : '' ) );
                $content_to_use = $is_georgian ? ( isset( $chapter['content'] ) ? $chapter['content'] : '' ) : ( isset( $chapter['content_en'] ) ? $chapter['content_en'] : ( isset( $chapter['content'] ) ? $chapter['content'] : '' ) );
                
                if ( ! empty( $title_to_use ) ) {
                    $seo_html .= '<h2>' . esc_html( $title_to_use ) . '</h2>';
                }
                if ( ! empty( $content_to_use ) ) {
                    $seo_html .= wp_kses_post( $content_to_use );
                }
            }
            // Cache for 12 hours
            set_transient( $transient_key, $seo_html, 12 * HOUR_IN_SECONDS );
            $seo_content = $seo_html;
        }
    }
}
?>

<?php if ( ! empty( $seo_content ) ) : ?>
<article id="seo-book-content" class="sr-only">
    <?php echo $seo_content; ?>
</article>
<?php endif; ?>

<div class="global-header-ui">
    <button id="theme-toggle-btn" class="lang-portal-btn theme-btn-override" title="Theme">
        <span class="material-icons-outlined">light_mode</span>
    </button>
    <button id="lang-switcher-btn" class="lang-portal-btn"></button>
    <button id="user-auth-btn" class="lang-portal-btn" title="Sign In">
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
            <h2 id="sidebar-main-title">სარჩევი</h2>
            <button id="toggle-btn" class="toggle-btn"><span>&times;</span></button>
        </div>
        <ul class="chapter-list" id="chapter-list-ui"></ul>
        <div class="sidebar-controls">
            <button class="font-control-btn" id="font-size-minus">−</button>
            <span class="font-display-label">FONT SIZE</span>
            <button class="font-control-btn" id="font-size-plus">+</button>
        </div>
    </nav>

    <div class="nav-toolbar">
        <button id="open-sidebar-btn" class="tool-btn" title="Menu">
            <span class="material-icons-outlined">menu</span>
        </button>

        <button id="open-glossary-btn" class="tool-btn" title="განმარტებები">
            <span class="material-icons-outlined">auto_stories</span>
        </button>

        <button id="open-desc-btn" class="tool-btn" title="About Book">
            <span class="material-icons-outlined">info</span>
        </button>

    </div>

    <main id="main-content">
        <div class="site-title" translate="no">
            <h1 id="site-main-title"></h1>
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
            <h3>წიგნის განმარტებები</h3>
            <button id="close-glossary-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div id="glossary-list" class="glossary-body"></div>
    </div>
</div>

<div id="description-modal" class="glossary-overlay">
    <div class="glossary-content desc-modal-content">
        <div class="glossary-header">
            <h3>სინოპსისი</h3>
            <button id="close-desc-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div id="description-body" class="glossary-body description-text"></div>
    </div>
</div>
<div id="auth-modal" class="glossary-overlay">
    <div class="glossary-content auth-modal-content notranslate skiptranslate" translate="no" data-no-translation>
        <div class="glossary-header">
            <h3 id="auth-modal-title">Sign In</h3>
            <button id="close-auth-modal" class="glossary-close-btn">&times;</button>
        </div>
        <div class="glossary-body auth-body">
            <div class="form-group" id="auth-name-group" style="display: none !important;">
                <label id="auth-name-label">Full Name</label>
                <input type="text" id="auth-name" placeholder="John Doe">
            </div>
            <div class="form-group">
                <label id="auth-email-label">Email Address</label>
                <input type="email" id="auth-email" placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label id="auth-pass-label">Password</label>
                <input type="password" id="auth-password" placeholder="••••••••">
            </div>
            <div id="auth-error" class="auth-error-msg"></div>

            <button id="auth-submit-btn" class="primary-btn auth-submit">Sign In</button>

            <div class="auth-divider"><span>ან შედიხართ</span></div>
            <button id="auth-google-btn" class="oauth-btn">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google Logo">
                <span id="auth-google-text">Google - ით გაგრძელება</span>
            </button>

            <div class="auth-toggle-wrap">
                <span id="auth-toggle-text">Don't have an account?</span>
                <a href="#" id="auth-toggle-link">Register</a>
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
