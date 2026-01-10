<?php

namespace App\Database;

/**
 * Migration Manager
 * Tracks and executes database migrations
 */
class MigrationManager
{
    protected Db $db;
    protected string $migrationsPath;
    protected string $tableName = 'migrations_tracker';

    public function __construct()
    {
        $this->db = new Db();
        $this->migrationsPath = dirname(dirname(__DIR__)) . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Ensure migrations tracking table exists
     */
    protected function ensureMigrationsTable(): void
    {
        if (Schema::hasTable($this->tableName)) {
            return;
        }

        $sql = "CREATE TABLE `{$this->tableName}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $this->db->query($sql);
    }

    /**
     * Get all migration files
     */
    public function getAllMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        return array_map(function ($file) {
            return basename($file, '.php');
        }, $files);
    }

    /**
     * Get pending migrations
     */
    public function getPendingMigrations(): array
    {
        $all = $this->getAllMigrations();
        $ran = $this->getRanMigrations();

        return array_diff($all, $ran);
    }

    /**
     * Get ran migrations
     */
    public function getRanMigrations(): array
    {
        $result = $this->db->query("SELECT migration FROM `{$this->tableName}` ORDER BY batch, id");

        $migrations = [];
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row['migration'];
        }

        return $migrations;
    }

    /**
     * Get last batch number
     */
    protected function getNextBatchNumber(): int
    {
        $result = $this->db->query("SELECT MAX(batch) as max_batch FROM `{$this->tableName}`");
        $row = $result->fetch_assoc();

        return ($row['max_batch'] ?? 0) + 1;
    }

    /**
     * Run pending migrations
     */
    public function migrate(): array
    {
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $migrated = [];

        foreach ($pending as $migration) {
            try {
                $this->runMigration($migration, $batch);
                $migrated[] = $migration;
            } catch (\Exception $e) {
                throw new \Exception("Migration failed: {$migration}\n" . $e->getMessage());
            }
        }

        return $migrated;
    }

    /**
     * Run a single migration
     */
    protected function runMigration(string $migration, int $batch): void
    {
        $file = $this->migrationsPath . '/' . $migration . '.php';

        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }

        /** @var Migration $instance */
        $instance = require $file;

        if (!$instance instanceof Migration) {
            throw new \Exception("Migration file must return a Migration instance");
        }

        // Run the migration
        $instance->execute();

        // Record in database
        $this->db->query("INSERT INTO `{$this->tableName}` (migration, batch) VALUES ('{$migration}', {$batch})");
    }

    /**
     * Rollback last batch
     */
    public function rollback(): array
    {
        $result = $this->db->query("SELECT MAX(batch) as last_batch FROM `{$this->tableName}`");
        $row = $result->fetch_assoc();
        $lastBatch = $row['last_batch'] ?? 0;

        if ($lastBatch == 0) {
            return [];
        }

        $result = $this->db->query(
            "SELECT migration FROM `{$this->tableName}` 
             WHERE batch = {$lastBatch} 
             ORDER BY id DESC"
        );

        $rolledBack = [];

        while ($row = $result->fetch_assoc()) {
            $migration = $row['migration'];
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        // Delete from tracker
        $this->db->query("DELETE FROM `{$this->tableName}` WHERE batch = {$lastBatch}");

        return $rolledBack;
    }

    /**
     * Rollback a single migration
     */
    protected function rollbackMigration(string $migration): void
    {
        $file = $this->migrationsPath . '/' . $migration . '.php';

        /** @var Migration $instance */
        $instance = require $file;
        $instance->rollback();
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $all = [];

        while (true) {
            $rolledBack = $this->rollback();

            if (empty($rolledBack)) {
                break;
            }

            $all = array_merge($all, $rolledBack);
        }

        return $all;
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $all = $this->getAllMigrations();
        $ran = $this->getRanMigrations();

        $status = [];

        foreach ($all as $migration) {
            $status[] = [
                'migration' => $migration,
                'ran' => in_array($migration, $ran)
            ];
        }

        return $status;
    }
}
