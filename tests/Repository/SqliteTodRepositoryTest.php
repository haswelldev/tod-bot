<?php

use TodBot\Repository\SqliteTodRepository;
use PHPUnit\Framework\TestCase;

class SqliteTodRepositoryTest extends TestCase
{
    private string $dbFile;
    private SqliteTodRepository $repo;

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'tods_');
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile); // ensure PDO creates it cleanly
        }
        $this->repo = new SqliteTodRepository($this->dbFile);
    }

    protected function tearDown(): void
    {
        if (isset($this->repo)) {
            // no explicit close needed
        }
        if (is_file($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testSetGetAndDeletePerChannel()
    {
        $chanA = 'channel-A';
        $chanB = 'channel-B';

        $this->repo->set('antharas', $chanA, [
            'tod' => 1700000000,
            'start_reminded' => false,
            'end_reminded' => false,
        ]);
        $this->repo->set('antharas', $chanB, [
            'tod' => 1700000100,
            'start_reminded' => true,
            'end_reminded' => false,
        ]);

        $a = $this->repo->get('antharas', $chanA);
        $b = $this->repo->get('antharas', $chanB);

        $this->assertIsArray($a);
        $this->assertSame(1700000000, $a['tod']);
        $this->assertFalse($a['start_reminded']);
        $this->assertFalse($a['end_reminded']);

        $this->assertIsArray($b);
        $this->assertSame(1700000100, $b['tod']);
        $this->assertTrue($b['start_reminded']);
        $this->assertFalse($b['end_reminded']);

        // Delete only channel A
        $this->repo->delete('antharas', $chanA);
        $this->assertNull($this->repo->get('antharas', $chanA));
        $this->assertNotNull($this->repo->get('antharas', $chanB));
    }

    public function testAllAndAllByChannel()
    {
        $chan = 'test-channel';
        $other = 'other-channel';

        $this->repo->set('baium', $chan, [
            'tod' => 1,
            'start_reminded' => 0,
            'end_reminded' => 0,
        ]);
        $this->repo->set('zaken', $chan, [
            'tod' => 2,
            'start_reminded' => 1,
            'end_reminded' => 0,
        ]);
        $this->repo->set('orfen', $other, [
            'tod' => 3,
            'start_reminded' => 1,
            'end_reminded' => 1,
        ]);

        $grouped = $this->repo->all();
        $this->assertArrayHasKey($chan, $grouped);
        $this->assertArrayHasKey($other, $grouped);
        $this->assertArrayHasKey('baium', $grouped[$chan]);
        $this->assertArrayHasKey('zaken', $grouped[$chan]);
        $this->assertArrayHasKey('orfen', $grouped[$other]);

        $byChan = $this->repo->allByChannel($chan);
        $this->assertArrayHasKey('baium', $byChan);
        $this->assertArrayHasKey('zaken', $byChan);
        $this->assertArrayNotHasKey('orfen', $byChan);
        $this->assertSame(2, $byChan['zaken']['tod']);
        $this->assertTrue($byChan['zaken']['start_reminded']);
        $this->assertFalse($byChan['zaken']['end_reminded']);
    }
}
