<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MyNewLeadsTable extends TableWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('New leads for me')
            ->description('Not contacted yet, newest first')
            ->query(fn (): Builder => Lead::query()->where('owner_id', auth()->id())->where('status', LeadStatus::New))
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No new leads')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (Lead $record): ?string => $record->company_name),
                TextColumn::make('source')->badge(),
                TextColumn::make('created_at')->label('Received')->since(),
            ]);
    }
}
