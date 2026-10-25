<?php

namespace App\Models;

use App\Enums\CompanySize;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasOwner;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasTimeline;
use App\Models\Concerns\PurgesTrashedRecords;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property ?string $industry
 * @property ?string $website
 * @property ?string $phone
 * @property ?string $address
 * @property ?string $pan_number
 * @property ?CompanySize $size
 * @property ?int $owner_id
 * @property ?string $notes
 * @property ?Carbon $last_activity_at
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use AuditsChanges, BelongsToWorkspace, HasFactory, HasOwner, HasTags, HasTasks, HasTimeline, PurgesTrashedRecords, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'industry',
        'website',
        'phone',
        'address',
        'pan_number',
        'size',
        'owner_id',
        'notes',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => CompanySize::class,
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * @return BelongsToMany<Contact, $this, CompanyContact>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->using(CompanyContact::class)
            ->withPivot(['id', 'team_id', 'job_title', 'is_primary'])
            ->withTimestamps();
    }
}
