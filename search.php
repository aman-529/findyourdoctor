<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

require_login();

// Get filter parameters from form
$spec_id = isset($_GET['spec_id']) && !empty($_GET['spec_id']) ? (int)$_GET['spec_id'] : null;
$area_id = isset($_GET['area_id']) && !empty($_GET['area_id']) ? (int)$_GET['area_id'] : null;
$symptom_search = isset($_GET['symptom']) && !empty($_GET['symptom']) ? trim($_GET['symptom']) : null;

// Call stored procedure: sp_search_doctors(spec_id, area_id, symptom_name)
try {
    $apdo = get_admin_pdo();
    $stmt = $apdo->prepare("CALL sp_search_doctors(?, ?, ?)");
    $stmt->execute([$spec_id, $area_id, $symptom_search]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} catch (PDOException $e) {
    $doctors = [];
    set_flash('error', 'Error loading doctors: ' . htmlspecialchars($e->getMessage()));
}

// Load reference data for filter dropdowns
$apdo = get_admin_pdo();
$specializations = $apdo->query("SELECT specialization_id, specialization_name FROM specializations ORDER BY specialization_name")->fetchAll(PDO::FETCH_ASSOC);
$areas = $apdo->query("SELECT area_id, area_name, city FROM areas ORDER BY city, area_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search Doctors - Doctor Appointment</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal.active {
            display: block;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2196F3;
            padding-bottom: 15px;
        }
        .modal-header h2 {
            margin: 0;
            color: #2196F3;
        }
        .close-btn {
            cursor: pointer;
            font-size: 28px;
            font-weight: bold;
            color: #999;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-btn:hover {
            color: #000;
        }
        .doctor-detail-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .doctor-detail-item:last-child {
            border-bottom: none;
        }
        .doctor-detail-label {
            font-weight: bold;
            color: #2196F3;
            display: block;
            margin-bottom: 5px;
        }
        .doctor-detail-value {
            color: #666;
            line-height: 1.6;
        }
        .reviews-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        .review-card {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
        }
        .review-rating {
            color: #ffc107;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .review-text {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .review-author {
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="index.php">Home</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Find Doctors</h2>
    
    <?php echo display_flash(); ?>
    
    <!-- Filter Form -->
    <div class="filter-box">
        <h3>Filter Doctors</h3>
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label>Specialization:</label>
                <select name="spec_id">
                    <option value="">-- All Specializations --</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo $spec['specialization_id']; ?>" 
                            <?php echo $spec_id == $spec['specialization_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Working Area:</label>
                <select name="area_id">
                    <option value="">-- All Areas --</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?php echo $area['area_id']; ?>" 
                            <?php echo $area_id == $area['area_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area['area_name'] . ' - ' . $area['city']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Search by Symptom:</label>
                <input type="text" name="symptom" placeholder="e.g., Fever, Headache" value="<?php echo htmlspecialchars($symptom_search); ?>">
            </div>
            
            <button type="submit" class="btn">Search</button>
        </form>
    </div>
    
    <!-- Results -->
    <h3>Available Doctors</h3>
    
    <?php if (!empty($doctors)): ?>
        <div class="doctors-grid">
            <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                        <span class="badge badge-specialization"><?php echo htmlspecialchars($doctor['specialization_name']); ?></span>
                    </div>
                    
                    <div class="card-body">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($doctor['area_name'] . ', ' . $doctor['city']); ?></p>
                        
                        <?php if (!empty($doctor['chamber_name']) || !empty($doctor['chamber_address'])): ?>
                            <p><strong>Chamber:</strong> <br>
                                <?php if (!empty($doctor['chamber_name'])): ?>
                                    <?php echo htmlspecialchars($doctor['chamber_name']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($doctor['chamber_address'])): ?>
                                    <span style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars($doctor['chamber_address']); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <p><strong>Consultation Fee:</strong> <span class="fee">৳<?php echo htmlspecialchars($doctor['visit_fee']); ?></span></p>
                        
                        <?php if (!empty($doctor['avg_rating'])): ?>
                            <p><strong>Rating:</strong> 
                                <span class="rating">
                                    <?php 
                                    $rating = round($doctor['avg_rating'], 1);
                                    echo '★ ' . $rating . '/5 (' . (int)$doctor['review_count'] . ' reviews)';
                                    ?>
                                </span>
                            </p>
                        <?php else: ?>
                            <p><strong>Rating:</strong> <span class="no-rating">No reviews yet</span></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <?php if (has_role('patient')): ?>
                            <button class="btn btn-small" onclick="openDoctorModal(<?php echo $doctor['doctor_id']; ?>, '<?php echo htmlspecialchars(addslashes($doctor['full_name'])); ?>')">More Info</button>
                            <a href="booking.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-small btn-primary">Book Appointment</a>
                        <?php else: ?>
                            <p style="text-align: center; color: #666;">Please login as patient to book</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            No doctors found matching your criteria. <a href="search.php">Clear filters</a>
        </div>
    <?php endif; ?>
</div>

<!-- Doctor Info Modal -->
<div id="doctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalDoctorName">Doctor Name</h2>
            <button class="close-btn" onclick="closeDoctorModal()">×</button>
        </div>
        <div id="modalContent">
            <p style="text-align: center; color: #999;">Loading...</p>
        </div>
    </div>
</div>

<script>
function openDoctorModal(doctorId, doctorName) {
    const modal = document.getElementById('doctorModal');
    const content = document.getElementById('modalContent');
    const titleEl = document.getElementById('modalDoctorName');
    
    // Set title
    titleEl.textContent = doctorName;
    
    // Show loading state
    content.innerHTML = '<p style="text-align: center; color: #999;">Loading doctor information...</p>';
    modal.classList.add('active');
    
    // Fetch doctor details
    fetch('get_doctor_details.php?doctor_id=' + doctorId)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<p style="color: #e74c3c;">Error loading doctor information</p>';
            console.error('Error:', error);
        });
}

function closeDoctorModal() {
    document.getElementById('doctorModal').classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('doctorModal');
    if (event.target == modal) {
        modal.classList.remove('active');
    }
}
</script>
</body>
</html>
