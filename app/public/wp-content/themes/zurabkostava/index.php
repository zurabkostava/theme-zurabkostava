<?php
/**
 * index.php — main template
 * Zurab Kostava · ultra-minimalist portfolio
 *
 * Hybrid SSR + SPA. WordPress serves real Pages at clean URLs (/about,
 * /projects, …), so every route works on direct load, refresh, and even
 * with JavaScript disabled. The client router below then takes over with
 * the History API, fetching content from the REST API for instant,
 * reload-free navigation. All content is edited from the WP admin.
 */

$zk_routes = zk_routes();
$zk_site   = get_bloginfo( 'name' );

/* Hero markup — reused for the front-page view AND the home <template>. */
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

/* Resolve the current route + its server-rendered view. */
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

$zk_visual = in_array( $zk_current, array( '/gallery', '/photography', '/video' ), true );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

	<!-- Polite live region: announces route changes to screen readers -->
	<p class="sr-only" id="route-announcer" role="status" aria-live="polite"></p>

	<!-- ============================================================
	     STICKY GLASS HEADER
	     ============================================================ -->
	<header class="site-header" id="site-header">
		<div class="header-inner">

			<!-- Logo → home route -->
			<a class="logo" data-route="/" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $zk_site ); ?> — home">
				Zurab<span> Kostava</span>
			</a>

			<!-- Hamburger (mobile only) -->
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

			<!-- Primary navigation -->
			<nav class="primary-nav" id="primaryNav" aria-label="Primary">
				<ul class="nav-list">
					<li class="nav-item"><?php echo zk_nav_link( '/about', 'About', $zk_current ); ?></li>
					<li class="nav-item"><?php echo zk_nav_link( '/projects', 'Projects', $zk_current ); ?></li>
					<li class="nav-item"><?php echo zk_nav_link( '/music', 'Music', $zk_current ); ?></li>
					<li class="nav-item"><?php echo zk_nav_link( '/books', 'Books', $zk_current ); ?></li>

					<!-- Visual: dropdown -->
					<li class="nav-item has-dropdown">
						<button
							class="nav-link dropdown-trigger<?php echo $zk_visual ? ' is-current' : ''; ?>"
							type="button"
							aria-haspopup="true"
							aria-expanded="false">
							Visual
							<svg class="dropdown-caret" width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true">
								<path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
						<ul class="dropdown-menu">
							<li><?php echo zk_nav_link( '/gallery', 'Gallery', $zk_current, 'dropdown-link' ); ?></li>
							<li><?php echo zk_nav_link( '/photography', 'Photography', $zk_current, 'dropdown-link' ); ?></li>
							<li><?php echo zk_nav_link( '/video', 'Video', $zk_current, 'dropdown-link' ); ?></li>
						</ul>
					</li>
				</ul>
			</nav>

		</div>
	</header>

	<!-- ============================================================
	     SPA CONTENT SURFACE
	     The router swaps the inner HTML of #view. On first load this is
	     already server-rendered (above), so there is no flash and no JS
	     dependency for the initial paint.
	     ============================================================ -->
	<main id="app">
		<article id="view" class="view" data-route="<?php echo esc_attr( $zk_current ); ?>" tabindex="-1">
			<?php echo $zk_view; // phpcs:ignore WordPress.Security.EscapeOutput — built from WP core output (the_content / esc_html). ?>
		</article>
	</main>

	<!-- Home view kept client-side so the router can return home without a fetch -->
	<template id="view-home"><?php echo $zk_hero; // phpcs:ignore WordPress.Security.EscapeOutput ?></template>

	<!-- Data handed to the router -->
	<script>
	window.ZK = <?php echo wp_json_encode( array(
		'home'   => home_url( '/' ),
		'rest'   => esc_url_raw( rest_url() ),
		'nonce'  => wp_create_nonce( 'wp_rest' ),
		'site'   => $zk_site,
		'routes' => $zk_routes,
	) ); ?>;
	</script>

	<!-- ============================================================
	     BEHAVIOUR — header state, mobile overlay, dropdown accordion,
	     and a dependency-free History-API router (clean URLs + REST).
	     ============================================================ -->
	<script>
	(function () {
		var ZK     = window.ZK || {};
		var ROUTES = ZK.routes || {};
		var SITE   = ZK.site || document.title;
		var REST   = ZK.rest || '/wp-json/';
		var BASE   = (function () { try { return new URL(ZK.home).pathname; } catch (e) { return '/'; } })();
		var VISUAL = ['/gallery', '/photography', '/video'];

		var body      = document.body;
		var header    = document.getElementById('site-header');
		var toggle    = document.getElementById('navToggle');
		var nav       = document.getElementById('primaryNav');
		var mq        = window.matchMedia('(max-width: 900px)');
		var dropdown  = nav.querySelector('.has-dropdown');
		var trigger   = dropdown ? dropdown.querySelector('.dropdown-trigger') : null;
		var viewEl    = document.getElementById('view');
		var homeTpl   = document.getElementById('view-home');
		var announcer = document.getElementById('route-announcer');
		var navLinks  = [].slice.call(document.querySelectorAll('.nav-link[data-route], .dropdown-link[data-route]'));
		var cache     = {};

		/* ---------- Condensed glass header on scroll ---------- */
		function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 24); }
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });

		/* ---------- Mobile overlay open / close ---------- */
		function setMenu(open) {
			body.classList.toggle('nav-open', open);
			toggle.setAttribute('aria-expanded', String(open));
			toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
			body.style.overflow = open ? 'hidden' : '';
			if (!open && dropdown) {
				dropdown.classList.remove('open');
				if (trigger) trigger.setAttribute('aria-expanded', 'false');
			}
		}
		toggle.addEventListener('click', function () { setMenu(!body.classList.contains('nav-open')); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
		if (trigger) {
			trigger.addEventListener('click', function (e) {
				if (!mq.matches) return;          // desktop: pure-CSS hover
				e.preventDefault();
				var open = dropdown.classList.toggle('open');
				trigger.setAttribute('aria-expanded', String(open));
			});
		}
		window.addEventListener('resize', function () { if (!mq.matches) setMenu(false); });

		/* ---------- Router ---------- */
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
			if (!def.slug)  return Promise.resolve(homeHTML());     // home / hero
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
			navLinks.forEach(function (a) {
				var cur = a.getAttribute('data-route') === route;
				a.classList.toggle('is-current', cur);
				if (cur) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
			});
			if (trigger) trigger.classList.toggle('is-current', VISUAL.indexOf(route) !== -1);
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
			/* Wait for content AND a min exit time so the crossfade is visible */
			Promise.all([ getHTML(route), delay(200) ]).then(function (res) {
				if (t !== token) return;          // superseded by a newer click
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
			if (url.origin !== location.origin) return;          // external → let it go
			var route = toRoute(url.pathname);
			if (!(route in ROUTES)) return;                       // non-SPA URL → full load
			e.preventDefault();
			if (url.pathname === location.pathname) { setMenu(false); return; }
			history.pushState({ route: route }, '', a.href);
			go(route);
		}
		[].slice.call(header.querySelectorAll('a[data-route]')).forEach(function (a) {
			a.addEventListener('click', onLinkClick);
		});
		window.addEventListener('popstate', function () { go(toRoute(location.pathname)); });

		/* Init — the server already rendered the current view; just seed
		   the cache and sync the nav/title (no fetch, no focus steal). */
		var initial = toRoute(location.pathname);
		if (initial in ROUTES) cache[initial] = viewEl.innerHTML;
		updateChrome(initial in ROUTES ? initial : '/');
	})();
	</script>

<?php wp_footer(); ?>
</body>
</html>
