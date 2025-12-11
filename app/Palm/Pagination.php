<?php

namespace Frontend\Palm;

/**
 * Pagination Helper
 * 
 * Generates pagination links
 */
class Pagination
{
    /**
     * Generate pagination HTML
     */
    public static function render(int $currentPage, int $totalPages, array $options = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $baseUrl = $options['base_url'] ?? current_url();
        $pageParam = $options['page_param'] ?? 'page';
        $class = $options['class'] ?? 'pagination';
        $showFirstLast = $options['show_first_last'] ?? true;
        $showPrevNext = $options['show_prev_next'] ?? true;
        $maxLinks = $options['max_links'] ?? 5;

        // Remove existing page parameter from URL
        $parsedUrl = parse_url($baseUrl);
        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }
        unset($query[$pageParam]);
        
        $urlBase = ($parsedUrl['path'] ?? '/') . (!empty($query) ? '?' . http_build_query($query) . '&' : '?');

        $html = '<nav aria-label="Page navigation"><ul class="' . htmlspecialchars($class) . '">';

        // First page
        if ($showFirstLast && $currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . 
                     htmlspecialchars($urlBase . $pageParam . '=1') . '">First</a></li>';
        }

        // Previous page
        if ($showPrevNext && $currentPage > 1) {
            $prevPage = $currentPage - 1;
            $html .= '<li class="page-item"><a class="page-link" href="' . 
                     htmlspecialchars($urlBase . $pageParam . '=' . $prevPage) . '">Previous</a></li>';
        }

        // Calculate page range
        $startPage = max(1, $currentPage - floor($maxLinks / 2));
        $endPage = min($totalPages, $startPage + $maxLinks - 1);
        
        if ($endPage - $startPage < $maxLinks - 1) {
            $startPage = max(1, $endPage - $maxLinks + 1);
        }

        // Page numbers
        for ($page = $startPage; $page <= $endPage; $page++) {
            if ($page === $currentPage) {
                $html .= '<li class="page-item active"><span class="page-link" aria-current="page">' . 
                         $page . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . 
                         htmlspecialchars($urlBase . $pageParam . '=' . $page) . '">' . $page . '</a></li>';
            }
        }

        // Next page
        if ($showPrevNext && $currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            $html .= '<li class="page-item"><a class="page-link" href="' . 
                     htmlspecialchars($urlBase . $pageParam . '=' . $nextPage) . '">Next</a></li>';
        }

        // Last page
        if ($showFirstLast && $currentPage < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . 
                     htmlspecialchars($urlBase . $pageParam . '=' . $totalPages) . '">Last</a></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * Generate pagination info text
     */
    public static function info(int $currentPage, int $totalItems, int $perPage): string
    {
        $start = ($currentPage - 1) * $perPage + 1;
        $end = min($currentPage * $perPage, $totalItems);
        
        return "Showing {$start} to {$end} of {$totalItems} results";
    }

    /**
     * Calculate total pages
     */
    public static function totalPages(int $totalItems, int $perPage): int
    {
        return max(1, (int)ceil($totalItems / $perPage));
    }
}

