<?php

namespace Frontend\Palm;

/**
 * PalmCache provides a high-level API for inspecting and clearing the compiled
 * Palm view cache (storage/cache/palm) and the compiled component cache
 * (public/palm-assets/compiled).
 */
class PalmCache
{
    protected string $rootPath;
    protected string $palmCacheDir;
    protected string $componentCacheDir;
    protected array $protectedFiles = ['.gitignore', 'index.html'];

    public function __construct(?string $rootPath = null)
    {
        if ($rootPath === null) {
            $rootPath = defined('PALM_ROOT') ? PALM_ROOT : dirname(__DIR__, 2);
        }

        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->palmCacheDir = $this->rootPath . '/storage/cache/palm';
        $this->componentCacheDir = $this->rootPath . '/public/palm-assets/compiled';

        $this->ensureDirectory($this->palmCacheDir);
        $this->ensureDirectory($this->componentCacheDir);
    }

    /**
     * Return summary statistics for both cache buckets.
     */
    public function summary(): array
    {
        $palmStats = $this->buildStats($this->palmCacheDir, ['cache', 'php']);
        $componentStats = $this->buildStats($this->componentCacheDir, ['js', 'meta']);

        $totalSize = $palmStats['total_size'] + $componentStats['total_size'];
        $lastModified = max($palmStats['last_modified_raw'], $componentStats['last_modified_raw']);

        return [
            'palm' => $palmStats,
            'components' => $componentStats,
            'total' => [
                'file_count' => $palmStats['file_count'] + $componentStats['file_count'],
                'total_size' => $totalSize,
                'human_size' => $this->formatBytes($totalSize),
                'last_modified' => $this->formatTimestamp($lastModified),
            ],
        ];
    }

    /**
     * Clear cache directories.
     */
    public function clear(?string $target = null): array
    {
        $target = $this->normalizeTarget($target);
        $removedFiles = 0;
        $actions = [];

        if ($target === 'all' || $target === 'views' || $target === 'palm') {
            $removedFiles += $this->wipeDirectory($this->palmCacheDir, ['cache', 'php']);
            $actions[] = 'palm';
        }

        if ($target === 'all' || $target === 'components') {
            $removedFiles += $this->wipeDirectory($this->componentCacheDir, ['js', 'meta']);
            $actions[] = 'components';
        }

        $message = $this->buildMessage($target, $removedFiles);

        return [
            'status' => 'success',
            'target' => $target,
            'actions' => $actions,
            'removed_files' => $removedFiles,
            'message' => $message,
            'summary' => $this->summary(),
            'recent_files' => $this->recentFiles(),
        ];
    }

    /**
     * List the most recently updated cache entries.
     */
    public function recentFiles(int $limit = 10): array
    {
        $files = array_merge(
            $this->collectFileMetadata($this->palmCacheDir, 'palm'),
            $this->collectFileMetadata($this->componentCacheDir, 'components')
        );

        usort($files, static function (array $a, array $b): int {
            return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
        });

        return array_slice($files, 0, $limit);
    }

    protected function collectFileMetadata(string $directory, string $bucket): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (in_array($fileInfo->getFilename(), $this->protectedFiles, true)) {
                continue;
            }

            $files[] = [
                'name' => $fileInfo->getFilename(),
                'path' => $fileInfo->getPathname(),
                'bucket' => $bucket,
                'size' => $fileInfo->getSize(),
                'human_size' => $this->formatBytes($fileInfo->getSize()),
                'mtime' => $fileInfo->getMTime(),
                'last_modified' => $this->formatTimestamp($fileInfo->getMTime()),
            ];
        }

        return $files;
    }

    protected function buildStats(string $directory, array $extensions = []): array
    {
        $stats = [
            'path' => $directory,
            'file_count' => 0,
            'total_size' => 0,
            'human_size' => '0 B',
            'last_modified' => null,
            'last_modified_raw' => 0,
            'readable' => is_readable($directory),
            'writable' => is_writable($directory),
        ];

        if (!is_dir($directory)) {
            return $stats;
        }

        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (in_array($fileInfo->getFilename(), $this->protectedFiles, true)) {
                continue;
            }

            if (!empty($extensions)) {
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }
            }

            $stats['file_count']++;
            $stats['total_size'] += $fileInfo->getSize();
            $stats['last_modified_raw'] = max($stats['last_modified_raw'], $fileInfo->getMTime());
        }

        $stats['human_size'] = $this->formatBytes($stats['total_size']);
        $stats['last_modified'] = $this->formatTimestamp($stats['last_modified_raw']);

        return $stats;
    }

    protected function wipeDirectory(string $directory, array $extensions = []): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $removed = 0;
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();

            if (in_array($filename, $this->protectedFiles, true)) {
                continue;
            }

            if (!empty($extensions)) {
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }
            }

            if (@unlink($fileInfo->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return sprintf('%.2f %s', $value, $units[$power]);
    }

    protected function formatTimestamp(?int $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        return date('c', $timestamp);
    }

    protected function normalizeTarget(?string $target): string
    {
        $target = strtolower($target ?? 'all');

        if (!in_array($target, ['all', 'views', 'palm', 'components'], true)) {
            return 'all';
        }

        return $target === 'views' ? 'palm' : $target;
    }

    protected function buildMessage(string $target, int $removed): string
    {
        $label = match ($target) {
            'palm' => 'Palm view cache',
            'components' => 'Component cache',
            default => 'All caches',
        };

        if ($removed === 0) {
            return "{$label} already clean.";
        }

        return sprintf('%s cleared (%d file%s removed).', $label, $removed, $removed === 1 ? '' : 's');
    }
}


