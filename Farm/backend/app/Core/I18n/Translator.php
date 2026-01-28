<?php

declare(strict_types=1);

namespace PHPFrarm\Core\I18n;

class Translator
{
    private static array $cache = [];

    public static function setLocale(string $locale): void
    {
        LocaleContext::setLocale($locale);
    }

    public static function getLocale(): string
    {
        return LocaleContext::getLocale();
    }

    public static function translate(string $key, array $params = [], ?string $fallback = null): string
    {
        $locale = self::getLocale();
        $fallbackLocale = self::getConfig()['fallback'];

        $message = self::getMessage($locale, $key)
            ?? self::getMessage($fallbackLocale, $key)
            ?? $fallback
            ?? $key;

        return self::interpolate($message, $params);
    }

    public static function has(string $key): bool
    {
        $locale = self::getLocale();
        $fallbackLocale = self::getConfig()['fallback'];

        return self::getMessage($locale, $key) !== null
            || self::getMessage($fallbackLocale, $key) !== null;
    }

    public static function detectLocale(array $headers = [], ?string $userLocale = null): string
    {
        $config = self::getConfig();
        $supported = $config['supported'];

        if ($userLocale && in_array($userLocale, $supported, true)) {
            return $userLocale;
        }

        $acceptLanguage = $headers[$config['header']] ?? $headers[strtolower($config['header'])] ?? null;
        if ($acceptLanguage) {
            $locale = self::parseAcceptLanguage($acceptLanguage, $supported);
            if ($locale !== null) {
                return $locale;
            }
        }

        return $config['default'];
    }

    private static function getMessage(string $locale, string $key): ?string
    {
        $messages = self::loadLocale($locale);
        return $messages[$key] ?? null;
    }

    private static function loadLocale(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $coreMessages = self::loadCoreMessages($locale);
        $moduleMessages = self::loadModuleMessages($locale);
        $messages = array_merge($coreMessages, $moduleMessages);

        self::$cache[$locale] = $messages;
        return self::$cache[$locale];
    }

    private static function loadCoreMessages(string $locale): array
    {
        $corePath = dirname(__DIR__, 2) . '/lang/' . $locale . '/messages.php';
        if (!file_exists($corePath)) {
            return [];
        }

        $messages = require $corePath;
        return is_array($messages) ? $messages : [];
    }

    private static function loadModuleMessages(string $locale): array
    {
        $modulesPath = dirname(__DIR__, 3) . '/modules';
        if (!is_dir($modulesPath)) {
            return [];
        }

        $messages = [];
        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $moduleDir) {
            $moduleLang = $moduleDir . '/lang/' . $locale . '/messages.php';
            if (!file_exists($moduleLang)) {
                continue;
            }

            $moduleMessages = require $moduleLang;
            if (is_array($moduleMessages)) {
                $messages = array_merge($messages, $moduleMessages);
            }
        }

        return $messages;
    }

    private static function interpolate(string $message, array $params): string
    {
        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', (string)$value, $message);
        }

        return $message;
    }

    private static function parseAcceptLanguage(string $header, array $supported): ?string
    {
        $langs = array_map('trim', explode(',', $header));
        foreach ($langs as $lang) {
            $parts = explode(';', $lang);
            $code = strtolower(trim($parts[0]));
            $primary = explode('-', $code)[0];

            if (in_array($code, $supported, true)) {
                return $code;
            }

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return null;
    }

    private static function getConfig(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = require dirname(__DIR__, 3) . '/config/locale.php';
        $config['supported'] = $config['supported'] ?: [$config['default']];
        return $config;
    }
}
