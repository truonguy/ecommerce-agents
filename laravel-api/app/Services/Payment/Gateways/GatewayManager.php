<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentMethod;
use InvalidArgumentException;

class GatewayManager
{
    /**
     * Resolve adapter theo method (COD|VNPAY hoặc string 'cod'/'vnpay').
     */
    public function for(PaymentMethod|string $method): PaymentGateway
    {
        $key = $method instanceof PaymentMethod ? $method->value : $method;

        return match (strtolower($key)) {
            'cod' => app(CodAdapter::class),
            'vnpay' => app(VnpayAdapter::class),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$key}"),
        };
    }
}
