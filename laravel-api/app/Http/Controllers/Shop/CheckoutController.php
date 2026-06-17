<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Services\Shop\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkout->checkout(
            $request->user(),
            $request->validated(),
            $request->header('Idempotency-Key'),
        );

        return response()->json($order, 201);
    }
}
