<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Bảng chỉ có created_at (không có updated_at).
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'guard',
        'email',
        'ip',
        'user_agent',
        'action',
        'result',
    ];
}
