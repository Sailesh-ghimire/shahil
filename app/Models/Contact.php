<?php

namespace App\Models;

use App\Enums\ContactMethodType;
use App\Enums\RecordSource;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasOwner;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasTimeline;
use App\Models\Concerns\PurgesTrashedRecords;
use App\Support\PhoneNumber;
use App\Support\Workspace\CurrentWorkspace;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person. One contact can have many emails/phones/handles and work at several companies.
 *
 * @property int $id
 * @property int $team_id
 * @property string $first_name
 * @property ?string $last_name
 * @property-read string $name
 * @property ?int $owner_id
 * @property ?RecordSource $source
 * @property ?string $notes
 * @property ?Carbon $last_activity_at
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use AuditsChanges, BelongsToWorkspace, HasFactory, HasOwner, HasTags, HasTasks, HasTimeline, PurgesTrashedRecords, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'first_name',
        'last_name',
        'owner_id',
        'source',
        'notes',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RecordSource::class,
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return HasMany<ContactMethod, $this>
     */
    public function methods(): HasMany
    {
        return $this->hasMany(ContactMethod::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * @return HasMany<ContactMethod, $this>
     */
    public function emails(): HasMany
    {
        return $this->methods()->where('type', ContactMethodType::Email);
    }

    /**
     * @return HasMany<ContactMethod, $this>
     */
    public function phones(): HasMany
    {
        return $this->methods()->whereIn('type', [ContactMethodType::Phone, ContactMethodType::WhatsApp]);
    }

    /**
     * @return BelongsToMany<Company, $this, CompanyContact>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyContact::class)
            ->withPivot(['id', 'team_id', 'job_title', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Deals where this person is the primary contact.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * The pivot rows themselves, so a form repeater can edit company + job title pairs.
     *
     * @return HasMany<CompanyContact, $this>
     */
    public function companyLinks(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    /**
     * Reads the loaded `methods` (primary first), so list pages can eager load one relation.
     */
    public function primaryEmail(): ?string
    {
        return $this->methods->firstWhere('type', ContactMethodType::Email)?->value;
    }

    public function primaryPhone(): ?string
    {
        return $this->methods
            ->first(fn (ContactMethod $method): bool => $method->type->isPhoneNumber())
            ?->value;
    }

    /**
     * Fast search for big lists: names, emails, phones and handles that start with the search text
     * (prefix matches use indexes; "contains" searches would scan every row).
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public static function scopeSearchPrefix(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $words = preg_split('/\s+/', $search) ?: [$search];
        $candidates = array_unique(array_filter([
            mb_strtolower(ltrim($search, '@')),
            preg_match('/^[\d\s()+-]{6,}$/', $search) === 1 ? PhoneNumber::normalize($search) : null,
        ]));

        return $query->where(function (Builder $query) use ($search, $words, $candidates): void {
            $query->where('first_name', 'like', $search.'%')
                ->orWhere('last_name', 'like', $search.'%')
                ->orWhereIn('contacts.id', function ($methods) use ($candidates): void {
                    $methods->select('contact_id')
                        ->from('contact_methods')
                        ->where('team_id', app(CurrentWorkspace::class)->id())
                        ->where(function ($match) use ($candidates): void {
                            foreach ($candidates as $candidate) {
                                $match->orWhere('normalized_value', 'like', $candidate.'%');
                            }
                        });
                });

            if (count($words) === 2) {
                $query->orWhere(fn (Builder $name) => $name->where('first_name', 'like', $words[0].'%')->where('last_name', 'like', $words[1].'%'));
            }
        });
    }

    /**
     * Contacts in the current workspace with this email, phone or handle.
     *
     * @param  Builder<Contact>  $query
     */
    public function scopeWithMethod(Builder $query, string $value, ?ContactMethodType $type = null): void
    {
        $normalized = ContactMethod::normalizeValue($value, $type ?? ContactMethod::guessType($value));

        $query->whereHas('methods', function (Builder $methods) use ($normalized, $type): void {
            $methods->where('normalized_value', $normalized);

            if ($type !== null) {
                $methods->where('type', $type);
            }
        });
    }
}
