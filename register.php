<?php
include 'config.php';
include 'flash.php';
include 'auth.php';

if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$errors    = [];
$old_input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $password      = trim($_POST['password'] ?? '');
    $confirm_pwd   = trim($_POST['confirm_password'] ?? '');
    $gender        = $_POST['gender'] ?? '';
    $dob           = $_POST['date_of_birth'] ?? '';
    $role          = $_POST['role'] ?? 'patient';
    $spec_id       = !empty($_POST['specialization_id']) ? (int)$_POST['specialization_id'] : null;
    $area_id       = !empty($_POST['area_id'])           ? (int)$_POST['area_id']           : null;
    $license_no    = trim($_POST['license_no'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $old_input     = $_POST;

    // Validation
    if (empty($full_name))                                              $errors['full_name']  = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))   $errors['email']       = 'Valid email is required.';
    if (empty($phone))                                                  $errors['phone']       = 'Phone is required.';
    if (empty($password))                                               $errors['password']    = 'Password is required.';
    if ($password !== $confirm_pwd)                                     $errors['confirm_password'] = 'Passwords do not match.';
    if (!in_array($gender, ['male','female','other']))                  $errors['gender']      = 'Please select gender.';
    if (empty($dob))                                                    $errors['date_of_birth'] = 'Date of birth is required.';
    if (!in_array($role, ['patient','doctor']))                         $errors['role']        = 'Invalid role.';

    if ($role === 'doctor') {
        if (empty($spec_id))    $errors['specialization_id'] = 'Specialization required.';
        if (empty($area_id))    $errors['area_id']           = 'Area required.';
        if (empty($license_no)) $errors['license_no']        = 'License number required.';
        if (empty($qualification)) $errors['qualification']  = 'Qualification required.';
    }

    // Duplicate checks using admin PDO
    if (empty($errors)) {
        $apdo = get_admin_pdo();

        $chk = $apdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors['email'] = 'Email already registered.';

        $chk = $apdo->prepare("SELECT user_id FROM users WHERE phone = ?");
        $chk->execute([$phone]);
        if ($chk->fetch()) $errors['phone'] = 'Phone already registered.';

        if ($role === 'doctor' && empty($errors['license_no'])) {
            $chk = $apdo->prepare("SELECT doctor_id FROM doctor_profiles WHERE license_no = ?");
            $chk->execute([$license_no]);
            if ($chk->fetch()) $errors['license_no'] = 'License number already registered.';
        }
    }

    // Register via stored procedure
    if (empty($errors)) {
        try {
            $apdo = get_admin_pdo();
            // sp_register_user has OUT param — use @variable syntax
            $apdo->exec("SET @new_uid = 0");
            $stmt = $apdo->prepare(
                "CALL sp_register_user(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @new_uid)"
            );
            // Password stored as plain text (no hashing)
            $stmt->execute([
                $role, $full_name, $email, $phone,
                $password,   // stored directly as plain text
                $gender, $dob, $spec_id, $area_id, $license_no, $qualification
            ]);
            $stmt->closeCursor();

            $row     = $apdo->query("SELECT @new_uid AS uid")->fetch();
            $new_uid = (int)($row['uid'] ?? 0);

            if ($new_uid > 0) {
                set_flash('success', 'Registration successful! Please log in.');
                header('Location: login.php');
                exit;
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'unique') !== false) {
                if   (stripos($msg, 'email')   !== false) $errors['email']      = 'Email already registered.';
                elseif (stripos($msg, 'phone') !== false) $errors['phone']      = 'Phone already registered.';
                elseif (stripos($msg, 'license')!== false)$errors['license_no'] = 'License already registered.';
                else $errors['general'] = 'A duplicate entry was found.';
            } else {
                $errors['general'] = 'Registration error: ' . htmlspecialchars($msg);
            }
        }
    }
}

