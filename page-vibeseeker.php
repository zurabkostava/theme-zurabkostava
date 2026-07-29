<?php
/*
Template Name: App - VibeSeeker
*/

// Protect this custom app from global WordPress styles and scripts
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('zk-style');
    wp_dequeue_style('zk-fonts');
    wp_dequeue_script('zk-app');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
    
    // Enqueue VibeSeeker App Styles
    wp_enqueue_style('vibeseeker-app-style', get_template_directory_uri() . '/VibeSeeker/style.css', array(), time());
}, 999);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_footer', 'zk_mobile_bottom_nav'); // Remove Mobile Bottom Nav from App
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>VibeSeeker - Music Discovery</title>
    
    <?php wp_head(); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="theme-color" content="#090909">

    <script defer src="<?php echo get_template_directory_uri(); ?>/VibeSeeker/app.js?v=<?php echo time(); ?>"></script>
</head>
<body <?php body_class(); ?>>
    
    <div class="ambient-bg"></div>

    <div class="app-container">
        <header class="app-header">
            <div class="app-logo">Randi<span>Fly</span></div>
            <div class="settings-btn"><i class="fa-solid fa-sliders"></i></div>
        </header>

        <div class="filters-container">
            <select id="genreFilter" class="vibe-select">
                <option value="all">All Genres</option>
                <option value="pop">Pop</option>
                <option value="hip-hop">Hip-Hop</option>
                <option value="electronic">Electronic</option>
                <option value="rock">Rock</option>
                <option value="jazz">Jazz</option>
                <option value="latin">Latin</option>
                <option value="indie">Indie</option>
            </select>
            <select id="yearFilter" class="vibe-select">
                <option value="all">All Time</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
                <option value="2020">2020s</option>
                <option value="2010">2010s</option>
                <option value="2000">2000s</option>
                <option value="1990">90s Classics</option>
            </select>
        </div>

        <div class="card-stack" id="cardStack">
            <!-- Example Card -->
            <div class="track-card">
                <div class="track-art-wrapper">
                    <img src="https://i.scdn.co/image/ab67616d0000b2734121fa0f23d77d8df6e68051" alt="Album Art" class="track-art">
                </div>
                <div class="track-info">
                    <div class="track-title">Blinding Lights</div>
                    <div class="track-artist">The Weeknd</div>
                </div>
            </div>
        </div>

        <div class="app-controls">
            <button class="control-btn btn-dislike" id="btnDislike">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <button class="control-btn btn-play" id="btnPlay">
                <i class="fa-solid fa-play"></i>
            </button>
            <button class="control-btn btn-like" id="btnLike">
                <i class="fa-solid fa-heart"></i>
            </button>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
