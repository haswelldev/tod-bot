<?php

namespace TodBot\Service;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use TodBot\Repository\ChannelConfigRepositoryInterface;
use TodBot\Repository\TodRepositoryInterface;

class CommandHandler
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

    public function __invoke($message): void
    {
        // Set per-channel locale for all I18n::t() calls in this invocation
        $locale = $this->channelConfigRepo
            ? ($this->channelConfigRepo->get((string) $message->channel_id)['locale'] ?? null)
            : null;
        I18n::setLocale($locale);

        $content = trim($message->content);
        $parts = explode(' ', $content);
        $cmd = strtolower($parts[0]);

        if (in_array($cmd, ['.tod', '.тод']) && isset($parts[1])) {
            $boss = $this->bossRegistry->resolve(strtolower($parts[1]));
            $args = array_slice($parts, 2);
            $timeArg = null;
            $tzArg = null;
            if (!empty($args)) {
                // If there are 2+ args, try to detect if the last one is a timezone, then join the rest as time
                if (count($args) >= 2) {
                    $maybeTz = $args[count($args) - 1];
                    if ($this->looksLikeTimezone($maybeTz)) {
                        $tzArg = $maybeTz;
                        $timeArg = trim(implode(' ', array_slice($args, 0, -1)));
                    } else {
                        $timeArg = trim(implode(' ', $args));
                    }
                } else {
                    $timeArg = $args[0];
                }
            }
            $this->handleTod($message, $boss, $timeArg, $tzArg);
            return;
        }

        if (in_array($cmd, ['.window', '.w', '.вікно', '.окно']) && isset($parts[1])) {
            $this->handleWindow($message, $this->bossRegistry->resolve(strtolower($parts[1])));
            return;
        }

        if (in_array($cmd, ['.del', '.дел']) && isset($parts[1])) {
            $this->handleDelete($message, $this->bossRegistry->resolve(strtolower($parts[1])));
            return;
        }

        if (in_array($cmd, ['.list', '.ls', '.all', '.список'])) {
            $this->handleList($message);
            return;
        }
    }

    private function looksLikeTimezone($s): bool
    {
        $s = trim($s);
        if ($s === '') return false;
        $u = strtoupper($s);
        if ($u === 'UTC' || $u === 'GMT') return true;
        if (preg_match('/^(UTC|GMT)?\s*[+-]\s*\d{1,2}$/', $u)) return true;
        // IANA tz contains a slash usually, like Europe/Kyiv or America/New_York
        if (str_contains($s, '/')) return true;
        return false;
    }

    private function handleTod($message, $boss, $timeArg = null, $tzArg = null): void
    {
        $parsed = TimeParser::parse($timeArg, $tzArg, time());
        $now = $parsed['ts'];

        if ($now === null) {
            $help = I18n::t('help.unrecognized_time');
            $message->channel->sendMessage($help)
                ->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
            return;
        }

        $data = [
            'tod' => $now,
            'channel' => $message->channel_id,
            'start_reminded' => false,
            'end_reminded' => false,
        ];
        $this->repo->set($boss, $message->channel_id, $data);
        $this->repo->save();

        $window = $this->bossRegistry->getWindow($boss);
        $start = $now + $window['start'];
        $end   = $now + $window['end'];

        $embed = new Embed($this->discord);
        $embed->setTitle(I18n::t('tod.title', ['%boss%' => ucfirst($boss)]))
            ->setColor(0x3498db)
            ->addFieldValues(I18n::t('common.death_time'), TimeFormatter::discord($now), false)
            ->addFieldValues(I18n::t('common.window_start'), TimeFormatter::discord($start), true)
            ->addFieldValues(I18n::t('common.window_end'), TimeFormatter::discord($end), true);

        // Use MessageBuilder to send embeds (discord-php >=10)
        // Delete user's command message after responding (if bot has permission)
        $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed))
            ->then(function () use ($message) {
                $message->delete();
            }, function () use ($message) {
                $message->delete();
            });
    }

    private function handleWindow($message, $boss): void
    {
        $info = $this->repo->get($boss, $message->channel_id);
        if (!$info) {
            $message->channel->sendMessage(I18n::t('common.no_boss', ['%boss%' => $boss]))
                ->then(function () use ($message) {
                    $message->delete();
                }, function () use ($message) {
                    $message->delete();
                });
            return;
        }

        $tod = $info['tod'];
        $window = $this->bossRegistry->getWindow($boss);
        $start = $tod + $window['start'];
        $end   = $tod + $window['end'];

        $embed = new Embed($this->discord);
        $embed->setTitle(I18n::t('window.title', ['%boss%' => ucfirst($boss)]))
            ->setColor(0x2ecc71)
            ->addFieldValues(I18n::t('common.last_tod'), TimeFormatter::discord($tod), false)
            ->addFieldValues(I18n::t('common.window_start'), TimeFormatter::discord($start), true)
            ->addFieldValues(I18n::t('common.window_end'), TimeFormatter::discord($end), true);

        // Use MessageBuilder to send embeds (discord-php >=10)
        $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed))
            ->then(function () use ($message) {
                $message->delete();
            }, function () use ($message) {
                $message->delete();
            });
    }

    private function handleDelete($message, $boss): void
    {
        $info = $this->repo->get($boss, $message->channel_id);
        if (!$info) {
            $message->channel->sendMessage(I18n::t('common.no_boss', ['%boss%' => $boss]))
                ->then(function () use ($message) {
                    $message->delete();
                }, function () use ($message) {
                    $message->delete();
                });
            return;
        }

        $this->repo->delete($boss, $message->channel_id);
        $this->repo->save();

        $embed = new Embed($this->discord);
        $embed->setTitle(I18n::t('del.title', ['%boss%' => ucfirst($boss)]))
            ->setColor(0xFF3333);

        // Use MessageBuilder to send embeds (discord-php >=10)
        $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed))
            ->then(function () use ($message) {
                $message->delete();
            }, function () use ($message) {
                $message->delete();
            });
    }

    private function handleList($message): void
    {
        $all = $this->repo->allByChannel($message->channel_id);
        $now = time();
        $lines = [];
        foreach ($all as $boss => $info) {
            if (!isset($info['tod'])) continue;
            $tod = (int) $info['tod'];
            $window = $this->bossRegistry->getWindow($boss);
            $start = $tod + $window['start'];
            $end   = $tod + $window['end'];
            if ($now >= $end) {
                // window closed — skip
                continue;
            }

            if ($now < $start) {
                $lines[] = I18n::t('list.opens_in', ['%boss%' => ucfirst($boss)]) . ' ' . TimeFormatter::discord($start, 'R');
            } else {
                $lines[] = I18n::t('list.closes_in', ['%boss%' => ucfirst($boss)]) . ' ' . TimeFormatter::discord($end, 'R');
            }
        }

        if (empty($lines)) {
            $text = I18n::t('common.none_available');
        } else {
            $text = I18n::t('list.header') . "\n" . implode("\n", $lines);
        }

        $message->channel->sendMessage($text)
            ->then(function () use ($message) {
                $message->delete();
            }, function () use ($message) {
                $message->delete();
            });
    }
}
