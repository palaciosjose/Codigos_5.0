<?php
// Database configuration loader.
// Credentials can be defined through environment variables
// or by creating a non-tracked file at config/db_credentials.php
// which sets $db_host, $db_user, $db_password and $db_name.

$db_host = getenv('DB_HOST') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_password = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: '';

// Load from credentials file if environment variables are empty
if (file_exists(__DIR__ . '/../config/db_credentials.php')) {
    include __DIR__ . '/../config/db_credentials.php';
}
?>
