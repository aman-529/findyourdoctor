DELIMITER //

CREATE PROCEDURE sp_register_user(
    IN p_role_name VARCHAR(20),
    IN p_full_name VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_phone VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_gender ENUM('male','female','other'),
    IN p_dob DATE,
    IN p_specialization_id SMALLINT UNSIGNED,
    IN p_area_id SMALLINT UNSIGNED,
    IN p_license_no VARCHAR(60),
    IN p_qualification VARCHAR(200),
    OUT p_new_user_id INT
)
BEGIN
    DECLARE v_role_id TINYINT UNSIGNED;
    DECLARE v_user_id INT UNSIGNED;
    
    START TRANSACTION;
    SELECT role_id INTO v_role_id FROM roles WHERE role_name = p_role_name COLLATE utf8mb4_unicode_ci;
    INSERT INTO users (role_id, full_name, email, phone, password_hash)
    VALUES (v_role_id, p_full_name, p_email, p_phone, p_password_hash);
    
    SET v_user_id = LAST_INSERT_ID();
    IF p_role_name = 'doctor' THEN
        INSERT INTO doctor_profiles (doctor_id, specialization_id, area_id, gender, date_of_birth, license_no, qualification)
        VALUES (v_user_id, p_specialization_id, p_area_id, p_gender, p_dob, p_license_no, p_qualification);
    ELSE
        INSERT INTO patient_profiles (patient_id, gender, date_of_birth)
        VALUES (v_user_id, p_gender, p_dob);
    END IF;
    
    SET p_new_user_id = v_user_id;
    COMMIT;
END //

DELIMITER //
CREATE PROCEDURE sp_book_appointment(
    IN p_slot_id INT UNSIGNED,
    IN p_patient_id INT UNSIGNED,
    IN p_symptom_note TEXT,
    OUT p_booking_id INT UNSIGNED,
    OUT p_serial_no TINYINT UNSIGNED
)
BEGIN
    DECLARE v_slot_status VARCHAR(20);
    DECLARE v_booked INT;
    DECLARE v_capacity INT;
    DECLARE v_serial INT;
    
    START TRANSACTION;
    SELECT slot_status, booked_patient_count, max_patient_count 
    INTO v_slot_status, v_booked, v_capacity
    FROM appointment_slots 
    WHERE slot_id = p_slot_id 
    FOR UPDATE;
    IF v_slot_status != 'open' THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slot is not available';
    END IF;
    IF v_booked >= v_capacity THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slot is full';
    END IF;
    SET v_serial = v_booked + 1;
    INSERT INTO appointment_bookings (slot_id, patient_id, booking_serial_no, symptom_note)
    VALUES (p_slot_id, p_patient_id, v_serial, p_symptom_note);
    
    SET p_booking_id = LAST_INSERT_ID();
    SET p_serial_no = v_serial;
    UPDATE appointment_slots SET booked_patient_count = booked_patient_count + 1 
    WHERE slot_id = p_slot_id;
    
    COMMIT;
END //
DELIMITER //
CREATE PROCEDURE sp_confirm_payment(
    IN p_booking_id INT UNSIGNED,
    IN p_patient_id INT UNSIGNED,
    IN p_doctor_id INT UNSIGNED,
    IN p_amount DECIMAL(10,2),
    IN p_method VARCHAR(50),
    IN p_transaction_ref VARCHAR(150)
)
BEGIN
    DECLARE v_booking_status VARCHAR(20);
    
    START TRANSACTION;
    SELECT booking_status INTO v_booking_status 
    FROM appointment_bookings 
    WHERE booking_id = p_booking_id;
    
    IF v_booking_status != 'pending_payment' THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Booking not awaiting payment';
    END IF;
    INSERT INTO payments (booking_id, patient_id, doctor_id, amount, payment_method, transaction_reference, payment_status, paid_at)
    VALUES (p_booking_id, p_patient_id, p_doctor_id, p_amount, p_method, p_transaction_ref, 'paid', NOW());
    UPDATE appointment_bookings 
    SET booking_status = 'confirmed', confirmed_at = NOW() 
    WHERE booking_id = p_booking_id;
    
    COMMIT;
END //

DELIMITER //

