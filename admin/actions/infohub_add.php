<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/infohub_add.php
 * Purpose: Handles creating new educational articles in info_hub_tbl.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('Content Admin');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/infohub.php');
}

$title    = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? 'General');
$content  = trim($_POST['content'] ?? '');
$status   = (($_POST['status'] ?? 'Published') === 'Draft') ? 'Draft' : 'Published';

if (empty($title) || empty($content)) {
    set_message('error', 'Please fill in the title and article content.');
    redirect_path('/admin/infohub.php');
}

$image_url = null;
if (!empty($_FILES['image']['tmp_name'])) {
    $upload_dir = __DIR__ . '/../../uploads/infohub/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed)) {
        $filename = 'article_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $dest = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $image_url = 'uploads/infohub/' . $filename;
        }
    }
}

$stmt = mysqli_prepare($conn, "
    INSERT INTO info_hub_tbl (title, category, content, image_url, status, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
mysqli_stmt_bind_param($stmt, "sssss", $title, $category, $content, $image_url, $status);

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Article "' . e($title) . '" has been successfully published.');
} else {
    set_message('error', 'Failed to publish article: ' . mysqli_error($conn));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect_path('/admin/infohub.php');
