<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TelegramBot\Config\TelegramBotConfig;

class TelegramBotSetup {
    public static function configureWebhook() {
        TelegramBotConfig::load();

        $url = "https://api.telegram.org/bot" . TelegramBotConfig::$BOT_TOKEN . "/setWebhook";
        $data = [
            'url' => TelegramBotConfig::$WEBHOOK_URL,
            'secret_token' => TelegramBotConfig::$WEBHOOK_SECRET,
            'allowed_updates' => json_encode(['message', 'callback_query'])
        ];

        return self::makeRequest($url, $data);
    }

    private static function makeRequest($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}
