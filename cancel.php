<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('patient');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$patient_id = get_current_user_id();

// Verify booking belongs to patient - use admin PDO for complex queries
$apdo = get_admin_pdo();
$stmt = $apdo->prepare("SELECT booking_id FROM appointment_bookings WHERE booking_id = ? AND patient_id = ? AND booking_status IN ('pending_payment', 'confirmed')");
$stmt->execute([$booking_id, $patient_id]);

if (!$stmt->fetch()) {
    set_flash('error', 'Booking not found or cannot be cancelled');
    header('Location: dashboard.php');
    exit;
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Call stored procedure: sp_cancel_booking(booking_id, patient_id)
        $apdo = get_admin_pdo();
        $stmt = $apdo->prepare("CALL sp_cancel_booking(?, ?)");
        $stmt->execute([$booking_id, $patient_id]);
        $stmt->closeCursor();
        
        set_flash('success', 'Booking cancelled successfully');
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Failed to cancel booking: ' . htmlspecialchars($e->getMessage()));
    }
}

// Get booking details for confirmation
$stmt = $apdo->prepare("
    SELECT ab.booking_id, u.full_name, s.specialization_name, 
           ast.appointment_date, ast.start_time
    FROM appointment_bookings ab
    JOIN appointment_slots ast ON ab.slot_id = ast.slot_id
    JOIN doctor_profiles dp ON ast.doctor_id = dp.doctor_id
    JOIN users u ON dp.doctor_id = u.user_id
    JOIN specializations s ON dp.specialization_id = s.specialization_id
    WHERE ab.booking_id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cancel Appointment</title>
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
        <h2>Cancel Appointment</h2>
        
        <?php echo display_flash(); ?>
        
        <div class="alert alert-warning">
            <strong>Are you sure you want to cancel this appointment?</strong>
            <p>
                <strong>Doctor:</strong> <?php echo htmlspecialchars($booking['full_name']); ?> 
                (<?php echo htmlspecialchars($booking['specialization_name']); ?>)<br>
                <strong>Date & Time:</strong> <?php echo date('d-M-Y h:i A', strtotime($booking['appointment_date'] . ' ' . $booking['start_time'])); ?>
            </p>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Confirm cancellation?')">
                Yes, Cancel Appointment
            </button>
            <a href="dashboard.php" class="btn btn-secondary">No, Keep Appointment</a>
        </form>
    </div>
</div>
</body>
</html>
