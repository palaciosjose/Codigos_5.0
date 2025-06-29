<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';
check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

// ========================================
// FUNCIONES DE DIAGNÓSTICO SIMPLIFICADAS
// ========================================

function checkBotStatus($conn) {
    $status = ['overall' => 'ok', 'checks' => []];
    
    // 1. Verificar vendor/autoload.php
    $autoloadExists = file_exists('../vendor/autoload.php');
    $status['checks']['autoload'] = [
        'status' => $autoloadExists ? 'ok' : 'error',
        'message' => $autoloadExists ? 'Composer instalado correctamente' : 'Composer no instalado - ejecutar simple_install.php desde la raíz',
        'fixable' => !$autoloadExists
    ];
    
    // 2. Verificar configuración en base de datos
    $config = [];
    try {
        $config_res = $conn->query("SELECT      CASE          WHEN name = 'TELEGRAM_BOT_TOKEN' THEN 'token'         WHEN name = 'TELEGRAM_WEBHOOK_URL' THEN 'webhook'         ELSE LOWER(REPLACE(name, 'TELEGRAM_', ''))     END as setting_name,     value as setting_value      FROM settings      WHERE name LIKE 'TELEGRAM%'");
        if ($config_res) {
            while($row = $config_res->fetch_assoc()) {
                $config[$row['setting_name']] = $row['setting_value'];
            }
        }
    } catch (Exception $e) {
        // La tabla no existe aún
    }
    
    $token = $config['token'] ?? '';
    $webhook_url_db = $config['webhook'] ?? '';
    
    if (empty($token) || empty($webhook_url_db)) {
        $status['checks']['config_db'] = [
            'status' => 'error', 
            'message' => empty($config) ? 'Tabla de configuración no existe - usar "Crear Tablas"' : 'Token y URL del webhook no configurados'
        ];
        $status['overall'] = 'error';
    } else {
        $status['checks']['config_db'] = [
            'status' => 'ok', 
            'message' => 'La configuración del bot está guardada en la base de datos'
        ];
    }
    
    // 3. Verificar conexión con API de Telegram
    if ($status['overall'] === 'ok') {
        $apiUrl = "https://api.telegram.org/bot{$token}/getMe";
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data['ok'] ?? false) {
                $status['checks']['api'] = [
                    'status' => 'ok',
                    'message' => 'Conexión con API de Telegram exitosa - Bot: ' . ($data['result']['first_name'] ?? 'Sin nombre')
                ];
            } else {
                $status['checks']['api'] = [
                    'status' => 'error',
                    'message' => 'Token inválido o bot no encontrado'
                ];
                $status['overall'] = 'error';
            }
        } else {
            $status['checks']['api'] = [
                'status' => 'error',
                'message' => 'No se pudo conectar con la API de Telegram'
            ];
            $status['overall'] = 'error';
        }
    }
    
    // 4. Verificar estado del webhook
    if ($status['overall'] === 'ok') {
        $webhookUrl = "https://api.telegram.org/bot{$token}/getWebhookInfo";
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($webhookUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data['ok'] ?? false) {
                $currentWebhook = $data['result']['url'] ?? '';
                if (!empty($currentWebhook)) {
                    if ($currentWebhook === $webhook_url_db) {
                        $status['checks']['webhook'] = [
                            'status' => 'ok',
                            'message' => 'El Webhook está registrado y coincide con la configuración guardada'
                        ];
                    } else {
                        $status['checks']['webhook'] = [
                            'status' => 'warning',
                            'message' => 'El Webhook está registrado en Telegram, pero la URL no coincide con la guardada'
                        ];
                        if ($status['overall'] !== 'error') $status['overall'] = 'warning';
                    }
                } else {
                    $status['checks']['webhook'] = [
                        'status' => 'warning',
                        'message' => 'El token es válido, pero el Webhook no está configurado. Usa el botón de prueba para registrarlo'
                    ];
                    if ($status['overall'] !== 'error') $status['overall'] = 'warning';
                }
            }
        }
    }
    
    // 5. Verificar tablas de base de datos
    $requiredTables = ['telegram_bot_config', 'telegram_bot_logs'];
    $tablesExist = true;
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows == 0) {
                $tablesExist = false;
                $missingTables[] = $table;
            }
        } catch (Exception $e) {
            $tablesExist = false;
            $missingTables[] = $table;
        }
    }
    
    if (!$tablesExist) {
        $status['checks']['database'] = [
            'status' => 'error',
            'message' => 'Faltan tablas: ' . implode(', ', $missingTables) . ' - usar "Crear Tablas"'
        ];
        $status['overall'] = 'error';
    } else {
        $status['checks']['database'] = [
            'status' => 'ok',
            'message' => 'Todas las tablas requeridas existen'
        ];
    }
    
    // Determinar estado general
    foreach ($status['checks'] as $check) {
        if ($check['status'] === 'error') {
            $status['overall'] = 'error';
            break;
        } elseif ($check['status'] === 'warning' && $status['overall'] !== 'error') {
            $status['overall'] = 'warning';
        }
    }
    
    return $status;
}

