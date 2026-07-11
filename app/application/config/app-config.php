<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
* --------------------------------------------------------------------------
* Base Site URL
* --------------------------------------------------------------------------
*
* URL to your CodeIgniter root. Typically this will be your base URL,
* WITH a trailing slash:
*
*   http://example.com/
*
* If this is not set then CodeIgniter will try guess the protocol, domain
* and path to your installation. However, you should always configure this
* explicitly and never rely on auto-guessing, especially in production
* environments.
*
*/
// define('APP_BASE_URL', 'https://localhost/medi_travel/app/');
$app_base_url = getenv('APP_BASE_URL');

if (!$app_base_url) {
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    $scheme = $is_https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $path   = rtrim(str_replace('\\', '/', dirname($script)), '/\\');

    $app_base_url = $scheme . '://' . $host . ($path ? $path : '') . '/';
}

define('APP_BASE_URL', rtrim($app_base_url, '/') . '/');



/*
* --------------------------------------------------------------------------
* Encryption Key
* IMPORTANT: Do not change this ever!
* --------------------------------------------------------------------------
*
* If you use the Encryption class, you must set an encryption key.
* See the user guide for more info.
*
* http://codeigniter.com/user_guide/libraries/encryption.html
*
* Auto added on install
*/
define('APP_ENC_KEY', '680eea3ac668baabcc860a3a37045fb9');

/**
 * Database Credentials
 * The hostname of your database server
 */
define('APP_DB_HOSTNAME', 'localhost');

/**
 * The username used to connect to the database
 */
define('APP_DB_USERNAME', 'root');

/**
 * The password used to connect to the database
 */
define('APP_DB_PASSWORD', '');

/**
 * The name of the database you want to connect to
 */
define('APP_DB_NAME', 'medi_travel_solution');




/**
 * @since  2.3.0
 * Database charset
 */
define('APP_DB_CHARSET', 'utf8mb4');

/**
 * @since  2.3.0
 * Database collation
 */
define('APP_DB_COLLATION', 'utf8mb4_unicode_ci');

/**
 *
 * Session handler driver
 * By default the database driver will be used.
 *
 * For files session use this config:
 * define('SESS_DRIVER', 'files');
 * define('SESS_SAVE_PATH', NULL);
 * In case you are having problem with the SESS_SAVE_PATH consult with your hosting provider to set "session.save_path" value to php.ini
 *
 */
define('SESS_DRIVER', 'database');
define('SESS_SAVE_PATH', 'sessions');
define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');

/**
 * Enables CSRF Protection
 */
define('APP_CSRF_PROTECTION', true);


require_once(FCPATH . 'modules/branch/config/my_config.php'); // added by branch