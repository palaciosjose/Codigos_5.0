<?php
namespace TelegramBot\Utils;

use Longman\TelegramBot\Request;

/**
 * Envoltorio sencillo para llamadas a la API de Telegram.
 */
class TelegramAPI
{
    /**
     * Envía un mensaje al chat indicado.
     */
    public static function sendMessage(int $chatId, string $text, array $extra = []): void
    {
        $data = array_merge(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'MarkdownV2'], $extra);
        Request::sendMessage($data);
    }

    /**
     * Indica una acción en el chat.
     */
    public static function sendChatAction(int $chatId, string $action): void
    {
        Request::sendChatAction(['chat_id' => $chatId, 'action' => $action]);
    }

    public static function answerCallbackQuery(string $callbackId): void
    {
        Request::answerCallbackQuery(['callback_query_id' => $callbackId]);
    }
}
