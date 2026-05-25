<?php
// router.php - Used ONLY by the PHP built-in web server in Railway to serve static assets properly.
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file(__DIR__ . $path)) {
        // Return false to tell the built-in server to serve the requested static file directly
        return false; 
    }
}
// For all other requests, load the standard Symfony front controller
require_once __DIR__ . '/index.php';