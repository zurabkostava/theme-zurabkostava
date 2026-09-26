<?php require_once '../../../../wp-load.php'; global $wp_rewrite; print_r(array_slice($wp_rewrite->wp_rewrite_rules(), 0, 5));
