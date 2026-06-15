<?php

namespace App\Services\Crm;

use App\Enums\UserStatus;
use App\Exceptions\AccountNotActiveException;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class EmployeeAuthService
{
    private const GUARD = 'employee';

    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Đăng nhập employee/admin (phân hệ CRM). Ghi audit log mọi lần (success/fail).
     *
     * @return array{access_token: string, type: string, role: string|null}
     *
     * @throws AuthenticationException sai email/mật khẩu (generic)
     * @throws AccountNotActiveException status != ACTIVE
     */
    public function login(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array
    {
        $employee = $this->employees->findByEmail($email);

        if (! $employee || ! Hash::check($password, $employee->password)) {
            $this->audit($email, AuditLogger::RESULT_FAIL, $ip, $userAgent);
            throw new AuthenticationException('Invalid credentials.');
        }

        if ($employee->status !== UserStatus::ACTIVE) {
            $this->audit($email, AuditLogger::RESULT_FAIL, $ip, $userAgent);
            throw new AccountNotActiveException;
        }

        $token = $employee->createToken('crm')->plainTextToken;

        $this->audit($email, AuditLogger::RESULT_SUCCESS, $ip, $userAgent);

        return [
            'access_token' => $token,
            'type' => 'employee',
            'role' => $employee->getRoleNames()->first(),
        ];
    }

    private function audit(?string $email, string $result, ?string $ip, ?string $userAgent): void
    {
        $this->audit->record(self::GUARD, $email, AuditLogger::ACTION_LOGIN, $result, $ip, $userAgent);
    }
}
