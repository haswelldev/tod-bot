<?php

return [
    'help.unrecognized_time' => "Δεν ήταν δυνατή η αναγνώριση της ώρας. Παραδείγματα:\n"
        . ".tod icarus 14:30 Europe/Kyiv\n"
        . ".tod behemoth 1430 UTC+2\n"
        . ".tod catgang 2025-11-28 14:00 UTC\n"
        . ".tod shuriel now\n"
        . ".tod skylancer 30m ago",

    'common.last_tod' => 'Τελευταίο ToD',
    'common.death_time' => 'Ώρα θανάτου',
    'common.window_start' => 'Έναρξη παραθύρου',
    'common.window_end' => 'Λήξη παραθύρου',
    'common.no_boss' => 'Δεν υπάρχει ToD για **%boss%**.',
    'common.none_available' => 'Δεν υπάρχουν διαθέσιμα bosses.',

    'list.header' => 'Τρέχοντα ToD/παράθυρα:',
    'list.opens_in' => '%boss% — ανοίγει σε:',
    'list.closes_in' => '%boss% — κλείνει σε:',

    'tod.title' => '💀 Ο %boss% σκοτώθηκε.',
    'window.title' => '📅 Παράθυρο respawn: %boss%',
    'del.title' => '❌ Διαγράφηκε το ToD: %boss%',

    'reminder.start.title' => '⏰ Το παράθυρο άνοιξε: %boss%',
    'reminder.end.title' => '⚠️ Το παράθυρο έκλεισε: %boss%',
    'reminder.start.field' => 'Έναρξη παραθύρου:',
    'reminder.end.field' => 'Λήξη παραθύρου: ',

    'remind.set.title' => '🔔 Εφάπαξ υπενθύμιση ορίστηκε: %boss%',

    'reminders.on' => '🔔 Υπενθυμίσεις καναλιού ενεργοποιήθηκαν.',
    'reminders.off' => '🔕 Υπενθυμίσεις καναλιού απενεργοποιήθηκαν.',
    'reminders.usage' => 'Χρήση: `.reminders on` ή `.reminders off`',
];
