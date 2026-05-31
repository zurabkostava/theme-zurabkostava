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
        <!-- ვორდპრესიდან მოგვაქვს მთავარი გვერდის ლინკი და საიტის სახელი -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo">
            <?php bloginfo( 'name' ); ?>
        </a>

        <!-- ვორდპრესიდან მოგვაქვს საიტის აღწერა (Tagline) -->
        <div class="site-tagline">
            <?php bloginfo( 'description' ); ?>
        </div>
    </div>
</header>

<!-- აქ მოგვიანებით კონტენტს ჩავსვამთ -->
<main class="main-content">
    <p>აქ იქნება შენი ვანილა კოდით აწყობილი საოცრებები!</p>
</main>

<?php wp_footer(); ?>
</body>
</html>