<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('doctor');

$user_id = get_current_user_id();
$slot_id = (int)($_GET['slot_id'] ?? 0);

if (!$slot_id) {
    set_flash('Slot ID not provided', 'error');
    header('Location: dashboard.php');
    exit;
}

try {
    $apdo = get_admin_pdo();
    
    // Verify slot belongs to doctor and get booking count
    $vstmt = $apdo->prepare("
        SELECT slot_id, booked_patient_count
        FROM appointment_slots
        WHERE slot_id = ? AND doctor_id = ?
    ");
    $vstmt->execute([$slot_id, $user_id]);
    $slot = $vstmt->fetch();
    
    if (!$slot) {
        set_flash('Slot not found or does not belong to you', 'error');
        header('Location: dashboard.php');
        exit;
    }
    
    if ($slot['booked_patient_count'] > 0) {
        set_flash('Cannot cancel slot with active bookings', 'error');
        header('Location: dashboard.php');
        exit;
    }
    
    // Delete the slot
    $dstmt = $apdo->prepare("DELETE FROM appointment_slots WHERE slot_id = ? AND doctor_id = ?");
    $dstmt->execute([$slot_id, $user_id]);
    
    set_flash('Appointment slot cancelled successfully', 'success');
    header('Location: dashboard.php');
    
} catch (PDOException $e) {
    set_flash('Error: ' . $e->getMessage(), 'error');
    header('Location: dashboard.php');
}
exit;
