<?php

namespace App\Filament\Pages;

use App\Filament\Forms\OwnerSelect;
use App\Filament\Widgets\Reports\ActivityMixChart;
use App\Filament\Widgets\Reports\FirstReplyTimeChart;
use App\Filament\Widgets\Reports\IdleDealsBubbleChart;
use App\Filament\Widgets\Reports\LeadFunnelWidget;
use App\Filament\Widgets\Reports\LeadSourcesChart;
use App\Filament\Widgets\Reports\LostReasonsChart;
use App\Filament\Widgets\Reports\PipelineByStageChart;
use App\Filament\Widgets\Reports\ReportStatsOverview;
use App\Filament\Widgets\Reports\RepScorecardChart;
use App\Filament\Widgets\Reports\RevenueTargetChart;
use App\Filament\Widgets\Reports\StaleDealsTable;
use App\Filament\Widgets\Reports\WonLostByRepChart;
use App\Models\Pipeline;
use App\Support\Reports\ReportFilters;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;
use UnitEnum;

/**
 * Reports: one filters form (dates, owner, pipeline) read by every widget.
 */
class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static ?string $title = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view.own') || auth()->user()?->can('reports.view.team');
    }

    public function filtersForm(Schema $schema): Schema
    {
        $locked = ReportFilters::lockedOwnerId() !== null;

        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->default(now()->startOfMonth()->toDateString())
                            ->maxDate(now()),
                        DatePicker::make('until')
                            ->label('Until')
                            ->default(now()->toDateString())
                            ->maxDate(now()),
                        Select::make('owner_id')
                            ->label('Owner')
                            ->options(fn (): array => OwnerSelect::options())
                            ->placeholder('Whole team')
                            ->default($locked ? auth()->id() : null)
                            ->disabled($locked)
                            ->helperText($locked ? 'You see your own numbers.' : null),
                        Select::make('pipeline_id')
                            ->label('Pipeline')
                            ->options(fn (): array => Pipeline::query()->orderBy('sort')->pluck('name', 'id')->all())
                            ->placeholder('All pipelines'),
                    ]),
            ]);
    }

    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            ReportStatsOverview::class,
            RevenueTargetChart::class,
            PipelineByStageChart::class,
            LeadSourcesChart::class,
            WonLostByRepChart::class,
            LostReasonsChart::class,
            ActivityMixChart::class,
            RepScorecardChart::class,
            IdleDealsBubbleChart::class,
            FirstReplyTimeChart::class,
            LeadFunnelWidget::class,
            StaleDealsTable::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
