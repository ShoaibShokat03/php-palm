<?php

/** @phpstan-ignore-file */

require_once dirname(__DIR__, 3) . '/app/Palm/helpers.php';

$prefill = $prefill ?? ['name' => '', 'message' => ''];
?>
<div class="content-section">
    <h1>Contact Us</h1>
    <p class="lead">Questions, feature ideas, or bug reports? Drop us a note.</p>
    
    <?php if (!empty($flash)): ?>
        <div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
            ✅ <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>
    
    <form action="/contact" method="post" style="max-width:600px;">
        <?= csrf_field() ?>
        
        <div style="margin-bottom:1.5rem;">
            <label for="name" style="display:block;margin-bottom:0.5rem;font-weight:500;">
                Name
            </label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($prefill['name'] ?? '') ?>" 
                   required
                   style="width:100%;padding:0.75rem;border-radius:6px;border:1px solid #d0d7e2;font-size:1rem;font-family:inherit;">
        </div>
        
        <div style="margin-bottom:1.5rem;">
            <label for="message" style="display:block;margin-bottom:0.5rem;font-weight:500;">
                Message
            </label>
            <textarea id="message" name="message" rows="5" required
                      style="width:100%;padding:0.75rem;border-radius:6px;border:1px solid #d0d7e2;font-size:1rem;font-family:inherit;resize:vertical;"><?= htmlspecialchars($prefill['message'] ?? '') ?></textarea>
        </div>
        
        <button type="submit" class="btn-action">
            Send Message
        </button>
    </form>
</div>