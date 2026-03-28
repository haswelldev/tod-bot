<?php

namespace NapevBot;

class Config
{
    private $token;
    private $todFile;
    private $storageDriver;
    private $sqliteFile;
    private int $defaultWindowStart;
    private int $defaultWindowRandom;
    private string $bossConfigPath;

    public function __construct($token = null, $todFile = null)
    {
        $envToken = getenv('DISCORD_TOKEN');
        $this->token = $token !== null ? $token : ($envToken ?: '');
        $defaultFile = dirname(__DIR__) . '/data/tods.json';
        $this->todFile = $todFile !== null ? $todFile : $defaultFile;

        $envStorage = getenv('TOD_STORAGE');
        $this->storageDriver = $envStorage ? strtolower($envStorage) : 'json';

        $defaultSqlite = dirname(__DIR__) . '/data/tods.sqlite';
        $envSqlite = getenv('TOD_SQLITE');
        $this->sqliteFile = $envSqlite ?: $defaultSqlite;

        $envStart = getenv('TOD_WINDOW_START');
        $this->defaultWindowStart = $envStart !== false ? (int) $envStart : 12;

        $envRandom = getenv('TOD_WINDOW_RANDOM');
        $this->defaultWindowRandom = $envRandom !== false ? (int) $envRandom : 9;

        $envBossConfig = getenv('BOSS_CONFIG');
        $this->bossConfigPath = $envBossConfig ?: dirname(__DIR__) . '/config/bosses.yaml';
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getTodFile()
    {
        return $this->todFile;
    }

    public function getStorageDriver(): string
    {
        return $this->storageDriver;
    }

    public function getSqliteFile(): false|array|string
    {
        return $this->sqliteFile;
    }

    public function getDefaultWindowStart(): int
    {
        return $this->defaultWindowStart;
    }

    public function getDefaultWindowRandom(): int
    {
        return $this->defaultWindowRandom;
    }

    public function getBossConfigPath(): string
    {
        return $this->bossConfigPath;
    }
}
