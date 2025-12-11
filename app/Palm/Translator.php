<?php

namespace Frontend\Palm;

/**
 * Internationalization (i18n) Translator
 * 
 * Provides translation support for multi-language applications
 */
class Translator
{
    protected static string $locale = 'en';
    protected static string $fallbackLocale = 'en';
    protected static array $translations = [];
    protected static string $translationsPath = '';

    /**
     * Initialize translator
     */
    public static function init(string $baseDir, ?string $locale = null): void
    {
        self::$translationsPath = $baseDir . '/lang';
        
        // Detect locale if not provided
        if ($locale === null) {
            $locale = self::detectLocale();
        }
        
        self::$locale = $locale;
        
        // Load translations
        self::loadTranslations($locale);
    }

    /**
     * Detect locale from browser or session
     */
    protected static function detectLocale(): string
    {
        // Check session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }

        // Check Accept-Language header
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLanguage) {
            $languages = explode(',', $acceptLanguage);
            if (!empty($languages[0])) {
                $lang = trim(explode(';', $languages[0])[0]);
                // Extract language code (e.g., 'en' from 'en-US')
                if (strlen($lang) >= 2) {
                    return strtolower(substr($lang, 0, 2));
                }
            }
        }

        return self::$fallbackLocale;
    }

    /**
     * Load translations for locale
     */
    protected static function loadTranslations(string $locale): void
    {
        $translationFile = self::$translationsPath . '/' . $locale . '.php';
        
        if (file_exists($translationFile)) {
            self::$translations[$locale] = require $translationFile;
        } else {
            self::$translations[$locale] = [];
        }
        
        // Also load fallback if different
        if ($locale !== self::$fallbackLocale) {
            $fallbackFile = self::$translationsPath . '/' . self::$fallbackLocale . '.php';
            if (file_exists($fallbackFile)) {
                self::$translations[self::$fallbackLocale] = require $fallbackFile;
            }
        }
    }

    /**
     * Translate a string
     */
    public static function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale;
        
        // Get translation
        $translation = self::getTranslation($key, $locale);
        
        // Replace placeholders
        if (!empty($replace)) {
            foreach ($replace as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, (string)$value, $translation);
            }
        }
        
        return $translation;
    }

    /**
     * Get translation for key
     */
    protected static function getTranslation(string $key, string $locale): string
    {
        // Check current locale
        if (isset(self::$translations[$locale][$key])) {
            return self::$translations[$locale][$key];
        }
        
        // Check fallback locale
        if ($locale !== self::$fallbackLocale && isset(self::$translations[self::$fallbackLocale][$key])) {
            return self::$translations[self::$fallbackLocale][$key];
        }
        
        // Return key if no translation found
        return $key;
    }

    /**
     * Pluralization translation
     */
    public static function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale;
        $translation = self::getTranslation($key, $locale);
        
        // Handle pluralization syntax: {0} No items|{1} One item|[2,*] :count items
        if (preg_match_all('/\{(\d+)\}|\[(\d+),(\*|\d+)\]/', $translation, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($match[1])) {
                    // Exact number match: {0}
                    if ((int)$match[1] === $number) {
                        $translation = preg_replace('/\{' . $match[1] . '\}\s*([^|]+)/', '$1', $translation);
                        $translation = preg_replace('/\|.*$/', '', $translation);
                        break;
                    }
                } elseif (isset($match[2]) && isset($match[3])) {
                    // Range match: [2,*] or [2,10]
                    $min = (int)$match[2];
                    $max = $match[3] === '*' ? PHP_INT_MAX : (int)$match[3];
                    
                    if ($number >= $min && $number <= $max) {
                        $pattern = '/\[' . preg_quote($match[2]) . ',' . preg_quote($match[3]) . '\]\s*([^|]+)/';
                        $translation = preg_replace($pattern, '$1', $translation);
                        $translation = preg_replace('/\{.*?\}\s*[^|]*\|/', '', $translation);
                        $translation = preg_replace('/\|.*$/', '', $translation);
                        break;
                    }
                }
            }
        }
        
        // Replace placeholders
        if (!empty($replace)) {
            foreach ($replace as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, (string)$value, $translation);
            }
        }
        
        // Replace :count placeholder with actual number
        $translation = str_replace(':count', (string)$number, $translation);
        
        return $translation;
    }

    /**
     * Set locale
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        
        // Save to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['locale'] = $locale;
        
        // Load translations for new locale
        self::loadTranslations($locale);
    }

    /**
     * Get current locale
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Check if translation exists
     */
    public static function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? self::$locale;
        
        if (isset(self::$translations[$locale][$key])) {
            return true;
        }
        
        if ($locale !== self::$fallbackLocale && isset(self::$translations[self::$fallbackLocale][$key])) {
            return true;
        }
        
        return false;
    }
}

