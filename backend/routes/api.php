<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CandidateProfileController;
use App\Http\Controllers\Api\V1\CandidateEducationController;
use App\Http\Controllers\Api\V1\CandidateExperienceController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\CandidateSkillController;

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
            });
        });
    });
});
