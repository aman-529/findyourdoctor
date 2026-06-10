<?php
include 'config.php';
include 'auth.php';

require_login();

$doctor_id = (int)($_GET['doctor_id'] ?? 0);

if (!$doctor_id) {
    echo '<p style="color: #e74c3c;">Invalid doctor ID</p>';
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
            a.area_name, a.city,
            ROUND(AVG(r.rating), 1) AS avg_rating,
            COUNT(DISTINCT r.review_id) AS total_reviews
        FROM doctor_profiles dp
        JOIN users u ON dp.doctor_id = u.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN areas a ON dp.area_id = a.area_id
        LEFT JOIN reviews r ON dp.doctor_id = r.doctor_id AND r.review_status = 'approved'
        WHERE dp.doctor_id = ?
        GROUP BY dp.doctor_id
    ");
    $dstmt->execute([$doctor_id]);
    $doctor = $dstmt->fetch();
    
    if (!$doctor) {
        echo '<p style="color: #e74c3c;">Doctor not found</p>';
        exit;
    }
    
    // Get doctor reviews
    $rstmt = $apdo->prepare("
        SELECT r.rating, r.review_text, r.created_at,
               u.full_name AS patient_name
        FROM reviews r
        JOIN appointment_bookings ab ON r.booking_id = ab.booking_id
        JOIN users u ON ab.patient_id = u.user_id
        WHERE r.doctor_id = ?
        AND r.review_status = 'approved'
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $rstmt->execute([$doctor_id]);
    $reviews = $rstmt->fetchAll();
    
} catch (PDOException $e) {
    echo '<p style="color: #e74c3c;">Error loading doctor information: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
?>

<div class="doctor-detail-item">
    <span class="doctor-detail-label">Specialization</span>
    <span class="doctor-detail-value"><?= htmlspecialchars($doctor['specialization_name']) ?></span>
</div>

<div class="doctor-detail-item">
    <span class="doctor-detail-label">Experience</span>
    <span class="doctor-detail-value"><?= htmlspecialchars($doctor['experience_years']) ?> years</span>
</div>

<div class="doctor-detail-item">
    <span class="doctor-detail-label">Qualification</span>
    <span class="doctor-detail-value"><?= nl2br(htmlspecialchars($doctor['qualification'] ?? 'Not specified')) ?></span>
</div>

<div class="doctor-detail-item">
    <span class="doctor-detail-label">Consultation Fee</span>
    <span class="doctor-detail-value" style="color: #4caf50; font-weight: bold; font-size: 16px;">
        ৳<?= htmlspecialchars($doctor['consultation_fee']) ?>
    </span>
</div>

<?php if (!empty($doctor['chamber_name']) || !empty($doctor['chamber_address'])): ?>
    <div class="doctor-detail-item">
        <span class="doctor-detail-label">Chamber</span>
        <span class="doctor-detail-value">
            <?php if (!empty($doctor['chamber_name'])): ?>
                <?= htmlspecialchars($doctor['chamber_name']) ?><br>
            <?php endif; ?>
            <?php if (!empty($doctor['chamber_address'])): ?>
                <small style="color: #999;"><?= htmlspecialchars($doctor['chamber_address']) ?></small>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($doctor['biography'])): ?>
    <div class="doctor-detail-item">
        <span class="doctor-detail-label">About</span>
        <span class="doctor-detail-value"><?= nl2br(htmlspecialchars($doctor['biography'])) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($reviews) || $doctor['total_reviews'] > 0): ?>
    <div class="reviews-section">
        <span class="doctor-detail-label">Patient Reviews (<?= $doctor['total_reviews'] ?? 0 ?>)</span>
        
        <?php if (!empty($doctor['avg_rating'])): ?>
            <div style="margin-bottom: 15px; padding-bottom: 10px;">
                <span style="color: #ffc107; font-size: 18px; font-weight: bold;">★ <?= round($doctor['avg_rating'], 1) ?>/5</span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-rating">★ <?= $review['rating'] ?>/5</div>
                    <div class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></div>
                    <div class="review-author">
                        <strong><?= htmlspecialchars($review['patient_name']) ?></strong> - 
                        <?= date('d M Y', strtotime($review['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #999; font-size: 14px;">No reviews yet</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
