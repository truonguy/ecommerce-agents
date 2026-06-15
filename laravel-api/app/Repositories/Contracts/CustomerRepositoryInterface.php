<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;

interface CustomerRepositoryInterface
{
    public function findByEmail(string $email): ?Customer;

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): Customer;
}
