<?php
/**
 * SeaLink Web Application
 * File: /forum/actions/post_create.php
 * Purpose: Handles creation of new forum discussion posts.
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

$user_id     = (int)($_SESSION['user_id'] ?? 0);
$author_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'SeaLink Member');
$title       = trim($_POST['title'] ?? '');
$category    = trim($_POST['category'] ?? 'General Discussion');
$content     = trim($_POST['content'] ?? '');

$return_url  = trim($_POST['return'] ?? '');

if (empty($title) || empty($content)) {
    set_message('error', 'Please fill in the discussion title and message.');
    redirect_path(!empty($return_url) ? $return_url : '/infohub/index.php?tab=forum');
}

$stmt = mysqli_prepare($conn, "
    INSERT INTO forum_post_tbl (user_id, user_role, author_name, title, category, content, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
mysqli_stmt_bind_param($stmt, "isssss", $user_id, $role, $author_name, $title, $category, $content);

if (mysqli_stmt_execute($stmt)) {
    $new_id = mysqli_insert_id($conn);
    set_message('success', 'Your discussion has been posted to the community forum.');
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    $return_url = trim($_POST['return'] ?? '');
    if (!empty($return_url)) {
        redirect_path($return_url);
    } else {
        redirect_path('/forum/post.php?post_id=' . $new_id);
    }
} else {
    set_message('error', 'Failed to publish discussion post.');
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    redirect_path(!empty($return_url) ? $return_url : '/infohub/index.php?tab=forum');
}
