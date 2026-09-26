<?php
/**
 * SeaLink Web Application
 * File: /flash_sale.php
 * Forwarder to /flash_sales.php
 */
require_once __DIR__ . '/config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . BASE_URL . '/flash_sales.php' . $qs);
exit;
