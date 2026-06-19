<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback/webhook gateway — PUBLIC (không guard); bảo vệ bằng verify chữ ký trong adapter.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $gateway = (string) $request->query('gateway', 'vnpay');

        // Loại 'gateway' (định tuyến qua query) khỏi payload để không lẫn vào verify chữ ký.
        $this->payments->handleWebhook($gateway, $request->except('gateway'));

        return response()->json(['received' => true]);
    }
}
