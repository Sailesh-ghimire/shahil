<?php

namespace App\Models;

use App\Enums\ContactMethodType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An email, phone number or social handle of a contact.
 *
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property ContactMethodType $type
 * @property string $value
 * @property string $normalized_value
 * @property ?string $label
 * @property bool $is_primary
 */
class ContactMethod extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'contact_id',
        'type',
        'value',
        'label',
        'is_primary',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactMethodType::class,
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContactMethod $method): void {
            $method->team_id ??= $method->contact?->team_id;

            if ($method->type->isPhoneNumber()) {
                $method->value = PhoneNumber::toE164($method->value) ?? trim($method->value);
            }

            $method->normalized_value = static::normalizeValue($method->value, $method->type);
        });
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public static function normalizeValue(string $value, ContactMethodType $type): string
    {
        return match ($type) {
            ContactMethodType::Email => Str::lower(trim($value)),
            ContactMethodType::Phone, ContactMethodType::WhatsApp => PhoneNumber::normalize($value),
            ContactMethodType::Instagram, ContactMethodType::Facebook => Str::lower(ltrim(trim($value), '@')),
        };
    }

    public static function guessType(string $value): ContactMethodType
    {
        return match (true) {
            str_contains($value, '@') && ! str_starts_with(trim($value), '@') => ContactMethodType::Email,
            str_starts_with(trim($value), '@') => ContactMethodType::Instagram,
            default => ContactMethodType::Phone,
        };
    }
}
