<?php

namespace App\Services\Crm;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function list(): Collection
    {
        return $this->categories->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return $this->categories->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        return $this->categories->update($category, $data);
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }
}
