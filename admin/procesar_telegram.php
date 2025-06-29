<?php
/**
 * Procesador de configuración del bot de Telegram CORREGIDO
 * Ahora guarda en la tabla 'settings' (sistema principal)
 * Reemplaza el archivo admin/procesar_telegram.php existente
 */

session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

// Verificar autenticación de administrador
check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save_config':
            $token = trim($_POST['token'] ?? '');
            $webhook = trim($_POST['webhook'] ?? '');
            
            // Validaciones básicas
            if (empty($token)) {
                throw new Exception('El token del bot es requerido');
            }
            
            if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
                throw new Exception('Formato de token inválido');
            }
            
            if (!empty($webhook) && !filter_var($webhook, FILTER_VALIDATE_URL)) {
                throw new Exception('URL del webhook inválida');
            }
            
            // ✅ CORRECCIÓN: Guardar en tabla 'settings' (sistema principal)
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'TELEGRAM_BOT_TOKEN'");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            
            // También guardar webhook si se proporcionó
            if (!empty($webhook)) {
                // Crear setting para webhook si no existe
                $stmt = $conn->prepare("INSERT INTO settings (name, value, description, category) VALUES ('TELEGRAM_WEBHOOK_URL', ?, 'URL del webhook de Telegram', 'telegram') ON DUPLICATE KEY UPDATE value = VALUES(value)");
                $stmt->bind_param("s", $webhook);
                $stmt->execute();
                $stmt->close();
            }
            
            // ✅ COMPATIBILIDAD: También actualizar tabla legacy para evitar confusiones
            $stmt = $conn->prepare("INSERT INTO telegram_bot_config (setting_name, setting_value) VALUES ('token', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            
            if (!empty($webhook)) {
                $stmt = $conn->prepare("INSERT INTO telegram_bot_config (setting_name, setting_value) VALUES ('webhook', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->bind_param("s", $webhook);
                $stmt->execute();
                $stmt->close();
            }
            
            // Registrar en logs
            $log_message = "Configuración del bot actualizada - Token: " . substr($token, 0, 10) . "...";
            if (!empty($webhook)) {
                $log_message .= ", Webhook: $webhook";
            }
            
            // Log opcional en tabla si existe
            $stmt = $conn->prepare("INSERT INTO telegram_bot_logs (user_id, telegram_user_id, action_type, action_data, response_status) VALUES (?, 0, 'config_update', ?, 'success')");
            $log_data = json_encode(['token_updated' => true, 'webhook_updated' => !empty($webhook)]);
            $stmt->bind_param("is", $_SESSION['user_id'], $log_data);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Configuración del bot guardada correctamente';
            break;
            
        case 'test_webhook':
            $token = trim($_POST['token'] ?? '');
            $webhook = trim($_POST['webhook'] ?? '');
            
            if (empty($token) || empty($webhook)) {
                throw new Exception('Token y webhook son requeridos para la prueba');
            }
            
            // Probar conexión con Telegram API
            $url = "https://api.telegram.org/bot$token/getMe";
            $response = file_get_contents($url);
            $result = json_decode($response, true);
            
            if (!$result['ok']) {
                throw new Exception('Token inválido: ' . ($result['description'] ?? 'Error desconocido'));
            }
            
            // Registrar webhook
            $webhook_url = "https://api.telegram.org/bot$token/setWebhook";
            $webhook_data = http_build_query(['url' => $webhook]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $webhook_data
                ]
            ]);
            
            $webhook_response = file_get_contents($webhook_url, false, $context);
            $webhook_result = json_decode($webhook_response, true);
            
            if (!$webhook_result['ok']) {
                throw new Exception('Error registrando webhook: ' . ($webhook_result['description'] ?? 'Error desconocido'));
            }
            
            $_SESSION['success_message'] = 'Bot probado exitosamente. Webhook registrado.';
            break;
            
        case 'disable_bot':
            // Deshabilitar bot
            $stmt = $conn->prepare("UPDATE settings SET value = '0' WHERE name = 'TELEGRAM_BOT_ENABLED'");
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Bot de Telegram deshabilitado';
            break;
            
        case 'enable_bot':
            // Habilitar bot
            $stmt = $conn->prepare("UPDATE settings SET value = '1' WHERE name = 'TELEGRAM_BOT_ENABLED'");
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Bot de Telegram habilitado';
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

$conn->close();

// Redireccionar de vuelta al panel
$redirect_url = $_POST['redirect'] ?? 'telegram_management.php';
header("Location: $redirect_url");
exit;
?>