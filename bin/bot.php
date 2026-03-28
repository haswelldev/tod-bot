#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use TodBot\Bot\DiscordBot;
use TodBot\Config;
use TodBot\Repository\JsonChannelConfigRepository;
use TodBot\Repository\JsonTodRepository;
use TodBot\Repository\MysqlChannelConfigRepository;
use TodBot\Repository\MysqlTodRepository;

$config = new Config();

if (!$config->getToken()) {
    fwrite(STDERR, "DISCORD_TOKEN is not set. Please export DISCORD_TOKEN env variable.\n");
    exit(1);
}

// Select storage backend based on env TOD_STORAGE (json|mysql)
$driver = $config->getStorageDriver();
if ($driver === 'mysql') {
    $dsn               = $config->getMysqlDsn();
    $user              = $config->getMysqlUser();
    $password          = $config->getMysqlPassword();
    $repo              = new MysqlTodRepository($dsn, $user, $password);
    $channelConfigRepo = new MysqlChannelConfigRepository($dsn, $user, $password);
} else {
    $repo              = new JsonTodRepository($config->getTodFile());
    $channelConfigRepo = new JsonChannelConfigRepository($config->getChannelsFile());
}

$bot = new DiscordBot($config, $repo, $channelConfigRepo);
$bot->run();
