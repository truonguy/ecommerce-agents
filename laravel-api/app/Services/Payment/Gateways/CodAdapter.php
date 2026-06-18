<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * COD: không redirect, không webhook. Tạo → coi như thành công ngay (PaymentService confirm order).
 */
class CodAdapter implements PaymentGateway
{
    public function create(Payment $payment): array
    {
        return [
            'url' => null,
            'ref' => 'COD-'.$payment->id,
        ];
    }

    public function verify(array $payload): array
    {
        return [
            'ref' => $payload['ref'] ?? null,
            'status' => PaymentStatus::SUCCESS,
            'valid' => true,
        ];
    }

    public function query(string $ref): PaymentStatus
    {
        return PaymentStatus::SUCCESS;
    }

    public function name(): string
    {
        return 'cod';
    }
}
