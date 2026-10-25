<?php

namespace App\Filament\Imports;

use App\Actions\Sales\AssignLeadOwner;
use App\Enums\RecordSource;
use App\Filament\Imports\Concerns\HandlesDuplicates;
use App\Models\Lead;
use App\Support\Money;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Str;

/**
 * Duplicates are matched by email, then phone, among leads that are not converted yet.
 *
 * @property Lead|null $record
 */
class LeadImporter extends Importer
{
    use HandlesDuplicates;

    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->guess(['name', 'full name', 'contact name'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('company_name')
                ->label('Company')
                ->guess(['company', 'company name', 'business'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('phone')
                ->guess(['phone', 'mobile', 'phone number'])
                ->rules(['nullable', 'max:40']),
            ImportColumn::make('source')
                ->rules(['nullable'])
                ->castStateUsing(fn (?string $state): RecordSource => RecordSource::tryFrom(Str::lower(trim((string) $state))) ?? RecordSource::Import),
            ImportColumn::make('estimated_value')
                ->label('Estimated value')
                ->guess(['value', 'estimated value', 'amount', 'budget'])
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->castStateUsing(fn (mixed $state): int => Money::toMinor($state)),
            ImportColumn::make('notes')
                ->rules(['nullable', 'max:5000']),
        ];
    }

    public function resolveRecord(): ?Lead
    {
        $open = Lead::query()->where('status', '!=', 'converted');
        $existing = null;

        if (filled($this->data['email'] ?? null)) {
            $existing = (clone $open)->where('email', Str::lower(trim($this->data['email'])))->first();
        }

        if ($existing === null && filled($this->data['phone'] ?? null)) {
            $existing = (clone $open)->where('phone', trim($this->data['phone']))->first();
        }

        return $this->resolveWithDuplicateRule($existing, fn (): Lead => new Lead(['source' => RecordSource::Import]));
    }

    protected function beforeSave(): void
    {
        if (filled($this->record->email)) {
            $this->record->email = Str::lower(trim($this->record->email));
        }
    }

    protected function afterCreate(): void
    {
        $this->import->increment('created_rows');

        app(AssignLeadOwner::class)->handle($this->record);
    }
}
