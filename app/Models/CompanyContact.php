<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A contact's job at a company.
 *
 * @property int $id
 * @property int $team_id
 * @property int $company_id
 * @property int $contact_id
 * @property ?string $job_title
 * @property bool $is_primary
 */
class CompanyContact extends Pivot
{
    use BelongsToWorkspace;

    protected $table = 'company_contact';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CompanyContact $link): void {
            $link->team_id ??= Contact::query()->whereKey($link->contact_id)->value('team_id');
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
