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
use App\Http\Controllers\Api\V1\PublicJobController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CompanyJobController;
use App\Http\Controllers\Api\V1\AdminJobController;

Route::prefix('v1')->group(function () {

    // --- PUBLIC ROUTES ---
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // API-SKL-01: Tra cứu danh mục kỹ năng chuẩn đang hoạt động
    Route::get('/catalog/skills', [SkillController::class, 'index']);

    // API-CAT-06: Lấy 4 danh mục bộ lọc active
    Route::get('/catalog/lookups', [CatalogController::class, 'publicLookups']);

    // API-JOB-01 & API-JOB-02: Tìm kiếm và xem chi tiết việc làm công khai
    Route::get('/jobs', [PublicJobController::class, 'index']);
    Route::get('/jobs/{jobId}', [PublicJobController::class, 'show'])->whereNumber('jobId');

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

            // --- COMPANY ROUTES (PHASE 06 & 07) ---
            Route::prefix('company')->middleware(['role:company'])->group(function () {
                // API-COM-01..02
                Route::get('/profile', [CompanyProfileController::class, 'show']);
                Route::patch('/profile', [CompanyProfileController::class, 'update']);

                // API-JOB-03..07
                Route::get('/jobs', [CompanyJobController::class, 'indexOwn']);
                Route::post('/jobs', [CompanyJobController::class, 'store']);
                Route::get('/jobs/{jobId}', [CompanyJobController::class, 'showOwn'])->whereNumber('jobId');
                Route::patch('/jobs/{jobId}', [CompanyJobController::class, 'update'])->whereNumber('jobId');
                Route::post('/jobs/{jobId}/close', [CompanyJobController::class, 'close'])->whereNumber('jobId');
            });

            // --- ADMIN ROUTES (PHASE 06 & 07) ---
            Route::prefix('admin')->middleware(['role:admin'])->group(function () {
                // API-ADM-01..03
                Route::get('/companies/unverified', [AdminCompanyController::class, 'unverified']);
                Route::get('/companies/{companyId}', [AdminCompanyController::class, 'showCompany'])->whereNumber('companyId');
                Route::patch('/companies/{companyId}/verification', [AdminCompanyController::class, 'verify'])->whereNumber('companyId');

                // API-ADM-04..07
                Route::get('/jobs/pending', [AdminJobController::class, 'pendingJobs']);
                Route::get('/jobs/{id}', [AdminJobController::class, 'showJob'])->whereNumber('id');
                Route::patch('/jobs/{id}/review', [AdminJobController::class, 'reviewJob'])->whereNumber('id');
                Route::patch('/jobs/{id}/hide', [AdminJobController::class, 'hideJob'])->whereNumber('id');
            });
        });
    });
});
