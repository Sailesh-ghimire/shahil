<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Filament\Forms\OwnerSelect;
use App\Models\AuditLog;
use App\Support\Audit\AuditChanges;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject' => fn ($morph) => $morph->withoutGlobalScopes()]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('System'),
                TextColumn::make('event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                TextColumn::make('subject')
                    ->label('Record')
                    ->state(fn (AuditLog $record): string => static::describeSubject($record)),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn (AuditLog $record): string => AuditChanges::summarize($record))
                    ->wrap()
                    ->limit(200),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => OwnerSelect::options()),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn (): array => collect(Relation::morphMap() ?: [])
                        ->keys()
                        ->merge(AuditLog::query()->distinct()->pluck('subject_type'))
                        ->filter()
                        ->unique()
                        ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                        ->all()),
                SelectFilter::make('event')
                    ->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'restored' => 'Restored']),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    protected static function describeSubject(AuditLog $record): string
    {
        $type = class_basename((string) $record->subject_type);
        $attributes = $record->subject?->getAttributes() ?? [];
        $name = $attributes['name'] ?? $attributes['title'] ?? (isset($attributes['first_name']) ? trim($attributes['first_name'].' '.($attributes['last_name'] ?? '')) : null);

        return $type.': '.($name ?? '#'.$record->subject_id);
    }
}
