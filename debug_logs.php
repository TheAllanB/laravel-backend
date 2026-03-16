<?php
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    $tail = substr($content, -2000);
    echo $tail;
} else {
    echo "No log found.";
}
