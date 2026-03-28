<?php

namespace NapevBot\Bot;

use Discord\Discord;
use Discord\WebSockets\Intents;
use NapevBot\Config;
use NapevBot\Repository\TodRepositoryInterface;
use NapevBot\Service\BossRegistry;
use NapevBot\Service\CommandHandler;
use NapevBot\Service\ReminderScheduler;

class DiscordBot
{
    private Discord $discord;
    private TodRepositoryInterface $repo;
    private BossRegistry $bossRegistry;

    public function __construct(Config $config, TodRepositoryInterface $repo)
    {
        date_default_timezone_set('UTC');

        $this->repo = $repo;
        $this->bossRegistry = new BossRegistry(
            $config->getDefaultWindowStart(),
            $config->getDefaultWindowRandom(),
            $config->getBossConfigPath()
        );

        $this->discord = new Discord(array(
            'token' => $config->getToken(),
            'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
        ));

        $this->wireEvents();
    }

    private function wireEvents(): void
    {
        $discord = $this->discord;
        $repo = $this->repo;
        $bossRegistry = $this->bossRegistry;

        $discord->on('init', function (Discord $discord) use ($repo, $bossRegistry) {
            echo "Bot is ready." . PHP_EOL;

            // Commands
            $handler = new CommandHandler($discord, $repo, $bossRegistry);
            $discord->on('message', $handler);

            // Reminders
            (new ReminderScheduler($discord, $repo, $bossRegistry))->start();
        });
    }

    public function run()
    {
        $this->discord->run();
    }
}
