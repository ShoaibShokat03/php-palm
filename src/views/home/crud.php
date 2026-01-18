<?php

/** @phpstan-ignore-file */

// Include pagination component
// require_once dirname(__DIR__, 2) . '/components/pagination.php';

?>

<div class="content-section">
    <div class="page-header">
        <h1>Users</h1>
        <p class="lead">Manage and view all users in the system</p>
    </div>

    <?php // Search and Filter Controls 
    ?>
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <div class="search-box">
                <input type="text"
                    name="search"
                    placeholder="Search users..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="search-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </div>

            <select name="role" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="user" <?= ($_GET['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                <option value="guest" <?= ($_GET['role'] ?? '') === 'guest' ? 'selected' : '' ?>>Guest</option>
                <option value="manager" <?= ($_GET['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                <option value="editor" <?= ($_GET['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
            </select>
            <span>
                <strong>Total: <?= $meta['total'] ?? 0 ?></strong>
            </span>
            <input type="hidden" name="per_page" value="<?= $_GET['per_page'] ?? 10 ?>">
        </form>
    </div>

    <?php // Users Table 
    ?>
    <div class="users-table-container">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>No users found</h3>
                <p>Try adjusting your search or filter criteria</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="th-narrow">#</th>
                        <th>
                            <a href="?sort=name&order=<?= ($_GET['sort'] ?? '') === 'name' && ($_GET['order'] ?? 'desc') === 'asc' ? 'desc' : 'asc' ?>&<?= http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])) ?>" class="sort-link">
                                Name
                                <?php if (($_GET['sort'] ?? '') === 'name'): ?>
                                    <span class="sort-icon"><?= ($_GET['order'] ?? 'desc') === 'asc' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=email&order=<?= ($_GET['sort'] ?? '') === 'email' && ($_GET['order'] ?? 'desc') === 'asc' ? 'desc' : 'asc' ?>&<?= http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])) ?>" class="sort-link">
                                Email
                                <?php if (($_GET['sort'] ?? '') === 'email'): ?>
                                    <span class="sort-icon"><?= ($_GET['order'] ?? 'desc') === 'asc' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Role</th>
                        <th class="th-narrow">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $srNo = ($meta['from'] ?? 1) - 1;
                    foreach ($users as $user):
                        $srNo++;
                    ?>
                        <tr>
                            <td class="td-narrow"><?= $srNo ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></div>
                                    <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></span>
                                </div>
                            </td>
                            <td class="td-email"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td>
                                <?php $role = $user['role'] ?? 'user'; ?>
                                <span class="status-badge status-<?= $role ?>"><?= ucfirst($role) ?></span>
                            </td>
                            <td class="td-actions">
                                <button class="action-btn" title="View">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button class="action-btn" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php // Render pagination 
            ?>
            <?php if (isset($meta)): ?>
                <?= component('Pagination', ['meta' => $meta]) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Page Header */
    .page-header {
        margin-bottom: 1.5rem;
    }

    .page-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text, #1f2937);
        margin: 0;
    }

    .page-header .lead {
        color: var(--color-text-light, #6b7280);
        margin: 0.25rem 0 0;
    }

    /* Filter Bar */
    .filter-bar {
        margin-bottom: 1.5rem;
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .search-box {
        display: flex;
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 320px;
    }

    .search-box input {
        flex: 1;
        padding: 0.625rem 2.5rem 0.625rem 0.875rem;
        border: 1px solid var(--color-border, #d1d5db);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        background: var(--color-surface, #fff);
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--color-primary, #10b981);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .search-btn {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--color-text-muted, #9ca3af);
        cursor: pointer;
        padding: 0.25rem;
    }

    .search-btn:hover {
        color: var(--color-primary, #10b981);
    }

    .filter-form select {
        padding: 0.625rem 0.875rem;
        border: 1px solid var(--color-border, #d1d5db);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        background: var(--color-surface, #fff);
        cursor: pointer;
        transition: border-color 0.2s;
    }

    .filter-form select:focus {
        outline: none;
        border-color: var(--color-primary, #10b981);
    }

    /* Table Container */
    .users-table-container {
        background: var(--color-surface, #fff);
        border-radius: 0.75rem;
        border: 1px solid var(--color-border, #e5e7eb);
        overflow: hidden;
    }

    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 0.875rem 1rem;
        text-align: left;
        border-bottom: 1px solid var(--color-border, #e5e7eb);
    }

    .data-table th {
        background: var(--color-bg-alt, #f9fafb);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-text-muted, #6b7280);
    }

    .data-table tbody tr:hover {
        background: var(--color-bg-alt, #f9fafb);
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .th-narrow,
    .td-narrow {
        width: 60px;
        text-align: center;
    }

    /* Sort Links */
    .sort-link {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        color: inherit;
        text-decoration: none;
    }

    .sort-link:hover {
        color: var(--color-primary, #10b981);
    }

    .sort-icon {
        font-size: 0.75rem;
    }

    /* User Info */
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary, #10b981), var(--color-primary-dark, #059669));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .user-name {
        font-weight: 500;
    }

    .td-email {
        color: var(--color-text-light, #6b7280);
        font-size: 0.875rem;
    }

    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-inactive {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    /* Action Buttons */
    .td-actions {
        display: flex;
        gap: 0.25rem;
    }

    .action-btn {
        padding: 0.375rem;
        border: none;
        background: transparent;
        color: var(--color-text-muted, #9ca3af);
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .action-btn:hover {
        background: var(--color-bg-alt, #f3f4f6);
        color: var(--color-primary, #10b981);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--color-text-muted, #9ca3af);
    }

    .empty-state svg {
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text, #374151);
        margin: 0 0 0.25rem;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .data-table {
            display: block;
            overflow-x: auto;
        }

        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            max-width: none;
        }
    }
</style>