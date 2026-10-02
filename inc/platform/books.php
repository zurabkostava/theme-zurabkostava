<?php
/** Book records own their public URLs; reader storage keys stay independent. */
if ( ! defined( 'ABSPATH' ) ) exit;

function zk_book_records( $statuses = array( 'publish' ) ) {
    return get_posts( array( 'post_type' => 'zk_book', 'post_status' => $statuses, 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
}

function zk_book_reader_slug( $book ) {
    $book = get_post( $book );
    return $book ? ( get_post_meta( $book->ID, '_zk_book_reader_slug', true ) ?: $book->post_name ) : '';
}

function zk_book_url( $book, $language = null ) {
    $book = get_post( $book );
    if ( ! $book ) return '';
    $language = $language ?? zk_get_current_language();
    $path = get_post_meta( $book->ID, '_zk_book_path', true );
    if ( $path ) return home_url( zk_get_language_path( $path, $language ) );
    $translated = 'en' === $language ? '' : get_post_meta( $book->ID, '_zk_book_link_' . $language, true );
    return zk_normalize_internal_destination( $translated ?: get_post_meta( $book->ID, '_zk_book_link', true ) );
}

function zk_book_localized_detail( $id, $field, $language ) {
    $base = get_post_meta( $id, '_zk_book_' . $field, true );
    return 'en' === $language ? $base : ( get_post_meta( $id, '_zk_book_' . $field . '_' . $language, true ) ?: $base );
}

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'zk_book_publishing', 'Book destination & navigation', 'zk_book_publishing_fields', 'zk_book', 'normal', 'high' );
} );

function zk_book_publishing_fields( $post ) {
    wp_nonce_field( 'zk_book_publishing', 'zk_book_publishing_nonce' );
    $reader = (bool) get_post_meta( $post->ID, '_zk_book_path', true );
    echo '<p><label for="zk_book_destination"><strong>Book destination</strong></label><br><select id="zk_book_destination" name="zk_book_destination">';
    echo '<option value="reader" ' . selected( $reader, true, false ) . '>Interactive book reader</option><option value="external" ' . selected( $reader, false, false ) . '>External book / read link</option></select></p>';
    echo '<p><label for="zk_book_path"><strong>Public path</strong></label><br><input class="regular-text" id="zk_book_path" name="zk_book_path" placeholder="/books/my-book/" value="' . esc_attr( get_post_meta( $post->ID, '_zk_book_path', true ) ) . '"></p>';
    echo '<p><label for="zk_book_reader_slug"><strong>Reader storage key</strong></label><br><input class="regular-text" id="zk_book_reader_slug" name="zk_book_reader_slug" value="' . esc_attr( zk_book_reader_slug( $post ) ) . '" ' . ( $reader ? 'readonly' : '' ) . '></p>';
    echo '<p class="description">Public path can change without changing the reader storage key, chapters, audio or saved reading progress. Previous public paths redirect automatically. The reader key must match the existing book in Book Manager.</p>';
    echo '<p><label><input type="checkbox" name="zk_book_in_menu" value="1" ' . checked( get_post_meta( $post->ID, '_zk_book_in_menu', true ), '1', false ) . '> Show in Books dropdown</label></p>';
    echo '<p><label for="zk_book_menu_order">Dropdown order</label><br><input id="zk_book_menu_order" type="number" name="zk_book_menu_order" value="' . esc_attr( $post->menu_order ) . '"></p>';
    echo '<p class="description">Manage the cover, synopsis, translations and SEO/GEO on this Book. Chapters remain in Book Manager.</p>';
    $legacy = (int) get_post_meta( $post->ID, '_zk_book_legacy_page', true );
    if ( $legacy ) echo '<p>Original Page retained as a draft backup: <a href="' . esc_url( get_edit_post_link( $legacy ) ) . '">View original Page</a>.</p>';
}

