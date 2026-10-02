<?php
/** Category archives are the destinations for blog listings; post URLs stay unchanged. */
if ( ! defined( 'ABSPATH' ) ) exit;

function zk_blog_listing_routes() {
    return (array) get_option( 'zk_blog_listing_routes', array() );
}

function zk_blog_listing_term( $path ) {
    $path = trailingslashit( zk_strip_language_prefix( $path ) );
    $routes = zk_blog_listing_routes();
    if ( ! isset( $routes[ $path ] ) ) return false;
    $term = get_term( (int) $routes[ $path ], 'category' );
    return $term && ! is_wp_error( $term ) ? $term : false;
}

/** One-time migration of the two existing listings, preserving filled category fields. */
function zk_migrate_blog_category_listings() {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'zk_blog_categories_migrated' ) ) return;
    $routes = zk_blog_listing_routes();
    $pages = array();
    foreach ( array( 'blog/news' => 'news', 'reviews' => 'reviews' ) as $path => $slug ) {
        $page = get_page_by_path( $path );
        if ( ! $page || 'publish' !== $page->post_status ) continue;
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term ) {
            $created = wp_insert_term( $page->post_title, 'category', array( 'slug' => $slug, 'description' => $page->post_excerpt ) );
            if ( is_wp_error( $created ) ) return;
            $term = get_term( $created['term_id'], 'category' );
        }
        foreach ( get_post_meta( $page->ID ) as $key => $values ) {
            if ( preg_match( '/^_zk_(seo_|geo_)/', $key ) && '' === (string) get_term_meta( $term->term_id, $key, true ) ) {
                update_term_meta( $term->term_id, $key, maybe_unserialize( $values[0] ) );
            }
        }
        if ( ! get_term_meta( $term->term_id, '_zk_seo_image', true ) && has_post_thumbnail( $page->ID ) ) {
            update_term_meta( $term->term_id, '_zk_seo_image', get_the_post_thumbnail_url( $page->ID, 'full' ) );
        }
        foreach ( zk_get_translatable_languages() as $code => $language ) {
            foreach ( array( 'title' => 'name', 'excerpt' => 'description' ) as $source => $target ) {
                $value = get_post_meta( $page->ID, zk_language_meta_key( $source, $code ), true );
                $key = zk_language_meta_key( $target, $code );
                if ( $value && ! get_term_meta( $term->term_id, $key, true ) ) update_term_meta( $term->term_id, $key, $value );
            }
        }
        if ( ! $term->description && $page->post_excerpt ) wp_update_term( $term->term_id, 'category', array( 'description' => $page->post_excerpt ) );
        $routes[ '/' . $path . '/' ] = $term->term_id;
        $pages[ $page->ID ] = $term;
    }
    update_option( 'zk_blog_listing_routes', $routes, false );
    foreach ( wp_get_nav_menus() as $menu ) {
        foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
            if ( 'post_type' !== $item->type || 'page' !== $item->object || ! isset( $pages[ $item->object_id ] ) ) continue;
            $term = $pages[ $item->object_id ];
            $result = wp_update_nav_menu_item( $menu->term_id, $item->ID, array(
                'menu-item-object-id' => $term->term_id, 'menu-item-object' => 'category', 'menu-item-type' => 'taxonomy',
                'menu-item-parent-id' => $item->menu_item_parent, 'menu-item-position' => $item->menu_order,
                'menu-item-title' => $term->name, 'menu-item-status' => 'publish',
                'menu-item-target' => $item->target, 'menu-item-classes' => implode( ' ', $item->classes ),
                'menu-item-xfn' => $item->xfn, 'menu-item-description' => $item->description,
            ) );
            if ( is_wp_error( $result ) ) return;
        }
    }
    update_option( 'zk_blog_categories_migrated', 1, false );
}
add_action( 'admin_init', 'zk_migrate_blog_category_listings' );

function zk_redirect_blog_listing_pages() {
    if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) || is_preview() || is_feed() || ! ( is_page() || is_404() ) ) return;
    $path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
    $term = zk_blog_listing_term( $path );
    if ( ! $term ) return;
    $url = get_term_link( $term );
    if ( is_wp_error( $url ) ) return;
    $query = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY );
    if ( $query ) $url .= '?' . $query;
    wp_safe_redirect( $url, 301 );
    exit;
}
add_action( 'template_redirect', 'zk_redirect_blog_listing_pages', 0 );

/** Register a previous listing address without needing to retain its old Page. */
function zk_blog_category_previous_url_field( $term ) {
    $paths = array_keys( array_filter( zk_blog_listing_routes(), function( $id ) use ( $term ) { return (int) $id === (int) $term->term_id; } ) );
    ?>
    <tr class="form-field"><th><label for="zk_previous_listing_paths">Previous listing paths</label></th><td>
        <?php wp_nonce_field( 'zk_category_listing_paths', 'zk_category_listing_nonce' ); ?>
        <textarea id="zk_previous_listing_paths" name="zk_previous_listing_paths" rows="3"><?php echo esc_textarea( implode( "\n", $paths ) ); ?></textarea>
        <p class="description">Optional: old Page paths, one per line (e.g. /blog/news/). Redirects continue after the Page is deleted. Do not enter article paths.</p>
    </td></tr>
    <?php
}
add_action( 'category_edit_form_fields', 'zk_blog_category_previous_url_field' );

