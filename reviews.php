<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('admin');

$apdo = get_admin_pdo();

// Handle approve/reject review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    $admin_id = get_current_user_id();
    
    try {
        if ($action === 'approve') {
            $stmt = $apdo->prepare("UPDATE reviews SET review_status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE review_id = ?");
            $stmt->execute([$admin_id, $review_id]);
            set_flash('success', 'Review approved successfully!');
        } elseif ($action === 'reject') {
            $stmt = $apdo->prepare("UPDATE reviews SET review_status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE review_id = ?");
            $stmt->execute([$admin_id, $review_id]);
            set_flash('success', 'Review rejected.');
        }
        header('Location: reviews.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Action failed: ' . htmlspecialchars($e->getMessage()));
    }
}

// Fetch pending reviews
try {
    $pending = $apdo->query("
        SELECT r.review_id, r.rating, r.review_text, r.created_at,
               du.full_name AS doctor_name, s.specialization_name,
               pu.full_name AS patient_name,
               ab.booking_id
        FROM reviews r
        JOIN doctor_profiles dp ON r.doctor_id = dp.doctor_id
        JOIN users du ON dp.doctor_id = du.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN users pu ON r.patient_id = pu.user_id
        JOIN appointment_bookings ab ON r.booking_id = ab.booking_id
        WHERE r.review_status = 'pending'
        ORDER BY r.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending = [];
    set_flash('error', 'Error loading pending reviews');
}

// Fetch approved reviews
try {
    $approved = $apdo->query("
        SELECT r.review_id, r.rating, r.review_text, r.created_at,
               du.full_name AS doctor_name,
               pu.full_name AS patient_name
        FROM reviews r
        JOIN doctor_profiles dp ON r.doctor_id = dp.doctor_id
        JOIN users du ON dp.doctor_id = du.user_id
        JOIN users pu ON r.patient_id = pu.user_id
        WHERE r.review_status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $approved = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderate Reviews - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span><?= htmlspecialchars($_SESSION['user_name']) ?> (Admin)</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Moderate Reviews</h2>
    
    <?php echo display_flash(); ?>
    
    <!-- Pending Reviews -->
    <div class="dashboard-section">
        <h3>Pending Moderation (<?= count($pending) ?>)</h3>
        
        <?php if (!empty($pending)): ?>
            <?php foreach ($pending as $review): ?>
                <div style="border:1px solid #ddd; padding:15px; margin-bottom:15px; border-radius:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:start;">
                        <div>
                            <strong><?= htmlspecialchars($review['doctor_name']) ?></strong> (<?= htmlspecialchars($review['specialization_name']) ?>)
                            <br/>
                            <small style="color:#666;">Reviewed by: <?= htmlspecialchars($review['patient_name']) ?></small>
                            <br/>
                            <strong>Rating:</strong> <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5-$review['rating']) ?> (<?= $review['rating'] ?>/5)
                            <br/>
                            <strong>Review:</strong>
                            <p style="margin:8px 0; font-style:italic;"><?= htmlspecialchars($review['review_text']) ?></p>
                            <small style="color:#999;">Submitted: <?= date('d-M-Y h:i A', strtotime($review['created_at'])) ?></small>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="review_id" value="<?= $review['review_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-small btn-success" onclick="return confirm('Approve this review?')">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="review_id" value="<?= $review['review_id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Reject this review?')">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No pending reviews to moderate.</div>
        <?php endif; ?>
    </div>
    
    <!-- Approved Reviews -->
    <div class="dashboard-section" style="margin-top:24px;">
        <h3>Approved Reviews (<?= count($approved) ?>)</h3>
        
        <?php if (!empty($approved)): ?>
            <?php foreach ($approved as $review): ?>
                <div style="border:1px solid #e0e0e0; padding:12px; margin-bottom:10px; border-radius:4px; background:#f9f9f9;">
                    <strong><?= htmlspecialchars($review['doctor_name']) ?></strong>
                    <span style="float:right; color:#666; font-size:12px;"><?= date('d-M-Y', strtotime($review['created_at'])) ?></span>
                    <br/>
                    <small>by <?= htmlspecialchars($review['patient_name']) ?></small>
                    <br/>
                    <span style="color:#f39c12;">★<?= $review['rating'] ?>/5</span>
                    <p style="margin:5px 0; color:#555;"><?= htmlspecialchars(mb_substr($review['review_text'], 0, 100)) ?>...</p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No approved reviews yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
