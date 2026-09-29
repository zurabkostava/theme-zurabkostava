<?php
/**
 * Visual Hub Admin Control Panel & Data Management
 * Zurab Kostava — Theme Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'zk_hex2rgb' ) ) {
    function zk_hex2rgb( $hex ) {
        $hex = trim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( $hex[0] . $hex[0] );
            $g = hexdec( $hex[1] . $hex[1] );
            $b = hexdec( $hex[2] . $hex[2] );
        } elseif ( strlen( $hex ) === 6 ) {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        } else {
            return '99, 102, 241';
        }
        return "$r, $g, $b";
    }
}

/**
 * Default Cards Configuration
 */
function zk_visual_hub_get_default_cards() {
    return array(
        'photography' => array(
            'id'           => 'photography',
            'class'        => 'visual-photo',
            'title_en'     => 'Photography',
            'title_ka'     => 'ფოტოგრაფია',
            'badge_en'     => 'Moments & Light',
            'badge_ka'     => 'მომენტები და სინათლე',
            'desc_en'      => 'Capturing evocative moments, architectural geometries, street observations, and raw light.',
            'desc_ka'      => 'ემოციური მომენტების, არქიტექტურული გეომეტრიის, ქუჩის დაკვირვებებისა და ბუნებრივი სინათლის აღბეჭდვა.',
            'link_en'      => '/visual/photography/',
            'link_ka'      => '/ka/visual/photography/',
            'image_url'    => 'https://zurabkostava.com/wp-content/uploads/2025/10/Sunset-in-Suburban.webp',
            'accent_color' => '#f59e0b',
        ),
        'video' => array(
            'id'           => 'video',
            'class'        => 'visual-video',
            'title_en'     => 'Video & Cinema',
            'title_ka'     => 'ვიდეო და კინო',
            'badge_en'     => 'Motion & Directing',
            'badge_ka'     => 'მოძრაობა და რეჟისურა',
            'desc_en'      => 'Cinematic frames, dynamic visual pacing, music video productions, and atmospheric motion design.',
            'desc_ka'      => 'კინემატოგრაფიული კადრები, დინამიკური ვიზუალური რიტმი, მუსიკალური ვიდეოები და ატმოსფერული მოუშენ დიზაინი.',
            'link_en'      => '/visual/video/',
            'link_ka'      => '/ka/visual/video/',
            'image_url'    => 'https://zurabkostava.com/wp-content/uploads/2025/10/motion.jpg',
            'accent_color' => '#6366f1',
        ),
        'graphic' => array(
            'id'           => 'graphic',
            'class'        => 'visual-graphic',
            'title_en'     => 'Graphic Design',
            'title_ka'     => 'გრაფიკული დიზაინი',
            'badge_en'     => 'Branding & UI',
            'badge_ka'     => 'ბრენდინგი და ინტერფეისი',
            'desc_en'      => 'Brand identity systems, minimalist digital typography, editorial layouts, and experimental visuals.',
            'desc_ka'      => 'ბრენდის იდენტობა, მინიმალისტური ტიპოგრაფია, სარედაქციო განლაგებები და ექსპერიმენტული ვიზუალი.',
            'link_en'      => '/visual/graphic/',
            'link_ka'      => '/ka/visual/graphic/',
            'image_url'    => 'https://zurabkostava.com/wp-content/uploads/2025/11/Poster-08.02.2023-copy-1.webp',
            'accent_color' => '#06b6d4',
        ),
        'paint' => array(
            'id'           => 'paint',
            'class'        => 'visual-paint',
            'title_en'     => 'Paint & Fine Art',
            'title_ka'     => 'ფერწერა და არტი',
            'badge_en'     => 'Canvas & Expression',
            'badge_ka'     => 'ტილო და ექსპრესია',
            'desc_en'      => 'Expressive digital paintings, abstract canvas explorations, tactile textures, and figurative concepts.',
            'desc_ka'      => 'ექსპრესიული ციფრული მხატვრობა, აბსტრაქტული ტილოები, ტექსტურები და ფიგურატიული კონცეფციები.',
            'link_en'      => '/visual/paint/',
            'link_ka'      => '/ka/visual/paint/',
            'image_url'    => 'https://zurabkostava.com/wp-content/uploads/2026/06/1-REF_004-main-.jpg',
            'accent_color' => '#ec4899',
        ),
    );
}

/**
 * Default Intro Configuration
 */