// Load dropdowns via admin PDO (guest has no role yet)
$apdo = get_admin_pdo();
$specializations = $apdo->query("SELECT specialization_id, specialization_name FROM specializations ORDER BY specialization_name")->fetchAll();
$areas           = $apdo->query("SELECT area_id, area_name, city FROM areas ORDER BY city, area_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Doctor Appointment System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h1>Doctor Appointment System</h1>
    <div class="nav-right"><a href="login.php">Login</a></div>
</div>

<div class="container">
    <div class="form-box">
        <h2>Create Account</h2>

        <?php echo display_flash(); ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label>Account Type: <span class="required">*</span></label>
                <select name="role" id="role-select" onchange="toggleDoctorFields()">
                    <option value="patient" <?= ($old_input['role']??'patient')==='patient'?'selected':'' ?>>Patient</option>
                    <option value="doctor"  <?= ($old_input['role']??'')==='doctor' ?'selected':'' ?>>Doctor</option>
                </select>
            </div>

            <div class="form-group">
                <label>Full Name: <span class="required">*</span></label>
                <input type="text" name="full_name" maxlength="100"
                       value="<?= htmlspecialchars($old_input['full_name']??'') ?>" required>
                <?php if (!empty($errors['full_name'])): ?><span class="error-text"><?= $errors['full_name'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Email: <span class="required">*</span></label>
                <input type="email" name="email" maxlength="150"
                       value="<?= htmlspecialchars($old_input['email']??'') ?>" required>
                <?php if (!empty($errors['email'])): ?><span class="error-text"><?= $errors['email'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Phone: <span class="required">*</span></label>
                <input type="text" name="phone" maxlength="20"
                       value="<?= htmlspecialchars($old_input['phone']??'') ?>" required>
                <?php if (!empty($errors['phone'])): ?><span class="error-text"><?= $errors['phone'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Gender: <span class="required">*</span></label>
                <select name="gender" required>
                    <option value="">-- Select --</option>
                    <option value="male"   <?= ($old_input['gender']??'')==='male'   ?'selected':'' ?>>Male</option>
                    <option value="female" <?= ($old_input['gender']??'')==='female' ?'selected':'' ?>>Female</option>
                    <option value="other"  <?= ($old_input['gender']??'')==='other'  ?'selected':'' ?>>Other</option>
                </select>
                <?php if (!empty($errors['gender'])): ?><span class="error-text"><?= $errors['gender'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Date of Birth: <span class="required">*</span></label>
                <input type="date" name="date_of_birth"
                       value="<?= htmlspecialchars($old_input['date_of_birth']??'') ?>" required>
                <?php if (!empty($errors['date_of_birth'])): ?><span class="error-text"><?= $errors['date_of_birth'] ?></span><?php endif; ?>
            </div>

            <!-- Doctor-only fields -->
            <div id="doctor-fields" style="display:none; border:1px solid #ccc; padding:14px; border-radius:4px; margin-bottom:12px;">
                <h3 style="margin-top:0;">Doctor Information</h3>

                <div class="form-group">
                    <label>Specialization: <span class="required">*</span></label>
                    <select name="specialization_id">
                        <option value="">-- Select --</option>
                        <?php foreach ($specializations as $s): ?>
                            <option value="<?= $s['specialization_id'] ?>"
                                <?= ($old_input['specialization_id']??'')==$s['specialization_id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['specialization_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['specialization_id'])): ?><span class="error-text"><?= $errors['specialization_id'] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Working Area: <span class="required">*</span></label>
                    <select name="area_id">
                        <option value="">-- Select --</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['area_id'] ?>"
                                <?= ($old_input['area_id']??'')==$a['area_id']?'selected':'' ?>>
                                <?= htmlspecialchars($a['area_name'].' - '.$a['city']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['area_id'])): ?><span class="error-text"><?= $errors['area_id'] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>License Number: <span class="required">*</span></label>
                    <input type="text" name="license_no" value="<?= htmlspecialchars($old_input['license_no']??'') ?>">
                    <?php if (!empty($errors['license_no'])): ?><span class="error-text"><?= $errors['license_no'] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Qualification: <span class="required">*</span></label>
                    <input type="text" name="qualification" placeholder="e.g. MBBS, MD"
                           value="<?= htmlspecialchars($old_input['qualification']??'') ?>">
                    <?php if (!empty($errors['qualification'])): ?><span class="error-text"><?= $errors['qualification'] ?></span><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Password: <span class="required">*</span></label>
                <input type="password" name="password" required>
                <?php if (!empty($errors['password'])): ?><span class="error-text"><?= $errors['password'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Confirm Password: <span class="required">*</span></label>
                <input type="password" name="confirm_password" required>
                <?php if (!empty($errors['confirm_password'])): ?><span class="error-text"><?= $errors['confirm_password'] ?></span><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <p style="text-align:center; margin-top:16px;">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</div>

<script>
function toggleDoctorFields() {
    var role = document.getElementById('role-select').value;
    document.getElementById('doctor-fields').style.display = (role === 'doctor') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleDoctorFields);
</script>
</body>
</html>
