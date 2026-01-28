<?php

/**
 * Storage Configuration
 * 
 * Configuration for blob/object storage providers.
 * Supports S3-compatible and native cloud storage systems.
 * 
 * @package PHPFrarm\Config
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default disk to use when no disk is specified.
    | Options: local, s3, azure, gcs, minio, r2, spaces, b2, wasabi
    |
    */
    'default' => $_ENV['STORAGE_DISK'] ?? 'local',

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    */
    'max_upload_size' => (int)($_ENV['STORAGE_MAX_UPLOAD_SIZE'] ?? 104857600), // 100MB

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    |
    | Configure multiple storage disks for different purposes:
    | - media: User uploads, images, videos
    | - documents: PDFs, spreadsheets, reports
    | - backups: Database dumps, system backups
    | - temp: Temporary files, exports
    |
    */
    'disks' => [
        // Local filesystem storage (development)
        'local' => [
            'driver' => 'local',
            'root' => $_ENV['STORAGE_LOCAL_ROOT'] ?? '/var/www/html/storage',
            'url' => $_ENV['STORAGE_LOCAL_URL'] ?? '/storage',
            'visibility' => 'private',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],

        // Amazon S3
        's3' => [
            'driver' => 's3',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['AWS_BUCKET'] ?? null,
            'endpoint' => $_ENV['AWS_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => (bool)($_ENV['AWS_USE_PATH_STYLE'] ?? false),
            'visibility' => 'private',
            'options' => [
                'CacheControl' => 'max-age=31536000',
            ],
        ],

        // MinIO (S3-compatible, local/on-prem)
        'minio' => [
            'driver' => 's3',
            'key' => $_ENV['MINIO_ACCESS_KEY'] ?? 'minioadmin',
            'secret' => $_ENV['MINIO_SECRET_KEY'] ?? 'minioadmin',
            'region' => $_ENV['MINIO_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['MINIO_BUCKET'] ?? 'phpfrarm',
            'endpoint' => $_ENV['MINIO_ENDPOINT'] ?? 'http://minio:9000',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],

        // Azure Blob Storage
        'azure' => [
            'driver' => 'azure',
            'account_name' => $_ENV['AZURE_STORAGE_ACCOUNT'] ?? null,
            'account_key' => $_ENV['AZURE_STORAGE_KEY'] ?? null,
            'container' => $_ENV['AZURE_STORAGE_CONTAINER'] ?? 'phpfrarm',
            'endpoint' => $_ENV['AZURE_STORAGE_ENDPOINT'] ?? null,
            'visibility' => 'private',
        ],

        // Google Cloud Storage
        'gcs' => [
            'driver' => 'gcs',
            'project_id' => $_ENV['GCS_PROJECT_ID'] ?? null,
            'key_file' => $_ENV['GCS_KEY_FILE'] ?? null,
            'bucket' => $_ENV['GCS_BUCKET'] ?? null,
            'visibility' => 'private',
        ],

        // Cloudflare R2 (S3-compatible, edge-friendly)
        'r2' => [
            'driver' => 's3',
            'key' => $_ENV['R2_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['R2_SECRET_ACCESS_KEY'] ?? null,
            'region' => 'auto',
            'bucket' => $_ENV['R2_BUCKET'] ?? null,
            'endpoint' => $_ENV['R2_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],

        // DigitalOcean Spaces (S3-compatible)
        'spaces' => [
            'driver' => 's3',
            'key' => $_ENV['DO_SPACES_KEY'] ?? null,
            'secret' => $_ENV['DO_SPACES_SECRET'] ?? null,
            'region' => $_ENV['DO_SPACES_REGION'] ?? 'nyc3',
            'bucket' => $_ENV['DO_SPACES_BUCKET'] ?? null,
            'endpoint' => $_ENV['DO_SPACES_ENDPOINT'] ?? 'https://nyc3.digitaloceanspaces.com',
            'visibility' => 'private',
        ],

        // Backblaze B2 (S3-compatible, cost-optimized)
        'b2' => [
            'driver' => 's3',
            'key' => $_ENV['B2_KEY_ID'] ?? null,
            'secret' => $_ENV['B2_APPLICATION_KEY'] ?? null,
            'region' => $_ENV['B2_REGION'] ?? 'us-west-002',
            'bucket' => $_ENV['B2_BUCKET'] ?? null,
            'endpoint' => $_ENV['B2_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],

        // Wasabi (S3-compatible, cost-optimized)
        'wasabi' => [
            'driver' => 's3',
            'key' => $_ENV['WASABI_ACCESS_KEY'] ?? null,
            'secret' => $_ENV['WASABI_SECRET_KEY'] ?? null,
            'region' => $_ENV['WASABI_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['WASABI_BUCKET'] ?? null,
            'endpoint' => $_ENV['WASABI_ENDPOINT'] ?? 'https://s3.wasabisys.com',
            'visibility' => 'private',
        ],

        // Oracle Object Storage (S3-compatible)
        'oracle' => [
            'driver' => 's3',
            'key' => $_ENV['OCI_ACCESS_KEY'] ?? null,
            'secret' => $_ENV['OCI_SECRET_KEY'] ?? null,
            'region' => $_ENV['OCI_REGION'] ?? null,
            'bucket' => $_ENV['OCI_BUCKET'] ?? null,
            'endpoint' => $_ENV['OCI_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],

        // Alibaba Cloud OSS (S3-compatible)
        'oss' => [
            'driver' => 's3',
            'key' => $_ENV['ALIYUN_ACCESS_KEY'] ?? null,
            'secret' => $_ENV['ALIYUN_SECRET_KEY'] ?? null,
            'region' => $_ENV['ALIYUN_REGION'] ?? 'oss-cn-hangzhou',
            'bucket' => $_ENV['ALIYUN_BUCKET'] ?? null,
            'endpoint' => $_ENV['ALIYUN_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Categories
    |--------------------------------------------------------------------------
    |
    | Map storage categories to specific disks and paths.
    |
    */
    'categories' => [
        'media' => [
            'disk' => $_ENV['STORAGE_MEDIA_DISK'] ?? 'default',
            'path' => 'media',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'],
            'max_size' => 52428800, // 50MB
        ],
        'documents' => [
            'disk' => $_ENV['STORAGE_DOCUMENTS_DISK'] ?? 'default',
            'path' => 'documents',
            'allowed_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
            'max_size' => 26214400, // 25MB
        ],
        'backups' => [
            'disk' => $_ENV['STORAGE_BACKUPS_DISK'] ?? 'default',
            'path' => 'backups',
            'allowed_types' => ['application/gzip', 'application/zip', 'application/x-tar'],
            'allowed_extensions' => ['gz', 'zip', 'tar'],
            'max_size' => 5368709120, // 5GB
        ],
        'temp' => [
            'disk' => $_ENV['STORAGE_TEMP_DISK'] ?? 'local',
            'path' => 'temp',
            'allowed_types' => ['*/*'],
            'allowed_extensions' => [],
            'max_size' => 104857600, // 100MB
            'ttl' => 86400, // 24 hours
        ],
        'exports' => [
            'disk' => $_ENV['STORAGE_EXPORTS_DISK'] ?? 'default',
            'path' => 'exports',
            'allowed_types' => ['application/zip', 'text/csv', 'application/json'],
            'allowed_extensions' => ['zip', 'csv', 'json'],
            'max_size' => 1073741824, // 1GB
            'ttl' => 604800, // 7 days
        ],
        'avatars' => [
            'disk' => $_ENV['STORAGE_AVATARS_DISK'] ?? 'default',
            'path' => 'avatars',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size' => 5242880, // 5MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed URL Configuration
    |--------------------------------------------------------------------------
    */
    'signed_urls' => [
        'enabled' => (bool)($_ENV['STORAGE_SIGNED_URLS'] ?? true),
        'expiry' => (int)($_ENV['STORAGE_SIGNED_URL_EXPIRY'] ?? 3600), // 1 hour
        'upload_expiry' => (int)($_ENV['STORAGE_UPLOAD_URL_EXPIRY'] ?? 900), // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Rules
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        'temp_cleanup_days' => (int)($_ENV['STORAGE_TEMP_CLEANUP_DAYS'] ?? 1),
        'export_cleanup_days' => (int)($_ENV['STORAGE_EXPORT_CLEANUP_DAYS'] ?? 7),
        'backup_retention_days' => (int)($_ENV['STORAGE_BACKUP_RETENTION_DAYS'] ?? 30),
        'archive_after_days' => (int)($_ENV['STORAGE_ARCHIVE_AFTER_DAYS'] ?? 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encryption_at_rest' => (bool)($_ENV['STORAGE_ENCRYPTION_AT_REST'] ?? true),
        'versioning' => (bool)($_ENV['STORAGE_VERSIONING'] ?? false),
        'scan_uploads' => (bool)($_ENV['STORAGE_SCAN_UPLOADS'] ?? true),
        'blocked_extensions' => ['exe', 'php', 'phar', 'sh', 'bat', 'cmd', 'ps1', 'vbs', 'js', 'jar'],
    ],
];
