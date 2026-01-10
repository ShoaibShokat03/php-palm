<div class="content-section">
    <div class="error-container" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--spacing-xl); margin: var(--spacing-xl) 0; text-align: center;">
        <div class="error-icon" style="font-size: 3rem; margin-bottom: var(--spacing-md);">⚠️</div>
        <h1 style="font-size: 1.5rem; margin-bottom: var(--spacing-sm); color: #b91c1c;"><?= htmlspecialchars($title ?? 'Error') ?></h1>
        <p style="color: var(--color-text-light); margin-bottom: var(--spacing-lg); line-height: 1.6;">
            <?= htmlspecialchars($message ?? 'An unexpected error occurred. Please try again later.') ?>
        </p>
        <div class="error-actions">
            <a href="/" class="btn-action">Return to Home</a>
        </div>
    </div>
</div>

<style>
    /* Override global card styles if necessary for this specific view */
    .error-container {
        box-shadow: none !important;
        transform: none !important;
    }
</style>