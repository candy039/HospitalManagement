-- Carewell academic demo. Import once into a new local database using phpMyAdmin.
-- Intentionally does not DROP existing tables or overwrite existing patient data.
CREATE DATABASE IF NOT EXISTS hospital_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_management;
CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('patient','doctor','receptionist','admin') NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE patients (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL UNIQUE,
 age TINYINT UNSIGNED NOT NULL,
 gender ENUM('Female','Male','Other') NOT NULL,
 phone VARCHAR(30) NOT NULL,
 address VARCHAR(500) NOT NULL,
 medical_history TEXT NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE doctors (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL UNIQUE,
 specialization VARCHAR(120) NOT NULL,
 qualification VARCHAR(190) NOT NULL,
 experience TINYINT UNSIGNED NOT NULL,
 phone VARCHAR(30) NOT NULL,
 availability VARCHAR(255) NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE receptionists (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL UNIQUE,
 phone VARCHAR(30) NOT NULL,
 address VARCHAR(500) NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE appointments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 patient_id INT UNSIGNED NOT NULL,
 doctor_id INT UNSIGNED NOT NULL,
 appointment_date DATE NOT NULL,
 appointment_time TIME NOT NULL,
 status ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
 FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT,
 INDEX schedule(doctor_id,appointment_date,appointment_time),
 INDEX patient_schedule(patient_id,appointment_date)
) ENGINE=InnoDB;
CREATE TABLE medical_records (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 patient_id INT UNSIGNED NOT NULL,
 doctor_id INT UNSIGNED NOT NULL,
 diagnosis TEXT NOT NULL,
 treatment TEXT NOT NULL,
 prescription TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
 FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
-- All sample accounts: Carewell123! (bcrypt, generated with PHP password_hash).
INSERT INTO users(id,name,email,password,role) VALUES
(1,'Alex Morgan','admin@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','admin'),
(2,'Dr. Sarah Mitchell','sarah@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','doctor'),
(3,'Dr. James Wilson','james@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','doctor'),
(4,'Emma Davis','reception@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','receptionist'),
(5,'Olivia Bennett','olivia@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','patient'),
(6,'Noah Williams','noah@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','patient'),
(7,'Ava Thompson','ava@carewell.test','$2y$12$/T5OSkJzExkngBnrOlHB9OX3NJU5n.UpQJFTjs0IXTgRWaB7cqJ1y','patient');
INSERT INTO doctors(id,user_id,specialization,qualification,experience,phone,availability) VALUES
(1,2,'General Medicine','MBBS, MD',8,'01700000001','Mon–Fri, 09:00–17:00'),
(2,3,'Cardiology','MBBS, MD (Cardiology)',12,'01700000002','Sun–Thu, 10:00–16:00');
INSERT INTO receptionists(user_id,phone,address) VALUES(4,'01700000003','Carewell Hospital, Main Reception');
INSERT INTO patients(id,user_id,age,gender,phone,address,medical_history) VALUES
(1,5,29,'Female','01700000004','24 Garden Road, Dhaka','Seasonal allergies. No known drug allergies.'),
(2,6,45,'Male','01700000005','16 Lake Avenue, Dhaka','Family history of hypertension.'),
(3,7,34,'Female','01700000006','8 Green Street, Dhaka','Routine annual health review.');
INSERT INTO appointments(patient_id,doctor_id,appointment_date,appointment_time,status) VALUES
(1,1,CURRENT_DATE,'09:00','Confirmed'),
(2,2,CURRENT_DATE,'10:30','Pending'),
(3,1,DATE_ADD(CURRENT_DATE,INTERVAL 1 DAY),'11:00','Confirmed'),
(1,1,DATE_SUB(CURRENT_DATE,INTERVAL 7 DAY),'14:00','Completed'),
(2,2,DATE_SUB(CURRENT_DATE,INTERVAL 3 DAY),'12:00','Completed');
INSERT INTO medical_records(patient_id,doctor_id,diagnosis,treatment,prescription) VALUES
(1,1,'Seasonal allergic rhinitis','Avoid known triggers; follow up if symptoms persist.','Demo record only: saline nasal rinse as needed. Review medication with your doctor.'),
(2,2,'Blood pressure review','Monitor blood pressure at home; review in two weeks.','Demo record only: maintain a blood pressure diary; follow clinician instructions.');
