<?php

namespace App\Repositories\Contracts;

use App\Models\Employee;

interface EmployeeRepositoryInterface
{
    public function findByEmail(string $email): ?Employee;
}
