<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CandidateProfileController;
use App\Http\Controllers\Api\V1\CandidateEducationController;
use App\Http\Controllers\Api\V1\CandidateExperienceController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\CandidateSkillController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\AdminCompanyController;

Route::prefix('v1')->group(function () {

    // --- PUBLIC ROUTES ---
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // API-SKL-01: Tra cứu danh mục kỹ năng chuẩn đang hoạt động
    Route::get('/catalog/skills', [SkillController::class, 'index']);

    // --- AUTHENTICATED ROUTES ---
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware(['active_account'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);

            // --- CANDIDATE ROUTES (PHASE 04) ---
            Route::prefix('candidate')->middleware(['role:candidate'])->group(function () {
                // API-CAN-01..02
                Route::get('/profile', [CandidateProfileController::class, 'show']);
                Route::patch('/profile', [CandidateProfileController::class, 'update']);

                // API-CAN-03..05
                Route::post('/educations', [CandidateEducationController::class, 'store']);
                Route::patch('/educations/{id}', [CandidateEducationController::class, 'update'])->whereNumber('id');
                Route::delete('/educations/{id}', [CandidateEducationController::class, 'destroy'])->whereNumber('id');

                // API-CAN-06..08
                Route::post('/experiences', [CandidateExperienceController::class, 'store']);
                Route::patch('/experiences/{id}', [CandidateExperienceController::class, 'update'])->whereNumber('id');
                Route::delete('/experiences/{id}', [CandidateExperienceController::class, 'destroy'])->whereNumber('id');

                // API-SKL-02..03
                Route::get('/skills', [CandidateSkillController::class, 'show']);
                Route::put('/skills', [CandidateSkillController::class, 'sync']);

                // API-CV-01..07
                Route::prefix('cvs')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\V1\CandidateCVController::class, 'index']);
                    Route::post('/', [\App\Http\Controllers\Api\V1\CandidateCVController::class, 'upload']);
                    Route::get('/{cvId}', [\App\Http\Controllers\Api\V1\CandidateCVController::class, 'show'])->whereNumber('cvId');
                    Route::get('/{cvId}/file', [\App\Http\Controllers\Api\V1\CandidateCVController::class, 'downloadOwn'])->whereNumber('cvId');
                    
                    Route::get('/{cvId}/extraction', [\App\Http\Controllers\Api\V1\CVExtractionController::class, 'show'])->whereNumber('cvId');
                    Route::patch('/{cvId}/extraction', [\App\Http\Controllers\Api\V1\CVExtractionController::class, 'confirm'])->whereNumber('cvId');
                    Route::post('/{cvId}/retry-extraction', [\App\Http\Controllers\Api\V1\CVExtractionController::class, 'retry'])->whereNumber('cvId');
                });
            });

            // --- COMPANY ROUTES (PHASE 06) ---
            Route::prefix('company')->middleware(['role:company'])->group(function () {
                // API-COM-01..02
                Route::get('/profile', [CompanyProfileController::class, 'show']);
                Route::patch('/profile', [CompanyProfileController::class, 'update']);
            });

            // --- ADMIN ROUTES (PHASE 06) ---
            Route::prefix('admin')->middleware(['role:admin'])->group(function () {
                // API-ADM-01..03
                Route::get('/companies/unverified', [AdminCompanyController::class, 'unverified']);
                Route::get('/companies/{companyId}', [AdminCompanyController::class, 'showCompany'])->whereNumber('companyId');
                Route::patch('/companies/{companyId}/verification', [AdminCompanyController::class, 'verify'])->whereNumber('companyId');
            });
        });
    });
});
