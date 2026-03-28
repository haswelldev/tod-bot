<?php

namespace TodBot\Service;

use TodBot\Repository\ChannelConfigRepositoryInterface;

class InitHandler
{
    private ChannelConfigRepositoryInterface $channelConfigRepo;

    /** @var array<string, array{step: string, locale?: string, reminders?: bool}> */
    private array $pending = [];

    private const array LANGUAGES = [
        '1' => ['code' => 'en', 'name' => 'English'],
        '2' => ['code' => 'ru', 'name' => 'Русский'],
        '3' => ['code' => 'uk', 'name' => 'Українська'],
        '4' => ['code' => 'fr', 'name' => 'Français'],
        '5' => ['code' => 'el', 'name' => 'Ελληνικά'],
        '6' => ['code' => 'pt', 'name' => 'Português'],
    ];

    public function __construct(ChannelConfigRepositoryInterface $channelConfigRepo)
    {
        $this->channelConfigRepo = $channelConfigRepo;
    }

    public function hasPending(string $channelId): bool
    {
        return isset($this->pending[$channelId]);
    }

    public function handleInit($message): void
    {
        $channelId = (string) $message->channel_id;

        $existing = $this->channelConfigRepo->get($channelId);
        if ($existing !== null) {
            $langName = $this->langName($existing['locale'] ?? 'en');
            $message->channel->sendMessage(
                "✅ This channel is already configured as a ToD tracking channel (language: **$langName**)."
            )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
            return;
        }

        $this->pending[$channelId] = ['step' => 'language'];

        $lines = [];
        foreach (self::LANGUAGES as $n => $lang) {
            $lines[] = "$n. {$lang['name']} (`{$lang['code']}`)";
        }

        $message->channel->sendMessage(
            "**ToD Bot Setup**\n\n"
            . "Please choose a language:\n"
            . implode("\n", $lines) . "\n\n"
            . "Reply with the number or language code (e.g. `1` or `en`)."
        )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
    }

    public function handleResponse($message): void
    {
        $channelId = (string) $message->channel_id;
        $state = $this->pending[$channelId];
        $text = trim($message->content);

        if ($state['step'] === 'language') {
            $locale = $this->resolveLocale($text);

            if ($locale === null) {
                $message->channel->sendMessage(
                    "❓ Unknown language. Please reply with a number (1–" . count(self::LANGUAGES) . ") or a language code."
                )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
                return;
            }

            $this->pending[$channelId] = ['step' => 'confirm', 'locale' => $locale];
            $langName = $this->langName($locale);

            $message->channel->sendMessage(
                "Language selected: **$langName**\n\n"
                . "Register <#$channelId> as a ToD tracking channel?\n"
                . "Reply with `yes` to confirm or `no` to cancel."
            )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
            return;
        }

        if ($state['step'] === 'confirm') {
            $lower = strtolower($text);

            if ($lower === 'yes') {
                $this->pending[$channelId] = ['step' => 'reminders', 'locale' => $state['locale']];
                $message->channel->sendMessage(
                    "**Reminders Setup**\n\n"
                    . "Enable automatic reminders for all bosses in this channel?\n"
                    . "- `yes` — notify when every boss window opens and closes\n"
                    . "- `no` — reminders off (use `.remind BossName` for one-time alerts)\n\n"
                    . "Reply with `yes` or `no`."
                )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
                return;
            }

            if ($lower === 'no') {
                unset($this->pending[$channelId]);
                $message->channel->sendMessage(
                    "Setup cancelled."
                )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
                return;
            }

            $message->channel->sendMessage(
                "Please reply with `yes` or `no`."
            )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
            return;
        }

        if ($state['step'] === 'reminders') {
            $lower = strtolower($text);

            if (!in_array($lower, ['yes', 'no'])) {
                $message->channel->sendMessage(
                    "Please reply with `yes` or `no`."
                )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
                return;
            }

            $this->channelConfigRepo->set($channelId, [
                'guild_id'          => $message->guild_id ?? '',
                'guild_name'        => $message->channel->guild?->name ?? '',
                'channel_name'      => $message->channel->name ?? '',
                'locale'            => (string) $state['locale'],
                'reminders_enabled' => $lower === 'yes',
            ]);
            $this->channelConfigRepo->save();
            unset($this->pending[$channelId]);

            $reminderStatus = $lower === 'yes'
                ? "🔔 Reminders **enabled** — the bot will notify this channel when every boss window opens and closes."
                : "🔕 Reminders **disabled** — use `.remind BossName` to set a one-time alert for a specific boss.";

            $message->channel->sendMessage(
                "✅ **Channel registered!**\n"
                . "$reminderStatus\n\n"
                . "**Quick-start commands:**\n"
                . "`.tod Antharas` — record Antharas death right now\n"
                . "`.tod Antharas 14:30` — record death at 14:30 (today, server time)\n"
                . "`.tod Antharas 14:30 UTC+2` — record death at 14:30 in a specific timezone\n"
                . "`.window Antharas` — show the current respawn window\n"
                . "`.list` — show all tracked bosses and their window status\n"
                . "`.del Antharas` — remove a boss from tracking\n"
                . "`.remind Antharas` — set a one-time reminder when Antharas window opens\n"
                . "`.reminders on` / `.reminders off` — toggle automatic reminders for this channel\n\n"
                . "Boss names are case-insensitive. Aliased spellings and Cyrillic names are also supported."
            )->then(function () use ($message) { $message->delete(); }, function () use ($message) { $message->delete(); });
        }
    }

    private function resolveLocale(string $input): ?string
    {
        $input = trim($input);

        // Match by number
        if (isset(self::LANGUAGES[$input])) {
            return self::LANGUAGES[$input]['code'];
        }

        // Match by code (case-insensitive)
        $lower = strtolower($input);
        foreach (self::LANGUAGES as $lang) {
            if ($lang['code'] === $lower) {
                return $lang['code'];
            }
        }

        return null;
    }

    private function langName(string $code): string
    {
        foreach (self::LANGUAGES as $lang) {
            if ($lang['code'] === $code) {
                return $lang['name'];
            }
        }
        return $code;
    }
}
