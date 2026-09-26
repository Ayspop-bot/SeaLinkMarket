<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/infohub_toggle.php
 * Purpose: Toggles article status between Published and Draft.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('Content Admin');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/infohub.php');
}

$article_id = (int)($_POST['article_id'] ?? 0);

if ($article_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT status FROM info_hub_tbl WHERE info_hub_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $curr = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($curr) {
        $new_status = ($curr['status'] === 'Published') ? 'Draft' : 'Published';
        $up = mysqli_prepare($conn, "UPDATE info_hub_tbl SET status = ?, updated_at = NOW() WHERE info_hub_id = ?");
        mysqli_stmt_bind_param($up, "si", $new_status, $article_id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);

        set_message('success', 'Article status changed to ' . $new_status . '.');
    }
}

mysqli_close($conn);
redirect_path('/admin/infohub.php');
