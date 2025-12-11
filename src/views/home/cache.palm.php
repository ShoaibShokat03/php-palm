<?php

/** @phpstan-ignore-file */

$title = $title ?? 'Palm Cache Manager';
$summary = $summary ?? [];
$recentFiles = $recentFiles ?? [];
$message = $message ?? null;
?>
<div class="content-section">
    <h1>Palm Cache Manager</h1>
    <p class="lead">Manage cached assets and improve performance.</p>

    <?php if ($message): ?>
        <div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
            ✅ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($summary)): ?>
        <div class="demo-section">
            <h3>Cache Summary</h3>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($summary as $key => $value): ?>
                    <li style="padding:0.75rem 0;border-bottom:1px solid rgba(15,23,42,0.1);">
                        <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($recentFiles)): ?>
        <div class="demo-section">
            <h3>Recent Cached Files</h3>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($recentFiles as $file): ?>
                    <li style="padding:0.5rem 0;border-bottom:1px solid rgba(15,23,42,0.1);font-family:monospace;font-size:0.9rem;">
                        <?= htmlspecialchars($file) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="demo-section">
        <h3>Cache Actions</h3>
        <form action="/cache-clear" method="post" style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="cache">
            <button type="submit" name="target" value="all" class="btn-action">Clear All Cache</button>
            <button type="submit" name="target" value="views" class="btn-action-secondary">Clear Views Only</button>
            <button type="submit" name="target" value="assets" class="btn-action-secondary">Clear Assets Only</button>
        </form>
    </div>
</div>