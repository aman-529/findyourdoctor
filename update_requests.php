<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('admin');

$apdo = get_admin_pdo();

// Handle approve/reject profile update requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $admin_id = get_current_user_id();
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    try {
        if ($action === 'approve') {
            $stmt = $apdo->prepare("CALL sp_approve_profile_update(?, ?)");
            $stmt->execute([$request_id, $admin_id]);
            $stmt->closeCursor();
            set_flash('success', 'Profile update approved successfully!');
        } elseif ($action === 'reject') {
            if (empty($rejection_reason)) {
                set_flash('error', 'Please provide a rejection reason.');
                header('Location: update_requests.php');
                exit;
            }
            $stmt = $apdo->prepare("CALL sp_reject_profile_update(?, ?, ?)");
            $stmt->execute([$request_id, $admin_id, $rejection_reason]);
            $stmt->closeCursor();
            set_flash('success', 'Profile update request rejected.');
        }
        header('Location: update_requests.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Action failed: ' . htmlspecialchars($e->getMessage()));
    }
}

// Fetch pending profile update requests
try {
    $pending = $apdo->query("
        SELECT dpur.request_id, dpur.submitted_at,
               u.full_name, u.email, u.phone,
               s.specialization_name,
               a.area_name, a.city,
               dpur.req_biography, dpur.req_consultation_fee, dpur.req_qualification,
               dpur.req_license_no, dpur.req_experience_years
        FROM doctor_profile_update_requests dpur
        JOIN doctor_profiles dp ON dpur.doctor_id = dp.doctor_id
        JOIN users u ON dp.doctor_id = u.user_id
        LEFT JOIN specializations s ON dpur.req_specialization_id = s.specialization_id
        LEFT JOIN areas a ON dpur.req_area_id = a.area_id
        WHERE dpur.request_status = 'pending'
        ORDER BY dpur.submitted_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending = [];
    set_flash('error', 'Error loading pending requests');
}

// Fetch approved requests
try {
    $approved = $apdo->query("
        SELECT dpur.request_id, dpur.reviewed_at,
               u.full_name, dpur.request_status
        FROM doctor_profile_update_requests dpur
        JOIN doctor_profiles dp ON dpur.doctor_id = dp.doctor_id
        JOIN users u ON dp.doctor_id = u.user_id
        WHERE dpur.request_status IN ('approved', 'rejected')
        ORDER BY dpur.reviewed_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $approved = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Update Requests - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .request-card { border:1px solid #ddd; padding:15px; margin-bottom:15px; border-radius:4px; }
        .request-card strong { display:block; margin-bottom:8px; }
        .change-item { margin:5px 0; padding:5px; background:#f5f5f5; border-left:3px solid #2196F3; padding-left:10px; }
    </style>
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
    <h2>Profile Update Requests</h2>
    
    <?php echo display_flash(); ?>
    
    <!-- Pending Requests -->
    <div class="dashboard-section">
        <h3>Pending Review (<?= count($pending) ?>)</h3>
        
        <?php if (!empty($pending)): ?>
            <?php foreach ($pending as $req): ?>
                <div class="request-card">
                    <strong><?= htmlspecialchars($req['full_name']) ?></strong>
                    <small style="color:#666;">Email: <?= htmlspecialchars($req['email']) ?> | Phone: <?= htmlspecialchars($req['phone']) ?></small>
                    <small style="color:#999; display:block; margin-top:5px;">Submitted: <?= date('d-M-Y h:i A', strtotime($req['submitted_at'])) ?></small>
                    
                    <div style="margin-top:10px;">
                        <strong style="font-size:13px;">Requested Changes:</strong>
                        <?php if (!empty($req['req_biography'])): ?>
                            <div class="change-item"><strong>Biography:</strong> <?= htmlspecialchars($req['req_biography']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($req['req_consultation_fee'])): ?>
                            <div class="change-item"><strong>Consultation Fee:</strong> ৳<?= number_format($req['req_consultation_fee'], 2) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($req['req_qualification'])): ?>
                            <div class="change-item"><strong>Qualification:</strong> <?= htmlspecialchars($req['req_qualification']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($req['req_license_no'])): ?>
                            <div class="change-item"><strong>License No:</strong> <?= htmlspecialchars($req['req_license_no']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($req['req_experience_years'])): ?>
                            <div class="change-item"><strong>Experience:</strong> <?= $req['req_experience_years'] ?> years</div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top:12px; display:flex; gap:8px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-small btn-success" onclick="return confirm('Approve this update?')">Approve</button>
                        </form>
                        <button type="button" class="btn btn-small btn-danger" onclick="document.getElementById('reject-form-<?= $req['request_id'] ?>').style.display='block'">Reject</button>
                    </div>
                    
                    <!-- Rejection form -->
                    <div id="reject-form-<?= $req['request_id'] ?>" style="display:none; margin-top:10px; background:#fff3cd; padding:10px; border-radius:4px;">
                        <form method="POST">
                            <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <label>Reason for rejection:</label>
                            <textarea name="rejection_reason" required rows="3" style="width:100%; padding:5px; border:1px solid #ddd; border-radius:3px;"></textarea>
                            <div style="margin-top:8px;">
                                <button type="submit" class="btn btn-small btn-danger">Confirm Rejection</button>
                                <button type="button" class="btn btn-small" onclick="document.getElementById('reject-form-<?= $req['request_id'] ?>').style.display='none'">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No pending profile update requests.</div>
        <?php endif; ?>
    </div>
    
    <!-- Reviewed Requests -->
    <div class="dashboard-section" style="margin-top:24px;">
        <h3>Reviewed Requests (<?= count($approved) ?>)</h3>
        
        <?php if (!empty($approved)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Reviewed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars($req['full_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($req['request_status']) ?>">
                                    <?= ucfirst($req['request_status']) ?>
                                </span>
                            </td>
                            <td><?= date('d-M-Y h:i A', strtotime($req['reviewed_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No reviewed requests yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
