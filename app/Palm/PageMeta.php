<?php

namespace Frontend\Palm;

/**
 * PageMeta - Container for SEO meta tags and Open Graph data
 * 
 * Usage:
 *   $meta->title = 'My Page';
 *   $meta->description = 'Page description';
 *   echo $meta->render();
 */
class PageMeta
{
    // Basic meta tags
    public ?string $title = null;
    public ?string $description = null;
    public ?string $keywords = null;
    public ?string $author = null;
    public ?string $canonical = null;
    public ?string $robots = null;

    // Open Graph tags
    public ?string $ogTitle = null;
    public ?string $ogDescription = null;
    public ?string $ogImage = null;
    public ?string $ogUrl = null;
    public ?string $ogType = 'website';
    public ?string $ogSiteName = null;
    public ?string $ogLocale = 'en_US';

    // Twitter Card tags
    public ?string $twitterCard = 'summary_large_image';
    public ?string $twitterSite = null;
    public ?string $twitterCreator = null;
    public ?string $twitterTitle = null;
    public ?string $twitterDescription = null;
    public ?string $twitterImage = null;

    // Additional meta
    public ?string $themeColor = null;
    public array $customMeta = [];

    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Set title
     */
    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set description
     */
    public function description(string $desc): self
    {
        $this->description = $desc;
        return $this;
    }

    /**
     * Set keywords
     */
    public function keywords($keywords): self
    {
        if (is_array($keywords)) {
            $this->keywords = implode(', ', $keywords);
        } else {
            $this->keywords = $keywords;
        }
        return $this;
    }

    /**
     * Set Open Graph image
     */
    public function ogImage(string $url): self
    {
        $this->ogImage = $url;
        return $this;
    }

    /**
     * Set canonical URL
     */
    public function canonical(string $url): self
    {
        $this->canonical = $url;
        return $this;
    }

    /**
     * Add custom meta tag
     */
    public function addMeta(string $name, string $content, string $type = 'name'): self
    {
        $this->customMeta[] = [
            'type' => $type,
            'name' => $name,
            'content' => $content
        ];
        return $this;
    }

    /**
     * Render all meta tags as HTML
     */
    public function render(): string
    {
        $html = [];

        // Basic meta tags
        if ($this->title) {
            $html[] = sprintf('<title>%s</title>', htmlspecialchars($this->title));
        }

        if ($this->description) {
            $html[] = sprintf('<meta name="description" content="%s">', htmlspecialchars($this->description));
        }

        if ($this->keywords) {
            $html[] = sprintf('<meta name="keywords" content="%s">', htmlspecialchars($this->keywords));
        }

        if ($this->author) {
            $html[] = sprintf('<meta name="author" content="%s">', htmlspecialchars($this->author));
        }

        if ($this->robots) {
            $html[] = sprintf('<meta name="robots" content="%s">', htmlspecialchars($this->robots));
        }

        if ($this->canonical) {
            $html[] = sprintf('<link rel="canonical" href="%s">', htmlspecialchars($this->canonical));
        }

        // Open Graph tags
        $ogTitle = $this->ogTitle ?? $this->title;
        $ogDescription = $this->ogDescription ?? $this->description;

        if ($ogTitle) {
            $html[] = sprintf('<meta property="og:title" content="%s">', htmlspecialchars($ogTitle));
        }

        if ($ogDescription) {
            $html[] = sprintf('<meta property="og:description" content="%s">', htmlspecialchars($ogDescription));
        }

        if ($this->ogImage) {
            $html[] = sprintf('<meta property="og:image" content="%s">', htmlspecialchars($this->ogImage));
        }

        if ($this->ogUrl) {
            $html[] = sprintf('<meta property="og:url" content="%s">', htmlspecialchars($this->ogUrl));
        }

        if ($this->ogType) {
            $html[] = sprintf('<meta property="og:type" content="%s">', htmlspecialchars($this->ogType));
        }

        if ($this->ogSiteName) {
            $html[] = sprintf('<meta property="og:site_name" content="%s">', htmlspecialchars($this->ogSiteName));
        }

        if ($this->ogLocale) {
            $html[] = sprintf('<meta property="og:locale" content="%s">', htmlspecialchars($this->ogLocale));
        }

        // Twitter Card tags
        $twitterTitle = $this->twitterTitle ?? $this->title;
        $twitterDescription = $this->twitterDescription ?? $this->description;
        $twitterImage = $this->twitterImage ?? $this->ogImage;

        if ($this->twitterCard) {
            $html[] = sprintf('<meta name="twitter:card" content="%s">', htmlspecialchars($this->twitterCard));
        }

        if ($twitterTitle) {
            $html[] = sprintf('<meta name="twitter:title" content="%s">', htmlspecialchars($twitterTitle));
        }

        if ($twitterDescription) {
            $html[] = sprintf('<meta name="twitter:description" content="%s">', htmlspecialchars($twitterDescription));
        }

        if ($twitterImage) {
            $html[] = sprintf('<meta name="twitter:image" content="%s">', htmlspecialchars($twitterImage));
        }

        if ($this->twitterSite) {
            $html[] = sprintf('<meta name="twitter:site" content="%s">', htmlspecialchars($this->twitterSite));
        }

        if ($this->twitterCreator) {
            $html[] = sprintf('<meta name="twitter:creator" content="%s">', htmlspecialchars($this->twitterCreator));
        }

        // Theme color
        if ($this->themeColor) {
            $html[] = sprintf('<meta name="theme-color" content="%s">', htmlspecialchars($this->themeColor));
        }

        // Custom meta tags
        foreach ($this->customMeta as $meta) {
            if ($meta['type'] === 'property') {
                $html[] = sprintf(
                    '<meta property="%s" content="%s">',
                    htmlspecialchars($meta['name']),
                    htmlspecialchars($meta['content'])
                );
            } else {
                $html[] = sprintf(
                    '<meta name="%s" content="%s">',
                    htmlspecialchars($meta['name']),
                    htmlspecialchars($meta['content'])
                );
            }
        }

        return implode("\n    ", $html);
    }

    /**
     * Render high-priority resource hints as HTTP Link headers
     * Used for HTTP/2 server push or preloading early
     */
    public function renderLinkHeaders(): string
    {
        $links = [];

        // Canonical link
        if ($this->canonical) {
            $links[] = sprintf('<%s>; rel="canonical"', $this->canonical);
        }

        // Preload essential fonts or images if we had them tracked here
        // (Currently tracked in ProgressiveResourceLoader, but we could add some here)

        return implode(', ', $links);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'author' => $this->author,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'ogTitle' => $this->ogTitle,
            'ogDescription' => $this->ogDescription,
            'ogImage' => $this->ogImage,
            'ogUrl' => $this->ogUrl,
            'ogType' => $this->ogType,
            'ogSiteName' => $this->ogSiteName,
            'ogLocale' => $this->ogLocale,
            'twitterCard' => $this->twitterCard,
            'twitterSite' => $this->twitterSite,
            'twitterCreator' => $this->twitterCreator,
            'twitterTitle' => $this->twitterTitle,
            'twitterDescription' => $this->twitterDescription,
            'twitterImage' => $this->twitterImage,
            'themeColor' => $this->themeColor,
            'customMeta' => $this->customMeta,
        ];
    }
}
