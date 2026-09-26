<?php
/**
 * SeaLink Web Application
 * File: /includes/auth_check.php
 * Purpose: Protects pages by requiring login and allowed role(s).
 * Connected To: farmer/* buyer/* admin/* profile/* support/*
 * Uses: Session (user_id, user_role, verification_status for farmer)
 * Notes: Handles 'admin' alias for both 'Content Admin' and 'User Admin'.
 */

require_once __DIR__ . '/functions.php';

function check_access($allowed_roles)
{
    /* ========== MUST BE LOGGED IN ========== */
    if (!is_logged_in()) {
        set_message('error', 'Please log in first.');
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        $redirect_arg = (!empty($current_uri) && str_starts_with($current_uri, BASE_URL)) 
            ? ('?redirect=' . urlencode(substr($current_uri, strlen(BASE_URL)))) 
            : '';
        redirect_path('/login.php' . $redirect_arg);
    }

    /* ========== ROLE CHECK ========== */
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    // Expand 'admin' shortcut into both Admin roles
    if (in_array('admin', $allowed_roles, true)) {
        $allowed_roles[] = 'Content Admin';
        $allowed_roles[] = 'User Admin';
    }

    $role = $_SESSION['user_role'] ?? '';

    if (!in_array($role, $allowed_roles, true)) {
        set_message('error', 'Access denied.');
        redirect_path('/index.php');
    }

    /* ========== FARMER REJECT RULE ========== */
    if ($role === 'farmer' && (($_SESSION['verification_status'] ?? '') === 'Rejected')) {
        set_message('error', 'Your farmer account was rejected. Contact User Admin.');

        // Log out but keep session alive for flash message
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['verification_status']);
        session_regenerate_id(true);

        redirect_path('/index.php');
    }
}