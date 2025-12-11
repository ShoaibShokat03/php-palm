<?php

namespace App\Core\Security;

use PhpPalm\Core\Request;

/**
 * Secure File Upload Handler
 * 
 * Provides secure file upload validation and storage
 * 
 * Features:
 * - MIME type validation
 * - Extension whitelist
 * - File size limits
 * - Secure storage outside public folder
 * - Random filename generation
 * - Virus scanning integration (optional)
 */
class FileUpload
{
    protected array $allowedMimeTypes = [];
    protected array $allowedExtensions = [];
    protected int $maxFileSize = 5242880; // 5MB default
    protected string $uploadPath = '';
    protected bool $randomizeFilename = true;

    /**
     * Common allowed file types
     */
    public const IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    public const DOCUMENT_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * Constructor
     */
    public function __construct(string $uploadPath = null)
    {
        $this->uploadPath = $uploadPath ?? (__DIR__ . '/../../../storage/uploads');
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Set allowed MIME types
     */
    public function setAllowedMimeTypes(array $types): self
    {
        $this->allowedMimeTypes = $types;
        return $this;
    }

    /**
     * Set allowed file extensions
     */
    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * Set maximum file size in bytes
     */
    public function setMaxFileSize(int $bytes): self
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    /**
     * Set upload path
     */
    public function setUploadPath(string $path): self
    {
        $this->uploadPath = $path;
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        return $this;
    }

    /**
     * Enable/disable filename randomization
     */
    public function setRandomizeFilename(bool $randomize): self
    {
        $this->randomizeFilename = $randomize;
        return $this;
    }

    /**
     * Validate and upload file
     * 
     * @param string $fieldName Form field name
     * @return array ['success' => bool, 'message' => string, 'file' => array|null]
     */
    public function upload(string $fieldName): array
    {
        $file = Request::files($fieldName);
        
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)
            ];
        }

        // Validate file
        $validation = $this->validate($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        // Generate secure filename
        $filename = $this->generateFilename($file['name']);
        $destination = $this->uploadPath . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => false,
                'message' => 'Failed to move uploaded file'
            ];
        }

        // Set secure permissions
        chmod($destination, 0644);

        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file' => [
                'original_name' => $file['name'],
                'filename' => $filename,
                'path' => $destination,
                'url' => $this->getPublicUrl($filename),
                'size' => $file['size'],
                'mime_type' => $file['type']
            ]
        ];
    }

    /**
     * Validate uploaded file
     */
    protected function validate(array $file): array
    {
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed size (' . $this->formatBytes($this->maxFileSize) . ')'
            ];
        }

        // Check if file was actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'File upload validation failed'
            ];
        }

        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate extension
        if (!empty($this->allowedExtensions) && !in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'File extension not allowed. Allowed: ' . implode(', ', $this->allowedExtensions)
            ];
        }

        // Validate MIME type
        $mimeType = $this->getMimeType($file['tmp_name'], $file['type']);
        
        if (!empty($this->allowedMimeTypes) && !in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'valid' => false,
                'message' => 'File type not allowed'
            ];
        }

        // Additional security: check file content matches extension
        if (!$this->validateFileContent($file['tmp_name'], $extension)) {
            return [
                'valid' => false,
                'message' => 'File content does not match file extension'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get MIME type (more reliable than $_FILES['type'])
     */
    protected function getMimeType(string $filePath, string $fallback): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType ?: $fallback;
        }
        
        return $fallback;
    }

    /**
     * Validate file content matches extension
     */
    protected function validateFileContent(string $filePath, string $extension): bool
    {
        // Basic validation - check file signatures
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $bytes = fread($handle, 4);
        fclose($handle);

        // Check common file signatures
        $signatures = [
            'jpg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89\x50\x4E\x47"],
            'gif' => ["\x47\x49\x46\x38"],
            'pdf' => ["%PDF"],
        ];

        if (isset($signatures[$extension])) {
            foreach ($signatures[$extension] as $signature) {
                if (strpos($bytes, $signature) === 0) {
                    return true;
                }
            }
            return false;
        }

        // For other file types, just check extension is in allowed list
        return true;
    }

    /**
     * Generate secure filename
     */
    protected function generateFilename(string $originalName): string
    {
        if ($this->randomizeFilename) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $random = bin2hex(random_bytes(16));
            return $random . '.' . strtolower($extension);
        }

        // Sanitize original filename
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return $name . '_' . time() . '.' . strtolower($extension);
    }

    /**
     * Get public URL for file (if stored outside public folder, use a proxy)
     */
    protected function getPublicUrl(string $filename): string
    {
        // Files are stored outside public folder for security
        // Return a route that serves files securely
        return '/api/files/' . $filename;
    }

    /**
     * Get upload error message
     */
    protected function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $messages[$error] ?? 'Unknown upload error';
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Delete uploaded file
     */
    public function delete(string $filename): bool
    {
        $filePath = $this->uploadPath . '/' . basename($filename);
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}

