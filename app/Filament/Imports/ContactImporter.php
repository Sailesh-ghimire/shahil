<?php

namespace App\Filament\Imports;

use App\Enums\ContactMethodType;
use App\Enums\RecordSource;
use App\Filament\Imports\Concerns\HandlesDuplicates;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMethod;
use App\Models\Tag;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Str;

/**
 * Columns: first name (required), last name, email, phone, WhatsApp, company, job title, source, owner email, tags.
 * Duplicates are matched by email first, then phone.
 *
 * @property Contact|null $record
 */
class ContactImporter extends Importer
{
    use HandlesDuplicates;

    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('first_name')
                ->label('First name')
                ->guess(['first name', 'firstname', 'given name', 'name', 'full name'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('last_name')
                ->label('Last name')
                ->guess(['last name', 'lastname', 'surname', 'family name'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('email')
                ->guess(['email', 'email address', 'e-mail'])
                ->rules(['nullable', 'email', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('phone')
                ->guess(['phone', 'mobile', 'phone number', 'cell'])
                ->rules(['nullable', 'max:40'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('whatsapp')
                ->label('WhatsApp')
                ->guess(['whatsapp', 'whatsapp number'])
                ->rules(['nullable', 'max:40'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('company')
                ->guess(['company', 'company name', 'organisation', 'organization', 'business'])
                ->rules(['nullable', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('job_title')
                ->label('Job title')
                ->guess(['job title', 'title', 'position', 'designation', 'role'])
                ->rules(['nullable', 'max:255'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('source')
                ->rules(['nullable'])
                ->castStateUsing(fn (?string $state): RecordSource => RecordSource::tryFrom(Str::lower(trim((string) $state))) ?? RecordSource::Import),
            ImportColumn::make('owner')
                ->label('Owner email')
                ->guess(['owner', 'owner email', 'assigned to'])
                ->rules(['nullable', 'email'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('tags')
                ->guess(['tags', 'labels'])
                ->rules(['nullable', 'max:500'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('notes')
                ->rules(['nullable', 'max:5000']),
        ];
    }

    public function resolveRecord(): ?Contact
    {
        $this->splitFullName();

        $existing = null;

        if (filled($this->data['email'] ?? null)) {
            $existing = Contact::query()->withMethod($this->data['email'], ContactMethodType::Email)->first();
        }

        foreach (['phone', 'whatsapp'] as $phoneColumn) {
            if ($existing === null && filled($this->data[$phoneColumn] ?? null)) {
                $existing = Contact::query()->withMethod($this->data[$phoneColumn], ContactMethodType::Phone)->first()
                    ?? Contact::query()->withMethod($this->data[$phoneColumn], ContactMethodType::WhatsApp)->first();
            }
        }

        return $this->resolveWithDuplicateRule($existing, fn (): Contact => new Contact([
            'source' => RecordSource::Import,
            'owner_id' => $this->import->user_id,
        ]));
    }

    protected function beforeSave(): void
    {
        $ownerEmail = $this->data['owner'] ?? null;

        if (filled($ownerEmail)) {
            $owner = User::query()
                ->where('email', Str::lower($ownerEmail))
                ->whereHas('teams', fn ($query) => $query->whereKey($this->record->team_id ?? app(CurrentWorkspace::class)->id()))
                ->first();

            if ($owner !== null) {
                $this->record->owner_id = $owner->getKey();
            }
        }
    }

    protected function afterSave(): void
    {
        $contact = $this->record;

        $this->addMethod($contact, ContactMethodType::Email, $this->data['email'] ?? null);
        $this->addMethod($contact, ContactMethodType::Phone, $this->data['phone'] ?? null);
        $this->addMethod($contact, ContactMethodType::WhatsApp, $this->data['whatsapp'] ?? null);

        if (filled($this->data['company'] ?? null)) {
            $company = Company::query()->firstOrCreate(
                ['team_id' => $contact->team_id, 'name' => trim($this->data['company'])],
                ['owner_id' => $contact->owner_id],
            );

            $contact->companies()->syncWithoutDetaching([
                $company->getKey() => ['team_id' => $contact->team_id, 'job_title' => $this->data['job_title'] ?? null],
            ]);
        }

        if (filled($this->data['tags'] ?? null)) {
            $tagIds = collect(explode(',', $this->data['tags']))
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->map(fn (string $name): int => Tag::query()->firstOrCreate(['team_id' => $contact->team_id, 'name' => $name])->getKey());

            $contact->tags()->syncWithoutDetaching($tagIds->all());
        }
    }

    protected function addMethod(Contact $contact, ContactMethodType $type, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        $normalized = ContactMethod::normalizeValue($value, $type);

        if ($contact->methods()->where('type', $type)->where('normalized_value', $normalized)->exists()) {
            return;
        }

        $contact->methods()->create([
            'team_id' => $contact->team_id,
            'type' => $type,
            'value' => $value,
            'is_primary' => ! $contact->methods()->where('type', $type)->exists(),
        ]);
    }

    /**
     * A sheet with only a "Name" column fills first_name with the full name; split it.
     */
    protected function splitFullName(): void
    {
        $first = trim((string) ($this->data['first_name'] ?? ''));

        if (blank($this->data['last_name'] ?? null) && str_contains($first, ' ')) {
            [$this->data['first_name'], $this->data['last_name']] = explode(' ', $first, 2);
        }
    }
}
