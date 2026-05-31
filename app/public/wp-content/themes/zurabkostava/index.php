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

// აქ დავამატეთ is_single(), რათა პოსტებიც ჩაიტვირთოს
if ( ( is_page() || is_single() ) && ! is_front_page() && have_posts() ) {
    the_post();

    // მარშრუტის დინამიური ამოღება, რომელიც ერგება როგორც გვერდებს, ისე პოსტებს
    $zk_current = rtrim( wp_parse_url( get_permalink(), PHP_URL_PATH ), '/' );
    if ( empty( $zk_current ) ) {
        $zk_current = '/';
    }

    $zk_def = isset( $zk_routes[ $zk_current ] ) ? $zk_routes[ $zk_current ] : array( 'eyebrow' => '' );

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
} elseif ( is_404() || is_archive() ) {
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
            'home' => home_url( '/' ),
            'site' => $zk_site,
    ) ); ?>;
</script>

<script>
    (function () {
        var ZK   = window.ZK || {};
        var BASE = (function () { try { return new URL(ZK.home).pathname; } catch (e) { return '/'; } })();

        var body      = document.body;
        var header    = document.getElementById('site-header');
        var toggle    = document.getElementById('navToggle');
        var nav       = document.getElementById('primaryNav');
        var mq        = window.matchMedia('(max-width: 900px)');
        var viewEl    = document.getElementById('view');
        var announcer = document.getElementById('route-announcer');
        var dropdowns = [].slice.call(nav.querySelectorAll('.has-dropdown, .has-nested-dropdown'));
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
                    var tr = d.children[0]; // ვიღებთ უშუალო შვილს
                    if (tr && tr.classList.contains('dropdown-trigger')) tr.setAttribute('aria-expanded', 'false');
                });
            }
        }
        toggle.addEventListener('click', function () { setMenu(!body.classList.contains('nav-open')); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

        dropdowns.forEach(function(dropdown) {
            var trigger = dropdown.children[0];
            if (trigger && trigger.classList.contains('dropdown-trigger')) {
                trigger.addEventListener('click', function (e) {
                    if (!mq.matches) return; // Desktop-ზე CSS hover აკეთებს საქმეს
                    e.preventDefault();
                    e.stopPropagation(); // ხელს უშლის მშობელი მენიუს დახურვას

                    var isOpen = dropdown.classList.contains('open');

                    // ვხურავთ მხოლოდ და-ძმა (Sibling) ჩაშლებს და არა მშობელს
                    var siblings = dropdown.parentElement.children;
                    for (var i = 0; i < siblings.length; i++) {
                        var sib = siblings[i];
                        if (sib !== dropdown && (sib.classList.contains('has-dropdown') || sib.classList.contains('has-nested-dropdown'))) {
                            sib.classList.remove('open');
                            var t = sib.children[0];
                            if(t && t.classList.contains('dropdown-trigger')) t.setAttribute('aria-expanded', 'false');
                        }
                    }

                    if (!isOpen) {
                        dropdown.classList.add('open');
                        trigger.setAttribute('aria-expanded', 'true');
                    } else {
                        dropdown.classList.remove('open');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
        window.addEventListener('resize', function () { if (!mq.matches) setMenu(false); });

        /* ===========================================================
           Generic SPA router — fetch the real URL, lift out #view.
           Works for ANY server-rendered route (pages, posts, archives,
           dynamic grids) because the server is the single source of
           truth for #view. No per-type route table needed.
           =========================================================== */
        function toRoute(pathname) {
            var p = pathname || '/';
            if (BASE !== '/' && p.indexOf(BASE) === 0) p = '/' + p.slice(BASE.length);
            p = p.replace(/\/+$/, '');
            return p === '' ? '/' : p;
        }
        function keyOf(u) { return u.pathname + u.search; }
        function delay(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

        /* Mark the matching nav link + any ancestor dropdown triggers active */
        function updateChrome(route) {
            [].slice.call(nav.querySelectorAll('[data-route], .dropdown-trigger')).forEach(function (el) {
                el.classList.remove('is-current');
                el.removeAttribute('aria-current');
            });
            var active = null;
            [].slice.call(nav.querySelectorAll('[data-route]')).forEach(function (el) {
                if (el.getAttribute('data-route') === route) active = el;
            });
            if (active) {
                active.classList.add('is-current');
                active.setAttribute('aria-current', 'page');
                var li = active.closest('.has-dropdown, .has-nested-dropdown');
                while (li) {
                    var tr = li.children[0];
                    if (tr && tr.classList.contains('dropdown-trigger')) tr.classList.add('is-current');
                    li = li.parentElement && li.parentElement.closest('.has-dropdown, .has-nested-dropdown');
                }
            }
        }

        function scrollTopInstant() {
            var d = document.documentElement, prev = d.style.scrollBehavior;
            d.style.scrollBehavior = 'auto';
            window.scrollTo(0, 0);
            d.style.scrollBehavior = prev;
        }

        /* Fetch a URL and lift out the server-rendered #view fragment */
        function fetchView(href) {
            return fetch(href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                if ((r.headers.get('Content-Type') || '').indexOf('text/html') === -1) throw new Error('Not HTML');
                return r.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var v   = doc.getElementById('view');
                if (!v) throw new Error('No #view in response');
                var titleEl = doc.querySelector('title');
                return {
                    html:  v.innerHTML,
                    route: v.getAttribute('data-route') || toRoute(new URL(href, location.origin).pathname),
                    title: titleEl ? titleEl.textContent : document.title
                };
            });
        }
        function getView(href, key) {
            if (cache[key]) return Promise.resolve(cache[key]);
            return fetchView(href).then(function (data) { cache[key] = data; return data; });
        }

        var token = 0;
        function navigate(href, push) {
            var u;
            try { u = new URL(href, location.origin); } catch (e) { window.location.href = href; return; }
            var key = keyOf(u);
            var t = ++token;

            setMenu(false);
            viewEl.classList.add('is-loading');

            Promise.all([
                getView(href, key).then(function (d) { return d; }, function (err) { return { error: err }; }),
                delay(200)
            ]).then(function (res) {
                if (t !== token) return;
                var data = res[0];
                if (!data || data.error) { window.location.href = href; return; } // graceful hard fallback
                if (push) history.pushState({ url: u.href }, '', u.href);
                viewEl.innerHTML = data.html;
                viewEl.setAttribute('data-route', data.route);
                document.title = data.title;
                viewEl.classList.remove('is-loading');
                updateChrome(data.route);
                if (announcer) announcer.textContent = (data.title || 'Page') + ' loaded';
                scrollTopInstant();
                viewEl.focus({ preventScroll: true });
            });
        }

        /* Is this anchor an in-app navigation, or should the browser keep it? */
        function isInternal(a, url) {
            if (url.origin !== location.origin) return false;            // external host
            if (a.hasAttribute('download')) return false;
            if (a.target && a.target !== '_self') return false;          // _blank, etc.
            if (/\bexternal\b/.test(a.getAttribute('rel') || '')) return false;
            if (/\/wp-(admin|login|json|content|includes)\b/.test(url.pathname)) return false;
            // a real file (.pdf, .jpg, .zip…) → let the browser download/open it
            if (/\.[a-z0-9]{1,8}$/i.test(url.pathname) && !/\.html?$/i.test(url.pathname)) return false;
            return true;
        }

        /* ONE delegated listener — covers the nav, grid cards, and any link
           injected into #view later, with zero rebinding. */
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a) return;
            var url;
            try { url = new URL(a.href); } catch (_) { return; }
            if (!isInternal(a, url)) return;

            if (url.pathname === location.pathname && url.search === location.search) {
                if (url.hash) return;            // same page + #anchor → let it scroll
                e.preventDefault();
                setMenu(false);
                return;
            }
            e.preventDefault();
            navigate(a.href, true);
        });

        window.addEventListener('popstate', function () { navigate(location.href, false); });

        /* Seed the cache with the already server-rendered initial view */
        cache[keyOf(location)] = {
            html:  viewEl.innerHTML,
            route: viewEl.getAttribute('data-route') || toRoute(location.pathname),
            title: document.title
        };
        updateChrome(viewEl.getAttribute('data-route') || toRoute(location.pathname));
    })();
</script>

<?php wp_footer(); ?>
</body>
</html>