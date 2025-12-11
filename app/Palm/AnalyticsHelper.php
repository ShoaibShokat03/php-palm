<?php

namespace Frontend\Palm;

/**
 * Analytics Helper
 * 
 * Provides analytics integration helpers
 */
class AnalyticsHelper
{
    protected static array $providers = [];
    protected static string $defaultProvider = '';

    /**
     * Register analytics provider
     */
    public static function register(string $name, string $trackingId, array $options = []): void
    {
        self::$providers[$name] = [
            'id' => $trackingId,
            'options' => $options,
        ];
        
        if (empty(self::$defaultProvider)) {
            self::$defaultProvider = $name;
        }
    }

    /**
     * Generate Google Analytics script
     */
    public static function googleAnalytics(string $trackingId, string $version = '4'): string
    {
        if ($version === '4') {
            return <<<JS
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$trackingId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$trackingId}');
</script>
JS;
        } else {
            // GA3 (Universal Analytics)
            return <<<JS
<!-- Google Analytics -->
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
  ga('create', '{$trackingId}', 'auto');
  ga('send', 'pageview');
</script>
JS;
        }
    }

    /**
     * Generate Plausible Analytics script
     */
    public static function plausible(string $domain): string
    {
        return <<<JS
<!-- Plausible Analytics -->
<script defer data-domain="{$domain}" src="https://plausible.io/js/script.js"></script>
JS;
    }

    /**
     * Generate custom analytics script
     */
    public static function custom(string $script, array $attributes = []): string
    {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            if (is_bool($value) && $value) {
                $attrString .= ' ' . htmlspecialchars($key);
            } else {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }

        return '<script' . $attrString . '>' . $script . '</script>';
    }

    /**
     * Get all registered analytics scripts
     */
    public static function render(): string
    {
        $html = '';
        
        foreach (self::$providers as $name => $config) {
            $trackingId = $config['id'];
            $options = $config['options'];
            
            switch ($name) {
                case 'google_analytics':
                case 'ga':
                    $version = $options['version'] ?? '4';
                    $html .= self::googleAnalytics($trackingId, $version) . "\n    ";
                    break;
                case 'plausible':
                    $html .= self::plausible($trackingId) . "\n    ";
                    break;
                case 'custom':
                    $html .= self::custom($options['script'] ?? '', $options['attributes'] ?? []) . "\n    ";
                    break;
            }
        }
        
        return rtrim($html);
    }
}

