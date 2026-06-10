<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();
require_role('admin');

$apdo = get_admin_pdo();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    $admin_id = get_current_user_id();
    
    try {
        if ($action === 'approve') {
            $stmt = $apdo->prepare("CALL sp_approve_doctor_profile(?, ?)");
            $stmt->execute([$doctor_id, $admin_id]);
            $stmt->closeCursor();
            set_flash('success', 'Doctor profile approved successfully!');
        } elseif ($action === 'reject') {
            $stmt = $apdo->prepare("CALL sp_reject_doctor_profile(?, ?)");
            $stmt->execute([$doctor_id, $admin_id]);
            $stmt->closeCursor();
            set_flash('success', 'Doctor profile rejected.');
        }
        header('Location: doctors.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Action failed: ' . htmlspecialchars($e->getMessage()));
    }
}

// Fetch pending doctors
try {
    $pending = $apdo->query("
        SELECT dp.doctor_id, u.full_name, u.email, u.phone,
               s.specialization_name, a.area_name, a.city,
               dp.license_no, dp.qualification, dp.experience_years,
               dp.created_at
        FROM doctor_profiles dp
        JOIN users u ON dp.doctor_id = u.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN areas a ON dp.area_id = a.area_id
        WHERE dp.verification_status = 'pending'
        ORDER BY dp.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending = [];
    set_flash('error', 'Error loading pending doctors');
}

// Fetch approved doctors
try {
    $approved = $apdo->query("
        SELECT dp.doctor_id, u.full_name, u.email, u.phone,
               s.specialization_name, a.area_name,
               dp.license_no, dp.created_at
        FROM doctor_profiles dp
        JOIN users u ON dp.doctor_id = u.user_id
        JOIN specializations s ON dp.specialization_id = s.specialization_id
        JOIN areas a ON dp.area_id = a.area_id
        WHERE dp.verification_status = 'approved'
        ORDER BY dp.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $approved = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Doctors - Admin</title>
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
    <h2>Manage Doctors</h2>
    
    <?php echo display_flash(); ?>
    
    <!-- Pending Doctors -->
    <div class="dashboard-section">
        <h3>Pending Approval (<?= count($pending) ?>)</h3>
        
        <?php if (!empty($pending)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>Area</th>
                        <th>License</th>
                        <th>Qualification</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['full_name']) ?></td>
                            <td><?= htmlspecialchars($doc['email']) ?></td>
                            <td><?= htmlspecialchars($doc['phone']) ?></td>
                            <td><?= htmlspecialchars($doc['specialization_name']) ?></td>
                            <td><?= htmlspecialchars($doc['area_name'] . ', ' . $doc['city']) ?></td>
                            <td><?= htmlspecialchars($doc['license_no']) ?></td>
                            <td><?= htmlspecialchars($doc['qualification']) ?></td>
                            <td><?= date('d-M-Y', strtotime($doc['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="doctor_id" value="<?= $doc['doctor_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-small btn-success" onclick="return confirm('Approve this doctor?')">Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="doctor_id" value="<?= $doc['doctor_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Reject this doctor?')">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No pending doctor approvals.</div>
        <?php endif; ?>
    </div>
    
    <!-- Approved Doctors -->
    <div class="dashboard-section" style="margin-top:24px;">
        <h3>Approved Doctors (<?= count($approved) ?>)</h3>
        
        <?php if (!empty($approved)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>Area</th>
                        <th>License</th>
                        <th>Approved On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['full_name']) ?></td>
                            <td><?= htmlspecialchars($doc['email']) ?></td>
                            <td><?= htmlspecialchars($doc['phone']) ?></td>
                            <td><?= htmlspecialchars($doc['specialization_name']) ?></td>
                            <td><?= htmlspecialchars($doc['area_name']) ?></td>
                            <td><?= htmlspecialchars($doc['license_no']) ?></td>
                            <td><?= date('d-M-Y', strtotime($doc['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No approved doctors yet.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
