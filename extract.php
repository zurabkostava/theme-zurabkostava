<?php
$lines = file('app.js');
$analytics_code = "(function() {\nwindow.zkTrackView = " . implode('', array_slice($lines, 86, 194)) . "\n// Run initially\nwindow.zkTrackView(window.location.pathname);\n})();\n";
file_put_contents('analytics.js', $analytics_code);

// Remove those lines from app.js
array_splice($lines, 86, 194, "");
file_put_contents('app.js', implode('', $lines));
echo "Done.";
