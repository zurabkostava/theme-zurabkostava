<?php
/**
 * Language Center: language registry and translation coverage dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function zk_language_center_menu() {
    add_menu_page(
        'Language Center',
        'Language Center',
        'manage_options',
        'zk-language-center',
        'zk_render_language_center',
        'dashicons-translation',
        21
    );
}
add_action( 'admin_menu', 'zk_language_center_menu' );

function zk_language_center_save_languages() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage languages.', 'zurabkostava' ) );
    }

    check_admin_referer( 'zk_save_languages', 'zk_languages_nonce' );
    $languages = zk_get_languages( false );

    if ( isset( $_POST['zk_existing_languages'] ) && is_array( $_POST['zk_existing_languages'] ) ) {
        foreach ( wp_unslash( $_POST['zk_existing_languages'] ) as $code => $language ) {
            if ( ! isset( $languages[ $code ] ) ) {
                continue;
            }
            $language['code']    = $code;
            $language['enabled'] = in_array( $code, array( 'en', 'ka' ), true ) || ! empty( $language['enabled'] );
            $clean = zk_sanitize_language_definition( $language, $code );
            if ( $clean ) {
                $languages[ $code ] = $clean;
            }
        }
    }

    $new = isset( $_POST['zk_new_language'] ) && is_array( $_POST['zk_new_language'] )
        ? wp_unslash( $_POST['zk_new_language'] )
        : array();
    if ( ! empty( $new['code'] ) ) {
        // New languages start disabled so an empty translation never appears in
        // the public switcher before its important pages are ready.
        $new['enabled'] = false;
        $clean = zk_sanitize_language_definition( $new );
        if ( $clean && ! isset( $languages[ $clean['code'] ] ) ) {
            $languages[ $clean['code'] ] = $clean;
        }
    }

    update_option( 'zk_languages_v1', $languages, false );
    wp_safe_redirect( add_query_arg( array( 'page' => 'zk-language-center', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_zk_save_languages', 'zk_language_center_save_languages' );

function zk_language_center_post_types() {
    $types = get_post_types( array( 'public' => true ), 'objects' );
    unset( $types['attachment'] );
    foreach ( array( 'zk_music_release', 'zk_book', 'zk_tool', 'zk_welcome_music' ) as $name ) {
        $object = get_post_type_object( $name );
        if ( $object ) {
            $types[ $name ] = $object;
        }
    }
    return $types;
}

function zk_language_center_audit_posts( $code ) {
    $post_types = array_keys( zk_language_center_post_types() );
    $posts = get_posts( array(
        'post_type'        => $post_types,
        'post_status'      => array( 'publish', 'private', 'draft' ),
        'numberposts'      => -1,
        'orderby'          => 'post_type title',
        'order'            => 'ASC',
        'suppress_filters' => true,
    ) );

    $rows = array();
    $ignored_templates = apply_filters( 'zk_language_center_ignored_templates', array(
        'page-instavery.php',
        'page-wordevo.php',
        'page-reader.php',
        'template-analytics.php',
        'book-engine/template-book-manager.php',
        'book-engine/template-book-reader.php',
        'encrolib/template-encrolib.php',
    ) );
    $ignored_post_types = apply_filters( 'zk_language_center_ignored_post_types', array( 'zk_tool' ) );
    foreach ( $posts as $post ) {
        if ( in_array( $post->post_type, $ignored_post_types, true ) ) {
            continue;
        }
        if ( 'page' === $post->post_type && in_array( get_page_template_slug( $post->ID ), $ignored_templates, true ) ) {
            continue;
        }
        $required = array( 'title' );
        if ( '' !== trim( wp_strip_all_tags( $post->post_excerpt ) ) ) {
            $required[] = 'excerpt';
        }
        if ( '' !== trim( wp_strip_all_tags( $post->post_content ) ) ) {
            $required[] = 'content';
        }

        $completed = 0;
        $missing   = array();
        foreach ( $required as $field ) {
            $value = get_post_meta( $post->ID, zk_language_meta_key( $field, $code ), true );
            if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
                $completed++;
            } else {
                $missing[] = $field;
            }
        }

        $rows[] = array(
            'id'        => $post->ID,
            'title'     => get_the_title( $post ),
            'type'      => $post->post_type,
            'status'    => empty( $missing ) ? 'complete' : ( $completed ? 'partial' : 'missing' ),
            'completed' => $completed,
            'required'  => count( $required ),
            'missing'   => $missing,
            'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
        );
    }
    return $rows;
}

function zk_language_center_audit_terms( $code ) {
    $terms = get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) );
    $rows  = array();
    if ( is_wp_error( $terms ) ) {
        return $rows;
    }
    foreach ( $terms as $term ) {
        $has_name = '' !== trim( (string) get_term_meta( $term->term_id, zk_language_meta_key( 'name', $code ), true ) );
        $needs_description = '' !== trim( wp_strip_all_tags( $term->description ) );
        $has_description = ! $needs_description || '' !== trim( (string) get_term_meta( $term->term_id, zk_language_meta_key( 'description', $code ), true ) );
        $rows[] = array(
            'title'    => $term->name,
            'taxonomy' => $term->taxonomy,
            'status'   => $has_name && $has_description ? 'complete' : ( $has_name ? 'partial' : 'missing' ),
            'edit_url' => get_edit_term_link( $term->term_id, $term->taxonomy ),
        );
    }
    return $rows;
}

function zk_language_center_status_label( $status ) {
    $labels = array( 'complete' => 'Complete', 'partial' => 'Partial', 'missing' => 'Missing' );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

function zk_render_language_center() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $languages = zk_get_languages( false );
    $selected  = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : 'ka';
    if ( 'en' === $selected || ! isset( $languages[ $selected ] ) ) {
        $selected = 'ka';
    }
    $language = $languages[ $selected ];
    $posts    = zk_language_center_audit_posts( $selected );
    $terms    = zk_language_center_audit_terms( $selected );
    $all      = array_merge( $posts, $terms );
    $complete = count( array_filter( $all, function( $row ) { return 'complete' === $row['status']; } ) );
    $partial  = count( array_filter( $all, function( $row ) { return 'partial' === $row['status']; } ) );
    $missing  = count( $all ) - $complete - $partial;
    $percent  = count( $all ) ? (int) round( ( $complete / count( $all ) ) * 100 ) : 100;
    ?>
    <div class="wrap zk-language-center">
        <h1>Language Center</h1>
        <p>Manage site languages and see which public content still needs translation. English is the source language; empty translations safely fall back to English. Test applications and project entries are currently excluded from coverage.</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Languages saved.</p></div><?php endif; ?>

        <style>
            .zk-language-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;max-width:900px;margin:18px 0}.zk-language-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px}.zk-language-card strong{display:block;font-size:28px;margin-top:6px}.zk-language-progress{height:12px;background:#dcdcde;border-radius:10px;overflow:hidden;max-width:900px}.zk-language-progress span{display:block;height:100%;background:#2271b1}.zk-status-complete{color:#008a20}.zk-status-partial{color:#b26200}.zk-status-missing{color:#b32d2e}.zk-language-table input[type=text]{width:100%}.zk-language-section{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-top:22px;max-width:1100px}
        </style>

        <div class="zk-language-section">
            <h2>Translation coverage: <?php echo esc_html( $language['native_name'] ); ?></h2>
            <form method="get" style="margin-bottom:16px">
                <input type="hidden" name="page" value="zk-language-center">
                <label for="zk-audit-language"><strong>Language:</strong></label>
                <select id="zk-audit-language" name="language" onchange="this.form.submit()">
                    <?php foreach ( zk_get_translatable_languages( false ) as $code => $item ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected, $code ); ?>><?php echo esc_html( $item['native_name'] . ' (' . strtoupper( $code ) . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="zk-language-progress" aria-label="<?php echo esc_attr( $percent . '% complete' ); ?>"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></div>
            <div class="zk-language-cards">
                <div class="zk-language-card">Coverage<strong><?php echo esc_html( $percent ); ?>%</strong></div>
                <div class="zk-language-card">Complete<strong class="zk-status-complete"><?php echo esc_html( $complete ); ?></strong></div>
                <div class="zk-language-card">Partial<strong class="zk-status-partial"><?php echo esc_html( $partial ); ?></strong></div>
                <div class="zk-language-card">Missing<strong class="zk-status-missing"><?php echo esc_html( $missing ); ?></strong></div>
            </div>

            <h3>Content requiring attention</h3>
            <table class="widefat striped">
                <thead><tr><th>Content</th><th>Type</th><th>Status</th><th>Missing fields</th><th></th></tr></thead>
                <tbody>
                <?php $shown = 0; foreach ( $posts as $row ) : if ( 'complete' === $row['status'] ) continue; $shown++; ?>
                    <tr><td><?php echo esc_html( $row['title'] ?: '(Untitled)' ); ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td class="zk-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( zk_language_center_status_label( $row['status'] ) ); ?></td><td><?php echo esc_html( implode( ', ', $row['missing'] ) ); ?></td><td><a class="button" href="<?php echo esc_url( $row['edit_url'] ); ?>">Edit translation</a></td></tr>
                <?php endforeach; if ( ! $shown ) : ?><tr><td colspan="5">All content translations are complete.</td></tr><?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:24px">Categories and tags</h3>
            <table class="widefat striped"><thead><tr><th>Name</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
                <?php $shown = 0; foreach ( $terms as $row ) : if ( 'complete' === $row['status'] ) continue; $shown++; ?>
                    <tr><td><?php echo esc_html( $row['title'] ); ?></td><td><?php echo esc_html( $row['taxonomy'] ); ?></td><td class="zk-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( zk_language_center_status_label( $row['status'] ) ); ?></td><td><a class="button" href="<?php echo esc_url( $row['edit_url'] ); ?>">Edit translation</a></td></tr>
                <?php endforeach; if ( ! $shown ) : ?><tr><td colspan="4">All category and tag translations are complete.</td></tr><?php endif; ?>
            </tbody></table>
        </div>

        <div class="zk-language-section">
            <h2>Site languages</h2>
            <p>Adding a language creates its URL prefix and translation fields. Keep it disabled until its important pages are ready.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="zk_save_languages">
                <?php wp_nonce_field( 'zk_save_languages', 'zk_languages_nonce' ); ?>
                <table class="widefat striped zk-language-table"><thead><tr><th>Code</th><th>Name</th><th>Native name</th><th>Locale</th><th>URL prefix</th><th>Enabled</th></tr></thead><tbody>
                <?php foreach ( $languages as $code => $item ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( strtoupper( $code ) ); ?></strong></td>
                        <td><input type="text" name="zk_existing_languages[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>"></td>
                        <td><input type="text" name="zk_existing_languages[<?php echo esc_attr( $code ); ?>][native_name]" value="<?php echo esc_attr( $item['native_name'] ); ?>"></td>
                        <td><input type="text" name="zk_existing_languages[<?php echo esc_attr( $code ); ?>][locale]" value="<?php echo esc_attr( $item['locale'] ); ?>"></td>
                        <td><input type="text" name="zk_existing_languages[<?php echo esc_attr( $code ); ?>][prefix]" value="<?php echo esc_attr( $item['prefix'] ); ?>" <?php disabled( 'en', $code ); ?>></td>
                        <td><label><input type="checkbox" name="zk_existing_languages[<?php echo esc_attr( $code ); ?>][enabled]" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?> <?php disabled( in_array( $code, array( 'en', 'ka' ), true ) ); ?>> Active</label></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>

                <h3>Add a language</h3>
                <table class="form-table"><tbody>
                    <tr><th><label for="zk-new-code">Language code</label></th><td><input id="zk-new-code" name="zk_new_language[code]" type="text" maxlength="3" placeholder="de"> <p class="description">Two or three lowercase letters, such as de or fr.</p></td></tr>
                    <tr><th><label for="zk-new-name">English name</label></th><td><input id="zk-new-name" name="zk_new_language[name]" type="text" placeholder="German"></td></tr>
                    <tr><th><label for="zk-new-native">Native name</label></th><td><input id="zk-new-native" name="zk_new_language[native_name]" type="text" placeholder="Deutsch"></td></tr>
                    <tr><th><label for="zk-new-locale">Locale</label></th><td><input id="zk-new-locale" name="zk_new_language[locale]" type="text" placeholder="de-DE"></td></tr>
                    <tr><th><label for="zk-new-prefix">URL prefix</label></th><td><input id="zk-new-prefix" name="zk_new_language[prefix]" type="text" placeholder="de"></td></tr>
                </tbody></table>
                <?php submit_button( 'Save languages' ); ?>
            </form>
        </div>
    </div>
    <?php
}

