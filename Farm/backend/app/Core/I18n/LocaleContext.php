<?php

declare(strict_types=1);

namespace PHPFrarm\Core\I18n;

class LocaleContext
{
    private static string $locale = 'en';

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }
}
