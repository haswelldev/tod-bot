<?php

namespace TodBot;

class Config
{
    private $token;
    private $todFile;
    private $storageDriver;
    private int $defaultWindowStart;
    private int $defaultWindowRandom;
    private string $bossConfigPath;
    private string $channelsFile;
    private string $mysqlDsn;
    private string $mysqlUser;
    private string $mysqlPassword;

    public function __construct($token = null, $todFile = null)
    {
        $envToken = getenv('DISCORD_TOKEN');
        $this->token = $token !== null ? $token : ($envToken ?: '');
        $defaultFile = dirname(__DIR__) . '/data/tods.json';
        $this->todFile = $todFile !== null ? $todFile : $defaultFile;

        $envStorage = getenv('TOD_STORAGE');
        $this->storageDriver = $envStorage ? strtolower($envStorage) : 'json';

        $envStart = getenv('TOD_WINDOW_START');
        $this->defaultWindowStart = $envStart !== false ? (int) $envStart : 12;

        $envRandom = getenv('TOD_WINDOW_RANDOM');
        $this->defaultWindowRandom = $envRandom !== false ? (int) $envRandom : 9;

        $envBossConfig = getenv('BOSS_CONFIG');
        $this->bossConfigPath = $envBossConfig ?: dirname(__DIR__) . '/config/bosses.yaml';

        $this->channelsFile = dirname(__DIR__) . '/data/channels.json';

        $host     = getenv('MYSQL_HOST')     ?: '127.0.0.1';
        $port     = (int) (getenv('MYSQL_PORT') ?: 3306);
        $dbname   = getenv('MYSQL_DATABASE') ?: 'todbot';
        $this->mysqlDsn      = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $this->mysqlUser     = getenv('MYSQL_USER')     ?: 'todbot';
        $this->mysqlPassword = getenv('MYSQL_PASSWORD') ?: '';
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

    public function getChannelsFile(): string
    {
        return $this->channelsFile;
    }

    public function getMysqlDsn(): string
    {
        return $this->mysqlDsn;
    }

    public function getMysqlUser(): string
    {
        return $this->mysqlUser;
    }

    public function getMysqlPassword(): string
    {
        return $this->mysqlPassword;
    }
}
