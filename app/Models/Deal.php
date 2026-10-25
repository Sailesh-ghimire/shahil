<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasOwner;
use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasTimeline;
use App\Models\Concerns\PurgesTrashedRecords;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property ?int $company_id
 * @property ?int $contact_id
 * @property int $pipeline_id
 * @property int $stage_id
 * @property int $amount Minor units.
 * @property string $currency
 * @property ?Carbon $expected_close_on
 * @property ?Carbon $closed_at
 * @property ?string $lost_reason
 * @property ?int $owner_id
 * @property ?int $lead_id
 * @property ?Carbon $stage_changed_at
 * @property ?Carbon $last_activity_at
 * @property int $board_position
 * @property ?string $notes
 * @property Carbon $created_at
 * @property-read Stage $stage
 * @property-read Pipeline $pipeline
 * @property-read ?Company $company
 * @property-read ?Contact $contact
 */
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use AuditsChanges, BelongsToWorkspace, HasFactory, HasOwner, HasTasks, HasTimeline, PurgesTrashedRecords, SoftDeletes;

    /**
     * A deal with no activity for this many days is flagged as stale on the board.
     */
    public const STALE_AFTER_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'title',
        'company_id',
        'contact_id',
        'pipeline_id',
        'stage_id',
        'amount',
        'currency',
        'expected_close_on',
        'closed_at',
        'lost_reason',
        'owner_id',
        'lead_id',
        'stage_changed_at',
        'last_activity_at',
        'board_position',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount' => 0,
        'currency' => 'NPR',
        'board_position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expected_close_on' => 'date',
            'closed_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Deal $deal): void {
            $deal->stage_changed_at ??= now();
            $deal->currency = Team::query()->whereKey($deal->team_id)->value('currency') ?? $deal->currency;
        });
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
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

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<DealProduct, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(DealProduct::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Deal value = sum of line totals, when the deal has line items.
     */
    public function recalculateAmount(): void
    {
        if (! $this->lineItems()->exists()) {
            return;
        }

        $this->forceFill(['amount' => (int) $this->lineItems()->sum('line_total')])->saveQuietly();
    }

    public function daysIdle(): int
    {
        $since = $this->last_activity_at ?? $this->stage_changed_at ?? $this->created_at;

        return (int) $since->diffInDays(now());
    }

    public function isStale(): bool
    {
        return $this->closed_at === null && $this->daysIdle() >= self::STALE_AFTER_DAYS;
    }

    /**
     * @param  Builder<Deal>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('closed_at'));
    }
}
