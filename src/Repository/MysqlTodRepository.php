<?php

namespace TodBot\Repository;

use PDO;

class MysqlTodRepository implements TodRepositoryInterface
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
            CREATE TABLE IF NOT EXISTS tods (
                boss           VARCHAR(255) NOT NULL,
                channel        VARCHAR(255) NOT NULL,
                tod            BIGINT       NOT NULL,
                start_reminded TINYINT(1)   NOT NULL DEFAULT 0,
                end_reminded   TINYINT(1)   NOT NULL DEFAULT 0,
                PRIMARY KEY (boss, channel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
        $this->migrateSchema();
    }

    private function migrateSchema(): void
    {
        $this->pdo->exec("
            ALTER TABLE tods
                ADD COLUMN IF NOT EXISTS remind TINYINT(1) NOT NULL DEFAULT 0
        ");
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT boss, tod, channel, start_reminded, end_reminded, remind FROM tods');
        $out  = [];
        foreach ($stmt->fetchAll() as $row) {
            $chan = $row['channel'];
            if (!isset($out[$chan])) {
                $out[$chan] = [];
            }
            $out[$chan][$row['boss']] = $this->hydrate($row);
        }
        return $out;
    }

    public function allByChannel($channel): array
    {
        $stmt = $this->pdo->prepare('SELECT boss, tod, channel, start_reminded, end_reminded, remind FROM tods WHERE channel = :channel');
        $stmt->execute([':channel' => $channel]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['boss']] = $this->hydrate($row);
        }
        return $out;
    }

    public function get($boss, $channel): ?array
    {
        $stmt = $this->pdo->prepare('SELECT boss, tod, channel, start_reminded, end_reminded, remind FROM tods WHERE boss = :boss AND channel = :channel');
        $stmt->execute([':boss' => $boss, ':channel' => $channel]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function set($boss, $channel, $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tods (boss, channel, tod, start_reminded, end_reminded, remind)
            VALUES (:boss, :channel, :tod, :sr, :er, :remind)
            ON DUPLICATE KEY UPDATE
                tod            = VALUES(tod),
                start_reminded = VALUES(start_reminded),
                end_reminded   = VALUES(end_reminded),
                remind         = VALUES(remind)
        ');
        $stmt->execute([
            ':boss'    => (string) $boss,
            ':channel' => (string) $channel,
            ':tod'     => (int) ($data['tod'] ?? 0),
            ':sr'      => !empty($data['start_reminded']) ? 1 : 0,
            ':er'      => !empty($data['end_reminded'])   ? 1 : 0,
            ':remind'  => !empty($data['remind'])          ? 1 : 0,
        ]);
    }

    public function delete($boss, $channel): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tods WHERE boss = :boss AND channel = :channel');
        $stmt->execute([':boss' => $boss, ':channel' => $channel]);
    }

    public function save(): void
    {
        // MySQL writes are committed immediately; nothing to flush.
    }

    private function hydrate(array $row): array
    {
        return [
            'tod'            => (int)  $row['tod'],
            'channel'        => (string) $row['channel'],
            'start_reminded' => (bool) $row['start_reminded'],
            'end_reminded'   => (bool) $row['end_reminded'],
            'remind'         => (bool) $row['remind'],
        ];
    }
}
