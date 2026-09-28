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

function zk_language_center_save_gallery_labels() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage languages.', 'zurabkostava' ) );
    }

    check_admin_referer( 'zk_save_gallery_labels', 'zk_gallery_labels_nonce' );
    $language = isset( $_POST['zk_gallery_language'] ) ? sanitize_key( wp_unslash( $_POST['zk_gallery_language'] ) ) : '';
    $languages = zk_get_translatable_languages( false );
    if ( ! $language || ! isset( $languages[ $language ] ) ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'zk-language-center' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $submitted = isset( $_POST['zk_gallery_labels'] ) && is_array( $_POST['zk_gallery_labels'] )
        ? wp_unslash( $_POST['zk_gallery_labels'] )
        : array();
    $saved = get_option( 'zk_gallery_labels_v1', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    $saved[ $language ] = array();
    foreach ( array( 'all', 'camera', 'mobile' ) as $key ) {
        $value = isset( $submitted[ $key ] ) ? sanitize_text_field( $submitted[ $key ] ) : '';
        if ( '' !== $value ) {
            $saved[ $language ][ $key ] = $value;
        }
    }

    update_option( 'zk_gallery_labels_v1', $saved, false );
    if ( function_exists( 'zk_flush_gallery_cache' ) ) {
        zk_flush_gallery_cache();
    }

    wp_safe_redirect( add_query_arg( array(
        'page'           => 'zk-language-center',
        'language'       => $language,
        'labels-updated' => '1',
    ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_zk_save_gallery_labels', 'zk_language_center_save_gallery_labels' );

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

/**
 * Return true when post content contains human-readable copy after registered
 * shortcodes and block comments are removed.
 */
function zk_language_center_has_translatable_content( $content ) {
    if ( '' === trim( (string) $content ) ) {
        return false;
    }

    $content = strip_shortcodes( (string) $content );
    $content = preg_replace( '/<!--(?:.|\s)*?-->/', '', $content );
    $content = str_replace( array( '&nbsp;', '&#160;' ), ' ', $content );
    return '' !== trim( wp_strip_all_tags( $content ) );
}

/**
 * Fields that must exist before a translated post can be advertised to search
 * engines as a complete language alternate.
 */
function zk_language_translation_post_requirements( $post, $code ) {
    $post = get_post( $post );
    if ( ! $post || 'en' === $code ) {
        return array();
    }

    $required = array( 'title' => zk_language_meta_key( 'title', $code ) );
    if ( '' !== trim( wp_strip_all_tags( $post->post_excerpt ) ) ) {
        $required['excerpt'] = zk_language_meta_key( 'excerpt', $code );
    }
    if ( zk_language_center_has_translatable_content( $post->post_content ) ) {
        $required['content'] = zk_language_meta_key( 'content', $code );
    }

    foreach ( array(
        'SEO title'       => 'seo_title',
        'SEO description' => 'seo_description',
        'GEO summary'     => 'geo_ai_summary',
        'GEO FAQ'         => 'geo_faq',
    ) as $label => $field ) {
        $source = get_post_meta( $post->ID, '_zk_' . $field, true );
        if ( '' !== trim( wp_strip_all_tags( (string) $source ) ) ) {
            $required[ $label ] = zk_language_meta_key( $field, $code );
        }
    }
    return $required;
}

function zk_is_post_translation_complete( $post, $code ) {
    // Preserve the already-established English and Georgian index. Languages
    // added later are held back until every required translation is ready.
    if ( in_array( $code, array( 'en', 'ka' ), true ) ) return true;
    $post = get_post( $post );
    if ( ! $post ) return false;
    foreach ( zk_language_translation_post_requirements( $post, $code ) as $meta_key ) {
        $value = get_post_meta( $post->ID, $meta_key, true );
        if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) return false;
    }
    return true;
}

function zk_language_translation_term_requirements( $term, $code ) {
    $term = get_term( $term );
    if ( ! $term || is_wp_error( $term ) || 'en' === $code ) return array();

    $required = array( 'name' => zk_language_meta_key( 'name', $code ) );
    if ( '' !== trim( wp_strip_all_tags( $term->description ) ) ) {
        $required['description'] = zk_language_meta_key( 'description', $code );
    }
    foreach ( array(
        'SEO title'       => 'seo_title',
        'SEO description' => 'seo_description',
        'GEO summary'     => 'geo_ai_summary',
        'GEO FAQ'         => 'geo_faq',
    ) as $label => $field ) {
        $source = get_term_meta( $term->term_id, '_zk_' . $field, true );
        if ( '' !== trim( wp_strip_all_tags( (string) $source ) ) ) {
            $required[ $label ] = zk_language_meta_key( $field, $code );
        }
    }
    return $required;
}

function zk_is_term_translation_complete( $term, $code ) {
    if ( in_array( $code, array( 'en', 'ka' ), true ) ) return true;
    $term = get_term( $term );
    if ( ! $term || is_wp_error( $term ) ) return false;
    foreach ( zk_language_translation_term_requirements( $term, $code ) as $meta_key ) {
        $value = get_term_meta( $term->term_id, $meta_key, true );
        if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) return false;
    }
    return true;
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
    foreach ( $posts as $post ) {
        $required = zk_language_translation_post_requirements( $post, $code );

        $completed = 0;
        $missing   = array();
        foreach ( $required as $label => $meta_key ) {
            $value = get_post_meta( $post->ID, $meta_key, true );
            if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
                $completed++;
            } else {
                $missing[] = $label;
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
        $required = zk_language_translation_term_requirements( $term, $code );
        $completed = 0;
        foreach ( $required as $meta_key ) {
            if ( '' !== trim( wp_strip_all_tags( (string) get_term_meta( $term->term_id, $meta_key, true ) ) ) ) $completed++;
        }
        $rows[] = array(
            'title'    => $term->name,
            'taxonomy' => $term->taxonomy,
            'status'   => $completed === count( $required ) ? 'complete' : ( $completed ? 'partial' : 'missing' ),
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
        <p>Manage site languages and see which public content still needs translation. English is the source language; empty translations safely fall back to English.</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Languages saved.</p></div><?php endif; ?>
        <?php if ( isset( $_GET['labels-updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Photography filter translations saved.</p></div><?php endif; ?>

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

            <?php $gallery_labels = function_exists( 'zk_gallery_labels' ) ? zk_gallery_labels( $selected ) : array( 'all' => 'All', 'camera' => 'Camera', 'mobile' => 'Mobile' ); ?>
            <h3 style="margin-top:24px">Photography filter tabs</h3>
            <p>Translate the three tabs shown above the photography gallery for <?php echo esc_html( $language['native_name'] ); ?>. Empty fields fall back to English.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="zk_save_gallery_labels">
                <input type="hidden" name="zk_gallery_language" value="<?php echo esc_attr( $selected ); ?>">
                <?php wp_nonce_field( 'zk_save_gallery_labels', 'zk_gallery_labels_nonce' ); ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="zk-gallery-all">All</label></th><td><input class="regular-text" id="zk-gallery-all" name="zk_gallery_labels[all]" type="text" value="<?php echo esc_attr( $gallery_labels['all'] ); ?>" placeholder="All"></td></tr>
                    <tr><th><label for="zk-gallery-camera">Camera</label></th><td><input class="regular-text" id="zk-gallery-camera" name="zk_gallery_labels[camera]" type="text" value="<?php echo esc_attr( $gallery_labels['camera'] ); ?>" placeholder="Camera"></td></tr>
                    <tr><th><label for="zk-gallery-mobile">Mobile</label></th><td><input class="regular-text" id="zk-gallery-mobile" name="zk_gallery_labels[mobile]" type="text" value="<?php echo esc_attr( $gallery_labels['mobile'] ); ?>" placeholder="Mobile"></td></tr>
                </tbody></table>
                <?php submit_button( 'Save photography translations', 'secondary' ); ?>
            </form>

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