CREATE PROCEDURE sp_cancel_booking(
    IN p_booking_id INT UNSIGNED,
    IN p_patient_id INT UNSIGNED
)
BEGIN
    DECLARE v_slot_id INT UNSIGNED;
    DECLARE v_booking_status VARCHAR(30);
    
    START TRANSACTION;
    SELECT slot_id, booking_status 
    INTO v_slot_id, v_booking_status
    FROM appointment_bookings 
    WHERE booking_id = p_booking_id AND patient_id = p_patient_id;
    
    IF v_booking_status IN ('completed', 'cancelled', 'no_show') THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot cancel this booking';
    END IF;
    UPDATE appointment_bookings 
    SET booking_status = 'cancelled', cancelled_at = NOW()
    WHERE booking_id = p_booking_id;
    UPDATE appointment_slots
    SET booked_patient_count = booked_patient_count - 1 
    WHERE slot_id = v_slot_id;
    
    COMMIT;
END //


DELIMITER //
CREATE PROCEDURE sp_submit_review(
    IN p_booking_id INT UNSIGNED,
    IN p_doctor_id INT UNSIGNED,
    IN p_patient_id INT UNSIGNED,
    IN p_rating TINYINT,
    IN p_review_text TEXT
)
BEGIN
    DECLARE v_status VARCHAR(20);
    SELECT booking_status INTO v_status 
    FROM appointment_bookings 
    WHERE booking_id = p_booking_id;
    
    IF v_status != 'completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Review can only be submitted for completed appointments';
    END IF;
    IF EXISTS (SELECT 1 FROM reviews WHERE booking_id = p_booking_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You have already reviewed this appointment';
    END IF;
    INSERT INTO reviews (booking_id, doctor_id, patient_id, rating, review_text)
    VALUES (p_booking_id, p_doctor_id, p_patient_id, p_rating, p_review_text);
END //

DELIMITER //
CREATE PROCEDURE sp_approve_doctor_profile(
    IN p_doctor_id INT UNSIGNED,
    IN p_admin_user_id INT UNSIGNED
)
BEGIN
    UPDATE doctor_profiles 
    SET verification_status = 'approved', approved_by = p_admin_user_id, approved_at = NOW() 
    WHERE doctor_id = p_doctor_id;
END //

DELIMITER //
CREATE PROCEDURE sp_reject_doctor_profile(
    IN p_doctor_id INT UNSIGNED,
    IN p_admin_user_id INT UNSIGNED
)
BEGIN
    UPDATE doctor_profiles 
    SET verification_status = 'rejected', approved_by = p_admin_user_id, approved_at = NOW() 
    WHERE doctor_id = p_doctor_id;
END //

DELIMITER //
CREATE PROCEDURE sp_approve_profile_update(
    IN p_request_id INT UNSIGNED,
    IN p_admin_user_id INT UNSIGNED
)
BEGIN
    DECLARE v_doctor_id INT UNSIGNED;
    DECLARE v_spec SMALLINT UNSIGNED;
    DECLARE v_area SMALLINT UNSIGNED;
    DECLARE v_license VARCHAR(60);
    DECLARE v_qualification VARCHAR(200);
    DECLARE v_exp INT;
    DECLARE v_workplace VARCHAR(200);
    DECLARE v_chamber VARCHAR(150);
    DECLARE v_address TEXT;
    DECLARE v_bio TEXT;
    DECLARE v_fee DECIMAL(10,2);
    START TRANSACTION;
    SELECT doctor_id, req_specialization_id, req_area_id, req_license_no, req_qualification,
           req_experience_years, req_current_workplace, req_chamber_name, req_chamber_address,
           req_biography, req_consultation_fee
    INTO v_doctor_id, v_spec, v_area, v_license, v_qualification, v_exp, v_workplace,
         v_chamber, v_address, v_bio, v_fee
    FROM doctor_profile_update_requests
    WHERE request_id = p_request_id;
    UPDATE doctor_profiles SET 
        specialization_id = COALESCE(v_spec, specialization_id),
        area_id = COALESCE(v_area, area_id),
        license_no = COALESCE(v_license, license_no),
        qualification = COALESCE(v_qualification, qualification),
        experience_years = COALESCE(v_exp, experience_years),
        current_workplace = COALESCE(v_workplace, current_workplace),
        chamber_name = COALESCE(v_chamber, chamber_name),
        chamber_address = COALESCE(v_address, chamber_address),
        biography = COALESCE(v_bio, biography),
        consultation_fee = COALESCE(v_fee, consultation_fee)
    WHERE doctor_id = v_doctor_id;
    UPDATE doctor_profile_update_requests 
    SET request_status = 'approved', reviewed_by = p_admin_user_id, reviewed_at = NOW() 
    WHERE request_id = p_request_id;
    
    COMMIT;
END //

DELIMITER //
CREATE PROCEDURE sp_reject_profile_update(
    IN p_request_id INT UNSIGNED,
    IN p_admin_user_id INT UNSIGNED,
    IN p_rejection_reason TEXT
)
BEGIN
    UPDATE doctor_profile_update_requests 
    SET request_status = 'rejected', reviewed_by = p_admin_user_id, reviewed_at = NOW(), rejection_reason = p_rejection_reason 
    WHERE request_id = p_request_id;
END //

DELIMITER //
CREATE PROCEDURE sp_mark_booking_completed(
    IN p_booking_id INT UNSIGNED,
    IN p_doctor_id INT UNSIGNED
)
BEGIN
    DECLARE v_slot_id INT UNSIGNED;
    DECLARE v_booking_status VARCHAR(30);
    SELECT ab.slot_id, ab.booking_status 
    INTO v_slot_id, v_booking_status
    FROM appointment_bookings ab
    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
    WHERE ab.booking_id = p_booking_id AND asl.doctor_id = p_doctor_id;
    
    IF v_booking_status != 'confirmed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only confirmed bookings can be marked completed';
    END IF;
    UPDATE appointment_bookings 
    SET booking_status = 'completed' 
    WHERE booking_id = p_booking_id;
END //

DELIMITER //

CREATE PROCEDURE sp_search_doctors(
    IN p_specialization_id SMALLINT UNSIGNED,
    IN p_area_id SMALLINT UNSIGNED,
    IN p_symptom_name VARCHAR(100)
)
BEGIN
    SELECT
        dp.doctor_id, u.user_id, u.full_name,
        s.specialization_name,
        a.area_name, a.city,
        dp.consultation_fee AS visit_fee, dp.experience_years,
        dp.chamber_name, dp.chamber_address, dp.biography,
        ROUND(AVG(r.rating), 1) AS avg_rating,
        COUNT(DISTINCT r.review_id) AS review_count
    FROM doctor_profiles dp
    JOIN users u ON dp.doctor_id = u.user_id
    JOIN specializations s ON dp.specialization_id = s.specialization_id
    JOIN areas a ON dp.area_id = a.area_id
    LEFT JOIN reviews r ON dp.doctor_id = r.doctor_id AND r.review_status = 'approved'
    WHERE dp.verification_status = 'approved'
      AND (p_specialization_id IS NULL OR dp.specialization_id = p_specialization_id)
      AND (p_area_id IS NULL OR dp.area_id = p_area_id)
      AND (p_symptom_name IS NULL OR dp.doctor_id IN (
            SELECT ds.doctor_id FROM doctor_symptoms ds
            JOIN symptoms sym ON ds.symptom_id = sym.symptom_id
            WHERE sym.symptom_name COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_symptom_name COLLATE utf8mb4_unicode_ci, '%')
          ))
    GROUP BY dp.doctor_id
    ORDER BY avg_rating DESC;
