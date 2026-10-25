<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\RecordSource;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasOwner;
use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasTimeline;
use App\Models\Concerns\PurgesTrashedRecords;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An unverified enquiry. Converting it creates (or links) a contact, a company and a deal.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $handle
 * @property ?string $company_name
 * @property RecordSource $source
 * @property LeadStatus $status
 * @property int $estimated_value Minor units.
 * @property ?int $owner_id
 * @property ?int $contact_id
 * @property ?int $converted_contact_id
 * @property ?int $converted_company_id
 * @property ?int $converted_deal_id
 * @property ?Carbon $converted_at
 * @property ?string $unqualified_reason
 * @property int $board_position
 * @property ?string $notes
 * @property ?Carbon $last_activity_at
 * @property Carbon $created_at
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use AuditsChanges, BelongsToWorkspace, HasFactory, HasOwner, HasTasks, HasTimeline, PurgesTrashedRecords, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'email',
        'phone',
        'handle',
        'company_name',
        'source',
        'status',
        'estimated_value',
        'owner_id',
        'contact_id',
        'unqualified_reason',
        'board_position',
        'notes',
        'last_activity_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
        'status' => 'new',
        'estimated_value' => 0,
        'board_position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RecordSource::class,
            'status' => LeadStatus::class,
            'estimated_value' => 'integer',
            'converted_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * The existing contact this lead matched (by phone or email), if any.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function convertedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'converted_deal_id');
    }

    public function isConverted(): bool
    {
        return $this->status === LeadStatus::Converted;
    }
}
