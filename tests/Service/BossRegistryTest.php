<?php

use TodBot\Service\BossRegistry;
use PHPUnit\Framework\TestCase;

class BossRegistryTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = dirname(__DIR__, 2) . '/config/bosses.yaml';
    }

    // --- resolve() without any config (pure defaults) ---

    public function testResolveUnknownBossReturnsAsIs(): void
    {
        $registry = new BossRegistry();
        $this->assertSame('shuriel', $registry->resolve('shuriel'));
        $this->assertSame('icarus', $registry->resolve('ICARUS'));
    }

    // --- resolve() with bosses.yaml ---

    public function testResolveExactCanonicalName(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $this->assertSame('antharas', $registry->resolve('antharas'));
        $this->assertSame('valakas', $registry->resolve('VALAKAS'));
        $this->assertSame('queen ant', $registry->resolve('queen ant'));
    }

    public function testResolveExactAlias(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $this->assertSame('antharas', $registry->resolve('taras'));
        $this->assertSame('frintezza', $registry->resolve('tezza'));
        $this->assertSame('queen ant', $registry->resolve('qa'));
        $this->assertSame('queen ant', $registry->resolve('aq'));
        $this->assertSame('queen ant', $registry->resolve('AQ'));
        $this->assertSame('queen ant', $registry->resolve('ant queen'));
    }

    public function testResolvePartialSubstringOfCanonicalName(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        // "taras" is a substring of "antharas"
        $this->assertSame('antharas', $registry->resolve('taras'));
        // "tezza" is a substring of "frintezza"
        $this->assertSame('frintezza', $registry->resolve('tezza'));
        // "aken" is a substring of "zaken"
        $this->assertSame('zaken', $registry->resolve('aken'));
        // "aium" is a substring of "baium"
        $this->assertSame('baium', $registry->resolve('aium'));
    }

    public function testResolveCaseInsensitive(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $this->assertSame('antharas', $registry->resolve('ANTHARAS'));
        $this->assertSame('antharas', $registry->resolve('Taras'));
        $this->assertSame('queen ant', $registry->resolve('QA'));
    }

    public function testResolveUnknownBossWithConfigReturnsAsIs(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $this->assertSame('shuriel', $registry->resolve('shuriel'));
    }

    // --- getWindow() default values ---

    public function testGetWindowDefaultValues(): void
    {
        $registry = new BossRegistry(12, 9);
        $window = $registry->getWindow('anything');
        $this->assertSame(12 * 3600, $window['start']);
        $this->assertSame(21 * 3600, $window['end']);
    }

    public function testGetWindowCustomDefaults(): void
    {
        $registry = new BossRegistry(24, 6);
        $window = $registry->getWindow('anything');
        $this->assertSame(24 * 3600, $window['start']);
        $this->assertSame(30 * 3600, $window['end']);
    }

    // --- getWindow() with boss config ---

    public function testGetWindowAntharas(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $window = $registry->getWindow('antharas');
        $this->assertSame(192 * 3600, $window['start']);
        $this->assertSame(196 * 3600, $window['end']); // 192 + 4
    }

    public function testGetWindowValakas(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $window = $registry->getWindow('valakas');
        $this->assertSame(264 * 3600, $window['start']);
        $this->assertSame(268 * 3600, $window['end']); // 264 + 4
    }

    public function testGetWindowQueenAnt(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $window = $registry->getWindow('queen ant');
        $this->assertSame(24 * 3600, $window['start']);
        $this->assertSame(28 * 3600, $window['end']); // 24 + 4
    }

    public function testGetWindowUnknownBossFallsBackToDefaults(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $window = $registry->getWindow('shuriel');
        $this->assertSame(12 * 3600, $window['start']);
        $this->assertSame(21 * 3600, $window['end']);
    }

    // --- round-trip: resolve then getWindow ---

    public function testResolveAndWindowForAlias(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $canonical = $registry->resolve('taras');
        $this->assertSame('antharas', $canonical);
        $window = $registry->getWindow($canonical);
        $this->assertSame(192 * 3600, $window['start']);
    }

    public function testResolveAndWindowForQA(): void
    {
        $registry = new BossRegistry(12, 9, $this->configPath);
        $canonical = $registry->resolve('qa');
        $this->assertSame('queen ant', $canonical);
        $window = $registry->getWindow($canonical);
        $this->assertSame(24 * 3600, $window['start']);
    }

    // --- missing config file ---

    public function testMissingConfigFileUsesDefaults(): void
    {
        $registry = new BossRegistry(12, 9, '/nonexistent/path/bosses.yaml');
        $this->assertSame('antharas', $registry->resolve('antharas')); // no alias resolution, returns as-is
        $window = $registry->getWindow('antharas');
        $this->assertSame(12 * 3600, $window['start']); // falls back to defaults
    }
}
