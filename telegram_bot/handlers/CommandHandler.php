<?php
// telegram_bot/handlers/CommandHandler.php
namespace TelegramBot\Handlers;

use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Telegram;
use TelegramBot\Services\TelegramAuth;
use TelegramBot\Services\TelegramQuery;
use TelegramBot\Services\ResponseFormatter;
use TelegramBot\Utils\TelegramAPI;
use TelegramBot\Config\TelegramBotConfig;

/**
 * Gestiona los comandos recibidos por el bot.
 */
class CommandHandler
{
    private static ?TelegramAuth $auth = null;
    private static ?TelegramQuery $query = null;
    private static array $requestLog = [];

    private static function init(): void
    {
        if (!self::$auth) {
            self::$auth = new TelegramAuth();
            self::$query = new TelegramQuery(self::$auth);
        }
    }

    /**
     * Maneja una actualización de Telegram.
     */
    public static function handle(Update $update, Telegram $telegram): void
    {
        self::init();

        $message = $update->getMessage();
        if (!$message) {
            return;
        }

        $chatId = $message->getChat()->getId();
        $from = $message->getFrom();
        $telegramId = $from->getId();
        $telegramUser = $from->getUsername() ?: '';

        $text = trim($message->getText(true) ?? '');
        $command = strtolower($message->getCommand() ?? '');

        if (!self::checkRateLimit($telegramId)) {
            TelegramAPI::sendMessage($chatId, 'Demasiadas solicitudes, intenta de nuevo más tarde.');
            return;
        }

        // Registrar actividad
        if (self::$query) {
            self::$query->logActivity($telegramId, "command_$command", [
                'chat_id' => $chatId,
                'text' => $text
            ]);
        }

        switch ($command) {
            case 'start':
                $user = self::$auth->authenticateUser($telegramId, $telegramUser);
                $msg = $user ? self::getMessage('welcome') : self::getMessage('unauthorized');
                TelegramAPI::sendMessage($chatId, $msg, ['reply_markup' => json_encode(self::getKeyboard('start'))]);
                break;
                
            case 'ayuda':
            case 'help':
                TelegramAPI::sendMessage($chatId, self::getMessage('help'));
                break;
                
            case 'buscar':
                self::handleSearchCommand($chatId, $telegramId, $telegramUser, $text);
                break;
                
            case 'codigo':
                self::handleCodeCommand($chatId, $telegramId, $telegramUser, $text);
                break;
                
            case 'stats':
                self::handleStatsCommand($chatId, $telegramId);
                break;
                
            case 'config':
                self::handleConfigCommand($chatId, $telegramId, $telegramUser);
                break;
                
            default:
                TelegramAPI::sendMessage($chatId, 'Comando no reconocido. Usa /ayuda para ver comandos disponibles.');
        }
    }

    /**
     * Verifica el rate limiting por usuario
     */
    private static function checkRateLimit(int $telegramId): bool
    {
        $now = time();
        $windowSize = TelegramBotConfig::RATE_LIMIT_WINDOW;
        $maxRequests = TelegramBotConfig::MAX_REQUESTS_PER_MINUTE;

        // Limpiar requests antiguos
        if (!isset(self::$requestLog[$telegramId])) {
            self::$requestLog[$telegramId] = [];
        }

        $userLog = &self::$requestLog[$telegramId];
        $userLog = array_filter($userLog, function($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });

        // Verificar límite
        if (count($userLog) >= $maxRequests) {
            return false;
        }

        // Agregar request actual
        $userLog[] = $now;
        
        return true;
    }

    /**
     * Obtiene un mensaje de las plantillas
     */
    private static function getMessage(string $key): string
    {
        static $messages = null;
        
        if ($messages === null) {
            $messages = include dirname(__DIR__) . '/templates/messages.php';
        }

        return $messages[$key] ?? "Mensaje no encontrado: $key";
    }

    /**
     * Obtiene un teclado de las plantillas
     */
    private static function getKeyboard(string $key): array
    {
        static $keyboards = null;
        
        if ($keyboards === null) {
            $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
        }

        return $keyboards[$key] ?? [];
    }

