<?php
// თემის საბაზისო პარამეტრების გააქტიურება
function zurabkostava_setup() {
    // 1. მთავარი მენიუს მხარდაჭერა
    register_nav_menus(array(
        'primary' => 'მთავარი მენიუ'
    ));

    // 2. პოსტებზე/პროექტებზე სურათის (Thumbnail) მიბმის ფუნქცია
    add_theme_support('post-thumbnails');

    // 3. საიტის სათაურის (Title ტეგის) ავტომატური გენერაცია
    add_theme_support('title-tag');
}
add_action('after_setup_theme', 'zurabkostava_setup');

// ჩვენი CSS ფაილის სწორად და სუფთად ჩატვირთვა
function zurabkostava_scripts() {
    wp_enqueue_style('main-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'zurabkostava_scripts');
?>