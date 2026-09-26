<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/forum_delete.php
 * Purpose: Content Admin deletes an inappropriate forum post or thread.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/infohub.php?category=Forum');
}

$post_id = (int)($_POST['post_id'] ?? 0);

if ($post_id > 0) {
    // Delete comments first
    $stmt1 = mysqli_prepare($conn, "DELETE FROM forum_comment_tbl WHERE post_id = ?");
    mysqli_stmt_bind_param($stmt1, "i", $post_id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    // Delete post
    $stmt2 = mysqli_prepare($conn, "DELETE FROM forum_post_tbl WHERE post_id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $post_id);
    if (mysqli_stmt_execute($stmt2)) {
        set_message('success', 'Forum post deleted by administrator.');
    } else {
        set_message('error', 'Failed to delete forum post.');
    }
    mysqli_stmt_close($stmt2);
}

mysqli_close($conn);
redirect_path('/admin/infohub.php?category=Forum');
