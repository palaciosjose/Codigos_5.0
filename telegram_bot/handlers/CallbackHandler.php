<?php
// telegram_bot/handlers/CallbackHandler.php
namespace TelegramBot\Handlers;

use Longman\TelegramBot\Entities\Update;
use TelegramBot\Utils\TelegramAPI;
use TelegramBot\Services\TelegramAuth;
use TelegramBot\Services\TelegramQuery;

/**
 * Manejador de callbacks (botones inline) del bot
 */
class CallbackHandler
{
    private static ?TelegramAuth $auth = null;
    private static ?TelegramQuery $query = null;

    private static function init(): void
    {
        if (!self::$auth) {
            self::$auth = new TelegramAuth();
            self::$query = new TelegramQuery(self::$auth);
        }
    }

    /**
     * Procesa callbacks de botones inline
     */
    public static function handle(Update $update): void
    {
        self::init();

        $callbackQuery = $update->getCallbackQuery();
        if (!$callbackQuery) {
            return;
        }

        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        $from = $callbackQuery->getFrom();
        $telegramId = $from->getId();
        $telegramUser = $from->getUsername() ?: '';

        // Verificar autenticación para ciertas acciones
        $user = self::$auth->authenticateUser($telegramId, $telegramUser);
        
        // Cargar mensajes y teclados
        $messages = include dirname(__DIR__) . '/templates/messages.php';
        $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';

        // Procesar según el callback
        switch ($data) {
            case 'help':
                self::sendMessage($chatId, $messages['help'], $keyboards['help_menu']);
                break;

            case 'search_menu':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::sendMessage($chatId, $messages['search_instructions'], $keyboards['search_menu']);
                break;

            case 'start_menu':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::sendMessage($chatId, $messages['welcome'], $keyboards['start']);
                break;

            case 'config':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleConfigCallback($chatId, $telegramId, $telegramUser);
                break;

            case 'stats':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleStatsCallback($chatId, $telegramId);
                break;

            case 'search_email':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::sendMessage($chatId, $messages['search_instructions'], $keyboards['back_to_start']);
                break;

            case 'search_id':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::sendMessage($chatId, $messages['code_instructions'], $keyboards['back_to_start']);
                break;

            case 'list_platforms':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleListPlatforms($chatId);
                break;

            case 'help_commands':
                self::sendMessage($chatId, $messages['help'], $keyboards['help_menu']);
                break;

            case 'help_search':
                self::sendMessage($chatId, $messages['search_instructions'], $keyboards['help_menu']);
                break;

            case 'help_config':
                self::sendMessage($chatId, $messages['config_info'], $keyboards['help_menu']);
                break;

            case 'admin_stats':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleStatsCallback($chatId, $telegramId);
                break;

            case 'admin_users':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleAdminUsers($chatId, $telegramId);
                break;

            case 'admin_config':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleAdminConfig($chatId, $telegramId);
                break;

            case 'admin_logs':
                if (!$user) {
                    self::sendMessage($chatId, $messages['unauthorized']);
                    break;
                }
                self::handleAdminLogs($chatId, $telegramId);
                break;

            default:
                self::sendMessage($chatId, $messages['invalid_format'], $keyboards['start']);
                break;
        }

        // Confirmar el callback
        TelegramAPI::answerCallbackQuery($callbackQuery->getId());
    }

    /**
     * Envía un mensaje con teclado opcional
     */
    private static function sendMessage(int $chatId, string $text, ?array $keyboard = null): void
    {
        $extra = [];
        if ($keyboard) {
            $extra['reply_markup'] = json_encode($keyboard);
        }
        TelegramAPI::sendMessage($chatId, $text, $extra);
    }

    /**
     * Maneja el callback de configuración
     */
    private static function handleConfigCallback(int $chatId, int $telegramId, string $telegramUser): void
    {
        try {
            $config = self::$query->getUserConfig($telegramId, $telegramUser);
            if (isset($config['error'])) {
                $messages = include dirname(__DIR__) . '/templates/messages.php';
                self::sendMessage($chatId, $messages['error_prefix'] . $config['error']);
                return;
            }

            $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
            
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

            self::sendMessage($chatId, $message, $keyboards['back_to_start']);
        } catch (\Exception $e) {
            error_log("Error en configuración callback: " . $e->getMessage());
            $messages = include dirname(__DIR__) . '/templates/messages.php';
            self::sendMessage($chatId, $messages['error_generic']);
        }
    }

    /**
     * Maneja el callback de estadísticas
     */
    private static function handleStatsCallback(int $chatId, int $telegramId): void
    {
        try {
            $stats = self::$query->getUserStats($telegramId);
            if (isset($stats['error'])) {
                $messages = include dirname(__DIR__) . '/templates/messages.php';
                self::sendMessage($chatId, $messages['error_prefix'] . $stats['error']);
                return;
            }

            $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';

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

            self::sendMessage($chatId, $message, $keyboards['back_to_start']);
        } catch (\Exception $e) {
            error_log("Error en estadísticas callback: " . $e->getMessage());
            $messages = include dirname(__DIR__) . '/templates/messages.php';
            self::sendMessage($chatId, $messages['error_generic']);
        }
    }

    /**
     * Lista las plataformas disponibles
     */
    private static function handleListPlatforms(int $chatId): void
    {
        try {
            // Aquí puedes obtener las plataformas desde la base de datos
            // Por ahora usamos una lista básica
            $platforms = [
                'Netflix', 'Amazon', 'PayPal', 'Steam', 'Epic Games',
                'Spotify', 'Apple', 'Google', 'Microsoft', 'Adobe'
            ];

            $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
            
            $message = "🏷️ *Plataformas Disponibles:*\n\n";
            foreach ($platforms as $platform) {
                $message .= "• `{$platform}`\n";
            }
            $message .= "\n💡 *Tip:* Usa exactamente estos nombres en tus búsquedas\\.";

            self::sendMessage($chatId, $message, $keyboards['search_menu']);
        } catch (\Exception $e) {
            error_log("Error listando plataformas: " . $e->getMessage());
            $messages = include dirname(__DIR__) . '/templates/messages.php';
            self::sendMessage($chatId, $messages['error_generic']);
        }
    }

    /**
     * Maneja usuarios admin (solo para administradores)
     */
    private static function handleAdminUsers(int $chatId, int $telegramId): void
    {
        // Implementar funcionalidad de administración de usuarios
        $messages = include dirname(__DIR__) . '/templates/messages.php';
        $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
        self::sendMessage($chatId, "👥 *Gestión de Usuarios*\n\nFuncionalidad en desarrollo\\.", $keyboards['back_to_start']);
    }

    /**
     * Configuración de administrador
     */
    private static function handleAdminConfig(int $chatId, int $telegramId): void
    {
        // Implementar configuración de admin
        $messages = include dirname(__DIR__) . '/templates/messages.php';
        $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
        self::sendMessage($chatId, "🔧 *Configuración del Sistema*\n\nFuncionalidad en desarrollo\\.", $keyboards['back_to_start']);
    }

    /**
     * Logs del sistema para admin
     */
    private static function handleAdminLogs(int $chatId, int $telegramId): void
    {
        // Implementar visualización de logs
        $messages = include dirname(__DIR__) . '/templates/messages.php';
        $keyboards = include dirname(__DIR__) . '/templates/keyboards.php';
        self::sendMessage($chatId, "📝 *Logs del Sistema*\n\nFuncionalidad en desarrollo\\.", $keyboards['back_to_start']);
    }
}