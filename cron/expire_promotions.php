<?php
/**
 * SeaLink Web Application
 * File: /cron/expire_promotions.php
 * Purpose: Scheduled job / cron command to automatically check and expire promotions.
 * Usage: php c:/xampp/htdocs/SealinkWeb/cron/expire_promotions.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting SeaLink promotion expiration check...\n";

check_and_expire_promotions($conn);

echo "[" . date('Y-m-d H:i:s') . "] Promotion expiration check completed.\n";
mysqli_close($conn);
