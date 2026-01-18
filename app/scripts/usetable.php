<?php

/**
 * Generate Module from Database Table
 */

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/ApiDocGenerator.php';
require_once __DIR__ . '/TableModuleGenerator.php';

use App\Database\Db;

// Load environment logic (simplified for script)
$envPath = __DIR__ . '/../../config';
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();
} elseif (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

if ($argc < 2) {
    echo "Usage: palm make usetable <table_name>\n";
    exit(1);
}

// Connect to DB
$db = new Db();
$db->connect();

$arg = trim($argv[1]);

if ($arg === 'all') {
    // Batch logic
    $result = $db->query("SHOW TABLES");
    $tables = [];
    if ($result) {
        $tableKey = "Tables_in_{$db->db_name}";
        while ($row = $result->fetch_assoc()) $tables[] = $row[$tableKey];
    }

    foreach ($tables as $table) {
        $moduleName = TableModuleGenerator::tableToModuleName($table);
        $modulePath = __DIR__ . '/../../modules/' . $moduleName;
        if (!is_dir($modulePath)) {
            TableModuleGenerator::generate($db, $table, $moduleName);
        }
    }
} else {
    $tableName = $arg;
    $moduleName = TableModuleGenerator::tableToModuleName($tableName);

    // Check if module already exists
    $modulePath = __DIR__ . '/../../modules/' . $moduleName;
    if (is_dir($modulePath)) {
        echo "Error: Module '$moduleName' already exists.\n";
        exit(1);
    }

    TableModuleGenerator::generate($db, $tableName, $moduleName);
}
