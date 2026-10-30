<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CirculationController;
use App\Http\Controllers\Api\CopyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('SELECT 1');
    return response()->json(['status' => 'ok', 'database' => 'connected']);
})->name('api.health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book}', [BookController::class, 'show']);
    Route::get('/lookups/{type}', [LookupController::class, 'index'])->whereIn('type', ['authors', 'publishers', 'categories']);
    Route::get('/borrowings', [CirculationController::class, 'borrowings']);
    Route::get('/reservations', [CirculationController::class, 'reservations']);
    Route::post('/books/{book}/reservations', [CirculationController::class, 'reserve']);
    Route::delete('/reservations/{reservation}', [CirculationController::class, 'cancelReservation']);
    Route::post('/borrowings/{borrowing}/renew', [CirculationController::class, 'renew']);
    Route::get('/fines', [FineController::class, 'index']);
    Route::post('/fines/{fine}/payments', [FineController::class, 'pay']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    Route::middleware('role:admin,librarian')->group(function (): void {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);

        Route::post('/lookups/{type}', [LookupController::class, 'store'])->whereIn('type', ['authors', 'publishers', 'categories']);
        Route::put('/lookups/{type}/{id}', [LookupController::class, 'update'])->whereIn('type', ['authors', 'publishers', 'categories']);
        Route::delete('/lookups/{type}/{id}', [LookupController::class, 'destroy'])->whereIn('type', ['authors', 'publishers', 'categories']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::put('/members/{member}', [MemberController::class, 'update']);
        Route::delete('/members/{member}', [MemberController::class, 'destroy']);

        Route::get('/copies', [CopyController::class, 'index']);
        Route::post('/copies', [CopyController::class, 'store']);
        Route::put('/copies/{copy}', [CopyController::class, 'update']);
        Route::delete('/copies/{copy}', [CopyController::class, 'destroy']);

        Route::post('/borrowings', [CirculationController::class, 'issue']);
        Route::post('/borrowings/{borrowing}/return', [CirculationController::class, 'returnBook']);
        Route::get('/reports', [ReportController::class, 'overview']);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/fines/{fine}/waive', [FineController::class, 'waive'])->middleware('role:admin');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/audit-logs', [AuditController::class, 'index']);
    });
});
