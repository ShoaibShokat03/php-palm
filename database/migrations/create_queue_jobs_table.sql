-- Migration: Create queue_jobs table
-- This table stores background jobs for processing

CREATE TABLE IF NOT EXISTS queue_jobs (
    id VARCHAR(36) PRIMARY KEY,
    job VARCHAR(255) NOT NULL,
    data TEXT NOT NULL,
    priority INT DEFAULT 0,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error TEXT NULL,
    available_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority, available_at),
    INDEX idx_available_at (available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

