<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('patient');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$patient_id = get_current_user_id();
$errors     = [];

// Fetch booking — include booking_status and doctor_id
$apdo = get_admin_pdo();
$stmt = $apdo->prepare("
    SELECT ab.booking_id, ab.booking_status, ab.booking_serial_no, ab.symptom_note,
           asl.visit_fee, asl.appointment_date, asl.start_time, asl.doctor_id,
           du.full_name AS doctor_name,
           s.specialization_name,
           pu.full_name AS patient_name
    FROM appointment_bookings ab
    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
    JOIN doctor_profiles dp    ON asl.doctor_id = dp.doctor_id
    JOIN users du              ON dp.doctor_id = du.user_id
    JOIN specializations s     ON dp.specialization_id = s.specialization_id
    JOIN users pu              ON ab.patient_id = pu.user_id
    WHERE ab.booking_id = ? AND ab.patient_id = ?
");
$stmt->execute([$booking_id, $patient_id]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking not found.');
    header('Location: dashboard.php'); exit;
}

if ($booking['booking_status'] !== 'pending_payment') {
    set_flash('info', 'This booking is not awaiting payment.');
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method  = trim($_POST['payment_method'] ?? '');
    $transaction_ref = trim($_POST['transaction_reference'] ?? '');
    $allowed         = ['bKash','Nagad','Card','Cash'];

    if (!in_array($payment_method, $allowed))            $errors['payment_method'] = 'Select a valid payment method.';
    if ($payment_method !== 'Cash' && empty($transaction_ref)) $errors['transaction_reference'] = 'Transaction reference required.';

    if (empty($errors)) {
        try {
            $apdo = get_admin_pdo();
            $stmt = $apdo->prepare("CALL sp_confirm_payment(?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $booking_id,
                $patient_id,
                $booking['doctor_id'],
                $booking['visit_fee'],
                $payment_method,
                $transaction_ref ?: null
            ]);
            $stmt->closeCursor();
            set_flash('success', 'Payment successful! Your booking is confirmed.');
            header('Location: dashboard.php'); exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Payment failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - Doctor Appointment System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Complete Payment</h2>

        <?php echo display_flash(); ?>
        <?php if (!empty($errors['general'])): ?><div class="alert alert-error"><?= $errors['general'] ?></div><?php endif; ?>

        <div class="alert alert-info">
            <strong>Booking Summary</strong><br>
            <strong>Doctor:</strong> <?= htmlspecialchars($booking['doctor_name']) ?> (<?= htmlspecialchars($booking['specialization_name']) ?>)<br>
            <strong>Date &amp; Time:</strong> <?= date('d-M-Y h:i A', strtotime($booking['appointment_date'].' '.$booking['start_time'])) ?><br>
            <strong>Serial No:</strong> #<?= (int)$booking['booking_serial_no'] ?><br>
            <strong>Amount Due:</strong> <big><strong>&#2547;<?= number_format($booking['visit_fee'],2) ?></strong></big>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Payment Method: <span class="required">*</span></label>
                <select name="payment_method" id="pm" onchange="toggleTxn()" required>
                    <option value="">-- Select --</option>
                    <option value="bKash"  <?= ($_POST['payment_method']??'')==='bKash' ?'selected':''?>>bKash</option>
                    <option value="Nagad"  <?= ($_POST['payment_method']??'')==='Nagad' ?'selected':''?>>Nagad</option>
                    <option value="Card"   <?= ($_POST['payment_method']??'')==='Card'  ?'selected':''?>>Card</option>
                    <option value="Cash"   <?= ($_POST['payment_method']??'')==='Cash'  ?'selected':''?>>Cash</option>
                </select>
                <?php if (!empty($errors['payment_method'])): ?><span class="error-text"><?= $errors['payment_method'] ?></span><?php endif; ?>
            </div>

            <div class="form-group" id="txn-group" style="display:none;">
                <label>Transaction Reference: <span class="required">*</span></label>
                <input type="text" name="transaction_reference" placeholder="e.g. TXN123456"
                       value="<?= htmlspecialchars($_POST['transaction_reference']??'') ?>">
                <?php if (!empty($errors['transaction_reference'])): ?><span class="error-text"><?= $errors['transaction_reference'] ?></span><?php endif; ?>
            </div>

            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="submit" class="btn btn-success"
                        onclick="return confirm('Confirm payment of ৳<?= number_format($booking['visit_fee'],2) ?>?')">
                    Confirm Payment
                </button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
function toggleTxn() {
    var m = document.getElementById('pm').value;
    document.getElementById('txn-group').style.display = (m && m !== 'Cash') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleTxn);
</script>
</body>
</html>
