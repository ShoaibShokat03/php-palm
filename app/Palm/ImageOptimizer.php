<?php

namespace Frontend\Palm;

/**
 * Image Optimizer
 * 
 * Provides image optimization helpers and responsive image generation
 */
class ImageOptimizer
{
    protected static string $publicPath = '';
    protected static array $formats = ['webp', 'jpg', 'png'];
    protected static bool $generateWebP = true;

    /**
     * Initialize image optimizer
     */
    public static function init(string $baseDir): void
    {
        self::$publicPath = $baseDir . '/public';
    }

    /**
     * Generate responsive image srcset
     */
    public static function srcset(string $imagePath, array $sizes = [640, 768, 1024, 1280, 1920]): string
    {
        $srcset = [];
        $baseUrl = url($imagePath);
        $pathInfo = pathinfo($imagePath);
        $extension = $pathInfo['extension'] ?? 'jpg';
        $filename = $pathInfo['filename'] ?? 'image';
        $dir = $pathInfo['dirname'] ?? '';

        foreach ($sizes as $size) {
            // Generate responsive image URL
            $responsivePath = $dir . '/' . $filename . '-' . $size . 'w.' . $extension;
            $url = url($responsivePath);
            $srcset[] = $url . ' ' . $size . 'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * Generate picture element with multiple sources (WebP + fallback)
     */
    public static function picture(string $imagePath, array $options = []): string
    {
        $alt = $options['alt'] ?? '';
        $class = $options['class'] ?? '';
        $lazy = $options['lazy'] ?? true;
        $sizes = $options['sizes'] ?? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
        $srcsetSizes = $options['srcset_sizes'] ?? [640, 768, 1024, 1280];

        $pathInfo = pathinfo($imagePath);
        $extension = $pathInfo['extension'] ?? 'jpg';
        $filename = $pathInfo['filename'] ?? 'image';
        $dir = $pathInfo['dirname'] ?? '';

        $html = '<picture>';
        
        // WebP source (if enabled)
        if (self::$generateWebP) {
            $webpSrcset = self::srcset($dir . '/' . $filename . '.webp', $srcsetSizes);
            $html .= '<source srcset="' . htmlspecialchars($webpSrcset) . '" sizes="' . htmlspecialchars($sizes) . '" type="image/webp">';
        }

        // Fallback source
        $fallbackSrcset = self::srcset($imagePath, $srcsetSizes);
        $html .= '<source srcset="' . htmlspecialchars($fallbackSrcset) . '" sizes="' . htmlspecialchars($sizes) . '" type="image/' . htmlspecialchars($extension) . '">';

        // Fallback img tag
        $imgAttrs = [
            'src' => url($imagePath),
            'alt' => $alt,
        ];
        
        if ($class) {
            $imgAttrs['class'] = $class;
        }
        
        if ($lazy) {
            $imgAttrs['loading'] = 'lazy';
        }

        $attrString = '';
        foreach ($imgAttrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $html .= '<img' . $attrString . '>';
        $html .= '</picture>';

        return $html;
    }

    /**
     * Generate optimized image tag with responsive srcset
     */
    public static function responsive(string $imagePath, array $options = []): string
    {
        $alt = $options['alt'] ?? '';
        $class = $options['class'] ?? '';
        $lazy = $options['lazy'] ?? true;
        $sizes = $options['sizes'] ?? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
        $srcsetSizes = $options['srcset_sizes'] ?? [640, 768, 1024, 1280];

        $srcset = self::srcset($imagePath, $srcsetSizes);
        $src = url($imagePath);

        $attrs = [
            'src' => $src,
            'srcset' => $srcset,
            'sizes' => $sizes,
            'alt' => $alt,
        ];

        if ($class) {
            $attrs['class'] = $class;
        }

        if ($lazy) {
            $attrs['loading'] = 'lazy';
        }

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return '<img' . $attrString . '>';
    }

    /**
     * Enable/disable WebP generation
     */
    public static function setGenerateWebP(bool $enable): void
    {
        self::$generateWebP = $enable;
    }
}

