<?php
/** Presentation-only language preference; shared HTML remains cacheable. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function zk_ui_language_assets() {
    wp_enqueue_style( 'zk-ui-language', get_template_directory_uri() . '/ui-language.css', array('zk-style'), filemtime( get_template_directory() . '/ui-language.css' ) );
    wp_enqueue_script( 'zk-ui-language', get_template_directory_uri() . '/ui-language.js', array(), filemtime( get_template_directory() . '/ui-language.js' ), false );
}
add_action( 'wp_enqueue_scripts', 'zk_ui_language_assets', 5 );
add_filter( 'rocket_delay_js_exclusions', function ( $scripts ) {
    $scripts[] = 'ui-language.js';
    return $scripts;
} );
function zk_ui_language_switcher() {
    ?>
    <div class="zk-language-switch" role="group" aria-label="Interface language / ინტერფეისის ენა" hidden>
        <button type="button" data-ui-language="en" lang="en" aria-label="English" aria-pressed="true" data-url="<?php echo esc_url(zk_get_translation_url('en')); ?>">EN</button>
        <button type="button" data-ui-language="ka" lang="ka" aria-label="ქართული" aria-pressed="false" data-url="<?php echo esc_url(zk_get_translation_url('ka')); ?>">ქარ</button>
    </div>
    <?php
}
