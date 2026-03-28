<?php

namespace TodBot\Repository;

interface ChannelConfigRepositoryInterface
{
    /**
     * @return array{guild_id: string, locale: string}|null
     */
    public function get(string $channelId): ?array;

    /**
     * @param array{guild_id: string, locale: string} $data
     */
    public function set(string $channelId, array $data): void;

    public function delete(string $channelId): void;

    public function save(): void;
}
