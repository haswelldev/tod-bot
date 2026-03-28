<?php

namespace TodBot\Repository;

use PDO;

class MysqlChannelConfigRepository implements ChannelConfigRepositoryInterface
{
    private PDO $pdo;

    public function __construct(string $dsn, string $user, string $password)
    {
        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => true,
        ]);
        $this->initSchema();
    }

    private function initSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS channels (
                channel_id VARCHAR(255) NOT NULL,
                guild_id   VARCHAR(255) NOT NULL DEFAULT \'\',
                locale     VARCHAR(10)  NOT NULL DEFAULT \'en\',
                PRIMARY KEY (channel_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function get(string $channelId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT guild_id, locale FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function set(string $channelId, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO channels (channel_id, guild_id, locale)
            VALUES (:channel_id, :guild_id, :locale)
            ON DUPLICATE KEY UPDATE
                guild_id = VALUES(guild_id),
                locale   = VALUES(locale)
        ');
        $stmt->execute([
            ':channel_id' => $channelId,
            ':guild_id'   => $data['guild_id'] ?? '',
            ':locale'     => $data['locale']   ?? 'en',
        ]);
    }

    public function delete(string $channelId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
    }

    public function save(): void
    {
        // MySQL writes are committed immediately; nothing to flush.
    }
}
