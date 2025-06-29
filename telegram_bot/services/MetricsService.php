<?php
namespace TelegramBot\Services;

class MetricsService
{
    public function trackCommand(string $command, int $userId): void
    {
        // Registrar métricas de uso
    }

    public function getDailyStats(): array
    {
        // Obtener estadísticas diarias
        return [];
    }

    public function exportMetrics(): array
    {
        // Exportar métricas para análisis
        return [];
    }
}
