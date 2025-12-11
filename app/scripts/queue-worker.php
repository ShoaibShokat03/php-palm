<?php
/**
 * Queue Worker
 * Processes background jobs from the queue
 * 
 * Usage:
 * php app/scripts/queue-worker.php [--max-jobs=N] [--timeout=N]
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Queue\Queue;
use App\Core\Logger;

$maxJobs = null;
$timeout = 300; // 5 minutes default

// Parse command line arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--max-jobs=') === 0) {
        $maxJobs = (int)substr($arg, 12);
    }
    if (strpos($arg, '--timeout=') === 0) {
        $timeout = (int)substr($arg, 10);
    }
}

$startTime = time();
$processed = 0;

Logger::infoStatic('Queue worker started', ['max_jobs' => $maxJobs, 'timeout' => $timeout]);

while (true) {
    // Check timeout
    if (time() - $startTime >= $timeout) {
        Logger::infoStatic('Queue worker timeout reached', ['processed' => $processed]);
        break;
    }

    try {
        $job = Queue::processStatic();
        
        if ($job === null) {
            // No more jobs, wait a bit
            sleep(1);
            continue;
        }

        $processed++;
        
        if ($maxJobs !== null && $processed >= $maxJobs) {
            Logger::infoStatic('Queue worker max jobs reached', ['processed' => $processed]);
            break;
        }
    } catch (\Throwable $e) {
        Logger::errorStatic('Queue worker error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sleep(1); // Wait before retrying
    }
}

Logger::infoStatic('Queue worker finished', ['processed' => $processed]);

