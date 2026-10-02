<?php
/** One project record owns its metadata, navigation and application route. */
if ( ! defined( 'ABSPATH' ) ) exit;

function zk_project_templates() {
    return array( 'detail' => '', 'wordevo' => 'page-wordevo.php', 'reader' => 'page-reader.php', 'encrolib' => 'encrolib/template-encrolib.php', 'instavery' => 'page-instavery.php', 'external' => '' );
}

function zk_project_records() {
    return get_posts( array( 'post_type' => 'zk_tool', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
}

function zk_project_save_error( $message ) {
    set_transient( 'zk_project_error_' . get_current_user_id(), $message, 60 );
}
add_action( 'admin_notices', function() {
    $key = 'zk_project_error_' . get_current_user_id();
    $message = get_transient( $key );
    if ( $message ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>'; }
} );

function zk_project_url( $project, $language = null ) {
    $project = get_post( $project );
    if ( ! $project ) return '';
    $language = $language ?? zk_get_current_language();
    $path = get_post_meta( $project->ID, '_zk_project_path', true );
    if ( $path ) return home_url( zk_get_language_path( $path, $language ) );
    $localized = 'en' === $language ? '' : get_post_meta( $project->ID, '_zk_tool_link_' . $language, true );
    return zk_normalize_internal_destination( $localized ?: get_post_meta( $project->ID, '_zk_tool_link', true ) );
}

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'zk_project_publishing', 'Project destination & navigation', 'zk_project_publishing_fields', 'zk_tool', 'normal', 'high' );
} );

function zk_project_publishing_fields( $post ) {
    wp_nonce_field( 'zk_project_publishing', 'zk_project_publishing_nonce' );
    $type = get_post_meta( $post->ID, '_zk_project_app', true ) ?: 'external';
    echo '<p><label for="zk_project_app"><strong>Project type</strong></label><br><select id="zk_project_app" name="zk_project_app">';
    foreach ( array( 'detail' => 'Project information page', 'wordevo' => 'WordEvo application', 'reader' => 'ReadRoad application', 'encrolib' => 'Encrolib application', 'instavery' => 'Instavery application', 'external' => 'External website / link' ) as $key => $label ) {
        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p><p><label for="zk_project_path"><strong>Public path (internal projects)</strong></label><br>';
    echo '<input id="zk_project_path" name="zk_project_path" value="' . esc_attr( get_post_meta( $post->ID, '_zk_project_path', true ) ) . '" placeholder="/projects/my-project/" class="regular-text"></p>';
    echo '<p><label><input type="checkbox" name="zk_project_in_menu" value="1" ' . checked( get_post_meta( $post->ID, '_zk_project_in_menu', true ), '1', false ) . '> Show in Projects dropdown</label></p>';
    echo '<p><label for="zk_project_menu_order">Dropdown order</label><br><input type="number" id="zk_project_menu_order" name="zk_project_menu_order" value="' . esc_attr( $post->menu_order ) . '"></p>';
    echo '<p class="description">Manage the title, description, featured image, translations and SEO/GEO here. Internal projects keep their app URL. External projects use Project Link below. New projects appear in the dropdown only when selected.</p>';
}

add_action( 'save_post_zk_tool', function( $id ) {
    if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) || ! isset( $_POST['zk_project_publishing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zk_project_publishing_nonce'] ) ), 'zk_project_publishing' ) ) return;
    $type = sanitize_key( $_POST['zk_project_app'] ?? 'external' );
    if ( ! array_key_exists( $type, zk_project_templates() ) ) return;
    $path = trim( sanitize_text_field( wp_unslash( $_POST['zk_project_path'] ?? '' ) ) );
    if ( 'external' !== $type ) {
        if ( ! $path ) $path = '/projects/' . get_post_field( 'post_name', $id ) . '/';
        if ( ! preg_match( '#^/projects/[a-z0-9-]+/?$#', $path ) ) { zk_project_save_error( 'Project settings were not saved: use a path such as /projects/my-project/.' ); return; }
        $path = trailingslashit( $path );
        foreach ( zk_project_records() as $other ) {
            if ( (int) $other->ID !== (int) $id && $path === get_post_meta( $other->ID, '_zk_project_path', true ) ) { zk_project_save_error( 'Project settings were not saved: this path belongs to another project.' ); return; }
        }
        $page = get_page_by_path( trim( $path, '/' ) );
        if ( $page && 'publish' === $page->post_status ) { zk_project_save_error( 'Project settings were not saved: an existing published Page uses this path. Choose another path.' ); return; }
        // Existing routes are stable: changing the project name does not break app/PWA URLs.
        $old_path = get_post_meta( $id, '_zk_project_path', true );
        if ( $old_path && $old_path !== $path ) { zk_project_save_error( 'Project settings were not saved: existing app paths are kept stable to protect shared links and installed apps.' ); return; }
    } else {
        if ( get_post_meta( $id, '_zk_project_path', true ) ) { zk_project_save_error( 'Project settings were not saved: an existing internal project must keep its public route.' ); return; }
        $path = '';
    }
    update_post_meta( $id, '_zk_project_app', $type );
    update_post_meta( $id, '_zk_project_path', $path );
    update_post_meta( $id, '_zk_project_in_menu', isset( $_POST['zk_project_in_menu'] ) ? '1' : '0' );
    // Avoid recursive save_post when updating menu_order.
    global $wpdb;
    $wpdb->update( $wpdb->posts, array( 'menu_order' => (int) ( $_POST['zk_project_menu_order'] ?? 0 ) ), array( 'ID' => $id ) );
    clean_post_cache( $id );
} );

