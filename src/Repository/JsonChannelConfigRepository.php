<?php

namespace TodBot\Repository;

class JsonChannelConfigRepository implements ChannelConfigRepositoryInterface
{
    private string $filePath;
    private array $data = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        if (file_exists($filePath)) {
            $raw = file_get_contents($filePath);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
    }

    public function get(string $channelId): ?array
    {
        return $this->data[$channelId] ?? null;
    }

    public function set(string $channelId, array $data): void
    {
        $this->data[$channelId] = $data;
    }

    public function delete(string $channelId): void
    {
        unset($this->data[$channelId]);
    }

    public function save(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
