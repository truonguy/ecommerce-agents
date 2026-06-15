<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực request bằng đúng guard của phân hệ (customer/employee) và đặt nó
 * làm guard mặc định cho phần còn lại của request.
 *
 * Sanctum đã kiểm tra token thuộc đúng provider/model (hasValidProvider), nên
 * token sai phân hệ sẽ không resolve được user qua guard tương ứng → 401.
 */
class EnsureGuard
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        if (! $request->user($guard)) {
            abort(401, 'Unauthenticated.');
        }

        Auth::shouldUse($guard);

        return $next($request);
    }
}
