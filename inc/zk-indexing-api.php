<?php
/**
 * ZK Google Indexing API Integration
 */

// 1. JWT Generator
function zk_generate_google_jwt($client_email, $private_key) {
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $client_email,
        'sub' => $client_email,
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/indexing'
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = '';
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $private_key, 'sha256WithRSAEncryption');
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// 2. Fetch Access Token
function zk_get_google_access_token($json_data) {
    // Cache the token for 50 minutes to avoid hitting Google OAuth limits
    $transient_name = 'zk_google_indexing_token';
    $cached_token = get_transient($transient_name);
    if ($cached_token) {
        return $cached_token;
    }

    $jwt = zk_generate_google_jwt($json_data['client_email'], $json_data['private_key']);
    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 15,
        'body' => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]
    ]);
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['access_token'])) {
        set_transient($transient_name, $body['access_token'], 50 * MINUTE_IN_SECONDS);
        return $body['access_token'];
    }
    return false;
}

// 3. API Request
function zk_notify_google_indexing($url, $type = 'URL_UPDATED') {
    $json_key = get_option('zk_indexing_json_key');
    if (!$json_key) return new WP_Error('no_key', 'JSON Key is not set.');
    
    $json_data = json_decode($json_key, true);
    if (!$json_data || !isset($json_data['private_key'])) return new WP_Error('invalid_key', 'Invalid JSON Key.');

    $access_token = zk_get_google_access_token($json_data);
    if (!$access_token) return new WP_Error('no_token', 'Failed to retrieve access token.');

    $response = wp_remote_post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'url' => $url,
            'type' => $type
        ])
    ]);
    
    return $response;
}

/**
 * Check translation completeness without the legacy EN/KA indexing exception.
 * The bulk submitter must follow the Language Center's actual field coverage.
 */
