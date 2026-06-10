
CREATE DATABASE IF NOT EXISTS doctor_appointment_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE doctor_appointment_db;

CREATE TABLE roles (
    role_id   TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(20) NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE users (
    user_id        INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    role_id        TINYINT UNSIGNED NOT NULL,
    full_name      VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    email          VARCHAR(150) NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci,
    phone          VARCHAR(20)  NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci,
    password_hash  VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    account_status ENUM('active','inactive','blocked') DEFAULT 'active',
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE specializations (
    specialization_id   SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    specialization_name VARCHAR(100) NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci,
    description         TEXT NULL COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE areas (
    area_id   SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
    city      VARCHAR(80)  NOT NULL COLLATE utf8mb4_unicode_ci,
    district  VARCHAR(80)  NOT NULL COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE symptoms (
    symptom_id   SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    symptom_name VARCHAR(100) NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE doctor_profiles (
    doctor_id           INT UNSIGNED PRIMARY KEY,
    specialization_id   SMALLINT UNSIGNED NOT NULL,
    area_id             SMALLINT UNSIGNED NOT NULL,
    gender              ENUM('male','female','other') NOT NULL,
    date_of_birth       DATE NOT NULL,
    license_no          VARCHAR(60) NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci,
    qualification       VARCHAR(200) NOT NULL COLLATE utf8mb4_unicode_ci,
    experience_years    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    current_workplace   VARCHAR(200) NULL COLLATE utf8mb4_unicode_ci,
    chamber_name        VARCHAR(150) NULL COLLATE utf8mb4_unicode_ci,
    chamber_address     TEXT NULL COLLATE utf8mb4_unicode_ci,
    biography           TEXT NULL COLLATE utf8mb4_unicode_ci,
    consultation_fee    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    profile_image       VARCHAR(300) NULL COLLATE utf8mb4_unicode_ci,
    verification_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by         INT UNSIGNED NULL,
    approved_at         DATETIME NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id)         REFERENCES users(user_id),
    FOREIGN KEY (specialization_id) REFERENCES specializations(specialization_id),
    FOREIGN KEY (area_id)           REFERENCES areas(area_id),
    FOREIGN KEY (approved_by)       REFERENCES users(user_id),
    INDEX idx_verification (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE doctor_profile_update_requests (
    request_id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    doctor_id             INT UNSIGNED NOT NULL,
    req_specialization_id SMALLINT UNSIGNED NULL,
    req_area_id           SMALLINT UNSIGNED NULL,
    req_license_no        VARCHAR(60)  NULL COLLATE utf8mb4_unicode_ci,
    req_qualification     VARCHAR(200) NULL COLLATE utf8mb4_unicode_ci,
    req_experience_years  TINYINT UNSIGNED NULL,
    req_current_workplace VARCHAR(200) NULL COLLATE utf8mb4_unicode_ci,
    req_chamber_name      VARCHAR(150) NULL COLLATE utf8mb4_unicode_ci,
    req_chamber_address   TEXT NULL COLLATE utf8mb4_unicode_ci,
    req_biography         TEXT NULL COLLATE utf8mb4_unicode_ci,
    req_consultation_fee  DECIMAL(10,2) NULL,
    req_profile_image     VARCHAR(300) NULL COLLATE utf8mb4_unicode_ci,
    supporting_document   VARCHAR(300) NULL COLLATE utf8mb4_unicode_ci,
    request_status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    submitted_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_by           INT UNSIGNED NULL,
    reviewed_at           DATETIME NULL,
    rejection_reason      TEXT NULL COLLATE utf8mb4_unicode_ci,
    FOREIGN KEY (doctor_id)             REFERENCES doctor_profiles(doctor_id),
    FOREIGN KEY (req_specialization_id) REFERENCES specializations(specialization_id),
    FOREIGN KEY (req_area_id)           REFERENCES areas(area_id),
    FOREIGN KEY (reviewed_by)           REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE patient_profiles (
    patient_id        INT UNSIGNED PRIMARY KEY,
    gender            ENUM('male','female','other') NOT NULL,
    date_of_birth     DATE NOT NULL,
    blood_group       VARCHAR(5) NULL COLLATE utf8mb4_unicode_ci,
    address           TEXT NULL COLLATE utf8mb4_unicode_ci,
    emergency_contact VARCHAR(20) NULL COLLATE utf8mb4_unicode_ci,
    profile_image     VARCHAR(300) NULL COLLATE utf8mb4_unicode_ci,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE doctor_symptoms (
    doctor_id  INT UNSIGNED NOT NULL,
    symptom_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (doctor_id, symptom_id),
    FOREIGN KEY (doctor_id)  REFERENCES doctor_profiles(doctor_id),
    FOREIGN KEY (symptom_id) REFERENCES symptoms(symptom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointment_slots (
    slot_id              INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    doctor_id            INT UNSIGNED NOT NULL,
    appointment_date     DATE NOT NULL,
    start_time           TIME NOT NULL,
    end_time             TIME NOT NULL,
    visit_fee            DECIMAL(10,2) NOT NULL,
    max_patient_count    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    booked_patient_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    chamber_name         VARCHAR(150) NULL COLLATE utf8mb4_unicode_ci,
    chamber_address      TEXT NULL COLLATE utf8mb4_unicode_ci,
    slot_status          ENUM('open','full','closed','cancelled') DEFAULT 'open',
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctor_profiles(doctor_id),
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_status (slot_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE appointment_bookings (
    booking_id        INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    slot_id           INT UNSIGNED NOT NULL,
    patient_id        INT UNSIGNED NOT NULL,
    booking_serial_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    symptom_note      TEXT NULL COLLATE utf8mb4_unicode_ci,
    booking_status    ENUM('pending_payment','confirmed','completed','cancelled','no_show') DEFAULT 'pending_payment',
    booked_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at      DATETIME NULL,
    cancelled_at      DATETIME NULL,
    UNIQUE KEY uq_slot_patient (slot_id, patient_id),
    FOREIGN KEY (slot_id)    REFERENCES appointment_slots(slot_id),
    FOREIGN KEY (patient_id) REFERENCES patient_profiles(patient_id),
    INDEX idx_status (booking_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE payments (
    payment_id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    booking_id            INT UNSIGNED NOT NULL UNIQUE,
    patient_id            INT UNSIGNED NOT NULL,
    doctor_id             INT UNSIGNED NOT NULL,
    amount                DECIMAL(10,2) NOT NULL,
    payment_method        VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci,
    transaction_reference VARCHAR(150) NULL COLLATE utf8mb4_unicode_ci,
    payment_status        ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    paid_at               DATETIME NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES appointment_bookings(booking_id),
    FOREIGN KEY (patient_id) REFERENCES patient_profiles(patient_id),
    FOREIGN KEY (doctor_id)  REFERENCES doctor_profiles(doctor_id),
    INDEX idx_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
    review_id     INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    booking_id    INT UNSIGNED NOT NULL UNIQUE,
    doctor_id     INT UNSIGNED NOT NULL,
    patient_id    INT UNSIGNED NOT NULL,
    rating        TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text   TEXT NULL COLLATE utf8mb4_unicode_ci,
    review_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_by   INT UNSIGNED NULL,
    reviewed_at   DATETIME NULL,
    FOREIGN KEY (booking_id)  REFERENCES appointment_bookings(booking_id),
    FOREIGN KEY (doctor_id)   REFERENCES doctor_profiles(doctor_id),
    FOREIGN KEY (patient_id)  REFERENCES patient_profiles(patient_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id),
    INDEX idx_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;



INSERT INTO roles VALUES (1,'admin'),(2,'doctor'),(3,'patient');


INSERT INTO users (role_id,full_name,email,phone,password_hash,account_status) VALUES
(1,'System Admin','admin@clinic.com','01700000000','admin123','active');

INSERT INTO specializations (specialization_name,description) VALUES
('Cardiology','Heart and cardiovascular diseases'),
('Dentistry','Teeth and oral health'),
('Pediatrics','Children medical care'),
('Orthopedics','Bones and joint care'),
('Neurology','Brain and nervous system');

INSERT INTO areas (area_name,city,district) VALUES
('Dhanmondi','Dhaka','Dhaka'),
('Gulshan','Dhaka','Dhaka'),
('Mirpur','Dhaka','Dhaka'),
('Chittagong Sadar','Chittagong','Chittagong'),
('Sylhet Sadar','Sylhet','Sylhet');

INSERT INTO symptoms (symptom_name) VALUES
('fever'),('chest pain'),('headache'),('skin rash'),('toothache'),('fatigue');

INSERT INTO users (role_id,full_name,email,phone,password_hash,account_status) VALUES
(2,'Dr. Ahmed Khan','dr.ahmed@clinic.com','01800000001','doctor123','active');

INSERT INTO doctor_profiles
  (doctor_id,specialization_id,area_id,gender,date_of_birth,license_no,
   qualification,experience_years,current_workplace,chamber_name,
   chamber_address,biography,consultation_fee,verification_status,approved_by,approved_at)
VALUES
  (2,1,1,'male','1980-05-15','DL-2024-001','MBBS, MD Cardiology',
   15,'National Heart Foundation','Heart Care Chamber',
   'House 5, Road 15, Dhanmondi, Dhaka',
   'Experienced cardiologist specialising in heart disease.',
   500,'approved',1,NOW());

INSERT INTO doctor_symptoms (doctor_id,symptom_id) VALUES (2,1),(2,2),(2,6);

INSERT INTO users (role_id,full_name,email,phone,password_hash,account_status) VALUES
(3,'Rahim Hossain','rahim@email.com','01900000002','patient123','active');

INSERT INTO patient_profiles (patient_id,gender,date_of_birth,blood_group,address,emergency_contact)
VALUES (3,'male','1995-08-20','O+','Dhanmondi, Dhaka','01711111111');

INSERT INTO appointment_slots
  (doctor_id,appointment_date,start_time,end_time,visit_fee,max_patient_count,chamber_name,chamber_address,slot_status)
VALUES
  (2,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'09:00:00','10:00:00',500,3,'Heart Care Chamber','House 5, Road 15, Dhanmondi, Dhaka','open'),
  (2,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'10:30:00','11:30:00',500,2,'Heart Care Chamber','House 5, Road 15, Dhanmondi, Dhaka','open'),
  (2,DATE_ADD(CURDATE(),INTERVAL 2 DAY),'09:00:00','10:00:00',500,3,'Heart Care Chamber','House 5, Road 15, Dhanmondi, Dhaka','open');


INSERT INTO appointment_bookings
  (slot_id,patient_id,booking_serial_no,symptom_note,booking_status,booked_at,confirmed_at)
VALUES (1,3,1,'Chest pain and shortness of breath','confirmed',NOW(),NOW());

UPDATE appointment_slots SET booked_patient_count=1 WHERE slot_id=1;

INSERT INTO payments
  (booking_id,patient_id,doctor_id,amount,payment_method,transaction_reference,payment_status,paid_at)
VALUES (1,3,2,500,'bKash','TXN-BK-001','paid',NOW());

INSERT INTO appointment_bookings
  (slot_id,patient_id,booking_serial_no,symptom_note,booking_status,booked_at,confirmed_at)
VALUES (2,3,1,'Routine cardiac checkup','completed',
  DATE_SUB(NOW(),INTERVAL 7 DAY),DATE_SUB(NOW(),INTERVAL 7 DAY));

UPDATE appointment_slots SET booked_patient_count=1 WHERE slot_id=2;

INSERT INTO payments (booking_id,patient_id,doctor_id,amount,payment_method,payment_status,paid_at)
VALUES (2,3,2,500,'Cash','paid',DATE_SUB(NOW(),INTERVAL 7 DAY));

INSERT INTO reviews (booking_id,doctor_id,patient_id,rating,review_text,review_status)
VALUES (2,2,3,5,'Excellent doctor! Very thorough.','pending');


INSERT INTO doctor_profile_update_requests
  (doctor_id,req_biography,req_consultation_fee,request_status,submitted_at)
VALUES (2,'Senior cardiologist with 15+ years of experience.',600,'pending',NOW());