function zk_save_book_destination( $id ) {
    if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) || ! isset( $_POST['zk_book_publishing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zk_book_publishing_nonce'] ) ), 'zk_book_publishing' ) ) return;
    $type = sanitize_key( $_POST['zk_book_destination'] ?? 'external' );
    $old_path = get_post_meta( $id, '_zk_book_path', true );
    $path = trim( sanitize_text_field( wp_unslash( $_POST['zk_book_path'] ?? '' ) ) );
    if ( 'reader' === $type ) {
        $path = trailingslashit( $path ?: '/books/' . get_post_field( 'post_name', $id ) . '/' );
        if ( ! preg_match( '#^/books/[a-z0-9-]+/$#', $path ) ) { zk_project_save_error( 'Book destination was not saved: use a path such as /books/my-book/.' ); return; }
        foreach ( zk_book_records( array( 'publish', 'draft', 'pending', 'future', 'private' ) ) as $other ) {
            $reserved = array_merge( array( get_post_meta( $other->ID, '_zk_book_path', true ) ), (array) get_post_meta( $other->ID, '_zk_book_previous_paths', true ) );
            if ( (int) $other->ID !== (int) $id && in_array( $path, $reserved, true ) ) { zk_project_save_error( 'Book destination was not saved: another Book reserves this path.' ); return; }
        }
        $page = get_page_by_path( trim( $path, '/' ) );
        if ( $page && 'publish' === $page->post_status ) { zk_project_save_error( 'Book destination was not saved: a published Page already uses this path.' ); return; }
        $key = get_post_meta( $id, '_zk_book_reader_slug', true ) ?: sanitize_title( wp_unslash( $_POST['zk_book_reader_slug'] ?? get_post_field( 'post_name', $id ) ) );
        if ( ! $key ) { zk_project_save_error( 'Book destination was not saved: a reader storage key is required.' ); return; }
        if ( $old_path && $old_path !== $path ) {
            $previous = (array) get_post_meta( $id, '_zk_book_previous_paths', true );
            $previous[] = $old_path;
            update_post_meta( $id, '_zk_book_previous_paths', array_values( array_diff( array_unique( array_filter( $previous ) ), array( $path ) ) ) );
        }
        update_post_meta( $id, '_zk_book_reader_slug', $key );
    } elseif ( 'external' === $type && ! $old_path ) {
        $path = '';
    } else { zk_project_save_error( 'Book destination was not saved: an existing reader must keep its public route.' ); return; }
    update_post_meta( $id, '_zk_book_path', $path );
    update_post_meta( $id, '_zk_book_in_menu', isset( $_POST['zk_book_in_menu'] ) ? '1' : '0' );
    global $wpdb;
    $wpdb->update( $wpdb->posts, array( 'menu_order' => (int) ( $_POST['zk_book_menu_order'] ?? 0 ) ), array( 'ID' => $id ) );
    clean_post_cache( $id );
}
add_action( 'save_post_zk_book', 'zk_save_book_destination' );

function zk_migrate_beta_book_record() {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'zk_beta_book_migrated_v1' ) ) return;
    $page = get_page_by_path( 'books/beta' );
    if ( ! $page || 'book-engine/template-book-reader.php' !== get_page_template_slug( $page->ID ) ) return;
    $books = get_posts( array( 'post_type' => 'zk_book', 'name' => 'beta', 'post_status' => 'publish', 'numberposts' => 1 ) );
    if ( ! $books ) { zk_project_save_error( 'BETA migration needs the existing published BETA Book record.' ); return; }
    $book = $books[0];
    if ( ! get_post_meta( $book->ID, '_zk_book_migration_backup', true ) ) {
        update_post_meta( $book->ID, '_zk_book_migration_backup', array( 'book' => (array) $book, 'book_meta' => get_post_meta( $book->ID ), 'page' => (array) $page, 'page_meta' => get_post_meta( $page->ID ) ) );
    }
    // Books already supplied the public SEO. Preserve those values and fill gaps only.
    foreach ( get_post_meta( $page->ID ) as $key => $values ) {
        if ( preg_match( '/^_zk_(seo_|geo_|title_|excerpt_|content_)/', $key ) && ! get_post_meta( $book->ID, $key, true ) && '' !== (string) $values[0] ) update_post_meta( $book->ID, $key, maybe_unserialize( $values[0] ) );
    }
    if ( ! has_post_thumbnail( $book->ID ) && has_post_thumbnail( $page->ID ) ) set_post_thumbnail( $book->ID, get_post_thumbnail_id( $page->ID ) );
    update_post_meta( $book->ID, '_zk_book_path', '/books/beta/' );
    update_post_meta( $book->ID, '_zk_book_reader_slug', $page->post_name );
    update_post_meta( $book->ID, '_zk_book_legacy_page', $page->ID );
    update_post_meta( $book->ID, '_zk_book_previous_paths', array( '/blog/zk_book/beta/' ) );
    foreach ( wp_get_nav_menus() as $menu ) {
        foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
            if ( (int) $item->object_id === (int) $page->ID || '/books/beta/' === trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $item->url, PHP_URL_PATH ) ) ) ) {
                update_post_meta( $book->ID, '_zk_book_in_menu', '1' );
                update_post_meta( $book->ID, '_zk_book_menu_target', $item->target );
                wp_update_post( array( 'ID' => $book->ID, 'menu_order' => $item->menu_order ) );
            }
        }
    }
    $result = wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ), true );
    if ( is_wp_error( $result ) ) { zk_project_save_error( $result->get_error_message() ); return; }
    update_option( 'zk_beta_book_migrated_v1', 1, false );
}
add_action( 'admin_init', 'zk_migrate_beta_book_record' );

