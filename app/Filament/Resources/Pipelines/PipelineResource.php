<?php

namespace App\Filament\Resources\Pipelines;

use App\Filament\Resources\Pipelines\Pages\CreatePipeline;
use App\Filament\Resources\Pipelines\Pages\EditPipeline;
use App\Filament\Resources\Pipelines\Pages\ListPipelines;
use App\Filament\Resources\Pipelines\Schemas\PipelineForm;
use App\Filament\Resources\Pipelines\Tables\PipelinesTable;
use App\Models\Pipeline;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PipelineResource extends Resource
{
    protected static ?string $model = Pipeline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PipelineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PipelinesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPipelines::route('/'),
            'create' => CreatePipeline::route('/create'),
            'edit' => EditPipeline::route('/{record}/edit'),
        ];
    }
}
