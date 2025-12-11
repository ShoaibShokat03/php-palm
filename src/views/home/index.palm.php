<?php

/** @phpstan-ignore-file */

$pageTitle = 'Home';
?>

<div class="content-section">
    <h1><?= htmlspecialchars($title ?? 'Welcome to PHP Palm!') ?></h1>
    <p class="lead"><?= htmlspecialchars($message ?? 'Build PHP applications with clean, simple code.') ?></p>

    <div class="demo-section">
        <h3>Getting Started</h3>
        <p>This is your PHP Palm frontend. Edit this file at <code>src/views/home/index.palm.php</code> to customize it.</p>
        <p>Your routes are defined in <code>src/routes/main.php</code> and the layout is in <code>src/layouts/main.php</code>.</p>
    </div>

    <div class="demo-section">
        <h3>Quick Links</h3>
        <ul>
            <li><a href="/about">About</a> - Learn more about PHP Palm</li>
            <li><a href="/contact">Contact</a> - Get in touch</li>
            <li><a href="/demo">Demo</a> - See examples</li>
        </ul>
    </div>
</div>