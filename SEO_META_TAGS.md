# SEO Meta Tags - PHP Palm

## Overview

PHP Palm includes a powerful SEO meta tags system through the `Page` class. It automatically injects meta tags into your pages and supports overriding from routes and views.

## Basic Usage

### In Routes (Fluent API)

```php
Route::get('/contact', function () {
    \Frontend\Palm\Page::title('Contact Us - PHP Palm')
        ->description('Get in touch with us')
        ->keywords(['contact', 'support', 'help']);
    
    Route::render('home.contact');
});
```

### In Routes (Direct Assignment)

```php
Route::get('/about', function () {
    \Frontend\Palm\Page::meta->title = 'About - PHP Palm';
    \Frontend\Palm\Page::meta->description = 'Learn about our framework';
    \Frontend\Palm\Page::meta->keywords = 'about, php, framework';
    
    Route::render('home.about');
});
```

### In Views

```php
<?php
use Frontend\Palm\Page;

// Set meta tags from within the view
Page::meta->title = 'Dynamic Page Title';
Page::meta->ogImage = '/images/banner.jpg';
?>
<div class="content">
    <!-- Your content here -->
</div>
```

## Supported Meta Tags

### Basic SEO
- `title` - Page title
- `description` - Meta description
- `keywords` - Meta keywords (string or array)
- `author` - Content author
- `canonical` - Canonical URL
- `robots` - Robots directive (index/noindex)

### Open Graph (Facebook)
- `ogTitle` - OG title (defaults to page title)
- `ogDescription` - OG description (defaults to meta description)
- `ogImage` - OG image URL
- `ogUrl` - OG URL
- `ogType` - OG type (default: 'website')
- `ogSiteName` - Site name
- `ogLocale` - Locale (default: 'en_US')

### Twitter Cards
- `twitterCard` - Card type (default: 'summary_large_image')
- `twitterSite` - @username for site
- `twitterCreator` - @username for content creator
- `twitterTitle` - Twitter title (defaults to page title)
- `twitterDescription` - Twitter description (defaults to meta description)
- `twitterImage` - Twitter image (defaults to ogImage)

### Additional
- `themeColor` - Browser theme color
- Custom meta tags via `addMeta()`

## API Reference

### Static Methods (Fluent)

```php
Page::title(string $title): PageMeta
Page::description(string $desc): PageMeta
Page::keywords(array|string $keywords): PageMeta
Page::ogImage(string $url): PageMeta
Page::canonical(string $url): PageMeta
Page::author(string $author): PageMeta
Page::robots(string $robots): PageMeta
Page::addMeta(string $name, string $content, string $type = 'name'): PageMeta
```

### Direct Property Access

```php
Page::meta->title = 'My Title';
Page::meta->description = 'My Description';
Page::meta->ogImage = '/image.jpg';
```

### Helper Methods

```php
// Reset to defaults
Page::reset(['title' => 'Default Title']);

// Get as array
$metaData = Page::toArray();
```

## Advanced Examples

### E-commerce Product Page

```php
Route::get('/product/{id}', function($id) {
    $product = ProductModel::find($id);
    
    Page::title($product->name . ' - My Store')
        ->description($product->short_description)
        ->keywords($product->tags)
        ->ogImage($product->image_url)
        ->ogType('product')
        ->canonical('/product/' . $product->id);
    
    // Add custom meta
    Page::addMeta('product:price:amount', $product->price, 'property');
    Page::addMeta('product:price:currency', 'USD', 'property');
    
    Route::render('shop.product', compact('product'));
});
```

### Blog Post with Author

```php
Route::get('/blog/{slug}', function($slug) {
    $post = BlogPost::where('slug', $slug)->first();
    
    Page::title($post->title . ' - Blog')
        ->description(substr($post->content, 0, 155))
        ->author($post->author->name)
        ->ogImage($post->featured_image)
        ->ogType('article')
        ->canonical('/blog/' . $post->slug);
    
    // Twitter Card with author
    Page::meta->twitterCreator = '@' . $post->author->twitter;
    
    Route::render('blog.post', compact('post'));
});
```

### Setting Defaults in Layout

The layout auto-initializes Page with defaults:

```php
// src/layouts/main.php
Page::init([
    'title' => $title ?? 'PHP Palm',
    'description' => $meta['description'] ?? 'Modern PHP Framework',
    'ogSiteName' => 'PHP Palm',
    'themeColor' => '#10b981'
]);
```

Route and view values override these defaults.

## How It Works

1. **Initialization**: `Page::init()` is called in the main layout with defaults
2. **Override**: Routes/views can override any meta tag
3. **Rendering**: `Page::meta->render()` outputs all meta tags as HTML
4. **Auto-fallback**: Open Graph and Twitter tags fallback to basic meta tags

## Best Practices

✅ **DO**:
- Set unique titles and descriptions for each page
- Use descriptive, keyword-rich titles (50-60 characters)
- Keep descriptions between 120-155 characters
- Provide high-quality Open Graph images (1200x630px recommended)
- Use canonical URLs to avoid duplicate content issues

❌ **DON'T**:
- Duplicate titles across pages
- Keyword stuff descriptions
- Forget to set Open Graph images for social sharing
- Use special characters without proper escaping (handled automatically)

## Output Example

When you set:
```php
Page::title('Contact Us')->description('Get in touch')->keywords(['contact', 'support']);
```

The layout renders:
```html
<title>Contact Us</title>
<meta name="description" content="Get in touch">
<meta name="keywords" content="contact, support">
<meta property="og:title" content="Contact Us">
<meta property="og:description" content="Get in touch">
<meta property="og:type" content="website">
<meta property="og:site_name" content="PHP Palm">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Contact Us">
<meta name="twitter:description" content="Get in touch">
<meta name="theme-color" content="#10b981">
```

## Integration with Modules

Use with module internal calls:

```php
// In routes
$users = UserModule::all();
Page::title('Users (' . count($users) . ') - Admin Panel');
```

Perfect for dynamic content! 🌴✨
