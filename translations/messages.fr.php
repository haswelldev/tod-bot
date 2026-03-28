<?php

return [
    'help.unrecognized_time' => "Heure non reconnue. Exemples:\n"
        . ".tod icarus 14:30 Europe/Kyiv\n"
        . ".tod behemoth 1430 UTC+2\n"
        . ".tod catgang 2025-11-28 14:00 UTC\n"
        . ".tod shuriel now\n"
        . ".tod skylancer 30m ago",

    'common.last_tod' => 'Dernier ToD',
    'common.death_time' => 'Heure de mort',
    'common.window_start' => "Début de la fenêtre",
    'common.window_end' => "Fin de la fenêtre",
    'common.no_boss' => 'Aucun ToD pour **%boss%**.',
    'common.none_available' => 'Aucun boss disponible.',

    'list.header' => 'ToD/fenêtres actuels:',
    'list.opens_in' => '%boss% — ouvre dans :',
    'list.closes_in' => '%boss% — se termine dans :',

    'tod.title' => '💀 %boss% a été tué.',
    'window.title' => '📅 Fenêtre de respawn : %boss%',
    'del.title' => '❌ ToD supprimé : %boss%',

    'reminder.start.title' => '⏰ Fenêtre ouverte : %boss%',
    'reminder.end.title' => '⚠️ Fenêtre fermée : %boss%',
    'reminder.start.field' => 'Début de la fenêtre :',
    'reminder.end.field' => 'Fin de la fenêtre : ',

    'remind.set.title' => '🔔 Rappel unique défini : %boss%',

    'reminders.on' => '🔔 Rappels du canal activés.',
    'reminders.off' => '🔕 Rappels du canal désactivés.',
    'reminders.usage' => 'Utilisation : `.reminders on` ou `.reminders off`',
];
