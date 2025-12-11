<?php

namespace Frontend\Palm;

/**
 * Social Share Helper
 * 
 * Provides social media sharing links
 */
class SocialShare
{
    /**
     * Generate social share links
     */
    public static function links(string $url, string $title, ?string $description = null, array $options = []): array
    {
        $description = $description ?? $title;
        $encodedUrl = urlencode($url);
        $encodedTitle = urlencode($title);
        $encodedDescription = urlencode($description);

        $platforms = $options['platforms'] ?? ['facebook', 'twitter', 'linkedin', 'whatsapp', 'email'];
        $class = $options['class'] ?? 'social-share-link';

        $links = [];

        if (in_array('facebook', $platforms)) {
            $links['facebook'] = [
                'url' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
                'text' => 'Share on Facebook',
                'icon' => '📘',
            ];
        }

        if (in_array('twitter', $platforms)) {
            $links['twitter'] = [
                'url' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}",
                'text' => 'Share on Twitter',
                'icon' => '🐦',
            ];
        }

        if (in_array('linkedin', $platforms)) {
            $links['linkedin'] = [
                'url' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}",
                'text' => 'Share on LinkedIn',
                'icon' => '💼',
            ];
        }

        if (in_array('whatsapp', $platforms)) {
            $links['whatsapp'] = [
                'url' => "https://wa.me/?text={$encodedTitle}%20{$encodedUrl}",
                'text' => 'Share on WhatsApp',
                'icon' => '💬',
            ];
        }

        if (in_array('email', $platforms)) {
            $links['email'] = [
                'url' => "mailto:?subject={$encodedTitle}&body={$encodedDescription}%20{$encodedUrl}",
                'text' => 'Share via Email',
                'icon' => '📧',
            ];
        }

        if (in_array('reddit', $platforms)) {
            $links['reddit'] = [
                'url' => "https://reddit.com/submit?url={$encodedUrl}&title={$encodedTitle}",
                'text' => 'Share on Reddit',
                'icon' => '🔴',
            ];
        }

        if (in_array('pinterest', $platforms)) {
            $image = $options['image'] ?? '';
            $encodedImage = $image ? '&media=' . urlencode($image) : '';
            $links['pinterest'] = [
                'url' => "https://pinterest.com/pin/create/button/?url={$encodedUrl}&description={$encodedTitle}{$encodedImage}",
                'text' => 'Share on Pinterest',
                'icon' => '📌',
            ];
        }

        return $links;
    }

    /**
     * Generate social share HTML
     */
    public static function render(string $url, string $title, ?string $description = null, array $options = []): string
    {
        $links = self::links($url, $title, $description, $options);
        $class = $options['class'] ?? 'social-share';
        $linkClass = $options['link_class'] ?? 'social-share-link';
        $target = $options['target'] ?? '_blank';
        $rel = $options['rel'] ?? 'noopener noreferrer';

        if (empty($links)) {
            return '';
        }

        $html = '<div class="' . htmlspecialchars($class) . '">';
        
        foreach ($links as $platform => $data) {
            $icon = $options['show_icons'] ?? true ? $data['icon'] . ' ' : '';
            $html .= '<a href="' . htmlspecialchars($data['url']) . '" ' .
                     'class="' . htmlspecialchars($linkClass) . ' ' . htmlspecialchars($platform) . '" ' .
                     'target="' . htmlspecialchars($target) . '" ' .
                     'rel="' . htmlspecialchars($rel) . '" ' .
                     'aria-label="' . htmlspecialchars($data['text']) . '">' .
                     htmlspecialchars($icon . $data['text']) .
                     '</a>';
        }

        $html .= '</div>';

        return $html;
    }
}