function zk_visual_hub_get_default_intro() {
    return array(
        'badge_en' => 'Disciplines',
        'badge_ka' => 'მიმართულებები',
        'intro_en' => 'A curated index of visual disciplines — exploring light, motion, graphic composition, and tactile expression.',
        'intro_ka' => 'ვიზუალური მიმართულებების კურატორული ინდექსი — სინათლის, მოძრაობის, გრაფიკული კომპოზიციისა და შემოქმედებითი ექსპრესიის ძიება.',
    );
}

/**
 * Fetch Merged Settings
 */
function zk_visual_hub_get_settings() {
    $defaults = array(
        'intro' => zk_visual_hub_get_default_intro(),
        'cards' => zk_visual_hub_get_default_cards(),
    );

    $saved = get_option( 'zk_visual_hub_settings' );
    if ( ! is_array( $saved ) ) {
        return $defaults;
    }

    $merged = $defaults;
    if ( isset( $saved['intro'] ) && is_array( $saved['intro'] ) ) {
        $merged['intro'] = wp_parse_args( $saved['intro'], $defaults['intro'] );
    }

    if ( isset( $saved['cards'] ) && is_array( $saved['cards'] ) ) {
        foreach ( $defaults['cards'] as $key => $default_card ) {
            if ( isset( $saved['cards'][ $key ] ) && is_array( $saved['cards'][ $key ] ) ) {
                $merged['cards'][ $key ] = wp_parse_args( $saved['cards'][ $key ], $default_card );
            }
        }
    }

    return $merged;
}

/**
 * Galleries are driven by FileBird folders. Each configured tab owns one
 * folder; that folder's direct images appear in the grid and its immediate
 * subfolders become grouped carousels in the shared cinematic viewer.
 */
function zk_visual_gallery_default_settings() {
    $defaults = array(
        'photography' => array(
            'title'       => 'Photography',
            'all_labels'  => array( 'en' => 'All', 'ka' => 'ყველა' ),
            'aria_labels' => array( 'en' => 'Filter photography', 'ka' => 'ფოტოგრაფიის გაფილტვრა' ),
            'tabs'        => array(
                array(
                    'id'          => 'camera',
                    'folder_id'   => 0,
                    'folder_name' => 'Camera Photography',
                    'source_tag'  => 'CAM',
                    'labels'      => array( 'en' => 'Camera', 'ka' => 'კამერა' ),
                ),
                array(
                    'id'          => 'mobile',
                    'folder_id'   => 0,
                    'folder_name' => 'Mobile Photography',
                    'source_tag'  => 'PHONE',
                    'labels'      => array( 'en' => 'Mobile', 'ka' => 'მობილური' ),
                ),
            ),
        ),
        'paint' => array(
            'title'       => 'Paint',
            'all_labels'  => array( 'en' => 'All', 'ka' => 'ყველა' ),
            'aria_labels' => array( 'en' => 'Filter artworks', 'ka' => 'ნამუშევრების გაფილტვრა' ),
            'tabs'        => array(
                array(
                    'id'          => 'artworks',
                    'folder_id'   => 0,
                    'folder_name' => 'Paint',
                    'source_tag'  => 'ART',
                    'labels'      => array( 'en' => 'Artworks', 'ka' => 'ნამუშევრები' ),
                ),
            ),
        ),
    );

    // Preserve the labels previously entered in Language Center during the
    // transition from the fixed three-field photography UI.
    $legacy = get_option( 'zk_gallery_labels_v1', array() );
    if ( is_array( $legacy ) ) {
        foreach ( $legacy as $language => $labels ) {
            if ( ! is_array( $labels ) ) continue;
            if ( ! empty( $labels['all'] ) ) $defaults['photography']['all_labels'][ $language ] = $labels['all'];
            foreach ( $defaults['photography']['tabs'] as &$tab ) {
                if ( ! empty( $labels[ $tab['id'] ] ) ) $tab['labels'][ $language ] = $labels[ $tab['id'] ];
            }
            unset( $tab );
        }
    }

    return $defaults;
}

function zk_visual_gallery_get_settings() {
    $defaults = zk_visual_gallery_default_settings();
    $saved    = get_option( 'zk_visual_gallery_settings_v1', array() );
    if ( ! is_array( $saved ) ) return $defaults;

    foreach ( $defaults as $gallery_id => $definition ) {
        if ( empty( $saved[ $gallery_id ] ) || ! is_array( $saved[ $gallery_id ] ) ) continue;
        $gallery = $saved[ $gallery_id ];
        foreach ( array( 'all_labels', 'aria_labels' ) as $label_group ) {
            if ( ! empty( $gallery[ $label_group ] ) && is_array( $gallery[ $label_group ] ) ) {
                $definition[ $label_group ] = array_merge( $definition[ $label_group ], $gallery[ $label_group ] );
            }
        }
        if ( isset( $gallery['tabs'] ) && is_array( $gallery['tabs'] ) ) {
            $definition['tabs'] = array_values( $gallery['tabs'] );
        }
        $defaults[ $gallery_id ] = $definition;
    }
    return $defaults;
}

