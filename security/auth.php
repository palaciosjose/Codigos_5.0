<?php
/**
 * Archivo de seguridad para autenticación y verificación de sesiones
 */

// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si hay una sesión activa
 * @return bool Verdadero si el usuario está autenticado, falso en caso contrario
 */
function is_authenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Verifica si el usuario tiene rol de administrador
 * @return bool Verdadero si el usuario es administrador, falso en caso contrario
 */
function is_admin() {
    return is_authenticated() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Verifica si se requiere inicio de sesión según la configuración
 * @param mysqli $conn Conexión a la base de datos
 * @return bool Verdadero si se requiere login, falso en caso contrario
 */
function is_login_required($conn) {
    // Verificar si la tabla settings existe
    $result = $conn->query("SHOW TABLES LIKE 'settings'");
    if (!$result || $result->num_rows == 0) {
        // Si la tabla no existe, asumir que sí se requiere login por defecto
        return true;
    }
    
    // Si la tabla existe, consultar el valor
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'REQUIRE_LOGIN'");
    if (!$stmt) {
        return true; // En caso de error, asumir que se requiere login
    }
    
    $stmt->execute();
    $stmt->bind_result($require_login);
    $stmt->fetch();
    $stmt->close();
    
    return $require_login === '1';
}

/**
 * Verifica si la sesión ha expirado por inactividad (15 minutos)
 * @return bool Verdadero si la sesión ha expirado, falso en caso contrario
 */
function is_session_expired() {
    $max_lifetime = 900; // 15 minutos en segundos
    
    if (!isset($_SESSION['last_activity'])) {
        return true; // Si no existe el timestamp, consideramos que expiró
    }
    
    $inactive_time = time() - $_SESSION['last_activity'];
    return $inactive_time > $max_lifetime;
}

/**
 * Actualiza el timestamp de la última actividad
 */
function update_session_activity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Verifica la sesión y actualiza el timestamp si está activa
 * Si la sesión expiró, redirecciona al login
 * @param bool $require_admin Si es verdadero, verifica también que el usuario sea administrador
 * @param string $redirect_url URL a la que redireccionar si falla la verificación
 * @param bool $check_required_login Si es verdadero, verifica si se requiere login según configuración
 */
function check_session($require_admin = false, $redirect_url = '/index.php', $check_required_login = true) {
    global $conn;
    
    // Primero verificar si el sistema está instalado
    if (function_exists('is_installed') && !is_installed()) {
        // No hacer redirección si el sistema no está instalado aún
        return;
    }
    
    // Si no es necesario verificar el login requerido o si estamos verificando para admin
    if (!$check_required_login || $require_admin) {
        // Verificar sesión expirada
        if (is_session_expired()) {
            session_unset();
            session_destroy();
            header("Location: $redirect_url");
            exit();
        }
        
        // Verificar autenticación
        if (!is_authenticated()) {
            header("Location: $redirect_url");
            exit();
        }
        
        // Verificar rol admin si es necesario
        if ($require_admin && !is_admin()) {
            header("Location: $redirect_url");
            exit();
        }
        
        // Actualizar timestamp
        update_session_activity();
    } 
    // Si es necesario verificar el login requerido y no es para admin
    else {
        // Verificar si la tabla settings existe antes de consultar
        $result = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($result && $result->num_rows > 0) {
            // Obtener configuración de login requerido
            $require_login = is_login_required($conn);
            
            // Si el login es requerido, verificar sesión
            if ($require_login) {
                if (is_session_expired()) {
                    session_unset();
                    session_destroy();
                    header("Location: $redirect_url");
                    exit();
                }
                
                if (!is_authenticated()) {
                    header("Location: $redirect_url");
                    exit();
                }
                
                update_session_activity();
            }
        }
    }
}

/**
 * Cerrar la sesión del usuario
 * @param string $redirect_url URL a la que redireccionar después del logout
 */
function logout($redirect_url = '/index.php') {
    // Destruir la sesión
    session_unset();
    session_destroy();
    
    // Redireccionar
    header("Location: $redirect_url");
    exit();
}
