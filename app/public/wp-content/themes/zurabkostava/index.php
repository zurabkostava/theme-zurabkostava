<?php
/**
 * index.php — main template
 * Zurab Kostava · ultra-minimalist portfolio
 *
 * The whole document lives here by design (no header.php/footer.php),
 * so the entire frontend frame stays in one readable file.
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

	<!-- Accessibility: lets keyboard users skip straight to content -->
	<a class="skip-link" href="#main">Skip to content</a>

	<!-- ============================================================
	     FULLSCREEN BACKGROUND VIDEO
	     Drop your file at assets/videos/background.mp4 (and a poster
	     image at assets/images/poster.jpg) or swap the paths below.
	     ============================================================ -->
	<video
		class="fullscreen-video"
		autoplay loop muted playsinline
		poster="<?php echo esc_url( get_theme_file_uri( 'assets/images/poster.jpg' ) ); ?>">
		<source src="<?php echo esc_url( get_theme_file_uri( 'assets/videos/background.mp4' ) ); ?>" type="video/mp4">
	</video>
	<!-- Gradient scrim over the video for guaranteed text readability -->
	<div class="video-overlay" aria-hidden="true"></div>

	<!-- ============================================================
	     STICKY GLASS HEADER
	     ============================================================ -->
	<header class="site-header" id="site-header">
		<div class="header-inner">

			<!-- Logo -->
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Zurab Kostava — home">
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
					<li class="nav-item"><a class="nav-link" href="#about">About</a></li>
					<li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
					<li class="nav-item"><a class="nav-link" href="#music">Music</a></li>
					<li class="nav-item"><a class="nav-link" href="#books">Books</a></li>

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
							<li><a class="dropdown-link" href="#gallery">Gallery</a></li>
							<li><a class="dropdown-link" href="#photography">Photography</a></li>
							<li><a class="dropdown-link" href="#video">Video</a></li>
						</ul>
					</li>
				</ul>
			</nav>

		</div>
	</header>

	<!-- ============================================================
	     PAGE CONTENT
	     Minimal placeholder sections so the nav anchors + scroll
	     behaviour are testable. Replace freely with your real content.
	     ============================================================ -->
	<main id="main">

		<section class="hero" id="about">
			<p class="hero-eyebrow">Portfolio</p>
			<h1 class="hero-title">Zurab Kostava</h1>
			<p class="hero-sub">Sound, code &amp; image — composed with intent.</p>
		</section>

		<section class="section" id="projects"><h2 class="section-title">Projects</h2></section>
		<section class="section" id="music"><h2 class="section-title">Music</h2></section>
		<section class="section" id="books"><h2 class="section-title">Books</h2></section>
		<section class="section" id="gallery"><h2 class="section-title">Gallery</h2></section>
		<section class="section" id="photography"><h2 class="section-title">Photography</h2></section>
		<section class="section" id="video"><h2 class="section-title">Video</h2></section>

	</main>

	<!-- ============================================================
	     NAV BEHAVIOUR — minimal, dependency-free, progressive
	     enhancement. The desktop dropdown is pure CSS (:hover /
	     :focus-within); this only powers the scrolled-header state,
	     the mobile overlay, and the mobile tap-accordion.
	     ============================================================ -->
	<script>
	(function () {
		var body   = document.body;
		var header = document.getElementById('site-header');
		var toggle = document.getElementById('navToggle');
		var nav    = document.getElementById('primaryNav');
		var mq     = window.matchMedia('(max-width: 900px)');

		/* Condensed glass state once the user scrolls */
		function onScroll() {
			header.classList.toggle('is-scrolled', window.scrollY > 24);
		}
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });

		/* Open / close the mobile overlay */
		function setMenu(open) {
			body.classList.toggle('nav-open', open);
			toggle.setAttribute('aria-expanded', String(open));
			toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
			body.style.overflow = open ? 'hidden' : '';
		}
		toggle.addEventListener('click', function () {
			setMenu(!body.classList.contains('nav-open'));
		});

		/* Close after picking a destination */
		nav.querySelectorAll('a').forEach(function (link) {
			link.addEventListener('click', function () { setMenu(false); });
		});

		/* Escape closes the overlay */
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') setMenu(false);
		});

		/* "Visual" becomes a tap-accordion on touch / small screens */
		var dropdown = nav.querySelector('.has-dropdown');
		var trigger  = dropdown.querySelector('.dropdown-trigger');
		trigger.addEventListener('click', function (e) {
			if (!mq.matches) return; // desktop = pure-CSS hover, do nothing
			e.preventDefault();
			var open = dropdown.classList.toggle('open');
			trigger.setAttribute('aria-expanded', String(open));
		});

		/* Reset everything when crossing back to desktop */
		window.addEventListener('resize', function () {
			if (!mq.matches) {
				setMenu(false);
				dropdown.classList.remove('open');
				trigger.setAttribute('aria-expanded', 'false');
			}
		});

		/* Respect reduced-motion: pause the looping video */
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			var v = document.querySelector('.fullscreen-video');
			if (v) v.removeAttribute('autoplay'), v.pause && v.pause();
		}
	})();
	</script>

<?php wp_footer(); ?>
</body>
</html>
