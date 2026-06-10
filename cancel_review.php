<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('patient');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$patient_id = get_current_user_id();
$errors = [];

$apdo = get_admin_pdo();
$stmt = $apdo->prepare("
    SELECT ab.booking_id, ab.booking_status, u.full_name, s.specialization_name,
           ast.appointment_date, ast.start_time, dp.doctor_id
    FROM appointment_bookings ab
    JOIN appointment_slots ast ON ab.slot_id = ast.slot_id
    JOIN doctor_profiles dp ON ast.doctor_id = dp.doctor_id
    JOIN users u ON dp.doctor_id = u.user_id
    JOIN specializations s ON dp.specialization_id = s.specialization_id
    WHERE ab.booking_id = ? AND ab.patient_id = ?
");
$stmt->execute([$booking_id, $patient_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    set_flash('error', 'Booking not found');
    header('Location: dashboard.php');
    exit;
}

if ($booking['booking_status'] !== 'completed') {
    set_flash('error', 'You can only review completed appointments');
    header('Location: dashboard.php');
    exit;
}

$stmt = $apdo->prepare("SELECT review_id FROM reviews WHERE booking_id = ?");
$stmt->execute([$booking_id]);
if ($stmt->fetch()) {
    set_flash('info', 'You have already reviewed this appointment');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = trim($_POST['review_text'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Please select a rating between 1 and 5';
    }
    if (empty($review_text) || strlen($review_text) > 1000) {
        $errors['review_text'] = 'Review text required, max 1000 characters';
    }
    
    if (empty($errors)) {
        try {
            // Call stored procedure: sp_submit_review(booking_id, doctor_id, patient_id, rating, review_text)
            $apdo = get_admin_pdo();
            $stmt = $apdo->prepare("CALL sp_submit_review(?, ?, ?, ?, ?)");
            $stmt->execute([
                $booking_id,
                $booking['doctor_id'],
                $patient_id,
                $rating,
                $review_text
            ]);
            $stmt->closeCursor();
            
            set_flash('success', 'Thank you! Your review has been submitted for approval.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'completed') !== false) {
                $errors['general'] = 'This appointment is not marked as completed';
            } else {
                $errors['general'] = 'Failed to submit review: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Write Review</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Find My Doctor</h1>
    <div class="nav-right">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Write a Review</h2>
        <p style="color: #666;">
            Doctor: <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong> 
            (<?php echo htmlspecialchars($booking['specialization_name']); ?>)
        </p>
        
        <?php echo display_flash(); ?>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Rating -->
            <div class="form-group">
                <label>Rating: <span class="required">*</span></label>
                <div class="rating-input">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating-<?php echo $i; ?>">
                        <label for="rating-<?php echo $i; ?>" class="rating-label">
                            <?php for ($j = 0; $j < $i; $j++) echo '★'; ?>
                        </label>
                    <?php endfor; ?>
                </div>
                <?php if (!empty($errors['rating'])): ?>
                    <span class="error-text"><?php echo $errors['rating']; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Review Text -->
            <div class="form-group">
                <label>Your Review: <span class="required">*</span></label>
                <textarea name="review_text" maxlength="1000" rows="6" placeholder="Share your experience with this doctor..." required></textarea>
                <small>Max 1000 characters</small>
                <?php if (!empty($errors['review_text'])): ?>
                    <span class="error-text"><?php echo $errors['review_text']; ?></span>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn">Submit Review</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.rating-input {
    display: flex; 
    gap: 10px;
    font-size: 30px;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-label {
    cursor: pointer;
    color: #ddd;
    transition: color 0.2s;
}

.rating-input input[type="radio"]:checked ~ .rating-label,
.rating-label:hover {
    color: #ffc107;
}
</style>
</body>
</html>
