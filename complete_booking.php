<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('doctor');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$doctor_id = get_current_user_id();

// Verify booking belongs to doctor and is confirmed - use admin PDO
$apdo = get_admin_pdo();
$stmt = $apdo->prepare("
    SELECT ab.booking_id 
    FROM appointment_bookings ab
    JOIN appointment_slots ast ON ab.slot_id = ast.slot_id
    WHERE ab.booking_id = ? AND ast.doctor_id = ? AND ab.booking_status = 'confirmed'
");
$stmt->execute([$booking_id, $doctor_id]);

if (!$stmt->fetch()) {
    set_flash('error', 'Booking not found or cannot be completed');
    header('Location: dashboard.php');
    exit;
}

// Handle completion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Call stored procedure: sp_mark_booking_completed(booking_id, doctor_id)
        $apdo = get_admin_pdo();
        $stmt = $apdo->prepare("CALL sp_mark_booking_completed(?, ?)");
        $stmt->execute([$booking_id, $doctor_id]);
        $stmt->closeCursor();
        
        set_flash('success', 'Appointment marked as completed. Patient can now submit a review.');
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Failed to complete appointment: ' . htmlspecialchars($e->getMessage()));
    }
}

// Get booking details
$stmt = $apdo->prepare("
    SELECT ab.booking_id, pu.full_name as patient_name,
           ast.appointment_date, ast.start_time, ab.symptom_note
    FROM appointment_bookings ab
    JOIN appointment_slots ast ON ab.slot_id = ast.slot_id
    JOIN users pu ON ab.patient_id = pu.user_id
    WHERE ab.booking_id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complete Appointment</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Mark Appointment as Completed</h2>
        
        <?php echo display_flash(); ?>
        
        <div class="alert alert-info">
            <strong>Appointment Details:</strong>
            <p>
                <strong>Patient:</strong> <?php echo htmlspecialchars($booking['patient_name']); ?><br>
                <strong>Date & Time:</strong> <?php echo date('d-M-Y h:i A', strtotime($booking['appointment_date'] . ' ' . $booking['start_time'])); ?><br>
                <strong>Symptoms:</strong> <?php echo htmlspecialchars($booking['symptom_note']); ?>
            </p>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn" onclick="return confirm('Mark this appointment as completed?')">
                Mark as Completed
            </button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
</div>
</body>
</html>
