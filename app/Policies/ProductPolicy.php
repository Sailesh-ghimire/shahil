<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Everyone who can see deals can see the catalogue; products.manage changes it.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('deals.view') || $user->can('products.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function import(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function export(User $user): bool
    {
        return $user->can('products.manage');
    }
}
