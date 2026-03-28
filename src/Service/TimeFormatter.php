<?php

namespace TodBot\Service;

class TimeFormatter
{
    public static function fmt($ts)
    {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /**
     * Returns a Discord dynamic timestamp which renders in each user's local time.
     * $style corresponds to Discord formatting styles: t, T, d, D, f, F, R
     * Default 'F' shows full date and time.
     */
    public static function discord($ts, $style = 'F')
    {
        $ts = (int) $ts;
        return '<t:' . $ts . ':' . $style . '>';
    }
}
