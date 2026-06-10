<?php
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_role($required_role) {
    require_login();
    if ($_SESSION['user_role'] !== $required_role) {
        http_response_code(403);
        die('Access Denied.');
    }
}

function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_user_name() {
    return $_SESSION['user_name'] ?? 'Guest';
}

function get_current_user_role() {
    return $_SESSION['user_role'] ?? '';
}

function is_authenticated() {
    return !empty($_SESSION['user_id']);
}
?>
