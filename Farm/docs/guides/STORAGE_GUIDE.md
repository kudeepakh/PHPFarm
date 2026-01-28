# 📦 Storage Module - Blob/Object Storage Guide

> **Multi-provider storage abstraction for PHPFrarm Framework**

## Overview

The Storage module provides a unified interface for file storage operations across multiple cloud providers and local filesystems. It supports S3-compatible storage (AWS S3, MinIO, Cloudflare R2, etc.), Azure Blob Storage, Google Cloud Storage, and local filesystem.

---

## 🌐 Supported Providers

### Cloud Object Storage

| Provider | Driver | S3-Compatible |
|----------|--------|---------------|
| Amazon S3 | `s3` | ✅ Native |
| Google Cloud Storage | `gcs` | ✅ Interop |
| Azure Blob Storage | `azure` | ❌ Native |
| Oracle Object Storage | `s3` | ✅ |
| IBM Cloud Object Storage | `s3` | ✅ |

### S3-Compatible Storage

| Provider | Driver | Best For |
|----------|--------|----------|
| MinIO | `s3` | Local dev, On-prem |
| Cloudflare R2 | `s3` | Edge, Zero egress |
| DigitalOcean Spaces | `s3` | Simple cloud |
| Backblaze B2 | `s3` | Cost-optimized |
| Wasabi | `s3` | High-performance |
| Scaleway | `s3` | EU compliance |

### On-Premises

| Provider | Driver | Best For |
|----------|--------|----------|
| MinIO | `s3` | Self-hosted S3 |
| Local Filesystem | `local` | Development |

---

## ⚙️ Configuration

### Environment Variables

```env
# Default disk
STORAGE_DISK=local

# AWS S3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket

# MinIO (local development)
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_ENDPOINT=http://minio:9000
MINIO_BUCKET=phpfrarm

# Azure Blob
AZURE_STORAGE_ACCOUNT=your-account
AZURE_STORAGE_KEY=your-key
AZURE_STORAGE_CONTAINER=phpfrarm

# Cloudflare R2
R2_ACCESS_KEY_ID=your-key
R2_SECRET_ACCESS_KEY=your-secret
R2_BUCKET=your-bucket
R2_ENDPOINT=https://xxx.r2.cloudflarestorage.com
```

### Storage Categories

Pre-configured categories with validation rules:

```php
// config/storage.php
'categories' => [
    'media' => [
        'allowed_types' => ['image/jpeg', 'image/png', 'video/mp4'],
        'max_size' => 52428800, // 50MB
    ],
    'documents' => [
        'allowed_types' => ['application/pdf', 'application/msword'],
        'max_size' => 26214400, // 25MB
    ],
    'avatars' => [
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_size' => 5242880, // 5MB
    ],
]
```

---

## 🚀 Usage

### Basic Usage

```php
use App\Core\Storage\StorageManager;

$storage = StorageManager::getInstance();

// Use default disk
$storage->disk()->put('path/to/file.txt', 'contents');

// Use specific disk
$storage->disk('s3')->put('path/to/file.txt', 'contents');

// Use category (with validation)
$storage->media()->store($_FILES['upload']);
```

### Upload Files

```php
// Direct upload
$path = $storage->media()->store($_FILES['image']);
// Returns: "2026/01/18/abc123def456.jpg"

// Upload with custom path
$storage->documents()->putUploadedFile(
    'reports/quarterly.pdf',
    $_FILES['report']
);

// Upload with options
$storage->media()->put('custom/path.jpg', $contents, [
    'visibility' => 'public',
    'content_type' => 'image/jpeg',
    'metadata' => ['author' => 'John'],
]);
```

### Download/Read Files

```php
// Get file contents
$contents = $storage->media()->get('path/to/file.jpg');

// Get file stream (for large files)
$stream = $storage->media()->getStream('path/to/video.mp4');

// Check if exists
if ($storage->media()->exists('path/to/file.jpg')) {
    // File exists
}
```

### Pre-signed URLs

```php
// Download URL (1 hour expiry)
$downloadUrl = $storage->media()->temporaryUrl('path/to/file.jpg', 3600);

// Upload URL (15 minutes expiry)
$uploadData = $storage->media()->temporaryUploadUrl('photo.jpg', 900, [
    'content_type' => 'image/jpeg',
]);
// Returns: ['url' => '...', 'headers' => [...], 'path' => '...']
```

### File Operations

```php
// Delete
$storage->media()->delete('path/to/file.jpg');

// Copy
$storage->media()->copy('source.jpg', 'destination.jpg');

// Move
$storage->media()->move('old/path.jpg', 'new/path.jpg');

// Get metadata
$metadata = $storage->media()->metadata('path/to/file.jpg');
// Returns: ['size', 'mime_type', 'last_modified', 'etag']

// List files
$files = $storage->media()->listContents('uploads/', recursive: true);
```

