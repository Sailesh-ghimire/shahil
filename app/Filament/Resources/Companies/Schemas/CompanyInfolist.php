<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('industry')->placeholder('—'),
                        TextEntry::make('website')
                            ->url(fn (Company $record): ?string => $record->website)
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('pan_number')->label('PAN / tax number')->placeholder('—'),
                        TextEntry::make('size')->placeholder('—'),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                        TextEntry::make('tags.name')->label('Tags')->badge()->placeholder('—'),
                        TextEntry::make('last_activity_at')->label('Last activity')->since()->placeholder('Never'),
                        TextEntry::make('created_at')->label('Added')->date(),
                        TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
