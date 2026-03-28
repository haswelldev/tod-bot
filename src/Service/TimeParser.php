<?php

namespace TodBot\Service;

use DateTime;
use DateTimeZone;

class TimeParser
{
    /**
     * Parse a user-supplied time string with optional timezone into a UTC unix timestamp.
     *
     * @param string|null $timeArg e.g. "now", "14:35", "1435", "2025-11-28 14:00", "28.11.2025 14:00", "28-11 14:00", "2h", "30m", "2h30m", "10m ago", unix timestamp
     * @param string|null $tzArg   e.g. "Europe/Kyiv", "UTC", "UTC+2", "+2"
     * @param int|null    $now     reference time (unix, UTC). Defaults to time().
     * @return array{ts:int,tz:string} Parsed UTC timestamp and normalized timezone label used for interpretation.
     */
    public static function parse($timeArg = null, $tzArg = null, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $timeArg = $timeArg !== null ? trim((string) $timeArg) : '';
        $tzArg = $tzArg !== null ? trim((string) $tzArg) : '';

        // Determine timezone to interpret local times
        $tzInfo = self::normalizeTz($tzArg);
        $tzLabel = $tzInfo['label'];
        $offsetSeconds = $tzInfo['offset']; // null means use IANA timezone
        $iana = $tzInfo['iana']; // DateTimeZone or null

        // Default: no time provided → now
        if ($timeArg === '' || strtolower($timeArg) === 'now') {
            return ['ts' => $now, 'tz' => $tzLabel];
        }

        // Numeric unix timestamp
        if (ctype_digit($timeArg) && strlen($timeArg) >= 9 && strlen($timeArg) <= 11) {
            return ['ts' => (int) $timeArg, 'tz' => 'UTC'];
        }

        // Relative durations like 2h, 30m, 1h20m, optionally with 'ago' or leading '-'
        if (preg_match('/^-?\d+[hm](?:\d+m)?\s*(ago)?$/i', str_replace(' ', '', $timeArg)) || preg_match('/\d+\s*[hm]/i', $timeArg)) {
            $negative = strpos($timeArg, '-') === 0 || stripos($timeArg, 'ago') !== false;
            $hours = 0; $mins = 0;
            if (preg_match('/(\d+)\s*h/i', $timeArg, $m)) { $hours = (int) $m[1]; }
            if (preg_match('/(\d+)\s*m/i', $timeArg, $m)) { $mins = (int) $m[1]; }
            $delta = ($hours * 3600) + ($mins * 60);
            if ($delta > 0) {
                $ts = $negative ? $now - $delta : $now - $delta; // default assume 'ago'
                return ['ts' => $ts, 'tz' => 'UTC'];
            }
        }

        // HH:MM or HHMM → assume today in provided timezone (relative to provided $now)
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeArg, $m) || preg_match('/^(\d{3,4})$/', $timeArg, $m2)) {
            if (!empty($m2)) {
                $str = $m2[1];
                $h = (int) substr($str, 0, strlen($str) - 2);
                $i = (int) substr($str, -2);
            } else {
                $h = (int) $m[1];
                $i = (int) $m[2];
            }
            if ($h >= 0 && $h < 24 && $i >= 0 && $i < 60) {
                if ($iana) {
                    // Build the reference date from $now in the target IANA timezone
                    $ref = new DateTime('@' . $now);
                    $ref->setTimezone($iana);
                    // Set the provided wall-clock time on that date
                    $ref->setTime($h, $i, 0);
                    // Convert to UTC epoch
                    $utcTs = self::toUtcTimestamp($ref);
                    return ['ts' => $utcTs, 'tz' => $tzLabel];
                } else {
                    // Offset-based tz: build using UTC then subtract offset
                    $utc = new DateTime('@' . $now); // immutable point
                    $utc->setTimezone(new DateTimeZone('UTC'));
                    $ymd = gmdate('Y-m-d', $now + ($offsetSeconds !== null ? $offsetSeconds : 0));
                    $dt = new DateTime($ymd . sprintf(' %02d:%02d:00', $h, $i), new DateTimeZone('UTC'));
                    // dt is in UTC representing local time; convert to true UTC by subtracting offset
                    $ts = $dt->getTimestamp() - (int) $offsetSeconds;
                    return ['ts' => $ts, 'tz' => $tzLabel];
                }
            }
        }

        // Full date-time attempts with various formats in the given timezone
        $formats = [
            'Y-m-d H:i',
            'Y/m/d H:i',
            'd.m.Y H:i',
            'd-m-Y H:i',
            'd/m/Y H:i',
            'd-m H:i', // current year
            'd.m H:i', // current year
        ];
        foreach ($formats as $fmt) {
            $fmtToUse = $fmt;
            $text = $timeArg;
            if ($fmt === 'd-m H:i' || $fmt === 'd.m H:i') {
                $year = gmdate('Y', $now);
                // Insert the year between the date and time parts
                $text = preg_replace('/\s+/', ' ' . $year . ' ', $timeArg, 1);
                $fmtToUse = str_replace(['d-m H:i', 'd.m H:i'], ['d-m Y H:i', 'd.m Y H:i'], $fmt);
            }
            $dt = false;
            if ($iana) {
                $dt = DateTime::createFromFormat($fmtToUse, $text, $iana);
            } else {
                $dt = DateTime::createFromFormat($fmtToUse, $text, new DateTimeZone('UTC'));
            }
            if ($dt instanceof DateTime) {
                if ($iana) {
                    return ['ts' => self::toUtcTimestamp($dt), 'tz' => $tzLabel];
                }
                // Offset-based
                $tsLocal = $dt->getTimestamp();
                $ts = $tsLocal - (int) $offsetSeconds;
                return ['ts' => $ts, 'tz' => $tzLabel];
            }
        }

        // Generic fallback: let strtotime handle various inputs if possible
        $fallback = @strtotime($timeArg . ' ' . $tzLabel);
        if ($fallback !== false) {
            return ['ts' => (int) $fallback, 'tz' => $tzLabel];
        }

        // If nothing matched, return null by throwing to caller via false
        return ['ts' => null, 'tz' => $tzLabel];
    }

    private static function normalizeTz($tzArg)
    {
        $label = 'UTC';
        if ($tzArg === '') {
            return ['label' => $label, 'offset' => 0, 'iana' => null];
        }
        $arg = strtoupper($tzArg);

        // Explicit UTC/GMT with numeric offset like UTC+2 or GMT-3 (preserve prefix in label)
        if (preg_match('/^(UTC|GMT)\s*([+-])\s*(\d{1,2})$/', $arg, $m)) {
            $sign = $m[2] === '-' ? -1 : 1;
            $hours = (int) $m[3];
            $offset = $sign * $hours * 3600;
            $label = $m[1] . ($sign >= 0 ? '+' : '-') . $hours;
            return ['label' => $label, 'offset' => $offset, 'iana' => null];
        }

        // Plain numeric offsets like +2 or -4 (normalize label to UTC±H)
        if (preg_match('/^([+-])\s*(\d{1,2})$/', $arg, $m)) {
            $sign = $m[1] === '-' ? -1 : 1;
            $hours = (int) $m[2];
            $offset = $sign * $hours * 3600;
            $label = 'UTC' . ($sign >= 0 ? '+' : '') . $hours;
            return ['label' => $label, 'offset' => $offset, 'iana' => null];
        }

        // Exactly UTC/GMT means UTC
        if ($arg === 'UTC' || $arg === 'GMT') {
            return ['label' => 'UTC', 'offset' => 0, 'iana' => null];
        }

        // IANA timezone? (check last so that shorthand like '+2' doesn't get treated as IANA)
        try {
            $iana = new DateTimeZone($tzArg);
            $label = $tzArg;
            return ['label' => $label, 'offset' => null, 'iana' => $iana];
        } catch (\Exception $e) {
            // not an IANA id
        }

        // Fallback to UTC if unrecognized
        return ['label' => 'UTC', 'offset' => 0, 'iana' => null];
    }

    private static function toUtcTimestamp(DateTime $dt)
    {
        // Convert DateTime with its current timezone to UTC epoch seconds
        $clone = clone $dt;
        $clone->setTimezone(new DateTimeZone('UTC'));
        return $clone->getTimestamp();
    }
}
