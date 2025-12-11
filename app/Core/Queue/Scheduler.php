<?php

namespace App\Core\Queue;

use App\Core\Logger;

/**
 * Cron-like Scheduler
 * 
 * Features:
 * - Schedule jobs to run at specific times
 * - Cron-like syntax support
 * - Recurring tasks
 */
class Scheduler
{
    protected static Scheduler $instance;
    protected array $scheduledJobs = [];

    public static function getInstance(): Scheduler
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Schedule a job
     * 
     * @param string $schedule Cron expression (minute hour day month weekday) or time string
     * @param string $job Job class name
     * @param array $data Job data
     */
    public function schedule(string $schedule, string $job, array $data = []): void
    {
        $this->scheduledJobs[] = [
            'schedule' => $schedule,
            'job' => $job,
            'data' => $data,
            'last_run' => null
        ];
    }

    /**
     * Run scheduled jobs
     */
    public function run(): int
    {
        $run = 0;
        
        foreach ($this->scheduledJobs as &$scheduled) {
            if ($this->shouldRun($scheduled)) {
                Queue::pushStatic($scheduled['job'], $scheduled['data']);
                $scheduled['last_run'] = time();
                $run++;
                Logger::infoStatic('Scheduled job queued', [
                    'job' => $scheduled['job'],
                    'schedule' => $scheduled['schedule']
                ]);
            }
        }

        return $run;
    }

    /**
     * Check if job should run
     */
    protected function shouldRun(array $scheduled): bool
    {
        $schedule = $scheduled['schedule'];
        $lastRun = $scheduled['last_run'];

        // Simple time-based scheduling
        if (preg_match('/^(\d{2}):(\d{2})$/', $schedule, $matches)) {
            // Time format: "14:30" (2:30 PM)
            $hour = (int)$matches[1];
            $minute = (int)$matches[2];
            $now = getdate();
            
            if ($now['hours'] == $hour && $now['minutes'] == $minute) {
                // Check if already ran today
                if ($lastRun === null || date('Y-m-d', $lastRun) !== date('Y-m-d')) {
                    return true;
                }
            }
            return false;
        }

        // Cron-like expression: "0 2 * * *" (2 AM daily)
        if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)$/', $schedule, $matches)) {
            return $this->matchesCron($matches, $lastRun);
        }

        // Interval: "every:5min", "every:1hour", "every:1day"
        if (preg_match('/^every:(\d+)(min|hour|day)$/', $schedule, $matches)) {
            $interval = (int)$matches[1];
            $unit = $matches[2];
            $seconds = $this->unitToSeconds($interval, $unit);
            
            if ($lastRun === null || (time() - $lastRun) >= $seconds) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if cron expression matches current time
     */
    protected function matchesCron(array $matches, ?int $lastRun): bool
    {
        $minute = $matches[1];
        $hour = $matches[2];
        $day = $matches[3];
        $month = $matches[4];
        $weekday = $matches[5];

        $now = getdate();

        // Simple matching (supports * and numbers)
        if ($minute !== '*' && (int)$minute !== $now['minutes']) return false;
        if ($hour !== '*' && (int)$hour !== $now['hours']) return false;
        if ($day !== '*' && (int)$day !== $now['mday']) return false;
        if ($month !== '*' && (int)$month !== $now['mon']) return false;
        if ($weekday !== '*' && (int)$weekday !== $now['wday']) return false;

        // Check if already ran in this interval
        if ($lastRun !== null && abs(time() - $lastRun) < 60) {
            return false; // Already ran in last minute
        }

        return true;
    }

    /**
     * Convert unit to seconds
     */
    protected function unitToSeconds(int $value, string $unit): int
    {
        return match($unit) {
            'min' => $value * 60,
            'hour' => $value * 3600,
            'day' => $value * 86400,
            default => $value
        };
    }

    /**
     * Get all scheduled jobs
     */
    public function getScheduledJobs(): array
    {
        return $this->scheduledJobs;
    }
}

