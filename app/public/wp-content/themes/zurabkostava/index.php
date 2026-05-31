<?php
/**
 * index.php — main template
 * Zurab Kostava · ultra-minimalist portfolio
 *
 * Single-file frontend frame. Navigation is a client-side, hash-based
 * SPA: WordPress always serves this template and the router below swaps
 * the visible .page section — no reloads.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

	<!-- ============================================================
	     STICKY GLASS HEADER
	     ============================================================ -->
	<header class="site-header" id="site-header">
		<div class="header-inner">

			<!-- Logo → home route -->
			<a class="logo" href="#/" aria-label="Zurab Kostava — home">
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
					<li class="nav-item"><a class="nav-link" href="#/about">About</a></li>
					<li class="nav-item"><a class="nav-link" href="#/projects">Projects</a></li>
					<li class="nav-item"><a class="nav-link" href="#/music">Music</a></li>
					<li class="nav-item"><a class="nav-link" href="#/books">Books</a></li>

					<!-- Visual: dropdown -->
					<li class="nav-item has-dropdown">
						<button
							class="nav-link dropdown-trigger"
							type="button"
							aria-haspopup="true"
							aria-expanded="false">
							Visual
							<svg class="dropdown-caret" width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true">
								<path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
						<ul class="dropdown-menu">
							<li><a class="dropdown-link" href="#/gallery">Gallery</a></li>
							<li><a class="dropdown-link" href="#/photography">Photography</a></li>
							<li><a class="dropdown-link" href="#/video">Video</a></li>
						</ul>
					</li>
				</ul>
			</nav>

		</div>
	</header>

	<!-- ============================================================
	     SPA VIEWS
	     One <section class="page"> per route. The router toggles
	     .is-active; only the active page is rendered. Replace the
	     placeholder copy inside each with your real content.
	     ============================================================ -->
	<main id="app">

		<!-- LANDING / HERO -->
		<section class="page page--home is-active" id="page-home"
		         data-route="/" aria-labelledby="home-title" tabindex="-1">
			<div class="hero">
				<p class="hero-eyebrow">Portfolio</p>
				<h1 class="hero-title" id="home-title">Zurab Kostava</h1>
				<p class="hero-sub">Sound, code &amp; image — composed with intent.</p>
			</div>
		</section>

		<!-- ABOUT -->
		<section class="page" id="page-about"
		         data-route="/about" aria-labelledby="about-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">01 — About</p>
				<h1 class="page__title" id="about-title">About</h1>
				<p class="page__lead">Replace with a short bio — who you are, what you make, and the thread that connects it.</p>
			</div>
		</section>

		<!-- PROJECTS -->
		<section class="page" id="page-projects"
		         data-route="/projects" aria-labelledby="projects-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">02 — Projects</p>
				<h1 class="page__title" id="projects-title">Projects</h1>
				<p class="page__lead">A selection of work lives here. Replace with your project grid or list.</p>
			</div>
		</section>

		<!-- MUSIC -->
		<section class="page" id="page-music"
		         data-route="/music" aria-labelledby="music-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">03 — Music</p>
				<h1 class="page__title" id="music-title">Music</h1>
				<p class="page__lead">Compositions, releases and sound experiments — your music content goes here.</p>
			</div>
		</section>

		<!-- BOOKS -->
		<section class="page" id="page-books"
		         data-route="/books" aria-labelledby="books-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">04 — Books</p>
				<h1 class="page__title" id="books-title">Books</h1>
				<p class="page__lead">Reading, writing, or published work — replace with your books content.</p>
			</div>
		</section>

		<!-- GALLERY -->
		<section class="page" id="page-gallery"
		         data-route="/gallery" aria-labelledby="gallery-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">05 — Visual · Gallery</p>
				<h1 class="page__title" id="gallery-title">Gallery</h1>
				<p class="page__lead">A curated gallery — drop your images or grid here.</p>
			</div>
		</section>

		<!-- PHOTOGRAPHY -->
		<section class="page" id="page-photography"
		         data-route="/photography" aria-labelledby="photography-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">06 — Visual · Photography</p>
				<h1 class="page__title" id="photography-title">Photography</h1>
				<p class="page__lead">Photographic work — replace with your photography set.</p>
			</div>
		</section>

		<!-- VIDEO -->
		<section class="page" id="page-video"
		         data-route="/video" aria-labelledby="video-title" tabindex="-1">
			<div class="page__inner">
				<p class="page__eyebrow">07 — Visual · Video</p>
				<h1 class="page__title" id="video-title">Video</h1>
				<p class="page__lead">Moving image and film — embed your videos here.</p>
			</div>
		</section>

	</main>

	<!-- ============================================================
	     BEHAVIOUR — header state, mobile overlay, dropdown accordion,
	     and a tiny dependency-free hash router. The desktop dropdown
	     itself stays pure CSS (:hover / :focus-within).
	     ============================================================ -->
	<script>
	(function () {
		var body     = document.body;
		var header   = document.getElementById('site-header');
		var toggle   = document.getElementById('navToggle');
		var nav      = document.getElementById('primaryNav');
		var mq       = window.matchMedia('(max-width: 900px)');
		var dropdown = nav.querySelector('.has-dropdown');
		var trigger  = dropdown ? dropdown.querySelector('.dropdown-trigger') : null;

		/* ---- Condensed glass header on scroll ---- */
		function onScroll() {
			header.classList.toggle('is-scrolled', window.scrollY > 24);
		}
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });

		/* ---- Mobile overlay open / close ---- */
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
		toggle.addEventListener('click', function () {
			setMenu(!body.classList.contains('nav-open'));
		});

		/* Close the overlay after any in-header navigation (logo + links) */
		header.querySelectorAll('a[href^="#"]').forEach(function (link) {
			link.addEventListener('click', function () { setMenu(false); });
		});

		/* Escape closes the overlay */
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') setMenu(false);
		});

		/* "Visual" = tap-accordion on touch / small screens */
		if (trigger) {
			trigger.addEventListener('click', function (e) {
				if (!mq.matches) return;          // desktop: pure-CSS hover
				e.preventDefault();
				var open = dropdown.classList.toggle('open');
				trigger.setAttribute('aria-expanded', String(open));
			});
		}

		/* Reset when crossing back up to desktop */
		window.addEventListener('resize', function () {
			if (!mq.matches) setMenu(false);
		});

		/* -----------------------------------------------------------
		   Tiny hash router — view-swap SPA, zero reloads
		   ----------------------------------------------------------- */
		var pages    = [].slice.call(document.querySelectorAll('.page'));
		var navLinks = [].slice.call(document.querySelectorAll('.nav-link[href^="#/"], .dropdown-link[href^="#/"]'));
		var VISUAL   = ['/gallery', '/photography', '/video'];
		var TITLES   = {
			'/':            'Zurab Kostava',
			'/about':       'About — Zurab Kostava',
			'/projects':    'Projects — Zurab Kostava',
			'/music':       'Music — Zurab Kostava',
			'/books':       'Books — Zurab Kostava',
			'/gallery':     'Gallery — Zurab Kostava',
			'/photography': 'Photography — Zurab Kostava',
			'/video':       'Video — Zurab Kostava'
		};

		function currentRoute() {
			var hash = location.hash.replace(/^#/, '');
			return hash === '' ? '/' : hash;
		}

		function render() {
			var route  = currentRoute();
			var target = null;

			pages.forEach(function (p) {
				var active = p.getAttribute('data-route') === route;
				if (active) target = p;
				p.classList.toggle('is-active', active);
			});

			/* Unknown route → fall back to home */
			if (!target) {
				route  = '/';
				target = pages[0];
				if (target) target.classList.add('is-active');
			}

			/* Reflect the active route in the nav */
			navLinks.forEach(function (a) {
				var isCurrent = a.getAttribute('href') === '#' + route;
				a.classList.toggle('is-current', isCurrent);
				if (isCurrent) { a.setAttribute('aria-current', 'page'); }
				else { a.removeAttribute('aria-current'); }
			});
			if (trigger) {
				trigger.classList.toggle('is-current', VISUAL.indexOf(route) !== -1);
			}

			document.title = TITLES[route] || TITLES['/'];

			/* Jump to top instantly (bypass the global smooth-scroll) */
			var docEl = document.documentElement;
			var prev  = docEl.style.scrollBehavior;
			docEl.style.scrollBehavior = 'auto';
			window.scrollTo(0, 0);
			docEl.style.scrollBehavior = prev;

			/* Announce the new view to assistive tech without re-scrolling */
			if (target) target.focus({ preventScroll: true });
		}

		window.addEventListener('hashchange', render);
		render(); // initial route
	})();
	</script>

<?php wp_footer(); ?>
</body>
</html>
