<?php

namespace App\Core\Api;

use PhpPalm\Core\Request;

/**
 * API Pagination Helper
 * 
 * Features:
 * - Automatic pagination
 * - URL generation
 * - Metadata included
 */
class Paginator
{
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected string $path;

    public function __construct(array $items, int $total, int $perPage, int $currentPage, string $path = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->path = $path ?? Request::path();
    }

    /**
     * Create paginator from query builder result
     */
    public static function fromQuery($query, int $perPage = 15, int $page = null): self
    {
        $page = $page ?? (int)(Request::get('page', 1));
        $perPage = (int)(Request::get('per_page', $perPage));
        
        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->limit($perPage)->all();
        
        return new self($items, $total, $perPage, $page);
    }

    /**
     * Get paginated data
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'pagination' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage(),
                'from' => $this->from(),
                'to' => $this->to(),
                'has_more' => $this->hasMorePages()
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl()
            ]
        ];
    }

    /**
     * Get last page number
     */
    public function lastPage(): int
    {
        return max(1, (int)ceil($this->total / $this->perPage));
    }

    /**
     * Get from index
     */
    public function from(): ?int
    {
        if ($this->total === 0) {
            return null;
        }
        return (($this->currentPage - 1) * $this->perPage) + 1;
    }

    /**
     * Get to index
     */
    public function to(): ?int
    {
        if ($this->total === 0) {
            return null;
        }
        return min($this->total, $this->currentPage * $this->perPage);
    }

    /**
     * Check if has more pages
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Get URL for page
     */
    public function url(int $page): ?string
    {
        if ($page < 1 || $page > $this->lastPage()) {
            return null;
        }

        $query = $_GET;
        $query['page'] = $page;
        $queryString = http_build_query($query);
        
        return $this->path . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Get previous page URL
     */
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }
        return $this->url($this->currentPage - 1);
    }

    /**
     * Get next page URL
     */
    public function nextPageUrl(): ?string
    {
        if (!$this->hasMorePages()) {
            return null;
        }
        return $this->url($this->currentPage + 1);
    }
}

