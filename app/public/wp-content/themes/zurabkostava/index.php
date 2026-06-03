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

    <?php
    // ვამოწმებთ, აქვს თუ არა პოსტს სურათი (Featured Image)
    $has_image = has_post_thumbnail();
    if ( $has_image ) :
        ?>
        <div class="zk-post-hero" style="background-image: url('<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'full' ) ); ?>');">
            <div class="zk-post-hero-gradient"></div>
        </div>
    <?php endif; ?>

    <div class="page__inner <?php echo $has_image ? 'has-hero' : ''; ?>">
        <?php zk_breadcrumbs(); ?>
        <?php if ( is_single() ) : ?>
            <div style="display: block; width: 100%;">
                <div class="page__meta">
                    <svg class="page__meta-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span class="page__date"><?php echo get_the_date( 'M j, Y' ); ?></span>
                </div>
            </div>
        <?php endif; ?>
        <h1 class="page__title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="page__description"><?php echo get_the_excerpt(); ?></p>
        <?php endif; ?>
        <div class="page__content"><?php the_content(); ?></div>
        <?php
        // პოსტის ნავიგაცია (Cinematic Next / Prev)
        $prev_post = get_previous_post();
        $next_post = get_next_post();

        if ( is_single() && ( $prev_post || $next_post ) ) :
            ?>
            <nav class="zk-post-nav">
                <?php if ( $prev_post ) :
                    $prev_url = get_permalink( $prev_post );
                    $prev_path = wp_parse_url( $prev_url, PHP_URL_PATH );
                    $prev_img = get_the_post_thumbnail_url( $prev_post->ID, 'large' );
                    $prev_style = $prev_img ? 'style="--nav-bg: url(\'' . esc_url( $prev_img ) . '\');"' : '';
                    ?>
                    <a href="<?php echo esc_url( $prev_url ); ?>" data-route="<?php echo esc_attr( $prev_path ); ?>" class="zk-nav-link zk-nav-prev" <?php echo $prev_style; ?>>
                        <span class="zk-nav-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Previous</span>
                        <span class="zk-nav-title"><?php echo esc_html( get_the_title( $prev_post ) ); ?></span>
                    </a>
                <?php else : ?>
                    <div class="zk-nav-link zk-nav-empty"></div>
                <?php endif; ?>

                <?php if ( $next_post ) :
                    $next_url = get_permalink( $next_post );
                    $next_path = wp_parse_url( $next_url, PHP_URL_PATH );
                    $next_img = get_the_post_thumbnail_url( $next_post->ID, 'large' );
                    $next_style = $next_img ? 'style="--nav-bg: url(\'' . esc_url( $next_img ) . '\');"' : '';
                    ?>
                    <a href="<?php echo esc_url( $next_url ); ?>" data-route="<?php echo esc_attr( $next_path ); ?>" class="zk-nav-link zk-nav-next" <?php echo $next_style; ?>>
                        <span class="zk-nav-label">Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></span>
                        <span class="zk-nav-title"><?php echo esc_html( get_the_title( $next_post ) ); ?></span>
                    </a>
                <?php else : ?>
                    <div class="zk-nav-link zk-nav-empty"></div>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
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
