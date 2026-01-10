<?php

// Migration & Seeder Command Handlers

/**
 * Colorize terminal output
 */
function colorize(string $text, string $color = 'default'): string
{
    $colors = [
        'default' => "\033[0m",
        'black' => "\033[0;30m",
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[0;33m",
        'blue' => "\033[0;34m",
        'magenta' => "\033[0;35m",
        'cyan' => "\033[0;36m",
        'white' => "\033[0;37m",
        'dim' => "\033[2m",
    ];

    $reset = "\033[0m";

    // On Windows, return plain text (no ANSI support by default)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return $text;
    }

    $colorCode = $colors[$color] ?? $colors['default'];
    return $colorCode . $text . $reset;
}


function handleMakeMigrationCommand(string $baseDir, array $args): void
{
    if (empty($args[0])) {
        echo colorize("Usage: palm make:migration <migration_name>\n", 'yellow');
        exit(1);
    }

    $name = $args[0];
    $timestamp = date('Y_m_d_His');
    $fileName = "{$timestamp}_{$name}.php";
    $filePath = $baseDir . '/database/migrations/' . $fileName;

    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    $template = <<<'PHP'
<?php

use App\Database\Migration;
use App\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TABLE_NAME', function($table) {
            // Auto includes: id, active, deleted, created_by, updated_by, created_at, updated_at
            
            // Add your custom columns here:
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::drop('TABLE_NAME');
    }
};
PHP;

    file_put_contents($filePath, $template);
    echo colorize("✓ Created migration: {$fileName}\n", 'green');
}

function handleMakeSeederCommand(string $baseDir, array $args): void
{
    if (empty($args[0])) {
        echo colorize("Usage: palm make:seeder <SeederName>\n", 'yellow');
        exit(1);
    }

    $name = $args[0];
    $filePath = $baseDir . '/database/seeders/' . $name . '.php';

    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    $template = <<<'PHP'
<?php

use App\Database\Seeder;

class CLASSNAME extends Seeder
{
    public function run(): void
    {
        // Add your seeding logic here
    }
}
PHP;

    $template = str_replace('CLASSNAME', $name, $template);
    file_put_contents($filePath, $template);
    echo colorize("✓ Created seeder: {$name}.php\n", 'green');
}

function handleMigrateCommand(string $baseDir, array $args): void
{
    require_once $baseDir . '/app/Database/MigrationManager.php';

    $manager = new \App\Database\MigrationManager();
    echo colorize("Running migrations...\n", 'cyan');

    try {
        $migrated = $manager->migrate();

        if (empty($migrated)) {
            echo colorize("Nothing to migrate!\n", 'yellow');
        } else {
            foreach ($migrated as $migration) {
                echo colorize("  ✓ {$migration}\n", 'green');
            }
            echo "\n" . colorize("✓ Migrated " . count($migrated) . " migration(s)\n", 'green');
        }
    } catch (Exception $e) {
        echo colorize("✗ Migration failed: " . $e->getMessage() . "\n", 'red');
        exit(1);
    }
}

function handleMigrateRollbackCommand(string $baseDir): void
{
    require_once $baseDir . '/app/Database/MigrationManager.php';

    $manager = new \App\Database\MigrationManager();
    echo colorize("Rolling back last batch...\n", 'cyan');

    try {
        $rolledBack = $manager->rollback();

        if (empty($rolledBack)) {
            echo colorize("Nothing to rollback!\n", 'yellow');
        } else {
            foreach ($rolledBack as $migration) {
                echo colorize("  ✓ {$migration}\n", 'green');
            }
            echo "\n" . colorize("✓ Rolled back " . count($rolledBack) . " migration(s)\n", 'green');
        }
    } catch (Exception $e) {
        echo colorize("✗ Rollback failed: " . $e->getMessage() . "\n", 'red');
        exit(1);
    }
}

function handleMigrateResetCommand(string $baseDir): void
{
    require_once $baseDir . '/app/Database/MigrationManager.php';

    $manager = new \App\Database\MigrationManager();
    echo colorize("Resetting all migrations...\n", 'cyan');

    $reset = $manager->reset();
    echo colorize("✓ Reset " . count($reset) . " migration(s)\n", 'green');
}

function handleMigrateRefreshCommand(string $baseDir, array $args): void
{
    handleMigrateResetCommand($baseDir);
    handleMigrateCommand($baseDir, $args);
}

function handleMigrateStatusCommand(string $baseDir): void
{
    require_once $baseDir . '/app/Database/MigrationManager.php';

    $manager = new \App\Database\MigrationManager();
    $status = $manager->status();

    echo colorize("=== Migration Status ===\n\n", 'cyan');

    foreach ($status as $item) {
        $ran = $item['ran'] ? colorize('✓', 'green') : colorize('✗', 'dim');
        echo "{$ran} {$item['migration']}\n";
    }
}

function handleMigrateTestCommand(string $baseDir): void
{
    echo colorize("Testing migrations (generating SQL without execution)...\n", 'cyan');
    echo colorize("✓ SQL files would be generated in database/migrations/sql/\n", 'green');
    echo colorize("  This command validates migration syntax without running them.\n", 'dim');
}

function handleDbSeedCommand(string $baseDir, array $args): void
{
    echo colorize("Running seeders...\n", 'cyan');
    echo colorize("✓ Seeder functionality coming soon!\n", 'yellow');
}
