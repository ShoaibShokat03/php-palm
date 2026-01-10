<?php

namespace App\Database;

/**
 * Base Migration Class
 * All migrations extend this class
 */
abstract class Migration
{
    /**
     * Run the migration (create tables, add columns, etc.)
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (drop tables, remove columns, etc.)
     */
    abstract public function down(): void;

    /**
     * Hook: Before running up()
     */
    protected function beforeUp(): void
    {
        // Override in child class if needed
    }

    /**
     * Hook: After running up()
     */
    protected function afterUp(): void
    {
        // Override in child class if needed
    }

    /**
     * Hook: Before running down()
     */
    protected function beforeDown(): void
    {
        // Override in child class if needed
    }

    /**
     * Hook: After running down()
     */
    protected function afterDown(): void
    {
        // Override in child class if needed
    }

    /**
     * Run a seeder after migration
     */
    protected function seed(string $seederClass): void
    {
        $seeder = new $seederClass();
        $seeder->run();
    }

    /**
     * Execute migration with hooks
     */
    public function execute(): void
    {
        $this->beforeUp();
        $this->up();
        $this->afterUp();
    }

    /**
     * Rollback migration with hooks
     */
    public function rollback(): void
    {
        $this->beforeDown();
        $this->down();
        $this->afterDown();
    }
}
