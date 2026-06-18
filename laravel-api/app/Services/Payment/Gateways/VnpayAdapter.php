<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * VNPay: build URL redirect + verify chữ ký HMAC-SHA512 trên webhook/IPN.
 * Số tiền VNPay tính theo đơn vị ×100. Webhook = source of truth.
 */
class VnpayAdapter implements PaymentGateway
{
    public function create(Payment $payment): array
    {
        $ref = 'VNP-'.$payment->id.'-'.Str::upper(Str::random(6));

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => (string) config('payment.vnpay.tmn_code'),
            'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $ref,
            'vnp_OrderInfo' => 'Order #'.$payment->order_id,
            'vnp_ReturnUrl' => (string) config('payment.vnpay.return_url'),
        ];

        $secureHash = self::hash($params, (string) config('payment.vnpay.secret'));

        $query = http_build_query($params + ['vnp_SecureHash' => $secureHash], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => config('payment.vnpay.url').'?'.$query,
            'ref' => $ref,
        ];
    }

    public function verify(array $payload): array
    {
        $received = (string) ($payload['vnp_SecureHash'] ?? '');
        $params = Arr::except($payload, ['vnp_SecureHash', 'vnp_SecureHashType']);

        $expected = self::hash($params, (string) config('payment.vnpay.secret'));
        $valid = hash_equals($expected, $received);

        $status = ($payload['vnp_ResponseCode'] ?? null) === '00'
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;

        return [
            'ref' => $payload['vnp_TxnRef'] ?? null,
            'status' => $status,
            'valid' => $valid,
        ];
    }

    public function query(string $ref): PaymentStatus
    {
        // Không gọi API thật ở đây; reconciliation dùng adapter (fake trong test).
        // Mặc định: chưa có kết quả → coi như còn PROCESSING.
        return PaymentStatus::PROCESSING;
    }

    public function name(): string
    {
        return 'vnpay';
    }

    /**
     * Chữ ký HMAC-SHA512 trên query string (params đã sắp xếp). Public để test/verify tái dùng cùng công thức.
     *
     * @param  array<string, mixed>  $params
     */
    public static function hash(array $params, string $secret): string
    {
        ksort($params);
        $data = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return hash_hmac('sha512', $data, $secret);
    }
}