function zk_migrate_project_records() {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'zk_project_records_migrated_v1' ) ) return;
    $projects = zk_project_records();
    $migrated = array();
    foreach ( array( 'projects/wordevo' => 'wordevo', 'projects/reader' => 'reader', 'projects/encrolib' => 'encrolib' ) as $route => $app ) {
        $page = get_page_by_path( $route );
        if ( ! $page || 'publish' !== $page->post_status ) continue;
        $path = '/' . $route . '/';
        $project = null;
        foreach ( $projects as $candidate ) {
            $link = get_post_meta( $candidate->ID, '_zk_tool_link', true );
            if ( trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $link, PHP_URL_PATH ) ) ) === $path ) { $project = $candidate; break; }
        }
        if ( ! $project ) {
            $id = wp_insert_post( array( 'post_type' => 'zk_tool', 'post_status' => 'publish', 'post_title' => $page->post_title, 'post_name' => $page->post_name, 'post_content' => $page->post_content, 'post_excerpt' => $page->post_excerpt, 'post_date' => $page->post_date ), true );
            if ( is_wp_error( $id ) ) return;
            $project = get_post( $id );
            $projects[] = $project;
        }
        // Public-page SEO is authoritative. Keep a backup of both records before merging.
        if ( ! get_post_meta( $project->ID, '_zk_project_migration_backup', true ) ) update_post_meta( $project->ID, '_zk_project_migration_backup', array( 'project_meta' => get_post_meta( $project->ID ), 'page_id' => $page->ID, 'page_meta' => get_post_meta( $page->ID ) ) );
        foreach ( get_post_meta( $page->ID ) as $key => $values ) {
            if ( preg_match( '/^_zk_(seo_|geo_)/', $key ) && '' !== (string) $values[0] ) update_post_meta( $project->ID, $key, maybe_unserialize( $values[0] ) );
            elseif ( preg_match( '/^_zk_(title|excerpt|content)_/', $key ) && ! get_post_meta( $project->ID, $key, true ) ) update_post_meta( $project->ID, $key, maybe_unserialize( $values[0] ) );
        }
        if ( ! has_post_thumbnail( $project->ID ) && has_post_thumbnail( $page->ID ) ) set_post_thumbnail( $project->ID, get_post_thumbnail_id( $page->ID ) );
        update_post_meta( $project->ID, '_zk_project_app', $app );
        update_post_meta( $project->ID, '_zk_project_path', $path );
        update_post_meta( $project->ID, '_zk_tool_link', home_url( $path ) );
        update_post_meta( $project->ID, '_zk_project_legacy_page', $page->ID );
        $migrated[ $page->ID ] = $project->ID;
    }
    // Preserve the existing selected projects and their order, including external projects.
    foreach ( wp_get_nav_menus() as $menu ) {
        foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
            foreach ( zk_project_records() as $project ) {
                $url = zk_project_url( $project, 'en' );
                if ( isset( $migrated[ $item->object_id ] ) && $migrated[ $item->object_id ] === $project->ID || $url && untrailingslashit( $item->url ) === untrailingslashit( $url ) ) {
                    update_post_meta( $project->ID, '_zk_project_in_menu', '1' );
                    update_post_meta( $project->ID, '_zk_project_menu_target', $item->target );
                    wp_update_post( array( 'ID' => $project->ID, 'menu_order' => $item->menu_order ) );
                }
            }
        }
    }
    // Draft copies remain recoverable; public requests now resolve to the Project record.
    foreach ( $migrated as $page_id => $project_id ) {
        if ( is_wp_error( wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ), true ) ) ) return;
    }
    update_option( 'zk_project_records_migrated_v1', 1, false );
}
add_action( 'admin_init', 'zk_migrate_project_records' );

