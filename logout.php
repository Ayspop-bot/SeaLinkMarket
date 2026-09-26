<?php
/**
 * SeaLink Web Application
 * File: /logout.php
 * Purpose: Clears the session and returns to login.
 * Connected To: /profile/index.php logout button
 */

require_once __DIR__ . '/config/app.php';

session_start();

$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;