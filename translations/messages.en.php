<?php

return [
    'help.unrecognized_time' => "Couldn't recognize the time. Examples:\n"
        . ".tod icarus 14:30 Europe/Kyiv\n"
        . ".tod behemoth 1430 UTC+2\n"
        . ".tod catgang 2025-11-28 14:00 UTC\n"
        . ".tod shuriel now\n"
        . ".tod skylancer 30m ago",

    'common.last_tod' => 'Last ToD',
    'common.death_time' => 'Death time',
    'common.window_start' => 'Window start',
    'common.window_end' => 'Window end',
    'common.no_boss' => 'No ToD for **%boss%**.',
    'common.none_available' => 'No available bosses.',

    'list.header' => 'Current ToDs/windows:',
    'list.opens_in' => '%boss% — opens in:',
    'list.closes_in' => '%boss% — closes in:',

    'tod.title' => '💀 %boss% was killed.',
    'window.title' => '📅 Respawn window: %boss%',
    'del.title' => '❌ Deleted ToD: %boss%',

    'reminder.start.title' => '⏰ Window opened: %boss%',
    'reminder.end.title' => '⚠️ Window closed: %boss%',
    'reminder.start.field' => 'Window start:',
    'reminder.end.field' => 'Window end: ',

    'remind.set.title' => '🔔 One-time reminder set: %boss%',

    'reminders.on' => '🔔 Channel reminders enabled.',
    'reminders.off' => '🔕 Channel reminders disabled.',
    'reminders.usage' => 'Usage: `.reminders on` or `.reminders off`',
];
