CREATE DATABASE IF NOT EXISTS student_id_system;
USE student_id_system;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    level ENUM('100','200','300','400') NOT NULL,
    post VARCHAR(255) NOT NULL DEFAULT 'Student',
    matric_no VARCHAR(100) NOT NULL UNIQUE,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional seed. Password hash should be generated with password_hash in PHP.
-- INSERT INTO admin_users (username, password_hash)
-- VALUES ('admin', '$2y$10$replace_with_real_hash');
