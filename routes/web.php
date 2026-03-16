<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// Deployment Hook for Render Free Tier (No SSH access)
Route::get('/migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return response()->json([
        'status' => 'success',
        'message' => 'Database migrations executed successfully.',
        'output' => Artisan::output()
    ]);
});
