<?php
/**
 * SeaLink Web Application
 * File: /config/app.php
 * Purpose: Stores global app settings like BASE_URL, app name, and dev error mode.
 * Connected To: Included by most PHP files (directly or via includes/functions.php)
 * Uses: none
 * Notes: Change BASE_URL only if the folder name in htdocs changes.
 */

define('BASE_URL', '/SealinkWeb');   // http://localhost/SealinkWeb/
$base_path = BASE_URL;              // compatibility with existing code

$app_name = 'SeaLink';

date_default_timezone_set('Asia/Manila');

/**
 * Dev mode:
 * - true  = show PHP errors (during coding)
 * - false = hide errors (deployment)
 */
$is_dev = true;

if ($is_dev) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}