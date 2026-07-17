<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
global $wpdb;

$table_fbv = $wpdb->prefix . 'fbv';
$table_fbv_attachment = $wpdb->prefix . 'fbv_attachment_folder';

echo "Table FBV columns:\n";
$cols = $wpdb->get_results("SHOW COLUMNS FROM {$table_fbv}");
print_r($cols);

echo "\nFolders:\n";
$folders = $wpdb->get_results("SELECT * FROM {$table_fbv} LIMIT 10");
print_r($folders);