---

## 🔌 API Endpoints

### Upload File

```http
POST /api/v1/storage/upload
Content-Type: multipart/form-data
Authorization: Bearer {token}

file: (binary)
category: media
visibility: private
```

**Response:**
```json
{
    "success": true,
    "data": {
        "path": "2026/01/18/abc123def456.jpg",
        "url": "https://storage.example.com/media/2026/01/18/abc123def456.jpg",
        "size": 1024000,
        "mime_type": "image/jpeg",
        "original_name": "photo.jpg"
    }
}
```

### Get Pre-signed Upload URL

```http
POST /api/v1/storage/presigned-upload
Content-Type: application/json
Authorization: Bearer {token}

{
    "filename": "document.pdf",
    "category": "documents",
    "content_type": "application/pdf",
    "expiration": 900
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "upload_url": "https://s3.amazonaws.com/bucket/...",
        "method": "PUT",
        "headers": {
            "Content-Type": "application/pdf"
        },
        "path": "documents/2026/01/18/xyz789.pdf",
        "expires_in": 900
    }
}
```

### Get Pre-signed Download URL

```http
POST /api/v1/storage/presigned-download
Content-Type: application/json
Authorization: Bearer {token}

{
    "path": "2026/01/18/abc123.jpg",
    "category": "media",
    "expiration": 3600
}
```

### List Files

```http
GET /api/v1/storage/{category}/list?directory=uploads&recursive=false&page=1&per_page=50
Authorization: Bearer {token}
```

### Delete File

```http
DELETE /api/v1/storage/{category}/{path}
Authorization: Bearer {token}
```

---

## 🛡️ Security Features

### Blocked Extensions

```php
'security' => [
    'blocked_extensions' => [
        'exe', 'php', 'phar', 'sh', 'bat', 'cmd', 'ps1', 'vbs', 'js', 'jar'
    ],
]
```

### File Validation

- MIME type validation per category
- File size limits per category
- Extension blocking
- Optional virus scanning integration

### Access Control

- Private files require authentication
- Signed URLs for temporary access
- User-level storage quotas
- Audit logging for all operations

---

## 📊 Storage Quotas

```php
// Get user quota
$quota = $storage->getUserQuota($userId);
// Returns: ['quota_bytes', 'used_bytes', 'available_bytes', 'usage_percent']

// Check before upload
if ($file['size'] > $quota['available_bytes']) {
    throw new QuotaExceededException();
}
```

---

## 🔄 Lifecycle Management

### Temporary Files

```env
STORAGE_TEMP_CLEANUP_DAYS=1
STORAGE_EXPORT_CLEANUP_DAYS=7
```

### Archive Policy

```env
STORAGE_ARCHIVE_AFTER_DAYS=90
STORAGE_BACKUP_RETENTION_DAYS=30
```

### Cleanup Command

```php
// Run via cron
php artisan storage:cleanup
```

---

## 🏗️ Architecture

```
Storage Module
├── StorageManager (Singleton)
│   ├── disk(name) → StorageDriverInterface
│   └── category(name) → CategoryStorage
│
├── Drivers
│   ├── LocalDriver (Filesystem)
│   ├── S3Driver (AWS, MinIO, R2, etc.)
│   └── AzureDriver (Azure Blob)
│
├── CategoryStorage
│   ├── Validation (types, size, extensions)
│   ├── Path prefixing
│   └── Auto-naming
│
└── Database
    ├── files (metadata tracking)
    ├── file_access_logs (audit)
    ├── file_shares (sharing)
    └── storage_quotas (limits)
```

---

## 📦 Adding New Providers

To add a new storage provider:

1. **Create Driver** implementing `StorageDriverInterface`
2. **Add Config** in `config/storage.php`
3. **Register** in `StorageManager::createDisk()`

```php
// Example: Adding Supabase Storage
class SupabaseDriver implements StorageDriverInterface
{
    // Implement all interface methods
}
```

---

## 🔧 Recommended Stack

| Use Case | Provider |
|----------|----------|
| Production (Cloud) | AWS S3 / Azure Blob |
| Development | MinIO / Local |
| Cost-optimized | Wasabi / Backblaze B2 |
| Edge/CDN | Cloudflare R2 |
| On-premises | MinIO / Ceph |

---

## 📝 Best Practices

1. **Use categories** for different file types
2. **Use pre-signed URLs** for client-side uploads
3. **Set appropriate quotas** per user
4. **Enable lifecycle rules** for cleanup
5. **Log all file access** for audit
6. **Validate file types** on upload
7. **Use private visibility** by default
