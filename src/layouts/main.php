<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'PHP Palm') ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description'] ?? 'PHP Palm Frontend') ?>">
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f7f8fb;
            color: #1f2933;
            overflow: scroll;
        }

        header {
            background: linear-gradient(120deg, #0d6efd, #00b4d8);
            color: #fff;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        nav a.is-active {
            background: rgba(255, 255, 255, 0.25);
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }

        main {
            padding: 3rem clamp(1.25rem, 4vw, 3rem);
            min-height: 60vh;
            max-width: 1200px;
            margin: 0 auto;
        }

        footer {
            padding: 1rem 2rem;
            color: #6b7a89;
            font-size: 0.9rem;
            text-align: center;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        /* Content sections - clean, no cards or shadows */
        .content-section {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Links */
        a {
            color: #0d6efd;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: #0b5ed7;
            text-decoration: underline;
        }

        /* Lists */
        ul, ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        li {
            margin: 0.5rem 0;
            line-height: 1.6;
        }

        /* Code */
        code {
            background: rgba(15, 23, 42, 0.08);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        h1 {
            margin: 0 0 1rem;
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        h2 {
            margin: 1.5rem 0 1rem;
            font-size: 1.75rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        h3 {
            margin: 1.5rem 0 0.75rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        h4 {
            margin: 1rem 0 0.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .lead {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .features-banner {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding: 1rem 0;
        }

        .feature-badge {
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
            background: rgba(13, 110, 253, 0.08);
            color: #0d6efd;
        }

        .state-demo {
            margin-top: 2rem;
        }

        .state-demo__heading {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .state-demo__heading h3 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .state-demo__heading p {
            margin: 0;
            color: #64748b;
        }

        .demo-section {
            margin: 2rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.1);
        }
        
        .demo-section:first-of-type {
            border-top: none;
            padding-top: 0;
        }

        .demo-section h4 {
            margin: 0 0 1rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .state-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
        }

        .btn-counter {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #0d6efd;
            background: #fff;
            color: #0d6efd;
            font-size: 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-counter:hover {
            background: #0d6efd;
            color: #fff;
            transform: scale(1.1);
        }

        .counter-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            min-width: 60px;
            text-align: center;
        }

        .counter-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn-action,
        .btn-action-secondary {
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .btn-action {
            background: #0d6efd;
            color: #fff;
        }

        .btn-action:hover {
            background: #0b5ed7;
        }

        .btn-action-secondary {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .btn-action-secondary:hover {
            background: rgba(13, 110, 253, 0.15);
        }

        .value-display {
            font-size: 1.1rem;
            margin: 1rem 0;
            padding: 0.75rem;
            background: rgba(247, 248, 251, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.1);
        }

        .value-display strong {
            color: #0d6efd;
            font-weight: 600;
        }

        .action-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .state-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .favorite-btn,
        .loading-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: 2px solid rgba(13, 110, 253, 0.2);
            background: #fff;
            color: #0d6efd;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .favorite-btn.is-active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .loading-btn.is-busy {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .code-example {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #1e293b;
            border-radius: 12px;
            color: #e2e8f0;
        }

        .code-example h3 {
            margin: 0 0 1rem;
            color: #f1f5f9;
        }

        .code-example pre {
            margin: 0;
            overflow-x: auto;
        }

        .code-example code {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="/" style="color:inherit;text-decoration:none;">PHP Palm</a></h1>
        <nav>
            <a href="/" class="<?= ($currentPath ?? '/') === '/' ? 'is-active' : '' ?>">Home</a>
            <a href="/about" class="<?= ($currentPath ?? '') === '/about' ? 'is-active' : '' ?>">About</a>
            <a href="/contact" class="<?= ($currentPath ?? '') === '/contact' ? 'is-active' : '' ?>">Contact</a>
            <a href="/demo" class="<?= ($currentPath ?? '') === '/demo' ? 'is-active' : '' ?>">Demo</a>
        </nav>
    </header>
    <main>
        <?= $content ?? '' ?>
    </main>
    <footer>
        <p>Built with ❤️ using <strong>PHP Palm</strong> · Modern PHP framework</p>
        <p style="font-size:0.85rem;margin-top:0.5rem;color:#94a3b8;">
            <a href="/" style="color:inherit;">Home</a> · 
            <a href="/about" style="color:inherit;">About</a> · 
            <a href="/contact" style="color:inherit;">Contact</a>
        </p>
    </footer>
</body>
</html>