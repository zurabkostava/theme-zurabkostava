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
        <?php zk_breadcrumbs(); ?>
        <h1 class="page__title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="page__description"><?php echo get_the_excerpt(); ?></p>
        <?php endif; ?>
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
    <script>
        /* Hide the native scrollbar before first paint (desktop pointers only) so
           there's no layout shift; touch / no-JS keep the native bar. */
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            document.documentElement.classList.add('custom-scroll');
        }
    </script>
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

<?php wp_footer(); ?>
</body>
</html>
