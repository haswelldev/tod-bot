<?php

namespace TodBot\Bot;

use Discord\Discord;
use Discord\WebSockets\Intents;
use TodBot\Config;
use TodBot\Repository\ChannelConfigRepositoryInterface;
use TodBot\Repository\TodRepositoryInterface;
use TodBot\Service\BossRegistry;
use TodBot\Service\CommandHandler;
use TodBot\Service\InitHandler;
use TodBot\Service\ReminderScheduler;

class DiscordBot
{
    private Discord $discord;
    private TodRepositoryInterface $repo;
    private ChannelConfigRepositoryInterface $channelConfigRepo;
    private BossRegistry $bossRegistry;

    public function __construct(Config $config, TodRepositoryInterface $repo, ChannelConfigRepositoryInterface $channelConfigRepo)
    {
        date_default_timezone_set('UTC');

        $this->repo = $repo;
        $this->channelConfigRepo = $channelConfigRepo;
        $this->bossRegistry = new BossRegistry(
            $config->getDefaultWindowStart(),
            $config->getDefaultWindowRandom(),
            $config->getBossConfigPath()
        );

        $this->discord = new Discord([
            'token'   => $config->getToken(),
            'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
        ]);

        $this->wireEvents();
    }

    private function wireEvents(): void
    {
        $discord           = $this->discord;
        $repo              = $this->repo;
        $channelConfigRepo = $this->channelConfigRepo;
        $bossRegistry      = $this->bossRegistry;

        $discord->on('init', function (Discord $discord) use ($repo, $channelConfigRepo, $bossRegistry) {
            echo "Bot is ready." . PHP_EOL;

            $commandHandler = new CommandHandler($discord, $repo, $bossRegistry, $channelConfigRepo);
            $initHandler    = new InitHandler($channelConfigRepo);

            $discord->on('message', function ($message) use ($commandHandler, $initHandler, $channelConfigRepo) {
                // Ignore messages from bots (including self)
                if ($message->author?->bot ?? false) {
                    return;
                }

                $content   = trim($message->content ?? '');
                $firstWord = strtolower(explode(' ', $content)[0] ?? '');

                // .init works on any channel, registered or not
                if ($firstWord === '.init') {
                    $initHandler->handleInit($message);
                    return;
                }

                // If this channel has an active init conversation in progress, route to init handler
                if ($initHandler->hasPending((string) $message->channel_id)) {
                    $initHandler->handleResponse($message);
                    return;
                }

                // All other ToD commands only work on channels that have been registered via .init
                if ($channelConfigRepo->get((string) $message->channel_id) === null) {
                    return;
                }

                $commandHandler($message);
            });

            new ReminderScheduler($discord, $repo, $bossRegistry, $channelConfigRepo)->start();
        });
    }

    public function run(): void
    {
        $this->discord->run();
    }
}
