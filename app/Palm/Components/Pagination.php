<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Pagination Component
 * 
 * Usage: component('Pagination', ['meta' => $meta, 'options' => []])
 */
class Pagination extends Component
{
    protected function renderComponent(): string
    {
        $meta = $this->prop('meta', []);
        $options = $this->prop('options', []);

        // Extract meta values with defaults
        $total = $meta['total'] ?? 0;
        $page = $meta['page'] ?? 1;
        $perPage = $meta['per_page'] ?? 10;
        $lastPage = $meta['last_page'] ?? 1;
        $from = $meta['from'] ?? null;
        $to = $meta['to'] ?? null;
        $hasMore = $meta['has_more'] ?? false;

        // Options
        $baseUrl = $options['base_url'] ?? '';
        $showInfo = $options['show_info'] ?? true;
        $showPerPage = $options['show_per_page'] ?? true;
        $perPageOptions = $options['per_page_options'] ?? [10, 25, 50, 100];
        $maxButtons = $options['max_buttons'] ?? 5;

        // Don't render if no results or only one page
        if ($total === 0) {
            return '<div class="pagination-empty">No results found</div>';
        }

        // Build base URL with existing query params (excluding page/per_page)
        $queryParams = $_GET;
        unset($queryParams['page'], $queryParams['per_page']);
        $baseQueryString = http_build_query($queryParams);
        $urlPrefix = $baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $urlPrefix .= $baseQueryString ? '?' . $baseQueryString . '&' : '?';

        // Calculate visible page range
        $startPage = max(1, $page - floor($maxButtons / 2));
        $endPage = min($lastPage, $startPage + $maxButtons - 1);
        if ($endPage - $startPage < $maxButtons - 1) {
            $startPage = max(1, $endPage - $maxButtons + 1);
        }

        ob_start();
?>
        <div class="palm-pagination">
            <?php if ($showInfo && $from !== null): ?>
                <div class="pagination-info">
                    Showing <strong><?= $from ?></strong> to <strong><?= $to ?></strong> of <strong><?= number_format($total) ?></strong> results
                </div>
            <?php endif; ?>

            <div class="pagination-controls">
                <?php if ($showPerPage): ?>
                    <div class="pagination-per-page">
                        <label>Show:</label>
                        <select onchange="window.location.href='<?= $urlPrefix ?>per_page=' + this.value + '&page=1'">
                            <?php foreach ($perPageOptions as $option): ?>
                                <option value="<?= $option ?>" <?= $perPage == $option ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="pagination-buttons">
                    <?php // First & Previous 
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= $urlPrefix ?>page=1&per_page=<?= $perPage ?>" class="pagination-btn pagination-first" title="First page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="11 17 6 12 11 7"></polyline>
                                <polyline points="18 17 13 12 18 7"></polyline>
                            </svg>
                        </a>
                        <a href="<?= $urlPrefix ?>page=<?= $page - 1 ?>&per_page=<?= $perPage ?>" class="pagination-btn pagination-prev" title="Previous">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                            <span>Prev</span>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-first disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="11 17 6 12 11 7"></polyline>
                                <polyline points="18 17 13 12 18 7"></polyline>
                            </svg>
                        </span>
                        <span class="pagination-btn pagination-prev disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                            <span>Prev</span>
                        </span>
                    <?php endif; ?>

                    <?php // Page numbers 
                    ?>
                    <div class="pagination-pages">
                        <?php if ($startPage > 1): ?>
                            <a href="<?= $urlPrefix ?>page=1&per_page=<?= $perPage ?>" class="pagination-btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= $urlPrefix ?>page=<?= $i ?>&per_page=<?= $perPage ?>" class="pagination-btn"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $lastPage): ?>
                            <?php if ($endPage < $lastPage - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="<?= $urlPrefix ?>page=<?= $lastPage ?>&per_page=<?= $perPage ?>" class="pagination-btn"><?= $lastPage ?></a>
                        <?php endif; ?>
                    </div>

                    <?php // Next & Last 
                    ?>
                    <?php if ($hasMore): ?>
                        <a href="<?= $urlPrefix ?>page=<?= $page + 1 ?>&per_page=<?= $perPage ?>" class="pagination-btn pagination-next" title="Next">
                            <span>Next</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </a>
                        <a href="<?= $urlPrefix ?>page=<?= $lastPage ?>&per_page=<?= $perPage ?>" class="pagination-btn pagination-last" title="Last page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="13 17 18 12 13 7"></polyline>
                                <polyline points="6 17 11 12 6 7"></polyline>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-next disabled">
                            <span>Next</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </span>
                        <span class="pagination-btn pagination-last disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="13 17 18 12 13 7"></polyline>
                                <polyline points="6 17 11 12 6 7"></polyline>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .palm-pagination {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1rem 0;
                margin-top: 1.5rem;
                border-top: 1px solid var(--color-border, #e5e7eb);
            }

            .pagination-info {
                color: var(--color-text-light, #6b7280);
                font-size: 0.875rem;
            }

            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 1rem;
                flex-wrap: wrap;
            }

            .pagination-per-page {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: var(--color-text-light, #6b7280);
            }

            .pagination-per-page select {
                padding: 0.375rem 0.75rem;
                border: 1px solid var(--color-border, #d1d5db);
                border-radius: 0.375rem;
                background: var(--color-surface, #fff);
                color: var(--color-text, #374151);
                font-size: 0.875rem;
                cursor: pointer;
                transition: border-color 0.2s, box-shadow 0.2s;
            }

            .pagination-per-page select:hover {
                border-color: var(--color-primary, #10b981);
            }

            .pagination-per-page select:focus {
                outline: none;
                border-color: var(--color-primary, #10b981);
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            }

            .pagination-buttons {
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }

            .pagination-pages {
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }

            .pagination-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.25rem;
                min-width: 2.25rem;
                height: 2.25rem;
                padding: 0 0.5rem;
                border: 1px solid var(--color-border, #d1d5db);
                border-radius: 0.375rem;
                background: var(--color-surface, #fff);
                color: var(--color-text, #374151);
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
            }

            .pagination-btn:hover:not(.disabled):not(.active) {
                border-color: var(--color-primary, #10b981);
                color: var(--color-primary, #10b981);
                background: rgba(16, 185, 129, 0.05);
            }

            .pagination-btn.active {
                background: var(--color-primary, #10b981);
                border-color: var(--color-primary, #10b981);
                color: white;
            }

            .pagination-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                background: var(--color-bg-alt, #f3f4f6);
            }

            .pagination-ellipsis {
                padding: 0 0.5rem;
                color: var(--color-text-muted, #9ca3af);
            }

            .pagination-prev span,
            .pagination-next span {
                display: none;
            }

            @media (min-width: 640px) {

                .pagination-prev span,
                .pagination-next span {
                    display: inline;
                }
            }

            @media (max-width: 639px) {
                .palm-pagination {
                    justify-content: center;
                }

                .pagination-info {
                    width: 100%;
                    text-align: center;
                }

                .pagination-first,
                .pagination-last {
                    display: none;
                }
            }

            .pagination-empty {
                text-align: center;
                padding: 2rem;
                color: var(--color-text-muted, #9ca3af);
                font-size: 0.875rem;
            }
        </style>
<?php
        return ob_get_clean();
    }
}
