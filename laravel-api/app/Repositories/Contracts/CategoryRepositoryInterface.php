<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    /**
     * @return Collection<int, Category>
     */
    public function all(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category;

    public function delete(Category $category): void;
}
