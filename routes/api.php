<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationVerificationController;
use App\Http\Controllers\CertifiedPersonController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/certifications/verify', [CertificationVerificationController::class, 'verify']);


Route::middleware('auth:sanctum')->group(function () {

    // contractor-only endpoints
    Route::middleware('role:contractor')->group(function () {
        Route::get('/contractor/me', [ContractorController::class, 'me']);
        Route::patch('/contractor/me', [ContractorController::class, 'updateMe']);
        Route::get('/certified-people', [CertifiedPersonController::class, 'index']);
        Route::post('/certified-people', [CertifiedPersonController::class, 'store']);
        Route::patch('/certified-people/{certifiedPerson}', [CertifiedPersonController::class, 'update']);
        Route::delete('/certified-people/{certifiedPerson}', [CertifiedPersonController::class, 'destroy']);
    });

    // admin can manage contractors (optional)
    Route::middleware('role:admin')->group(function () {
        Route::post('/register/contractor', [AuthController::class, 'registerContractor']);
        Route::get('/contractors', [ContractorController::class, 'index']);
        Route::get('/contractors/{user}', [ContractorController::class, 'show']); // user id
        Route::patch('/contractors/{user}', [ContractorController::class, 'update']); // edit contractor profile
    });
});