function zk_save_blog_category_previous_urls( $term_id ) {
    if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['zk_previous_listing_paths'], $_POST['zk_category_listing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zk_category_listing_nonce'] ) ), 'zk_category_listing_paths' ) ) return;
    $routes = array_filter( zk_blog_listing_routes(), function( $id ) use ( $term_id ) { return (int) $id !== (int) $term_id; } );
    foreach ( preg_split( '/\R/', wp_unslash( $_POST['zk_previous_listing_paths'] ) ) as $path ) {
        $path = trim( $path );
        if ( ! preg_match( '#^/(?!/)[a-zA-Z0-9_/-]+/?$#', $path ) ) continue;
        $path = trailingslashit( $path );
        if ( zk_strip_language_prefix( $path ) !== $path || '/blog/' === $path ) continue;
        $page = get_page_by_path( trim( $path, '/' ) );
        if ( $page && 'publish' !== $page->post_status ) continue;
        if ( ! $page && ! isset( zk_blog_listing_routes()[ $path ] ) ) continue;
        $routes[ $path ] = (int) $term_id;
    }
    update_option( 'zk_blog_listing_routes', $routes, false );
}
add_action( 'edited_category', 'zk_save_blog_category_previous_urls' );

function zk_category_featured_image_id( $term_id ) {
    $id = absint( get_term_meta( $term_id, '_zk_featured_image_id', true ) );
    return $id && wp_attachment_is_image( $id ) ? $id : 0;
}

function zk_category_featured_image_control( $term_id = 0 ) {
    $id = zk_category_featured_image_id( $term_id );
    wp_nonce_field( 'zk_category_featured_image', 'zk_category_image_nonce' );
    ?>
    <div class="zk-category-image-control">
        <input type="hidden" name="zk_category_featured_image" value="<?php echo esc_attr( $id ); ?>">
        <div class="zk-category-image-preview" style="max-width:300px;margin-bottom:12px;">
            <?php if ( $id ) echo wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'max-width:100%;height:auto;' ) ); ?>
        </div>
        <button type="button" class="button zk-category-image-select">Choose featured image</button>
        <button type="button" class="button zk-category-image-remove" <?php if ( ! $id ) echo 'style="display:none"'; ?>>Remove image</button>
        <p class="description">Background photo for this category in all languages. The separate SEO Image URL overrides it for social sharing.</p>
    </div>
    <?php
}

add_action( 'category_add_form_fields', function() {
    echo '<div class="form-field"><label>Featured image</label>';
    zk_category_featured_image_control();
    echo '</div>';
} );
add_action( 'category_edit_form_fields', function( $term ) {
    echo '<tr class="form-field"><th scope="row">Featured image</th><td>';
    zk_category_featured_image_control( $term->term_id );
    echo '</td></tr>';
} );

add_action( 'admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if ( ! $screen || 'category' !== $screen->taxonomy || ! in_array( $screen->base, array( 'edit-tags', 'term' ), true ) ) return;
    wp_enqueue_media();
    wp_enqueue_script( 'zk-category-image', get_template_directory_uri() . '/inc/platform/category-image.js', array( 'jquery', 'media-editor' ), zk_asset_version( 'inc/platform/category-image.js' ), true );
} );

function zk_save_category_featured_image( $term_id ) {
    if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['zk_category_featured_image'], $_POST['zk_category_image_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zk_category_image_nonce'] ) ), 'zk_category_featured_image' ) ) return;
    $id = absint( $_POST['zk_category_featured_image'] );
    if ( ! $id ) delete_term_meta( $term_id, '_zk_featured_image_id' );
    elseif ( wp_attachment_is_image( $id ) ) update_term_meta( $term_id, '_zk_featured_image_id', $id );
}
add_action( 'created_category', 'zk_save_category_featured_image' );
add_action( 'edited_category', 'zk_save_category_featured_image' );

/** Carry over the former listing backgrounds without replacing an editor's choice. */
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'zk_category_images_migrated' ) ) return;
    foreach ( zk_blog_listing_routes() as $path => $term_id ) {
        $page = get_page_by_path( trim( $path, '/' ) );
        if ( $page && ! get_term_meta( $term_id, '_zk_featured_image_id', true ) ) {
            $id = get_post_thumbnail_id( $page->ID );
            if ( $id && wp_attachment_is_image( $id ) ) update_term_meta( $term_id, '_zk_featured_image_id', $id );
        }
    }
    update_option( 'zk_category_images_migrated', 1, false );
} );
