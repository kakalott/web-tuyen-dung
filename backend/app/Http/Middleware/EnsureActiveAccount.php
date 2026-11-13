<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            throw new AuthenticationException();
        }

        if ($request->user()->account_status === 'locked') {
            throw new AccessDeniedHttpException('Tài khoản của bạn đã bị khóa.');
        }

        return $next($request);
    }
}