add_filter( 'request', function( $query ) {
    if ( is_admin() || isset( $query['preview'] ) || isset( $query['feed'] ) || defined( 'REST_REQUEST' ) && REST_REQUEST ) return $query;
    $path = trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ) );
    foreach ( zk_book_records() as $book ) {
        $route = get_post_meta( $book->ID, '_zk_book_path', true );
        if ( $route && ( $path === $route || in_array( $path, (array) get_post_meta( $book->ID, '_zk_book_previous_paths', true ), true ) ) ) return array( 'post_type' => 'zk_book', 'p' => $book->ID );
    }
    return $query;
}, 21 );

add_filter( 'post_type_link', function( $url, $post ) {
    if ( 'zk_book' !== $post->post_type ) return $url;
    return zk_book_url( $post ) ?: $url;
}, 21, 2 );

add_filter( 'template_include', function( $template ) {
    return is_singular( 'zk_book' ) && get_post_meta( get_queried_object_id(), '_zk_book_path', true ) ? get_template_directory() . '/book-engine/template-book-reader.php' : $template;
}, 100 );

add_action( 'template_redirect', function() {
    if ( ! is_singular( 'zk_book' ) || is_preview() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) return;
    $book = get_queried_object();
    if ( ! get_post_meta( $book->ID, '_zk_book_path', true ) ) { wp_safe_redirect( home_url( zk_get_language_path( '/books/', zk_get_current_language() ) ), 301 ); exit; }
    $url = zk_book_url( $book );
    $request = wp_parse_url( defined( 'ZK_ORIGINAL_REQUEST_URI' ) ? ZK_ORIGINAL_REQUEST_URI : ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
    if ( wp_parse_url( $url, PHP_URL_PATH ) === $request ) return;
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        wp_parse_str( $_SERVER['QUERY_STRING'], $params );
        unset( $params['p'], $params['post_type'], $params['name'] );
        $url = add_query_arg( $params, $url );
    }
    wp_safe_redirect( $url, 301 ); exit;
}, 3 );

add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( is_admin() || ! get_option( 'zk_beta_book_migrated_v1' ) ) return $items;
    $parent = null;
    foreach ( $items as $item ) if ( ! $item->menu_item_parent && '/books/' === trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $item->url, PHP_URL_PATH ) ) ) ) { $parent = $item; break; }
    if ( ! $parent ) return $items;
    $removed = array( (int) $parent->ID );
    do {
        $count = count( $removed );
        foreach ( $items as $item ) if ( in_array( (int) $item->menu_item_parent, $removed, true ) && ! in_array( (int) $item->ID, $removed, true ) ) $removed[] = (int) $item->ID;
    } while ( $count !== count( $removed ) );
    $items = array_values( array_filter( $items, function( $item ) use ( $parent, $removed ) { return $item->ID === $parent->ID || ! in_array( (int) $item->ID, $removed, true ); } ) );
    $has_books = false;
    foreach ( zk_book_records() as $book ) {
        if ( '1' !== get_post_meta( $book->ID, '_zk_book_in_menu', true ) || ! ( $url = zk_book_url( $book ) ) ) continue;
        $has_books = true;
        $item = (object) array( 'ID' => -$book->ID, 'db_id' => -$book->ID, 'object_id' => $book->ID, 'object' => 'zk_book', 'type' => 'post_type', 'type_label' => 'Book', 'menu_item_parent' => $parent->ID, 'menu_order' => $book->menu_order, 'title' => $book->post_title, 'url' => $url, 'target' => get_post_meta( $book->ID, '_zk_book_menu_target', true ), 'attr_title' => '', 'description' => '', 'xfn' => '', 'classes' => array(), 'current' => false, 'current_item_parent' => false, 'current_item_ancestor' => false );
        if ( is_singular( 'zk_book' ) && get_queried_object_id() === $book->ID ) $item->classes[] = 'current-menu-item';
        $items[] = $item;
    }
    $parent->classes = array_diff( $parent->classes, array( 'menu-item-has-children' ) );
    if ( $has_books ) $parent->classes[] = 'menu-item-has-children';
    return $items;
}, 21, 2 );