END //


DELIMITER //
CREATE PROCEDURE sp_get_doctor_dashboard(
    IN p_doctor_id INT UNSIGNED
)
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM appointment_slots WHERE doctor_id = p_doctor_id AND appointment_date >= CURDATE() AND slot_status IN ('open', 'full')) AS upcoming_slots,
        (SELECT COUNT(*) FROM appointment_bookings ab
         JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
         WHERE asl.doctor_id = p_doctor_id AND DATE(asl.appointment_date) = CURDATE()) AS today_bookings,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE doctor_id = p_doctor_id AND payment_status = 'paid') AS total_earnings;
    SELECT slot_id, appointment_date, start_time, end_time, booked_patient_count, max_patient_count, slot_status
    FROM appointment_slots
    WHERE doctor_id = p_doctor_id AND DATE(appointment_date) = CURDATE()
    ORDER BY start_time;
    SELECT COUNT(*) AS pending_updates FROM doctor_profile_update_requests
    WHERE doctor_id = p_doctor_id AND request_status = 'pending';
END //

DELIMITER //
CREATE PROCEDURE sp_get_patient_dashboard(
    IN p_patient_id INT UNSIGNED
)
BEGIN
    SELECT
        ab.booking_id, ab.booking_serial_no, ab.booking_status,
        ab.booked_at, ab.confirmed_at,
        asl.appointment_date, asl.start_time, asl.visit_fee,
        dp.doctor_id,
        u.full_name AS doctor_name,
        s.specialization_name,
        p.payment_status, p.payment_method
    FROM appointment_bookings ab
    JOIN appointment_slots asl ON ab.slot_id = asl.slot_id
    JOIN doctor_profiles dp ON asl.doctor_id = dp.doctor_id
    JOIN users u ON dp.doctor_id = u.user_id
    JOIN specializations s ON dp.specialization_id = s.specialization_id
    LEFT JOIN payments p ON ab.booking_id = p.booking_id
    WHERE ab.patient_id = p_patient_id
    ORDER BY ab.booked_at DESC;
END //

DELIMITER ;
