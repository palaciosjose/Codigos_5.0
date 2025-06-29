<?php
/**
 * TelegramBotManager.php
 * Clase para gestionar el bot de Telegram con sincronización completa con la web
 */

namespace Shared;

class TelegramBotManager
{
    private \mysqli $db;
    private array $settings;
    private string $botToken;
    private bool $loggingEnabled;
    
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->loadSettings();
        $this->botToken = $this->settings['TELEGRAM_BOT_TOKEN'] ?? '';
        $this->loggingEnabled = ($this->settings['TELEGRAM_LOG_ACTIVITY'] ?? '1') === '1';
    }
    
    // ========== CONFIGURACIÓN ==========
    
    private function loadSettings(): void
    {
        $this->settings = [];
        $query = "SELECT name, value FROM settings";
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->settings[$row['name']] = $row['value'];
            }
        }
    }
    
    public function isEnabled(): bool
    {
        return ($this->settings['TELEGRAM_BOT_ENABLED'] ?? '0') === '1' && !empty($this->botToken);
    }
    
    // ========== VALIDACIÓN DE USUARIOS ==========
    
    public function validateUser(int $telegramId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, role, status, created_at 
                FROM users 
                WHERE telegram_id = ? AND status = 1
            ");
            $stmt->bind_param("i", $telegramId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $stmt->close();
                
                // Verificar rate limiting
                if ($this->checkRateLimit($user['id'])) {
                    return $user;
                }
            }
            
            $stmt->close();
            return null;
        } catch (\Exception $e) {
            $this->logError("Error validating user: " . $e->getMessage());
            return null;
        }
    }
    
    // ========== RATE LIMITING ==========
    
    private function checkRateLimit(int $userId): bool
    {
        $rateLimit = (int)($this->settings['TELEGRAM_RATE_LIMIT'] ?? 30);
        $maxRequests = (int)($this->settings['MAX_TELEGRAM_REQUESTS_PER_USER'] ?? 100);
        
        try {
            // Verificar límite por hora
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM telegram_bot_logs 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $hourlyCount = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            if ($hourlyCount >= $rateLimit) {
                return false;
            }
            
            // Verificar límite diario
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM telegram_bot_logs 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $dailyCount = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            return $dailyCount < $maxRequests;
            
        } catch (\Exception $e) {
            $this->logError("Error checking rate limit: " . $e->getMessage());
            return true; // En caso de error, permitir el acceso
        }
    }
    
    // ========== AUTORIZACIÓN DE CORREOS ==========
    
    public function getUserAuthorizedEmails(int $userId): array
    {
        try {
            // Verificar si EMAIL_AUTH_ENABLED está activo
            if (($this->settings['EMAIL_AUTH_ENABLED'] ?? '1') !== '1') {
                // Si no está habilitada la autenticación, retornar array vacío
                // para que el sistema maneje como "sin restricciones"
                return [];
            }
            
            // Obtener correos específicos del usuario o todos si no tiene restricciones
            $stmt = $this->db->prepare("
                SELECT ae.email 
                FROM authorized_emails ae
                LEFT JOIN user_authorized_emails uae ON ae.id = uae.authorized_email_id AND uae.user_id = ?
                WHERE ae.status = 1 AND (
                    uae.user_id IS NOT NULL OR 
                    NOT EXISTS (SELECT 1 FROM user_authorized_emails WHERE user_id = ?)
                )
                ORDER BY ae.email
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
        } catch (\Exception $e) {
            $this->logError("Error getting authorized emails: " . $e->getMessage());
            return [];
        }
    }
    
    public function isEmailAuthorized(string $email, int $userId): bool
    {
        $authorizedEmails = $this->getUserAuthorizedEmails($userId);
        
        // Si no hay restricciones (array vacío), verificar si está en la lista global
        if (empty($authorizedEmails)) {
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM authorized_emails 
                    WHERE email = ? AND status = 1
                ");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_assoc()['count'] ?? 0;
                $stmt->close();
                
                return $count > 0;
            } catch (\Exception $e) {
                $this->logError("Error checking global email authorization: " . $e->getMessage());
                return false;
            }
        }
        
        return in_array($email, $authorizedEmails);
    }
    
    // ========== PLATAFORMAS Y ASUNTOS ==========
    
    public function getAvailablePlatforms(int $userId = null): array
    {
        try {
            // Verificar si hay restricciones por usuario
            $userRestricted = false;
            if ($userId && ($this->settings['USER_SUBJECT_RESTRICTIONS_ENABLED'] ?? '0') === '1') {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM user_platform_subjects 
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userRestricted = ($result->fetch_assoc()['count'] ?? 0) > 0;
                $stmt->close();
            }
            
            if ($userRestricted) {
                // Usuario con restricciones específicas
                $stmt = $this->db->prepare("
                    SELECT DISTINCT p.name, p.display_name 
                    FROM platforms p
                    INNER JOIN user_platform_subjects ups ON p.id = ups.platform_id
                    WHERE ups.user_id = ? AND p.status = 1
                    ORDER BY p.display_name
                ");
                $stmt->bind_param("i", $userId);
            } else {
                // Sin restricciones o usuario sin restricciones específicas
                $stmt = $this->db->prepare("
                    SELECT DISTINCT p.name, p.display_name 
                    FROM platforms p 
                    INNER JOIN platform_subjects ps ON p.id = ps.platform_id 
                    WHERE p.status = 1 
                    ORDER BY p.display_name
                ");
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $platforms = [];
            while ($row = $result->fetch_assoc()) {
                $platforms[$row['name']] = $row['display_name'];
            }
            $stmt->close();
            
            return $platforms;
        } catch (\Exception $e) {
            $this->logError("Error getting platforms: " . $e->getMessage());
            return [];
        }
    }
    
    // ========== LOGGING Y ESTADÍSTICAS ==========
    
    public function logActivity(int $userId, int $telegramChatId, int $telegramUserId, string $actionType, array $actionData = [], string $status = 'success', int $responseTime = 0): void
    {
        if (!$this->loggingEnabled) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO telegram_bot_logs 
                (user_id, telegram_chat_id, telegram_user_id, action_type, action_data, response_status, response_time_ms, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $actionDataJson = json_encode($actionData);
            $stmt->bind_param("iiisssi", $userId, $telegramChatId, $telegramUserId, $actionType, $actionDataJson, $status, $responseTime);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            error_log("Error logging telegram activity: " . $e->getMessage());
        }
    }
    
    public function getUserStats(int $userId): array
    {
        try {
            $stats = [];
            
            // Búsquedas totales
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_searches 
                FROM search_logs 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['total_searches'] = $result->fetch_assoc()['total_searches'] ?? 0;
            $stmt->close();
            
            // Búsquedas exitosas
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as successful_searches 
                FROM search_logs 
                WHERE user_id = ? AND status = 'found'
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['successful_searches'] = $result->fetch_assoc()['successful_searches'] ?? 0;
            $stmt->close();
            
            // Actividad en Telegram
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as telegram_actions 
                FROM telegram_bot_logs 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['telegram_actions'] = $result->fetch_assoc()['telegram_actions'] ?? 0;
            $stmt->close();
            
            // Última actividad
            $stmt = $this->db->prepare("
                SELECT MAX(created_at) as last_activity 
                FROM telegram_bot_logs 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $lastActivity = $result->fetch_assoc()['last_activity'];
            $stats['last_activity'] = $lastActivity ? date('d/m/Y H:i', strtotime($lastActivity)) : 'Nunca';
            $stmt->close();
            
            return $stats;
        } catch (\Exception $e) {
            $this->logError("Error getting user stats: " . $e->getMessage());
            return [];
        }
    }
    
    // ========== GESTIÓN DE DATOS TEMPORALES ==========
    
    public function storeTempData(int $userId, string $dataType, array $data, int $expirationHours = 2): bool
    {
        try {
            $dataJson = json_encode($data);
            $expiresAt = date('Y-m-d H:i:s', time() + ($expirationHours * 3600));
            
            $stmt = $this->db->prepare("
                INSERT INTO telegram_temp_data (user_id, data_type, data_content, expires_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                data_content = VALUES(data_content), 
                expires_at = VALUES(expires_at),
                created_at = NOW()
            ");
            $stmt->bind_param("isss", $userId, $dataType, $dataJson, $expiresAt);
            $stmt->execute();
            $stmt->close();
            
            return true;
        } catch (\Exception $e) {
            $this->logError("Error storing temp data: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTempData(int $userId, string $dataType): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT data_content 
                FROM telegram_temp_data 
                WHERE user_id = ? AND data_type = ? 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param("is", $userId, $dataType);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return json_decode($row['data_content'], true);
            }
            
            $stmt->close();
            return null;
        } catch (\Exception $e) {
            $this->logError("Error getting temp data: " . $e->getMessage());
            return null;
        }
    }
    
    // ========== UTILIDADES ==========
    
    public function escapeMD2(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($chars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
    
    public function formatEmailForDisplay(string $email): string
    {
        return '`' . $this->escapeMD2($email) . '`';
    }
    
    private function logError(string $message): void
    {
        error_log("[TelegramBotManager] " . $message);
        
        if ($this->loggingEnabled) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO telegram_bot_logs 
                    (action_type, action_data, response_status, created_at) 
                    VALUES ('system_error', ?, 'error', NOW())
                ");
                $stmt->bind_param("s", $message);
                $stmt->execute();
                $stmt->close();
            } catch (\Exception $e) {
                error_log("[TelegramBotManager] Failed to log error to database: " . $e->getMessage());
            }
        }
    }
    
    // ========== COMANDOS Y VALIDACIONES ==========
    
    public function validateCommand(string $command, int $userId): array
    {
        $result = ['valid' => false, 'error' => '', 'cooldown' => false];
        
        // Verificar cooldown
        $cooldown = (int)($this->settings['TELEGRAM_COMMAND_COOLDOWN'] ?? 2);
        if ($cooldown > 0) {
            try {
                $stmt = $this->db->prepare("
                    SELECT created_at 
                    FROM telegram_bot_logs 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result_db = $stmt->get_result();
                
                if ($row = $result_db->fetch_assoc()) {
                    $lastAction = strtotime($row['created_at']);
                    $timeDiff = time() - $lastAction;
                    
                    if ($timeDiff < $cooldown) {
                        $result['cooldown'] = true;
                        $result['error'] = "Espera " . ($cooldown - $timeDiff) . " segundos antes del próximo comando.";
                        $stmt->close();
                        return $result;
                    }
                }
                $stmt->close();
            } catch (\Exception $e) {
                $this->logError("Error checking command cooldown: " . $e->getMessage());
            }
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    // ========== OBTENER CONFIGURACIÓN WEB ==========
    
    public function getWebConfig(): array
    {
        return [
            'email_auth_enabled' => ($this->settings['EMAIL_AUTH_ENABLED'] ?? '1') === '1',
            'require_login' => ($this->settings['REQUIRE_LOGIN'] ?? '1') === '1',
            'user_email_restrictions' => ($this->settings['USER_EMAIL_RESTRICTIONS_ENABLED'] ?? '1') === '1',
            'user_subject_restrictions' => ($this->settings['USER_SUBJECT_RESTRICTIONS_ENABLED'] ?? '1') === '1',
            'admin_email_override' => ($this->settings['ADMIN_EMAIL_OVERRIDE'] ?? '1') === '1',
            'page_title' => $this->settings['PAGE_TITLE'] ?? 'Sistema de Códigos',
            'footer_whatsapp' => $this->settings['enlace_global_numero_whatsapp'] ?? '',
            'footer_text' => $this->settings['enlace_global_texto_whatsapp'] ?? ''
        ];
    }
}