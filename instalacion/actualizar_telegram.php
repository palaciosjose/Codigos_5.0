<?php
require_once __DIR__ . '/basededatos.php';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Añadir columna telegram_id si no existe
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'telegram_id'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN telegram_id BIGINT UNSIGNED NULL AFTER password");
        echo "Columna telegram_id añadida.\n";
    } else {
        echo "La columna telegram_id ya existe.\n";
    }

    // Eliminar columna email si existe
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($result->rowCount() > 0) {
        $pdo->exec("ALTER TABLE users DROP COLUMN email");
        echo "Columna email eliminada.\n";
    } else {
        echo "La columna email no existe.\n";
    }

    echo "Actualización completada correctamente.\n";
} catch (PDOException $e) {
    echo "Error al actualizar la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
?>
