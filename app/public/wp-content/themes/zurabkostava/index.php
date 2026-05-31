<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- wp_head() უზრუნველყოფს title-ის და style.css-ის ჩატვირთვას -->
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- ჰედერი და ნავიგაცია -->
<header class="site-header">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="logo">
        <?php bloginfo('name'); ?>
    </a>

    <nav class="main-nav">
        <?php
        wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'nav-links',
                'fallback_cb'    => false
        ));
        ?>
    </nav>
</header>

<!-- მთავარი კონტენტი (WordPress Loop) -->
<main class="main-content">
    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            ?>
            <article class="post-item">
                <h2><?php the_title(); ?></h2>
                <div class="content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php
        endwhile;
    else :
        echo '<p>კონტენტი ჯერ არ დამატებულა.</p>';
    endif;
    ?>
</main>

<!-- აუცილებელი ფუტერი -->
<?php wp_footer(); ?>
</body>
</html>