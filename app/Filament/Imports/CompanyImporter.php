<?php

namespace App\Filament\Imports;

use App\Enums\CompanySize;
use App\Filament\Imports\Concerns\HandlesDuplicates;
use App\Models\Company;
use App\Models\Tag;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

/**
 * Duplicates are matched by company name (case-insensitive) within the workspace.
 *
 * @property Company|null $record
 */
class CompanyImporter extends Importer
{
    use HandlesDuplicates;

    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->guess(['name', 'company', 'company name', 'organisation', 'organization'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('industry')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('website')
                ->guess(['website', 'url', 'web'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['nullable', 'max:40']),
            ImportColumn::make('address')
                ->guess(['address', 'city', 'location'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('pan_number')
                ->label('PAN / tax number')
                ->guess(['pan', 'pan number', 'vat', 'tax number', 'tax id'])
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('size')
                ->rules(['nullable'])
                ->castStateUsing(fn (?string $state): ?CompanySize => CompanySize::tryFrom(trim((string) $state))),
            ImportColumn::make('tags')
                ->rules(['nullable', 'max:500'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('notes')
                ->rules(['nullable', 'max:5000']),
        ];
    }

    public function resolveRecord(): ?Company
    {
        $existing = Company::query()->where('name', trim((string) $this->data['name']))->first();

        return $this->resolveWithDuplicateRule($existing, fn (): Company => new Company([
            'owner_id' => $this->import->user_id,
        ]));
    }

    protected function afterSave(): void
    {
        if (blank($this->data['tags'] ?? null)) {
            return;
        }

        $company = $this->record;

        $tagIds = collect(explode(',', $this->data['tags']))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(['team_id' => $company->team_id, 'name' => $name])->getKey());

        $company->tags()->syncWithoutDetaching($tagIds->all());
    }
}
