<?php
/**
 * Instalador del Sistema con Bot de Telegram Integrado
 * Versión 3.0 - CORREGIDA Y COMPLETA
 */

session_start();

// ==========================================
// DEFINIR RUTAS BASE 
// ==========================================
define('INSTALLER_MODE', true);
define('PROJECT_ROOT', dirname(__DIR__));
define('LICENSE_DIR', PROJECT_ROOT . '/license');
define('LICENSE_FILE', LICENSE_DIR . '/license.dat');

if (!file_exists(LICENSE_DIR)) {
    if (!mkdir(LICENSE_DIR, 0755, true)) {
        die('Error: No se pudo crear el directorio de licencias: ' . LICENSE_DIR);
    }
}

$license_htaccess_content = "Deny from all\n<Files \"*.dat\">\nDeny from all\n</Files>";
file_put_contents(LICENSE_DIR . '/.htaccess', $license_htaccess_content);

require_once PROJECT_ROOT . '/license_client.php';
require_once 'basededatos.php';
require_once '../funciones.php';

header('Content-Type: text/html; charset=utf-8');

$required_extensions = [
    'session' => 'Para manejar sesiones.',
    'imap' => 'Para conectarse y manejar correos a través de IMAP.',
    'mbstring' => 'Para manejar cadenas multibyte.',
    'fileinfo' => 'Para manejar la detección de tipos MIME.',
    'json' => 'Para manejar datos en formato JSON.',
    'openssl' => 'Para manejar conexiones seguras y cifrado.',
    'filter' => 'Para la sanitización y validación de datos.',
    'ctype' => 'Para la verificación de tipos de caracteres.',
    'iconv' => 'Para la conversión de conjuntos de caracteres.',
    'curl' => 'Para realizar peticiones HTTP (requerido para verificación de licencia).'
];

$php_version_required = '8.1.0';
$php_version = phpversion();
$extensions_status = [];

foreach ($required_extensions as $ext => $description) {
    $extensions_status[$ext] = extension_loaded($ext);
}

$all_extensions_loaded = !in_array(false, $extensions_status, true);
$php_version_valid = version_compare($php_version, $php_version_required, '>=');

$current_step = $_GET['step'] ?? 'requirements';
$license_client = new ClientLicense();

function verificarSistemaLicencias() {
    $diagnostico = [
        'license_dir_exists' => file_exists(LICENSE_DIR),
        'license_dir_writable' => is_writable(dirname(LICENSE_DIR)),
        'license_file_path' => LICENSE_FILE,
        'project_root' => PROJECT_ROOT,
        'current_working_dir' => getcwd(),
        'installer_dir' => __DIR__
    ];
    
    return $diagnostico;  
}                      

$diagnostico_licencias = verificarSistemaLicencias();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_license'])) {
    $license_key = trim($_POST['license_key'] ?? '');
    
    if (empty($license_key)) {
        $license_error = 'Por favor, ingrese una clave de licencia válida.';
    } else {
        try {
            if (!is_writable(LICENSE_DIR)) {
                throw new Exception('El directorio de licencias no tiene permisos de escritura: ' . LICENSE_DIR);
            }
            
            $activation_result = $license_client->activateLicense($license_key);
            
            if ($activation_result['success']) {
                $verification_attempts = 0;
                $max_attempts = 3;
                $license_verified = false;
                
                while ($verification_attempts < $max_attempts && !$license_verified) {
                    sleep(1);
                    $license_verified = $license_client->isLicenseValid();
                    $verification_attempts++;
                }
                
                if ($license_verified) {
                    $_SESSION['license_activated'] = true;
                    $_SESSION['license_key'] = $license_key;
                    $_SESSION['license_verified_at'] = time();
                    $license_success = 'Licencia activada y verificada exitosamente.';
                } else {
                    $_SESSION['license_activated'] = true;
                    $_SESSION['license_key'] = $license_key;
                    $_SESSION['license_verified_at'] = time();
                    $license_warning = 'Licencia activada exitosamente, pero la verificación tardó más de lo esperado. Continuando con la instalación.';
                }
            } else {
                $license_error = $activation_result['message'];
            }
        } catch (Exception $e) {
            $license_error = 'Error durante la activación: ' . $e->getMessage();
            error_log('Error activación licencia: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configure'])) {
    try {
        $license_valid = false;
        
        if ($license_client->isLicenseValid()) {
            $license_valid = true;
        } elseif (isset($_SESSION['license_activated']) && $_SESSION['license_activated']) {
            $time_since_activation = time() - ($_SESSION['license_verified_at'] ?? 0);
            if ($time_since_activation < 300) {
                $license_valid = true;
            }
        }
        
        if (!$license_valid) {
            throw new Exception('Debe activar una licencia válida antes de continuar con la instalación.');
        }
        
        $validation_errors = validateInstallationData($_POST);
        if (!empty($validation_errors)) {
            throw new Exception(implode('<br>', $validation_errors));
        }
        
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_password = $_POST['db_password'];
        $admin_user = trim($_POST['admin_user']);
        $admin_password = $_POST['admin_password'];
        
        createConfigurationFiles($db_host, $db_name, $db_user, $db_password);
        testDatabaseConnection($db_host, $db_user, $db_password);
        $pdo = setupDatabase($db_host, $db_name, $db_user, $db_password);
        
        // CREAR TODA LA ESTRUCTURA DE BASE DE DATOS (INCLUYENDO BOT)
        createCompleteDatabase($pdo);
        insertInitialData($pdo, $admin_user, $admin_password);
        setupFileSystem();
        
        ensureLicenseIsSaved($_SESSION['license_key'] ?? '');
        finalizeInstallation($pdo);
        
        $installation_successful = true;
        
        unset($_SESSION['license_activated']);
        unset($_SESSION['license_key']);
        unset($_SESSION['license_verified_at']);
        
    } catch (Exception $e) {
        $installation_error = true;
        $error_message = $e->getMessage();
        error_log("Error en instalación: " . $error_message);
    }
}

