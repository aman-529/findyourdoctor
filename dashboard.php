<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();

$user_id = get_current_user_id();
$role    = get_current_user_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Doctor Appointment System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span><?= htmlspecialchars($_SESSION['user_name']) ?> (<?= ucfirst($role) ?>)</span>
        <?php if ($role === 'patient'): ?>
            <a href="search.php">Find Doctors</a>
        <?php endif; ?>
        <?php if ($role === 'doctor'): ?>
            <a href="doctor_slots.php">Create Slots</a>
            <a href="doctor_profile.php">My Profile</a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <a href="doctors.php">Manage Doctors</a>
            <a href="reviews.php">Reviews</a>
            <a href="update_requests.php">Update Requests</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <?php echo display_flash(); ?>

    <?php /* ==================== PATIENT ==================== */ ?>
    <?php if ($role === 'patient'): ?>
        <h2>My Dashboard</h2>
        
        <?php
       
        try {
        
            $apdo = get_admin_pdo();
            $stmt = $apdo->prepare("CALL sp_get_patient_dashboard(?)");
            $stmt->execute([$user_id]);
            $bookings = $stmt->fetchAll();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $bookings = [];
            echo '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px; background: #e3f2fd; padding: 15px; border-radius: 4px; border-left: 4px solid #2196F3;">
                <div style="font-size: 12px; color: #666;">Upcoming Appointments</div>
                <div style="font-size: 24px; font-weight: bold; color: #2196F3;">
                    <?php 
                    $upcoming = 0;
                    foreach ($bookings as $b) {
                        if ($b['booking_status'] === 'confirmed' && strtotime($b['appointment_date']) >= time()) $upcoming++;
                    }
                    echo $upcoming;
                    ?>
                </div>
            </div>
            <div style="flex: 1; min-width: 200px; background: #f3e5f5; padding: 15px; border-radius: 4px; border-left: 4px solid #9c27b0;">
                <div style="font-size: 12px; color: #666;">Completed Appointments</div>
                <div style="font-size: 24px; font-weight: bold; color: #9c27b0;">
                    <?php 
                    $completed = 0;
                    foreach ($bookings as $b) {
                        if ($b['booking_status'] === 'completed') $completed++;
                    }
                    echo $completed;
                    ?>
                </div>
            </div>
            <div style="flex: 1; min-width: 200px; background: #e8f5e9; padding: 15px; border-radius: 4px; border-left: 4px solid #4caf50;">
                <div style="font-size: 12px; color: #666;">Total Spent</div>
                <div style="font-size: 24px; font-weight: bold; color: #4caf50;">
                    ৳<?php 
                    $total = 0;
                    foreach ($bookings as $b) {
                        if ($b['payment_status'] === 'paid') $total += (float)$b['visit_fee'];
                    }
                    echo number_format($total, 2);
                    ?>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h3>My Appointments</h3>
            ?>
            <?php if (!empty($bookings)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th><th>Doctor</th><th>Specialization</th>
                            <th>Date &amp; Time</th><th>Fee</th>
                            <th>Booking Status</th><th>Payment</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?= (int)$b['booking_serial_no'] ?></td>
                            <td><?= htmlspecialchars($b['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($b['specialization_name']) ?></td>
                            <td><?= date('d-M-Y h:i A', strtotime($b['appointment_date'].' '.$b['start_time'])) ?></td>
                            <td>&#2547;<?= number_format($b['visit_fee'],2) ?></td>
                            <td><span class="badge badge-<?= strtolower(str_replace('_','-',$b['booking_status'])) ?>">
                                <?= ucfirst(str_replace('_',' ',$b['booking_status'])) ?>
                            </span></td>
                            <td>
                                <?php if ($b['payment_status']): ?>
                                    <span class="badge badge-<?= strtolower($b['payment_status']) ?>">
                                        <?= ucfirst($b['payment_status']) ?>
                                    </span>
                                    <?php if (!empty($b['payment_method'])): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 3px;">via <?= htmlspecialchars($b['payment_method']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-pending">Not Paid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['booking_status'] === 'pending_payment'): ?>
                                    <a href="payment.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-small btn-primary">Pay Now</a>
                                <?php endif; ?>
                                <?php if (in_array($b['booking_status'], ['pending_payment','confirmed'])): ?>
                                    <a href="cancel.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-small btn-danger"
                                       onclick="return confirm('Cancel this booking?')">Cancel</a>
                                <?php endif; ?>
                                <?php if ($b['booking_status'] === 'completed'): ?>
                                    <a href="review.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-small">Review</a>
                                    <a href="search.php" class="btn btn-small btn-primary">Rebook</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No appointments yet. <a href="search.php">Book your first appointment</a></div>
            <?php endif; ?>
        </div>

    <?php /* ==================== DOCTOR ==================== */ ?>
    <?php elseif ($role === 'doctor'): ?>
        <h2>Doctor Dashboard</h2>

        <?php
        // sp_get_doctor_dashboard returns 3 result sets:
        // RS1: upcoming_slots, today_bookings, total_earnings
        // RS2: today's slot rows (slot_id, appointment_date, start_time, end_time, booked_patient_count, max_patient_count, slot_status)
        // RS3: pending_updates count row
        try {
            $apdo = get_admin_pdo();
            $stmt = $apdo->prepare("CALL sp_get_doctor_dashboard(?)");
            $stmt->execute([$user_id]);

            $stats          = $stmt->fetch();        // RS1
            $stmt->nextRowset();
            $today_slots    = $stmt->fetchAll();     // RS2
            $stmt->nextRowset();
            $upd_row        = $stmt->fetch();        // RS3
            $pending_upd    = (int)($upd_row['pending_updates'] ?? 0);
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $stats       = ['upcoming_slots'=>0,'today_bookings'=>0,'total_earnings'=>0];
            $today_slots = [];
            $pending_upd = 0;
            echo '<div class="alert alert-error">Dashboard error: '.htmlspecialchars($e->getMessage()).'</div>';
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Upcoming Slots</div>
                <div class="stat-value"><?= (int)($stats['upcoming_slots'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today's Bookings</div>
                <div class="stat-value"><?= (int)($stats['today_bookings'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value">&#2547;<?= number_format((float)($stats['total_earnings']??0),2) ?></div>
            </div>
        </div>

        <?php if ($pending_upd > 0): ?>
            <div class="alert alert-warning" style="margin-top:14px;">
                You have <strong><?= $pending_upd ?></strong> pending profile update request(s) awaiting admin review.
            </div>
        <?php endif; ?>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Today's Slots</h3>
            <?php if (!empty($today_slots)): ?>
                <table class="table">
                    <thead><tr><th>Start</th><th>End</th><th>Booked / Max</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($today_slots as $sl): ?>
                        <tr>
                            <td><?= date('h:i A', strtotime($sl['start_time'])) ?></td>
                            <td><?= date('h:i A', strtotime($sl['end_time'])) ?></td>
                            <td><?= (int)$sl['booked_patient_count'] ?> / <?= (int)$sl['max_patient_count'] ?></td>
                            <td><span class="badge badge-<?= strtolower($sl['slot_status']) ?>"><?= ucfirst($sl['slot_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No slots for today.</div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Quick Actions</h3>
            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                <a href="doctor_slots.php" class="btn btn-primary">Create Appointment Slots</a>
                <a href="doctor_profile.php" class="btn btn-primary">Update Profile</a>
            </div>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>All Upcoming Slots</h3>
            <?php
            try {
                $apdo = get_admin_pdo();
                $ustmt = $apdo->prepare("
                    SELECT slot_id, appointment_date, start_time, end_time, 
                           booked_patient_count, max_patient_count, slot_status
                    FROM appointment_slots
                    WHERE doctor_id = ? AND appointment_date >= CURDATE()
                    ORDER BY appointment_date ASC, start_time ASC
                ");
                $ustmt->execute([$user_id]);
                $all_slots = $ustmt->fetchAll();
            } catch (PDOException $e) {
                $all_slots = [];
            }
            ?>
            <?php if (!empty($all_slots)): ?>
                <table class="table">
                    <thead>
                        <tr><th>Date</th><th>Time</th><th>Booked / Max</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all_slots as $sl): ?>
                        <tr>
                            <td><?= date('d-M-Y', strtotime($sl['appointment_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($sl['start_time'])) ?> - <?= date('h:i A', strtotime($sl['end_time'])) ?></td>
                            <td><?= (int)$sl['booked_patient_count'] ?> / <?= (int)$sl['max_patient_count'] ?></td>
                            <td><span class="badge badge-<?= strtolower($sl['slot_status']) ?>"><?= ucfirst($sl['slot_status']) ?></span></td>
                            <td>
                                <?php if ($sl['booked_patient_count'] == 0): ?>
                                    <a href="delete_slot.php?slot_id=<?= $sl['slot_id'] ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Cancel this slot?')">Cancel</a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">Booked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No upcoming slots. <a href="doctor_slots.php">Create new slots</a></div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Confirmed Bookings — Mark as Completed</h3>
            <?php
            try {
                $apdo = get_admin_pdo();
                $bstmt = $apdo->prepare("
                    SELECT ab.booking_id, ab.booking_serial_no, ab.symptom_note,
                           pu.full_name AS patient_name,
                           asl.appointment_date, asl.start_time
                    FROM appointment_bookings ab
                    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
                    JOIN users pu ON ab.patient_id = pu.user_id
                    WHERE asl.doctor_id = ? AND ab.booking_status = 'confirmed'
                    ORDER BY asl.appointment_date, asl.start_time
                ");
                $bstmt->execute([$user_id]);
                $confirmed = $bstmt->fetchAll();
            } catch (PDOException $e) {
                $confirmed = [];
            }
            ?>
            <?php if (!empty($confirmed)): ?>
                <table class="table">
                    <thead><tr><th>#</th><th>Patient</th><th>Date &amp; Time</th><th>Symptoms</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($confirmed as $cb): ?>
                        <tr>
                            <td><?= (int)$cb['booking_serial_no'] ?></td>
                            <td><?= htmlspecialchars($cb['patient_name']) ?></td>
                            <td><?= date('d-M-Y h:i A', strtotime($cb['appointment_date'].' '.$cb['start_time'])) ?></td>
                            <td><?= htmlspecialchars(mb_substr($cb['symptom_note']??'',0,50)) ?>...</td>
                            <td><a href="complete_booking.php?booking_id=<?= $cb['booking_id'] ?>" class="btn btn-small btn-success">Mark Done</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No confirmed bookings right now.</div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Payment History</h3>
            <?php
            try {
                $apdo = get_admin_pdo();
                $pstmt = $apdo->prepare("
                    SELECT p.payment_id, p.amount, p.payment_method, p.paid_at,
                           pu.full_name AS patient_name,
                           asl.appointment_date, asl.start_time
                    FROM payments p
                    JOIN appointment_bookings ab ON p.booking_id = ab.booking_id
                    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
                    JOIN users pu ON ab.patient_id = pu.user_id
                    WHERE asl.doctor_id = ? AND p.payment_status = 'paid'
                    ORDER BY p.paid_at DESC LIMIT 20
                ");
                $pstmt->execute([$user_id]);
                $payments = $pstmt->fetchAll();
            } catch (PDOException $e) {
                $payments = [];
            }
            ?>
            <?php if (!empty($payments)): ?>
                <table class="table">
                    <thead>
                        <tr><th>Patient Name</th><th>Appointment Date</th><th>Payment Method</th><th>Amount</th><th>Payment Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['patient_name']) ?></td>
                            <td><?= date('d-M-Y h:i A', strtotime($p['appointment_date'].' '.$p['start_time'])) ?></td>
                            <td><?= htmlspecialchars($p['payment_method']) ?></td>
                            <td style="font-weight: bold;">&#2547;<?= number_format((float)$p['amount'], 2) ?></td>
                            <td><?= date('d-M-Y h:i A', strtotime($p['paid_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No payment history yet.</div>
            <?php endif; ?>
        </div>

    <?php /* ==================== ADMIN ==================== */ ?>
    <?php elseif ($role === 'admin'): ?>
        <h2>Admin Dashboard</h2>

        <?php
        try {
            $apdo = get_admin_pdo();
            $s = [
                'users'         => $apdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'],
                'doctors'       => $apdo->query("SELECT COUNT(*) c FROM doctor_profiles WHERE verification_status='approved'")->fetch()['c'],
                'pending_doc'   => $apdo->query("SELECT COUNT(*) c FROM doctor_profiles WHERE verification_status='pending'")->fetch()['c'],
                'bookings'      => $apdo->query("SELECT COUNT(*) c FROM appointment_bookings")->fetch()['c'],
                'completed'     => $apdo->query("SELECT COUNT(*) c FROM appointment_bookings WHERE booking_status='completed'")->fetch()['c'],
                'pending_rev'   => $apdo->query("SELECT COUNT(*) c FROM reviews WHERE review_status='pending'")->fetch()['c'],
                'pending_upd'   => $apdo->query("SELECT COUNT(*) c FROM doctor_profile_update_requests WHERE request_status='pending'")->fetch()['c'],
                'revenue'       => $apdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='paid'")->fetch()['s'],
            ];
        } catch (PDOException $e) {
            $s = array_fill_keys(['users','doctors','pending_doc','bookings','completed','pending_rev','pending_upd','revenue'], 0);
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Users</div><div class="stat-value"><?= $s['users'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Approved Doctors</div><div class="stat-value"><?= $s['doctors'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending Doctors</div><div class="stat-value"><?= $s['pending_doc'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Bookings</div><div class="stat-value"><?= $s['bookings'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Completed</div><div class="stat-value"><?= $s['completed'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending Reviews</div><div class="stat-value"><?= $s['pending_rev'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Profile Requests</div><div class="stat-value"><?= $s['pending_upd'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value">&#2547;<?= number_format((float)$s['revenue'],2) ?></div></div>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Quick Actions</h3>
            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                <a href="doctors.php" class="btn btn-primary">Manage Doctors <?php if($s['pending_doc']>0): ?><span style="background:#e53935;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;"><?= $s['pending_doc'] ?></span><?php endif; ?></a>
                <a href="update_requests.php" class="btn btn-primary">Profile Requests <?php if($s['pending_upd']>0): ?><span style="background:#e53935;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;"><?= $s['pending_upd'] ?></span><?php endif; ?></a>
                <a href="reviews.php" class="btn btn-primary">Moderate Reviews <?php if($s['pending_rev']>0): ?><span style="background:#e53935;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;"><?= $s['pending_rev'] ?></span><?php endif; ?></a>
            </div>
        </div>

        <div class="dashboard-section" style="margin-top:24px;">
            <h3>Recent Bookings</h3>
            <?php
            try {
                $apdo = get_admin_pdo();
                $recent = $apdo->query("
                    SELECT ab.booking_id, ab.booking_status, ab.booked_at,
                           pu.full_name AS patient_name,
                           du.full_name AS doctor_name,
                           s.specialization_name
                    FROM appointment_bookings ab
                    JOIN users pu ON ab.patient_id = pu.user_id
                    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
                    JOIN doctor_profiles dp ON asl.doctor_id = dp.doctor_id
                    JOIN users du ON dp.doctor_id = du.user_id
                    JOIN specializations s ON dp.specialization_id = s.specialization_id
                    ORDER BY ab.booked_at DESC LIMIT 10
                ")->fetchAll();
            } catch (PDOException $e) { $recent = []; }
            ?>
            <?php if (!empty($recent)): ?>
                <table class="table">
                    <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Specialization</th><th>Booked At</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td>#<?= $r['booking_id'] ?></td>
                            <td><?= htmlspecialchars($r['patient_name']) ?></td>
                            <td><?= htmlspecialchars($r['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($r['specialization_name']) ?></td>
                            <td><?= date('d-M-Y h:i A', strtotime($r['booked_at'])) ?></td>
                            <td><span class="badge badge-<?= strtolower(str_replace('_','-',$r['booking_status'])) ?>"><?= ucfirst(str_replace('_',' ',$r['booking_status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No bookings yet.</div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>
</body>
</html>
