<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\DomainStates;
use App\Support\ApiError;
use App\Models\Job;
use App\Exceptions\DomainConflictException;
use App\Exceptions\AIServiceException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class BackendFoundationTest extends TestCase
{
    public function test_application_state_transitions(): void
    {
        // Terminal states cấm mọi chuyển tiếp
        $this->assertTrue(DomainStates::isTerminalApplication('passed'));
        $this->assertFalse(DomainStates::canCompanyTransitionApplication('passed', 'rejected'));
        $this->assertFalse(DomainStates::canCandidateWithdraw('withdrawn'));

        // Luật interview -> reviewing
        $this->assertFalse(DomainStates::canCompanyTransitionApplication('interview', 'reviewing', true));
        $this->assertTrue(DomainStates::canCompanyTransitionApplication('interview', 'reviewing', false));

        // Ứng viên rút đơn hợp lệ
        $this->assertTrue(DomainStates::canCandidateWithdraw('pending'));
        $this->assertTrue(DomainStates::canCandidateWithdraw('interview'));
    }

    public function test_public_job_visibility(): void
    {
        $job = new Job();
        $job->review_status = 'approved';
        $job->publication_status = 'active';
        $job->expires_at = null;
        $this->assertTrue(DomainStates::isPublicJob($job));

        $job->expires_at = now()->subDay();
        $this->assertFalse(DomainStates::isPublicJob($job));
    }

    public function test_api_error_envelope_format(): void
    {
        config(['app.debug' => false]);
        $request = Request::create('/api/v1/test', 'GET');

        $res401 = ApiError::render($request, new AuthenticationException());
        $this->assertEquals(401, $res401->getStatusCode());
        $this->assertEquals('AUTH_REQUIRED', $res401->getData(true)['error']['code']);
        $this->assertArrayHasKey('request_id', $res401->getData(true));

        $res409 = ApiError::render($request, new DomainConflictException('Conflict'));
        $this->assertEquals(409, $res409->getStatusCode());
        $this->assertEquals('STATE_CONFLICT', $res409->getData(true)['error']['code']);

        $res502 = ApiError::render($request, new AIServiceException());
        $this->assertEquals(502, $res502->getStatusCode());
        $this->assertEquals('AI_SERVICE_ERROR', $res502->getData(true)['error']['code']);
    }
}
