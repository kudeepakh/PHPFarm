<?php

namespace PHPFrarm\Modules\Storage\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Modules\Storage\Services\StorageService;

/**
 * Storage Controller
 * 
 * Handles file upload, download, and management operations.
 * Delegates business logic to StorageService.
 * 
 * @package PHPFrarm\Modules\Storage
 */
#[RouteGroup('/api/v1/storage', middleware: ['cors', 'rateLimit'])]
class StorageController
{
    private StorageService $storageService;

    public function __construct()
    {
        $this->storageService = new StorageService();
    }

    /**
     * Upload a file
     * 
     * POST /api/v1/storage/upload
     */
    #[Route('/upload', method: 'POST', middleware: ['auth'], description: 'Upload file')]
    public function upload(array $request): void
    {
        try {
            if (empty($_FILES['file'])) {
                Response::badRequest('storage.upload.no_file', ['error' => 'STORAGE_NO_FILE']);
                return;
            }

            $file = $_FILES['file'];
            $category = $_POST['category'] ?? 'media';
            $visibility = $_POST['visibility'] ?? 'private';

            $result = $this->storageService->uploadFile($file, $category, $visibility);

            Response::success($result, 'storage.upload.success', 201);

        } catch (\InvalidArgumentException $e) {
            Response::badRequest($e->getMessage());
        } catch (\Exception $e) {
            Logger::error('Storage upload failed', ['error' => $e->getMessage()]);
            Response::serverError('storage.upload.failed');
        }
    }

    /**
     * Generate pre-signed upload URL
     * 
     * POST /api/v1/storage/presigned-upload
     */
    #[Route('/presigned-upload', method: 'POST', middleware: ['auth', 'jsonParser'], description: 'Get pre-signed upload URL')]
    public function presignedUpload(array $request): void
    {
        try {
            $data = $request['body'] ?? [];

            $filename = $data['filename'] ?? null;
            if (!$filename) {
                Response::badRequest('storage.presigned.filename_required');
                return;
            }

            $category = $data['category'] ?? 'media';
            $contentType = $data['content_type'] ?? 'application/octet-stream';
            $expiration = (int)($data['expiration'] ?? 900);

            $result = $this->storageService->generatePresignedUploadUrl(
                $filename,
                $category,
                $contentType,
                $expiration
            );

            Response::success($result, 'storage.presigned_upload.success');

        } catch (\Exception $e) {
            Logger::error('Pre-signed URL generation failed', ['error' => $e->getMessage()]);
            Response::serverError('storage.presigned_upload.failed');
        }
    }

    /**
     * Generate pre-signed download URL
     * 
     * POST /api/v1/storage/presigned-download
     */
    #[Route('/presigned-download', method: 'POST', middleware: ['auth', 'jsonParser'], description: 'Get pre-signed download URL')]
    public function presignedDownload(array $request): void
    {
        try {
            $data = $request['body'] ?? [];

            $path = $data['path'] ?? null;
            if (!$path) {
                Response::badRequest('storage.path_required');
                return;
            }

            $category = $data['category'] ?? 'media';
            $expiration = (int)($data['expiration'] ?? 3600);

            $result = $this->storageService->generatePresignedDownloadUrl(
                $path,
                $category,
                $expiration
            );

            Response::success($result, 'storage.presigned_download.success');

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'storage.file_not_found') {
                Response::notFound('storage.file_not_found');
            } else {
                Logger::error('Pre-signed download URL generation failed', ['error' => $e->getMessage()]);
                Response::serverError('storage.presigned_download.failed');
            }
        } catch (\Exception $e) {
            Logger::error('Pre-signed download URL generation failed', ['error' => $e->getMessage()]);
            Response::serverError('storage.presigned_download.failed');
        }
    }

    /**
     * Delete a file
     * 
     * DELETE /api/v1/storage/{category}/{path}
     */
    #[Route('/{category}/{path}', method: 'DELETE', middleware: ['auth'], description: 'Delete file')]
    public function delete(array $request): void
    {
        try {
            $category = $request['params']['category'] ?? 'media';
            $path = $request['params']['path'] ?? '';

            if (!$path) {
                Response::badRequest('storage.path_required');
                return;
            }

            $this->storageService->deleteFile($path, $category);

            Response::success(['deleted' => true], 'storage.delete.success');

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'storage.file_not_found') {
                Response::notFound('storage.file_not_found');
            } else {
                Response::serverError('storage.delete.error');
            }
        } catch (\Exception $e) {
            Logger::error('Delete failed', ['error' => $e->getMessage()]);
            Response::serverError('storage.delete.error');
        }
    }

    /**
     * Get file metadata
     * 
     * GET /api/v1/storage/metadata/{category}/{path}
     */
    #[Route('/metadata/{category}/{path}', method: 'GET', middleware: ['auth'], description: 'Get file metadata')]
    public function metadata(array $request): void
    {
        try {
            $category = $request['params']['category'] ?? 'media';
            $path = $request['params']['path'] ?? '';

            if (!$path) {
                Response::badRequest('storage.path_required');
                return;
            }

            $categoryStorage = $this->storage->category($category);

            if (!$categoryStorage->exists($path)) {
                Response::notFound('storage.file_not_found');
                return;
            }

            $metadata = $categoryStorage->metadata($path);

            Response::success([
                'path' => $path,
                'category' => $category,
                'url' => $categoryStorage->url($path),
                ...$metadata,
            ]);

        } catch (\Exception $e) {
            Response::serverError('storage.metadata.failed');
        }
    }

    /**
     * List files in a category/directory
     * 
     * GET /api/v1/storage/list?category=media
     */
    #[Route('/list', method: 'GET', middleware: ['auth'], description: 'List files')]
    #[Route('/list/{category}', method: 'GET', middleware: ['auth'], description: 'List files by category')]
    public function listFiles(array $request): void
    {
        try {
            $category = $request['params']['category'] ?? $_GET['category'] ?? 'media';
            $directory = $_GET['directory'] ?? '';
            $recursive = filter_var($_GET['recursive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));

            $contents = $this->storageService->listFiles($category, $directory, $recursive);

            // Paginate
            $total = count($contents);
            $offset = ($page - 1) * $perPage;
            $paginatedContents = array_slice($contents, $offset, $perPage);

            Response::success([
                'category' => $category,
                'files' => $paginatedContents,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int)ceil($total / $perPage),
                ],
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list files', ['error' => $e->getMessage()]);
            Response::serverError('storage.list.failed');
        }
    }

    /**
     * Get storage configuration (for client)
     * 
     * GET /api/v1/storage/config
     */
    #[Route('/config', method: 'GET', middleware: ['auth'], description: 'Get storage config')]
    public function config(array $request): void
    {
        $safeConfig = $this->storageService->getClientConfig();
        Response::success($safeConfig);
    }

    /**
     * Get public storage configuration (no auth)
     *
     * GET /api/v1/storage/public-config
     */
    #[Route('/public-config', method: 'GET', middleware: ['cors'], description: 'Get public storage config')]
    public function publicConfig(array $request): void
    {
        $safeConfig = $this->storageService->getClientConfig();
        Response::success($safeConfig, 'storage.config.retrieved');
    }
}
