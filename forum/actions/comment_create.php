<?php
/**
 * SeaLink Web Application
 * File: /forum/actions/comment_create.php
 * Purpose: Handles comment/reply submissions to a forum post.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['farmer', 'buyer', 'Content Admin', 'User Admin'], true)) {
    redirect_path('/index.php');
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/forum/index.php');
}

$post_id     = (int)($_POST['post_id'] ?? 0);
$user_id     = (int)($_SESSION['user_id'] ?? 0);
$author_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'SeaLink Member');
$content     = trim($_POST['content'] ?? '');
$return      = trim($_POST['return'] ?? '');

$redirect_to = '/forum/post.php?post_id=' . $post_id;
if (!empty($return)) {
    $redirect_to .= '&return=' . urlencode($return);
}

if ($post_id <= 0 || empty($content)) {
    set_message('error', 'Please write a valid reply before submitting.');
    redirect_path($redirect_to);
}

$stmt = mysqli_prepare($conn, "
    INSERT INTO forum_comment_tbl (post_id, user_id, user_role, author_name, content, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
mysqli_stmt_bind_param($stmt, "iisss", $post_id, $user_id, $role, $author_name, $content);

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Your reply has been posted.');
} else {
    set_message('error', 'Failed to post reply.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect_path($redirect_to);
