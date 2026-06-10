<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('doctor');

$doctor_id = get_current_user_id();
$apdo = get_admin_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $max_patients = !empty($_POST['max_patient_count']) ? (int)$_POST['max_patient_count'] : 0;
    $chamber_name = trim($_POST['chamber_name'] ?? '');
    $chamber_address = trim($_POST['chamber_address'] ?? '');
    
    if (empty($appointment_date)) $errors['appointment_date'] = 'Date is required';
    if (empty($start_time)) $errors['start_time'] = 'Start time is required';
    if (empty($end_time)) $errors['end_time'] = 'End time is required';
    if ($max_patients < 1) $errors['max_patient_count'] = 'Max patients must be at least 1';
    if (strtotime($appointment_date) === false) $errors['appointment_date'] = 'Invalid date format';
    if (strtotime($start_time) >= strtotime($end_time)) $errors['end_time'] = 'End time must be after start time';
    if (strtotime($appointment_date) < time()) $errors['appointment_date'] = 'Date must be in the future';
    
    if (empty($errors)) {
        try {
          
            $docfee = $apdo->query("SELECT consultation_fee FROM doctor_profiles WHERE doctor_id = $doctor_id")->fetch();
            $visit_fee = (float)($docfee['consultation_fee'] ?? 0);
            
            $stmt = $apdo->prepare("
                INSERT INTO appointment_slots 
                (doctor_id, appointment_date, start_time, end_time, visit_fee, max_patient_count, chamber_name, chamber_address, slot_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $doctor_id,
                $appointment_date,
                $start_time,
                $end_time,
                $visit_fee,
                $max_patients,
                $chamber_name,
                $chamber_address
            ]);
            
            set_flash('success', 'Appointment slot created successfully!');
            header('Location: doctor_slots.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to create slot: ' . htmlspecialchars($e->getMessage());
        }
    }
}

if (isset($_GET['cancel_slot'])) {
    $slot_id = (int)$_GET['cancel_slot'];
    try {
        $stmt = $apdo->prepare("UPDATE appointment_slots SET slot_status = 'cancelled' WHERE slot_id = ? AND doctor_id = ?");
        $stmt->execute([$slot_id, $doctor_id]);
        set_flash('success', 'Slot cancelled successfully');
        header('Location: doctor_slots.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Failed to cancel slot');
    }
}

// Fetch upcoming slots
try {
    $slots = $apdo->query("
        SELECT slot_id, appointment_date, start_time, end_time, max_patient_count, booked_patient_count, slot_status, chamber_name
        FROM appointment_slots
        WHERE doctor_id = $doctor_id AND appointment_date >= CURDATE()
        ORDER BY appointment_date ASC, start_time ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $slots = [];
    set_flash('error', 'Error loading slots');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointment Slots - Doctor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Find My Doctor</h1>
    <div class="nav-right">
        <span><?= htmlspecialchars($_SESSION['user_name']) ?> (Doctor)</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Manage Appointment Slots</h2>
    
    <?php echo display_flash(); ?>
    
    <div class="dashboard-section">
        <h3>Create New Slot</h3>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form-box">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Appointment Date: <span class="required">*</span></label>
                    <input type="date" name="appointment_date" required value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>">
                    <?php if (!empty($errors['appointment_date'])): ?>
                        <span class="error-text"><?= htmlspecialchars($errors['appointment_date']) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Max Patients: <span class="required">*</span></label>
                    <input type="number" name="max_patient_count" min="1" max="40" required value="<?= htmlspecialchars($_POST['max_patient_count'] ?? '1') ?>">
                    <?php if (!empty($errors['max_patient_count'])): ?>
                        <span class="error-text"><?= htmlspecialchars($errors['max_patient_count']) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Start Time: <span class="required">*</span></label>
                    <input type="time" name="start_time" required value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>">
                    <?php if (!empty($errors['start_time'])): ?>
                        <span class="error-text"><?= htmlspecialchars($errors['start_time']) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>End Time: <span class="required">*</span></label>
                    <input type="time" name="end_time" required value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
                    <?php if (!empty($errors['end_time'])): ?>
                        <span class="error-text"><?= htmlspecialchars($errors['end_time']) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Chamber Name:</label>
                    <input type="text" name="chamber_name" value="<?= htmlspecialchars($_POST['chamber_name'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Chamber Address:</label>
                    <textarea name="chamber_address" rows="3"><?= htmlspecialchars($_POST['chamber_address'] ?? '') ?></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Create Slot</button>
        </form>
    </div>
    
    <!-- Upcoming Slots -->
    <div class="dashboard-section" style="margin-top: 24px;">
        <h3>Your Upcoming Slots</h3>
        
        <?php if (!empty($slots)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Booked</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slots as $slot): ?>
                        <tr>
                            <td><?= date('d-M-Y', strtotime($slot['appointment_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?></td>
                            <td><?= htmlspecialchars($slot['chamber_name'] ?: 'Not specified') ?></td>
                            <td><?= $slot['max_patient_count'] ?></td>
                            <td><?= $slot['booked_patient_count'] ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($slot['slot_status']) ?>">
                                    <?= ucfirst($slot['slot_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($slot['slot_status'] !== 'cancelled'): ?>
                                    <a href="doctor_slots.php?cancel_slot=<?= $slot['slot_id'] ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Cancel this slot?')">Cancel</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No upcoming slots. Create your first slot above!</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
