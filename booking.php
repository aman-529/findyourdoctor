<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('patient');

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$errors = [];

// Get doctor details with JOINs - use admin PDO for complex queries
$apdo = get_admin_pdo();
$stmt = $apdo->prepare("
    SELECT dp.doctor_id, u.full_name, s.specialization_name, a.area_name, dp.consultation_fee as visit_fee
    FROM doctor_profiles dp
    JOIN users u ON dp.doctor_id = u.user_id
    JOIN specializations s ON dp.specialization_id = s.specialization_id
    JOIN areas a ON dp.area_id = a.area_id
    WHERE dp.doctor_id = ? AND dp.verification_status = 'approved'
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    set_flash('error', 'Doctor not found or not approved');
    header('Location: search.php');
    exit;
}

// Get available slots for this doctor
$stmt = $apdo->prepare("
    SELECT slot_id, appointment_date, start_time, end_time, max_patient_count, booked_patient_count
    FROM appointment_slots
    WHERE doctor_id = ? AND slot_status IN ('open', 'full') AND appointment_date >= CURDATE()
    ORDER BY appointment_date ASC, start_time ASC
");
$stmt->execute([$doctor_id]);
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_id = !empty($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
    $symptom_note = trim($_POST['symptom_note'] ?? '');
    $patient_id = get_current_user_id();
    
    // Validation
    if (empty($slot_id)) {
        $errors['slot_id'] = 'Please select a time slot';
    }
    if (empty($symptom_note) || strlen($symptom_note) > 500) {
        $errors['symptom_note'] = 'Symptom description required, max 500 characters';
    }
    
    if (empty($errors)) {
        try {
            $apdo = get_admin_pdo();
            
            // Start transaction
            $apdo->beginTransaction();
            
            // Lock and check slot availability
            $check_stmt = $apdo->prepare("
                SELECT slot_id, booked_patient_count, max_patient_count 
                FROM appointment_slots 
                WHERE slot_id = ? AND slot_status IN ('open', 'full')
                FOR UPDATE
            ");
            $check_stmt->execute([$slot_id]);
            $slot_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot_info) {
                $apdo->rollBack();
                $errors['general'] = 'Time slot is not available.';
            } else if ($slot_info['booked_patient_count'] >= $slot_info['max_patient_count']) {
                $apdo->rollBack();
                $errors['general'] = 'This time slot is now full. Please select another slot.';
            } else {
                // Calculate serial number
                $serial_no = $slot_info['booked_patient_count'] + 1;
                
                // Insert booking directly (bypass stored procedure to avoid trigger conflicts)
                $insert_stmt = $apdo->prepare("
                    INSERT INTO appointment_bookings 
                    (slot_id, patient_id, booking_serial_no, symptom_note, booking_status, booked_at)
                    VALUES (?, ?, ?, ?, 'pending_payment', NOW())
                ");
                $insert_stmt->execute([$slot_id, $patient_id, $serial_no, $symptom_note]);
                $booking_id = $apdo->lastInsertId();
                
                // Update slot count and status
                $update_stmt = $apdo->prepare("
                    UPDATE appointment_slots 
                    SET booked_patient_count = booked_patient_count + 1
                    WHERE slot_id = ?
                ");
                $update_stmt->execute([$slot_id]);
                
                // Commit transaction
                $apdo->commit();
                
                if ($booking_id > 0) {
                    set_flash('success', "Booking confirmed! Serial: #$serial_no. Please proceed to payment.");
                    header("Location: payment.php?booking_id=$booking_id");
                    exit;
                } else {
                    $errors['general'] = 'Booking failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            if ($apdo->inTransaction()) {
                $apdo->rollBack();
            }
            
            $error_msg = $e->getMessage();
            if (stripos($error_msg, 'duplicate') !== false) {
                $errors['general'] = 'You already have an active booking with this doctor.';
            } elseif (stripos($error_msg, 'foreign key') !== false) {
                $errors['general'] = 'Selected slot is no longer available.';
            } else {
                $errors['general'] = 'Booking failed: ' . htmlspecialchars($error_msg);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Appointment</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="search.php">Find Doctors</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Book Appointment</h2>
        
        <div class="doctor-info">
            <h3><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
            <p>
                <strong><?php echo htmlspecialchars($doctor['specialization_name']); ?></strong> |
                <?php echo htmlspecialchars($doctor['area_name']); ?> |
                <strong>Fee: ৳<?php echo $doctor['visit_fee']; ?></strong>
            </p>
        </div>
        
        <?php echo display_flash(); ?>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Time Slot Selection -->
            <div class="form-group">
                <label>Select Time Slot: <span class="required">*</span></label>
                <select name="slot_id" required>
                    <option value="">-- Choose a slot --</option>
                    <?php foreach ($slots as $slot): 
                        $slot_datetime = $slot['appointment_date'] . ' ' . $slot['start_time'];
                        $available = $slot['max_patient_count'] - $slot['booked_patient_count'];
                        $is_full = $available <= 0;
                    ?>
                        <option value="<?php echo $slot['slot_id']; ?>" <?php echo $is_full ? 'disabled' : ''; ?>>
                            <?php 
                            echo date('d-M-Y', strtotime($slot['appointment_date'])) . ' | ' .
                                 date('h:i A', strtotime($slot['start_time'])) . ' - ' .
                                 date('h:i A', strtotime($slot['end_time']));
                            if ($is_full) {
                                echo ' (FULL)';
                            } else {
                                echo ' (' . $available . ' seat' . ($available > 1 ? 's' : '') . ' available)';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['slot_id'])): ?>
                    <span class="error-text"><?php echo $errors['slot_id']; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Symptom Description -->
            <div class="form-group">
                <label>Describe Your Symptoms: <span class="required">*</span></label>
                <textarea name="symptom_note" maxlength="500" rows="4" placeholder="Please describe your symptoms..." required></textarea>
                <small>Max 500 characters</small>
                <?php if (!empty($errors['symptom_note'])): ?>
                    <span class="error-text"><?php echo $errors['symptom_note']; ?></span>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn">Proceed to Payment</button>
                <a href="search.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
</body>
</html>
