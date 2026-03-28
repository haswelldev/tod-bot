<?php

return [
    'help.unrecognized_time' => "Не вдалося розпізнати час. Приклади:\n"
        . ".tod icarus 14:30 Europe/Kyiv\n"
        . ".tod behemoth 1430 UTC+2\n"
        . ".tod catgang 2025-11-28 14:00 UTC\n"
        . ".tod shuriel now\n"
        . ".tod skylancer 30m ago",

    'common.last_tod' => 'Останній ToD',
    'common.death_time' => 'Час смерті',
    'common.window_start' => 'Початок вікна',
    'common.window_end' => 'Кінець вікна',
    'common.no_boss' => 'Немає ToD для **%boss%**.',
    'common.none_available' => 'Немає доступних босів.',

    'list.header' => 'Поточні ToD/вікна:',
    'list.opens_in' => '%boss% — відкривається через:',
    'list.closes_in' => '%boss% — закривається через:',

    'tod.title' => '💀 %boss% був успішно відпижджений.',
    'window.title' => '📅 Вікно респавну: %boss%',
    'del.title' => '❌ Видалено ToD: %boss%',

    'reminder.start.title' => '⏰ Вікно відкрилося: %boss%',
    'reminder.end.title' => '⚠️ Вікно закрилося: %boss%',
    'reminder.start.field' => 'Початок вікна:',
    'reminder.end.field' => 'Кінець вікна: ',

    'remind.set.title' => '🔔 Одноразове нагадування встановлено: %boss%',

    'reminders.on' => '🔔 Нагадування для каналу увімкнено.',
    'reminders.off' => '🔕 Нагадування для каналу вимкнено.',
    'reminders.usage' => 'Використання: `.reminders on` або `.reminders off`',
];
