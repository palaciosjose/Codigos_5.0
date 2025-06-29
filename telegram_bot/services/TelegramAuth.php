<?php
namespace TelegramBot\Services;

use Shared\DatabaseManager;

/**
 * Manejo de autenticación de usuarios mediante Telegram.
 */
class TelegramAuth
{
    private \mysqli $db;

    public function __construct()
    {
        $this->db = DatabaseManager::getInstance()->getConnection();
    }

    /**
     * Autentica al usuario por su telegram_id y actualiza actividad.
     *
     * @param int $telegramId ID de Telegram
     * @param string $username Nombre de usuario de Telegram
     * @return array|null Datos del usuario o null si no existe/activo
     */
    public function authenticateUser(int $telegramId, string $username): ?array
    {
        $user = $this->findUserByTelegramId($telegramId);
        if (!$user || (int)$user['status'] !== 1) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET telegram_username=?, last_telegram_activity=NOW() WHERE id=?'
        );
        $stmt->bind_param('si', $username, $user['id']);
        $stmt->execute();
        $stmt->close();

        return $user;
    }

    /**
     * Busca un usuario por su telegram_id.
     *
     * @param int $telegramId ID de Telegram
     * @return array|null
     */
    public function findUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE telegram_id=? LIMIT 1');
        $stmt->bind_param('i', $telegramId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Obtiene los permisos del usuario (emails y asuntos).
     *
     * @param int $userId ID del usuario
     * @return array
     */
    public function getUserPermissions(int $userId): array
    {
        $permissions = ['emails' => [], 'subjects' => []];

        $query = 'SELECT ae.email FROM user_authorized_emails uae
                  JOIN authorized_emails ae ON uae.authorized_email_id = ae.id
                  WHERE uae.user_id = ?';
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $permissions['emails'][] = $row['email'];
        }
        $stmt->close();

        $query = 'SELECT p.name, ups.subject_keyword FROM user_platform_subjects ups
                  JOIN platforms p ON ups.platform_id = p.id WHERE ups.user_id = ?';
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $permissions['subjects'][$row['name']][] = $row['subject_keyword'];
        }
        $stmt->close();

        return $permissions;
    }
}
