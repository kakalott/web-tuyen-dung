<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            throw new AuthenticationException();
        }

        if (!in_array($request->user()->role, $roles, true)) {
            throw new AccessDeniedHttpException('Bạn không có quyền truy cập tài nguyên này.');
        }

        return $next($request);
    }
}
