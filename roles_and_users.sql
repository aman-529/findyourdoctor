-- ============================================================
-- MySQL Role Management
-- Run AFTER database.sql, procedures.sql, triggers.sql
-- ============================================================
USE doctor_appointment_db;

DROP USER IF EXISTS 'admin_user'@'localhost';
DROP USER IF EXISTS 'doctor_user'@'localhost';
DROP USER IF EXISTS 'patient_user'@'localhost';

-- admin_user: full access
CREATE USER 'admin_user'@'localhost' IDENTIFIED BY 'admin_pass_123';
GRANT SELECT, INSERT, UPDATE, DELETE ON doctor_appointment_db.* TO 'admin_user'@'localhost';
GRANT EXECUTE ON doctor_appointment_db.* TO 'admin_user'@'localhost';

-- doctor_user
CREATE USER 'doctor_user'@'localhost' IDENTIFIED BY 'doctor_pass_123';
GRANT SELECT ON doctor_appointment_db.roles TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.users TO 'doctor_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON doctor_appointment_db.doctor_profiles TO 'doctor_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON doctor_appointment_db.doctor_profile_update_requests TO 'doctor_user'@'localhost';
GRANT SELECT, INSERT ON doctor_appointment_db.appointment_slots TO 'doctor_user'@'localhost';
GRANT SELECT, UPDATE ON doctor_appointment_db.appointment_bookings TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.payments TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.specializations TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.areas TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.symptoms TO 'doctor_user'@'localhost';
GRANT SELECT, INSERT, DELETE ON doctor_appointment_db.doctor_symptoms TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.patient_profiles TO 'doctor_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.reviews TO 'doctor_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_register_user TO 'doctor_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_mark_booking_completed TO 'doctor_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_search_doctors TO 'doctor_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_get_doctor_dashboard TO 'doctor_user'@'localhost';

-- patient_user
CREATE USER 'patient_user'@'localhost' IDENTIFIED BY 'patient_pass_123';
GRANT SELECT ON doctor_appointment_db.roles TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.users TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.doctor_profiles TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.appointment_slots TO 'patient_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON doctor_appointment_db.appointment_bookings TO 'patient_user'@'localhost';
GRANT SELECT, INSERT ON doctor_appointment_db.payments TO 'patient_user'@'localhost';
GRANT SELECT, INSERT ON doctor_appointment_db.reviews TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.specializations TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.areas TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.symptoms TO 'patient_user'@'localhost';
GRANT SELECT ON doctor_appointment_db.doctor_symptoms TO 'patient_user'@'localhost';
GRANT SELECT, UPDATE ON doctor_appointment_db.patient_profiles TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_register_user TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_book_appointment TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_confirm_payment TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_cancel_booking TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_submit_review TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_search_doctors TO 'patient_user'@'localhost';
GRANT EXECUTE ON PROCEDURE doctor_appointment_db.sp_get_patient_dashboard TO 'patient_user'@'localhost';

FLUSH PRIVILEGES;
