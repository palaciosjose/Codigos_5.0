<?php
namespace TelegramBot\Utils;

class InputValidator
{
    public static function validateEmail(string $email): array
    {
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email no puede estar vacío'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Formato de email inválido'];
        }
        if (strlen($email) > 100) {
            return ['valid' => false, 'message' => 'Email demasiado largo'];
        }
        return ['valid' => true];
    }

    public static function validatePlatform(string $platform): array
    {
        if (empty($platform)) {
            return ['valid' => false, 'message' => 'Plataforma no puede estar vacía'];
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $platform)) {
            return ['valid' => false, 'message' => 'Plataforma contiene caracteres inválidos'];
        }
        return ['valid' => true];
    }
}
