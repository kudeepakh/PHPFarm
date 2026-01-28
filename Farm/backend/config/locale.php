<?php

return [
    'default' => $_ENV['DEFAULT_LOCALE'] ?? 'en',
    'fallback' => $_ENV['FALLBACK_LOCALE'] ?? 'en',
    'supported' => array_filter(array_map('trim', explode(',', $_ENV['SUPPORTED_LOCALES'] ?? 'en'))),
    'header' => 'Accept-Language',
    'query_param' => 'lang',
];
