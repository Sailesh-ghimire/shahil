<?php

namespace App\Filament\Imports\Concerns;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Number;

/**
 * The "what to do with duplicates" choice and the created / updated / skipped / failed report.
 */
trait HandlesDuplicates
{
    public const DUPLICATES_UPDATE = 'update';

    public const DUPLICATES_SKIP = 'skip';

    public const DUPLICATES_CREATE = 'create';

    protected bool $wasExisting = false;

    /**
     * @return array<Component|Field>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Radio::make('duplicates')
                ->label('When a row matches an existing record')
                ->options([
                    self::DUPLICATES_UPDATE => 'Update the existing record',
                    self::DUPLICATES_SKIP => 'Skip the row',
                    self::DUPLICATES_CREATE => 'Create a new record anyway',
                ])
                ->default(self::DUPLICATES_UPDATE)
                ->required(),
        ];
    }

    protected function duplicateRule(): string
    {
        return $this->options['duplicates'] ?? self::DUPLICATES_UPDATE;
    }

    /**
     * Applies the duplicate rule to a matched record; null means "skip this row".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel|null  $existing
     * @param  callable(): TModel  $makeNew
     * @return TModel|null
     */
    protected function resolveWithDuplicateRule(mixed $existing, callable $makeNew): mixed
    {
        if ($existing === null || $this->duplicateRule() === self::DUPLICATES_CREATE) {
            return $makeNew();
        }

        if ($this->duplicateRule() === self::DUPLICATES_SKIP) {
            $this->import->increment('skipped_rows');

            return null;
        }

        // Imports don't go through the edit form: check the person importing may change this record.
        if (! $this->import->user->can('update', $existing)) {
            throw new RowImportFailedException('You can’t update this existing record (it belongs to someone else).');
        }

        $this->wasExisting = true;

        return $existing;
    }

    protected function afterCreate(): void
    {
        $this->import->increment('created_rows');
    }

    protected function afterUpdate(): void
    {
        $this->import->increment('updated_rows');
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Import finished';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $import->refresh();

        $parts = [
            Number::format($import->getAttribute('created_rows')).' created',
            Number::format($import->getAttribute('updated_rows')).' updated',
        ];

        if ($import->getAttribute('skipped_rows') > 0) {
            $parts[] = Number::format($import->getAttribute('skipped_rows')).' skipped';
        }

        $failed = $import->getFailedRowsCount();
        $parts[] = Number::format($failed).' failed'.($failed > 0 ? ' (download the failed rows to see why)' : '');

        return e($import->file_name).': '.implode(', ', $parts).'.';
    }
}
