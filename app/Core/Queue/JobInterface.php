<?php

namespace App\Core\Queue;

/**
 * Job Interface
 * All jobs must implement this interface
 */
interface JobInterface
{
    /**
     * Execute the job
     */
    public function handle(array $data): void;
}

