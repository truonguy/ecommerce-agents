<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function findByEmail(string $email): ?Employee
    {
        return Employee::query()->where('email', $email)->first();
    }
}
