<?php

namespace App\Core\Queue;

use App\Core\Logger;
use App\Database\Db;

/**
 * Queue System for Background Jobs
 * 
 * Features:
 * - Job queuing (emails, reports, notifications)
 * - Retry & failure handling
 * - Multiple queue drivers (database, file)
 * - Job priorities
 */
class Queue
{
    protected static Queue $instance;
    protected QueueDriverInterface $driver;
    protected int $maxRetries = 3;
    protected int $retryDelay = 60; // seconds

    public function __construct()
    {
        // Use database queue by default (most reliable)
        $this->driver = new DatabaseQueue();
    }

    public static function getInstance(): Queue
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Push job to queue
     */
    public function push(string $job, array $data = [], int $priority = 0, int $delay = 0): string
    {
        $jobId = $this->driver->push($job, $data, $priority, $delay);
        Logger::infoStatic('Job queued', ['job' => $job, 'job_id' => $jobId]);
        return $jobId;
    }

    /**
     * Process next job
     */
    public function process(): ?array
    {
        $job = $this->driver->pop();
        
        if ($job === null) {
            return null;
        }

        try {
            $this->executeJob($job);
            $this->driver->markCompleted($job['id']);
            Logger::infoStatic('Job completed', ['job_id' => $job['id'], 'job' => $job['job']]);
            return $job;
        } catch (\Throwable $e) {
            $this->handleJobFailure($job, $e);
            throw $e;
        }
    }

    /**
     * Execute job
     */
    protected function executeJob(array $job): void
    {
        $jobClass = $job['job'];
        $data = json_decode($job['data'], true) ?? [];

        // Check if job class exists
        if (!class_exists($jobClass)) {
            throw new \Exception("Job class {$jobClass} not found");
        }

        // Check if implements JobInterface
        if (!is_subclass_of($jobClass, JobInterface::class)) {
            throw new \Exception("Job class {$jobClass} must implement JobInterface");
        }

        // Instantiate and execute
        $jobInstance = new $jobClass();
        $jobInstance->handle($data);
    }

    /**
     * Handle job failure
     */
    protected function handleJobFailure(array $job, \Throwable $e): void
    {
        $attempts = (int)($job['attempts'] ?? 0) + 1;

        if ($attempts >= $this->maxRetries) {
            // Max retries reached - mark as failed
            $this->driver->markFailed($job['id'], $e->getMessage());
            Logger::errorStatic('Job failed permanently', [
                'job_id' => $job['id'],
                'job' => $job['job'],
                'error' => $e->getMessage(),
                'attempts' => $attempts
            ]);
        } else {
            // Retry job
            $this->driver->retry($job['id'], $attempts, $this->retryDelay);
            Logger::warnStatic('Job retry scheduled', [
                'job_id' => $job['id'],
                'job' => $job['job'],
                'attempt' => $attempts,
                'next_retry' => time() + $this->retryDelay
            ]);
        }
    }

    /**
     * Process all pending jobs
     */
    public function work(int $maxJobs = null): int
    {
        $processed = 0;
        
        while (true) {
            if ($maxJobs !== null && $processed >= $maxJobs) {
                break;
            }

            $job = $this->process();
            
            if ($job === null) {
                break; // No more jobs
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Static helper methods
     */
    public static function pushStatic(string $job, array $data = [], int $priority = 0, int $delay = 0): string
    {
        return self::getInstance()->push($job, $data, $priority, $delay);
    }

    public static function processStatic(): ?array
    {
        return self::getInstance()->process();
    }

    public static function workStatic(int $maxJobs = null): int
    {
        return self::getInstance()->work($maxJobs);
    }
}

