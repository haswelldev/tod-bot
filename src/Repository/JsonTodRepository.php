<?php

namespace TodBot\Repository;

class JsonTodRepository implements TodRepositoryInterface
{
    private       $file;
    private array $data = [];

    public function __construct($file)
    {
        $this->file = $file;
        $this->load();
    }

    private function load(): void
    {
        if (!file_exists($this->file)) {
            $this->data = [];
            return;
        }
        $json = @file_get_contents($this->file);
        $arr = $json ? json_decode($json, true) : null;
        $loaded = is_array($arr) ? $arr : [];
        // Normalize to grouped-by-channel structure
        // New format: [channelId => [boss => data]]
        // Old format: [boss => data] with 'channel' inside data
        $isOld = false;
        foreach ($loaded as $k => $v) {
            if (is_array($v) && isset($v['tod'])) { $isOld = true; break; }
        }
        if ($isOld) {
            $grouped = [];
            foreach ($loaded as $boss => $info) {
                $chan = isset($info['channel']) ? (string) $info['channel'] : '';
                if (!isset($grouped[$chan])) $grouped[$chan] = [];
                $grouped[$chan][$boss] = $info;
            }
            $this->data = $grouped;
        } else {
            $this->data = $loaded;
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function allByChannel($channel): array
    {
        return $this->data[$channel] ?? [];
    }

    public function get($boss, $channel)
    {
        return $this->data[$channel][$boss] ?? null;
    }

    public function set($boss, $channel, $data): void
    {
        if (!isset($this->data[$channel])) $this->data[$channel] = [];
        $this->data[$channel][$boss] = $data;
    }

    public function delete($boss, $channel): void
    {
        if (isset($this->data[$channel][$boss])) {
            unset($this->data[$channel][$boss]);
        }
    }

    public function save(): void
    {
        @file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT));
    }
}
