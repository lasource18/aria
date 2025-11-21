<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Test route for Inertia.js
Route::get('/test-inertia', function () {
    return Inertia::render('Test', [
        'message' => 'Hello from Inertia!',
    ]);
});

// Dashboard routes - require authentication and org membership
Route::middleware(['auth:sanctum', 'org.member'])->prefix('org/{org}')->group(function () {
    Route::get('/dashboard', function ($orgId) {
        return Inertia::render('Dashboard/Index', [
            'org' => ['id' => $orgId, 'name' => 'Sample Organization'],
        ]);
    })->name('org.dashboard');

    Route::get('/events', function ($orgId) {
        return Inertia::render('Events/List', [
            'org' => ['id' => $orgId, 'name' => 'Sample Organization'],
            'events' => [],
        ]);
    })->name('org.events.index');

    Route::get('/events/create', function ($orgId) {
        return Inertia::render('Events/Create', [
            'org' => ['id' => $orgId, 'name' => 'Sample Organization'],
        ]);
    })->name('org.events.create');

    Route::get('/events/{event}/edit', function ($orgId, $eventId) {
        return Inertia::render('Events/Edit', [
            'org' => ['id' => $orgId, 'name' => 'Sample Organization'],
            'event' => [
                'id' => $eventId,
                'title' => 'Sample Event',
                'venue_name' => 'Sample Venue',
                'status' => 'draft',
            ],
        ]);
    })->name('org.events.edit');
});
