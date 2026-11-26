<?php
/**
 * ONE Office - configuration
 * Edit these values to match your MySQL server (or set them as environment variables).
 */
declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'one_office');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

// Set to true only on your local machine while developing.
define('APP_DEBUG', getenv('APP_DEBUG') === '1');

// Max upload size for images (bytes)
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);
