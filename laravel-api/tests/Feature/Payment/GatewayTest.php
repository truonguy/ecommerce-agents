<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Gateways\CodAdapter;
use App\Services\Payment\Gateways\GatewayManager;
use App\Services\Payment\Gateways\VnpayAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.vnpay.secret' => 'sekret',
            'payment.vnpay.tmn_code' => 'TMN01',
            'payment.vnpay.url' => 'https://gw.example/pay',
            'payment.vnpay.return_url' => 'https://app.example/return',
        ]);
    }

    private function manager(): GatewayManager
    {
        return app(GatewayManager::class);
    }

    public function test_manager_resolves_adapters(): void
    {
        $this->assertInstanceOf(CodAdapter::class, $this->manager()->for('cod'));
        $this->assertInstanceOf(VnpayAdapter::class, $this->manager()->for('vnpay'));
    }

    public function test_manager_unknown_gateway_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager()->for('paypal');
    }

    public function test_cod_create_returns_ref_without_url(): void
    {
        $payment = Payment::factory()->cod()->create();

        $result = $this->manager()->for('cod')->create($payment);

        $this->assertNull($result['url']);
        $this->assertNotEmpty($result['ref']);
    }

    public function test_vnpay_create_returns_url_with_signature(): void
    {
        $payment = Payment::factory()->create(['amount' => 50000]);

        $result = $this->manager()->for('vnpay')->create($payment);

        $this->assertNotEmpty($result['ref']);
        $this->assertStringContainsString('vnp_SecureHash', $result['url']);
        $this->assertStringContainsString('vnp_Amount=5000000', $result['url']); // ×100
    }

    public function test_vnpay_verify_valid_success_signature(): void
    {
        $params = ['vnp_TxnRef' => 'VNP-1', 'vnp_ResponseCode' => '00', 'vnp_Amount' => '5000000'];
        $payload = $params + ['vnp_SecureHash' => VnpayAdapter::hash($params, 'sekret')];

        $result = $this->manager()->for('vnpay')->verify($payload);

        $this->assertTrue($result['valid']);
        $this->assertSame(PaymentStatus::SUCCESS, $result['status']);
        $this->assertSame('VNP-1', $result['ref']);
    }

    public function test_vnpay_verify_failed_response_code(): void
    {
        $params = ['vnp_TxnRef' => 'VNP-2', 'vnp_ResponseCode' => '24', 'vnp_Amount' => '5000000'];
        $payload = $params + ['vnp_SecureHash' => VnpayAdapter::hash($params, 'sekret')];

        $result = $this->manager()->for('vnpay')->verify($payload);

        $this->assertTrue($result['valid']);
        $this->assertSame(PaymentStatus::FAILED, $result['status']);
    }

    public function test_vnpay_verify_tampered_signature_invalid(): void
    {
        $payload = [
            'vnp_TxnRef' => 'VNP-3', 'vnp_ResponseCode' => '00', 'vnp_Amount' => '5000000',
            'vnp_SecureHash' => 'deadbeef',
        ];

        $result = $this->manager()->for('vnpay')->verify($payload);

        $this->assertFalse($result['valid']);
    }
}
