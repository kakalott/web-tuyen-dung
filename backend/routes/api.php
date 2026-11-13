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
use App\Http\Controllers\Api\V1\CandidateApplicationController;
use App\Http\Controllers\Api\V1\CompanyApplicationController;
use App\Http\Controllers\Api\V1\InterviewController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\AdminCatalogController;
use App\Http\Controllers\Api\V1\AdminDashboardController;

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

            // --- TRANSACTIONAL NOTIFICATION ROUTES (PHASE 11: API-NOTI-01..03) ---
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::patch('/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
                Route::post('/read-all', [NotificationController::class, 'markAllRead']);
            });

            // --- CANDIDATE ROUTES (PHASE 04 & 08) ---
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

                // API-AI-01: Gợi ý việc làm theo CV đã confirmed
                Route::get('/recommendations', [RecommendationController::class, 'recommend']);

                // API-APP-02..04: Danh sách, chi tiết và rút đơn ứng tuyển
                Route::prefix('applications')->group(function () {
                    Route::get('/', [CandidateApplicationController::class, 'index']);
                    Route::get('/{id}', [CandidateApplicationController::class, 'show'])->whereNumber('id');
                    Route::post('/{id}/withdraw', [CandidateApplicationController::class, 'withdraw'])->whereNumber('id');
                });
            });

            // API-APP-01: Ứng viên nộp hồ sơ vào tin tuyển dụng
            Route::post('/jobs/{jobId}/applications', [CandidateApplicationController::class, 'apply'])
                ->middleware(['role:candidate'])
                ->whereNumber('jobId');

            // --- COMPANY ROUTES (PHASE 06, 07 & 08) ---
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

                // API-APP-05: Danh sách hồ sơ ứng tuyển theo tin tuyển dụng
                Route::get('/jobs/{jobId}/applications', [CompanyApplicationController::class, 'indexByJob'])->whereNumber('jobId');

                // API-APP-06..08: Chi tiết đơn, stream CV bảo mật, cập nhật trạng thái đơn
                Route::get('/applications/{id}', [CompanyApplicationController::class, 'show'])->whereNumber('id');
                Route::get('/applications/{id}/cv', [CompanyApplicationController::class, 'downloadForApplication'])->whereNumber('id');
                Route::patch('/applications/{id}/status', [CompanyApplicationController::class, 'changeStatus'])->whereNumber('id');

                // --- COMPANY INTERVIEW ROUTES (PHASE 10: API-INT-01..05) ---
                Route::post('/applications/{id}/interviews', [InterviewController::class, 'schedule'])->whereNumber('id');
                Route::patch('/interviews/{id}', [InterviewController::class, 'update'])->whereNumber('id');
                Route::post('/interviews/{id}/cancel', [InterviewController::class, 'cancel'])->whereNumber('id');
                Route::post('/interviews/{id}/complete', [InterviewController::class, 'complete'])->whereNumber('id');
                Route::post('/interviews/{id}/no-show', [InterviewController::class, 'noShow'])->whereNumber('id');
            });

            // --- ADMIN ROUTES (PHASE 06, 07 & 12) ---
            Route::prefix('admin')->middleware(['role:admin'])->group(function () {
                // API-ADM-08: Thống kê dashboard tổng quan
                Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);

                // API-ADM-01..03
                Route::get('/companies/unverified', [AdminCompanyController::class, 'unverified']);
                Route::get('/companies/{companyId}', [AdminCompanyController::class, 'showCompany'])->whereNumber('companyId');
                Route::patch('/companies/{companyId}/verification', [AdminCompanyController::class, 'verify'])->whereNumber('companyId');

                // API-ADM-04..07
                Route::get('/jobs/pending', [AdminJobController::class, 'pendingJobs']);
                Route::get('/jobs/{id}', [AdminJobController::class, 'showJob'])->whereNumber('id');
                Route::patch('/jobs/{id}/review', [AdminJobController::class, 'reviewJob'])->whereNumber('id');
                Route::patch('/jobs/{id}/hide', [AdminJobController::class, 'hideJob'])->whereNumber('id');

                // --- ADMIN CATALOG ROUTES (PHASE 12: API-CAT-01..05 & API-CAT-07..09) ---
                Route::prefix('catalog')->group(function () {
                    // API-CAT-01..03: Kỹ năng chuẩn
                    Route::get('/skills', [AdminCatalogController::class, 'skillsIndex']);
                    Route::post('/skills', [AdminCatalogController::class, 'skillStore']);
                    Route::patch('/skills/{id}', [AdminCatalogController::class, 'skillUpdate'])->whereNumber('id');

                    // API-CAT-04..05: Biến thể kỹ năng (aliases)
                    Route::post('/skills/{id}/aliases', [AdminCatalogController::class, 'aliasStore'])->whereNumber('id');
                    Route::patch('/aliases/{id}', [AdminCatalogController::class, 'aliasUpdate'])->whereNumber('id');

                    // API-CAT-07..09: 4 danh mục tuyển dụng động (khai báo sau routes tĩnh để tránh nuốt URL)
                    Route::get('/{catalog}', [AdminCatalogController::class, 'lookupIndex']);
                    Route::post('/{catalog}', [AdminCatalogController::class, 'lookupStore']);
                    Route::patch('/{catalog}/{id}', [AdminCatalogController::class, 'lookupUpdate'])->whereNumber('id');
                });
            });
        });
    });
});
