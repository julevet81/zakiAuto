<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\ContainerOpenerController;
use App\Http\Controllers\Api\ServiceProviderController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Phase 1: Auth (register/login/me/logout).
| Phase 2 (this update): Suppliers, Cars (+ nested car expenses),
| Container Openers, Service Providers, Import Batches.
|
| Authorization for these resources is enforced TWICE on purpose:
|   1) Controller-level via $this->authorize() against each model's Policy
|      (the Policy itself just checks the Spatie permission, e.g.
|      'suppliers.view') — this is the actual security boundary.
|   2) No route-level `permission:` middleware is added redundantly here,
|      to avoid maintaining the same rule in two places. If you prefer
|      route-level middleware instead of Policies, you can swap freely;
|      both approaches read from the same Spatie permissions.
|
*/

Route::prefix('auth')->group(function () {
    // Public endpoints (throttled to slow down brute-force attempts)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});

// Sanity-check route to confirm auth + role/permission data is wired correctly.
Route::middleware('auth:sanctum')->get('/ping', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'message' => 'pong',
        'user_id' => $request->user()->id,
        'roles' => $request->user()->getRoleNames(),
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('container-openers', ContainerOpenerController::class)
        ->parameters(['container-openers' => 'container_opener']);

    Route::apiResource('service-providers', ServiceProviderController::class)
        ->parameters(['service-providers' => 'service_provider']);

    Route::apiResource('batches', BatchController::class);

    Route::apiResource('cars', CarController::class);
    Route::get('cars/{car}/expenses', [CarController::class, 'expenses']);
    Route::post('cars/{car}/expenses', [CarController::class, 'storeExpense']);
});

