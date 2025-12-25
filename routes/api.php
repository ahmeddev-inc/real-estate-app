<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\ClientController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        
        // Protected Routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/change-password', [AuthController::class, 'changePassword']);
        });
    });
    
    // Protected API Routes
    Route::middleware('auth:sanctum')->group(function () {
        // User Management Routes
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/agents', [UserController::class, 'agents']);
            Route::get('/{uuid}', [UserController::class, 'show']);
            Route::put('/{uuid}', [UserController::class, 'update']);
            Route::delete('/{uuid}', [UserController::class, 'destroy']);
            Route::put('/{uuid}/status', [UserController::class, 'updateStatus']);
            Route::put('/{uuid}/permissions', [UserController::class, 'updatePermissions']);
            Route::get('/{uuid}/stats', [UserController::class, 'agentStats']);
            Route::post('/{userUuid}/assign-manager', [UserController::class, 'assignManager']);
            Route::delete('/{uuid}/remove-manager', [UserController::class, 'removeManager']);
            Route::get('/{managerUuid}/subordinates', [UserController::class, 'subordinates']);
        });
        
        // Property Management Routes
        Route::prefix('properties')->group(function () {
            Route::get('/', [PropertyController::class, 'index']);
            Route::post('/', [PropertyController::class, 'store']);
            Route::get('/featured', [PropertyController::class, 'featured']);
            Route::get('/new', [PropertyController::class, 'newProperties']);
            Route::get('/stats', [PropertyController::class, 'stats']);
            Route::get('/report', [PropertyController::class, 'report']);
            Route::get('/{uuid}', [PropertyController::class, 'show']);
            Route::put('/{uuid}', [PropertyController::class, 'update']);
            Route::delete('/{uuid}', [PropertyController::class, 'destroy']);
            Route::put('/{uuid}/status', [PropertyController::class, 'updateStatus']);
            Route::post('/{propertyUuid}/assign-agent', [PropertyController::class, 'assignAgent']);
            Route::post('/{uuid}/verify', [PropertyController::class, 'verify']);
            Route::post('/{uuid}/feature', [PropertyController::class, 'markAsFeatured']);
            Route::get('/{uuid}/similar', [PropertyController::class, 'similar']);
            Route::post('/{uuid}/increment-inquiry', [PropertyController::class, 'incrementInquiryCount']);
            Route::post('/{uuid}/images', [PropertyController::class, 'addImage']);
            Route::delete('/{uuid}/images', [PropertyController::class, 'removeImage']);
        });
        
        // Client Management Routes
        Route::prefix('clients')->group(function () {
            Route::get('/', [ClientController::class, 'index']);
            Route::post('/', [ClientController::class, 'store']);
            Route::get('/needs-follow-up', [ClientController::class, 'needsFollowUp']);
            Route::get('/new', [ClientController::class, 'newClients']);
            Route::get('/stats', [ClientController::class, 'stats']);
            Route::get('/report', [ClientController::class, 'report']);
            Route::get('/{uuid}', [ClientController::class, 'show']);
            Route::put('/{uuid}', [ClientController::class, 'update']);
            Route::delete('/{uuid}', [ClientController::class, 'destroy']);
            Route::put('/{uuid}/status', [ClientController::class, 'updateStatus']);
            Route::post('/{clientUuid}/assign-agent', [ClientController::class, 'assignAgent']);
            Route::post('/{uuid}/contact', [ClientController::class, 'updateLastContact']);
            Route::post('/{uuid}/schedule-follow-up', [ClientController::class, 'scheduleFollowUp']);
            Route::get('/{uuid}/match-properties', [ClientController::class, 'matchProperties']);
            Route::post('/{uuid}/notes', [ClientController::class, 'addNote']);
            Route::post('/{uuid}/tags', [ClientController::class, 'addTag']);
            Route::delete('/{uuid}/tags', [ClientController::class, 'removeTag']);
        });
    });
});
