<?php

namespace App\Core\Api;

use PhpPalm\Core\Request;

/**
 * API Filter Helper
 * 
 * Features:
 * - Automatic filtering
 * - Sorting
 * - Search
 */
class Filter
{
    protected array $filters = [];
    protected array $sort = [];
    protected ?string $search = null;
    protected array $searchFields = [];

    /**
     * Create filter from request
     */
    public static function fromRequest(array $searchFields = []): self
    {
        $filter = new self();
        
        // Get filters from query string
        $query = Request::get();
        foreach ($query as $key => $value) {
            if (!in_array($key, ['page', 'per_page', 'sort', 'search', 'order'])) {
                $filter->filters[$key] = $value;
            }
        }

        // Get sort
        $sort = Request::get('sort');
        $order = Request::get('order', 'asc');
        if ($sort) {
            $filter->sort = [$sort => strtolower($order)];
        }

        // Get search
        $search = Request::get('search');
        if ($search && !empty($searchFields)) {
            $filter->search = $search;
            $filter->searchFields = $searchFields;
        }

        return $filter;
    }

    /**
     * Apply filters to query builder
     */
    public function apply($query)
    {
        // Apply filters
        foreach ($this->filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        // Apply search
        if ($this->search && !empty($this->searchFields)) {
            $query->search($this->search, $this->searchFields);
        }

        // Apply sorting
        foreach ($this->sort as $field => $direction) {
            $query->orderBy($field, strtoupper($direction));
        }

        return $query;
    }

    /**
     * Get filters
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get sort
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * Get search term
     */
    public function getSearch(): ?string
    {
        return $this->search;
    }
}

