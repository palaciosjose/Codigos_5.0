<?php
namespace TelegramBot\Services;

use TelegramBot\Utils\TelegramAPI;

class NotificationService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function notifyAdmins(string $message): void
    {
        $stmt = $this->db->prepare('SELECT telegram_id FROM users WHERE role = "admin" AND telegram_id IS NOT NULL');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            TelegramAPI::sendMessage($row['telegram_id'], "\xF0\x9F\x94\x94 *Notificaci\xC3\xB3n Admin*\n\n" . $message);
        }
        $stmt->close();
    }

    public function scheduleNotification(int $userId, string $message, \DateTime $when): void
    {
        // Programar notificaciones
    }

    public function sendBulkNotification(array $userIds, string $message): void
    {
        // Envío masivo de notificaciones
    }
}
