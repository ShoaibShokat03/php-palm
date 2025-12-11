<?php

use Frontend\Palm\Route;
use Frontend\Palm\PalmCache;

Route::get('/cache', function () {
    $cache = new PalmCache();

    Route::render('home.cache', [
        'title' => 'Palm Cache Manager',
        'summary' => $cache->summary(),
        'recentFiles' => $cache->recentFiles(),
        'message' => $_GET['message'] ?? null,
    ]);
});

Route::get('/cache-clear', function () {
    $target = $_GET['target'] ?? 'all';
    $format = strtolower($_GET['format'] ?? '');
    $cache = new PalmCache();
    $result = $cache->clear($target);

    if ($format === 'html') {
        Route::render('home.cache', [
            'title' => 'Palm Cache Manager',
            'summary' => $result['summary'],
            'recentFiles' => $result['recent_files'],
            'message' => $result['message'],
        ]);
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

Route::post('/cache-clear', function () {
    $target = $_POST['target'] ?? 'all';
    $cache = new PalmCache();
    $result = $cache->clear($target);

    if (isset($_POST['redirect']) && $_POST['redirect'] === 'cache') {
        $query = http_build_query([
            'message' => $result['message'],
            'target' => $result['target'],
        ]);
        header('Location: /cache?' . $query);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

// Home page
Route::get('/', Route::view('home.index', [
    'title' => 'PHP Palm Frontend',
    'message' => 'Welcome to your PHP Palm powered frontend!',
]), 'home');

// About page
Route::get('/about', Route::view('home.about', [
    'title' => 'About PHP Palm',
    'meta' => ['description' => 'Learn how PHP Palm powers fast, clean PHP frontends'],
]), 'about');

// Contact page
Route::get('/contact', Route::view('home.contact', [
    'title' => 'Contact',
]), 'contact');

// Contact form submission
Route::post('/contact', function () {
    require_once dirname(__DIR__, 2) . '/app/Palm/helpers.php';
    
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($message)) {
        Route::render('home.contact', [
            'title' => 'Contact',
            'flash' => 'Please fill in all fields.',
            'prefill' => ['name' => $name, 'message' => $message],
        ]);
        return;
    }

    Route::render('home.contact', [
        'title' => 'Contact',
        'flash' => 'Thanks for reaching out! We will reply soon.',
        'prefill' => ['name' => '', 'message' => ''],
    ]);
}, 'contact.submit');

// Demo page
Route::get('/demo', Route::view('home.demo', [
    'title' => 'Demo Page',
    'message' => 'Playground for Palm experiments',
]), 'demo');

// Google Auth routes (automatically initialized in index.php)
// Google login - redirect to Google
Route::get('/auth/google', function () {
    try {
        google_auth_redirect();
    } catch (\Exception $e) {
        Route::render('home.error', [
            'title' => 'Authentication Error',
            'message' => 'Google authentication is not configured. Please set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in your .env file.',
        ]);
    }
}, 'auth.google');

// Google callback - handle OAuth response
Route::get('/auth/google/callback', function () {
    try {
        $user = \Frontend\Palm\GoogleAuth::handleCallback();
        
        // Redirect to dashboard or home
        $redirect = $_GET['redirect'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        Route::render('home.error', [
            'title' => 'Authentication Error',
            'message' => 'Failed to authenticate with Google: ' . htmlspecialchars($e->getMessage()),
        ]);
    }
}, 'auth.google.callback');

// Google logout
Route::get('/auth/google/logout', function () {
    google_auth_logout();
    header('Location: /');
    exit;
}, 'auth.google.logout');