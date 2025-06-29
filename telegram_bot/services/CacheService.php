<?php
namespace TelegramBot\Services;

class CacheService
{
    private string $cacheDir = __DIR__ . '/../cache/';
    private int $ttl = 3600; // 1 hora

    public function get(string $key): ?array
    {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (!file_exists($file) || (time() - filemtime($file)) > $this->ttl) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    public function set(string $key, array $data): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        file_put_contents(
            $this->cacheDir . md5($key) . '.cache',
            json_encode($data)
        );
    }
}
