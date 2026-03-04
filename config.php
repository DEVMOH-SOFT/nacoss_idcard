<?php
$host = 'localhost';
$db = 'student_id_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Error 1049 = unknown database. Create it for first run, then reconnect.
    if ((int) $e->errorInfo[1] === 1049) {
        try {
            $bootstrap = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
            $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $inner) {
            http_response_code(500);
            exit('Database bootstrap failed: ' . $inner->getMessage());
        }
    } else {
        http_response_code(500);
        exit('Database connection failed: ' . $e->getMessage());
    }
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            level ENUM('100','200','300','400') NOT NULL,
            post VARCHAR(255) NOT NULL DEFAULT 'Student',
            matric_no VARCHAR(100) NOT NULL UNIQUE,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $columns = $pdo->query('SHOW COLUMNS FROM students')->fetchAll();
    $columnMap = [];
    foreach ($columns as $column) {
        $columnMap[$column['Field']] = true;
    }

    if (!isset($columnMap['level'])) {
        $pdo->exec("ALTER TABLE students ADD COLUMN level ENUM('100','200','300','400') NOT NULL DEFAULT '100' AFTER full_name");
    }
    if (!isset($columnMap['post'])) {
        $pdo->exec("ALTER TABLE students ADD COLUMN post VARCHAR(255) NOT NULL DEFAULT 'Student' AFTER level");
    }
    if (!isset($columnMap['created_at']) && isset($columnMap['date_created'])) {
        $pdo->exec('ALTER TABLE students CHANGE date_created created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
    if (!isset($columnMap['created_at']) && !isset($columnMap['date_created'])) {
        $pdo->exec('ALTER TABLE students ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    }
    if (!isset($columnMap['updated_at'])) {
        $pdo->exec('ALTER TABLE students ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['site admin', password_hash('administrator@csc2026', PASSWORD_DEFAULT)]);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

ensureSchema($pdo);
