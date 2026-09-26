<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/infohub_edit.php
 * Purpose: Handles editing existing educational articles in info_hub_tbl.
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
$title      = trim($_POST['title'] ?? '');
$category   = trim($_POST['category'] ?? 'General');
$content    = trim($_POST['content'] ?? '');
$status     = (($_POST['status'] ?? 'Published') === 'Draft') ? 'Draft' : 'Published';

if ($article_id <= 0 || empty($title) || empty($content)) {
    set_message('error', 'Invalid article details provided.');
    redirect_path('/admin/infohub.php');
}

$image_sql = "";
$image_param = null;

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
            $stmt = mysqli_prepare($conn, "
                UPDATE info_hub_tbl
                SET title = ?, category = ?, content = ?, status = ?, image_url = ?, updated_at = NOW()
                WHERE info_hub_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "sssssi", $title, $category, $content, $status, $image_url, $article_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);

            set_message('success', 'Article updated successfully with new cover image.');
            redirect_path('/admin/infohub.php');
        }
    }
}

$stmt = mysqli_prepare($conn, "
    UPDATE info_hub_tbl
    SET title = ?, category = ?, content = ?, status = ?, updated_at = NOW()
    WHERE info_hub_id = ?
");
mysqli_stmt_bind_param($stmt, "ssssi", $title, $category, $content, $status, $article_id);

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Article updated successfully.');
} else {
    set_message('error', 'Failed to update article.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect_path('/admin/infohub.php');
