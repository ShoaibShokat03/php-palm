<?php

use Frontend\Palm\Page;

?>
<!DOCTYPE html>
<html lang="<?= $lang ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $metaTags ?>


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Palm Theme - Tropical Color Palette */
            --color-primary: #10b981;
            /* Emerald green - palm leaves */
            --color-primary-dark: #059669;
            /* Deep emerald */
            --color-primary-light: #34d399;
            /* Light emerald */
            --color-secondary: #f59e0b;
            /* Sandy gold - beach sand */
            --color-accent: #06b6d4;
            /* Ocean turquoise */

            /* Neutral Colors */
            --color-bg: #f0fdf4;
            /* Very light mint */
            --color-bg-alt: #dcfce7;
            /* Light mint */
            --color-surface: #ffffff;
            --color-border: #d1fae5;
            /* Mint border */

            /* Text Colors */
            --color-text: #064e3b;
            /* Deep forest green */
            --color-text-light: #047857;
            /* Forest green */
            --color-text-muted: #6ee7b7;
            /* Light emerald */

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(16 185 129 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(16 185 129 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(16 185 129 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(16 185 129 / 0.1);

            /* Spacing */
            --spacing-xs: 0.5rem;
            --spacing-sm: 0.75rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;

            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-full: 9999px;

            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ♿ Accessibility: Skip to Content */
        .skip-link {
            position: absolute;
            top: -100px;
            left: 0;
            background: var(--color-primary);
            color: white;
            padding: 1rem 2rem;
            z-index: 9999;
            transition: top 0.2s;
            text-decoration: none;
            font-weight: 700;
            border-bottom-right-radius: var(--radius-md);
        }

        .skip-link:focus {
            top: 0;
        }

        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: var(--spacing-lg) var(--spacing-xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-lg);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-decoration: none;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
            transition: transform var(--transition-fast);
        }

        .logo:hover {
            transform: translateY(-2px);
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            backdrop-filter: blur(10px);
        }

        /* Navigation */
        nav {
            display: flex;
            gap: var(--spacing-xs);
            align-items: center;
        }

        nav a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.9375rem;
            transition: all var(--transition-base);
            position: relative;
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) scaleX(0);
            width: 80%;
            height: 2px;
            background: white;
            border-radius: var(--radius-full);
            transition: transform var(--transition-base);
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        nav a:hover::after {
            transform: translateX(-50%) scaleX(1);
        }

        nav a.is-active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: var(--spacing-sm);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .mobile-menu-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 768px) {
            .header-container {
                padding: var(--spacing-md) var(--spacing-lg);
            }

            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
            }

            nav {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-secondary) 100%);
                flex-direction: column;
                padding: var(--spacing-md);
                gap: var(--spacing-xs);
                box-shadow: var(--shadow-xl);
                transform: translateY(-100%);
                opacity: 0;
                pointer-events: none;
                transition: all var(--transition-base);
            }

            nav.active {
                transform: translateY(0);
                opacity: 1;
                pointer-events: all;
            }

            nav a {
                width: 100%;
                text-align: left;
            }
        }

        /* Main Content */
        main {
            max-width: 1280px;
            margin: 0 auto;
            padding: var(--spacing-2xl) var(--spacing-xl);
            min-height: calc(100vh - 200px);
        }

        @media (max-width: 768px) {
            main {
                padding: var(--spacing-xl) var(--spacing-lg);
            }
        }

        /* Content Section */
        .content-section {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Card Styles */
        .card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border);
            transition: all var(--transition-base);
        }

        .card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-4px);
        }

        /* Pill Badge */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-xs) var(--spacing-md);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            color: var(--color-primary);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        /* Typography */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--color-text);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
        }

        h2 {
            font-size: 2rem;
            margin: var(--spacing-lg) 0 var(--spacing-md);
        }

        h3 {
            font-size: 1.5rem;
            margin: var(--spacing-md) 0 var(--spacing-sm);
        }

        h4 {
            font-size: 1.25rem;
            margin: var(--spacing-md) 0 var(--spacing-sm);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            h2 {
                font-size: 1.75rem;
            }

            h3 {
                font-size: 1.375rem;
            }
        }

        .lead {
            font-size: 1.125rem;
            color: var(--color-text-light);
            line-height: 1.75;
            margin-bottom: var(--spacing-xl);
        }

        p {
            margin-bottom: var(--spacing-md);
            color: var(--color-text-light);
        }

        /* Links */
        a {
            color: var(--color-primary);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        a:hover {
            color: var(--color-primary-dark);
        }

        /* Lists */
        ul,
        ol {
            margin: var(--spacing-md) 0;
            padding-left: var(--spacing-xl);
        }

        li {
            margin: var(--spacing-sm) 0;
            color: var(--color-text-light);
        }

        /* Code */
        code {
            background: var(--color-bg-alt);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.875em;
            color: var(--color-primary);
            border: 1px solid var(--color-border);
        }

        /* Demo Section */
        .demo-section {
            background: var(--color-bg-alt);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin: var(--spacing-lg) 0;
            border: 1px solid var(--color-border);
        }

        /* Buttons */
        .btn-action,
        .btn-action-secondary {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
            font-family: inherit;
        }

        .btn-action {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action-secondary {
            background: var(--color-surface);
            color: var(--color-primary);
            border: 2px solid var(--color-border);
        }

        .btn-action-secondary:hover {
            border-color: var(--color-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        /* Value Display */
        .value-display {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            margin: var(--spacing-md) 0;
        }

        .value-display strong {
            color: var(--color-primary);
            font-weight: 600;
        }

        /* Footer */
        footer {
            background: var(--color-surface);
            border-top: 1px solid var(--color-border);
            padding: var(--spacing-2xl) var(--spacing-xl);
            margin-top: var(--spacing-2xl);
            text-align: center;
            color: var(--color-text-light);
        }

        footer strong {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header role="banner">
        <div class="header-container">
            <a href="/" class="logo">
                <div class="logo-icon">🌴</div>
                <span>PHP Palm</span>
            </a>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <nav id="mainNav" role="navigation" aria-label="Main Navigation">
                <a href="/" class="<?= ($currentPath ?? '/') === '/' ? 'is-active' : '' ?>" <?= ($currentPath ?? '/') === '/' ? 'aria-current="page"' : '' ?>>Home</a>
                <a href="/about" class="<?= ($currentPath ?? '') === '/about' ? 'is-active' : '' ?>" <?= ($currentPath ?? '') === '/about' ? 'aria-current="page"' : '' ?>>About</a>
                <a href="/contact" class="<?= ($currentPath ?? '') === '/contact' ? 'is-active' : '' ?>" <?= ($currentPath ?? '') === '/contact' ? 'aria-current="page"' : '' ?>>Contact</a>
                <a href="/demo" class="<?= ($currentPath ?? '') === '/demo' ? 'is-active' : '' ?>" <?= ($currentPath ?? '') === '/demo' ? 'aria-current="page"' : '' ?>>Demo</a>
                <a href="/users" class="<?= ($currentPath ?? '') === '/users' ? 'is-active' : '' ?>" <?= ($currentPath ?? '') === '/users' ? 'aria-current="page"' : '' ?>>Users</a>
            </nav>
        </div>
    </header>

    <main id="main-content" role="main" tabindex="-1">
        <?= $content ?? '' ?>
    </main>

    <footer role="contentinfo">
        <p>Built with ❤️ using <strong>PHP Palm</strong> · Modern PHP Framework</p>
        <p style="font-size: 0.875rem; margin-top: 0.5rem; opacity: 0.7;">
            Fast · Secure · Elegant
        </p>
    </footer>

    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mainNav');
            nav.classList.toggle('active');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const nav = document.getElementById('mainNav');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                nav.classList.remove('active');
            }
        });

        // Close mobile menu on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('mainNav').classList.remove('active');
            }
        });
    </script>
</body>

</html>