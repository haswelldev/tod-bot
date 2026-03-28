<?php

namespace TodBot\Service;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use TodBot\Repository\ChannelConfigRepositoryInterface;
use TodBot\Repository\TodRepositoryInterface;

class ReminderScheduler
{
    private Discord $discord;
    private TodRepositoryInterface $repo;
    private BossRegistry $bossRegistry;
    private ?ChannelConfigRepositoryInterface $channelConfigRepo;

    public function __construct(
        Discord $discord,
        TodRepositoryInterface $repo,
        ?BossRegistry $bossRegistry = null,
        ?ChannelConfigRepositoryInterface $channelConfigRepo = null
    ) {
        $this->discord = $discord;
        $this->repo = $repo;
        $this->bossRegistry = $bossRegistry ?? new BossRegistry();
        $this->channelConfigRepo = $channelConfigRepo;
    }

    public function start(): void
    {
        $discord = $this->discord;
        $repo = $this->repo;
        $bossRegistry = $this->bossRegistry;
        $channelConfigRepo = $this->channelConfigRepo;

        $discord->loop->addPeriodicTimer(60, function () use ($discord, $repo, $bossRegistry, $channelConfigRepo) {
            $now = time();
            $tods = $repo->all(); // [channelId => [boss => info]]

            foreach ($tods as $channelId => $byBoss) {
                if (!$channelId) { continue; }

                $channel = $discord->getChannel($channelId);
                if (!$channel) { continue; }

                // Use this channel's configured locale for reminder messages
                $locale = $channelConfigRepo
                    ? ($channelConfigRepo->get($channelId)['locale'] ?? null)
                    : null;
                I18n::setLocale($locale);

                foreach ($byBoss as $boss => $info) {
                    $tod = $info['tod'] ?? 0;
                    $startReminded = !empty($info['start_reminded']);
                    $endReminded = !empty($info['end_reminded']);

                    $window = $bossRegistry->getWindow($boss);
                    $start = $tod + $window['start'];
                    $end   = $tod + $window['end'];

                    if (!$startReminded && $now >= $start) {
                        $embed = new Embed($discord);
                        $embed->setTitle(I18n::t('reminder.start.title', ['%boss%' => ucfirst($boss)]))
                            ->setColor(0x00cc99)
                            ->addFieldValues(I18n::t('reminder.start.field'), TimeFormatter::discord($start), true);
                        $channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                        $info['start_reminded'] = true;
                    }

                    if (!$endReminded && $now >= $end) {
                        $embed = new Embed($discord);
                        $embed->setTitle(I18n::t('reminder.end.title', ['%boss%' => ucfirst($boss)]))
                            ->setColor(0xFF6600)
                            ->addFieldValues(I18n::t('reminder.end.field'), TimeFormatter::discord($end), true);
                        $channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                        $info['end_reminded'] = true;
                    }

                    // Persist updates if changed
                    $repo->set($boss, $channelId, $info);
                }
            }

            $repo->save();
        });
    }
}
