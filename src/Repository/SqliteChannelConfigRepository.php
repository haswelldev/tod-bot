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
        $this->migrateSchema();
    }

    private function migrateSchema(): void
    {
        $columns = [
            "ALTER TABLE channels ADD COLUMN guild_name        TEXT    NOT NULL DEFAULT ''",
            "ALTER TABLE channels ADD COLUMN channel_name      TEXT    NOT NULL DEFAULT ''",
            "ALTER TABLE channels ADD COLUMN reminders_enabled INTEGER NOT NULL DEFAULT 0",
        ];
        foreach ($columns as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), 'duplicate column name')) {
                    throw $e;
                }
            }
        }
    }

    public function get(string $channelId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT guild_id, guild_name, channel_name, locale, reminders_enabled FROM channels WHERE channel_id = :id');
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
            ON CONFLICT(channel_id) DO UPDATE SET
                guild_id          = excluded.guild_id,
                guild_name        = excluded.guild_name,
                channel_name      = excluded.channel_name,
                locale            = excluded.locale,
                reminders_enabled = excluded.reminders_enabled
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
        // SQLite writes are immediate; nothing to flush
    }
}
