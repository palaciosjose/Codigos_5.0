<?php
// shared/TelegramIntegration.php
namespace Shared;

/**
 * Integración específica para funcionalidades de Telegram
 */
class TelegramIntegration
{
    private \mysqli $db;
    
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }
    
    /**
     * Marca un log de búsqueda como originado desde Telegram
     */
    public function markLogAsTelegram(int $logId, int $chatId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE search_logs 
                SET telegram_chat_id = ?, source = 'telegram' 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $chatId, $logId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (\Exception $e) {
            error_log("Error marking log as Telegram: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra actividad de usuario en Telegram
     */
    public function logActivity(int $telegramId, string $action, array $details = []): bool
    {
        try {
            $detailsJson = json_encode($details);
            
            $stmt = $this->db->prepare("
                INSERT INTO telegram_activity_log (telegram_id, action, details, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    action = VALUES(action),
                    details = VALUES(details),
                    created_at = VALUES(created_at)
            ");
            $stmt->bind_param('iss', $telegramId, $action, $detailsJson);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (\Exception $e) {
            // Si la tabla no existe, crear silenciosamente el log en archivos
            error_log("Telegram activity: User $telegramId - Action: $action - Details: " . json_encode($details));
            return true;
        }
    }
    
    /**
     * Obtiene estadísticas de uso del bot de Telegram
     */
    public function getTelegramStats(): array
    {
        try {
            $stats = [];
            
            // Usuarios activos en Telegram
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT telegram_id) as active_users 
                FROM users 
                WHERE telegram_id IS NOT NULL 
                AND status = 1 
                AND last_telegram_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['active_users'] = $row['active_users'] ?? 0;
            $stmt->close();
            
            // Búsquedas de hoy
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as searches_today 
                FROM search_logs 
                WHERE source = 'telegram' 
                AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['searches_today'] = $row['searches_today'] ?? 0;
            $stmt->close();
            
            // Total de búsquedas via Telegram
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_searches 
                FROM search_logs 
                WHERE source = 'telegram'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['total_searches'] = $row['total_searches'] ?? 0;
            $stmt->close();
            
            // Top usuarios (últimos 7 días)
            $stmt = $this->db->prepare("
                SELECT u.username, COUNT(sl.id) as searches
                FROM search_logs sl
                JOIN users u ON sl.user_id = u.id
                WHERE sl.source = 'telegram'
                AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY u.id, u.username
                ORDER BY searches DESC
                LIMIT 5
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $topUsers = [];
            while ($row = $result->fetch_assoc()) {
                $topUsers[] = [
                    'username' => $row['username'],
                    'searches' => (int)$row['searches']
                ];
            }
            $stats['top_users'] = $topUsers;
            $stmt->close();
            
            // Estadísticas por plataforma (últimos 7 días)
            $stmt = $this->db->prepare("
                SELECT platform, COUNT(*) as searches
                FROM search_logs
                WHERE source = 'telegram'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY platform
                ORDER BY searches DESC
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $platformStats = [];
            while ($row = $result->fetch_assoc()) {
                $platformStats[] = [
                    'platform' => $row['platform'],
                    'searches' => (int)$row['searches']
                ];
            }
            $stats['platform_stats'] = $platformStats;
            $stmt->close();
            
            return $stats;
            
        } catch (\Exception $e) {
            error_log("Error getting Telegram stats: " . $e->getMessage());
            return [
                'active_users' => 0,
                'searches_today' => 0,
                'total_searches' => 0,
                'top_users' => [],
                'platform_stats' => []
            ];
        }
    }
    
    /**
     * Obtiene el chat_id asociado a un telegram_id
     */
    public function getChatIdForUser(int $telegramId): ?int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT telegram_chat_id 
                FROM search_logs 
                WHERE telegram_chat_id IS NOT NULL
                AND user_id IN (SELECT id FROM users WHERE telegram_id = ?)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param('i', $telegramId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return $row ? (int)$row['telegram_chat_id'] : null;
            
        } catch (\Exception $e) {
            error_log("Error getting chat ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica si un usuario es administrador del bot
     */
    public function isUserAdmin(int $telegramId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT role 
                FROM users 
                WHERE telegram_id = ? 
                AND status = 1
            ");
            $stmt->bind_param('i', $telegramId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return $row && ($row['role'] === 'admin' || $row['role'] === 'superadmin');
            
        } catch (\Exception $e) {
            error_log("Error checking admin status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene códigos por ID para el comando /codigo
     */
    public function getCodeById(int $codeId): ?array
    {
        try {
            // Primero intentar en la tabla de códigos específica
            $stmt = $this->db->prepare("
                SELECT * 
                FROM codes 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $codeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row) {
                return $row;
            }
            
            // Si no existe tabla codes, buscar en search_logs
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    email,
                    platform,
                    result_details,
                    created_at,
                    status
                FROM search_logs 
                WHERE id = ? AND status = 'found'
            ");
            $stmt->bind_param('i', $codeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row && $row['result_details']) {
                $details = json_decode($row['result_details'], true);
                if ($details && isset($details['content'])) {
                    return [
                        'id' => $row['id'],
                        'code' => $details['content'],
                        'platform' => $row['platform'],
                        'email' => $row['email'],
                        'created_at' => $row['created_at'],
                        'details' => $details
                    ];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            error_log("Error getting code by ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene la configuración de usuario para el comando /config
     */
    public function getUserConfiguration(int $telegramId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    username,
                    telegram_id,
                    status,
                    role,
                    last_telegram_activity,
                    created_at
                FROM users 
                WHERE telegram_id = ?
            ");
            $stmt->bind_param('i', $telegramId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if (!$row) {
                return null;
            }
            
            // Obtener permisos de emails
            $stmt = $this->db->prepare("
                SELECT ae.email 
                FROM user_authorized_emails uae
                JOIN authorized_emails ae ON uae.authorized_email_id = ae.id
                WHERE uae.user_id = ?
            ");
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $emails = [];
            while ($emailRow = $result->fetch_assoc()) {
                $emails[] = $emailRow['email'];
            }
            $stmt->close();
            
            // Obtener permisos de plataformas
            $stmt = $this->db->prepare("
                SELECT p.name, ups.subject_keyword
                FROM user_platform_subjects ups
                JOIN platforms p ON ups.platform_id = p.id
                WHERE ups.user_id = ?
            ");
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $subjects = [];
            while ($subjectRow = $result->fetch_assoc()) {
                if (!isset($subjects[$subjectRow['name']])) {
                    $subjects[$subjectRow['name']] = [];
                }
                $subjects[$subjectRow['name']][] = $subjectRow['subject_keyword'];
            }
            $stmt->close();
            
            return [
                'user_id' => $row['id'],
                'username' => $row['username'],
                'telegram_id' => $row['telegram_id'],
                'status' => (bool)$row['status'],
                'role' => $row['role'] ?? 'user',
                'last_activity' => $row['last_telegram_activity'],
                'permissions' => [
                    'emails' => $emails,
                    'subjects' => $subjects
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("Error getting user configuration: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene las plataformas disponibles
     */
    public function getAvailablePlatforms(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT name, description 
                FROM platforms 
                WHERE status = 1 
                ORDER BY name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $platforms = [];
            while ($row = $result->fetch_assoc()) {
                $platforms[] = [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? $row['name']
                ];
            }
            $stmt->close();
            
            return $platforms;
            
        } catch (\Exception $e) {
            error_log("Error getting available platforms: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpia logs antiguos para optimización
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM search_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND source = 'telegram'
            ");
            $stmt->bind_param('i', $daysToKeep);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            return $affected;
            
        } catch (\Exception $e) {
            error_log("Error cleaning up old logs: " . $e->getMessage());
            return 0;
        }
    }
}