function ensureLicenseIsSaved($license_key) {
    if (empty($license_key)) {
        return;
    }
    
    global $license_client;
    
    if (!file_exists(LICENSE_FILE)) {
        try {
            error_log('Reactivando licencia porque no se encontró archivo en: ' . LICENSE_FILE);
            $activation_result = $license_client->activateLicense($license_key);
            if (!$activation_result['success']) {
                throw new Exception('No se pudo reactivar la licencia durante la instalación');
            }
            error_log('Licencia reactivada exitosamente en: ' . LICENSE_FILE);
        } catch (Exception $e) {
            error_log('Error reactivando licencia durante instalación: ' . $e->getMessage());
        }
    } else {
        error_log('Archivo de licencia encontrado correctamente en: ' . LICENSE_FILE);
    }
}

function validateInstallationData($data) {
    $errors = [];
    
    if (empty($data['db_host'])) $errors[] = "El servidor de BD es obligatorio";
    if (empty($data['db_name'])) $errors[] = "El nombre de la BD es obligatorio";
    if (empty($data['db_user'])) $errors[] = "El usuario de BD es obligatorio";
    if (empty($data['admin_user'])) $errors[] = "El usuario admin es obligatorio";
    if (strlen($data['admin_user']) < 3) $errors[] = "El usuario admin debe tener al menos 3 caracteres";
    if (empty($data['admin_password'])) $errors[] = "La contraseña admin es obligatoria";
    if (strlen($data['admin_password']) < 6) $errors[] = "La contraseña admin debe tener al menos 6 caracteres";
    
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $data['db_name'])) {
        $errors[] = "El nombre de BD solo puede contener letras, números, guiones y puntos";
    }
    
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $data['admin_user'])) {
        $errors[] = "El usuario admin solo puede contener letras, números y guiones";
    }
    
    return $errors;
}

function testDatabaseConnection($host, $user, $password) {
    try {
        $test_conn = new PDO("mysql:host={$host}", $user, $password);
        $test_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $test_conn = null;
    } catch (PDOException $e) {
        throw new Exception("No se pudo conectar a la base de datos: " . $e->getMessage());
    }
}

function createConfigurationFiles($db_host, $db_name, $db_user, $db_password) {
    $db_host_escaped = addslashes($db_host);
    $db_name_escaped = addslashes($db_name);
    $db_user_escaped = addslashes($db_user);
    $db_password_escaped = addslashes($db_password);

    $credentials_content = "<?php
// Archivo generado automáticamente durante la instalación
\$db_host = '{$db_host_escaped}';
\$db_user = '{$db_user_escaped}';
\$db_password = '{$db_password_escaped}';
\$db_name = '{$db_name_escaped}';
?>";

    $credentials_path = dirname(__DIR__) . '/config/db_credentials.php';
    if (!file_put_contents($credentials_path, $credentials_content)) {
        throw new Exception("No se pudo crear el archivo db_credentials.php");
    }
}

function setupDatabase($db_host, $db_name, $db_user, $db_password) {
    $pdo = new PDO("mysql:host={$db_host}", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci");
    $pdo->exec("USE `{$db_name}`");
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    return $pdo;
}

// ============================================
// FUNCIÓN UNIFICADA PARA CREAR TODA LA BD
// ============================================
function createCompleteDatabase($pdo) {
    echo "<div class='alert alert-info'><h6>Creando estructura completa de base de datos...</h6></div>";
    flush();
    
    $tables = [
        // --- Tablas Principales del Sistema ---
        "CREATE TABLE IF NOT EXISTS `admin` (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `users` (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, telegram_id BIGINT NULL UNIQUE, telegram_username VARCHAR(255) NULL, last_telegram_activity TIMESTAMP NULL, status TINYINT(1) DEFAULT 1, role ENUM('user', 'admin', 'superadmin') DEFAULT 'user', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `settings` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, value TEXT NOT NULL, description TEXT, category VARCHAR(50) DEFAULT 'general') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `email_servers` (id INT AUTO_INCREMENT PRIMARY KEY, server_name VARCHAR(50) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, imap_server VARCHAR(100) NOT NULL, imap_port INT NOT NULL DEFAULT 993, imap_user VARCHAR(100) NOT NULL, imap_password VARCHAR(100) NOT NULL, priority INT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        
        // --- Tablas de Plataformas y Asuntos ---
        "CREATE TABLE IF NOT EXISTS `platforms` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description TEXT, sort_order INT NOT NULL DEFAULT 0, status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `platform_subjects` (id INT AUTO_INCREMENT PRIMARY KEY, platform_id INT NOT NULL, subject VARCHAR(255) NOT NULL, FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        
        // --- Tablas de Permisos y Asignaciones ---
        "CREATE TABLE IF NOT EXISTS `authorized_emails` (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE, description TEXT, status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `user_authorized_emails` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, authorized_email_id INT NOT NULL, assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, assigned_by INT DEFAULT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (authorized_email_id) REFERENCES authorized_emails(id) ON DELETE CASCADE, UNIQUE KEY unique_user_email (user_id, authorized_email_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `user_platform_subjects` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, platform_id INT NOT NULL, subject_keyword VARCHAR(255), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE, UNIQUE KEY unique_assignment (user_id, platform_id, subject_keyword(191))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",

        // --- Tablas de Logs (Sistema Web y Bot) ---
        "CREATE TABLE IF NOT EXISTS `logs` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email_consultado VARCHAR(100) NOT NULL, plataforma VARCHAR(50) NOT NULL, ip VARCHAR(45), fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP, resultado TEXT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `search_logs` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), platform VARCHAR(100), status ENUM('searching', 'found', 'not_found', 'error') DEFAULT 'searching', result_details TEXT, telegram_chat_id BIGINT NULL, source VARCHAR(50) DEFAULT 'web', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, completed_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",
        "CREATE TABLE IF NOT EXISTS `telegram_bot_logs` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, telegram_id BIGINT, action VARCHAR(50), details JSON, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `telegram_activity_log` (id INT AUTO_INCREMENT PRIMARY KEY, telegram_id BIGINT NOT NULL, action VARCHAR(100) NOT NULL, details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci",

        // --- Tablas de Configuración Específica del Bot ---
        "CREATE TABLE IF NOT EXISTS `telegram_bot_config` (id INT AUTO_INCREMENT PRIMARY KEY, setting_name VARCHAR(50) UNIQUE, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);

        } catch (Exception $e) {

        }
    }

}

