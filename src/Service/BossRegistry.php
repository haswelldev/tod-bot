<?php

namespace NapevBot\Service;

use Symfony\Component\Yaml\Yaml;

class BossRegistry
{
    /** @var array<string, array{aliases: string[], respawn: int, random: int}> */
    private array $bosses = [];
    private int $defaultRespawn;
    private int $defaultRandom;

    public function __construct(int $defaultRespawn = 12, int $defaultRandom = 9, string $configPath = '')
    {
        $this->defaultRespawn = $defaultRespawn;
        $this->defaultRandom  = $defaultRandom;

        if ($configPath !== '' && file_exists($configPath)) {
            $this->load($configPath);
        }
    }

    private function load(string $path): void
    {
        $data = Yaml::parseFile($path);
        foreach ($data['bosses'] ?? [] as $name => $cfg) {
            $canonical = strtolower(trim((string) $name));
            $this->bosses[$canonical] = [
                'aliases' => array_map('strtolower', $cfg['aliases'] ?? []),
                'respawn' => (int) ($cfg['respawn'] ?? $this->defaultRespawn),
                'random'  => (int) ($cfg['random']  ?? $this->defaultRandom),
            ];
        }
    }

    /**
     * Resolve a user-supplied name to the canonical boss name.
     *
     * Resolution order:
     *   1. Exact match on canonical name
     *   2. Exact match on any alias
     *   3. User input is a substring of a canonical name  ("taras" → "antharas")
     *   4. User input is a substring of any alias
     *   5. Return input as-is (unknown boss, default window will apply)
     */
    public function resolve(string $input): string
    {
        $input = strtolower(trim($input));

        if (isset($this->bosses[$input])) {
            return $input;
        }

        foreach ($this->bosses as $canonical => $data) {
            if (in_array($input, $data['aliases'], true)) {
                return $canonical;
            }
        }

        foreach ($this->bosses as $canonical => $data) {
            if (str_contains($canonical, $input)) {
                return $canonical;
            }
        }

        foreach ($this->bosses as $canonical => $data) {
            foreach ($data['aliases'] as $alias) {
                if (str_contains($alias, $input)) {
                    return $canonical;
                }
            }
        }

        return $input;
    }

    /**
     * Return window offsets in seconds for a canonical boss name.
     *
     * @return array{start: int, end: int}
     */
    public function getWindow(string $boss): array
    {
        $data    = $this->bosses[$boss] ?? null;
        $respawn = $data ? $data['respawn'] : $this->defaultRespawn;
        $random  = $data ? $data['random']  : $this->defaultRandom;

        return [
            'start' => $respawn * 3600,
            'end'   => ($respawn + $random) * 3600,
        ];
    }
}
