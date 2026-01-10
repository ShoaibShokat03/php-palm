<?php

namespace Frontend\Palm;

/**
 * Main compiler for .palm.php files
 * Orchestrates lexing, parsing, transformation, and caching
 */
class PalmCompiler
{
    protected static string $cacheDir = '';
    protected static bool $cacheEnabled = false; // Disabled by default for frontend files
    protected static bool $sourceMapsEnabled = true;

    public static function init(string $cacheDir): void
    {
        self::$cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        // Ensure caching is disabled for frontend files
        self::$cacheEnabled = false;
    }
    
    public static function setCacheEnabled(bool $enabled): void
    {
        self::$cacheEnabled = $enabled;
    }

    /**
     * Compile a .palm.php file
     * 
     * @param string $filePath Path to .palm.php file
     * @return array ['php' => string, 'js' => string, 'metadata' => array, 'sourceMap' => array]
     */
    public static function compile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        // Check cache
        $cacheKey = self::getCacheKey($filePath);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.cache';
        $fileMtime = filemtime($filePath);

        if (self::$cacheEnabled && file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['mtime']) && $cached['mtime'] >= $fileMtime) {
                return $cached['data'];
            }
        }

        // Read source
        $source = file_get_contents($filePath);

        // Step 1: Lex/Tokenize
        $lexer = new PalmLexer($source);
        $tokens = $lexer->tokenize();

        // Step 2: Parse to AST
        $parser = new PalmParser($tokens);
        $ast = $parser->parse();

        // Step 3: Transform AST
        $transformer = new PalmASTTransformer($ast, $filePath);
        $php = $transformer->toPhp();
        $js = $transformer->toJs();
        $metadata = $transformer->getMetadata();

        // Step 4: Generate source map
        $sourceMap = self::generateSourceMap($tokens, $filePath);

        $result = [
            'php' => $php,
            'js' => $js,
            'metadata' => $metadata,
            'sourceMap' => $sourceMap,
        ];

        // Cache result
        if (self::$cacheEnabled) {
            file_put_contents($cacheFile, json_encode([
                'mtime' => $fileMtime,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        return $result;
    }

    /**
     * Get compiled PHP output (for SSR)
     * Returns the path to the compiled PHP file instead of the content
     */
    public static function getCompiledPhp(string $filePath): string
    {
        $compiled = self::compile($filePath);
        
        // Always use temporary files (not cache directory) to avoid stale files
        $tempDir = sys_get_temp_dir() . '/palm-compiled';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Use a unique filename based on file path and mtime to ensure freshness
        $fileMtime = filemtime($filePath);
        $cacheKey = md5($filePath . $fileMtime);
        $phpCacheFile = $tempDir . '/' . $cacheKey . '.php';
        
        // Always write fresh compiled PHP
        file_put_contents($phpCacheFile, $compiled['php']);
        
        // Clean up old temporary files (older than 1 hour)
        self::cleanupTempFiles($tempDir);
        
        return $phpCacheFile;
    }
    
    /**
     * Clean up old temporary files
     */
    protected static function cleanupTempFiles(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }
        
        $files = glob($tempDir . '/*.php');
        $oneHourAgo = time() - 3600;
        
        foreach ($files as $file) {
            if (filemtime($file) < $oneHourAgo) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Get compiled PHP content as string (for backward compatibility if needed)
     */
    public static function getCompiledPhpContent(string $filePath): string
    {
        $compiled = self::compile($filePath);
        return $compiled['php'];
    }

    /**
     * Get compiled JS module (for hydration)
     */
    public static function getCompiledJs(string $filePath): string
    {
        $compiled = self::compile($filePath);
        return $compiled['js'];
    }

    /**
     * Invalidate cache for a file
     */
    public static function invalidateCache(string $filePath): void
    {
        $cacheKey = self::getCacheKey($filePath);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.cache';
        $phpCacheFile = self::$cacheDir . '/' . $cacheKey . '.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        if (file_exists($phpCacheFile)) {
            @unlink($phpCacheFile);
        }
    }

    /**
     * Clear all caches
     */
    public static function clearCache(): void
    {
        if (!is_dir(self::$cacheDir)) {
            return;
        }

        $files = glob(self::$cacheDir . '/*.cache') ?: [];
        $phpFiles = glob(self::$cacheDir . '/*.php') ?: [];
        foreach (array_merge($files, $phpFiles) as $file) {
            @unlink($file);
        }
    }

    protected static function getCacheKey(string $filePath): string
    {
        return md5(realpath($filePath) ?: $filePath);
    }

    protected static function generateSourceMap(array $tokens, string $filePath): array
    {
        // Generate source map for debugging
        // Maps generated PHP/JS positions to original .palm.php positions
        $map = [
            'file' => basename($filePath),
            'mappings' => [],
        ];

        // Simple mapping: token positions
        foreach ($tokens as $idx => $token) {
            if (isset($token['line'], $token['column'])) {
                $map['mappings'][] = [
                    'token' => $idx,
                    'type' => $token['type'],
                    'original' => [
                        'line' => $token['line'],
                        'column' => $token['column'],
                    ],
                ];
            }
        }

        return $map;
    }

    public static function setSourceMapsEnabled(bool $enabled): void
    {
        self::$sourceMapsEnabled = $enabled;
    }
}

