<?php
/**
 * Template Name: Analytics Dashboard
 */

// Strict Admin-Only Access
if ( ! current_user_can( 'administrator' ) ) {
    wp_redirect( home_url() );
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'zk_analytics';

// Handle Reset Request
if ( isset($_POST['zk_reset_analytics']) && wp_verify_nonce($_POST['zk_reset_analytics_nonce'], 'zk_reset_action') ) {
    $table_logs = $wpdb->prefix . 'zk_encrolib_logs';
    $wpdb->query("TRUNCATE TABLE $table_name");
    $wpdb->query("TRUNCATE TABLE $table_logs");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'zk_photo_views'");
    wp_cache_flush();
    wp_redirect(home_url('/analytics'));
    exit;
}

// Handle Encrolib Logs Deletion
if ( isset($_POST['zk_delete_encro_logs']) && wp_verify_nonce($_POST['zk_delete_encro_logs_nonce'], 'zk_delete_encro_action') ) {
    if (!empty($_POST['delete_logs']) && is_array($_POST['delete_logs'])) {
        $log_ids = array_map('intval', $_POST['delete_logs']);
        $table_logs = $wpdb->prefix . 'zk_encrolib_logs';
        
        $ids_placeholder = implode(',', array_fill(0, count($log_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare("DELETE FROM $table_logs WHERE id IN ($ids_placeholder)", $log_ids)
        );
    }
    wp_redirect(home_url('/analytics'));
    exit;
}

// Handle Fans Deletion
if ( isset($_POST['zk_delete_fans']) && wp_verify_nonce($_POST['zk_delete_fans_nonce'], 'zk_delete_fans_action') ) {
    if (!empty($_POST['delete_fans']) && is_array($_POST['delete_fans'])) {
        $visitor_ids = array_map('sanitize_text_field', $_POST['delete_fans']);
        $table_name = $wpdb->prefix . 'zk_analytics';
        
        foreach ($visitor_ids as $vid) {
            $wpdb->delete($table_name, array('visitor_id' => $vid));
        }
    }
    wp_redirect(home_url('/analytics'));
    exit;
}

// Handle Fan Renaming
if ( isset($_POST['zk_rename_fan']) && wp_verify_nonce($_POST['zk_rename_fan_nonce'], 'zk_rename_action') ) {
    $visitor_id = sanitize_text_field($_POST['visitor_id']);
    $new_name = sanitize_text_field($_POST['new_name']);
    
    if (!empty($visitor_id)) {
        $custom_names = get_option('zk_custom_fan_names', array());
        if (!is_array($custom_names)) $custom_names = array();
        
        if (trim($new_name) === '') {
            unset($custom_names[$visitor_id]); // Revert to generated name
        } else {
            $custom_names[$visitor_id] = trim($new_name);
        }
        update_option('zk_custom_fan_names', $custom_names);
    }
    wp_redirect(home_url('/analytics'));
    exit;
}

// Name Generator for Visitors
function zk_generate_fan_name($visitor_id) {
    if (empty($visitor_id)) return "Unknown Visitor";
    
    $custom_names = get_option('zk_custom_fan_names', array());
    if (isset($custom_names[$visitor_id])) {
        return $custom_names[$visitor_id];
    }

    $adjectives = array("Silent", "Neon", "Crimson", "Midnight", "Lunar", "Velvet", "Golden", "Shadow", "Electric", "Crystal", "Ruby", "Azure", "Cosmic", "Phantom", "Silver", "Jade", "Ember", "Quantum", "Vivid", "Echo");
    $nouns = array("Wolf", "Tiger", "Raven", "Dragon", "Phoenix", "Panther", "Falcon", "Serpent", "Fox", "Hawk", "Bear", "Lion", "Eagle", "Leopard", "Owl", "Shark", "Cobra", "Stag", "Lynx", "Viper");
    $hash = crc32($visitor_id);
    $adj_index = abs($hash) % count($adjectives);
    $noun_index = abs((int)($hash / count($adjectives))) % count($nouns);
    return $adjectives[$adj_index] . ' ' . $nouns[$noun_index];
}

// 1. Basic Stats
$total_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$unique_visitors = $wpdb->get_var("SELECT COUNT(DISTINCT visitor_id) FROM $table_name WHERE visitor_id != ''");

// 2. Today's Stats
$today = current_time('Y-m-d');
$today_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(visit_time) = '$today'");
$today_uniques = $wpdb->get_var("SELECT COUNT(DISTINCT visitor_id) FROM $table_name WHERE DATE(visit_time) = '$today' AND visitor_id != ''");

// 2.5 Engagement Stats
$avg_duration_sec = $wpdb->get_var("
    SELECT AVG(session_dur) 
    FROM (
        SELECT SUM(duration) as session_dur 
        FROM $table_name 
        GROUP BY session_id
    ) as sq 
    WHERE session_dur > 0
");
$avg_duration_formatted = $avg_duration_sec ? gmdate((intval($avg_duration_sec) >= 3600 ? "H:i:s" : "i:s"), intval($avg_duration_sec)) : '00:00';

$music_plays = $wpdb->get_var("SELECT SUM(music_played) FROM $table_name");
$music_time_sec = $wpdb->get_var("SELECT SUM(music_duration) FROM $table_name");
$music_time_fmt = $music_time_sec ? (intval($music_time_sec) >= 86400 ? floor(intval($music_time_sec)/86400) . 'd ' . gmdate("H:i", intval($music_time_sec)) : (intval($music_time_sec) >= 3600 ? gmdate("H:i:s", intval($music_time_sec)) : gmdate("i:s", intval($music_time_sec)))) : '00:00';

// 3. Top Pages (All Time)
$top_pages = $wpdb->get_results("
    SELECT url, COUNT(*) as views, AVG(duration) as avg_duration
    FROM $table_name 
    GROUP BY url 
    ORDER BY views DESC 
");

// 3.1 Top Countries
$top_countries = $wpdb->get_results("
    SELECT country, COUNT(*) as views, COUNT(DISTINCT visitor_id) as uniques 
    FROM $table_name 
    WHERE country != '' AND country IS NOT NULL
    GROUP BY country 
    ORDER BY uniques DESC 
");

// 3.2 Top Cities
$top_cities = $wpdb->get_results("
    SELECT city, country, COUNT(*) as views, COUNT(DISTINCT visitor_id) as uniques 
    FROM $table_name 
    WHERE city != '' AND city IS NOT NULL
    GROUP BY city, country 
    ORDER BY uniques DESC 
");

// 3.2.1 Top Referrers
$top_referrers = $wpdb->get_results("
    SELECT IF(referrer = '', 'Direct', referrer) as referrer, COUNT(*) as views 
    FROM $table_name 
    WHERE referrer != 'Internal'
    GROUP BY IF(referrer = '', 'Direct', referrer) 
    ORDER BY views DESC 
");

// 3.3 Top Fans
$top_fans = $wpdb->get_results("
    SELECT t.visitor_id, 
           MAX(t.country) as country, 
           MAX(t.city) as city, 
           MAX(t.user_agent) as user_agent,
           MAX(t.visit_time) as last_visit,
           COUNT(DISTINCT t.session_id) as total_visits, 
           COUNT(*) as page_views,
           SUM(t.duration) as total_duration,
           (SELECT SUM(duration) FROM $table_name a WHERE a.session_id = (SELECT session_id FROM $table_name b WHERE b.visitor_id = t.visitor_id ORDER BY visit_time DESC LIMIT 1)) as last_duration
    FROM $table_name t
    WHERE t.visitor_id != '' 
    GROUP BY t.visitor_id 
    ORDER BY total_visits DESC, page_views DESC 
");

// 3.5 Encrolib Logs
$table_logs = $wpdb->prefix . 'zk_encrolib_logs';
// Use suppress_errors to avoid crashing if table doesn't exist yet
$wpdb->suppress_errors = true;
$encrolib_logs = $wpdb->get_results("
    SELECT l.*, 
           (SELECT user_agent FROM {$wpdb->prefix}zk_analytics a WHERE a.visitor_id = l.visitor_id ORDER BY visit_time DESC LIMIT 1) as user_agent
    FROM $table_logs l
    ORDER BY created_at DESC 
    LIMIT 50
");
$wpdb->suppress_errors = false;

// 3.4 Devices and Browsers
$ua_data = $wpdb->get_results("
    SELECT user_agent, COUNT(DISTINCT session_id) as visits 
    FROM $table_name 
    WHERE user_agent != '' 
    GROUP BY user_agent
");

$browsers = [];
$os_devices = [];

function zk_get_browser_and_os($ua) {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';

    // Extract Screen Data if present
    $screen = '';
    if (preg_match('/\[Screen: (.*?)\]/', $ua, $matches)) {
        $screen = ' (' . $matches[1] . ')';
    }

    // In-App Browser Detection
    if (stripos($ua, 'Instagram') !== false) { $browser = 'Instagram In-App'; }
    elseif (stripos($ua, 'FB_IAB') !== false || stripos($ua, 'FBAN') !== false || stripos($ua, 'FBAV') !== false || stripos($ua, 'FacebookApp') !== false) { $browser = 'Facebook In-App'; }
    elseif (stripos($ua, 'TikTok') !== false) { $browser = 'TikTok In-App'; }
    elseif (stripos($ua, 'LinkedInApp') !== false) { $browser = 'LinkedIn In-App'; }
    elseif (stripos($ua, 'Twitter') !== false) { $browser = 'Twitter/X In-App'; }
    elseif (stripos($ua, 'Snapchat') !== false) { $browser = 'Snapchat In-App'; }
    elseif (stripos($ua, 'Pinterest') !== false) { $browser = 'Pinterest In-App'; }
    elseif (stripos($ua, 'Telegram') !== false) { $browser = 'Telegram In-App'; }
    elseif (stripos($ua, 'WhatsApp') !== false) { $browser = 'WhatsApp In-App'; }
    elseif (stripos($ua, 'Viber') !== false) { $browser = 'Viber In-App'; }
    elseif (stripos($ua, 'Reddit') !== false) { $browser = 'Reddit In-App'; }
    elseif (stripos($ua, 'Threads') !== false) { $browser = 'Threads In-App'; }
    elseif (stripos($ua, 'Discord') !== false) { $browser = 'Discord In-App'; }
    elseif (stripos($ua, 'GSA/') !== false || stripos($ua, 'GoogleApp') !== false) { $browser = 'Google Search App'; }
    elseif (stripos($ua, 'YaBrowser') !== false || stripos($ua, 'Yowser') !== false) { $browser = 'Yandex Browser'; }
    elseif (stripos($ua, 'SamsungBrowser') !== false) { $browser = 'Samsung Internet'; }
    elseif (stripos($ua, 'MiuiBrowser') !== false) { $browser = 'Miui Browser'; }

    // Regular Browser Detection
    elseif (stripos($ua, 'Edg') !== false) { $browser = 'Microsoft Edge'; }
    elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) { $browser = 'Opera'; }
    elseif (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) { $browser = 'Mozilla Firefox'; }
    elseif (stripos($ua, 'Chrome') !== false || stripos($ua, 'CriOS') !== false) { $browser = 'Google Chrome'; }
    elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) { $browser = 'Apple Safari'; }

    // OS Detection
    if (stripos($ua, 'Windows NT 10.0') !== false || stripos($ua, 'Windows NT 11.0') !== false) { $os = 'Windows 10/11' . $screen; }
    elseif (stripos($ua, 'Windows NT') !== false) { $os = 'Windows (Older)' . $screen; }
    elseif (stripos($ua, 'iPhone') !== false) { $os = 'Apple iPhone' . $screen; }
    elseif (stripos($ua, 'iPad') !== false) { $os = 'Apple iPad' . $screen; }
    elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) { $os = 'Apple Mac' . $screen; }
    elseif (stripos($ua, 'Android') !== false) { $os = 'Android Device' . $screen; }
    elseif (stripos($ua, 'Linux') !== false) { $os = 'Linux' . $screen; }
    elseif (stripos($ua, 'CrOS') !== false) { $os = 'Chrome OS' . $screen; }

    return [$browser, $os];
}

if ($ua_data) {
    foreach ($ua_data as $row) {
        list($b, $o) = zk_get_browser_and_os($row->user_agent);
        if (!isset($browsers[$b])) $browsers[$b] = 0;
        $browsers[$b] += $row->visits;
        
        if (!isset($os_devices[$o])) $os_devices[$o] = 0;
        $os_devices[$o] += $row->visits;
    }
}
arsort($browsers);
arsort($os_devices);

$top_browsers = $browsers;
$top_os = $os_devices;

// 4. Activity Chart
$range_param = isset($_GET['range']) ? $_GET['range'] : '7';
$valid_ranges = ['7' => 'Last 7 Days', '30' => 'Last 30 Days', '365' => 'Last 1 Year'];
if (!array_key_exists($range_param, $valid_ranges)) $range_param = '7';

$dates = [];
$views_data = [];
$uniques_data = [];

if ($range_param === '365') {
    $one_year_ago = date('Y-m-01', strtotime('-11 months', current_time('timestamp')));
    $monthly_stats = $wpdb->get_results("
        SELECT DATE_FORMAT(visit_time, '%Y-%m') as visit_month, COUNT(*) as views, COUNT(DISTINCT session_id) as uniques 
        FROM $table_name 
        WHERE visit_time >= '$one_year_ago' 
        GROUP BY DATE_FORMAT(visit_time, '%Y-%m') 
        ORDER BY visit_month ASC
    ");
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months", current_time('timestamp')));
        $dates[] = date('M Y', strtotime($m . '-01'));
        $found = false;
        if ($monthly_stats) {
            foreach ($monthly_stats as $stat) {
                if ($stat->visit_month === $m) {
                    $views_data[] = $stat->views;
                    $uniques_data[] = $stat->uniques;
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) { $views_data[] = 0; $uniques_data[] = 0; }
    }
} else {
    $days_ago = intval($range_param) - 1;
    $start_date = date('Y-m-d', strtotime("-$days_ago days", current_time('timestamp')));
    $daily_stats = $wpdb->get_results("
        SELECT DATE(visit_time) as visit_date, COUNT(*) as views, COUNT(DISTINCT session_id) as uniques 
        FROM $table_name 
        WHERE DATE(visit_time) >= '$start_date' 
        GROUP BY DATE(visit_time) 
        ORDER BY DATE(visit_time) ASC
    ");
    for ($i = $days_ago; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days", current_time('timestamp')));
        $dates[] = date('M j', strtotime($d));
        $found = false;
        if ($daily_stats) {
            foreach ($daily_stats as $stat) {
                if ($stat->visit_date === $d) {
                    $views_data[] = $stat->views;
                    $uniques_data[] = $stat->uniques;
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) { $views_data[] = 0; $uniques_data[] = 0; }
}
}

add_action('wp_head', function() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">' . "\n";
}, 1);

get_header();
?>

<div class="page__content" id="view" data-route="/analytics">
    <div class="zk-analytics-container">
        <header class="zk-analytics-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 class="zk-title">Analytics Studio</h1>
                <p class="zk-subtitle" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    Real-time minimalist visitor tracking.
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text); background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1);">
                        <input type="checkbox" id="zkIgnoreTrackingToggle" style="accent-color: #ff453a;">
                        Ignore my visits on this device
                    </label>
                </p>
            </div>
            <form method="post" onsubmit="return confirm('Are you sure you want to permanently delete all analytics data? This cannot be undone.');">
                <?php wp_nonce_field('zk_reset_action', 'zk_reset_analytics_nonce'); ?>
                <button type="submit" name="zk_reset_analytics" class="zk-reset-btn">Reset Statistics</button>
            </form>
        </header>

        <!-- OVERVIEW CARDS -->
        <div class="zk-stats-grid">
            <div class="zk-stat-card">
                <span class="zk-stat-label">Total Views</span>
                <span class="zk-stat-value"><?php echo number_format($total_views ?: 0); ?></span>
            </div>
            <div class="zk-stat-card">
                <span class="zk-stat-label">Unique Visitors</span>
                <span class="zk-stat-value"><?php echo number_format($unique_visitors ?: 0); ?></span>
            </div>
            <div class="zk-stat-card highlight">
                <span class="zk-stat-label">Today's Views</span>
                <span class="zk-stat-value"><?php echo number_format($today_views ?: 0); ?></span>
            </div>
            <div class="zk-stat-card highlight">
                <span class="zk-stat-label">Avg Session Duration</span>
                <span class="zk-stat-value" style="color: #ff2a85; font-size: 2rem;"><?php echo esc_html($avg_duration_formatted); ?></span>
            </div>
            <div class="zk-stat-card" style="border-color: rgba(255, 193, 7, 0.3);">
                <span class="zk-stat-label" style="color: #ffc107;">Total Music Plays</span>
                <span class="zk-stat-value" style="color: #ffc107; font-size: 2rem;"><?php echo number_format($music_plays ?: 0); ?></span>
            </div>
            <div class="zk-stat-card" style="border-color: rgba(255, 193, 7, 0.3);">
                <span class="zk-stat-label" style="color: #ffc107;">Total Music Time</span>
                <span class="zk-stat-value" style="color: #ffc107; font-size: 2rem;"><?php echo esc_html($music_time_fmt); ?></span>
            </div>
        </div>

        <!-- CHARTS & TABLES -->
        <div class="zk-analytics-main">
            <!-- Left: Chart -->
            <div class="zk-analytics-panel has-chart">
                <h3 class="zk-panel-title" style="display: flex; justify-content: space-between; align-items: center; border-bottom: none; margin-bottom: 12px; padding-bottom: 0;">
                    <?php echo esc_html(strtoupper($valid_ranges[$range_param] . ' Activity')); ?>
                    <select onchange="window.location.href='?range=' + this.value" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; outline: none; cursor: pointer; font-family: var(--font-mono, monospace); font-size: 0.8rem;">
                        <?php foreach ($valid_ranges as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($range_param, (string)$val); ?> style="background: #121212;"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </h3>
                <div class="zk-chart-wrapper">
                    <canvas id="zkActivityChart"></canvas>
                </div>
            </div>

            <!-- Right: All Pages -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>All Pages</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_pages); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Route URL</th>
                                <th style="text-align: right;">Views</th>
                                <th style="text-align: right;">Avg Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_pages): ?>
                                <?php foreach ($top_pages as $page): 
                                    $p_dur = intval($page->avg_duration);
                                    $p_dur_fmt = $p_dur > 0 ? gmdate(($p_dur >= 3600 ? "H:i:s" : "i:s"), $p_dur) : '-';
                                ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $page_url = $page->url === '/' ? '/ (Home)' : esc_html($page->url);
                                            echo $page_url; 
                                            ?>
                                        </td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($page->views); ?></strong>
                                        </td>
                                        <td style="text-align: right; color: var(--text-dim);">
                                            <?php echo esc_html($p_dur_fmt); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--text-dim);">No data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right 2: Top Referrers -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>All Traffic Sources</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_referrers); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Source / Referrer</th>
                                <th style="text-align: right;">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_referrers): ?>
                                <?php foreach ($top_referrers as $ref): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($ref->referrer); ?>
                                        </td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($ref->views); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim);">No external traffic yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- GEO DATA (COUNTRIES & CITIES) -->
        <div class="zk-analytics-main zk-half" style="margin-top: 24px;">
            <!-- Left: Top Countries -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>Countries</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_countries); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th style="text-align: right;">Unique Visitors</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_countries): ?>
                                <?php foreach ($top_countries as $c): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($c->country); ?>
                                        </td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($c->uniques); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim);">No geo data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Top Cities -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>Cities</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_cities); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>City</th>
                                <th style="text-align: right;">Unique Visitors</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_cities): ?>
                                <?php foreach ($top_cities as $c): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($c->city . ', ' . $c->country); ?>
                                        </td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($c->uniques); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim);">No geo data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TOP FANS -->
        <div class="zk-analytics-main" style="margin-top: 24px; grid-template-columns: 1fr;">
            <div class="zk-analytics-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 class="zk-panel-title" style="color: #00bcd4; margin-bottom: 0; padding-bottom: 0; border: none; width: 100%;">
                        <span>Loyal Fans</span>
                        <span class="zk-count-badge" style="border-color: rgba(0,188,212,0.3); color: #00bcd4; background: rgba(0,188,212,0.1);"><?php echo count((array)$top_fans); ?></span>
                    </h3>
                </div>
                <div class="zk-table-wrapper" style="width: 100%; max-height: 700px;">
                    <form method="POST" action="">
                        <?php wp_nonce_field('zk_delete_fans_action', 'zk_delete_fans_nonce'); ?>
                        <div style="margin-bottom: 15px;">
                            <button type="submit" name="zk_delete_fans" class="zk-reset-btn" style="padding: 6px 12px; font-size: 0.8rem; border-color: rgba(0, 188, 212, 0.4); color: #00bcd4;" onclick="return confirm('Are you sure you want to delete selected fans and all their views?');">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                Delete Selected Fans
                            </button>
                        </div>
                        <table class="zk-table zk-table-fans" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">
                                        <input type="checkbox" id="selectAllFans" onclick="document.querySelectorAll('.fan-checkbox').forEach(cb => cb.checked = this.checked);" style="accent-color: #00bcd4;">
                                    </th>
                                    <th style="width: 20%;">Fan Name (Anonymous ID)</th>
                                    <th style="width: 20%;">Location</th>
                                    <th style="width: 15%;">Tech (OS & Browser)</th>
                                    <th style="text-align: right; width: 10%;">Visits</th>
                                    <th style="text-align: right; width: 10%;">Views</th>
                                    <th style="text-align: right; width: 10%;">Total Time</th>
                                    <th style="text-align: right; width: 15%;">Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_fans): 
                                    foreach ($top_fans as $fan): 
                                        list($b, $o) = zk_get_browser_and_os($fan->user_agent);
                                        
                                        // Human-readable time ago
                                        $time_diff = current_time('timestamp') - strtotime($fan->last_visit);
                                        if ($time_diff < 60) $last_seen = 'Just now';
                                        elseif ($time_diff < 3600) $last_seen = floor($time_diff/60) . ' mins ago';
                                        elseif ($time_diff < 86400) $last_seen = floor($time_diff/3600) . ' hrs ago';
                                        else $last_seen = floor($time_diff/86400) . ' days ago';
                                        
                                        $l_dur = isset($fan->last_duration) ? intval($fan->last_duration) : 0;
                                        $l_dur_fmt = '';
                                        if ($l_dur > 0) {
                                            $l_dur_fmt = ($l_dur >= 3600) ? gmdate("H:i:s", $l_dur) : gmdate("i:s", $l_dur);
                                        }
                                        
                                        // Format total time
                                        $f_dur = intval($fan->total_duration);
                                        if ($f_dur > 0) {
                                            if ($f_dur >= 86400) $f_dur_fmt = floor($f_dur/86400) . 'd ' . gmdate("H:i", $f_dur);
                                            elseif ($f_dur >= 3600) $f_dur_fmt = gmdate("H:i:s", $f_dur);
                                            else $f_dur_fmt = gmdate("i:s", $f_dur);
                                        } else {
                                            $f_dur_fmt = '-';
                                        }
                                    ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="delete_fans[]" value="<?php echo esc_attr($fan->visitor_id); ?>" class="fan-checkbox" style="accent-color: #00bcd4;">
                                            </td>
                                            <td>
                                                <strong style="color: #00bcd4; display: flex; align-items: center; gap: 8px;">
                                                    <?php 
                                                        $current_name = zk_generate_fan_name($fan->visitor_id);
                                                        echo esc_html($current_name); 
                                                    ?>
                                                    <button type="button" onclick="renameFan('<?php echo esc_attr($fan->visitor_id); ?>', '<?php echo esc_attr($current_name); ?>')" style="background: none; border: none; padding: 0; cursor: pointer; color: var(--text-dim); transition: color 0.2s;" title="Rename Fan">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                                    </button>
                                                </strong>
                                                <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;"><?php echo esc_html(substr($fan->visitor_id, 0, 8) . '...'); ?></div>
                                            </td>
                                            <td>
                                                <?php echo esc_html($fan->city ? $fan->city . ', ' . $fan->country : ($fan->country ?: 'Unknown')); ?>
                                            </td>
                                            <td>
                                                <div style="color: var(--text); font-size: 0.85rem;"><?php echo esc_html($o); ?></div>
                                                <div style="color: var(--text-dim); font-size: 0.75rem; margin-top: 2px;"><?php echo esc_html($b); ?></div>
                                            </td>
                                            <td style="text-align: right; color: var(--text);">
                                                <strong><?php echo number_format($fan->total_visits); ?></strong>
                                            </td>
                                            <td style="text-align: right; color: var(--text-dim);">
                                                <?php echo number_format($fan->page_views); ?>
                                            </td>
                                            <td style="text-align: right; color: #ff2a85; font-family: var(--font-mono, monospace);">
                                                <?php echo esc_html($f_dur_fmt); ?>
                                            </td>
                                            <td style="text-align: right; color: var(--text-dim); font-size: 0.85rem;">
                                                <?php echo esc_html($last_seen); ?>
                                                <?php if ($l_dur_fmt) echo '<span style="color: rgba(255,255,255,0.3); font-size: 0.75rem; margin-left: 4px;">(' . esc_html($l_dur_fmt) . ')</span>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--text-dim);">No loyal fans found yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>

        <!-- TECH DATA (BROWSERS & OS) -->
        <div class="zk-analytics-main zk-half" style="margin-top: 24px;">
            <!-- Left: Top Browsers -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>Browsers</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_browsers); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Browser</th>
                                <th style="text-align: right;">Unique Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_browsers): ?>
                                <?php foreach ($top_browsers as $name => $count): ?>
                                    <tr>
                                        <td><?php echo esc_html($name); ?></td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($count); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim);">No data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Top OS & Devices -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">
                    <span>Devices & OS</span>
                    <span class="zk-count-badge"><?php echo count((array)$top_os); ?></span>
                </h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Device / OS</th>
                                <th style="text-align: right;">Unique Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_os): ?>
                                <?php foreach ($top_os as $name => $count): ?>
                                    <tr>
                                        <td><?php echo esc_html($name); ?></td>
                                        <td style="text-align: right; color: var(--text);">
                                            <strong><?php echo number_format($count); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim);">No data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECRET ENCROLIB LOGS -->
        <div class="zk-analytics-main" style="margin-top: 24px; grid-template-columns: 1fr;">
            <div class="zk-analytics-panel" style="border-color: rgba(255, 42, 133, 0.4);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="zk-panel-title" style="color: #ff2a85; margin-bottom: 0; width: 100%;">
                        <span>Encrolib Stealth Logs</span>
                        <span class="zk-count-badge" style="border-color: rgba(255,42,133,0.3); color: #ff2a85; background: rgba(255,42,133,0.1);"><?php echo count((array)$encrolib_logs); ?></span>
                    </h3>
                </div>
                <div class="zk-table-wrapper" style="width: 100%;">
                    <form method="POST" action="">
                        <?php wp_nonce_field('zk_delete_encro_action', 'zk_delete_encro_logs_nonce'); ?>
                        <div style="margin-bottom: 15px;">
                            <button type="submit" name="zk_delete_encro_logs" class="zk-reset-btn" style="padding: 6px 12px; font-size: 0.8rem; border-color: rgba(255, 42, 133, 0.4); color: #ff2a85;" onclick="return confirm('Are you sure you want to delete selected logs?');">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                Delete Selected
                            </button>
                        </div>
                        <table class="zk-table zk-table-fans" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">
                                        <input type="checkbox" id="selectAllLogs" onclick="document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = this.checked);" style="accent-color: #ff2a85;">
                                    </th>
                                    <th style="width: 25%;">Author (Anonymous ID)</th>
                                    <th style="width: 50%;">Generated Text</th>
                                    <th style="text-align: right; width: 20%;">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($encrolib_logs)): ?>
                                    <?php foreach ($encrolib_logs as $log): 
                                        $time_diff = current_time('timestamp') - strtotime($log->created_at);
                                        if ($time_diff < 60) $last_seen = 'Just now';
                                        elseif ($time_diff < 3600) $last_seen = floor($time_diff/60) . ' mins ago';
                                        elseif ($time_diff < 86400) $last_seen = floor($time_diff/3600) . ' hrs ago';
                                        else $last_seen = floor($time_diff/86400) . ' days ago';
                                    ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="delete_logs[]" value="<?php echo esc_attr($log->id); ?>" class="log-checkbox" style="accent-color: #ff2a85;">
                                            </td>
                                            <td>
                                                <?php 
                                                    $os_name = 'Unknown Device';
                                                    if (!empty($log->user_agent)) {
                                                        list($browser, $os_name) = zk_get_browser_and_os($log->user_agent);
                                                    }
                                                ?>
                                                <strong style="color: #00bcd4;"><?php echo esc_html(zk_generate_fan_name($log->visitor_id)); ?></strong>
                                                <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                                                    <?php echo esc_html($os_name); ?>
                                                </div>
                                                <div style="font-size: 0.65rem; color: rgba(255,255,255,0.3); margin-top: 2px;"><?php echo esc_html(substr($log->visitor_id, 0, 8) . '...'); ?></div>
                                            </td>
                                            <td style="color: var(--text); white-space: pre-wrap; font-family: var(--font-mono, monospace); font-size: 0.85rem;"><?php echo esc_html($log->text_content); ?></td>
                                            <td style="text-align: right; color: var(--text-dim); font-size: 0.85rem;">
                                                <?php echo esc_html($last_seen); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-dim);">No texts intercepted yet...</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Hidden Form for Renaming -->
