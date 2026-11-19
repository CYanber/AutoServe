<?php
// Change these values to match your local MySQL Server account.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'autoserve_db';
$DB_USER = 'root';
$DB_PASS = ''; // Enter your MySQL password if you have one.

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit('Database connection failed. Check config/database.php and make sure MySQL Server is running.');
}
