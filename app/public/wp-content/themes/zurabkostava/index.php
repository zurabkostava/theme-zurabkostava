<?php
/**
 * index.php — main template
 * Zurab Kostava · ultra-minimalist portfolio
 */

$zk_routes = zk_routes();
$zk_site   = get_bloginfo( 'name' );

ob_start(); ?>
<div class="hero">
    <p class="hero-eyebrow"><?php echo esc_html( apply_filters( 'zk_hero_eyebrow', 'Portfolio' ) ); ?></p>
    <h1 class="hero-title"><?php echo esc_html( $zk_site ); ?></h1>
    <?php if ( get_bloginfo( 'description' ) ) : ?>
        <p class="hero-sub"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
    <?php endif; ?>
</div>
<?php
$zk_hero = ob_get_clean();

$zk_current = '/';
$zk_view    = $zk_hero;

if ( is_page() && ! is_front_page() && have_posts() ) {
    the_post();
    $zk_current = '/' . get_post_field( 'post_name', get_the_ID() );
    $zk_def     = isset( $zk_routes[ $zk_current ] ) ? $zk_routes[ $zk_current ] : array( 'eyebrow' => '' );

    ob_start(); ?>
    <div class="page__inner">
        <?php if ( ! empty( $zk_def['eyebrow'] ) ) : ?>
            <p class="page__eyebrow"><?php echo esc_html( $zk_def['eyebrow'] ); ?></p>
        <?php endif; ?>
        <h1 class="page__title"><?php the_title(); ?></h1>
        <div class="page__content"><?php the_content(); ?></div>
    </div>
    <?php
    $zk_view = ob_get_clean();
    rewind_posts();
} elseif ( is_404() ) {
$zk_current = '/404';
ob_start(); ?>
<div class="page__inner">
    <p class="page__eyebrow">Error 404</p>
    <h1 class="page__title">Not found</h1>
    <div class="page__content"><p>This page doesn&rsquo;t exist yet.</p></div>
</div>
<?php
$zk_view = ob_get_clean();
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<p class="sr-only" id="route-announcer" role="status" aria-live="polite"></p>

<header class="site-header" id="site-header">
    <div class="header-inner">
        <a class="logo" data-route="/" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $zk_site ); ?> — home">
            Zurab<span> Kostava</span>
        </a>

        <button
                class="nav-toggle"
                id="navToggle"
                type="button"
                aria-label="Open menu"
                aria-expanded="false"
                aria-controls="primaryNav">
				<span class="nav-toggle-box" aria-hidden="true">
					<span class="nav-toggle-line"></span>
					<span class="nav-toggle-line"></span>
					<span class="nav-toggle-line"></span>
				</span>
        </button>

        <nav class="primary-nav" id="primaryNav" aria-label="Primary">
            <?php
            if ( has_nav_menu( 'primary-menu' ) ) {
                wp_nav_menu( array(
                        'theme_location' => 'primary-menu',
                        'container'      => false,
                        'menu_class'     => 'nav-list',
                        'fallback_cb'    => false,
                        'walker'         => new ZK_SPA_Walker(),
                ) );
            } else {
                echo '<ul class="nav-list"><li class="nav-item"><a class="nav-link" data-route="/" href="' . esc_url( home_url( '/' ) ) . '">Home</a></li></ul>';
            }
            ?>
        </nav>
    </div>
</header>

<main id="app">
    <article id="view" class="view" data-route="<?php echo esc_attr( $zk_current ); ?>" tabindex="-1">
        <?php echo $zk_view; // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </article>
</main>

<template id="view-home"><?php echo $zk_hero; // phpcs:ignore WordPress.Security.EscapeOutput ?></template>

