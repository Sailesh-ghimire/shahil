<?php

namespace App\Policies;

use App\Models\User;

class ContactPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'contacts';
    }

    public function import(User $user): bool
    {
        return $user->can('contacts.import');
    }

    public function export(User $user): bool
    {
        return $user->can('contacts.export');
    }
}
