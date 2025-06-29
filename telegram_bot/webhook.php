<?php
/**
 * Bot de Telegram Mejorado - webhook_completo.php
 * Replica exactamente la funcionalidad de la aplicación web
 * Interfaz profesional con botones y menús intuitivos
 * VERSIÓN CON BÚSQUEDA IMAP REAL
 */

// Configuración inicial
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(30);

// Headers para Telegram
header('Content-Type: application/json');

// Autoload y dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../instalacion/basededatos.php';
require_once __DIR__ . '/../shared/UnifiedQueryEngine.php';

// 1. Incluimos el archivo correcto UNA SOLA VEZ.
require_once __DIR__ . '/../cache/cache_helper.php';

// ========== CONFIGURACIÓN ==========

// Conectar a la base de datos
try {
    $db = new mysqli($db_host, $db_user, $db_password, $db_name);
    $db->set_charset("utf8mb4");
    
    if ($db->connect_error) {
        throw new Exception("Error de conexión: " . $db->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    exit('{"ok":false,"error":"Database connection failed"}');
}

// Obtener configuraciones del sistema
$config = SimpleCache::get_settings($db);

// Validar que el bot esté habilitado
if (($config['TELEGRAM_BOT_ENABLED'] ?? '0') !== '1') {
    http_response_code(403);
    exit('{"ok":false,"error":"Bot disabled"}');
}

// Token del bot
$botToken = $config['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    http_response_code(400);
    exit('{"ok":false,"error":"No bot token configured"}');
}

// ========== FUNCIONES DE LOGGING ==========

function log_bot($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(__DIR__ . '/logs/bot.log', $log_entry, FILE_APPEND | LOCK_EX);
}

// ========== FUNCIONES DE TELEGRAM API ==========

function enviarMensaje($botToken, $chatId, $texto, $teclado = null, $parseMode = 'MarkdownV2') {
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $texto,
        'parse_mode' => $parseMode
    ];
    
    if ($teclado) {
        $data['reply_markup'] = json_encode($teclado);
    }
    
    return enviarRequest($url, $data);
}

function editarMensaje($botToken, $chatId, $messageId, $texto, $teclado = null, $parseMode = 'MarkdownV2') {
    $url = "https://api.telegram.org/bot$botToken/editMessageText";
    
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $texto,
        'parse_mode' => $parseMode
    ];
    
    if ($teclado) {
        $data['reply_markup'] = json_encode($teclado);
    }
    
    return enviarRequest($url, $data);
}

function responderCallback($botToken, $callbackQueryId, $texto = "") {
    $url = "https://api.telegram.org/bot$botToken/answerCallbackQuery";
    
    $data = [
        'callback_query_id' => $callbackQueryId,
        'text' => $texto
    ];
    
    return enviarRequest($url, $data);
}

