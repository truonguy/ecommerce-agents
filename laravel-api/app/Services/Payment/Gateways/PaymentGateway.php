<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;

/**
 * Abstraction cho cổng thanh toán (spec §6 FR-PM4). Thêm gateway mới = thêm adapter,
 * không sửa caller (PaymentService / WebhookController).
 */
interface PaymentGateway
{
    /**
     * Khởi tạo giao dịch. Trả về URL redirect (null nếu không cần, vd COD) + mã tham chiếu.
     *
     * @return array{url: string|null, ref: string}
     */
    public function create(Payment $payment): array;

    /**
     * Verify payload callback/webhook.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ref: string|null, status: \App\Enums\PaymentStatus, valid: bool}
     */
    public function verify(array $payload): array;

    /**
     * Truy vấn trạng thái giao dịch tại gateway (dùng cho reconciliation).
     */
    public function query(string $ref): \App\Enums\PaymentStatus;

    public function name(): string;
}