<form id="renameFanForm" method="POST" action="" style="display: none;">
    <?php wp_nonce_field('zk_rename_action', 'zk_rename_fan_nonce'); ?>
    <input type="hidden" name="zk_rename_fan" value="1">
    <input type="hidden" name="visitor_id" id="rename_visitor_id" value="">
    <input type="hidden" name="new_name" id="rename_new_name" value="">
</form>

<style>
/* ============================================================
   ANALYTICS DASHBOARD CSS
   ============================================================ */
.zk-analytics-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 120px 20px 80px 20px;
    animation: fadeIn 0.4s ease forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.zk-analytics-header {
    margin-bottom: 40px;
}
.zk-title {
    font-size: 2.5rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 8px;
}
.zk-subtitle {
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* RESET BUTTON */
.zk-reset-btn {
    background: transparent;
    border: 1px solid rgba(255, 69, 58, 0.3);
    color: #ff453a;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: var(--font-mono, monospace);
    font-size: 0.85rem;
    transition: all 0.3s ease;
}
.zk-reset-btn:hover {
    background: rgba(255, 69, 58, 0.1);
    border-color: rgba(255, 69, 58, 0.6);
}

/* STAT CARDS */
.zk-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.zk-stat-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--hairline);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    transition: all 0.3s ease;
}
.zk-stat-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}
.zk-stat-card.highlight {
    background: rgba(98, 0, 238, 0.05);
    border-color: rgba(98, 0, 238, 0.2);
}
.zk-stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 12px;
}
.zk-stat-value {
    font-size: 2.5rem;
    font-family: var(--font-mono, monospace);
    font-weight: 400;
    color: var(--text);
    line-height: 1;
}

