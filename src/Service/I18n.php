<?php

namespace TodBot\Service;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class I18n
{
    private static ?Translator $translator = null;
    private static ?string $overrideLocale = null;

    /**
     * Temporarily override the active locale (e.g. per-channel locale).
     * Pass null to revert to the translator's default locale.
     */
    public static function setLocale(?string $locale): void
    {
        self::$overrideLocale = $locale;
    }

    public static function translator(): Translator
    {
        if (self::$translator !== null) {
            return self::$translator;
        }

        $locale = getenv('BOT_LOCALE');
        if (!$locale) {
            $locale = 'en';
        }

        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());

        $baseDir = dirname(__DIR__, 2) . '/translations';
        // Register resources for available locales from PHP array files
        if (is_dir($baseDir)) {
            $ru = $baseDir . '/messages.ru.php';
            if (file_exists($ru)) {
                /** @var array $data */
                $data = include $ru;
                if (is_array($data)) {
                    $translator->addResource('array', $data, 'ru');
                }
            }
            $en = $baseDir . '/messages.en.php';
            if (file_exists($en)) {
                /** @var array $dataEn */
                $dataEn = include $en;
                if (is_array($dataEn)) {
                    $translator->addResource('array', $dataEn, 'en');
                }
            }
            $fr = $baseDir . '/messages.fr.php';
            if (file_exists($fr)) {
                $dataFr = include $fr;
                if (is_array($dataFr)) {
                    $translator->addResource('array', $dataFr, 'fr');
                }
            }
            $el = $baseDir . '/messages.el.php';
            if (file_exists($el)) {
                $dataEl = include $el;
                if (is_array($dataEl)) {
                    $translator->addResource('array', $dataEl, 'el');
                }
            }
            $pt = $baseDir . '/messages.pt.php';
            if (file_exists($pt)) {
                $dataPt = include $pt;
                if (is_array($dataPt)) {
                    $translator->addResource('array', $dataPt, 'pt');
                }
            }
            $uk = $baseDir . '/messages.uk.php';
            if (file_exists($uk)) {
                $dataUk = include $uk;
                if (is_array($dataUk)) {
                    $translator->addResource('array', $dataUk, 'uk');
                }
            }
        }

        self::$translator = $translator;
        return self::$translator;
    }

    public static function t($key, array $params = []): string
    {
        return self::translator()->trans($key, $params, null, self::$overrideLocale);
    }
}
