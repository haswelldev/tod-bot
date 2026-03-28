<?php

namespace TodBot\Repository;

interface TodRepositoryInterface
{
    /**
     * Returns all ToDs grouped by channel id.
     * @return array<string, array<string, array{tod:int,channel:string,start_reminded:bool,end_reminded:bool}>>
     */
    public function all();

    /**
     * Returns all ToDs for a specific channel.
     *
     * @param string $channel
     * @return array<string, array{tod:int,channel:string,start_reminded:bool,end_reminded:bool}>
     */
    public function allByChannel($channel);

    /**
     * @param string $boss
     * @param string $channel
     * @return array|null
     */
    public function get($boss, $channel);

    /**
     * @param string $boss
     * @param string $channel
     * @param array $data
     * @return void
     */
    public function set($boss, $channel, $data);

    /**
     * @param string $boss
     * @param string $channel
     * @return void
     */
    public function delete($boss, $channel);

    /**
     * Persist current state to storage.
     * @return void
     */
    public function save();
}
