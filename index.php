<?php
include 'config.php';
include 'flash.php';
include 'auth.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doctor Appointment System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>🏥 Find My Doctor</h1>
    <div class="nav-right">
        <?php if (is_authenticated()): ?>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?> (<?php echo ucfirst(get_current_user_role()); ?>)</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <h2>Welcome to Find My Doctor</h2>
    
    <?php echo display_flash(); ?>
    
    <?php if (is_authenticated()): ?>
        
        <div style="text-align: center; margin: 40px 0;">
            <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
            <p>You are logged in as <strong><?php echo ucfirst(get_current_user_role()); ?></strong></p>
            
            <?php if (get_current_user_role() === 'patient'): ?>
                <p style="margin-top: 20px;">
                    <a href="search.php" class="btn" style="display: inline-block; width: auto;">Find and Book Doctors</a>
                </p>
                <p style="margin-top: 10px;">
                    <a href="dashboard.php" class="btn" style="display: inline-block; width: auto; background-color: #6c757d;">View My Bookings</a>
                </p>
            <?php elseif (get_current_user_role() === 'doctor'): ?>
                <p style="margin-top: 20px;">
                    <a href="dashboard.php" class="btn" style="display: inline-block; width: auto;">View My Dashboard</a>
                </p>
            <?php elseif (get_current_user_role() === 'admin'): ?>
                <p style="margin-top: 20px;">
                    <a href="dashboard.php" class="btn" style="display: inline-block; width: auto;">Admin Dashboard</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        
        <div style="text-align: center; margin: 40px 0;">
            <h3>Find and Book Your Doctor Online</h3>
            <p style="font-size: 16px; color: #666; margin: 20px 0;">
                Schedule appointments with qualified doctors in your area. Get expert medical care at your convenience.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 40px 0;">
                <div style="padding: 20px; background: #f9fafb; border-radius: 8px; border-left: 4px solid #1a73e8;">
                    <h4>Register Account</h4>
                    <p>Create your account as a patient or doctor</p>
                    <a href="register.php" class="btn" style="display: inline-block; width: auto; margin-top: 10px;">Register Now</a>
                </div>
                
                <div style="padding: 20px; background: #f9fafb; border-radius: 8px; border-left: 4px solid #1a73e8;">
                    <h4>Login Account</h4>
                    <p>Login with your existing credentials</p>
                    <a href="login.php" class="btn" style="display: inline-block; width: auto; margin-top: 10px;">Login</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Features Section -->
    <div style="margin-top: 50px; padding: 30px; background: #f9fafb; border-radius: 8px;">
        <h3>Key Features</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
            <div>
                <h5>Easy Scheduling</h5>
                <p>Book appointments with doctors at convenient times</p>
            </div>
            <div>
                <h5>Secure Payments</h5>
                <p>Multiple payment options with secure processing</p>
            </div>
            <div>
                <h5>Doctor Ratings</h5>
                <p>Read reviews and ratings from other patients</p>
            </div>
            <div>
                <h5>Patient Profiles</h5>
                <p>Manage your medical information securely</p>
            </div>
            <div>
                <h5>Doctor Management</h5>
                <p>Doctors can manage their schedules and appointments</p>
            </div>
            <div>
                <h5>Admin Control</h5>
                <p>Administrators manage the entire system</p>
            </div>
        </div>
    </div>
</div>

<footer style="text-align: center; padding: 20px; color: #999; margin-top: 50px; border-top: 1px solid #ddd;">
    <p>&copy; 2026 Find My Doctor. All rights reserved.</p>
</footer>
</body>
</html>
