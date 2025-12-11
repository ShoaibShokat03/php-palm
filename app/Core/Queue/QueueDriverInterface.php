<?php

namespace App\Core\Queue;

/**
 * Queue Driver Interface
 */
interface QueueDriverInterface
{
    public function push(string $job, array $data, int $priority, int $delay): string;
    public function pop(): ?array;
    public function markCompleted(string $jobId): void;
    public function markFailed(string $jobId, string $error): void;
    public function retry(string $jobId, int $attempts, int $delay): void;
}

