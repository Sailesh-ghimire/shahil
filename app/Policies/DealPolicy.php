<?php

namespace App\Policies;

use App\Models\User;

class DealPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'deals';
    }

    public function import(User $user): bool
    {
        return $user->can('contacts.import');
    }

    public function export(User $user): bool
    {
        return $user->can('deals.export');
    }
}
