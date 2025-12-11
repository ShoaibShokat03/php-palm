<?php

namespace App\Core\Queue;

use App\Database\Db;

/**
 * Database Queue Driver
 * Stores jobs in database table
 */
class DatabaseQueue implements QueueDriverInterface
{
    protected Db $db;
    protected string $table = 'queue_jobs';

    public function __construct()
    {
        $this->db = new Db();
        $this->db->connect();
        $this->ensureTable();
    }

    /**
     * Ensure queue table exists
     */
    protected function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` VARCHAR(36) PRIMARY KEY,
            `job` VARCHAR(255) NOT NULL,
            `data` TEXT NOT NULL,
            `priority` INT DEFAULT 0,
            `attempts` INT DEFAULT 0,
            `max_attempts` INT DEFAULT 3,
            `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            `error` TEXT NULL,
            `available_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status_priority` (`status`, `priority`, `available_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }

    public function push(string $job, array $data, int $priority = 0, int $delay = 0): string
    {
        $id = $this->generateId();
        $availableAt = date('Y-m-d H:i:s', time() + $delay);
        $jobEscaped = $this->db->escape($job);
        $dataEscaped = $this->db->escape(json_encode($data));
        
        $sql = "INSERT INTO `{$this->table}` 
                (id, job, data, priority, available_at) 
                VALUES 
                ('{$id}', '{$jobEscaped}', '{$dataEscaped}', {$priority}, '{$availableAt}')";
        
        $this->db->query($sql);
        return $id;
    }

    public function pop(): ?array
    {
        // Get highest priority job that's available
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE status = 'pending' 
                AND available_at <= NOW() 
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1 
                FOR UPDATE";
        
        $result = $this->db->query($sql);
        
        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $job = $result->fetch_assoc();
        
        // Mark as processing
        $this->db->query("UPDATE `{$this->table}` SET status = 'processing' WHERE id = '{$job['id']}'");
        
        return $job;
    }

    public function markCompleted(string $jobId): void
    {
        $this->db->query("UPDATE `{$this->table}` SET status = 'completed' WHERE id = '{$this->db->escape($jobId)}'");
    }

    public function markFailed(string $jobId, string $error): void
    {
        $errorEscaped = $this->db->escape($error);
        $jobIdEscaped = $this->db->escape($jobId);
        $this->db->query("UPDATE `{$this->table}` SET status = 'failed', error = '{$errorEscaped}' WHERE id = '{$jobIdEscaped}'");
    }

    public function retry(string $jobId, int $attempts, int $delay): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delay);
        $jobIdEscaped = $this->db->escape($jobId);
        $this->db->query("UPDATE `{$this->table}` 
                         SET status = 'pending', 
                             attempts = {$attempts}, 
                             available_at = '{$availableAt}' 
                         WHERE id = '{$jobIdEscaped}'");
    }

    protected function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