function zk_indexing_translation_is_complete( $post, $code ) {
    $post = get_post( $post );
    if ( ! $post || 'en' === $code || 'publish' !== $post->post_status ) {
        return false;
    }

    if ( ! function_exists( 'zk_language_translation_post_requirements' ) ) {
        return false;
    }

    foreach ( zk_language_translation_post_requirements( $post, $code ) as $meta_key ) {
        $value = get_post_meta( $post->ID, $meta_key, true );
        if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Collect every completed translation URL for enabled non-source languages.
 */
function zk_indexing_completed_translation_urls() {
    if ( ! function_exists( 'zk_get_translatable_languages' ) || ! function_exists( 'zk_get_language_path' ) ) {
        return array();
    }

    $post_types = function_exists( 'zk_language_center_post_types' )
        ? array_keys( zk_language_center_post_types() )
        : array( 'post', 'page', 'books' );
    $posts = get_posts( array(
        'post_type'        => $post_types,
        'post_status'      => 'publish',
        'numberposts'      => -1,
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'suppress_filters' => true,
    ) );

    $urls = array();
    foreach ( zk_get_translatable_languages() as $code => $language ) {
        foreach ( $posts as $post ) {
            if ( ! zk_indexing_translation_is_complete( $post, $code ) ) {
                continue;
            }

            $permalink = get_permalink( $post );
            $path      = wp_parse_url( $permalink, PHP_URL_PATH );
            if ( ! $path ) {
                continue;
            }

            $urls[] = home_url( zk_get_language_path( $path, $code ) );
        }
    }

    return array_values( array_unique( $urls ) );
}

// 4. Hook on Post Save / Delete
add_action('save_post', 'zk_instant_indexing_on_save', 10, 3);
function zk_instant_indexing_on_save($post_id, $post, $update) {
    // Only fire for specific post types
    $allowed_types = ['post', 'page', 'books'];
    if (!in_array($post->post_type, $allowed_types)) return;
    
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    
    if ($post->post_status === 'publish') {
        zk_notify_google_indexing(get_permalink($post_id), 'URL_UPDATED');
    }
}

add_action('trashed_post', 'zk_instant_indexing_on_trash');
function zk_instant_indexing_on_trash($post_id) {
    $post = get_post($post_id);
    $allowed_types = ['post', 'page', 'books'];
    if (!in_array($post->post_type, $allowed_types)) return;
    
    zk_notify_google_indexing(get_permalink($post_id), 'URL_DELETED');
}

// 5. Admin Settings UI
add_action('admin_menu', 'zk_indexing_menu');
function zk_indexing_menu() {
    add_options_page('ZK Instant Indexing', 'Instant Indexing', 'manage_options', 'zk-indexing-api', 'zk_indexing_options_page');
}

function zk_indexing_options_page() {
    if (!current_user_can('manage_options')) return;

    if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        check_admin_referer( 'zk_indexing_settings', 'zk_indexing_nonce' );
    }

    if (isset($_POST['zk_indexing_json_key'])) {
        update_option('zk_indexing_json_key', stripslashes($_POST['zk_indexing_json_key']));
        echo '<div class="notice notice-success is-dismissible"><p>JSON Key Saved!</p></div>';
    }
    
    if (isset($_POST['zk_manual_url']) || isset($_POST['zk_submit_completed_translations'])) {
        $type = sanitize_text_field($_POST['zk_manual_type']);
        $urls_raw = isset( $_POST['zk_manual_url'] ) ? stripslashes( $_POST['zk_manual_url'] ) : '';
        $urls = array_filter(array_map('trim', explode("\n", $urls_raw)));
        $completed_count = 0;
        if ( ! empty( $_POST['zk_submit_completed_translations'] ) ) {
            $completed_urls  = zk_indexing_completed_translation_urls();
            $completed_count = count( $completed_urls );
            $urls            = array_merge( $urls, $completed_urls );
        }
        $urls = array_values( array_unique( $urls ) );
        $success_count = 0;
        $errors = [];

        if ( empty( $urls ) ) {
            $errors[] = 'Add at least one URL or select Submit all completed translations.';
        }
        
        foreach ($urls as $u) {
            $u = esc_url_raw($u);
            if (empty($u)) continue;
            
            $resp = zk_notify_google_indexing($u, $type);
            if (is_wp_error($resp)) {
                 $errors[] = $u . ': ' . esc_html($resp->get_error_message());
            } else {
                 $code = wp_remote_retrieve_response_code($resp);
                 if ($code == 200) {
                     $success_count++;
                 } else {
                     $body = json_decode(wp_remote_retrieve_body($resp), true);
                     $msg = isset($body['error']['message']) ? $body['error']['message'] : 'API Error ' . $code;
                     $errors[] = $u . ': ' . esc_html($msg);
                 }
            }
        }
        
        if ($success_count > 0) {
            $completed_note = $completed_count ? ' This included ' . $completed_count . ' completed translations.' : '';
            echo '<div class="notice notice-success is-dismissible"><p>Successfully submitted ' . $success_count . ' URLs to Google.' . esc_html( $completed_note ) . '</p></div>';
        }
        if (!empty($errors)) {
            echo '<div class="notice notice-error is-dismissible"><p>Errors:<br>' . implode('<br>', $errors) . '</p></div>';
        }
    }
    
    $key_configured = (bool) get_option( 'zk_indexing_json_key', '' );
    ?>
    <div class="wrap">
        <h1>ZK Instant Indexing Setup (Google Indexing API)</h1>
        
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-top:20px;">
            <?php wp_nonce_field( 'zk_indexing_settings', 'zk_indexing_nonce' ); ?>
            <h2>1. Service Account JSON Key</h2>
            <p>Paste the contents of your Google Cloud Service Account JSON file below.</p>
            <textarea name="zk_indexing_json_key" rows="12" style="width:100%; max-width:800px; font-family:monospace;" placeholder="<?php echo esc_attr( $key_configured ? 'A service account key is already saved. Paste new JSON only to replace it.' : 'Paste the service account JSON here.' ); ?>"></textarea>
            <?php if ( $key_configured ) : ?>
                <p class="description"><strong>Service account key configured.</strong> Its private contents are hidden for security.</p>
            <?php endif; ?>
            <p><input type="submit" class="button button-primary" value="Save JSON Key"></p>
        </form>

        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-top:20px;">
            <?php wp_nonce_field( 'zk_indexing_settings', 'zk_indexing_nonce' ); ?>
            <h2>2. Manual URL Submission (Bulk)</h2>
            <p>Paste one or more URLs below (one per line) to submit to Google. Max 100 per batch recommended.</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <textarea name="zk_manual_url" rows="5" style="width:100%; max-width:800px;" placeholder="https://zurabkostava.com/nocturne-1&#10;https://zurabkostava.com/nocturne-2"></textarea>
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="zk_submit_completed_translations" value="1">
                    Submit all completed translations
                </label>
                <p class="description" style="margin:0;max-width:800px;">Adds every published translation marked Complete in Language Center for all enabled languages. A successful submission requests a crawl; it does not guarantee indexing.</p>
                <div style="display:flex; gap:10px; align-items:center;">
                    <select name="zk_manual_type">
                        <option value="URL_UPDATED">Publish / Update</option>
                        <option value="URL_DELETED">Remove / Delete</option>
                    </select>
                    <input type="submit" class="button button-primary" value="Send to Google">
                </div>
            </div>
        </form>
    </div>
    <?php
}

// 6. Post Row Actions
add_filter('post_row_actions', 'zk_add_instant_index_action', 10, 2);
add_filter('page_row_actions', 'zk_add_instant_index_action', 10, 2);

function zk_add_instant_index_action($actions, $post) {
    if (!current_user_can('edit_post', $post->ID)) return $actions;
    
    // Only show on published posts
    if ($post->post_status !== 'publish') return $actions;

    $url = wp_nonce_url(admin_url('admin.php?action=zk_instant_index_post&post_id=' . $post->ID), 'zk_instant_index_' . $post->ID);
    
    $actions['instant_index'] = '<a href="' . esc_url($url) . '" style="color:#2271b1;" title="Send to Google Indexing API">Instant Index</a>';
    return $actions;
}

add_action('admin_action_zk_instant_index_post', 'zk_handle_instant_index_action');
function zk_handle_instant_index_action() {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_die('Permission denied.');
    }
    
    check_admin_referer('zk_instant_index_' . $post_id);
    
    $resp = zk_notify_google_indexing(get_permalink($post_id), 'URL_UPDATED');
    
    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url('edit.php');
    
    if (is_wp_error($resp)) {
        $redirect_url = add_query_arg('zk_index_msg', 'error', $redirect_url);
        $redirect_url = add_query_arg('zk_index_err', urlencode($resp->get_error_message()), $redirect_url);
    } else {
        $code = wp_remote_retrieve_response_code($resp);
        if ($code == 200) {
            $redirect_url = add_query_arg('zk_index_msg', 'success', $redirect_url);
        } else {
            $redirect_url = add_query_arg('zk_index_msg', 'error', $redirect_url);
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $err_msg = isset($body['error']['message']) ? $body['error']['message'] : 'API Error ' . $code;
            $redirect_url = add_query_arg('zk_index_err', urlencode($err_msg), $redirect_url);
        }
    }
    
    wp_redirect($redirect_url);
    exit;
}

add_action('admin_notices', 'zk_instant_index_admin_notice');
function zk_instant_index_admin_notice() {
    if (isset($_GET['zk_index_msg'])) {
        $msg = $_GET['zk_index_msg'];
        if ($msg === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Instant Index:</strong> URL successfully submitted to Google API.</p></div>';
        } elseif ($msg === 'error') {
            $err = isset($_GET['zk_index_err']) ? urldecode($_GET['zk_index_err']) : 'Unknown error';
            echo '<div class="notice notice-error is-dismissible"><p><strong>Instant Index Error:</strong> ' . esc_html($err) . '</p></div>';
        }
    }
}