function enviarRequest($url, $data) {
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

// ========== FUNCIONES DE VALIDACIÓN ==========

function verificarUsuario($telegramId, $db) {
    try {
        $stmt = $db->prepare("SELECT id, username, role, status FROM users WHERE telegram_id = ? AND status = 1");
        $stmt->bind_param("i", $telegramId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        }
        
        $stmt->close();
        return false;
    } catch (Exception $e) {
        log_bot("Error verificando usuario: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function obtenerCorreosAutorizados($userId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT ae.email 
            FROM authorized_emails ae
            LEFT JOIN user_authorized_emails uae ON ae.id = uae.authorized_email_id AND uae.user_id = ?
            WHERE ae.status = 1 AND (uae.user_id IS NOT NULL OR NOT EXISTS (
                SELECT 1 FROM user_authorized_emails WHERE user_id = ?
            ))
        ");
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
        $stmt->close();
        
        return $emails;
    } catch (Exception $e) {
        log_bot("Error obteniendo correos autorizados: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function obtenerPlataformasDisponibles($db) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT p.name, 
                   COALESCE(p.description, p.name) as display_name 
            FROM platforms p 
            WHERE p.status = 1 
            ORDER BY display_name
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $plataformas = [];
        while ($row = $result->fetch_assoc()) {
            $plataformas[$row['name']] = $row['display_name'];
        }
        $stmt->close();
        
        return $plataformas;
    } catch (Exception $e) {
        log_bot("Error obteniendo plataformas: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ========== FUNCIONES DE TECLADOS ==========

function crearTecladoMenuPrincipal($esAdmin = false) {
    $teclado = [
        'inline_keyboard' => [
            [
                ['text' => '🔍 Buscar Códigos', 'callback_data' => 'buscar_codigos'],
                ['text' => '📋 Mis Correos', 'callback_data' => 'mis_correos']
            ],
            [
                ['text' => '⚙️ Mi Configuración', 'callback_data' => 'mi_config'],
                ['text' => '❓ Ayuda', 'callback_data' => 'ayuda']
            ]
        ]
    ];
    
    if ($esAdmin) {
        $teclado['inline_keyboard'][] = [
            ['text' => '👨‍💼 Panel Admin', 'callback_data' => 'admin_panel']
        ];
    }
    
    return $teclado;
}

function crearTecladoCorreos($emails, $pagina = 0, $porPagina = 5) {
    $total = count($emails);
    $inicio = $pagina * $porPagina;
    $emailsPagina = array_slice($emails, $inicio, $porPagina);
    
    $teclado = ['inline_keyboard' => []];
    
    // Botones de emails
    foreach ($emailsPagina as $email) {
        $teclado['inline_keyboard'][] = [
            ['text' => "📧 $email", 'callback_data' => "select_email_$email"]
        ];
    }
    
    // Navegación de páginas
    $botonesPaginacion = [];
    if ($pagina > 0) {
        $botonesPaginacion[] = ['text' => '⬅️ Anterior', 'callback_data' => "emails_page_" . ($pagina - 1)];
    }
    if ($inicio + $porPagina < $total) {
        $botonesPaginacion[] = ['text' => 'Siguiente ➡️', 'callback_data' => "emails_page_" . ($pagina + 1)];
    }
    
    if (!empty($botonesPaginacion)) {
        $teclado['inline_keyboard'][] = $botonesPaginacion;
    }
    
    // Botón volver
    $teclado['inline_keyboard'][] = [
        ['text' => '🏠 Menú Principal', 'callback_data' => 'menu_principal']
    ];
    
    return $teclado;
}

function crearTecladoPlataformas($plataformas, $email) {
    $teclado = ['inline_keyboard' => []];
    
    $fila = [];
    $contador = 0;
    
    foreach ($plataformas as $nombre => $display) {
        $fila[] = ['text' => $display, 'callback_data' => "search_{$email}_{$nombre}"];
        $contador++;
        
        // Máximo 2 botones por fila
        if ($contador == 2) {
            $teclado['inline_keyboard'][] = $fila;
            $fila = [];
            $contador = 0;
        }
    }
    
    // Agregar fila restante si existe
    if (!empty($fila)) {
        $teclado['inline_keyboard'][] = $fila;
    }
    
    // Botones de navegación
    $teclado['inline_keyboard'][] = [
        ['text' => '📋 Cambiar Email', 'callback_data' => 'mis_correos'],
        ['text' => '🏠 Menú Principal', 'callback_data' => 'menu_principal']
    ];
    
    return $teclado;
}

function crearTecladoResultados($email, $plataforma, $resultados) {
    $teclado = ['inline_keyboard' => []];
    
    if (!empty($resultados) && isset($resultados['emails']) && count($resultados['emails']) > 0) {
        // Mostrar cada resultado
        foreach ($resultados['emails'] as $index => $emailData) {
            $fecha = isset($emailData['date']) ? date('d/m H:i', strtotime($emailData['date'])) : 'Sin fecha';
            $asunto = isset($emailData['subject']) ? 
                (strlen($emailData['subject']) > 30 ? substr($emailData['subject'], 0, 30) . '...' : $emailData['subject']) : 
                'Sin asunto';
            
            $teclado['inline_keyboard'][] = [
                ['text' => "📄 $fecha - $asunto", 'callback_data' => "show_email_{$email}_{$plataforma}_{$index}"]
            ];
        }
    }
    
    // Botones de navegación
    $teclado['inline_keyboard'][] = [
        ['text' => '🔄 Nueva Búsqueda', 'callback_data' => "select_email_$email"],
        ['text' => '🏠 Menú Principal', 'callback_data' => 'menu_principal']
    ];
    
    return $teclado;
}

function crearTecladoVolver($callback = 'menu_principal') {
    return [
        'inline_keyboard' => [
            [['text' => '🏠 Menú Principal', 'callback_data' => $callback]]
        ]
    ];
}

// ========== FUNCIONES DE ALMACENAMIENTO TEMPORAL ==========

function guardarBusquedaTemporal($userId, $email, $plataforma, $resultados, $db) {
    try {
        $data = json_encode([
            'email' => $email,
            'plataforma' => $plataforma,
            'resultados' => $resultados,
            'timestamp' => time()
        ]);
        
        $stmt = $db->prepare("
            INSERT INTO telegram_temp_data (user_id, data_type, data_content, created_at) 
            VALUES (?, 'search_result', ?, NOW())
            ON DUPLICATE KEY UPDATE data_content = VALUES(data_content), created_at = NOW()
        ");
        $stmt->bind_param("is", $userId, $data);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        log_bot("Error guardando búsqueda temporal: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function obtenerBusquedaTemporal($userId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT data_content 
            FROM telegram_temp_data 
            WHERE user_id = ? AND data_type = 'search_result' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return json_decode($row['data_content'], true);
        }
        
        $stmt->close();
        return null;
    } catch (Exception $e) {
        log_bot("Error obteniendo búsqueda temporal: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// ========== FUNCIONES PRINCIPALES ==========

function mostrarMenuPrincipal($botToken, $chatId, $firstName, $user, $messageId = null) {
    $esAdmin = ($user['role'] === 'admin');
    
    $texto = "🤖 *¡Hola " . escaparMarkdown($firstName) . "\\!*\n\n";
    $texto .= "🎯 *Sistema de Códigos de Verificación*\n\n";
    $texto .= "📱 Interfaz profesional e intuitiva\n";
    $texto .= "🔒 Seguridad y permisos avanzados\n";
    $texto .= "⚡ Búsquedas rápidas y precisas\n\n";
    $texto .= "*¿Qué deseas hacer?*";
    
    $teclado = crearTecladoMenuPrincipal($esAdmin);
    
    if ($messageId) {
        editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
    } else {
        enviarMensaje($botToken, $chatId, $texto, $teclado);
    }
}

function mostrarCorreosAutorizados($botToken, $chatId, $messageId, $user, $db, $pagina = 0) {
    $emails = obtenerCorreosAutorizados($user['id'], $db);
    
    if (empty($emails)) {
        $texto = "❌ *Sin Correos Autorizados*\n\n";
        $texto .= "No tienes correos autorizados para consultar\\.\n";
        $texto .= "Contacta al administrador para obtener permisos\\.";
        
        editarMensaje($botToken, $chatId, $messageId, $texto, crearTecladoVolver());
        return;
    }
    
    $texto = "📧 *Tus Correos Autorizados*\n\n";
    $texto .= "Tienes acceso a *" . count($emails) . "* correo" . (count($emails) != 1 ? 's' : '') . "\n\n";
    $texto .= "Selecciona un correo para buscar códigos:";
    
    $teclado = crearTecladoCorreos($emails, $pagina);
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarPlataformasParaEmail($botToken, $chatId, $messageId, $email, $db) {
    $plataformas = obtenerPlataformasDisponibles($db);
    
    if (empty($plataformas)) {
        $texto = "❌ *Sin Plataformas Configuradas*\n\n";
        $texto .= "No hay plataformas disponibles en el sistema\\.\n";
        $texto .= "Contacta al administrador\\.";
        
        editarMensaje($botToken, $chatId, $messageId, $texto, crearTecladoVolver());
        return;
    }
    
    $texto = "🎯 *Selecciona la Plataforma*\n\n";
    $texto .= "📧 Email: `" . escaparMarkdown($email) . "`\n\n";
    $texto .= "Elige dónde buscar los códigos:";
    
    $teclado = crearTecladoPlataformas($plataformas, $email);
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarResultadosBusqueda($botToken, $chatId, $messageId, $email, $plataforma, $resultado) {
    if ($resultado['found']) {
        $texto = "✅ *¡Códigos Encontrados\\!*\n\n";
        $texto .= "📧 Email: `" . escaparMarkdown($email) . "`\n";
        $texto .= "🎯 Plataforma: *" . escaparMarkdown($plataforma) . "*\n\n";
        
        if (isset($resultado['emails']) && count($resultado['emails']) > 0) {
            $texto .= "📊 *Resultados:* " . count($resultado['emails']) . " mensaje" . 
                     (count($resultado['emails']) != 1 ? 's' : '') . "\n\n";
            $texto .= "Toca un resultado para ver los detalles:";
            
            $teclado = crearTecladoResultados($email, $plataforma, $resultado);
        } else {
            $texto .= "❓ *Sin Detalles*\n\n";
            $texto .= "Se encontraron resultados pero sin detalles disponibles\\.";
            
            $teclado = crearTecladoVolver();
        }
    } else {
        $texto = "😔 *Sin Resultados*\n\n";
        $texto .= "📧 Email: `" . escaparMarkdown($email) . "`\n";
        $texto .= "🎯 Plataforma: *" . escaparMarkdown($plataforma) . "*\n\n";
        
        $mensaje = $resultado['message'] ?? 'No se encontraron códigos para tu búsqueda.';
        $texto .= "💡 " . escaparMarkdown($mensaje) . "\n\n";
        $texto .= "*Sugerencias:*\n";
        $texto .= "🔹 Verifica que el email sea correcto\n";
        $texto .= "🔹 Prueba con otra plataforma\n";
        $texto .= "🔹 Revisa tus permisos";
        
        $teclado = crearTecladoVolver();
    }
    
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarDetalleEmail($botToken, $chatId, $messageId, $email, $plataforma, $index, $user, $db) {
    $busqueda = obtenerBusquedaTemporal($user['id'], $db);
    
    if (!$busqueda || !isset($busqueda['resultados']['emails'][$index])) {
        $texto = "❌ *Email No Encontrado*\n\n";
        $texto .= "El email solicitado no está disponible\\.\n";
        $texto .= "Realiza una nueva búsqueda\\.";
        
        editarMensaje($botToken, $chatId, $messageId, $texto, crearTecladoVolver());
        return;
    }
    
    $emailData = $busqueda['resultados']['emails'][$index];
    
    $texto = "📄 *Detalle del Email*\n\n";
    
    // Información básica
    if (isset($emailData['date'])) {
        $fecha = date('d/m/Y H:i:s', strtotime($emailData['date']));
        $texto .= "📅 *Fecha:* `$fecha`\n";
    }
    
    if (isset($emailData['subject'])) {
        $asunto = strlen($emailData['subject']) > 100 ? 
                 substr($emailData['subject'], 0, 100) . '...' : 
                 $emailData['subject'];
        $texto .= "📝 *Asunto:* " . escaparMarkdown($asunto) . "\n";
    }
    
    if (isset($emailData['from'])) {
        $texto .= "👤 *De:* `" . escaparMarkdown($emailData['from']) . "`\n";
    }
    
    $texto .= "\n";
    
    // Código de verificación si existe
    if (isset($emailData['verification_code'])) {
        $texto .= "🔐 *CÓDIGO:* `" . $emailData['verification_code'] . "`\n\n";
    }
    
    // Contenido del email (limitado)
    if (isset($emailData['body'])) {
        $contenido = strip_tags($emailData['body']);
        $contenido = strlen($contenido) > 500 ? substr($contenido, 0, 500) . '...' : $contenido;
        $texto .= "📄 *Contenido:*\n" . escaparMarkdown($contenido);
    }
    
    $teclado = [
        'inline_keyboard' => [
            [
                ['text' => '🔙 Volver a Resultados', 'callback_data' => "search_{$email}_{$plataforma}"],
                ['text' => '🏠 Menú Principal', 'callback_data' => 'menu_principal']
            ]
        ]
    ];
    
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarConfiguracionUsuario($botToken, $chatId, $messageId, $user, $db) {
    $emails = obtenerCorreosAutorizados($user['id'], $db);
    $plataformas = obtenerPlataformasDisponibles($db);
    
    $texto = "⚙️ *Tu Configuración*\n\n";
    $texto .= "👤 *Usuario:* `" . escaparMarkdown($user['username']) . "`\n";
    $texto .= "🎭 *Rol:* `" . escaparMarkdown($user['role']) . "`\n";
    $texto .= "📊 *Estado:* " . ($user['status'] ? '✅ Activo' : '❌ Inactivo') . "\n\n";
    
    $texto .= "📧 *Correos Autorizados:* " . count($emails) . "\n";
    $texto .= "🎯 *Plataformas Disponibles:* " . count($plataformas) . "\n\n";
    
    $texto .= "*Permisos Actuales:*\n";
    foreach (array_slice($emails, 0, 5) as $email) {
        $texto .= "• `" . escaparMarkdown($email) . "`\n";
    }
    
    if (count($emails) > 5) {
        $texto .= "• \\.\\.\\. y " . (count($emails) - 5) . " más\n";
    }
    
    $teclado = crearTecladoVolver();
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarAyuda($botToken, $chatId, $messageId) {
    $texto = "❓ *Ayuda del Sistema*\n\n";
    $texto .= "*🔍 Buscar Códigos:*\n";
    $texto .= "1\\. Selecciona un correo autorizado\n";
    $texto .= "2\\. Elige la plataforma \\(WhatsApp, Telegram, etc\\.\\)\n";
    $texto .= "3\\. Espera los resultados\n";
    $texto .= "4\\. Toca un resultado para ver detalles\n\n";
    
    $texto .= "*📧 Correos Autorizados:*\n";
    $texto .= "Solo puedes consultar correos específicamente autorizados\\.\n";
    $texto .= "Si necesitas acceso a más correos, contacta al administrador\\.\n\n";
    
    $texto .= "*🎯 Plataformas:*\n";
    $texto .= "Cada plataforma tiene asuntos específicos configurados\\.\n";
    $texto .= "Elige la plataforma correcta para mejores resultados\\.\n\n";
    
    $texto .= "*⚡ Comandos Rápidos:*\n";
    $texto .= "• `/start` \\- Menú principal\n";
    $texto .= "• Usa los botones para navegar\n\n";
    
    $texto .= "*🆘 Soporte:*\n";
    $texto .= "Si tienes problemas, contacta al administrador del sistema\\.";
    
    $teclado = crearTecladoVolver();
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

function mostrarPanelAdmin($botToken, $chatId, $messageId, $user, $db) {
    // Verificar que sea administrador
    if ($user['role'] !== 'admin') {
        $texto = "🚫 *Acceso Denegado*\n\n";
        $texto .= "Solo los administradores pueden acceder a este panel\\.";
        editarMensaje($botToken, $chatId, $messageId, $texto, crearTecladoVolver());
        return;
    }
    
    // Obtener estadísticas
    try {
        // Usuarios totales
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE status = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $usuariosActivos = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // Correos autorizados
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM authorized_emails WHERE status = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $emailsAutorizados = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // Plataformas activas
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM platforms WHERE status = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $plataformasActivas = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // Búsquedas recientes
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM search_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $result = $stmt->get_result();
        $busquedasHoy = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
    } catch (Exception $e) {
        log_bot("Error obteniendo estadísticas admin: " . $e->getMessage(), 'ERROR');
        $usuariosActivos = $emailsAutorizados = $plataformasActivas = $busquedasHoy = 0;
    }
    
    $texto = "👨‍💼 *Panel de Administración*\n\n";
    $texto .= "📊 *Estadísticas del Sistema:*\n\n";
    $texto .= "👥 *Usuarios Activos:* `$usuariosActivos`\n";
    $texto .= "📧 *Correos Autorizados:* `$emailsAutorizados`\n";
    $texto .= "🎯 *Plataformas Activas:* `$plataformasActivas`\n";
    $texto .= "🔍 *Búsquedas Hoy:* `$busquedasHoy`\n\n";
    $texto .= "🌐 *Administrador:* `" . escaparMarkdown($user['username']) . "`\n\n";
    $texto .= "_Para gestión completa, usa el panel web_";
    
    $teclado = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Ver Logs', 'callback_data' => 'admin_logs'],
                ['text' => '👥 Ver Usuarios', 'callback_data' => 'admin_users']
            ],
            [
                ['text' => '🔧 Estado Sistema', 'callback_data' => 'admin_status'],
                ['text' => '📧 Test Email', 'callback_data' => 'admin_test']
            ],
            [
                ['text' => '🏠 Menú Principal', 'callback_data' => 'menu_principal']
            ]
        ]
    ];
    
    editarMensaje($botToken, $chatId, $messageId, $texto, $teclado);
}

// ========== FUNCIONES DE BÚSQUEDA IMAP REAL ==========

function ejecutarBusquedaReal($botToken, $chatId, $messageId, $email, $plataforma, $user, $db) {
    // Mostrar mensaje de búsqueda
    $texto = "🔍 *Buscando Códigos\\.\\.\\.*\n\n";
    $texto .= "📧 Email: `" . escaparMarkdown($email) . "`\n";
    $texto .= "🎯 Plataforma: *" . escaparMarkdown($plataforma) . "*\n\n";
    $texto .= "⏳ Consultando servidores\\.\\.\\.\n";
    $texto .= "_Esto puede tardar unos segundos_";
    
    editarMensaje($botToken, $chatId, $messageId, $texto, null);
    
    try {
        // 1. Obtener servidores habilitados
        $servidores = obtenerServidoresHabilitados($db);
        if (empty($servidores)) {
            mostrarError($botToken, $chatId, $messageId, "No hay servidores IMAP configurados");
            return;
        }
        
        // 2. Obtener asuntos para la plataforma
        $asuntos = obtenerAsuntosPlataforma($db, $plataforma);
        if (empty($asuntos)) {
            mostrarError($botToken, $chatId, $messageId, "La plataforma no tiene asuntos configurados");
            return;
        }
        
        // 3. Buscar en cada servidor
        log_bot("Iniciando búsqueda real: $email en $plataforma con " . count($asuntos) . " asuntos", 'INFO');
        log_bot("Servidores disponibles: " . count($servidores), 'INFO');
        
        foreach ($servidores as $servidor) {
            log_bot("Probando servidor: " . $servidor['server_name'], 'INFO');
            
            $resultado = buscarEnServidor($servidor, $email, $asuntos);
            
            if ($resultado['found']) {
                log_bot("¡Código encontrado en servidor: " . $servidor['server_name'] . "!", 'INFO');
                
                // Guardar resultado
                guardarBusquedaTemporal($user['id'], $email, $plataforma, $resultado, $db);
                
                // Mostrar resultado
                mostrarResultadosBusqueda($botToken, $chatId, $messageId, $email, $plataforma, $resultado);
                return;
            }
        }
        
        // No se encontró nada
        log_bot("No se encontraron códigos en ningún servidor", 'INFO');
        $resultado = [
            'found' => false,
            'message' => 'No se encontraron códigos de verificación en los últimos 30 minutos.'
        ];
        
        mostrarResultadosBusqueda($botToken, $chatId, $messageId, $email, $plataforma, $resultado);
        
    } catch (Exception $e) {
        log_bot("Error en búsqueda real: " . $e->getMessage(), 'ERROR');
        mostrarError($botToken, $chatId, $messageId, "Error interno del servidor");
    }
}

function obtenerServidoresHabilitados($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM email_servers WHERE enabled = 1 ORDER BY priority ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $servidores = [];
        while ($row = $result->fetch_assoc()) {
            $servidores[] = $row;
        }
        $stmt->close();
        
        log_bot("Servidores habilitados encontrados: " . count($servidores), 'INFO');
        return $servidores;
    } catch (Exception $e) {
        log_bot("Error obteniendo servidores: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function obtenerAsuntosPlataforma($db, $plataforma) {
    try {
        $stmt = $db->prepare("
            SELECT ps.subject 
            FROM platforms p 
            JOIN platform_subjects ps ON p.id = ps.platform_id 
            WHERE p.name = ? AND p.status = 1
        ");
        $stmt->bind_param('s', $plataforma);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $asuntos = [];
        while ($row = $result->fetch_assoc()) {
            $asuntos[] = $row['subject'];
        }
        $stmt->close();
        
        log_bot("Asuntos para $plataforma encontrados: " . count($asuntos), 'INFO');
        return $asuntos;
    } catch (Exception $e) {
        log_bot("Error obteniendo asuntos: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

function buscarEnServidor($servidor, $email, $asuntos) {
    try {
        log_bot("Conectando a servidor IMAP: " . $servidor['server_name'], 'INFO');
        
        // Configurar conexión IMAP
        $connectionString = sprintf(
            '{%s:%d/imap/ssl/novalidate-cert}INBOX',
            $servidor['imap_server'],
            $servidor['imap_port']
        );
        
        // Configurar timeout
        imap_timeout(IMAP_OPENTIMEOUT, 10);
        
        // Abrir conexión
        $connection = @imap_open(
            $connectionString,
            $servidor['imap_user'],
            $servidor['imap_password']
        );
        
        if (!$connection) {
            $error = imap_last_error();
            log_bot("Error conectando a " . $servidor['server_name'] . ": " . $error, 'ERROR');
            return ['found' => false, 'error' => 'Error de conexión'];
        }
        
        log_bot("Conexión exitosa a " . $servidor['server_name'], 'INFO');
        
        // Buscar emails
        $resultado = buscarCodigosEnBuzon($connection, $email, $asuntos);
        
        // Cerrar conexión
        imap_close($connection);
        
        return $resultado;
        
    } catch (Exception $e) {
        log_bot("Excepción en servidor " . $servidor['server_name'] . ": " . $e->getMessage(), 'ERROR');
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

function buscarCodigosEnBuzon($connection, $email, $asuntos) {
    try {
        // Calcular fecha de búsqueda (últimos 30 minutos)
        $hace30min = date('d-M-Y', strtotime('-30 minutes'));
        
        foreach ($asuntos as $asunto) {
            log_bot("Buscando asunto: '$asunto' para email: $email", 'INFO');
            
            // Criterio de búsqueda IMAP
            $criterio = sprintf(
                'FROM "%s" SUBJECT "%s" SINCE "%s"',
                $email,
                $asunto,
                $hace30min
            );
            
            // Buscar mensajes
            $mensajes = @imap_search($connection, $criterio);
            
            if ($mensajes && count($mensajes) > 0) {
                log_bot("Encontrados " . count($mensajes) . " mensajes para asunto: $asunto", 'INFO');
                
                // Procesar el mensaje más reciente
                $mensajeId = end($mensajes); // Último mensaje (más reciente)
                
                $codigo = extraerCodigoDeEmail($connection, $mensajeId);
                
                if ($codigo) {
                    return [
                        'found' => true,
                        'emails' => [
                            [
                                'date' => date('Y-m-d H:i:s'),
                                'subject' => $asunto,
                                'from' => $email,
                                'verification_code' => $codigo,
                                'body' => 'Código extraído automáticamente'
                            ]
                        ]
                    ];
                }
            } else {
                log_bot("No se encontraron mensajes para asunto: $asunto", 'INFO');
            }
        }
        
        return ['found' => false];
        
    } catch (Exception $e) {
        log_bot("Error buscando en buzón: " . $e->getMessage(), 'ERROR');
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

function extraerCodigoDeEmail($connection, $mensajeId) {
    try {
        // Obtener cuerpo del mensaje
        $cuerpo = imap_body($connection, $mensajeId);
        
        // Decodificar si es necesario
        $cuerpo = quoted_printable_decode($cuerpo);
        
        log_bot("Analizando cuerpo del mensaje para extraer código", 'INFO');
        
        // Patrones para extraer códigos de verificación
        $patrones = [
            '/(\d{6})/',                           // 6 dígitos
            '/(\d{4})/',                           // 4 dígitos  
            '/código[:\s]*(\d+)/i',               // "código: 123456"
            '/verification[:\s]*(\d+)/i',          // "verification: 123456"
            '/code[:\s]*(\d+)/i',                 // "code: 123456"
            '/pin[:\s]*(\d+)/i',                  // "pin: 123456"
        ];
        
        foreach ($patrones as $patron) {
            if (preg_match($patron, $cuerpo, $matches)) {
                $codigo = $matches[1];
                
                // Validar que el código tenga sentido (entre 4 y 8 dígitos)
                if (strlen($codigo) >= 4 && strlen($codigo) <= 8) {
                    log_bot("¡Código extraído exitosamente: $codigo!", 'INFO');
                    return $codigo;
                }
            }
        }
        
        log_bot("No se pudo extraer código del mensaje", 'WARNING');
        return null;
        
    } catch (Exception $e) {
        log_bot("Error extrayendo código: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

function mostrarError($botToken, $chatId, $messageId, $mensaje) {
    $texto = "❌ *Error*\n\n";
    $texto .= escaparMarkdown($mensaje) . "\n\n";
    $texto .= "Contacta al administrador\\.";
    
    editarMensaje($botToken, $chatId, $messageId, $texto, crearTecladoVolver());
}

// ========== FUNCIONES AUXILIARES ==========

function escaparMarkdown($texto) {
    $caracteres = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($caracteres as $char) {
        $texto = str_replace($char, '\\' . $char, $texto);
    }
    return $texto;
}

// ========== PROCESAMIENTO PRINCIPAL ==========

// Obtener datos del webhook
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('{"ok":false,"error":"Invalid JSON"}');
}

log_bot("Webhook recibido: " . $input);

// Procesar el update
try {
    if (isset($update['message'])) {
        // Mensaje de texto
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];
        $firstName = $message['from']['first_name'] ?? 'Usuario';
        
        log_bot("Mensaje de $firstName ($userId): $text");
        
        // Verificar usuario autorizado
        $user = verificarUsuario($userId, $db);
        if (!$user) {
            $texto = "🚫 *Acceso Denegado*\n\n";
            $texto .= "No estás autorizado para usar este bot\\.\n";
            $texto .= "Contacta al administrador del sistema\\.";
            
            enviarMensaje($botToken, $chatId, $texto);
            http_response_code(200);
            exit('{"ok":true}');
        }
        
        // Procesar comandos
        if (strpos($text, '/start') === 0) {
            mostrarMenuPrincipal($botToken, $chatId, $firstName, $user);
        } else {
            // Comando no reconocido - mostrar menú
            mostrarMenuPrincipal($botToken, $chatId, $firstName, $user);
        }
        
    } elseif (isset($update['callback_query'])) {
        // Callback query (botones)
        $callback = $update['callback_query'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId = $callback['from']['id'];
        $firstName = $callback['from']['first_name'] ?? 'Usuario';
        $callbackData = $callback['data'];
        
        log_bot("Callback de $firstName ($userId): $callbackData");
        
        // Verificar usuario autorizado
        $user = verificarUsuario($userId, $db);
        if (!$user) {
            responderCallback($botToken, $callback['id'], "❌ No autorizado");
            http_response_code(200);
            exit('{"ok":true}');
        }
        
        // Responder al callback
        responderCallback($botToken, $callback['id']);
        
        // Procesar callback
        switch (true) {
            case $callbackData === 'menu_principal':
                mostrarMenuPrincipal($botToken, $chatId, $firstName, $user, $messageId);
                break;
                
            case $callbackData === 'buscar_codigos':
            case $callbackData === 'mis_correos':
                mostrarCorreosAutorizados($botToken, $chatId, $messageId, $user, $db);
                break;
                
            case strpos($callbackData, 'emails_page_') === 0:
                $pagina = (int)substr($callbackData, 12);
                mostrarCorreosAutorizados($botToken, $chatId, $messageId, $user, $db, $pagina);
                break;
                
            case strpos($callbackData, 'select_email_') === 0:
                $email = substr($callbackData, 13);
                mostrarPlataformasParaEmail($botToken, $chatId, $messageId, $email, $db);
                break;
                
            case strpos($callbackData, 'search_') === 0:
                $parts = explode('_', $callbackData, 3);
                if (count($parts) === 3) {
                    $email = $parts[1];
                    $plataforma = $parts[2];
                    // ✅ USAR BÚSQUEDA REAL
                    ejecutarBusquedaReal($botToken, $chatId, $messageId, $email, $plataforma, $user, $db);
                }
                break;
                
            case strpos($callbackData, 'show_email_') === 0:
                $parts = explode('_', $callbackData, 5);
                if (count($parts) === 5) {
                    $email = $parts[2];
                    $plataforma = $parts[3];
                    $index = (int)$parts[4];
                    mostrarDetalleEmail($botToken, $chatId, $messageId, $email, $plataforma, $index, $user, $db);
                }
                break;
                
            case $callbackData === 'mi_config':
                mostrarConfiguracionUsuario($botToken, $chatId, $messageId, $user, $db);
                break;
                
            case $callbackData === 'ayuda':
                mostrarAyuda($botToken, $chatId, $messageId);
                break;
                
            case $callbackData === 'admin_panel':
                mostrarPanelAdmin($botToken, $chatId, $messageId, $user, $db);
                break;
                
            default:
                log_bot("Callback no reconocido: $callbackData", 'WARNING');
                mostrarMenuPrincipal($botToken, $chatId, $firstName, $user, $messageId);
                break;
        }
    }
    
    http_response_code(200);
    echo '{"ok":true}';
    
} catch (Exception $e) {
    log_bot("Error procesando update: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo '{"ok":false,"error":"Internal server error"}';
}

// Cerrar conexión
$db->close();
?>