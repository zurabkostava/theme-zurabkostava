<?php
// ვამატებთ მენიუს მხარდაჭერას ჩვენი თემისთვის
function zurabkostava_setup() {
    register_nav_menus( array(
        'primary_menu' => 'მთავარი მენიუ (Primary)',
    ) );
}
add_action( 'after_setup_theme', 'zurabkostava_setup' );
?>