function insertInitialData($pdo, $admin_user, $admin_password) {
    $pdo->beginTransaction();
    
    try {
        insertSystemSettings($pdo);
        insertDefaultPlatforms($pdo);
        insertSystemUsers($pdo, $admin_user, $admin_password);
        insertExampleEmailsAndAssignments($pdo);
        insertDefaultServers($pdo);
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Error insertando datos iniciales: " . $e->getMessage());
    }
}

// ============================================
// CONFIGURACIONES UNIFICADAS Y ORGANIZADAS
// ============================================
function insertSystemSettings($pdo) {
    $settings = [
        // === CONFIGURACIONES GENERALES ===
        ['enlace_global_titulo', 'StreamDigi', 'Título que aparece en el header', 'general'],
        ['PAGE_TITLE', 'Consulta tu Código', 'Título de la página principal', 'general'],
        ['LOGO', 'logo.png', 'Nombre del archivo de logo', 'general'],
        
        // === CONFIGURACIONES DE ENLACES ===
        ['enlace_global_1', 'https://', 'Enlace del botón 1 en el header', 'enlaces'],
        ['enlace_global_1_texto', 'Ir a Página web', 'Texto del botón 1 en el header', 'enlaces'],
        ['enlace_global_2', 'https://t.me/', 'Enlace del botón 2 en el header', 'enlaces'],
        ['enlace_global_2_texto', 'Ir a Telegram', 'Texto del botón 2 en el header', 'enlaces'],
        ['enlace_global_numero_whatsapp', '000000', 'Número de WhatsApp para contacto', 'enlaces'],
        ['enlace_global_texto_whatsapp', 'Hola, necesito soporte técnico', 'Mensaje predeterminado para WhatsApp', 'enlaces'],
        ['ID_VENDEDOR', '0', 'ID del vendedor para enlaces de afiliados', 'enlaces'],
        
        // === CONFIGURACIONES DE AUTENTICACIÓN ===
        ['REQUIRE_LOGIN', '1', 'Requerir inicio de sesión para todos los usuarios', 'auth'],
        ['EMAIL_AUTH_ENABLED', '1', 'Habilitar filtro de correos electrónicos autorizados', 'auth'],
        ['USER_EMAIL_RESTRICTIONS_ENABLED', '1', 'Activar restricciones de correos por usuario', 'auth'],
        ['USER_SUBJECT_RESTRICTIONS_ENABLED', '1', 'Activar restricciones de asuntos por usuario', 'auth'],
        ['ADMIN_EMAIL_OVERRIDE', '1', 'Administradores pueden acceder a todos los emails', 'auth'],
        
        // === CONFIGURACIONES DE PERFORMANCE ===
        ['EMAIL_QUERY_TIME_LIMIT_MINUTES', '30', 'Tiempo máximo para considerar emails válidos', 'performance'],
        ['TIMEZONE_DEBUG_HOURS', '48', 'Horas hacia atrás para búsqueda inicial IMAP', 'performance'],
        ['IMAP_CONNECTION_TIMEOUT', '8', 'Tiempo límite para conexiones IMAP (segundos)', 'performance'],
        ['IMAP_SEARCH_OPTIMIZATION', '1', 'Activar optimizaciones de búsqueda IMAP', 'performance'],
        ['EARLY_SEARCH_STOP', '1', 'Parar búsqueda al encontrar primer resultado', 'performance'],
        ['TRUST_IMAP_DATE_FILTER', '1', 'Confiar en el filtrado de fechas IMAP', 'performance'],
        ['USE_PRECISE_IMAP_SEARCH', '1', 'Usar búsquedas IMAP más precisas', 'performance'],
        ['MAX_EMAILS_TO_CHECK', '35', 'Número máximo de emails a verificar por consulta', 'performance'],
        ['IMAP_SEARCH_TIMEOUT', '30', 'Tiempo límite para búsquedas IMAP en segundos', 'performance'],
        
        // === CONFIGURACIONES DE CACHE ===
        ['CACHE_ENABLED', '1', 'Activar sistema de cache para mejorar performance', 'cache'],
        ['CACHE_TIME_MINUTES', '5', 'Tiempo de vida del cache en minutos', 'cache'],
        ['CACHE_MEMORY_ENABLED', '1', 'Activar cache en memoria para consultas repetidas', 'cache'],
        
        // === CONFIGURACIONES DEL BOT DE TELEGRAM ===
        ['TELEGRAM_BOT_ENABLED', '1', 'Habilitar bot de Telegram', 'telegram'],
        ['TELEGRAM_RATE_LIMIT', '30', 'Límite de solicitudes por minuto para Telegram', 'telegram'],
        ['TELEGRAM_LOG_ACTIVITY', '1', 'Registrar actividad del bot de Telegram', 'telegram'],
        ['TELEGRAM_WEBHOOK_ENABLED', '1', 'Habilitar webhook de Telegram', 'telegram'],
        ['MAX_TELEGRAM_REQUESTS_PER_USER', '100', 'Máximo solicitudes por usuario por día vía Telegram', 'telegram'],
        ['TELEGRAM_COMMAND_COOLDOWN', '2', 'Tiempo de espera entre comandos en segundos', 'telegram'],
        ['TELEGRAM_ADMIN_NOTIFICATIONS', '1', 'Notificar a admins sobre actividad sospechosa', 'telegram'],
        
        // === CONFIGURACIONES DE LOGS ===
        ['PERFORMANCE_LOGGING', '0', 'Activar logs de rendimiento (temporal para debugging)', 'logs'],
        ['TELEGRAM_LOG_RETENTION_DAYS', '30', 'Días para mantener logs de Telegram', 'logs'],
        ['SEARCH_LOG_RETENTION_DAYS', '90', 'Días para mantener logs de búsqueda', 'logs'],
        
        // === CONFIGURACIONES DE SISTEMA ===
        ['LICENSE_PROTECTED', '1', 'Sistema protegido por licencia', 'system'],
        ['INSTALLED', '0', 'Indica si el sistema está instalado correctamente', 'system']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (name, value, description, category) VALUES (?, ?, ?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

function insertSystemUsers($pdo, $admin_user, $admin_password) {
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // Crear usuario admin con role
    $stmt_user = $pdo->prepare("INSERT INTO users (username, password, telegram_id, status, role) VALUES (?, ?, ?, 1, 'admin')");
    $stmt_user->execute([$admin_user, $hashed_password, null]);
    $admin_user_id = $pdo->lastInsertId();
    
    // Crear entrada en tabla admin
    $stmt_admin = $pdo->prepare("INSERT INTO admin (id, username, password) VALUES (?, ?, ?)");
    $stmt_admin->execute([$admin_user_id, $admin_user, $hashed_password]);
    
    // Crear usuario cliente de ejemplo
    $cliente_password = password_hash('cliente123', PASSWORD_DEFAULT);
    $stmt_cliente = $pdo->prepare("INSERT INTO users (username, password, telegram_id, status, role) VALUES (?, ?, ?, 1, 'user')");
    $stmt_cliente->execute(['cliente', $cliente_password, null]);
}

function insertExampleEmailsAndAssignments($pdo) {
    $example_emails = [
        'ejemplo1@gmail.com',
        'ejemplo2@outlook.com',
        'test@yahoo.com'
    ];
    
    $stmt_email = $pdo->prepare("INSERT IGNORE INTO authorized_emails (email) VALUES (?)");
    $email_ids = [];
    
    foreach ($example_emails as $email) {
        $stmt_email->execute([$email]);
        $email_id = $pdo->lastInsertId();
        if ($email_id == 0) {
            $stmt_get = $pdo->prepare("SELECT id FROM authorized_emails WHERE email = ?");
            $stmt_get->execute([$email]);
            $email_id = $stmt_get->fetchColumn();
        }
        $email_ids[] = $email_id;
    }
    
    $stmt_get_cliente = $pdo->prepare("SELECT id FROM users WHERE username = 'cliente'");
    $stmt_get_cliente->execute();
    $cliente_id = $stmt_get_cliente->fetchColumn();
    
    if ($cliente_id && !empty($email_ids)) {
        $stmt_assign = $pdo->prepare("INSERT IGNORE INTO user_authorized_emails (user_id, authorized_email_id, assigned_by) VALUES (?, ?, ?)");
        foreach ($email_ids as $email_id) {
            $stmt_assign->execute([$cliente_id, $email_id, 1]);
        }
    }
}

function insertDefaultPlatforms($pdo) {
    $platforms = [
        'Netflix' => [
            'description' => 'Servicio de streaming Netflix',
            'subjects' => [
                'Tu código de acceso temporal de Netflix',
                'Importante: Cómo actualizar tu Hogar con Netflix',
                'Netflix: Tu código de inicio de sesión',
                'Completa tu solicitud de restablecimiento de contraseña'
            ]
        ],
        'Disney+' => [
            'description' => 'Servicio de streaming Disney Plus',
            'subjects' => [
                'Tu código de acceso único para Disney+',
                'Disney+: Verificación de cuenta',
                'Disney+: Código de seguridad',
                'Disney+: Actualización de perfil'
            ]
        ],
        'Prime Video' => [
            'description' => 'Amazon Prime Video',
            'subjects' => [
                'amazon.com: Sign-in attempt',
                'amazon.com: Intento de inicio de sesión',
                'Amazon Prime: Código de verificación',
                'Amazon: Actividad inusual en tu cuenta'
            ]
        ],
        'MAX' => [
            'description' => 'Servicio de streaming MAX',
            'subjects' => [
                'Tu código de acceso MAX',
                'MAX: Intento de inicio de sesión',
                'MAX: Tu código de verificación',
                'MAX: Actualización de tu cuenta'
            ]
        ],
        'Spotify' => [
            'description' => 'Servicio de música Spotify',
            'subjects' => [
                'Spotify: Código de verificación',
                'Spotify: Cambio de contraseña solicitado',
                'Spotify: Nuevo inicio de sesión detectado',
                'Spotify: Confirma tu dirección de email'
            ]
        ],
        'PayPal' => [
            'description' => 'Servicio de pagos PayPal',
            'subjects' => [
                'PayPal: Código de verificación',
                'PayPal: Actividad inusual',
                'PayPal: Confirma tu identidad',
                'PayPal: Nuevo dispositivo detectado'
            ]
        ],
        'Crunchyroll' => [
            'description' => 'Servicio de anime Crunchyroll',
            'subjects' => [
                'Crunchyroll: Código de acceso',
                'Crunchyroll: Actualización de cuenta',
                'Crunchyroll: Solicitud de inicio de sesión',
                'Crunchyroll: Restablecimiento de contraseña'
            ]
        ],
        'Paramount+' => [
            'description' => 'Servicio de streaming Paramount Plus',
            'subjects' => [
                'Paramount Plus: Código de acceso',
                'Paramount Plus: Actualización de cuenta',
                'Paramount Plus: Solicitud de inicio de sesión',
                'Paramount Plus: Restablecimiento de contraseña'
            ]
        ],
        'ChatGPT' => [
            'description' => 'OpenAI ChatGPT',
            'subjects' => [
                'Cambio de Contraseña',
                'Cambio de Correo Electrónico',
                'Cambio de Nombre',
                'Cambio de Cuenta'
            ]
        ]
    ];

    $stmt_platform = $pdo->prepare("INSERT IGNORE INTO platforms (name, description, sort_order, status) VALUES (?, ?, ?, 1)");
    $stmt_subject = $pdo->prepare("INSERT INTO platform_subjects (platform_id, subject) VALUES (?, ?)");

    $sort_order = 0;
    foreach ($platforms as $platform_name => $platform_data) {
        $stmt_platform->execute([$platform_name, $platform_data['description'], $sort_order]);
        $platform_id = $pdo->lastInsertId();
        
        if ($platform_id == 0) {
            $stmt_find = $pdo->prepare("SELECT id FROM platforms WHERE name = ?");
            $stmt_find->execute([$platform_name]);
            $platform_id = $stmt_find->fetchColumn();
        }

        if ($platform_id) {
            foreach ($platform_data['subjects'] as $subject) {
                $stmt_subject->execute([$platform_id, $subject]);
            }
        }
        $sort_order++;
    }
}

function insertDefaultServers($pdo) {
    $default_servers = [
        ["SERVIDOR_1", 0, "imap.gmail.com", 993, "usuario1@gmail.com", "", 1],
        ["SERVIDOR_2", 0, "imap.gmail.com", 993, "usuario2@gmail.com", "", 2],
        ["SERVIDOR_3", 0, "imap.gmail.com", 993, "usuario3@gmail.com", "", 3],
        ["SERVIDOR_4", 0, "outlook.office365.com", 993, "usuario4@outlook.com", "", 4],
        ["SERVIDOR_5", 0, "imap.mail.yahoo.com", 993, "usuario5@yahoo.com", "", 5]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO email_servers (server_name, enabled, imap_server, imap_port, imap_user, imap_password, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($default_servers as $server) {
        $stmt->execute($server);
    }
}

function setupFileSystem() {
    $directories = [
        PROJECT_ROOT . '/cache/' => 0755,
        PROJECT_ROOT . '/cache/data/' => 0777,
        PROJECT_ROOT . '/images/logo/' => 0755,
        PROJECT_ROOT . '/images/fondo/' => 0755,
        LICENSE_DIR => 0755
    ];
    
    foreach ($directories as $dir => $permissions) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, $permissions, true)) {
                throw new Exception("No se pudo crear el directorio: {$dir}");
            }
        }
        chmod($dir, $permissions);
    }
    
    $htaccess_content = "# Proteger carpeta de cache\nDeny from all\n<Files \"*.json\">\nDeny from all\n</Files>";
    file_put_contents(PROJECT_ROOT . '/cache/data/.htaccess', $htaccess_content);
    
    $license_htaccess = "Deny from all\n<Files \"*.dat\">\nDeny from all\n</Files>";
    file_put_contents(LICENSE_DIR . '/.htaccess', $license_htaccess);
    
    $files = [
        PROJECT_ROOT . '/cache/cache_helper.php' => 0755,
        PROJECT_ROOT . '/config/config.php' => 0644
    ];
    
    foreach ($files as $file => $permissions) {
        if (file_exists($file)) {
            chmod($file, $permissions);
        }
    }
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function cleanupInstaller() {
    $installDir = __DIR__;
    $projectRoot = dirname($installDir);

    $files = [
        $installDir . '/instalador.php',
        $installDir . '/decode.log',
        $installDir . '/error_log',
        $installDir . '/installed.txt',
        $projectRoot . '/decode.log',
        $projectRoot . '/error_log'
    ];

    foreach ($files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    foreach (glob($installDir . '/*') as $path) {
        if (basename($path) === 'basededatos.php') {
            continue;
        }
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
}

function finalizeInstallation($pdo) {
    $stmt = $pdo->prepare("INSERT INTO settings (name, value, description, category) VALUES ('INSTALLED', '1', 'Sistema instalado correctamente', 'system') ON DUPLICATE KEY UPDATE value = '1'");
    $stmt->execute();
    
    $install_file_content = date('Y-m-d H:i:s') . " - Instalación completada exitosamente con licencia activada\n" .
        "Archivo de licencia: " . LICENSE_FILE . "\n" .
        "Directorio de licencias: " . LICENSE_DIR . "\n" .
        "Base de datos configurada: SÍ\n" .
        "Bot de Telegram: INTEGRADO\n" .
        "Configuración INSTALLED creada: SÍ";
    
    file_put_contents(__DIR__ . '/installed.txt', $install_file_content);
    
    if (class_exists('SimpleCache')) {
        SimpleCache::clear_cache();
    }
    
    $check_stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'INSTALLED'");
    $check_stmt->execute();
    $check_result = $check_stmt->fetch();

    if (!$check_result || $check_result['value'] !== '1') {
        throw new Exception('Error: No se pudo confirmar la instalación en la base de datos');
    }

    cleanupInstaller();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador del Sistema de Códigos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/modern_global.css">
    <link rel="stylesheet" href="../styles/modern_admin.css">
    <link rel="stylesheet" href="../styles/instalador_neon.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    
    <div id="topProgressBar"></div>

    <div class="container py-4">
        <?php if (isset($installation_successful) && $installation_successful): 
        
try {
    require_once 'basededatos.php';
    $test_conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    $test_conn->set_charset("utf8mb4");
    
    if ($test_conn->connect_error) {
        throw new Exception('Error de conexión post-instalación: ' . $test_conn->connect_error);
    }
    
    $test_result = $test_conn->query("SELECT value FROM settings WHERE name = 'INSTALLED'");
    if (!$test_result || $test_result->num_rows === 0) {
        throw new Exception('Configuración INSTALLED no encontrada en la base de datos');
    }
    
    $test_row = $test_result->fetch_assoc();
    if ($test_row['value'] !== '1') {
        throw new Exception('Configuración INSTALLED no está establecida correctamente. Valor actual: ' . $test_row['value']);
    }
    
    $old_dir = getcwd();
    chdir('..');
    require_once 'funciones.php';
    $is_detected = is_installed();
    chdir($old_dir);
    
    if (!$is_detected) {
        throw new Exception('La función is_installed() no detecta la instalación como completa');
    }
    
    $test_conn->close();
    $installation_verified = true;
    
} catch (Exception $e) {
    $installation_verified = false;
    $verification_error = $e->getMessage();
    error_log("Error verificación post-instalación: " . $e->getMessage());
}
        ?>
<div class="text-center">
    <div class="mb-4">
        <?php if (isset($installation_verified) && $installation_verified): ?>
            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
            <div class="mt-3">
                <span class="badge bg-success fs-6">
                    <i class="fas fa-shield-check me-1"></i>
                    Verificación Exitosa
                </span>
            </div>
        <?php else: ?>
            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
            <div class="mt-3">
                <span class="badge bg-warning text-dark fs-6">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Verificación con Advertencias
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <h1 class="text-center mb-4">¡Instalación Exitosa!</h1>
    
    <div class="form-section">
        <p class="mb-3">La configuración se ha guardado correctamente y la instalación se ha completado.</p>
        
        <?php if (isset($installation_verified) && $installation_verified): ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>Verificación Completa</h6>
                <ul class="list-unstyled mb-0 text-start">
                    <li>✅ Base de datos conectada correctamente</li>
                    <li>✅ Configuración INSTALLED creada</li>
                    <li>✅ Sistema detecta instalación completa</li>
                    <li>✅ Licencia activa y funcionando</li>
                    <li>✅ Bot de Telegram integrado</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Instalación Completada con Advertencias</h6>
                <p class="mb-2"><strong>Error de verificación:</strong></p>
                <code class="d-block p-2 bg-dark text-light rounded">
                    <?= htmlspecialchars($verification_error ?? 'Error desconocido') ?>
                </code>
                <hr>
                <p class="mb-0">
                    <strong>La instalación se completó, pero hay un problema menor.</strong><br>
                    Puedes intentar acceder al sistema o contactar soporte.
                </p>
            </div>
        <?php endif; ?>
        
        <?php
        $license_info = $license_client->getLicenseInfo();
        if ($license_info): ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-certificate me-2"></i>Información de Licencia</h6>
                <ul class="list-unstyled mb-0 text-start">
                    <li><strong>Dominio:</strong> <span class="text-success"><?= htmlspecialchars($license_info['domain']) ?></span></li>
                    <li><strong>Activada:</strong> <span class="text-success"><?= htmlspecialchars($license_info['activated_at']) ?></span></li>
                    <li><strong>Estado:</strong> <span class="badge bg-success">Válida</span></li>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Información de Licencia</h6>
                <ul class="list-unstyled mb-0 text-start">
                    <li><strong>Estado:</strong> <span class="badge bg-success">Activada durante instalación</span></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <ul class="list-unstyled text-start mt-3">
            <li><i class="fas fa-check text-success me-2"></i> Licencia activada y verificada</li>
            <li><i class="fas fa-check text-success me-2"></i> Base de datos configurada</li>
            <li><i class="fas fa-check text-success me-2"></i> Usuario administrador creado</li>
            <li><i class="fas fa-check text-success me-2"></i> Bot de Telegram integrado</li>
            <li><i class="fas fa-check text-success me-2"></i> Sistema de protección habilitado</li>
            <li><i class="fas fa-check text-success me-2"></i> Rutas de licencia corregidas</li>
            <?php if (isset($installation_verified) && $installation_verified): ?>
            <li><i class="fas fa-check text-success me-2"></i> Verificación post-instalación exitosa</li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="d-flex justify-content-center gap-3">
        <a href="../inicio.php" class="btn btn-primary btn-lg">
            <i class="fas fa-home me-2"></i>Ir al Sistema
        </a>
        <a href="../telegram_bot/telegram_setup.php" class="btn btn-success btn-lg">
            <i class="fab fa-telegram me-2"></i>Configurar Bot
        </a>
        <?php if (!isset($installation_verified) || !$installation_verified): ?>
        <a href="?step=configuration" class="btn btn-warning btn-lg">
            <i class="fas fa-redo me-2"></i>Reintentar Instalación
        </a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($installation_verified) && !$installation_verified): ?>
    <div class="mt-4">
        <details class="text-start">
            <summary class="btn btn-outline-secondary btn-sm">Ver información de depuración</summary>
            <div class="mt-2 p-3 bg-dark text-light rounded">
                <small>
                    <strong>Directorio actual:</strong> <?= htmlspecialchars(getcwd()) ?><br>
                    <strong>Archivo basededatos.php:</strong> <?= file_exists('basededatos.php') ? 'Existe' : 'No existe' ?><br>
                    <strong>Directorio padre:</strong> <?= htmlspecialchars(dirname(getcwd())) ?><br>
                    <strong>Archivo funciones.php:</strong> <?= file_exists('../funciones.php') ? 'Existe' : 'No existe' ?><br>
                    <strong>Error específico:</strong> <?= htmlspecialchars($verification_error ?? 'N/A') ?>
                </small>
            </div>
        </details>
    </div>
    <?php endif; ?>
</div>
            
        <?php elseif (isset($installation_error) && $installation_error): ?>
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                </div>
                <h1 class="text-center mb-4">Error en la Instalación</h1>
                <div class="form-section">
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    
                    <div class="diagnostics-box">
                        <h6><i class="fas fa-wrench me-2"></i>Información de Diagnóstico:</h6>
                        <ul class="list-unstyled mb-0">
                            <li>📁 <span class="text-secondary">PROJECT_ROOT:</span> <?= htmlspecialchars(PROJECT_ROOT) ?></li>
                            <li>📂 <span class="text-secondary">Dir. actual:</span> <?= htmlspecialchars(getcwd()) ?></li>
                            <li>📂 <span class="text-secondary">Dir. instalador:</span> <?= htmlspecialchars(__DIR__) ?></li>
                            <li>✅ <span class="<?= file_exists(LICENSE_DIR) ? 'requirement-ok' : 'requirement-error' ?>">License dir existe:</span> <?= file_exists(LICENSE_DIR) ? 'SÍ' : 'NO' ?></li>
                            <li>✏️ <span class="<?= is_writable(dirname(LICENSE_DIR)) ? 'requirement-ok' : 'requirement-error' ?>">License dir escribible:</span> <?= is_writable(dirname(LICENSE_DIR)) ? 'SÍ' : 'NO' ?></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='?step=license'">
                        <i class="fas fa-redo me-2"></i>Reintentar
                    </button>
                </div>
            </div>
            
        <?php else: ?>
            <div class="step-indicator">
                <div class="step <?= $current_step === 'requirements' ? 'active' : ($current_step !== 'requirements' ? 'completed' : '') ?>">
                    <i class="fas fa-server me-2"></i>Requerimientos
                </div>
                <div class="step <?= $current_step === 'license' ? 'active' : ($current_step === 'configuration' ? 'completed' : '') ?>">
                    <i class="fas fa-key me-2"></i>Licencia
                </div>
                <div class="step <?= $current_step === 'configuration' ? 'active' : '' ?>">
                    <i class="fas fa-cogs me-2"></i>Configuración
                </div>
            </div>
            
            <?php if ($current_step === 'requirements'): ?>
                <div class="text-center mb-4">
                    <i class="fas fa-server text-primary" style="font-size: 3rem;"></i>
                    <h1 class="mt-3">Verificación de Requerimientos</h1>
                    <p class="text-secondary">Comprobando que su servidor cumple con los requisitos</p>
                </div>
                
                <div class="form-section">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th>Requerido</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fab fa-php me-2"></i>PHP</td>
                                    <td><?= $php_version_required ?> o superior</td>
                                    <td>
                                        <span class="<?= $php_version_valid ? 'requirement-ok' : 'requirement-error' ?>">
                                            <i class="fas <?= $php_version_valid ? 'fa-check' : 'fa-times' ?> me-1"></i>
                                            <?= $php_version ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php foreach ($required_extensions as $ext => $description): ?>
                                    <tr>
                                        <td><i class="fas fa-puzzle-piece me-2"></i><?= $ext ?></td>
                                        <td><?= $description ?></td>
                                        <td>
                                            <span class="<?= $extensions_status[$ext] ? 'requirement-ok' : 'requirement-error' ?>">
                                                <i class="fas <?= $extensions_status[$ext] ? 'fa-check' : 'fa-times' ?> me-1"></i>
                                                <?= $extensions_status[$ext] ? 'Habilitada' : 'Faltante' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="diagnostics-box">
                        <h6><i class="fas fa-folder me-2"></i>Información de Rutas:</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li>📁 <span class="text-secondary">Raíz del proyecto:</span> <?= htmlspecialchars(PROJECT_ROOT) ?></li>
                            <li>✅ <span class="<?= file_exists(LICENSE_DIR) ? 'requirement-ok' : 'requirement-error' ?>">Directorio existe:</span> <?= file_exists(LICENSE_DIR) ? 'SÍ' : 'NO' ?></li>
                            <li>✏️ <span class="<?= is_writable(dirname(LICENSE_DIR)) ? 'requirement-ok' : 'requirement-error' ?>">Directorio escribible:</span> <?= is_writable(dirname(LICENSE_DIR)) ? 'SÍ' : 'NO' ?></li>
                        </ul>
                    </div>
                    
                    <div class="text-center mt-3">
                        <?php if ($all_extensions_loaded && $php_version_valid): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                ¡Todos los requerimientos están satisfechos!
                            </div>
                            <a href="?step=license" class="btn btn-success btn-lg">
                                <i class="fas fa-key me-2"></i>Continuar con la Licencia
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Hay requerimientos faltantes. Contacte a su proveedor de hosting.
                            </div>
                            <button type="button" class="btn btn-warning" onclick="location.reload()">
                                <i class="fas fa-sync me-2"></i>Verificar Nuevamente
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($current_step === 'license'): ?>
                <div class="text-center mb-4">
                    <i class="fas fa-key text-primary" style="font-size: 3rem;"></i>
                    <h1 class="mt-3">Activación de Licencia</h1>
                    <p class="text-secondary">Ingrese su clave de licencia para continuar</p>
                </div>
                
                <?php if (isset($license_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($license_error) ?>
                        
                        <div class="diagnostics-box mt-3">
                            <h6><i class="fas fa-bug me-2"></i>Información de Debugging:</h6>
                            <ul class="list-unstyled mb-0 small">
                                <li>✅ <span class="<?= file_exists(LICENSE_DIR) ? 'requirement-ok' : 'requirement-error' ?>">Directorio existe:</span> <?= file_exists(LICENSE_DIR) ? 'SÍ' : 'NO' ?></li>
                                <li>✏️ <span class="<?= is_writable(LICENSE_DIR) ? 'requirement-ok' : 'requirement-error' ?>">Directorio escribible:</span> <?= is_writable(LICENSE_DIR) ? 'SÍ' : 'NO' ?></li>
                                <li>📄 <span class="<?= file_exists(LICENSE_FILE) ? 'requirement-ok' : 'requirement-error' ?>">Archivo existe:</span> <?= file_exists(LICENSE_FILE) ? 'SÍ' : 'NO' ?></li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($license_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($license_success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($license_warning)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($license_warning) ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <?php 
                    $license_is_valid = false;
                    if ($license_client->isLicenseValid()) {
                        $license_is_valid = true;
                    } elseif (isset($_SESSION['license_activated']) && $_SESSION['license_activated']) {
                        $time_since_activation = time() - ($_SESSION['license_verified_at'] ?? 0);
                        if ($time_since_activation < 300) {
                            $license_is_valid = true;
                        }
                    }
                    
                    if ($license_is_valid): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-shield-alt fa-3x mb-3"></i>
                            <h4>Licencia Activada</h4>
                            <?php
                            $license_info = $license_client->getLicenseInfo();
                            if ($license_info): ?>
                                <p class="mb-0">
                                    <strong>Dominio:</strong> <span class="text-success"><?= htmlspecialchars($license_info['domain']) ?></span><br>
                                    <strong>Activada:</strong> <span class="text-success"><?= htmlspecialchars($license_info['activated_at']) ?></span><br>
                                    <strong>Estado:</strong> <span class="badge bg-success">Válida</span><br>
                                </p>
                            <?php else: ?>
                                <p class="mb-0">
                                    <strong>Estado:</strong> <span class="badge bg-success">Activada en Sesión</span><br>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center">
                            <a href="?step=configuration" class="btn btn-primary btn-lg">
                                <i class="fas fa-cogs me-2"></i>Continuar con la Configuración
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="text-center">
                            <div class="mb-4">
                                <label for="license_key" class="form-label h5">
                                    <i class="fas fa-key me-2"></i>Clave de Licencia
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg license-key-input text-center" 
                                       name="license_key" 
                                       placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                                       maxlength="50"
                                       required>
                                <div class="form-text">
                                    Ingrese la clave de licencia proporcionada por el proveedor
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Información de Activación</h6>
                                <p class="mb-0">
                                    • La licencia se activará para el dominio: <strong class="text-primary"><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></strong><br>
                                    • Se verificará la validez con el servidor de licencias<br>
                                    • La activación requiere conexión a internet<br>
                                </p>
                            </div>
                            
                            <div class="d-flex justify-content-center gap-3">
                                <a href="?step=requirements" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i>Atrás
                                </a>
                                <button type="submit" name="activate_license" class="btn btn-success btn-lg">
                                    <i class="fas fa-shield-alt me-2"></i>Activar Licencia
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($current_step === 'configuration'): ?>
                <?php 
                $can_proceed = false;
                if ($license_client->isLicenseValid()) {
                    $can_proceed = true;
                } elseif (isset($_SESSION['license_activated']) && $_SESSION['license_activated']) {
                    $time_since_activation = time() - ($_SESSION['license_verified_at'] ?? 0);
                    if ($time_since_activation < 300) {
                        $can_proceed = true;
                    }
                }
                
                if (!$can_proceed): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>Licencia Requerida</h4>
                        <p>Debe activar una licencia válida antes de continuar.</p>
                        <a href="?step=license" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Activar Licencia
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-cogs text-primary" style="font-size: 3rem;"></i>
                        <h1 class="mt-3">Configuración del Sistema</h1>
                        <p class="text-secondary">Complete los datos para finalizar la instalación</p>
                    </div>
                    
                    <form method="POST" id="installForm">
                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-database me-2 text-info"></i>Base de Datos</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_host" class="form-label">
                                            <i class="fas fa-server me-2"></i>Servidor
                                        </label>
                                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_name" class="form-label">
                                            <i class="fas fa-database me-2"></i>Nombre de la Base de Datos
                                        </label>
                                        <input type="text" class="form-control" name="db_name" placeholder="mi_sistema_codigos" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_user" class="form-label">
                                            <i class="fas fa-user me-2"></i>Usuario de la Base de Datos
                                        </label>
                                        <input type="text" class="form-control" name="db_user" placeholder="usuario_bd" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_password" class="form-label">
                                            <i class="fas fa-key me-2"></i>Contraseña de la Base de Datos
                                        </label>
                                        <input type="password" class="form-control" name="db_password" placeholder="Contraseña BD">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="mb-3"><i class="fas fa-user-shield me-2 text-warning"></i>Usuario Administrador</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_user" class="form-label">
                                            <i class="fas fa-user-cog me-2"></i>Usuario Administrador
                                        </label>
                                        <input type="text" class="form-control" name="admin_user" placeholder="admin" required minlength="3">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Contraseña Administrador
                                        </label>
                                        <input type="password" class="form-control" name="admin_password" placeholder="Contraseña segura" required minlength="6">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="alert alert-success">
                                <h6><i class="fas fa-shield-alt me-2"></i>Estado de Licencia</h6>
                                <?php
                                $license_info = $license_client->getLicenseInfo();
                                if ($license_info): ?>
                                    <ul class="mb-0 text-start">
                                        <li>✅ <span class="text-success">Licencia válida y activada</span></li>
                                        <li>🌐 <span class="text-secondary">Dominio autorizado:</span> <strong class="text-primary"><?= htmlspecialchars($license_info['domain']) ?></strong></li>
                                        <li>📅 <span class="text-secondary">Activada el:</span> <span class="text-primary"><?= htmlspecialchars($license_info['activated_at']) ?></span></li>
                                        <li>🔒 <span class="text-success">Sistema protegido contra uso no autorizado</span></li>
                                        <li>🤖 <span class="text-success">Bot de Telegram será integrado automáticamente</span></li>
                                    </ul>
                                <?php else: ?>
                                    <ul class="mb-0 text-start">
                                        <li>✅ <span class="text-success">Licencia activada en esta sesión</span></li>
                                        <li>🌐 <span class="text-secondary">Dominio:</span> <strong class="text-primary"><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></strong></li>
                                        <li>🔒 <span class="text-success">Sistema protegido contra uso no autorizado</span></li>
                                        <li>🤖 <span class="text-success">Bot de Telegram será integrado automáticamente</span></li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="?step=license" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>Atrás
                            </a>
                            <button type="submit" name="configure" class="btn btn-success btn-lg">
                                <i class="fas fa-rocket me-2"></i>Instalar Sistema + Bot
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const topProgressBar = document.getElementById('topProgressBar');

        function showProgressBar() {
            topProgressBar.style.width = '100%';
        }

        function hideProgressBar() {
            topProgressBar.style.width = '0';
        }

        document.getElementById('installForm')?.addEventListener('submit', function() {
            showProgressBar();
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            hideProgressBar();
        });

        document.querySelector('.license-key-input')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            let formatted = value.match(/.{1,4}/g)?.join('-') || value;
            if (formatted.length > 47) formatted = formatted.substring(0, 47);
            e.target.value = formatted;
        });
    </script>
</body>
</html>