<script>
    window.ZK = <?php echo wp_json_encode( array(
            'home'   => home_url( '/' ),
            'rest'   => esc_url_raw( rest_url() ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'site'   => $zk_site,
            'routes' => $zk_routes,
    ) ); ?>;
</script>

<script>
    (function () {
        var ZK     = window.ZK || {};
        var ROUTES = ZK.routes || {};
        var SITE   = ZK.site || document.title;
        var REST   = ZK.rest || '/wp-json/';
        var BASE   = (function () { try { return new URL(ZK.home).pathname; } catch (e) { return '/'; } })();

        var body      = document.body;
        var header    = document.getElementById('site-header');
        var toggle    = document.getElementById('navToggle');
        var nav       = document.getElementById('primaryNav');
        var mq        = window.matchMedia('(max-width: 900px)');
        var viewEl    = document.getElementById('view');
        var homeTpl   = document.getElementById('view-home');
        var announcer = document.getElementById('route-announcer');
        var navLinks  = [].slice.call(document.querySelectorAll('.nav-link[data-route], .dropdown-link[data-route]'));
        var dropdowns = [].slice.call(nav.querySelectorAll('.has-dropdown'));
        var cache     = {};

        /* Header Scroll */
        function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 24); }
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        /* Mobile Menu & Multiple Dropdowns */
        function setMenu(open) {
            body.classList.toggle('nav-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            body.style.overflow = open ? 'hidden' : '';
            if (!open) {
                dropdowns.forEach(function(d) {
                    d.classList.remove('open');
                    var tr = d.querySelector('.dropdown-trigger');
                    if (tr) tr.setAttribute('aria-expanded', 'false');
                });
            }
        }

        toggle.addEventListener('click', function () { setMenu(!body.classList.contains('nav-open')); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

        dropdowns.forEach(function(dropdown) {
            var trigger = dropdown.querySelector('.dropdown-trigger');
            if (trigger) {
                trigger.addEventListener('click', function (e) {
                    if (!mq.matches) return;
                    e.preventDefault();
                    var isOpen = dropdown.classList.contains('open');
                    dropdowns.forEach(function(d) {
                        d.classList.remove('open');
                        var t = d.querySelector('.dropdown-trigger');
                        if(t) t.setAttribute('aria-expanded', 'false');
                    });
                    if (!isOpen) {
                        dropdown.classList.add('open');
                        trigger.setAttribute('aria-expanded', 'true');
                    }
                });
            }
        });
        window.addEventListener('resize', function () { if (!mq.matches) setMenu(false); });

        /* Router Functions */
        function toRoute(pathname) {
            var p = pathname || '/';
            if (BASE !== '/' && p.indexOf(BASE) === 0) p = '/' + p.slice(BASE.length);
            p = p.replace(/\/+$/, '');
            return p === '' ? '/' : p;
        }
        function delay(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

        function homeHTML() { return homeTpl ? homeTpl.innerHTML : ''; }
        function notFoundHTML() {
            return '<div class="page__inner"><p class="page__eyebrow">Error 404</p>' +
                '<h1 class="page__title">Not found</h1>' +
                '<div class="page__content"><p>This page doesn’t exist yet.</p></div></div>';
        }
        function pageHTML(route, page) {
            var def     = ROUTES[route] || {};
            var eyebrow = def.eyebrow ? '<p class="page__eyebrow">' + def.eyebrow + '</p>' : '';
            var title   = (page && page.title && page.title.rendered) || def.label || '';
            var content = (page && page.content && page.content.rendered) || '';
            return '<div class="page__inner">' + eyebrow +
                '<h1 class="page__title">' + title + '</h1>' +
                '<div class="page__content">' + content + '</div></div>';
        }

        function fetchRoute(route) {
            var def = ROUTES[route];
            if (!def)       return Promise.resolve(notFoundHTML());
            if (!def.slug)  return Promise.resolve(homeHTML());
            var url  = REST + 'wp/v2/pages?per_page=1&_fields=title,content&slug=' + encodeURIComponent(def.slug);
            var opts = ZK.nonce ? { headers: { 'X-WP-Nonce': ZK.nonce } } : {};
            return fetch(url, opts)
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (arr) { return (arr && arr.length) ? pageHTML(route, arr[0]) : notFoundHTML(); });
        }
        function getHTML(route) {
            if (cache[route] != null) return Promise.resolve(cache[route]);
            return fetchRoute(route).then(function (html) { cache[route] = html; return html; });
        }

        function updateChrome(route) {
            // Clear all active states
            navLinks.forEach(function (a) {
                a.classList.remove('is-current');
                a.removeAttribute('aria-current');
            });
            var dropdownTriggers = [].slice.call(document.querySelectorAll('.dropdown-trigger'));
            dropdownTriggers.forEach(function(btn) { btn.classList.remove('is-current'); });

            // Set active state dynamically
            var activeLink = document.querySelector('[data-route="' + route + '"]');
            if (activeLink) {
                activeLink.classList.add('is-current');
                activeLink.setAttribute('aria-current', 'page');

                // Highlight parent dropdown if nested
                var parentMenu = activeLink.closest('.dropdown-menu');
                if (parentMenu) {
                    var triggerBtn = parentMenu.previousElementSibling;
                    if (triggerBtn && triggerBtn.classList.contains('dropdown-trigger')) {
                        triggerBtn.classList.add('is-current');
                    }
                }
            }

            var def = ROUTES[route];
            document.title = (route === '/' || !def || !def.label) ? SITE : (def.label + ' — ' + SITE);
            if (announcer && def) announcer.textContent = (def.label || 'Home') + ' — loaded';
        }

        function scrollTopInstant() {
            var d = document.documentElement, prev = d.style.scrollBehavior;
            d.style.scrollBehavior = 'auto';
            window.scrollTo(0, 0);
            d.style.scrollBehavior = prev;
        }

        var token = 0;
        function go(route) {
            var known = (route in ROUTES);
            var t = ++token;
            updateChrome(known ? route : '/');
            setMenu(false);
            viewEl.classList.add('is-loading');

            Promise.all([ getHTML(route), delay(200) ]).then(function (res) {
                if (t !== token) return;
                viewEl.innerHTML = res[0];
                viewEl.setAttribute('data-route', route);
                viewEl.classList.remove('is-loading');
                scrollTopInstant();
                viewEl.focus({ preventScroll: true });
            }).catch(function () {
                if (t !== token) return;
                viewEl.innerHTML = notFoundHTML();
                viewEl.classList.remove('is-loading');
            });
        }

        function onLinkClick(e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var a = e.currentTarget, url;
            try { url = new URL(a.href); } catch (_) { return; }
            if (url.origin !== location.origin) return;
            var route = toRoute(url.pathname);
            if (!(route in ROUTES)) return;
            e.preventDefault();
            if (url.pathname === location.pathname) { setMenu(false); return; }
            history.pushState({ route: route }, '', a.href);
            go(route);
        }

        // Refresh navLinks listener attaching logic
        function bindLinks() {
            [].slice.call(header.querySelectorAll('a[data-route]')).forEach(function (a) {
                a.addEventListener('click', onLinkClick);
            });
        }
        bindLinks();

        window.addEventListener('popstate', function () { go(toRoute(location.pathname)); });

        var initial = toRoute(location.pathname);
        if (initial in ROUTES) cache[initial] = viewEl.innerHTML;
        updateChrome(initial in ROUTES ? initial : '/');
    })();
</script>

<?php wp_footer(); ?>
</body>
</html>