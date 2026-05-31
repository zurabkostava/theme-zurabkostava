<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- საიტის სახელი დინამიურად ტაბის სათაურისთვის -->
    <title><?php bloginfo('name'); ?> - <?php bloginfo('description'); ?></title>

    <!-- ვაკავშირებთ ჩვენს style.css ფაილს -->
    <link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>">

    <?php wp_head(); ?>
</head>
<body>

<header class="custom-header">
    <div class="header-container">
        <!-- ლოგო -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo">
            <?php bloginfo( 'name' ); ?>
        </a>

        <!-- ნავიგაციის მენიუ -->
        <nav class="site-navigation">
            <?php
            wp_nav_menu( array(
                    'theme_location' => 'primary_menu', // ფუნქციებში დარეგისტრირებული სახელი
                    'container'      => false, // ვორდპრესის ზედმეტი div-ების მოსაშორებლად
                    'menu_class'     => 'nav-list', // ჩვენი ქასთომ css კლასი ul ტეგისთვის
                    'fallback_cb'    => false // თუ მენიუ არაა შექმნილი, არაფერი გამოაჩინოს
            ) );
            ?>
        </nav>
    </div>
</header>

<!-- აქ მოგვიანებით კონტენტს ჩავსვამთ -->
<main class="main-content">
    <p>აქ იქნება შენი ვანილა კოდით აწყობილი საოცრებები!</p>
</main>

<?php wp_footer(); ?>
</body>
</html>