<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeadPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'leads';
    }

    /**
     * Converted leads stay read-only for reporting.
     */
    public function update(User $user, Model $record): bool
    {
        /** @var Lead $record */
        return ! $record->isConverted() && parent::update($user, $record);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return ! $lead->isConverted() && $user->can('leads.convert');
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