/* PANELS */
.zk-analytics-main {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 24px;
}
.zk-analytics-main.zk-half {
    grid-template-columns: 1fr 1fr;
}
@media (max-width: 900px) {
    .zk-analytics-main { display: flex; flex-direction: column; }
    .zk-analytics-main.zk-half { display: flex; flex-direction: column; }
}
@media (max-width: 600px) {
    .zk-analytics-container { padding: 80px 16px 40px 16px; }
    .zk-title { font-size: 2rem; }
    .zk-stat-value { font-size: 2rem; }
    .zk-analytics-panel { padding: 20px; }
}
.zk-analytics-panel {
    background: rgba(255, 255, 255, 0.015);
    border: 1px solid var(--hairline);
    border-radius: 16px;
    padding: 24px;
    min-width: 0;
}
.zk-panel-title {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    align-items: center;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-dim);
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--hairline);
}
.zk-count-badge {
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-muted);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-family: var(--font-mono, monospace);
    border: 1px solid rgba(255, 255, 255, 0.08);
    letter-spacing: normal;
    text-transform: none;
}

/* CHART */
.zk-analytics-panel.has-chart {
    display: flex;
    flex-direction: column;
}
.zk-chart-wrapper {
    position: relative;
    flex-grow: 1;
    min-height: 300px;
    width: 100%;
}

