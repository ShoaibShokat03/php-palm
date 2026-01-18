<?php

return [
    'name' => 'PHP Palm',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => true,
    'url' => 'http://localhost:8000',
    'timezone' => 'UTC',
    'locale' => 'en',

    // User Model Class (Namespace)
    // Default: App\Modules\Users\Model::class
    'user_model' => \App\Modules\Users\Model::class,
];
