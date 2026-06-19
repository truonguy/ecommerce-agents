<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Support\PaginationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentDashboardController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaginationService $pagination,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->payments->listAll(
            $request->query('per_page'),
            $request->query('status'),
            $request->query('method'),
        );

        return response()->json($this->pagination->format($paginator));
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load('attempts'));
    }

    public function retry(Payment $payment): JsonResponse
    {
        $result = $this->payments->retry($payment);

        return response()->json([
            'payment' => $result['payment'],
            'payment_url' => $result['payment_url'],
        ]);
    }
}