function createTelegramTables($conn) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS telegram_bot_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS telegram_bot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            telegram_id BIGINT,
            action VARCHAR(100),
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_telegram_id (telegram_id),
            INDEX idx_created_at (created_at)
        )"
    ];
    
    foreach ($queries as $query) {
        $conn->query($query);
    }
    
    // Verificar si la tabla users tiene la columna telegram_id
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'telegram_id'");
    if ($check_column->num_rows == 0) {
        // Agregar columna telegram_id a la tabla users si no existe
        $conn->query("ALTER TABLE users ADD COLUMN telegram_id BIGINT NULL");
    }
}

function call_telegram_api($url, $postData = null) {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'description' => 'La extensión cURL no está instalada o habilitada.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) return ['ok' => false, 'description' => 'Error de cURL: ' . $error];
    if (empty($response)) return ['ok' => false, 'description' => 'Respuesta vacía desde la API de Telegram.'];
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['ok' => false, 'description' => 'La respuesta de Telegram no es un JSON válido.'];
    return $data;
}

// ========================================
// PROCESAMIENTO DE ACCIONES
// ========================================

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    
    if ($action === 'save_config') {
        try {
            $token = $conn->real_escape_string(trim($_POST['token'] ?? ''));
            $webhook = $conn->real_escape_string(trim($_POST['webhook'] ?? ''));
            
            if (empty($token) || empty($webhook)) {
                throw new Exception('Token y URL del webhook son obligatorios');
            }
            
            // Verificar que las tablas existan
            $check_table = $conn->query("SHOW TABLES LIKE 'telegram_bot_config'");
            if (!$check_table || $check_table->num_rows == 0) {
                createTelegramTables($conn);
            }
            
            // Guardar en tabla principal (settings)
            $conn->query("UPDATE settings SET value = '$token' WHERE name = 'TELEGRAM_BOT_TOKEN'");
            $conn->query("INSERT INTO settings (name, value, description, category) VALUES ('TELEGRAM_WEBHOOK_URL', '$webhook', 'URL del webhook de Telegram', 'telegram') ON DUPLICATE KEY UPDATE value = VALUES(value)");

            // Mantener compatibilidad con sistema legacy
            $conn->query("REPLACE INTO telegram_bot_config (setting_name, setting_value) VALUES ('token', '$token')");
            $conn->query("REPLACE INTO telegram_bot_config (setting_name, setting_value) VALUES ('webhook', '$webhook')");
            
            $message = 'Configuración guardada correctamente.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error al guardar: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    elseif ($action === 'test_telegram_connection') {
        header('Content-Type: application/json; charset=utf-8');
        $response_data = ['success' => false, 'error' => 'Error desconocido.'];
        try {
            $token = $_POST['token'] ?? '';
            $webhook_url = $_POST['webhook_url'] ?? '';
            $admin_telegram_id = $_POST['admin_telegram_id'] ?? '';
            if (empty($token) || empty($webhook_url) || empty($admin_telegram_id)) {
                throw new Exception("Token, URL y ID de Admin son requeridos.");
            }

            // 1. Validar Token
            $getMeData = call_telegram_api("https://api.telegram.org/bot{$token}/getMe");
            if (!$getMeData['ok']) {
                throw new Exception('Token inválido. Telegram dice: ' . ($getMeData['description'] ?? 'N/A'));
            }
            $bot_username = $getMeData['result']['username'];

            // 2. Registrar Webhook
            $setWebhookData = call_telegram_api("https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhook_url));
            if (!$setWebhookData['ok']) {
                throw new Exception("Token válido, pero no se pudo registrar el webhook. Error: " . ($setWebhookData['description'] ?? 'Verifica la URL.'));
            }

            // 3. Enviar mensaje de prueba
            $message = "🎯✅ Prueba de conexión exitosa!\n\nEl bot @{$bot_username} está configurado y el webhook ha sido registrado. ✅ Todo listo!";
            $sendData = call_telegram_api("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $admin_telegram_id, 'text' => $message]);
            
            if (!$sendData['ok']) {
                throw new Exception("Webhook OK, pero no se pudo enviar mensaje. Error: " . ($sendData['description'] ?? 'N/A'));
            }

            $response_data = ['success' => true, 'message' => '✅ Prueba completada! Webhook registrado y mensaje de confirmación enviado.'];
        } catch (Exception $e) {
            error_log("Error en test_telegram_connection: " . $e->getMessage());
            $response_data['error'] = $e->getMessage();
        }
        
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    elseif ($action === 'create_tables') {
        try {
            createTelegramTables($conn);
            $message = 'Tablas creadas/verificadas exitosamente';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error al crear tablas: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// ========================================
// CARGAR DATOS PARA EL PANEL
// ========================================

$status = checkBotStatus($conn);

// Configuración actual
$config = [];
try {
    $config_res = $conn->query("SELECT      CASE          WHEN name = 'TELEGRAM_BOT_TOKEN' THEN 'token'         WHEN name = 'TELEGRAM_WEBHOOK_URL' THEN 'webhook'         ELSE LOWER(REPLACE(name, 'TELEGRAM_', ''))     END as setting_name,     value as setting_value      FROM settings      WHERE name LIKE 'TELEGRAM%'");
    if ($config_res) {
        while($row = $config_res->fetch_assoc()) {
            $config[$row['setting_name']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    $config = [];
}

// Estadísticas mejoradas
$stats = ['total_logs' => 0, 'logs_today' => 0, 'unique_users' => 0];

try {
    $res_total = $conn->query("SELECT COUNT(*) as total FROM telegram_bot_logs");
    if($res_total) $stats['total_logs'] = $res_total->fetch_assoc()['total'];
    
    $res_today = $conn->query("SELECT COUNT(*) as total FROM telegram_bot_logs WHERE DATE(created_at) = CURDATE()");
    if($res_today) $stats['logs_today'] = $res_today->fetch_assoc()['total'];
    
    $res_users = $conn->query("SELECT COUNT(DISTINCT telegram_id) as total FROM telegram_bot_logs WHERE telegram_id IS NOT NULL");
    if($res_users) $stats['unique_users'] = $res_users->fetch_assoc()['total'];
} catch (Exception $e) {
    // Si las tablas no existen, mantener valores por defecto
}

// Usuarios vinculados
$linked_users = [];
try {
    $result_users = $conn->query("SELECT id, username, telegram_id, created_at FROM users WHERE telegram_id IS NOT NULL AND telegram_id != '' ORDER BY username ASC");
    if($result_users) { 
        while($row = $result_users->fetch_assoc()){ 
            // Obtener count de actividad
            $activity_count = 0;
            try {
                $activity_res = $conn->query("SELECT COUNT(*) as count FROM telegram_bot_logs WHERE telegram_id = '{$row['telegram_id']}'");
                if ($activity_res) {
                    $activity_count = $activity_res->fetch_assoc()['count'];
                }
            } catch (Exception $e) {
                // Si no hay tabla de logs, mantener 0
            }
            $row['activity_count'] = $activity_count;
            $linked_users[] = $row; 
        } 
    }
} catch (Exception $e) {
    // Si hay error con la tabla users, mantener array vacío
}

// ID de Telegram del admin actual
$admin_telegram_id = '';
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id) {
    try {
        $stmt = $conn->prepare("SELECT telegram_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $admin_telegram_id = $row['telegram_id'] ?? '';
        }
        $stmt->close();
    } catch (Exception $e) {
        // Si hay error, mantener vacío
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Bot de Telegram</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/modern_global.css">
    <link rel="stylesheet" href="../styles/modern_admin.css">
</head>
<body class="admin-page">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title"><i class="fab fa-telegram me-3"></i>Panel del Bot de Telegram</h1>
        <p class="mb-0 opacity-75">Diagnostica, configura y monitorea la integración de tu bot.</p>
    </div>
    
    <div class="p-4">
        <a href="admin.php" class="btn-back-modern">
            <i class="fas fa-arrow-left"></i> Volver al Panel Principal
        </a>
    </div>

    <?php if (!empty($message)): ?>
    <div class="mx-4 mb-3">
        <div class="alert alert-<?= $message_type ?>-admin">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    </div>
    <?php endif; ?>

    <ul class="nav nav-tabs nav-tabs-modern" id="telegramTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="diagnostico-tab" data-bs-toggle="tab" data-bs-target="#diagnostico" type="button">
                <i class="fas fa-stethoscope me-2"></i>Diagnóstico
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button">
                <i class="fas fa-cog me-2"></i>Configuración
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button">
                <i class="fas fa-chart-bar me-2"></i>Estadísticas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
                <i class="fas fa-users me-2"></i>Usuarios Vinculados
            </button>
        </li>
    </ul>

    <div class="tab-content" id="telegramTabContent">
        <!-- PESTAÑA DIAGNÓSTICO -->
        <div class="tab-pane fade show active" id="diagnostico" role="tabpanel">
            <div class="admin-card">
                <h3 class="admin-card-title">
                    <i class="fas fa-heartbeat me-2"></i>Estado del Bot
                </h3>
                
                <!-- Estado General -->
                <div class="alert alert-<?= $status['overall'] === 'ok' ? 'success' : ($status['overall'] === 'warning' ? 'warning' : 'danger') ?>-admin mb-4">
                    <i class="fas <?= $status['overall'] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                    <strong>Estado General:</strong> 
                    <?= $status['overall'] === 'ok' ? '¡Bot operacional!' : ($status['overall'] === 'warning' ? 'Funcionando con advertencias' : 'Requiere atención') ?>
                </div>

                <!-- Verificaciones Detalladas -->
                <?php foreach ($status['checks'] as $name => $check): ?>
                <div class="d-flex align-items-center p-3 mb-3 rounded" style="background: rgba(0,0,0,0.1); border-left: 4px solid <?= $check['status'] === 'ok' ? 'var(--accent-green)' : ($check['status'] === 'warning' ? '#ffc107' : 'var(--danger-red)') ?>;">
                    <div class="me-3">
                        <?php if ($check['status'] === 'ok'): ?>
                            <i class="fas fa-check-circle" style="color: var(--accent-green); font-size: 1.5rem;"></i>
                        <?php elseif ($check['status'] === 'warning'): ?>
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 1.5rem;"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle" style="color: var(--danger-red); font-size: 1.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1" style="color: var(--text-primary);"><?= ucfirst(str_replace('_', ' ', $name)) ?></h6>
                        <p class="mb-0" style="color: var(--text-info-light);"><?= htmlspecialchars($check['message']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Acciones Rápidas -->
                <div class="d-flex gap-2 flex-wrap mt-4">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="create_tables">
                        <button type="submit" class="btn btn-warning-admin">
                            <i class="fas fa-database"></i> Crear Tablas
                        </button>
                    </form>
                    <a href="?refresh=1" class="btn btn-info-admin">
                        <i class="fas fa-sync-alt"></i> Verificar Nuevamente
                    </a>
                </div>
                
                <?php if($status['overall'] !== 'ok'): ?>
                <div class="alert alert-info-admin mt-3">
                    <i class="fas fa-info-circle"></i>
                    <div class="mt-2">
                        <strong>Instrucciones para corregir problemas:</strong>
                        <ul class="mb-0 mt-2">
                            <?php if (isset($status['checks']['autoload']) && $status['checks']['autoload']['status'] === 'error'): ?>
                            <li><strong>Instalar Composer:</strong> Ve a <a href="../simple_install.php" target="_blank" style="color: var(--accent-green); text-decoration: underline;">../simple_install.php</a> y sigue las instrucciones</li>
                            <li><strong>Alternativa:</strong> Ejecuta <code>composer install</code> desde la raíz del proyecto via SSH/Terminal</li>
                            <?php else: ?>
                            <li>Ve a la pestaña de <strong>Configuración</strong>, configura el token y URL</li>
                            <li>Usa el botón de <strong>Probar y Registrar</strong> para registrar el webhook</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PESTAÑA CONFIGURACIÓN -->
        <div class="tab-pane fade" id="config" role="tabpanel">
            <div class="admin-card">
                <h3 class="admin-card-title">
                    <i class="fas fa-tools me-2"></i>Configuración del Bot
                </h3>
                <p class="text-muted">Guarda tu token y la URL del webhook. Usa el botón de prueba para registrar tu bot en los servidores de Telegram.</p>
                
                <form method="post" class="mt-3">
                    <input type="hidden" name="action" value="save_config">
                    
                    <div class="form-group-admin">
                        <label for="token" class="form-label-admin">
                            <i class="fas fa-key me-2"></i>Token del Bot
                        </label>
                        <input type="text" id="token" name="token" class="form-control-admin" 
                               value="<?= htmlspecialchars($config['token'] ?? '') ?>" 
                               placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxyz">
                        <div class="text-muted">
                            Obtén tu token hablando con @BotFather en Telegram. Formato: número:letras_y_números
                        </div>
                    </div>
                    
                    <div class="form-group-admin">
                        <label for="webhook" class="form-label-admin">
                            <i class="fas fa-link me-2"></i>URL del Webhook
                        </label>
                        <input type="url" id="webhook" name="webhook" class="form-control-admin" 
                               value="<?= htmlspecialchars($config['webhook'] ?? '') ?>" 
                               placeholder="Se generará automáticamente al pegar el token">
                        <div class="text-muted">
                            URL HTTPS donde Telegram enviará las actualizaciones. Debe ser accesible públicamente.
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary-admin">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                        <button type="button" id="test-connection-btn" class="btn btn-info-admin">
                            <i class="fas fa-paper-plane me-2"></i>Probar y Registrar
                        </button>
                    </div>
                </form>
                
                <div id="test-results" class="mt-4" style="display: none;"></div>
            </div>

            <!-- Información Actual -->
            <?php if (!empty($config)): ?>
            <div class="admin-card">
                <h4 class="admin-card-title">
                    <i class="fas fa-info-circle me-2"></i>Configuración Actual
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Token configurado:</strong> 
                            <?= !empty($config['token']) ? 'Sí (' . substr($config['token'], 0, 10) . '...)' : 'No' ?>
                        </p>
                        <p><strong>Webhook URL:</strong> 
                            <?= !empty($config['webhook']) ? htmlspecialchars($config['webhook']) : 'No configurado' ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tu Telegram ID:</strong> 
                            <?= !empty($admin_telegram_id) ? $admin_telegram_id : 'No vinculado - envía /start al bot para vincularte' ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- PESTAÑA ESTADÍSTICAS -->
        <div class="tab-pane fade" id="stats" role="tabpanel">
            <div class="admin-card">
                <h3 class="admin-card-title">
                    <i class="fas fa-chart-line me-2"></i>Estadísticas de Uso
                </h3>
                
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="p-4" style="background: rgba(0,0,0,0.1); border-radius: 10px;">
                            <h2 style="color: var(--accent-green);"><?= $stats['total_logs'] ?></h2>
                            <p style="color: var(--text-info-light);">Total de Interacciones</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4" style="background: rgba(0,0,0,0.1); border-radius: 10px;">
                            <h2 style="color: var(--accent-green);"><?= $stats['unique_users'] ?></h2>
                            <p style="color: var(--text-info-light);">Usuarios Únicos</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-4" style="background: rgba(0,0,0,0.1); border-radius: 10px;">
                            <h2 style="color: var(--accent-green);"><?= $stats['logs_today'] ?></h2>
                            <p style="color: var(--text-info-light);">Interacciones Hoy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PESTAÑA USUARIOS VINCULADOS -->
        <div class="tab-pane fade" id="users" role="tabpanel">
            <div class="admin-card">
                <h3 class="admin-card-title">
                    <i class="fas fa-users-cog me-2"></i>Usuarios Vinculados (<?= count($linked_users) ?>)
                </h3>
                <p class="text-muted">Usuarios del sistema que han asociado un ID de Telegram.</p>
                
                <?php if (empty($linked_users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users" style="font-size: 3rem; color: var(--text-info-light); opacity: 0.5;"></i>
                    <p style="color: var(--text-info-light); margin-top: 1rem;">
                        No hay usuarios vinculados con Telegram aún.
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table-admin">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Telegram ID</th>
                                <th>Interacciones</th>
                                <th>Vinculado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_users as $user): ?>
                            <tr>
                                <td style="color: var(--text-primary);"><?= htmlspecialchars($user['username']) ?></td>
                                <td style="color: var(--accent-green);"><?= htmlspecialchars($user['telegram_id']) ?></td>
                                <td style="color: var(--text-info-light);"><?= $user['activity_count'] ?></td>
                                <td style="color: var(--text-info-light);"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tokenInput = document.getElementById('token');
        const webhookInput = document.getElementById('webhook');
        const testButton = document.getElementById('test-connection-btn');
        const testResultsContainer = document.getElementById('test-results');
        const adminTelegramId = '<?= $admin_telegram_id ?>';

        function fillWebhookUrl() {
            if (tokenInput.value.trim() !== '' && webhookInput.value.trim() === '') {
                const currentDomain = window.location.hostname;
                const basePath = window.location.pathname.split('/admin/')[0];
                const finalPath = (basePath === '/' ? '' : basePath);
                webhookInput.value = `https://${currentDomain}${finalPath}/telegram_bot/webhook.php`;
            }
        }
        tokenInput.addEventListener('input', fillWebhookUrl);

        testButton.addEventListener('click', function() {
            const token = tokenInput.value.trim();
            const webhookUrl = webhookInput.value.trim();

            if (!token || !webhookUrl) {
                showTestResult('error', 'Por favor, introduce el Token y la URL del Webhook.');
                return;
            }
            if (!adminTelegramId) {
                showTestResult('error', 'El administrador actual no tiene un ID de Telegram configurado. No se puede enviar un mensaje de prueba.');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Probando...';
            showTestResult('info', 'Realizando prueba de conexión y registro...');

            const formData = new FormData();
            formData.append('action', 'test_telegram_connection');
            formData.append('token', token);
            formData.append('webhook_url', webhookUrl);
            formData.append('admin_telegram_id', adminTelegramId);
            
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => { throw new Error('Error del servidor: ' + text) });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showTestResult('success', data.message);
                } else {
                    showTestResult('error', data.error || 'Ocurrió un error desconocido.');
                }
            })
            .catch(error => {
                showTestResult('error', 'Error de red o respuesta inesperada: ' + error.message);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Probar y Registrar';
                setTimeout(() => location.reload(), 3000); 
            });
        });

        function showTestResult(type, message) {
            testResultsContainer.style.display = 'block';
            let alertClass = '', iconClass = '';
            switch(type) {
                case 'success': alertClass = 'alert-success-admin'; iconClass = 'fa-check-circle'; break;
                case 'error': alertClass = 'alert-danger-admin'; iconClass = 'fa-times-circle'; break;
                default: alertClass = 'alert-info-admin'; iconClass = 'fa-info-circle'; break;
            }
            testResultsContainer.innerHTML = `<div class="alert-admin ${alertClass}"><i class="fas ${iconClass}"></i><span>${message}</span></div>`;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            const tabElement = document.querySelector(`#${tab}-tab`);
            if (tabElement) new bootstrap.Tab(tabElement).show();
        }
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(button => {
            button.addEventListener('shown.bs.tab', event => {
                const newTabId = event.target.getAttribute('data-bs-target').substring(1);
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('tab', newTabId);
                window.history.pushState({path: newUrl.href}, '', newUrl.href);
            });
        });
    });
</script>

<style>
/* Estilos adicionales para mejorar la apariencia */
.alert-success-admin {
    background: rgba(50, 255, 181, 0.1);
    border: 1px solid var(--accent-green);
    color: var(--accent-green);
}

.alert-warning-admin {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid #ffc107;
    color: #ffc107;
}

.alert-danger-admin {
    background: rgba(255, 77, 77, 0.1);
    border: 1px solid var(--danger-red);
    color: var(--danger-red);
}

.alert-info-admin {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid #06b6d4;
    color: #06b6d4;
}

.btn-warning-admin {
    background: transparent;
    color: #ffc107;
    border: 1px solid #ffc107;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-warning-admin:hover {
    background: rgba(255, 193, 7, 0.1);
    transform: translateY(-1px);
}

.btn-info-admin {
    background: transparent;
    color: var(--accent-green);
    border: 1px solid var(--accent-green);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-info-admin:hover {
    background: var(--glow-green);
    transform: translateY(-1px);
}

/* Variables de colores que faltaban */
:root {
    --text-info-light: #C4B5FD;
    --text-success-light: #90EE90;
    --danger-red: #ff4d4d;
}
</style>
</body>
</html>