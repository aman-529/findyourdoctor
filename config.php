<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['user_role'] ?? 'guest';

$db_credentials = [
    'admin'   => ['admin_user',   'admin_pass_123'],
    'doctor'  => ['doctor_user',  'doctor_pass_123'],
    'patient' => ['patient_user', 'patient_pass_123'],
    'guest'   => ['patient_user', 'patient_pass_123'],
];

[$db_user, $db_pass] = $db_credentials[$role] ?? $db_credentials['guest'];

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=doctor_appointment_db;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed. Check your MySQL server and credentials.");
}

function get_admin_pdo() {
    try {
        return new PDO(
            "mysql:host=localhost;dbname=doctor_appointment_db;charset=utf8mb4",
            'admin_user', 'admin_pass_123',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        die("Database connection failed.");
    }
}
?>
