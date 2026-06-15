<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\Auth\RegisterRequest;
use App\Services\Shop\CustomerAuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $auth,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return response()->json($result, 201);
    }
}
