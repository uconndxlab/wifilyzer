<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Home route shows building selection
Route::get('/', [DashboardController::class, 'selectBuilding'])->name('home');

// Building selection route
Route::get('/buildings', [DashboardController::class, 'selectBuilding'])->name('buildings.select');

// Store new building route
Route::post('/buildings', [DashboardController::class, 'storeBuilding'])->name('buildings.store');

// WiFi data upload route
Route::post('/wifi/upload', [DashboardController::class, 'uploadWifiData'])->name('wifi.upload');

// Floor selection route
Route::get('/buildings/{building}', [DashboardController::class, 'selectFloor'])->name('floors.select');

// Store new floor route
Route::post('/buildings/{building}/floors', [DashboardController::class, 'storeFloor'])->name('dashboard.building.floor.store');

// Main dashboard route with building and floor parameters
Route::get('/dashboard/{building}/{floor}', [DashboardController::class, 'index'])
    ->name('dashboard.building.floor');

// Access point positioning routes
Route::post('/dashboard/{building}/{floor}/position-ap', [DashboardController::class, 'storeAccessPointPosition'])
    ->name('dashboard.store.ap.position');

// Legacy route for backward compatibility
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/dashboard/filter', [DashboardController::class, 'filter'])->name('dashboard.filter');
