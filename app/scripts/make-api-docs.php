<?php

/**
 * Make API Docs Command
 * Usage: palm make api docs <ModuleName>
 */

require_once __DIR__ . '/ApiDocGenerator.php';

if ($argc < 3) {
    echo "\n";
    echo "Error: Module name is required\n";
    echo "Usage: palm make api docs <ModuleName>\n";
    echo "\n";
    exit(1);
}

// Args:
// 0: make-api-docs.php
// 1: api
// 2: docs
// 3: <ModuleName> 
// Wait, passed from palm.php?
// Implementation in palm.php:
// runPhpScript('make-api-docs.php', $args);
// If 'make api docs Users', then args might be ['api', 'docs', 'Users']?
// I need to check how palm.php handles 'make'.
// In palm.php:
// $makeTarget = strtolower($argv[2]); // 'api'
// $makeArgs = array_slice($argv, 3); // ['docs', 'Users']
// So make-api-docs.php receives ['docs', 'Users'].
// argv[1] = docs
// argv[2] = Users

$moduleName = $argv[2] ?? null;

if (!$moduleName) {
    echo "Error: Module name is required\n";
    echo "Usage: palm make api docs <ModuleName>\n";
    exit(1);
}

// Validation
if (!preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
    echo "Error: Invalid module name\n";
    exit(1);
}

$moduleName = ucfirst($moduleName);
$modulePath = __DIR__ . '/../../modules/' . $moduleName;

if (!is_dir($modulePath)) {
    echo "Error: Module '$moduleName' not found at $modulePath\n";
    exit(1);
}

// Try to detect route prefix from Module.php?
// Or just default to /modulename
$routePrefix = '/' . strtolower($moduleName);

// Check if Module.php exists to be smarter?
$moduleFile = $modulePath . '/Module.php';
if (file_exists($moduleFile)) {
    // Simple regex to find route prefix passed to parent constructor
    // parent::__construct('ModuleName', '/prefix')
    $content = file_get_contents($moduleFile);
    if (preg_match("/parent::__construct\('[^']+',\s*'([^']+)'\)/", $content, $matches)) {
        $routePrefix = $matches[1];
    }
}

echo "Generating API Docs for Module: $moduleName (Route Prefix: $routePrefix)...\n";

if (class_exists('ApiDocGenerator')) {
    ApiDocGenerator::generate($modulePath, $moduleName, $routePrefix);
    echo "✅ API Docs generated successfully!\n";
} else {
    echo "Error: ApiDocGenerator class not found.\n";
    exit(1);
}
