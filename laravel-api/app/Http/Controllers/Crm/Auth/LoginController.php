<?php

namespace App\Http\Controllers\Crm\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\Auth\LoginRequest;
use App\Services\Crm\EmployeeAuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(
        private readonly EmployeeAuthService $auth,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json($result);
    }
}
