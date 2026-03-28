<?php

return [
    'help.unrecognized_time' => "Não foi possível reconhecer a hora. Exemplos:\n"
        . ".tod icarus 14:30 Europe/Kyiv\n"
        . ".tod behemoth 1430 UTC+2\n"
        . ".tod catgang 2025-11-28 14:00 UTC\n"
        . ".tod shuriel now\n"
        . ".tod skylancer 30m ago",

    'common.last_tod' => 'Último ToD',
    'common.death_time' => 'Hora da morte',
    'common.window_start' => 'Início da janela',
    'common.window_end' => 'Fim da janela',
    'common.no_boss' => 'Sem ToD para **%boss%**.',
    'common.none_available' => 'Nenhum boss disponível.',

    'list.header' => 'ToDs/janelas atuais:',
    'list.opens_in' => '%boss% — abre em:',
    'list.closes_in' => '%boss% — fecha em:',

    'tod.title' => '💀 %boss% foi morto.',
    'window.title' => '📅 Janela de respawn: %boss%',
    'del.title' => '❌ ToD apagado: %boss%',

    'reminder.start.title' => '⏰ Janela aberta: %boss%',
    'reminder.end.title' => '⚠️ Janela fechada: %boss%',
    'reminder.start.field' => 'Início da janela:',
    'reminder.end.field' => 'Fim da janela: ',

    'remind.set.title' => '🔔 Lembrete único definido: %boss%',

    'reminders.on' => '🔔 Lembretes do canal ativados.',
    'reminders.off' => '🔕 Lembretes do canal desativados.',
    'reminders.usage' => 'Uso: `.reminders on` ou `.reminders off`',
];