add_filter( 'request', function( $query ) {
    if ( is_admin() || isset( $query['preview'] ) || isset( $query['feed'] ) || defined( 'REST_REQUEST' ) && REST_REQUEST ) return $query;
    $path = trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ) );
    foreach ( zk_project_records() as $project ) {
        $route = get_post_meta( $project->ID, '_zk_project_path', true );
        if ( $route && $path === $route ) {
            return array( 'post_type' => 'zk_tool', 'p' => $project->ID );
        }
    }
    return $query;
}, 20 );

add_filter( 'post_type_link', function( $url, $post ) {
    if ( 'zk_tool' !== $post->post_type ) return $url;
    $path = get_post_meta( $post->ID, '_zk_project_path', true );
    return $path ? home_url( zk_get_language_path( $path, zk_get_current_language() ) ) : $url;
}, 20, 2 );

add_filter( 'template_include', function( $template ) {
    if ( ! is_singular( 'zk_tool' ) ) return $template;
    $app = get_post_meta( get_queried_object_id(), '_zk_project_app', true );
    $templates = zk_project_templates();
    return ! empty( $templates[ $app ] ) ? get_template_directory() . '/' . $templates[ $app ] : $template;
}, 99 );

add_action( 'template_redirect', function() {
    if ( ! is_singular( 'zk_tool' ) || is_preview() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) return;
    $project = get_queried_object();
    $url = zk_project_url( $project );
    if ( ! $url ) return;
    $path = wp_parse_url( $url, PHP_URL_PATH );
    $request = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
    $request = zk_get_language_path( zk_strip_language_prefix( $request ), zk_get_current_language() );
    if ( $path !== $request || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        wp_redirect( $url, 301 );
        exit;
    }
}, 3 );

add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( is_admin() ) return $items;
    $parent = null;
    foreach ( $items as $item ) {
        if ( ! $item->menu_item_parent && '/projects/' === trailingslashit( zk_strip_language_prefix( (string) wp_parse_url( $item->url, PHP_URL_PATH ) ) ) ) { $parent = $item; break; }
    }
    if ( ! $parent || ! get_option( 'zk_project_records_migrated_v1' ) ) return $items;
    $removed = array( $parent->ID );
    do {
        $count = count( $removed );
        foreach ( $items as $item ) if ( in_array( (int) $item->menu_item_parent, $removed, true ) && ! in_array( $item->ID, $removed, true ) ) $removed[] = $item->ID;
    } while ( $count !== count( $removed ) );
    $items = array_values( array_filter( $items, function( $item ) use ( $parent, $removed ) { return $item->ID === $parent->ID || ! in_array( $item->ID, $removed, true ); } ) );
    $has_projects = false;
    foreach ( zk_project_records() as $project ) {
        if ( '1' !== get_post_meta( $project->ID, '_zk_project_in_menu', true ) || ! ( $url = zk_project_url( $project ) ) ) continue;
        $has_projects = true;
        $item = (object) array( 'ID' => -$project->ID, 'db_id' => -$project->ID, 'object_id' => $project->ID, 'object' => 'zk_tool', 'type' => 'post_type', 'type_label' => 'Project', 'menu_item_parent' => $parent->ID, 'menu_order' => $project->menu_order, 'title' => $project->post_title, 'url' => $url, 'target' => '', 'attr_title' => '', 'description' => '', 'xfn' => '', 'classes' => array(), 'current' => false, 'current_item_parent' => false, 'current_item_ancestor' => false );
        if ( is_singular( 'zk_tool' ) && get_queried_object_id() === $project->ID ) $item->classes[] = 'current-menu-item';
        $item->target = get_post_meta( $project->ID, '_zk_project_menu_target', true );
        $items[] = $item;
    }
    $parent->classes = array_diff( $parent->classes, array( 'menu-item-has-children' ) );
    if ( $has_projects ) $parent->classes[] = 'menu-item-has-children';
    return $items;
}, 20, 2 );
