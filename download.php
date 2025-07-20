<?php
require_once( dirname( __FILE__, 4 ) . '/wp-load.php' );

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$file = basename($_GET['file']);
$year = preg_replace('/[^0-9]/', '', $_GET['year'] ?? date('Y'));
$month = preg_replace('/[^0-9]/', '', $_GET['month'] ?? date('m'));

$filepath = WP_CONTENT_DIR . "/protected-media/$year/$month/$file";

if (file_exists($filepath)) {
    $mime = mime_content_type($filepath);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    echo 'File non trovato.';
}
