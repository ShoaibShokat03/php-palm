<?php

namespace Frontend\Palm;

/**
 * Asset Dependency Tree Loader
 * 
 * Automatically analyzes resource dependencies from Lighthouse reports
 * and helps the ProgressiveResourceLoader make intelligent decisions.
 */
class AssetDependencyTreeLoader
{
    protected string $reportFile;
    protected array $dependencyTree = [];
    protected array $criticalAssets = [];
    protected bool $dataLoaded = false;

    public function __construct(string $reportFile = null)
    {
        $this->reportFile = $reportFile ?? dirname(__DIR__, 2) . '/analyze_report.json';
        $this->loadData();
    }

    /**
     * Load Lighthouse report data and build the tree
     */
    protected function loadData(): void
    {
        if (!file_exists($this->reportFile)) {
            return;
        }

        $json = file_get_contents($this->reportFile);
        $data = json_decode($json, true);

        if (!$data || !isset($data['audits'])) {
            return;
        }

        $audits = $data['audits'];

        // Process simple network requests
        if (isset($audits['network-requests']['details']['items'])) {
            $this->processNetworkRequests($audits['network-requests']['details']['items']);
        }

        // Process dependency chains (more accurate for critical paths)
        if (isset($audits['network-dependency-tree-insight']['details']['items'])) {
            foreach ($audits['network-dependency-tree-insight']['details']['items'] as $item) {
                if (isset($item['value']['chains'])) {
                    $this->processChains($item['value']['chains']);
                }
            }
        }

        $this->dataLoaded = true;
    }

    /**
     * Process critical request chains
     */
    protected function processChains(array $chains): void
    {
        foreach ($chains as $chain) {
            $this->criticalAssets[] = $chain['url'];
            if (!empty($chain['children'])) {
                $this->processChains($chain['children']);
            }
        }
    }

    /**
     * Process network requests to categorize assets
     */
    protected function processNetworkRequests(array $items): void
    {
        foreach ($items as $item) {
            $url = $item['url'];
            $priority = $item['priority'] ?? 'Low';
            $resourceType = $item['resourceType'] ?? 'Other';

            // Critical assets are High/VeryHigh priority and typically Render-blocking types
            $isCriticalType = in_array($resourceType, ['Document', 'Stylesheet', 'Font', 'Script']);
            $isHighPriority = in_array($priority, ['High', 'VeryHigh']);

            if ($isHighPriority && $isCriticalType) {
                // Heuristic: If it's a script, check if it's likely critical
                // (Lighthouse doesn't explicitly flag render-blocking in this list, 
                // but we can infer from priority)
                $this->criticalAssets[] = $url;
            }

            $this->dependencyTree[$url] = [
                'priority' => $priority,
                'resourceType' => $resourceType,
                'transferSize' => $item['transferSize'] ?? 0,
                'mimeType' => $item['mimeType'] ?? '',
                'statusCode' => $item['statusCode'] ?? 200
            ];
        }
    }

    /**
     * Check if an asset is considered critical based on the report
     */
    public function isCritical(string $url): bool
    {
        foreach ($this->criticalAssets as $criticalUrl) {
            if (str_contains($url, $criticalUrl) || str_contains($criticalUrl, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get recommended assets to defer
     */
    public function getAssetsToDefer(): array
    {
        $toDefer = [];
        foreach ($this->dependencyTree as $url => $info) {
            // Defer if priority is Low/Medium and not already in critical list
            if (in_array($info['priority'], ['Low', 'Medium']) && !$this->isCritical($url)) {
                if (in_array($info['resourceType'], ['Script', 'Stylesheet', 'Image'])) {
                    $toDefer[] = [
                        'url' => $url,
                        'type' => strtolower($info['resourceType'] === 'Stylesheet' ? 'stylesheet' : ($info['resourceType'] === 'Script' ? 'script' : 'image'))
                    ];
                }
            }
        }
        return $toDefer;
    }

    /**
     * Get all detected assets
     */
    public function getDependencyTree(): array
    {
        return $this->dependencyTree;
    }

    /**
     * Is the dependency data available?
     */
    public function hasData(): bool
    {
        return $this->dataLoaded;
    }
}
