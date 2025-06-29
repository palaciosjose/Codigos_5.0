<?php
/**
 * Configuración unificada del bot de Telegram
 * Generado automáticamente: 2025-06-29 18:17:02
 */

class TelegramBotSettings {
    private static $db = null;
    
    public static function get($key) {
        if (self::$db === null) {
            require_once __DIR__ . '/../instalacion/basededatos.php';
            self::$db = new mysqli($db_host, $db_user, $db_password, $db_name);
            self::$db->set_charset('utf8mb4');
        }
        
        $stmt = self::$db->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $value = $result->fetch_assoc()['value'] ?? '';
        $stmt->close();
        
        return $value;
    }
    
    public static function set($key, $value) {
        if (self::$db === null) {
            require_once __DIR__ . '/../instalacion/basededatos.php';
            self::$db = new mysqli($db_host, $db_user, $db_password, $db_name);
            self::$db->set_charset('utf8mb4');
        }
        
        $stmt = self::$db->prepare('UPDATE settings SET value = ? WHERE name = ?');
        $stmt->bind_param('ss', $value, $key);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
}
?>