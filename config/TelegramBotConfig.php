<?php
// config/TelegramBotConfig.php - Configuración del Bot de Telegram
// Integrado con la base de datos del panel de administración

class TelegramBotConfig {
    private static $config = null;
    private static $db = null;
    
    // Configuración por defecto
    public static $BOT_TOKEN = '';
    public static $WEBHOOK_URL = '';
    public static $WEBHOOK_SECRET = '';
    
    // Constantes del bot
    public const MAX_MESSAGE_LENGTH = 4096;
    public const RATE_LIMIT_WINDOW = 60;
    public const MAX_REQUESTS_PER_MINUTE = 30;
    
    public const COMMANDS = [
        'start' => 'Iniciar bot y vincular cuenta',
        'buscar' => 'Buscar códigos por email y plataforma',
        'codigo' => 'Obtener código por ID',
        'ayuda' => 'Mostrar esta ayuda',
        'config' => 'Ver tu configuración personal'
    ];
    
    /**
     * Carga la configuración desde la base de datos
     */
    public static function load() {
        if (self::$config !== null) {
            return; // Ya está cargado
        }
        
        try {
            // Obtener configuración de base de datos
            self::loadDatabaseConfig();
            
            // Cargar configuración desde BD
            $config = self::getConfigFromDatabase();
            
            if ($config) {
                self::$BOT_TOKEN = $config['token'] ?? '';
                self::$WEBHOOK_URL = $config['webhook'] ?? '';
                self::$WEBHOOK_SECRET = $config['webhook_secret'] ?? '';
            }
            
            self::$config = $config ?: [];
            
        } catch (Exception $e) {
            error_log("Error cargando configuración del bot: " . $e->getMessage());
        }
    }
    
    /**
     * Carga configuración de la base de datos
     */
    private static function loadDatabaseConfig() {
        // Cargar configuración de BD desde el archivo del instalador
        $dbConfigFile = __DIR__ . '/../instalacion/basededatos.php';
        if (file_exists($dbConfigFile)) {
            include $dbConfigFile;
            
            self::$db = new mysqli($db_host, $db_user, $db_password, $db_name);
            if (self::$db->connect_error) {
                throw new Exception('Error conectando a BD: ' . self::$db->connect_error);
            }
            self::$db->set_charset('utf8mb4');
        } else {
            throw new Exception('Archivo de configuración de BD no encontrado');
        }
    }
    
    /**
     * Obtiene la configuración desde la base de datos
     */
    private static function getConfigFromDatabase() {
        if (!self::$db) {
            return null;
        }
        
        $config = [];
        $result = self::$db->query("SELECT setting_name, setting_value FROM telegram_bot_config");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $config[$row['setting_name']] = $row['setting_value'];
            }
        }
        
        return $config;
    }
    
    /**
     * Obtiene la conexión a la base de datos
     */
    public static function getDatabaseConnection() {
        if (!self::$db) {
            self::loadDatabaseConfig();
        }
        return self::$db;
    }
    
    /**
     * Verifica si la configuración está completa
     */
    public static function isConfigured() {
        self::load();
        return !empty(self::$BOT_TOKEN) && !empty(self::$WEBHOOK_URL);
    }
    
    /**
     * Registra actividad del bot en la base de datos
     */
    public static function logActivity($user_id, $telegram_id, $action, $details = '') {
        try {
            if (!self::$db) {
                self::loadDatabaseConfig();
            }
            
            $stmt = self::$db->prepare("INSERT INTO telegram_bot_logs (user_id, telegram_id, action, details) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $telegram_id, $action, $details);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error registrando actividad del bot: " . $e->getMessage());
        }
    }
    
    /**
     * Obtiene información de usuario por telegram_id
     */
    public static function getUserByTelegramId($telegram_id) {
        try {
            if (!self::$db) {
                self::loadDatabaseConfig();
            }
            
            $stmt = self::$db->prepare("SELECT id, username, telegram_id FROM users WHERE telegram_id = ?");
            $stmt->bind_param("s", $telegram_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return $row;
            }
            
            $stmt->close();
            return null;
        } catch (Exception $e) {
            error_log("Error obteniendo usuario: " . $e->getMessage());
            return null;
        }
    }
}

// Auto-cargar configuración al incluir el archivo
TelegramBotConfig::load();
?>