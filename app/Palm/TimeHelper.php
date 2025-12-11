<?php

namespace Frontend\Palm;

/**
 * Time Helper
 * 
 * Provides date and time formatting helpers
 */
class TimeHelper
{
    protected static string $defaultTimezone = 'UTC';
    protected static string $defaultLocale = 'en_US';
    protected static string $defaultFormat = 'Y-m-d H:i:s';

    /**
     * Set default timezone
     */
    public static function setTimezone(string $timezone): void
    {
        self::$defaultTimezone = $timezone;
    }

    /**
     * Format date/time
     */
    public static function format(string|int|\DateTime $date, ?string $format = null): string
    {
        $format = $format ?? self::$defaultFormat;

        if (is_int($date)) {
            $date = new \DateTime('@' . $date);
            $date->setTimezone(new \DateTimeZone(self::$defaultTimezone));
        } elseif (is_string($date)) {
            $date = new \DateTime($date);
            $date->setTimezone(new \DateTimeZone(self::$defaultTimezone));
        }

        return $date->format($format);
    }

    /**
     * Human-readable relative time (e.g., "2 hours ago")
     */
    public static function ago(string|int|\DateTime $date): string
    {
        if (is_int($date)) {
            $timestamp = $date;
        } elseif (is_string($date)) {
            $timestamp = strtotime($date);
        } else {
            $timestamp = $date->getTimestamp();
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }

    /**
     * Format date only
     */
    public static function date(string|int|\DateTime $date, string $format = 'Y-m-d'): string
    {
        return self::format($date, $format);
    }

    /**
     * Format time only
     */
    public static function time(string|int|\DateTime $date, string $format = 'H:i:s'): string
    {
        return self::format($date, $format);
    }

    /**
     * Format as ISO 8601
     */
    public static function iso(string|int|\DateTime $date): string
    {
        return self::format($date, 'c');
    }

    /**
     * Format as RFC 2822
     */
    public static function rfc2822(string|int|\DateTime $date): string
    {
        return self::format($date, 'r');
    }

    /**
     * Get current timestamp
     */
    public static function now(): int
    {
        return time();
    }

    /**
     * Get current date/time string
     */
    public static function nowString(string $format = 'Y-m-d H:i:s'): string
    {
        return self::format(time(), $format);
    }
}