/* TABLE */
.zk-table-wrapper {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 450px;
    border-bottom: 1px solid var(--hairline);
}
.zk-table-wrapper::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.zk-table-wrapper::-webkit-scrollbar-track {
    background: transparent;
}
.zk-table-wrapper::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}
.zk-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
.zk-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.zk-table th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #121212;
    text-align: left;
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 8px 12px;
    border-bottom: 1px solid var(--hairline-strong);
    white-space: nowrap;
    font-weight: 500;
}
.zk-table td {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    font-size: 0.95rem;
    color: var(--text-muted);
    font-family: var(--font-mono, monospace);
    word-break: break-all;
}
.zk-table-fans td, .zk-table-fans th {
    font-family: var(--font-sans, sans-serif);
    word-break: normal;
}
.zk-table tr:last-child td { border-bottom: none; }
</style>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// We use a small interval to ensure canvas exists if loaded via SPA
let zkChartInterval = setInterval(() => {
    const ctx = document.getElementById('zkActivityChart');
    if (!ctx) return;
    clearInterval(zkChartInterval);

    // Destroy existing chart if initialized (for SPA reload)
    if (window.zkChartInstance) {
        window.zkChartInstance.destroy();
    }

    const labels = <?php echo json_encode($dates); ?>;
    const viewsData = <?php echo json_encode($views_data); ?>;
    const uniquesData = <?php echo json_encode($uniques_data); ?>;

    window.zkChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Page Views',
                    data: viewsData,
                    borderColor: 'rgba(98, 0, 238, 0.8)',
                    backgroundColor: 'rgba(98, 0, 238, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#121212',
                    pointBorderColor: 'rgba(98, 0, 238, 0.8)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Unique Visitors',
                    data: uniquesData,
                    borderColor: 'rgba(0, 188, 212, 0.8)',
                    backgroundColor: 'rgba(0, 188, 212, 0.0)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#121212',
                    pointBorderColor: 'rgba(0, 188, 212, 0.8)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { color: 'rgba(255, 255, 255, 0.5)', usePointStyle: true, boxWidth: 8 }
                },
                tooltip: {
                    backgroundColor: 'rgba(18, 18, 18, 0.9)',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255, 255, 255, 0.8)',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    titleFont: { family: 'var(--font-mono, monospace)' },
                    bodyFont: { family: 'var(--font-mono, monospace)' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.03)', drawBorder: false },
                    ticks: { color: 'rgba(255, 255, 255, 0.4)', precision: 0 }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: 'rgba(255, 255, 255, 0.4)' }
                }
            }
        }
    });
}, 100);

