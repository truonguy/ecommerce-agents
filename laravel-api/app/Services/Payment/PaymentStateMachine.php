<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentTransitionException;

/**
 * State machine cho payment (spec §6):
 * start  PENDING→PROCESSING
 * success PROCESSING→SUCCESS
 * fail    PROCESSING→FAILED
 * expire  (PENDING|PROCESSING)→EXPIRED
 * SUCCESS / FAILED / EXPIRED là terminal.
 */
class PaymentStateMachine
{
    /**
     * @var array<string, array<string, PaymentStatus>>
     */
    private const TRANSITIONS = [
        'start' => [
            'PENDING' => PaymentStatus::PROCESSING,
        ],
        'success' => [
            'PROCESSING' => PaymentStatus::SUCCESS,
        ],
        'fail' => [
            'PROCESSING' => PaymentStatus::FAILED,
        ],
        'expire' => [
            'PENDING' => PaymentStatus::EXPIRED,
            'PROCESSING' => PaymentStatus::EXPIRED,
        ],
        // retry: tạo attempt mới → đưa payment về PROCESSING (không retry khi đã SUCCESS).
        'retry' => [
            'PENDING' => PaymentStatus::PROCESSING,
            'PROCESSING' => PaymentStatus::PROCESSING,
            'FAILED' => PaymentStatus::PROCESSING,
            'EXPIRED' => PaymentStatus::PROCESSING,
        ],
    ];

    public function canApply(PaymentStatus $from, string $action): bool
    {
        return isset(self::TRANSITIONS[$action][$from->value]);
    }

    public function target(PaymentStatus $from, string $action): PaymentStatus
    {
        if (! $this->canApply($from, $action)) {
            throw new InvalidPaymentTransitionException(
                "Cannot '{$action}' a payment in status '{$from->value}'."
            );
        }

        return self::TRANSITIONS[$action][$from->value];
    }
}
