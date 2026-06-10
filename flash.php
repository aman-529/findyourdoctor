<?php
function set_flash($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

function display_flash() {
    $flash = get_flash();
    if (!$flash) return '';
    $type    = htmlspecialchars($flash['type']);
    $message = htmlspecialchars($flash['message']);
    return "<div class='alert alert-{$type}'>{$message}</div>";
}
?>
