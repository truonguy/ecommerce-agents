<?php

/*
|--------------------------------------------------------------------------
| Payment Configuration
|--------------------------------------------------------------------------
| Timeout (phút) → payment quá hạn sẽ bị reconcile thành EXPIRED (spec §0.7).
| VNPay credentials qua env (KHÔNG commit secret thật).
*/

return [
    'timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 15),

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE', ''),
        'secret' => env('VNPAY_SECRET', 'test-secret'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', 'http://localhost/payment/return'),
    ],
];
