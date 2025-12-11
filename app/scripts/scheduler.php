<?php
/**
 * Scheduler
 * Runs scheduled jobs (cron-like)
 * 
 * Usage:
 * php app/scripts/scheduler.php
 * 
 * Add to crontab:
 * * * * * * cd /path/to/project && php app/scripts/scheduler.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Queue\Scheduler;
use App\Core\Logger;

// Load scheduled jobs from config or register them here
$scheduler = Scheduler::getInstance();

// Example scheduled jobs (register your jobs here or load from config)
// $scheduler->schedule('0 2 * * *', 'App\Jobs\DailyReportJob');
// $scheduler->schedule('every:5min', 'App\Jobs\CleanupJob');
// $scheduler->schedule('14:30', 'App\Jobs\AfternoonReportJob');

// Run scheduler
$run = $scheduler->run();

if ($run > 0) {
    Logger::infoStatic('Scheduler ran', ['jobs_queued' => $run]);
}

