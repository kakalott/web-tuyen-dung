<?php

namespace App\Support;

use App\Models\Job;
use Carbon\Carbon;

class DomainStates
{
    // Application (6 states)
    public const APP_PENDING = 'pending';
    public const APP_REVIEWING = 'reviewing';
    public const APP_INTERVIEW = 'interview';
    public const APP_PASSED = 'passed';
    public const APP_REJECTED = 'rejected';
    public const APP_WITHDRAWN = 'withdrawn';

    // Interview (4 states)
    public const INT_SCHEDULED = 'scheduled';
    public const INT_CANCELLED = 'cancelled';
    public const INT_COMPLETED = 'completed';
    public const INT_NO_SHOW = 'no_show';

    // Job Review & Publication
    public const JOB_REV_PENDING = 'pending';
    public const JOB_REV_APPROVED = 'approved';
    public const JOB_REV_REJECTED = 'rejected';

    public const JOB_PUB_NOT_PUBLIC = 'not_public';
    public const JOB_PUB_ACTIVE = 'active';
    public const JOB_PUB_CLOSED = 'closed';
    public const JOB_PUB_EXPIRED = 'expired';
    public const JOB_PUB_HIDDEN = 'hidden';

    public static function getApplicationStatuses(): array
    {
        return [
            self::APP_PENDING,
            self::APP_REVIEWING,
            self::APP_INTERVIEW,
            self::APP_PASSED,
            self::APP_REJECTED,
            self::APP_WITHDRAWN,
        ];
    }

    public static function isTerminalApplication(string $status): bool
    {
        return in_array($status, [
            self::APP_PASSED,
            self::APP_REJECTED,
            self::APP_WITHDRAWN,
        ], true);
    }

    public static function canCompanyTransitionApplication(
        string $from,
        string $to,
        bool $hasActiveScheduledInterview = false
    ): bool {
        if (self::isTerminalApplication($from)) {
            return false;
        }

        return match ($from) {
            self::APP_PENDING => in_array($to, [self::APP_REVIEWING, self::APP_REJECTED], true),
            self::APP_REVIEWING => in_array($to, [self::APP_INTERVIEW, self::APP_PASSED, self::APP_REJECTED], true),
            self::APP_INTERVIEW => in_array($to, [self::APP_PASSED, self::APP_REJECTED], true)
                || ($to === self::APP_REVIEWING && !$hasActiveScheduledInterview),
            default => false,
        };
    }

    public static function canCandidateWithdraw(string $from): bool
    {
        return in_array($from, [
            self::APP_PENDING,
            self::APP_REVIEWING,
            self::APP_INTERVIEW,
        ], true);
    }

    public static function canTransitionInterview(string $from, string $to): bool
    {
        if ($from !== self::INT_SCHEDULED) {
            return false;
        }

        return in_array($to, [
            self::INT_CANCELLED,
            self::INT_COMPLETED,
            self::INT_NO_SHOW,
        ], true);
    }

    public static function isPublicJob(Job $job): bool
    {
        if ($job->review_status !== self::JOB_REV_APPROVED || $job->publication_status !== self::JOB_PUB_ACTIVE) {
            return false;
        }

        if ($job->expires_at !== null && Carbon::parse($job->expires_at)->isPast()) {
            return false;
        }

        return true;
    }
}
