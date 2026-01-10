<?php

namespace App\Database;

/**
 * Base Seeder Class
 * Use for database seeding
 */
abstract class Seeder
{
    /**
     * Run the database seeds
     */
    abstract public function run(): void;

    /**
     * Call another seeder
     */
    protected function call(string $seederClass): void
    {
        $seeder = new $seederClass();
        $seeder->run();
    }

    /**
     * Get database connection
     */
    protected function db(): Db
    {
        return new Db();
    }
}
