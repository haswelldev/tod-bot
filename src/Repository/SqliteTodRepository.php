<?php

namespace TodBot\Repository;

use PDO;

class SqliteTodRepository implements TodRepositoryInterface
{
    /** @var PDO */
    private $pdo;

    public function __construct($file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $file);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initSchema();
    }

    private function initSchema(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS tods (
            boss TEXT NOT NULL,
            channel TEXT NOT NULL,
            tod INTEGER NOT NULL,
            start_reminded INTEGER NOT NULL DEFAULT 0,
            end_reminded INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (boss, channel)
        )';
        $this->pdo->exec($sql);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT boss, tod, channel, start_reminded, end_reminded FROM tods');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $chan = $row['channel'];
            if (!isset($out[$chan])) $out[$chan] = [];
            $out[$chan][$row['boss']] = [
                'tod' => (int) $row['tod'],
                'channel' => $row['channel'],
                'start_reminded' => (bool) $row['start_reminded'],
                'end_reminded' => (bool) $row['end_reminded'],
            ];
        }
        return $out;
    }

    public function allByChannel($channel): array
    {
        $stmt = $this->pdo->prepare('SELECT boss, tod, channel, start_reminded, end_reminded FROM tods WHERE channel = :channel');
        $stmt->execute([':channel' => $channel]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['boss']] = [
                'tod' => (int) $row['tod'],
                'channel' => $row['channel'],
                'start_reminded' => (bool) $row['start_reminded'],
                'end_reminded' => (bool) $row['end_reminded'],
            ];
        }
        return $out;
    }

    public function get($boss, $channel): ?array
    {
        $stmt = $this->pdo->prepare('SELECT boss, tod, channel, start_reminded, end_reminded FROM tods WHERE boss = :boss AND channel = :channel');
        $stmt->execute([':boss' => $boss, ':channel' => $channel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'tod' => (int) $row['tod'],
            'channel' => $row['channel'],
            'start_reminded' => (bool) $row['start_reminded'],
            'end_reminded' => (bool) $row['end_reminded'],
        ];
    }

    public function set($boss, $channel, $data)
    {
        // Upsert behavior: try update then insert if 0 rows affected
        $upd = $this->pdo->prepare('UPDATE tods SET tod = :tod, start_reminded = :sr, end_reminded = :er WHERE boss = :boss AND channel = :channel');
        $upd->execute([
            ':tod' => (int) ($data['tod'] ?? 0),
            ':channel' => (string) ($channel ?? ''),
            ':sr' => !empty($data['start_reminded']) ? 1 : 0,
            ':er' => !empty($data['end_reminded']) ? 1 : 0,
            ':boss' => $boss,
        ]);
        if ($upd->rowCount() === 0) {
            $ins = $this->pdo->prepare('INSERT INTO tods (boss, channel, tod, start_reminded, end_reminded) VALUES (:boss, :channel, :tod, :sr, :er)');
            $ins->execute([
                ':boss' => $boss,
                ':tod' => (int) ($data['tod'] ?? 0),
                ':channel' => (string) ($channel ?? ''),
                ':sr' => !empty($data['start_reminded']) ? 1 : 0,
                ':er' => !empty($data['end_reminded']) ? 1 : 0,
            ]);
        }
    }

    public function delete($boss, $channel): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tods WHERE boss = :boss AND channel = :channel');
        $stmt->execute([':boss' => $boss, ':channel' => $channel]);
    }

    public function save()
    {
        // No-op for SQLite; changes are persisted immediately.
    }
}
