<?php
/**
 * index.php — main template
 * Zurab Kostava · ultra-minimalist portfolio
 */

$zk_routes = zk_routes();
$zk_site   = get_bloginfo( 'name' );

ob_start(); ?>
<div class="hero">
    <!-- Ambient Cinematic Background -->
    <div class="hero-ambient">
        <canvas id="zk-starfield" class="zk-starfield"></canvas>
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
    </div>
    
    <!-- Cinematic Content -->
    <div class="hero-inner">
        <div class="hero-eyebrow-wrap">
            <p class="hero-eyebrow"><?php echo esc_html( apply_filters( 'zk_hero_eyebrow', '[ TRANSMISSION LIVE ]' ) ); ?></p>
        </div>
        <div class="hero-title-wrap">
            <h1 class="hero-title" data-text="<?php echo esc_attr( $zk_site ); ?>"><?php echo esc_html( $zk_site ); ?></h1>
        </div>
        <div class="hero-sub-wrap">
            <p class="hero-sub">Multidisciplinary Artist From The Solar System</p>
        </div>
    </div>
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
        $hero_img = esc_url( get_the_post_thumbnail_url( get_the_ID(), 'full' ) );
        ?>
        <div class="zk-post-hero">
            <img src="<?php echo $hero_img; ?>" fetchpriority="high" loading="eager" decoding="sync" alt="<?php echo esc_attr( get_the_title() ); ?>" class="zk-hero-img">
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
        // პოსტის თეგების (Tags) გამოტანა
        $post_tags = get_the_tags();
        if ( is_single() && $post_tags ) :
            ?>
            <div class="zk-post-tags">
                <span class="zk-tags-label">Topics:</span>
                <div class="zk-tags-list">
                    <?php foreach( $post_tags as $tag ) :
                        $tag_link = get_tag_link( $tag->term_id );
                        $tag_path = wp_parse_url( $tag_link, PHP_URL_PATH );
                        ?>
                        <a href="<?php echo esc_url( $tag_link ); ?>" data-route="<?php echo esc_attr( $tag_path ); ?>" class="zk-tag">
                            <?php echo esc_html( $tag->name ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
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
} elseif ( is_archive() || is_search() ) {
    // ── თეგების, კატეგორიებისა და ძებნის გვერდები ──
    $zk_current = rtrim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( empty( $zk_current ) ) {
        $zk_current = '/';
    }

    $eyebrow = 'Archive';
    $archive_title = 'Posts';

    // ვარკვევთ რის არქივში ვართ
    if ( is_tag() ) {
        $eyebrow = 'Topic';
        $archive_title = single_tag_title( '', false );
    } elseif ( is_category() ) {
        $eyebrow = 'Category';
        $archive_title = single_cat_title( '', false );
    } elseif ( is_search() ) {
        $eyebrow = 'Search Results';
        $archive_title = get_search_query();
    }

    ob_start(); ?>
    <div class="page__inner">
        <?php zk_breadcrumbs(); ?>
        <p class="page__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
        <h1 class="page__title"><?php echo esc_html( $archive_title ); ?></h1>

        <?php if ( get_the_archive_description() ) : ?>
            <div class="page__description"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
        <?php endif; ?>

        <?php if ( have_posts() ) : ?>
            <div class="zk-grid-wrapper">
                <!-- ── გრიდის კონტროლები (Live Search & Sort) ── -->
                <div class="zk-grid-controls">
                    <div class="zk-search-box">
                        <input type="text" class="zk-search-input" placeholder="Search in <?php echo esc_attr( $archive_title ); ?>..." aria-label="Search">
                        <svg class="zk-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </div>
                    <div class="zk-sort-dropdown" id="sortDropdown">
                        <button class="zk-sort-trigger" type="button" aria-expanded="false">
                            <span class="zk-sort-label">Sort by: </span><span class="zk-sort-current">Newest</span>
                            <svg class="dropdown-caret" width="10" height="10" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="zk-sort-menu">
                            <button class="zk-sort-option is-selected" type="button" data-sort="desc">Newest</button>
                            <button class="zk-sort-option" type="button" data-sort="asc">Oldest</button>
                        </div>
                    </div>
                </div>

                <!-- ── ქარდების გრიდი ── -->
                <div class="zk-post-grid">
                    <?php while ( have_posts() ) : the_post();
                        $title = get_the_title();
                        $link = get_permalink();
                        $path = wp_parse_url( $link, PHP_URL_PATH );
                        $categories = get_the_category();
                        $cat_name = ! empty( $categories ) ? esc_html( $categories[0]->name ) : 'Post';
                        $date = get_the_date( 'M j, Y' );
                        $timestamp = get_the_time( 'U' );
                        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : '';
                        ?>
                        <a href="<?php echo esc_url( $link ); ?>" class="zk-grid-card" data-route="<?php echo esc_attr( $path ); ?>" data-time="<?php echo esc_attr( $timestamp ); ?>">
                            <div class="zk-card-image">
                                <?php if ( $img_url ) : ?>
                                    <img src="<?php echo esc_url( $img_url ); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr( $title ); ?>" class="zk-card-img">
                                <?php endif; ?>
                            </div>
                            <div class="zk-card-content">
                                <div class="zk-card-meta">
                                    <span class="zk-card-category"><?php echo $cat_name; ?></span>
                                    <span class="zk-card-meta-separator"></span>
                                    <span class="zk-card-date"><?php echo esc_html( $date ); ?></span>
                                </div>
                                <h3 class="zk-card-title"><?php echo esc_html( $title ); ?></h3>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="page__content"><p>No posts found for this topic.</p></div>
        <?php endif; ?>
    </div>
    <?php
    $zk_view = ob_get_clean();
    rewind_posts();

} elseif ( is_404() ) {
    // ── რეალური 404 გვერდი ──
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
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
    <script>
        /* Enable cinematic image fade-in before the first paint (JS users only)
           so images stay hidden until fully loaded instead of popping in. */
        document.documentElement.classList.add('zk-img-js');
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

<div id="zk-global-loader">
    <div class="zk-line-loader"></div>
</div>

<main id="app">
    <article id="view" class="view" data-route="<?php echo esc_attr( $zk_current ); ?>" tabindex="-1">
        <?php echo $zk_view; // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </article>
</main>

<template id="view-home"><?php echo $zk_hero; // phpcs:ignore WordPress.Security.EscapeOutput ?></template>

<?php wp_footer(); ?>
</body>
</html>