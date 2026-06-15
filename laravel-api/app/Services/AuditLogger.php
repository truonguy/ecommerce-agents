<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Ghi audit log cho các sự kiện xác thực (bắt buộc cho CRM — spec §9).
 */
class AuditLogger
{
    public const ACTION_LOGIN = 'login';

    public const RESULT_SUCCESS = 'SUCCESS';

    public const RESULT_FAIL = 'FAIL';

    public function record(
        string $guard,
        ?string $email,
        string $action,
        string $result,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        AuditLog::create([
            'guard' => $guard,
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'action' => $action,
            'result' => $result,
        ]);
    }
}
