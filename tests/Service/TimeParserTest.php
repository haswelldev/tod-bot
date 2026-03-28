<?php

use TodBot\Service\TimeParser;
use PHPUnit\Framework\TestCase;

class TimeParserTest extends TestCase
{
    private $now;

    protected function setUp(): void
    {
        // Fixed reference time: 2025-11-28 12:00:00 UTC
        $this->now = 1764331200; // computed for deterministic tests
    }

    public function testNowAndDefault()
    {
        $r1 = TimeParser::parse(null, null, $this->now);
        $this->assertSame($this->now, $r1['ts']);
        $this->assertSame('UTC', $r1['tz']);

        $r2 = TimeParser::parse('now', 'UTC', $this->now);
        $this->assertSame($this->now, $r2['ts']);
        $this->assertSame('UTC', $r2['tz']);
    }

    public function testUnixTimestamp()
    {
        $r = TimeParser::parse('1700000000', null, $this->now);
        $this->assertSame(1700000000, $r['ts']);
        $this->assertSame('UTC', $r['tz']);
    }

    public function testRelativeTimes()
    {
        $r = TimeParser::parse('30m ago', null, $this->now);
        $this->assertSame($this->now - 1800, $r['ts']);

        $r = TimeParser::parse('2h', null, $this->now);
        $this->assertSame($this->now - 7200, $r['ts']);

        $r = TimeParser::parse('-45m', null, $this->now);
        $this->assertSame($this->now - 2700, $r['ts']);
    }

    public function testClockFormatsWithIanaTz()
    {
        // 14:30 Europe/Kyiv on the reference date
        $r = TimeParser::parse('14:30', 'Europe/Kyiv', $this->now);
        $dt = new DateTime('2025-11-28 14:30:00', new DateTimeZone('Europe/Kyiv'));
        $expected = (clone $dt)->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        $this->assertSame($expected, $r['ts']);
        $this->assertSame('Europe/Kyiv', $r['tz']);

        $r2 = TimeParser::parse('1430', 'Europe/Kyiv', $this->now);
        $this->assertSame($expected, $r2['ts']);
        $this->assertSame('Europe/Kyiv', $r2['tz']);
    }

    public function testClockFormatsWithOffsetTz()
    {
        $r = TimeParser::parse('14:00', 'UTC+2', $this->now);
        // 14:00 local at UTC+2 equals 12:00 UTC same date
        $ymd = gmdate('Y-m-d', $this->now + 7200);
        $expected = strtotime($ymd.' 12:00:00 UTC');
        $this->assertSame($expected, $r['ts']);
        $this->assertSame('UTC+2', $r['tz']);

        $r2 = TimeParser::parse('1400', '+2', $this->now);
        $this->assertSame($expected, $r2['ts']);
        $this->assertSame('UTC+2', $r2['tz']);
    }

    public function testFullDateTimeFormats()
    {
        $cases = [
            ['2025-11-28 14:00', 'UTC', '2025-11-28 14:00:00 UTC'],
            ['2025/11/28 14:00', 'UTC', '2025-11-28 14:00:00 UTC'],
            ['28.11.2025 14:00', 'UTC', '2025-11-28 14:00:00 UTC'],
            ['28-11-2025 14:00', 'UTC', '2025-11-28 14:00:00 UTC'],
            ['28/11/2025 14:00', 'UTC', '2025-11-28 14:00:00 UTC'],
        ];
        foreach ($cases as [$input, $tz, $expectedStr]) {
            $r = TimeParser::parse($input, $tz, $this->now);
            $this->assertSame(strtotime($expectedStr), $r['ts'], $input);
        }

        // Short formats without year assume current year
        $r = TimeParser::parse('28-11 14:00', 'UTC', $this->now);
        $this->assertSame(strtotime('2025-11-28 14:00:00 UTC'), $r['ts']);

        $r = TimeParser::parse('28.11 14:00', 'UTC', $this->now);
        $this->assertSame(strtotime('2025-11-28 14:00:00 UTC'), $r['ts']);
    }

    public function testTimezoneParsing()
    {
        $r = TimeParser::parse('14:00', 'GMT-3', $this->now);
        $ymd = gmdate('Y-m-d', $this->now - 10800);
        $expected = strtotime($ymd.' 17:00:00 UTC'); // 14:00 at -3 => 17:00 UTC
        $this->assertSame($expected, $r['ts']);
        $this->assertSame('GMT-3', $r['tz']);

        $r2 = TimeParser::parse('14:00', 'UTC', $this->now);
        $ymd2 = gmdate('Y-m-d', $this->now);
        $this->assertSame(strtotime($ymd2.' 14:00:00 UTC'), $r2['ts']);
    }

    public function testInvalidInput()
    {
        $r = TimeParser::parse('nonsense', 'UTC+2', $this->now);
        $this->assertNull($r['ts']);
        $this->assertSame('UTC+2', $r['tz']);
    }
}
