<?php
// XAMPP defaults. Environment overrides support other local installations.
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: 'localhost').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.(getenv('DB_NAME') ?: 'hospital_management').';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch (PDOException $e) {
            error_log('HMS database connection failed: '.$e->getMessage());
            http_response_code(503);
            exit('Database unavailable. Start MySQL and import database/hospital_management.sql. Check config/database.php or your DB_* environment settings.');
        }
    }
    return $pdo;
}
