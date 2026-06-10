<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pricing.php';

if (app_env_bool('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
}

// DB credentials.
defined('DB_HOST') || define('DB_HOST', app_env('DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', app_env_int('DB_PORT', 3306));
defined('DB_USER') || define('DB_USER', app_env('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', app_env('DB_PASS', ''));
defined('DB_NAME') || define('DB_NAME', app_env('DB_NAME', 'tms'));

// Establish database connection.
try {
    $dbh = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        )
    );
} catch (PDOException $e) {
    $message = app_env_bool('APP_DEBUG', false) ? $e->getMessage() : 'Unable to connect to the database.';
    exit('Error: ' . e($message));
}

require_once __DIR__ . '/public-actions.php';
?>
