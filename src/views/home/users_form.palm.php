<?php

/** @var array $user */
/** @var bool $isEdit */
/** @var string $title */

use App\Core\App;
?>

<div class="content-section">
    <div class="page-header">
        <h1><?= $title ?></h1>
        <p class="lead"><?= $isEdit ? 'Update user details' : 'Add a new user to the system' ?></p>
    </div>

    <div class="form-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $isEdit ? App::route('/users/' . $user['id'] . '/update') : App::route('/users') ?>" class="user-form">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required placeholder="e.g. John Doe">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required placeholder="e.g. john@example.com">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <?php
                    $currentRole = $user['role'] ?? 'user';
                    $roles = ['user', 'admin', 'manager', 'editor', 'guest'];
                    foreach ($roles as $role): ?>
                        <option value="<?= $role ?>" <?= $currentRole === $role ? 'selected' : '' ?>>
                            <?= ucfirst($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <a href="<?= App::route('/users') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update User' : 'Create User' ?></button>
            </div>
        </form>
    </div>
</div>

<style>
    .form-container {
        background: var(--color-surface, #fff);
        padding: 2rem;
        border-radius: 0.75rem;
        border: 1px solid var(--color-border, #e5e7eb);
        max-width: 600px;
    }

    .user-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 500;
        font-size: 0.875rem;
        color: var(--color-text, #374151);
    }

    .form-group input,
    .form-group select {
        padding: 0.75rem;
        border: 1px solid var(--color-border, #d1d5db);
        border-radius: 0.5rem;
        font-size: 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--color-primary, #10b981);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        text-decoration: none;
        text-align: center;
        transition: opacity 0.2s;
    }

    .btn:hover {
        opacity: 0.9;
    }

    .btn-primary {
        background: var(--color-primary, #10b981);
        color: white;
        flex: 1;
    }

    .btn-secondary {
        background: var(--color-bg-alt, #f3f4f6);
        color: var(--color-text, #374151);
    }

    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }

    .alert-error {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
</style>