<?php
namespace TelegramBot\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class LogService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('telegram_bot');
        $this->logger->pushHandler(
            new StreamHandler(__DIR__ . '/../logs/bot.log', Logger::DEBUG)
        );
    }

    public function logCommand(int $telegramId, string $command, array $context = []): void
    {
        $this->logger->info("Command executed", [
            'telegram_id' => $telegramId,
            'command' => $command,
            'context' => $context
        ]);
    }

    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
