<?php

require __DIR__ . '/vendor/autoload.php';

use App\Modules\Users\Module as UserModule;
use App\Core\ApplicationBootstrap;

// Mock $_GET parameters with search
$_GET['page'] = '1';
$_GET['search'] = 'test'; // This triggers the issue
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users?search=test';

echo "Testing UserModule::get('/users') with search param...\n";

try {
    ApplicationBootstrap::init();

    $module = UserModule::get("/users");

    if ($module === null) {
        echo "RESULT: NULL (Issue Reproduced)\n";
    } else {
        echo "RESULT: SUCCESS\n";
        // print_r($module);
    }
} catch (\Throwable $e) {
    echo "EXCEPTION CAUGHT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