function zk_visual_gallery_filebird_folders() {
    global $wpdb;
    $table = $wpdb->prefix . 'fbv';
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
    if ( $found !== $table ) return array();
    return $wpdb->get_results( "SELECT id, name, parent FROM {$table} ORDER BY parent ASC, name ASC" );
}

function zk_visual_gallery_admin_menu() {
    add_submenu_page(
        'zk-visual-hub',
        'Visual Galleries',
        'Galleries',
        'manage_options',
        'zk-visual-galleries',
        'zk_visual_gallery_render_admin_page'
    );
}
add_action( 'admin_menu', 'zk_visual_gallery_admin_menu', 20 );

function zk_visual_gallery_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied' );
    check_admin_referer( 'zk_visual_galleries_action', 'zk_visual_galleries_nonce' );

    $raw       = isset( $_POST['zk_galleries'] ) && is_array( $_POST['zk_galleries'] ) ? wp_unslash( $_POST['zk_galleries'] ) : array();
    $defaults  = zk_visual_gallery_default_settings();
    $languages = function_exists( 'zk_get_languages' ) ? zk_get_languages( false ) : array( 'en' => array(), 'ka' => array() );
    $folders   = zk_visual_gallery_filebird_folders();
    $folder_names = array();
    foreach ( $folders as $folder ) $folder_names[ (int) $folder->id ] = $folder->name;
    $clean = array();

    foreach ( $defaults as $gallery_id => $definition ) {
        $submitted = isset( $raw[ $gallery_id ] ) && is_array( $raw[ $gallery_id ] ) ? $raw[ $gallery_id ] : array();
        $clean[ $gallery_id ] = array( 'all_labels' => array(), 'aria_labels' => array(), 'tabs' => array() );
        foreach ( array_keys( $languages ) as $language ) {
            $all = isset( $submitted['all_labels'][ $language ] ) ? sanitize_text_field( $submitted['all_labels'][ $language ] ) : '';
            $aria = isset( $submitted['aria_labels'][ $language ] ) ? sanitize_text_field( $submitted['aria_labels'][ $language ] ) : '';
            if ( '' !== $all ) $clean[ $gallery_id ]['all_labels'][ $language ] = $all;
            if ( '' !== $aria ) $clean[ $gallery_id ]['aria_labels'][ $language ] = $aria;
        }

        $seen = array();
        $tabs = isset( $submitted['tabs'] ) && is_array( $submitted['tabs'] ) ? $submitted['tabs'] : array();
        foreach ( array_slice( $tabs, 0, 30 ) as $position => $tab ) {
            if ( ! is_array( $tab ) ) continue;
            $id = sanitize_key( isset( $tab['id'] ) ? $tab['id'] : '' );
            if ( '' === $id ) $id = 'tab-' . ( $position + 1 );
            $base_id = $id;
            $suffix = 2;
            while ( isset( $seen[ $id ] ) ) $id = $base_id . '-' . $suffix++;
            $seen[ $id ] = true;
            $folder_id = isset( $tab['folder_id'] ) ? absint( $tab['folder_id'] ) : 0;
            $labels = array();
            foreach ( array_keys( $languages ) as $language ) {
                $value = isset( $tab['labels'][ $language ] ) ? sanitize_text_field( $tab['labels'][ $language ] ) : '';
                if ( '' !== $value ) $labels[ $language ] = $value;
            }
            $clean[ $gallery_id ]['tabs'][] = array(
                'id'          => $id,
                'folder_id'   => $folder_id,
                'folder_name' => isset( $folder_names[ $folder_id ] ) ? sanitize_text_field( $folder_names[ $folder_id ] ) : '',
                'source_tag'  => strtoupper( substr( sanitize_key( isset( $tab['source_tag'] ) ? $tab['source_tag'] : '' ), 0, 10 ) ),
                'labels'      => $labels,
            );
        }
    }

    update_option( 'zk_visual_gallery_settings_v1', $clean, false );
    if ( function_exists( 'zk_flush_gallery_cache' ) ) zk_flush_gallery_cache();
    wp_safe_redirect( add_query_arg( array( 'page' => 'zk-visual-galleries', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_zk_save_visual_galleries', 'zk_visual_gallery_save_settings' );

function zk_visual_gallery_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied' );
    $galleries = zk_visual_gallery_get_settings();
    $languages = function_exists( 'zk_get_languages' ) ? zk_get_languages( false ) : array( 'en' => array( 'native_name' => 'English' ), 'ka' => array( 'native_name' => 'ქართული' ) );
    $folders   = zk_visual_gallery_filebird_folders();
    $folder_options = array();
    foreach ( $folders as $folder ) $folder_options[ (int) $folder->id ] = $folder->name;
    ?>
    <div class="wrap zk-gallery-admin">
        <h1>Visual Galleries</h1>
        <p>Manage gallery tabs, their FileBird source folders, order and translations in one place. A tab shows direct images from its folder; subfolders become grouped carousels.</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Gallery settings saved.</p></div><?php endif; ?>
        <?php if ( empty( $folders ) ) : ?><div class="notice notice-warning"><p>FileBird folders were not found. Create folders in Media Library first.</p></div><?php endif; ?>
        <style>
            .zk-gallery-admin{max-width:1280px}.zk-gallery-admin .zk-ga-card{background:#fff;border:1px solid #ccd0d4;border-radius:12px;padding:22px;margin:22px 0}.zk-ga-head{display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid #eee;padding-bottom:14px}.zk-ga-tabs{display:grid;gap:12px;margin-top:16px}.zk-ga-row{display:grid;grid-template-columns:34px minmax(120px,.7fr) minmax(220px,1.4fr) minmax(90px,.55fr) repeat(var(--zk-lang-count),minmax(150px,1fr)) 86px;gap:10px;align-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px}.zk-ga-row input,.zk-ga-row select{width:100%}.zk-ga-order{display:flex;flex-direction:column;gap:4px}.zk-ga-order button{min-height:24px;line-height:20px;padding:0}.zk-ga-actions{display:flex;gap:5px}.zk-ga-lang-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-top:16px}.zk-ga-field label,.zk-ga-label{display:block;font-size:11px;font-weight:700;color:#50575e;margin-bottom:4px;text-transform:uppercase}.zk-ga-empty{text-align:center;color:#646970;padding:18px;border:1px dashed #c3c4c7;border-radius:8px}@media(max-width:1100px){.zk-ga-row{grid-template-columns:34px 1fr 1.5fr}.zk-ga-row>*{min-width:0}.zk-ga-actions{grid-column:2/-1}.zk-ga-lang{grid-column:span 1}}
        </style>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="zk_save_visual_galleries">
            <?php wp_nonce_field( 'zk_visual_galleries_action', 'zk_visual_galleries_nonce' ); ?>
            <?php foreach ( $galleries as $gallery_id => $gallery ) : ?>
                <section class="zk-ga-card" data-gallery="<?php echo esc_attr( $gallery_id ); ?>" style="--zk-lang-count:<?php echo esc_attr( count( $languages ) ); ?>">
                    <div class="zk-ga-head">
                        <div><h2 style="margin:0"><?php echo esc_html( $gallery['title'] ); ?></h2><code>[zk_visual_gallery gallery=&quot;<?php echo esc_attr( $gallery_id ); ?>&quot;]</code></div>
                        <button type="button" class="button button-secondary zk-ga-add">+ Add tab</button>
                    </div>
                    <div class="zk-ga-lang-grid">
                        <?php foreach ( $languages as $code => $language ) : ?>
                            <div class="zk-ga-field"><label><?php echo esc_html( strtoupper( $code ) . ' — All tab' ); ?></label><input type="text" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][all_labels][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( isset( $gallery['all_labels'][ $code ] ) ? $gallery['all_labels'][ $code ] : '' ); ?>"></div>
                            <div class="zk-ga-field"><label><?php echo esc_html( strtoupper( $code ) . ' — accessibility label' ); ?></label><input type="text" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][aria_labels][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( isset( $gallery['aria_labels'][ $code ] ) ? $gallery['aria_labels'][ $code ] : '' ); ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="zk-ga-tabs">
                        <?php foreach ( $gallery['tabs'] as $index => $tab ) :
                            $selected_folder = ! empty( $tab['folder_id'] ) ? (int) $tab['folder_id'] : 0;
                            if ( ! $selected_folder && ! empty( $tab['folder_name'] ) ) {
                                $match = array_search( $tab['folder_name'], $folder_options, true );
                                if ( false !== $match ) $selected_folder = (int) $match;
                            }
                        ?>
                            <div class="zk-ga-row">
                                <div class="zk-ga-order"><button type="button" class="button zk-ga-up" aria-label="Move up">↑</button><button type="button" class="button zk-ga-down" aria-label="Move down">↓</button></div>
                                <div><span class="zk-ga-label">Key</span><input type="text" data-field="id" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][tabs][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $tab['id'] ); ?>"></div>
                                <div><span class="zk-ga-label">FileBird folder</span><select data-field="folder_id" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][tabs][<?php echo esc_attr( $index ); ?>][folder_id]"><option value="0">— Select folder —</option><?php foreach ( $folder_options as $folder_id => $folder_name ) : ?><option value="<?php echo esc_attr( $folder_id ); ?>" <?php selected( $selected_folder, $folder_id ); ?>><?php echo esc_html( $folder_name . ' (#' . $folder_id . ')' ); ?></option><?php endforeach; ?></select></div>
                                <div><span class="zk-ga-label">Badge</span><input type="text" data-field="source_tag" maxlength="10" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][tabs][<?php echo esc_attr( $index ); ?>][source_tag]" value="<?php echo esc_attr( $tab['source_tag'] ); ?>"></div>
                                <?php foreach ( $languages as $code => $language ) : ?><div class="zk-ga-lang"><span class="zk-ga-label"><?php echo esc_html( strtoupper( $code ) . ' label' ); ?></span><input type="text" data-label="<?php echo esc_attr( $code ); ?>" name="zk_galleries[<?php echo esc_attr( $gallery_id ); ?>][tabs][<?php echo esc_attr( $index ); ?>][labels][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( isset( $tab['labels'][ $code ] ) ? $tab['labels'][ $code ] : '' ); ?>"></div><?php endforeach; ?>
                                <div class="zk-ga-actions"><button type="button" class="button-link-delete zk-ga-remove">Remove</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php submit_button( 'Save gallery settings' ); ?>
        </form>
        <script>
        (function(){
            var languages=<?php echo wp_json_encode( array_keys( $languages ) ); ?>;
            var folders=<?php echo wp_json_encode( $folder_options ); ?>;
            function renumber(card){card.querySelectorAll('.zk-ga-row').forEach(function(row,i){var gallery=card.dataset.gallery;row.querySelectorAll('[data-field]').forEach(function(el){el.name='zk_galleries['+gallery+'][tabs]['+i+']['+el.dataset.field+']';});row.querySelectorAll('[data-label]').forEach(function(el){el.name='zk_galleries['+gallery+'][tabs]['+i+'][labels]['+el.dataset.label+']';});});}
            function addRow(card){var box=card.querySelector('.zk-ga-tabs'),row=document.createElement('div');row.className='zk-ga-row';var options='<option value="0">— Select folder —</option>';Object.keys(folders).forEach(function(id){options+='<option value="'+id+'">'+folders[id]+' (#'+id+')</option>';});var html='<div class="zk-ga-order"><button type="button" class="button zk-ga-up">↑</button><button type="button" class="button zk-ga-down">↓</button></div><div><span class="zk-ga-label">Key</span><input type="text" data-field="id" value="tab-'+(box.children.length+1)+'"></div><div><span class="zk-ga-label">FileBird folder</span><select data-field="folder_id">'+options+'</select></div><div><span class="zk-ga-label">Badge</span><input type="text" data-field="source_tag" maxlength="10"></div>';languages.forEach(function(code){html+='<div class="zk-ga-lang"><span class="zk-ga-label">'+code.toUpperCase()+' label</span><input type="text" data-label="'+code+'"></div>';});html+='<div class="zk-ga-actions"><button type="button" class="button-link-delete zk-ga-remove">Remove</button></div>';row.innerHTML=html;box.appendChild(row);renumber(card);}
            document.addEventListener('click',function(e){var card=e.target.closest('.zk-ga-card');if(!card)return;if(e.target.closest('.zk-ga-add')){addRow(card);return;}var row=e.target.closest('.zk-ga-row');if(!row)return;if(e.target.closest('.zk-ga-remove'))row.remove();else if(e.target.closest('.zk-ga-up')&&row.previousElementSibling)row.parentNode.insertBefore(row,row.previousElementSibling);else if(e.target.closest('.zk-ga-down')&&row.nextElementSibling)row.parentNode.insertBefore(row.nextElementSibling,row);renumber(card);});
        })();
        </script>
    </div>
    <?php
}

