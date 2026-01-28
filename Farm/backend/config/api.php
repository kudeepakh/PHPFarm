<?php

/**
 * API Configuration
 *
 * Central configuration for API versioning and lifecycle.
 */

$supportedVersions = array_filter(array_map('trim', explode(',', env('API_SUPPORTED_VERSIONS', 'v1,v2'))));

$deprecatedVersions = [];
$deprecatedRaw = trim((string) env('API_DEPRECATED_VERSIONS', ''));
if ($deprecatedRaw !== '') {
    foreach (explode(',', $deprecatedRaw) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        [$version, $date] = array_pad(explode(':', $entry, 2), 2, '');
        $version = trim($version);
        $date = trim($date);
        if ($version !== '') {
            $deprecatedVersions[$version] = $date;
        }
    }
}

return [
    'versioning' => [
        // Order matters: first entry is default version
        'supported_versions' => $supportedVersions ?: ['v1'],

        // Map of version => sunset date (YYYY-MM-DD). Example: ['v1' => '2026-12-31']
        'deprecated_versions' => $deprecatedVersions,
    ],
];
