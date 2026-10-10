<?php
// Local development database settings. Change these for your own environment.
define('DB_HOST', getenv('SRMS_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('SRMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('SRMS_DB_PASS') ?: '');
define('DB_NAME', getenv('SRMS_DB_NAME') ?: 'srms');
try {
    $dbh = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    error_log('SRMS database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check your local MySQL settings and confirm that the srms database has been imported.');
}
?>