/**
 * Register Admin Menu
 */
function zk_visual_hub_admin_menu() {
    add_menu_page(
        'Visual Hub Settings',
        'Visual Hub',
        'manage_options',
        'zk-visual-hub',
        'zk_visual_hub_render_admin_page',
        'dashicons-format-gallery',
        21
    );
}
add_action( 'admin_menu', 'zk_visual_hub_admin_menu' );

/**
 * Enqueue Media & Color Picker Scripts for Admin Page
 */
function zk_visual_hub_admin_scripts( $hook ) {
    if ( 'toplevel_page_zk-visual-hub' !== $hook ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
}
add_action( 'admin_enqueue_scripts', 'zk_visual_hub_admin_scripts' );

/**
 * Render Admin Page
 */
function zk_visual_hub_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied' );
    }

    $notice = '';

    // Handle Form Save
    if ( isset( $_POST['zk_visual_hub_save'] ) && check_admin_referer( 'zk_visual_hub_action', 'zk_visual_hub_nonce' ) ) {
        $raw_intro = isset( $_POST['zk_intro'] ) ? $_POST['zk_intro'] : array();
        $raw_cards = isset( $_POST['zk_cards'] ) ? $_POST['zk_cards'] : array();

        $clean_intro = array(
            'badge_en' => sanitize_text_field( isset( $raw_intro['badge_en'] ) ? $raw_intro['badge_en'] : '' ),
            'badge_ka' => sanitize_text_field( isset( $raw_intro['badge_ka'] ) ? $raw_intro['badge_ka'] : '' ),
            'intro_en' => sanitize_textarea_field( isset( $raw_intro['intro_en'] ) ? $raw_intro['intro_en'] : '' ),
            'intro_ka' => sanitize_textarea_field( isset( $raw_intro['intro_ka'] ) ? $raw_intro['intro_ka'] : '' ),
        );

        $default_cards = zk_visual_hub_get_default_cards();
        $clean_cards = array();

        foreach ( $default_cards as $key => $def ) {
            $c = isset( $raw_cards[ $key ] ) ? $raw_cards[ $key ] : array();
            $clean_cards[ $key ] = array(
                'id'           => $key,
                'class'        => $def['class'],
                'title_en'     => sanitize_text_field( isset( $c['title_en'] ) ? $c['title_en'] : $def['title_en'] ),
                'title_ka'     => sanitize_text_field( isset( $c['title_ka'] ) ? $c['title_ka'] : $def['title_ka'] ),
                'badge_en'     => sanitize_text_field( isset( $c['badge_en'] ) ? $c['badge_en'] : $def['badge_en'] ),
                'badge_ka'     => sanitize_text_field( isset( $c['badge_ka'] ) ? $c['badge_ka'] : $def['badge_ka'] ),
                'desc_en'      => sanitize_textarea_field( isset( $c['desc_en'] ) ? $c['desc_en'] : $def['desc_en'] ),
                'desc_ka'      => sanitize_textarea_field( isset( $c['desc_ka'] ) ? $c['desc_ka'] : $def['desc_ka'] ),
                'link_en'      => esc_url_raw( isset( $c['link_en'] ) ? $c['link_en'] : $def['link_en'] ),
                'link_ka'      => esc_url_raw( isset( $c['link_ka'] ) ? $c['link_ka'] : $def['link_ka'] ),
                'image_url'    => esc_url_raw( isset( $c['image_url'] ) ? $c['image_url'] : $def['image_url'] ),
                'accent_color' => sanitize_hex_color( isset( $c['accent_color'] ) ? $c['accent_color'] : $def['accent_color'] ) ?: $def['accent_color'],
            );
        }

        update_option( 'zk_visual_hub_settings', array(
            'intro' => $clean_intro,
            'cards' => $clean_cards,
        ) );

        $notice = '<div class="notice notice-success is-dismissible"><p><strong>ვიზუალური ჰაბის პარამეტრები წარმატებით შეინახა!</strong> (Visual Hub settings saved successfully!)</p></div>';
    }

    $settings = zk_visual_hub_get_settings();
    $intro    = $settings['intro'];
    $cards    = $settings['cards'];

    $home_url = home_url();
    ?>
    <div class="wrap" style="max-width: 1200px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-size: 26px; font-weight: 700; display: inline-flex; align-items: center; gap: 10px; margin: 0;">
                    <span class="dashicons dashicons-format-gallery" style="font-size: 28px; width: 28px; height: 28px; color: #2271b1;"></span>
                    Visual Hub Settings (ვიზუალური ჰაბის მართვა)
                </h1>
                <p style="color: #666; margin: 5px 0 0; font-size: 14px;">
                    მართეთ ვიზუალური ჰაბის (<code>/visual/</code>) სექციები, სათაურები, აღწერები, ფოტოები და ბმულები ორ ენაზე (EN / KA).
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url( home_url( '/visual/' ) ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px;"></span> ნახვა (EN Live)
                </a>
                <a href="<?php echo esc_url( home_url( '/ka/visual/' ) ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px;"></span> ნახვა (KA Live)
                </a>
            </div>
        </div>

        <?php echo $notice; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'zk_visual_hub_action', 'zk_visual_hub_nonce' ); ?>

            <!-- INTRO SECTION -->
            <div style="background: #ffffff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px 24px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h2 style="margin-top: 0; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 12px; color: #1d2327;">
                    1. ჰაბის ზედა ტექსტი / შესავალი (Header & Intro)
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px;">
                            <span style="background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">EN</span>
                            Badge / Category Pill:
                        </label>
                        <input type="text" name="zk_intro[badge_en]" value="<?php echo esc_attr( $intro['badge_en'] ); ?>" class="large-text" placeholder="Disciplines" />
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px;">
                            <span style="background: #008a20; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">KA</span>
                            Badge (ქართულად):
                        </label>
                        <input type="text" name="zk_intro[badge_ka]" value="<?php echo esc_attr( $intro['badge_ka'] ); ?>" class="large-text" placeholder="მიმართულებები" />
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px;">
                            <span style="background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">EN</span>
                            Intro Subtitle / Summary:
                        </label>
                        <textarea name="zk_intro[intro_en]" rows="2" class="large-text"><?php echo esc_textarea( $intro['intro_en'] ); ?></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px;">
                            <span style="background: #008a20; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">KA</span>
                            შესავალი ტექსტი (ქართულად):
                        </label>
                        <textarea name="zk_intro[intro_ka]" rows="2" class="large-text"><?php echo esc_textarea( $intro['intro_ka'] ); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- CARDS GRID -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h2 style="font-size: 20px; font-weight: 700; margin: 0; color: #1d2327;">
                    2. ვიზუალური ბარათები (Visual Disciplines Bento Cards)
                </h2>
                <span style="color: #666; font-size: 13px;">4 ძირითადი მიმართულება</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(520px, 1fr)); gap: 24px; margin-bottom: 30px;">
                <?php
                $card_icons = array(
                    'photography' => 'dashicons-camera-alt',
                    'video'       => 'dashicons-video-alt3',
                    'graphic'     => 'dashicons-layout',
                    'paint'       => 'dashicons-art',
                );

                $index = 1;
                foreach ( $cards as $card_key => $card ) :
                    $icon = isset( $card_icons[ $card_key ] ) ? $card_icons[ $card_key ] : 'dashicons-format-image';
                    $accent = esc_attr( $card['accent_color'] );
                    $img = esc_url( $card['image_url'] );
                ?>
                <div class="zk-admin-card-box" style="background: #ffffff; border: 1px solid #ccd0d4; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column;">
                    
                    <!-- Card Top Bar -->
                    <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons <?php echo esc_attr( $icon ); ?>" style="font-size: 22px; width: 22px; height: 22px; color: <?php echo $accent; ?>;"></span>
                            <span style="font-size: 16px; font-weight: 700; color: #1e293b;">
                                <?php echo sprintf( '%02d', $index ); ?>. <?php echo esc_html( $card['title_en'] ); ?> / <?php echo esc_html( $card['title_ka'] ); ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 12px; color: #64748b;">Accent Glow:</span>
                            <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][accent_color]" value="<?php echo $accent; ?>" class="zk-color-field" data-default-color="<?php echo $accent; ?>" />
                        </div>
                    </div>

                    <div style="padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 18px;">
                        
                        <!-- Image Preview & Uploader -->
                        <div style="display: flex; gap: 18px; align-items: flex-start; background: #0b0f19; padding: 16px; border-radius: 8px; border: 1px solid #1e293b;">
                            <div style="width: 140px; height: 95px; border-radius: 6px; overflow: hidden; background: #1a2234; flex-shrink: 0; position: relative; border: 1px solid rgba(255,255,255,0.15);">
                                <img id="preview_<?php echo esc_attr( $card_key ); ?>" src="<?php echo $img ?: 'https://via.placeholder.com/280x190?text=No+Image'; ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;" />
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <label style="display: block; font-weight: 600; font-size: 12px; color: #cbd5e1; margin-bottom: 6px;">
                                    ბექგრაუნდის სურათი (Background / Artwork Preview URL):
                                </label>
                                <input type="text" id="url_<?php echo esc_attr( $card_key ); ?>" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][image_url]" value="<?php echo $img; ?>" class="large-text" style="margin-bottom: 8px; font-size: 13px; background: #131b2e; color: #f8fafc; border-color: #334155;" />
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="button button-secondary zk-upload-btn" data-target="url_<?php echo esc_attr( $card_key ); ?>" data-preview="preview_<?php echo esc_attr( $card_key ); ?>">
                                        <span class="dashicons dashicons-admin-media" style="margin-top: 3px;"></span> აირჩიე მედიათეკიდან
                                    </button>
                                    <button type="button" class="button zk-remove-img-btn" data-target="url_<?php echo esc_attr( $card_key ); ?>" data-preview="preview_<?php echo esc_attr( $card_key ); ?>">
                                        გასუფთავება
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Titles EN & KA -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #2271b1; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">EN</span>
                                    Title:
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][title_en]" value="<?php echo esc_attr( $card['title_en'] ); ?>" class="large-text" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #008a20; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">KA</span>
                                    სათაური:
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][title_ka]" value="<?php echo esc_attr( $card['title_ka'] ); ?>" class="large-text" />
                            </div>
                        </div>

                        <!-- Badges EN & KA -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #2271b1; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">EN</span>
                                    Tag / Badge (e.g. Moments & Light):
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][badge_en]" value="<?php echo esc_attr( $card['badge_en'] ); ?>" class="large-text" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #008a20; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">KA</span>
                                    თეგი / ბეიჯი:
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][badge_ka]" value="<?php echo esc_attr( $card['badge_ka'] ); ?>" class="large-text" />
                            </div>
                        </div>

                        <!-- Descriptions EN & KA -->
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                <span style="background: #2271b1; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">EN</span>
                                Description:
                            </label>
                            <textarea name="zk_cards[<?php echo esc_attr( $card_key ); ?>][desc_en]" rows="2" class="large-text"><?php echo esc_textarea( $card['desc_en'] ); ?></textarea>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                <span style="background: #008a20; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">KA</span>
                                აღწერა:
                            </label>
                            <textarea name="zk_cards[<?php echo esc_attr( $card_key ); ?>][desc_ka]" rows="2" class="large-text"><?php echo esc_textarea( $card['desc_ka'] ); ?></textarea>
                        </div>

                        <!-- Links EN & KA -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #2271b1; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">EN</span>
                                    Destination Link:
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][link_en]" value="<?php echo esc_attr( $card['link_en'] ); ?>" class="large-text" placeholder="/visual/<?php echo esc_attr( $card_key ); ?>/" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px;">
                                    <span style="background: #008a20; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 10px;">KA</span>
                                    ბმული (ქართულად):
                                </label>
                                <input type="text" name="zk_cards[<?php echo esc_attr( $card_key ); ?>][link_ka]" value="<?php echo esc_attr( $card['link_ka'] ); ?>" class="large-text" placeholder="/ka/visual/<?php echo esc_attr( $card_key ); ?>/" />
                            </div>
                        </div>

                    </div>
                </div>
                <?php
                $index++;
                endforeach;
                ?>
            </div>

            <div style="background: #ffffff; padding: 18px 24px; border-radius: 8px; border: 1px solid #ccd0d4; display: flex; align-items: center; justify-content: space-between; position: sticky; bottom: 20px; z-index: 100; box-shadow: 0 -4px 12px rgba(0,0,0,0.06);">
                <span style="color: #64748b; font-size: 13px;">
                    ცვლილებების შესანახად დააჭირეთ ღილაკს:
                </span>
                <input type="submit" name="zk_visual_hub_save" value="ყველა ცვლილების შენახვა (Save Changes)" class="button button-primary button-large" style="padding: 4px 28px; font-size: 15px; font-weight: 600;" />
            </div>

        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Color pickers
        if ($.fn.wpColorPicker) {
            $('.zk-color-field').wpColorPicker();
        }

        // Media Library Uploader
        $('.zk-upload-btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var targetInputId = btn.data('target');
            var previewImgId = btn.data('preview');

            var customUploader = wp.media({
                title: 'აირჩიეთ ვიზუალური ჰაბის სურათი',
                button: { text: 'სურათის გამოყენება' },
                multiple: false
            });

            customUploader.on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                var url = attachment.url;
                if (attachment.sizes && attachment.sizes.large) {
                    url = attachment.sizes.large.url;
                }
                $('#' + targetInputId).val(url);
                $('#' + previewImgId).attr('src', url);
            });

            customUploader.open();
        });

        // Clear Image button
        $('.zk-remove-img-btn').on('click', function(e) {
            e.preventDefault();
            var targetInputId = $(this).data('target');
            var previewImgId = $(this).data('preview');
            $('#' + targetInputId).val('');
            $('#' + previewImgId).attr('src', 'https://via.placeholder.com/280x190?text=No+Image');
        });
    });
    </script>
    <?php
}
