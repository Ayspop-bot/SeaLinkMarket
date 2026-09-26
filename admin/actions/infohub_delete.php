<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/infohub_delete.php
 * Purpose: Deletes an article from info_hub_tbl.
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
    $stmt = mysqli_prepare($conn, "DELETE FROM info_hub_tbl WHERE info_hub_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $article_id);
    if (mysqli_stmt_execute($stmt)) {
        set_message('success', 'Article deleted successfully.');
    } else {
        set_message('error', 'Failed to delete article.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/admin/infohub.php');
