<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('doctor');

$doctor_id = get_current_user_id();
$apdo = get_admin_pdo();
$errors = [];

// Fetch current doctor profile
try {
    $profile = $apdo->query("
        SELECT dp.*, u.email, u.phone, u.full_name,
               s.specialization_name, a.area_name, a.city
        FROM doctor_profiles dp
        JOIN users u ON dp.doctor_id = u.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN areas a ON dp.area_id = a.area_id
        WHERE dp.doctor_id = $doctor_id
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profile = [];
    set_flash('error', 'Error loading profile');
}

if (!$profile) {
    set_flash('error', 'Profile not found');
    header('Location: dashboard.php');
    exit;
}

// Handle profile update request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $biography = trim($_POST['biography'] ?? '');
    $consultation_fee = !empty($_POST['consultation_fee']) ? (float)$_POST['consultation_fee'] : null;
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : null;
    $chamber_name = trim($_POST['chamber_name'] ?? '');
    
    // Validation
    if (empty($biography) && empty($consultation_fee) && empty($qualification) && empty($experience_years) && empty($chamber_name)) {
        $errors['general'] = 'Please fill in at least one field to request changes';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $apdo->prepare("
                INSERT INTO doctor_profile_update_requests 
                (doctor_id, req_biography, req_consultation_fee, req_qualification, req_experience_years, req_chamber_name, request_status, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $doctor_id,
                !empty($biography) ? $biography : null,
                $consultation_fee,
                !empty($qualification) ? $qualification : null,
                $experience_years,
                !empty($chamber_name) ? $chamber_name : null
            ]);
            
            set_flash('success', 'Profile update request submitted! Admin will review and approve.');
            header('Location: doctor_profile.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to submit request: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch pending update requests
try {
    $pending_requests = $apdo->query("
        SELECT request_id, submitted_at, request_status
        FROM doctor_profile_update_requests
        WHERE doctor_id = $doctor_id AND request_status = 'pending'
        ORDER BY submitted_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Doctor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span><?= htmlspecialchars($_SESSION['user_name']) ?> (Doctor)</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>My Professional Profile</h2>
    
    <?php echo display_flash(); ?>
    
    <!-- Current Profile Info -->
    <div class="dashboard-section">
        <h3>Current Profile Information</h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <p><strong>Name:</strong> <?= htmlspecialchars($profile['full_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($profile['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($profile['phone']) ?></p>
                <p><strong>Gender:</strong> <?= ucfirst($profile['gender']) ?></p>
                <p><strong>Date of Birth:</strong> <?= date('d-M-Y', strtotime($profile['date_of_birth'])) ?></p>
            </div>
            
            <div>
                <p><strong>Specialization:</strong> <?= htmlspecialchars($profile['specialization_name']) ?></p>
                <p><strong>Working Area:</strong> <?= htmlspecialchars($profile['area_name'] . ', ' . $profile['city']) ?></p>
                <p><strong>Qualification:</strong> <?= htmlspecialchars($profile['qualification']) ?></p>
                <p><strong>Experience:</strong> <?= (int)$profile['experience_years'] ?> years</p>
                <p><strong>License No:</strong> <?= htmlspecialchars($profile['license_no']) ?></p>
                <p><strong>Consultation Fee:</strong> ৳<?= number_format($profile['consultation_fee'], 2) ?></p>
            </div>
        </div>
        
        <div style="margin-top: 15px;">
            <p><strong>Biography:</strong></p>
            <p style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <?= htmlspecialchars($profile['biography'] ?: 'No biography added') ?>
            </p>
        </div>
        
        <div style="margin-top: 15px;">
            <p><strong>Verification Status:</strong> 
                <span class="badge badge-<?= strtolower($profile['verification_status']) ?>">
                    <?= ucfirst($profile['verification_status']) ?>
                </span>
            </p>
        </div>
    </div>
    
    <!-- Pending Requests Status -->
    <?php if (!empty($pending_requests)): ?>
        <div class="dashboard-section" style="margin-top: 24px; background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;">
            <h3 style="margin-top: 0;">Pending Update Requests</h3>
            <p style="color: #856404;">
                You have <strong><?= count($pending_requests) ?></strong> pending profile update request(s) awaiting admin review.
                Admin will review your changes and approve or reject them.
            </p>
            <ul style="color: #856404;">
                <?php foreach ($pending_requests as $req): ?>
                    <li>Submitted: <?= date('d-M-Y h:i A', strtotime($req['submitted_at'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Update Profile Form -->
    <div class="form-box" style="margin-top: 24px;">
        <h3>Request Profile Updates</h3>
        <p style="color: #666; font-size: 13px;">Submit changes for admin approval. Only fill fields you want to change.</p>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Consultation Fee (৳):</label>
                    <input type="number" name="consultation_fee" min="0" step="0.01" 
                           placeholder="e.g., 500" value="<?= htmlspecialchars($_POST['consultation_fee'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Experience (Years):</label>
                    <input type="number" name="experience_years" min="0" max="70" 
                           placeholder="e.g., 15" value="<?= htmlspecialchars($_POST['experience_years'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Qualification:</label>
                    <input type="text" name="qualification" placeholder="e.g., MBBS, MD Cardiology"
                           value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Chamber/Clinic Name:</label>
                    <input type="text" name="chamber_name" placeholder="e.g., Heart Care Chamber"
                           value="<?= htmlspecialchars($_POST['chamber_name'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Biography:</label>
                    <textarea name="biography" rows="5" placeholder="Tell patients about your expertise, experience, and approach..."><?= htmlspecialchars($_POST['biography'] ?? '') ?></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Submit Update Request</button>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 15px;">Back</a>
        </form>
    </div>
</div>
</body>
</html>
