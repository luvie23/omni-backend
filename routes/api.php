<?php

use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\CertifiedPersonAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationVerificationController;
use App\Http\Controllers\CertifiedPersonController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\KnowledgeBaseAdminController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\QuotationRequestAdminController;
use App\Http\Controllers\QuotationRequestController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out']);
});

Route::get('/certifications/verify', [CertificationVerificationController::class, 'verify']);

Route::get('/admin/knowledge-base/{article}/video', [KnowledgeBaseAdminController::class, 'stream'])
        ->name('admin.knowledge-base.video');

Route::get('/knowledge-base/{article}/video', [KnowledgeBaseController::class, 'stream'])
        ->name('knowledge-base.video');

Route::post('/quotation-request', [QuotationRequestController::class, 'store']);


Route::middleware('auth:sanctum')->group(function () {

    // contractor-only endpoints
    Route::middleware('role:contractor')->group(function () {
        Route::get('/contractor/me', [ContractorController::class, 'me']);
        Route::patch('/contractor/me', [ContractorController::class, 'updateMe']);
        Route::patch('/contractor/password', [ContractorController::class, 'updateMyPassword']);
        Route::get('/certified-people', [CertifiedPersonController::class, 'index']);
        // Route::post('/certified-people', [CertifiedPersonController::class, 'store']);
        // Route::patch('/certified-people/{certifiedPerson}', [CertifiedPersonController::class, 'update']);
        // Route::delete('/certified-people/{certifiedPerson}', [CertifiedPersonController::class, 'destroy']);
        Route::post('/contractor/logo', [ContractorController::class, 'uploadLogo']);

        Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index']);
        Route::get('/knowledge-base/{article}', [KnowledgeBaseController::class, 'show']);
        Route::get('/knowledge-base/{article}/video', [KnowledgeBaseController::class, 'stream']);

    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/register/contractor', [AuthController::class, 'registerContractor']);
        Route::get('/contractors', [ContractorController::class, 'index']);
        Route::get('/contractors/{contractor}', [ContractorController::class, 'show']);
        Route::patch('/contractors/{contractor}', [ContractorController::class, 'update']);

        Route::patch(
            '/admin/contractors/{user}/password',
            [ContractorController::class, 'updatePassword']
        );

        // Get certified people for a contractor (contractors.id)
        Route::get(
            '/admin/contractors/{contractor}/certified-people',
            [CertifiedPersonAdminController::class, 'index']
        );

        // Create certified person for contractor (contractors.id)
        Route::post(
            '/admin/contractors/{contractor}/certified-people',
            [CertifiedPersonAdminController::class, 'store']
        );

        // Update certified person
        Route::patch(
            '/admin/certified-people/{certifiedPerson}',
            [CertifiedPersonAdminController::class, 'update']
        );

        // Delete certified person
        Route::delete(
            '/admin/certified-people/{certifiedPerson}',
            [CertifiedPersonAdminController::class, 'destroy']
        );

        Route::get('/admin/knowledge-base', [KnowledgeBaseAdminController::class, 'index']);
        Route::post('/admin/knowledge-base', [KnowledgeBaseAdminController::class, 'store']);
        Route::get('/admin/knowledge-base/{article}', [KnowledgeBaseAdminController::class, 'show']);
        Route::patch('/admin/knowledge-base/{article}', [KnowledgeBaseAdminController::class, 'update']);
        Route::delete('/admin/knowledge-base/{article}', [KnowledgeBaseAdminController::class, 'destroy']);

        Route::get('/admin/quotation-requests', [QuotationRequestAdminController::class, 'index']);
        Route::get('/admin/quotation-requests/{quotation_request}', [QuotationRequestAdminController::class, 'show']);
        Route::patch('/admin/quotation-requests/{quotation_request}', [QuotationRequestAdminController::class, 'update']);
        Route::delete('/admin/quotation-requests/{quotation_request}', [QuotationRequestAdminController::class, 'destroy']);


        Route::get(
            '/admin/quotation-requests/{quotation_request}/nearby-contractors',
            [QuotationRequestAdminController::class, 'nearbyContractors']
        );

        Route::get('/admin/settings/distance', [AdminSettingsController::class, 'getDistance']);
        Route::patch('/admin/settings/distance', [AdminSettingsController::class, 'updateDistance']);

    });
});
