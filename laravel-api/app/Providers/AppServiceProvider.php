<?php

namespace App\Providers;

use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Binding repository interface → Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerRepositoryInterface::class => CustomerRepository::class,
        EmployeeRepositoryInterface::class => EmployeeRepository::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-logout theo inactivity (spec §7, AC-07.4): từ chối token nếu
        // last_used_at (hoặc created_at) cũ hơn ngưỡng inactivity_timeout.
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $timeout = (int) config('sanctum.inactivity_timeout', 0);

            if ($timeout <= 0) {
                return $isValid;
            }

            $idleSince = $accessToken->last_used_at ?? $accessToken->created_at;

            if ($idleSince && $idleSince->lt(now()->subMinutes($timeout))) {
                return false;
            }

            return $isValid;
        });
    }
}