document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('zkIgnoreTrackingToggle');
    if (toggle) {
        try {
            toggle.checked = localStorage.getItem('zk_ignore_tracking') === 'true';
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    localStorage.setItem('zk_ignore_tracking', 'true');
                } else {
                    localStorage.removeItem('zk_ignore_tracking');
                }
            });
        } catch(e) {}
    }
});

function renameFan(visitorId, currentName) {
    let newName = prompt(`Enter a new name for ${currentName}:\n(Leave empty to reset to auto-generated name)`, currentName);
    if (newName !== null) {
        document.getElementById('rename_visitor_id').value = visitorId;
        document.getElementById('rename_new_name').value = newName;
        document.getElementById('renameFanForm').submit();
    }
}

// Table Sorting
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.zk-table th').forEach(function(th) {
        if (th.querySelector('input')) return; // Ignore checkbox columns
        
        th.style.cursor = 'pointer';
        th.title = 'Click to sort';
        
        th.addEventListener('click', function() {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length === 0 || rows[0].querySelector('td[colspan]')) return; // Empty table
            
            const index = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = th.dataset.sort === 'asc';
            
            // Clear sort state for all th
            table.querySelectorAll('th').forEach(t => t.dataset.sort = '');
            th.dataset.sort = isAsc ? 'desc' : 'asc';
            
            // Update visual indicator
            table.querySelectorAll('th span.sort-icon').forEach(el => el.remove());
            th.insertAdjacentHTML('beforeend', `<span class="sort-icon" style="margin-left:5px; font-size:0.8em; opacity:0.7;">${isAsc ? '▼' : '▲'}</span>`);
            
            rows.sort((a, b) => {
                let cellA = a.children[index].innerText.trim();
                let cellB = b.children[index].innerText.trim();
                
                function parseValue(val) {
                    let cleanVal = val.replace(/,/g, '').trim();
                    // try time format 00:00:00 or 00:00
                    if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(cleanVal)) {
                        let parts = cleanVal.split(':');
                        if (parts.length === 2) return parseInt(parts[0])*60 + parseInt(parts[1]);
                        return parseInt(parts[0])*3600 + parseInt(parts[1])*60 + parseInt(parts[2]);
                    }
                    // try relative times
                    if (cleanVal.includes('ago')) {
                        let num = parseFloat(cleanVal);
                        if (cleanVal.includes('sec')) return -num;
                        if (cleanVal.includes('min')) return -num * 60;
                        if (cleanVal.includes('hr') || cleanVal.includes('hour')) return -num * 3600;
                        if (cleanVal.includes('day')) return -num * 86400;
                        if (cleanVal.includes('month')) return -num * 2592000;
                    }
                    if (cleanVal === 'Just now') return 0;
                    if (cleanVal === '-') return -1;
                    
                    let num = parseFloat(cleanVal);
                    if (!isNaN(num) && /^[\d.-]+$/.test(cleanVal)) return num;
                    return cleanVal.toLowerCase();
                }
                
                let valA = parseValue(cellA);
                let valB = parseValue(cellB);
                
                if (valA < valB) return isAsc ? -1 : 1;
                if (valA > valB) return isAsc ? 1 : -1;
                return 0;
            });
            
            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>

<?php get_footer(); ?>
