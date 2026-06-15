<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Khoá tài khoản sau N lần fail liên tiếp (status → LOCKED). Áp dụng cho mọi model
 * có cột `failed_login_attempts` + `status` (Customer/Employee).
 */
class AccountLockout
{
    public function recordFailure(Model $account): void
    {
        $attempts = (int) $account->failed_login_attempts + 1;

        $data = ['failed_login_attempts' => $attempts];

        if ($attempts >= (int) config('auth_security.lockout_threshold')) {
            $data['status'] = UserStatus::LOCKED;
        }

        $account->forceFill($data)->save();
    }

    public function reset(Model $account): void
    {
        if ((int) $account->failed_login_attempts !== 0) {
            $account->forceFill(['failed_login_attempts' => 0])->save();
        }
    }
}
