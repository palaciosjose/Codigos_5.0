<?php
// telegram_bot/services/TelegramQuery.php
namespace TelegramBot\Services;

use Shared\DatabaseManager;
use Shared\UnifiedQueryEngine;
use Shared\TelegramIntegration;

/**
 * Procesamiento de solicitudes de búsqueda provenientes de Telegram.
 */
class TelegramQuery
{
    private \mysqli $db;
    private TelegramAuth $auth;
    private UnifiedQueryEngine $engine;
    private TelegramIntegration $integration;

    /** @var array */
    private array $settings;

    public function __construct(TelegramAuth $auth)
    {
        $this->db = DatabaseManager::getInstance()->getConnection();
        $this->auth = $auth;
        $this->engine = new UnifiedQueryEngine($this->db);
        $this->integration = new TelegramIntegration($this->db);
        $this->settings = $this->loadSettings();
    }

    /**
     * Carga configuraciones relevantes desde la base de datos.
     */
    private function loadSettings(): array
    {
        $settings = [];
        $query = "SELECT name, value FROM settings WHERE name IN ('EMAIL_AUTH_ENABLED','USER_EMAIL_RESTRICTIONS_ENABLED','USER_SUBJECT_RESTRICTIONS_ENABLED')";
        $res = $this->db->query($query);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $settings[$row['name']] = $row['value'];
            }
            $res->close();
        }
        return $settings;
    }

    /**
     * Registra actividad de usuario
     */
    public function logActivity(int $telegramId, string $action, array $details = []): bool
    {
        return $this->integration->logActivity($telegramId, $action, $details);
    }

    private function isEmailAllowed(int $userId, string $email): bool
    {
        if (($this->settings['EMAIL_AUTH_ENABLED'] ?? '0') !== '1') {
            return true;
        }

        $stmt = $this->db->prepare('SELECT id FROM authorized_emails WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }

        if (($this->settings['USER_EMAIL_RESTRICTIONS_ENABLED'] ?? '0') !== '1') {
            return true;
        }

        $authId = $row['id'];
        $stmt = $this->db->prepare('SELECT 1 FROM user_authorized_emails WHERE user_id=? AND authorized_email_id=? LIMIT 1');
        $stmt->bind_param('ii', $userId, $authId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function hasPlatformAccess(int $userId, string $platform): bool
    {
        if (($this->settings['USER_SUBJECT_RESTRICTIONS_ENABLED'] ?? '0') !== '1') {
            return true;
        }

        $stmt = $this->db->prepare('SELECT id FROM platforms WHERE name=? LIMIT 1');
        $stmt->bind_param('s', $platform);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        $platformId = $row['id'];

        $stmt = $this->db->prepare('SELECT 1 FROM user_platform_subjects WHERE user_id=? AND platform_id=? LIMIT 1');
        $stmt->bind_param('ii', $userId, $platformId);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $ok;
    }

    /**
     * Procesa una consulta de búsqueda de códigos.
     *
     * @param int $telegramId ID de Telegram
     * @param int $chatId ID del chat de Telegram
     * @param string $email Correo a consultar
     * @param string $platform Plataforma
     * @param string $username Username de Telegram
     * @return array Resultado del motor de búsqueda
     */
    public function processSearchRequest(int $telegramId, int $chatId, string $email, string $platform, string $username): array
    {
        $user = $this->auth->authenticateUser($telegramId, $username);
        if (!$user) {
            return ['error' => 'Usuario no autorizado o inactivo'];
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $platform = trim($platform);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Formato de email inválido'];
        }

        if (!$this->isEmailAllowed((int)$user['id'], $email)) {
            return ['error' => 'No tienes permiso para consultar este correo'];
        }

        if (!$this->hasPlatformAccess((int)$user['id'], $platform)) {
            return ['error' => 'No tienes permisos para esta plataforma'];
        }

        // Realizar búsqueda usando el motor unificado
        $result = $this->engine->searchEmails($email, $platform, (int)$user['id']);

        // Marcar el log como originado desde Telegram
        $logId = $this->engine->getLastLogId();
        if ($logId > 0) {
            $this->integration->markLogAsTelegram($logId, $chatId);
        }

        return $result;
    }

    /**
     * Obtiene un código específico por ID
     */
    public function getCodeById(int $telegramId, int $codeId, string $username): array
    {
        $user = $this->auth->authenticateUser($telegramId, $username);
        if (!$user) {
            return ['error' => 'Usuario no autorizado o inactivo'];
        }

        $code = $this->integration->getCodeById($codeId);
        if (!$code) {
            return ['error' => 'Código no encontrado o no tienes permisos para verlo'];
        }

        return [
            'found' => true,
            'content' => $code,
            'message' => 'Código encontrado'
        ];
    }

    /**
     * Obtiene estadísticas para el comando /stats
     */
    public function getUserStats(int $telegramId): array
    {
        if (!$this->integration->isUserAdmin($telegramId)) {
            return ['error' => 'Solo administradores pueden ver estadísticas'];
        }

        return $this->integration->getTelegramStats();
    }

    /**
     * Obtiene información del usuario para el comando /config
     */
    public function getUserConfig(int $telegramId, string $username): array
    {
        $user = $this->auth->authenticateUser($telegramId, $username);
        if (!$user) {
            return ['error' => 'Usuario no autorizado'];
        }

        $config = $this->integration->getUserConfiguration($telegramId);
        if (!$config) {
            return ['error' => 'No se pudo obtener la configuración del usuario'];
        }

        return $config;
    }

    /**
     * Obtiene las plataformas disponibles
     */
    public function getAvailablePlatforms(int $telegramId, string $username): array
    {
        $user = $this->auth->authenticateUser($telegramId, $username);
        if (!$user) {
            return ['error' => 'Usuario no autorizado'];
        }

        $platforms = $this->integration->getAvailablePlatforms();
        
        return [
            'found' => true,
            'platforms' => $platforms,
            'count' => count($platforms)
        ];
    }

    /**
     * Realiza una búsqueda de prueba para verificar conectividad
     */
    public function testConnection(int $telegramId, string $username): array
    {
        $user = $this->auth->authenticateUser($telegramId, $username);
        if (!$user) {
            return ['error' => 'Usuario no autorizado'];
        }

        try {
            // Verificar conexión a base de datos
            $result = $this->db->query("SELECT 1 as test");
            if (!$result) {
                return ['error' => 'Error de conexión a base de datos'];
            }

            // Verificar tablas principales
            $tables = ['users', 'platforms', 'servers', 'settings'];
            foreach ($tables as $table) {
                $check = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$check || $check->num_rows === 0) {
                    return ['error' => "Tabla '$table' no encontrada"];
                }
            }

            // Verificar configuración
            $settings = $this->loadSettings();
            
            return [
                'found' => true,
                'status' => 'ok',
                'message' => 'Conexión exitosa',
                'details' => [
                    'user_id' => $user['id'],
                    'settings_loaded' => count($settings),
                    'database_ok' => true
                ]
            ];

        } catch (\Exception $e) {
            return ['error' => 'Error de sistema: ' . $e->getMessage()];
        }
    }

    /**
     * Busca usuarios por nombre (solo para admins)
     */
    public function searchUsers(int $telegramId, string $searchTerm): array
    {
        if (!$this->integration->isUserAdmin($telegramId)) {
            return ['error' => 'Solo administradores pueden buscar usuarios'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, username, telegram_id, status, role, 
                    last_telegram_activity, created_at
                FROM users 
                WHERE username LIKE ? 
                OR telegram_id = ?
                ORDER BY username
                LIMIT 20
            ");
            
            $searchPattern = "%$searchTerm%";
            $telegramIdSearch = is_numeric($searchTerm) ? (int)$searchTerm : 0;
            
            $stmt->bind_param('si', $searchPattern, $telegramIdSearch);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'telegram_id' => $row['telegram_id'],
                    'status' => (bool)$row['status'],
                    'role' => $row['role'] ?? 'user',
                    'last_activity' => $row['last_telegram_activity'],
                    'created_at' => $row['created_at']
                ];
            }
            $stmt->close();

            return [
                'found' => true,
                'users' => $users,
                'count' => count($users)
            ];

        } catch (\Exception $e) {
            error_log("Error searching users: " . $e->getMessage());
            return ['error' => 'Error buscando usuarios'];
        }
    }

    /**
     * Obtiene logs recientes (solo para admins)
     */
    public function getRecentLogs(int $telegramId, int $limit = 10): array
    {
        if (!$this->integration->isUserAdmin($telegramId)) {
            return ['error' => 'Solo administradores pueden ver logs'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sl.id, sl.email, sl.platform, sl.status, sl.created_at,
                    u.username, sl.telegram_chat_id
                FROM search_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE sl.source = 'telegram'
                ORDER BY sl.created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'platform' => $row['platform'],
                    'status' => $row['status'],
                    'username' => $row['username'] ?? 'Desconocido',
                    'chat_id' => $row['telegram_chat_id'],
                    'created_at' => $row['created_at']
                ];
            }
            $stmt->close();

            return [
                'found' => true,
                'logs' => $logs,
                'count' => count($logs)
            ];

        } catch (\Exception $e) {
            error_log("Error getting recent logs: " . $e->getMessage());
            return ['error' => 'Error obteniendo logs'];
        }
    }
}