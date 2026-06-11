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

// 1. Basic Stats
$total_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$unique_visitors = $wpdb->get_var("SELECT COUNT(DISTINCT ip_hash) FROM $table_name");

// 2. Today's Stats
$today = current_time('Y-m-d');
$today_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(visit_time) = '$today'");
$today_uniques = $wpdb->get_var("SELECT COUNT(DISTINCT ip_hash) FROM $table_name WHERE DATE(visit_time) = '$today'");

// 3. Top 10 Pages (All Time)
$top_pages = $wpdb->get_results("
    SELECT url, COUNT(*) as views 
    FROM $table_name 
    GROUP BY url 
    ORDER BY views DESC 
    LIMIT 10
");

// 3.1 Top Countries
$top_countries = $wpdb->get_results("
    SELECT country, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques 
    FROM $table_name 
    WHERE country != '' AND country IS NOT NULL
    GROUP BY country 
    ORDER BY uniques DESC 
    LIMIT 10
");

// 3.2 Top Cities
$top_cities = $wpdb->get_results("
    SELECT city, country, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques 
    FROM $table_name 
    WHERE city != '' AND city IS NOT NULL
    GROUP BY city, country 
    ORDER BY uniques DESC 
    LIMIT 10
");

// 4. Last 7 Days Activity
$seven_days_ago = date('Y-m-d', strtotime('-6 days', current_time('timestamp')));
$daily_stats = $wpdb->get_results("
    SELECT DATE(visit_time) as visit_date, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques 
    FROM $table_name 
    WHERE DATE(visit_time) >= '$seven_days_ago' 
    GROUP BY DATE(visit_time) 
    ORDER BY DATE(visit_time) ASC
");

// Prepare data for the chart
$dates = [];
$views_data = [];
$uniques_data = [];

// Fill in all 7 days even if no data
for ($i = 6; $i >= 0; $i--) {
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
    if (!$found) {
        $views_data[] = 0;
        $uniques_data[] = 0;
    }
}

get_header();
?>

<div class="page__content" id="view" data-route="/analytics">
    <div class="zk-analytics-container">
        <header class="zk-analytics-header">
            <h1 class="zk-title">Analytics Studio</h1>
            <p class="zk-subtitle">Real-time minimalist visitor tracking.</p>
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
                <span class="zk-stat-label">Today's Uniques</span>
                <span class="zk-stat-value"><?php echo number_format($today_uniques ?: 0); ?></span>
            </div>
        </div>

        <!-- CHARTS & TABLES -->
        <div class="zk-analytics-main">
            <!-- Left: 7 Days Chart -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">Last 7 Days Activity</h3>
                <div class="zk-chart-wrapper">
                    <canvas id="zkActivityChart"></canvas>
                </div>
            </div>

            <!-- Right: Top Pages -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">Top 10 Pages</h3>
                <div class="zk-table-wrapper">
                    <table class="zk-table">
                        <thead>
                            <tr>
                                <th>Route URL</th>
                                <th style="text-align: right;">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_pages): ?>
                                <?php foreach ($top_pages as $page): ?>
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

        <!-- GEO DATA (COUNTRIES & CITIES) -->
        <div class="zk-analytics-main" style="margin-top: 24px; grid-template-columns: 1fr 1fr;">
            <!-- Left: Top Countries -->
            <div class="zk-analytics-panel">
                <h3 class="zk-panel-title">Top Countries</h3>
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
                <h3 class="zk-panel-title">Top Cities</h3>
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
                                            <?php 
                                            if (!empty($c->city)) {
                                                echo esc_html($c->city . ', ' . $c->country);
                                            } else {
                                                echo esc_html($c->country . ' (Unknown City)');
                                            }
                                            ?>
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
    </div>
</div>

<style>
/* ============================================================
   ANALYTICS DASHBOARD CSS
   ============================================================ */
.zk-analytics-container {
    max-width: 1200px;
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
@media (max-width: 900px) {
    .zk-analytics-main { grid-template-columns: 1fr; }
}
.zk-analytics-panel {
    background: rgba(255, 255, 255, 0.015);
    border: 1px solid var(--hairline);
    border-radius: 16px;
    padding: 30px;
}
.zk-panel-title {
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-dim);
    margin-top: 0;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--hairline);
}

/* CHART */
.zk-chart-wrapper {
    position: relative;
    height: 300px;
    width: 100%;
}

/* TABLE */
.zk-table-wrapper {
    overflow-x: auto;
}
.zk-table {
    width: 100%;
    border-collapse: collapse;
}
.zk-table th {
    text-align: left;
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--hairline-strong);
    font-weight: 500;
}
.zk-table td {
    padding: 14px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    font-size: 0.95rem;
    color: var(--text-muted);
    font-family: var(--font-mono, monospace);
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
</script>

<?php get_footer(); ?>
