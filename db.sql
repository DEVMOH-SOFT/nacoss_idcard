-- SQL to create student_id_system database and students table

CREATE DATABASE IF NOT EXISTS student_id_system;
USE student_id_system;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    post VARCHAR(255) NOT NULL,
    matric_no VARCHAR(100) NOT NULL UNIQUE,
    image_path VARCHAR(255) NOT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