    /**
     * Verifica si un usuario es administrador
     */
    private static function isAdmin(int $telegramId): bool
    {
        if (!self::$query) {
            return false;
        }

        try {
            $user = self::$auth->findUserByTelegramId($telegramId);
            return $user && ($user['role'] === 'admin' || $user['role'] === 'superadmin');
        } catch (\Exception $e) {
            error_log("Error verificando admin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Maneja el comando /buscar
     */
    private static function handleSearchCommand(int $chatId, int $telegramId, string $telegramUser, string $text): void
    {
        $parts = explode(' ', $text);
        array_shift($parts); // Remover el comando
        
        $email = $parts[0] ?? '';
        $platform = $parts[1] ?? '';
        
        if (!$email || !$platform) {
            TelegramAPI::sendMessage($chatId, 'Uso: /buscar <email> <plataforma>');
            return;
        }

        TelegramAPI::sendChatAction($chatId, 'typing');
        
        try {
            $result = self::$query->processSearchRequest($telegramId, $chatId, $email, $platform, $telegramUser);
            $messages = ResponseFormatter::formatSearchResults($result);
            
            foreach ($messages as $msg) {
                TelegramAPI::sendMessage($chatId, $msg);
                // Pequeña pausa entre mensajes para evitar flood
                usleep(100000); // 0.1 segundos
            }
        } catch (\Exception $e) {
            error_log("Error en búsqueda Telegram: " . $e->getMessage());
            TelegramAPI::sendMessage($chatId, 'Error interno del servidor. Intenta nuevamente.');
        }
    }

    /**
     * Maneja el comando /codigo
     */
    private static function handleCodeCommand(int $chatId, int $telegramId, string $telegramUser, string $text): void
    {
        $parts = explode(' ', $text);
        $codeId = $parts[1] ?? '';
        
        if (!$codeId || !is_numeric($codeId)) {
            TelegramAPI::sendMessage($chatId, 'Uso: /codigo <id_numerico>');
            return;
        }

        try {
            $result = self::$query->getCodeById($telegramId, (int)$codeId, $telegramUser);
            $messages = ResponseFormatter::formatCodeResult($result);
            
            foreach ($messages as $msg) {
                TelegramAPI::sendMessage($chatId, $msg);
                usleep(100000); // 0.1 segundos
            }
        } catch (\Exception $e) {
            error_log("Error obteniendo código: " . $e->getMessage());
            TelegramAPI::sendMessage($chatId, 'Error obteniendo el código.');
        }
    }

    /**
     * Maneja el comando /stats
     */
    private static function handleStatsCommand(int $chatId, int $telegramId): void
    {
        if (!self::isAdmin($telegramId)) {
            TelegramAPI::sendMessage($chatId, 'Solo administradores pueden ver estadísticas');
            return;
        }

        try {
            $stats = self::$query->getUserStats($telegramId);
            if (isset($stats['error'])) {
                TelegramAPI::sendMessage($chatId, $stats['error']);
                return;
            }

            $message = "📊 *Estadísticas del Bot*\n\n";
            $message .= "👥 Usuarios activos: *{$stats['active_users']}*\n";
            $message .= "🔍 Búsquedas hoy: *{$stats['searches_today']}*\n";
            $message .= "📈 Total búsquedas: *{$stats['total_searches']}*\n\n";
            
            if (!empty($stats['top_users'])) {
                $message .= "🏆 *Top usuarios \\(7 días\\):*\n";
                foreach ($stats['top_users'] as $i => $user) {
                    $pos = $i + 1;
                    $message .= "{$pos}\\. `{$user['username']}`: *{$user['searches']}* búsquedas\n";
                }
            }

            TelegramAPI::sendMessage($chatId, $message);
        } catch (\Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            TelegramAPI::sendMessage($chatId, 'Error obteniendo estadísticas.');
        }
    }

    /**
     * Maneja el comando /config
     */
    private static function handleConfigCommand(int $chatId, int $telegramId, string $telegramUser): void
    {
        try {
            $config = self::$query->getUserConfig($telegramId, $telegramUser);
            if (isset($config['error'])) {
                TelegramAPI::sendMessage($chatId, $config['error']);
                return;
            }

            $message = "⚙️ *Tu Configuración*\n\n";
            $message .= "👤 Usuario: `{$config['username']}`\n";
            $message .= "🆔 Telegram ID: `{$config['telegram_id']}`\n";
            $message .= "🎭 Rol: *{$config['role']}*\n";
            $message .= "✅ Estado: " . ($config['status'] ? 'Activo' : 'Inactivo') . "\n\n";
            
            $emailCount = count($config['permissions']['emails'] ?? []);
            $subjectCount = count($config['permissions']['subjects'] ?? []);
            
            $message .= "📧 Emails autorizados: *{$emailCount}*\n";
            $message .= "🏷️ Plataformas disponibles: *{$subjectCount}*\n";
            
            if (isset($config['last_activity']) && $config['last_activity']) {
                $message .= "🕒 Última actividad: `{$config['last_activity']}`";
            }

            TelegramAPI::sendMessage($chatId, $message);
        } catch (\Exception $e) {
            error_log("Error obteniendo configuración: " . $e->getMessage());
            TelegramAPI::sendMessage($chatId, 'Error obteniendo configuración.');
        }
    }
}