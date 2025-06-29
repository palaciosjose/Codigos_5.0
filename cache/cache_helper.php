<?php
/**
 * Sistema de Cache Completo para mejorar performance
 * Compatible con admin.php avanzado
 */

class SimpleCache {
    private static $cache_dir = 'cache/data/';
    private static $cache_time = 300; // 5 minutos en segundos
    
    /**
     * Inicializar el sistema de cache
     */
    public static function init() {
        if (!file_exists(self::$cache_dir)) {
            mkdir(self::$cache_dir, 0755, true);
        }
    }
    
    /**
     * Obtener configuraciones desde cache o BD
     */
    public static function get_settings($conn) {
        $cache_file = self::$cache_dir . 'settings.json';
        
        // Verificar si existe cache válido
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < self::$cache_time) {
            $cached_data = file_get_contents($cache_file);
            return json_decode($cached_data, true);
        }
        
        // Si no hay cache válido, consultar BD y guardar
        $settings = [];
        $stmt = $conn->prepare("SELECT name, value FROM settings");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $settings[$row['name']] = $row['value'];
        }
        $stmt->close();
        
        // Guardar en cache
        file_put_contents($cache_file, json_encode($settings));
        
        return $settings;
    }
    
    /**
     * Limpiar TODO el cache
     */
    public static function clear_cache() {
        $files = glob(self::$cache_dir . '*.json');
        $cleared = 0;
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
                $cleared++;
            }
        }
        return $cleared;
    }
    
    /**
     * Limpiar solo cache de configuraciones
     */
    public static function clear_settings_cache() {
        $cache_file = self::$cache_dir . 'settings.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
            return true;
        }
        return false;
    }
    
    /**
     * Limpiar cache de plataformas
     */
    public static function clear_platforms_cache() {
        $cache_file = self::$cache_dir . 'platforms.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
            return true;
        }
        return false;
    }
    
    /**
     * Limpiar cache de servidores
     */
    public static function clear_servers_cache() {
        $cache_file = self::$cache_dir . 'enabled_servers.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
            return true;
        }
        return false;
    }
    
    /**
     * Invalidar y recargar configuraciones inmediatamente
     * FUNCIÓN CRÍTICA PARA admin.php
     */
    public static function invalidate_and_reload_settings($conn) {
        // 1. Eliminar cache existente
        $cache_file = self::$cache_dir . 'settings.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        
        // 2. Cargar datos frescos inmediatamente
        $settings = [];
        $stmt = $conn->prepare("SELECT name, value FROM settings");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $settings[$row['name']] = $row['value'];
        }
        $stmt->close();
        
        // 3. Guardar en cache nuevo
        file_put_contents($cache_file, json_encode($settings));
        
        return $settings;
    }
    
    /**
     * Obtener plataformas y asuntos desde cache o BD
     */
    public static function get_platform_subjects($conn) {
        $cache_file = self::$cache_dir . 'platforms.json';
        
        // Verificar si existe cache válido
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < self::$cache_time) {
            $cached_data = file_get_contents($cache_file);
            return json_decode($cached_data, true);
        }
        
        // Si no hay cache válido, consultar BD
        $platforms = [];
        
        // Obtener todas las plataformas con sus asuntos en una sola consulta optimizada
        $query = "
            SELECT p.name as platform_name, ps.subject 
            FROM platforms p 
            LEFT JOIN platform_subjects ps ON p.id = ps.platform_id 
            ORDER BY p.sort_order ASC, ps.subject ASC
        ";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $platform_name = $row['platform_name'];
                if (!isset($platforms[$platform_name])) {
                    $platforms[$platform_name] = [];
                }
                if (!empty($row['subject'])) {
                    $platforms[$platform_name][] = $row['subject'];
                }
            }
        }
        
        // Guardar en cache
        file_put_contents($cache_file, json_encode($platforms));
        
        return $platforms;
    }
    
    /**
     * Obtener servidores habilitados desde cache o BD
     */
    public static function get_enabled_servers($conn) {
        $cache_file = self::$cache_dir . 'enabled_servers.json';
        
        // Verificar si existe cache válido
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < self::$cache_time) {
            $cached_data = file_get_contents($cache_file);
            return json_decode($cached_data, true);
        }
        
        // Si no hay cache válido, consultar BD
        $servers = [];
        
        $query = "SELECT * FROM email_servers WHERE enabled = 1 ORDER BY id ASC";
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $servers[] = $row;
            }
        }
        
        // Guardar en cache
        file_put_contents($cache_file, json_encode($servers));
        
        return $servers;
    }
    
    /**
     * Verificar si el cache necesita ser limpiado
     */
    public static function needs_refresh($cache_type = 'settings') {
        $cache_file = self::$cache_dir . $cache_type . '.json';
        
        if (!file_exists($cache_file)) {
            return true;
        }
        
        return (time() - filemtime($cache_file)) >= self::$cache_time;
    }
    
    /**
     * Obtener información del estado del cache
     */
    public static function get_cache_info() {
        $cache_files = ['settings', 'platforms', 'enabled_servers'];
        $info = [];
        
        foreach ($cache_files as $cache_type) {
            $cache_file = self::$cache_dir . $cache_type . '.json';
            if (file_exists($cache_file)) {
                $info[$cache_type] = [
                    'exists' => true,
                    'size' => filesize($cache_file),
                    'last_modified' => filemtime($cache_file),
                    'age_seconds' => time() - filemtime($cache_file),
                    'needs_refresh' => self::needs_refresh($cache_type)
                ];
            } else {
                $info[$cache_type] = [
                    'exists' => false,
                    'needs_refresh' => true
                ];
            }
        }
        
        return $info;
    }
    
    /**
     * Reset inteligente basado en el tipo de cambio
     */
    public static function smart_cache_reset($change_type = 'settings') {
        switch ($change_type) {
            case 'settings':
            case 'config':
                self::clear_settings_cache();
                break;
                
            case 'platforms':
            case 'subjects':
                self::clear_platforms_cache();
                break;
                
            case 'servers':
            case 'imap':
                self::clear_servers_cache();
                break;
                
            case 'all':
            default:
                self::clear_cache();
                break;
        }
    }
    
    /**
     * Reset automático con notificación
     */
    public static function auto_reset_with_notification($change_type = 'all', $show_notification = true) {
        $cleared = false;
        
        switch ($change_type) {
            case 'settings':
                $cleared = self::clear_settings_cache();
                $message = "Cache de configuraciones actualizado automáticamente";
                break;
                
            case 'platforms':
                $cleared = self::clear_platforms_cache();
                $message = "Cache de plataformas actualizado automáticamente";
                break;
                
            case 'servers':
                $cleared = self::clear_servers_cache();
                $message = "Cache de servidores actualizado automáticamente";
                break;
                
            case 'all':
            default:
                $cleared_count = self::clear_cache();
                $cleared = $cleared_count > 0;
                $message = "Cache completo actualizado automáticamente ($cleared_count archivos)";
                break;
        }
        
        if ($show_notification && $cleared) {
            // Almacenar mensaje para mostrar en la siguiente carga
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['cache_reset_message'] = $message;
        }
        
        return $cleared;
    }
    
    /**
     * Establecer tiempo de vida del cache dinámicamente
     */
    public static function set_cache_time($seconds) {
        self::$cache_time = (int)$seconds;
    }
    
    /**
     * Obtener tiempo de vida actual del cache
     */
    public static function get_cache_time() {
        return self::$cache_time;
    }
    
    /**
     * Verificar si un archivo de cache específico existe
     */
    public static function cache_exists($cache_type) {
        $cache_file = self::$cache_dir . $cache_type . '.json';
        return file_exists($cache_file);
    }
    
    /**
     * Obtener la edad de un cache específico en segundos
     */
    public static function get_cache_age($cache_type) {
        $cache_file = self::$cache_dir . $cache_type . '.json';
        if (file_exists($cache_file)) {
            return time() - filemtime($cache_file);
        }
        return -1; // No existe
    }
    
    /**
     * Forzar limpieza y regeneración de un cache específico
     */
    public static function force_refresh($cache_type, $conn) {
        switch ($cache_type) {
            case 'settings':
                self::clear_settings_cache();
                return self::get_settings($conn);
                
            case 'platforms':
                self::clear_platforms_cache();
                return self::get_platform_subjects($conn);
                
            case 'servers':
                self::clear_servers_cache();
                return self::get_enabled_servers($conn);
                
            default:
                return false;
        }
    }
}

// ================================
// FUNCIONES HELPER PARA ADMIN
// ================================

/**
 * Usar en admin.php después de guardar configuraciones
 */
function reset_cache_after_config_save($config_type = 'all') {
    // Reset inmediato y agresivo
    SimpleCache::auto_reset_with_notification($config_type, true);
    
    // Verificar que se limpio correctamente
    $cache_info = SimpleCache::get_cache_info();
    
    $success = true;
    foreach ($cache_info as $cache_type => $info) {
        if (!$info['needs_refresh']) {
            // Si no necesita refresh, significa que no se limpió correctamente
            $success = false;
            break;
        }
    }
    
    return $success;
}

/**
 * Mostrar notificación de cache reset
 */
function show_cache_reset_notification() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['cache_reset_message'])) {
        $message = $_SESSION['cache_reset_message'];
        unset($_SESSION['cache_reset_message']);
        
        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-sync-alt me-2"></i>
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
}

// Inicializar cache al incluir el archivo
SimpleCache::init();
?>
