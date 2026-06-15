<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Guard dùng cho RBAC (spatie) — gắn role/permission theo guard `employee`.
     */
    protected string $guard_name = 'employee';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'failed_login_attempts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'failed_login_attempts' => 'integer',
        ];
    }
}
