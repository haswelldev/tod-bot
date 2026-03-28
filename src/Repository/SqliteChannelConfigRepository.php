<?php

namespace TodBot\Repository;

use PDO;

class SqliteChannelConfigRepository implements ChannelConfigRepositoryInterface
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS channels (
                channel_id TEXT NOT NULL PRIMARY KEY,
                guild_id   TEXT NOT NULL DEFAULT \'\',
                locale     TEXT NOT NULL DEFAULT \'en\'
            )
        ');
    }

    public function get(string $channelId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT guild_id, locale FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function set(string $channelId, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO channels (channel_id, guild_id, locale)
            VALUES (:channel_id, :guild_id, :locale)
            ON CONFLICT(channel_id) DO UPDATE SET
                guild_id = excluded.guild_id,
                locale   = excluded.locale
        ');
        $stmt->execute([
            ':channel_id' => $channelId,
            ':guild_id'   => $data['guild_id'] ?? '',
            ':locale'     => $data['locale'] ?? 'en',
        ]);
    }

    public function delete(string $channelId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
    }

    public function save(): void
    {
        // SQLite writes are immediate; nothing to flush
    }
}
