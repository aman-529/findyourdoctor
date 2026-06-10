
DELIMITER //

CREATE TRIGGER trg_slot_status_full
BEFORE UPDATE ON appointment_slots
FOR EACH ROW
BEGIN
    IF NEW.booked_patient_count >= NEW.max_patient_count THEN
        SET NEW.slot_status = 'full';
    END IF;
END //


DELIMITER //
CREATE TRIGGER trg_slot_status_reopen
BEFORE UPDATE ON appointment_slots
FOR EACH ROW
BEGIN
    IF NEW.booked_patient_count < NEW.max_patient_count AND OLD.slot_status = 'full' THEN
        SET NEW.slot_status = 'open';
    END IF;
END //



DELIMITER //
CREATE TRIGGER trg_payment_confirm_booking
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        UPDATE appointment_bookings
        SET booking_status = 'confirmed', confirmed_at = NOW()
        WHERE booking_id = NEW.booking_id;
    END IF;
END //

DELIMITER //
CREATE TRIGGER trg_prevent_duplicate_active_booking
BEFORE INSERT ON appointment_bookings
FOR EACH ROW
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(*) INTO v_count 
    FROM appointment_bookings
    WHERE slot_id = NEW.slot_id AND patient_id = NEW.patient_id
      AND booking_status != 'cancelled';
    
    IF v_count > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Patient already has an active booking for this slot';
    END IF;
END //


DELIMITER //
CREATE TRIGGER trg_review_only_after_completion
BEFORE INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(30);
    SELECT booking_status INTO v_status 
    FROM appointment_bookings 
    WHERE booking_id = NEW.booking_id;
    
    IF v_status != 'completed' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Review can only be submitted for completed appointments';
    END IF;
END //



DELIMITER //
CREATE TRIGGER trg_block_slot_if_doctor_not_approved
BEFORE INSERT ON appointment_slots
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(20);
    SELECT verification_status INTO v_status 
    FROM doctor_profiles 
    WHERE doctor_id = NEW.doctor_id;
    
    IF v_status != 'approved' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Only approved doctors can create appointment slots';
    END IF;
END //

DELIMITER //
CREATE TRIGGER trg_log_booking_serial
BEFORE INSERT ON appointment_bookings
FOR EACH ROW
BEGIN
    DECLARE v_serial TINYINT;
    SELECT COUNT(*) + 1 INTO v_serial 
    FROM appointment_bookings
    WHERE slot_id = NEW.slot_id AND booking_status != 'cancelled';
    
    SET NEW.booking_serial_no = v_serial;
END //

DELIMITER ;
