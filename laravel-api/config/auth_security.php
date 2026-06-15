<?php

/*
|--------------------------------------------------------------------------
| Auth Security Parameters
|--------------------------------------------------------------------------
| ⚠️ Giá trị mặc định là ĐỀ XUẤT của dev (spec §7) — cần BA xác nhận trước khi
| merge (Open Question §9.1). Có thể override qua biến môi trường.
*/

return [
    // Rate limit login: số lần THẤT BẠI tối đa trong cửa sổ, theo (IP + email).
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),

    // Khoá tài khoản (status=LOCKED) sau N lần fail liên tiếp.
    'lockout_threshold' => (int) env('LOCKOUT_THRESHOLD', 10),
];
