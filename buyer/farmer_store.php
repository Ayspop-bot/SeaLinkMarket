<?php
/**
 * SeaLink Web Application
 * File: /buyer/farmer_store.php
 * Note: Consolidated to unified /farmer_store.php
 */
require_once __DIR__ . '/../config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . BASE_URL . '/farmer_store.php' . $qs);
exit;
