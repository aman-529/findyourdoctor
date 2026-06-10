<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();

$doctor_id = (int)($_GET['doctor_id'] ?? 0);

if (!$doctor_id) {
    set_flash('Doctor not found', 'error');
    header('Location: search.php');
    exit;
}

try {
    $apdo = get_admin_pdo();
    
    // Get doctor profile details
    $dstmt = $apdo->prepare("
        SELECT 
            dp.doctor_id, dp.qualification, dp.experience_years, dp.biography,
            dp.chamber_name, dp.chamber_address, dp.consultation_fee,
            u.full_name, u.email, u.phone,
            s.specialization_name,
            a.area_name, a.city
        FROM doctor_profiles dp
        JOIN users u ON dp.doctor_id = u.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN areas a ON dp.area_id = a.area_id
        WHERE dp.doctor_id = ? AND dp.verification_status = 'approved'
    ");
    $dstmt->execute([$doctor_id]);
    $doctor = $dstmt->fetch();
    
    if (!$doctor) {
        set_flash('Doctor not found', 'error');
        header('Location: search.php');
        exit;
    }
    
    // Get doctor reviews
    $rstmt = $apdo->prepare("
        SELECT r.rating, r.review_text, r.review_date,
               u.full_name AS patient_name
        FROM reviews r
        JOIN appointment_bookings ab ON r.booking_id = ab.booking_id
        JOIN users u ON ab.patient_id = u.user_id
        WHERE ab.slot_id IN (SELECT slot_id FROM appointment_slots WHERE doctor_id = ?)
        AND r.review_status = 'approved'
        ORDER BY r.review_date DESC
    ");
    $rstmt->execute([$doctor_id]);
    $reviews = $rstmt->fetchAll();
    
    // Calculate average rating
    $avgstmt = $apdo->prepare("
        SELECT AVG(r.rating) AS avg_rating, COUNT(*) AS total_reviews
        FROM reviews r
        JOIN appointment_bookings ab ON r.booking_id = ab.booking_id
        WHERE ab.slot_id IN (SELECT slot_id FROM appointment_slots WHERE doctor_id = ?)
        AND r.review_status = 'approved'
    ");
    $avgstmt->execute([$doctor_id]);
    $rating_info = $avgstmt->fetch();
    
} catch (PDOException $e) {
    set_flash('Error loading doctor profile: ' . $e->getMessage(), 'error');
    header('Location: search.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($doctor['full_name']); ?> - Doctor Appointment</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .profile-sidebar {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .profile-info-item {
            margin-bottom: 15px;
        }
        .profile-info-item strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }
        .profile-info-item p {
            margin: 0;
            color: #666;
            line-height: 1.5;
        }
        .profile-main h1 {
            margin: 0 0 10px 0;
            color: #2196F3;
        }
        .profile-main .specialization {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
        }
        .rating-display {
            font-size: 18px;
            color: #ff9800;
            margin-bottom: 15px;
        }
        .review-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2196F3;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .review-author {
            font-weight: bold;
            color: #333;
        }
        .review-date {
            font-size: 12px;
            color: #999;
        }
        .review-rating {
            color: #ff9800;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .review-text {
            color: #666;
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="search.php">Find Doctors</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <a href="search.php" class="btn" style="margin-bottom: 20px;">← Back to Search</a>
    
    <?php echo display_flash(); ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-info-item">
                <strong>Consultation Fee</strong>
                <p style="font-size: 20px; color: #4caf50; font-weight: bold;">
                    ৳<?php echo htmlspecialchars($doctor['consultation_fee']); ?>
                </p>
            </div>
            
            <div class="profile-info-item">
                <strong>Specialization</strong>
                <p><?php echo htmlspecialchars($doctor['specialization_name']); ?></p>
            </div>
            
            <div class="profile-info-item">
                <strong>Location</strong>
                <p><?php echo htmlspecialchars($doctor['area_name'] . ', ' . $doctor['city']); ?></p>
            </div>
            
            <?php if (!empty($doctor['chamber_name']) || !empty($doctor['chamber_address'])): ?>
                <div class="profile-info-item">
                    <strong>Chamber Information</strong>
                    <?php if (!empty($doctor['chamber_name'])): ?>
                        <p><strong style="display: inline;">Name:</strong> <?php echo htmlspecialchars($doctor['chamber_name']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($doctor['chamber_address'])): ?>
                        <p><strong style="display: inline;">Address:</strong> <?php echo htmlspecialchars($doctor['chamber_address']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-info-item">
                <strong>Experience</strong>
                <p><?php echo htmlspecialchars($doctor['experience_years']); ?> years</p>
            </div>
            
            <?php if (!empty($doctor['phone'])): ?>
                <div class="profile-info-item">
                    <strong>Contact</strong>
                    <p><?php echo htmlspecialchars($doctor['phone']); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="booking.php?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-primary" style="width: 100%; text-align: center;">Book Appointment</a>
            </div>
        </div>
        
        <div class="profile-main">
            <h1><?php echo htmlspecialchars($doctor['full_name']); ?></h1>
            <p class="specialization"><?php echo htmlspecialchars($doctor['specialization_name']); ?></p>
            
            <?php if (!empty($rating_info['avg_rating'])): ?>
                <div class="rating-display">
                    ★ <?php echo round($rating_info['avg_rating'], 1); ?>/5 
                    (<?php echo $rating_info['total_reviews']; ?> reviews)
                </div>
            <?php endif; ?>
            
            <h2>About</h2>
            <p><?php echo nl2br(htmlspecialchars($doctor['biography'] ?? 'No biography provided')); ?></p>
            
            <h2>Qualifications</h2>
            <p><?php echo nl2br(htmlspecialchars($doctor['qualification'] ?? 'Not specified')); ?></p>
            
            <?php if (!empty($reviews)): ?>
                <h2>Patient Reviews (<?php echo count($reviews); ?>)</h2>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <span class="review-author"><?php echo htmlspecialchars($review['patient_name']); ?></span>
                            <span class="review-date"><?php echo date('d M Y', strtotime($review['review_date'])); ?></span>
                        </div>
                        <div class="review-rating">★ <?php echo $review['rating']; ?>/5</div>
                        <div class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">No reviews yet. Be the first to review this doctor!</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
