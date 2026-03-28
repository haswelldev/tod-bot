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
        $this->migrateSchema();
    }

    private function migrateSchema(): void
    {
        $this->pdo->exec("
            ALTER TABLE channels
                ADD COLUMN IF NOT EXISTS guild_name        VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS channel_name      VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN IF NOT EXISTS reminders_enabled TINYINT(1)   NOT NULL DEFAULT 0
        ");
    }

    public function get(string $channelId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT guild_id, guild_name, channel_name, locale, reminders_enabled FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'guild_id'          => $row['guild_id'],
            'guild_name'        => $row['guild_name'],
            'channel_name'      => $row['channel_name'],
            'locale'            => $row['locale'],
            'reminders_enabled' => (bool) $row['reminders_enabled'],
        ];
    }

    public function set(string $channelId, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO channels (channel_id, guild_id, guild_name, channel_name, locale, reminders_enabled)
            VALUES (:channel_id, :guild_id, :guild_name, :channel_name, :locale, :reminders_enabled)
            ON DUPLICATE KEY UPDATE
                guild_id          = VALUES(guild_id),
                guild_name        = VALUES(guild_name),
                channel_name      = VALUES(channel_name),
                locale            = VALUES(locale),
                reminders_enabled = VALUES(reminders_enabled)
        ');
        $stmt->execute([
            ':channel_id'        => $channelId,
            ':guild_id'          => $data['guild_id']          ?? '',
            ':guild_name'        => $data['guild_name']         ?? '',
            ':channel_name'      => $data['channel_name']       ?? '',
            ':locale'            => $data['locale']             ?? 'en',
            ':reminders_enabled' => !empty($data['reminders_enabled']) ? 1 : 0,
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
