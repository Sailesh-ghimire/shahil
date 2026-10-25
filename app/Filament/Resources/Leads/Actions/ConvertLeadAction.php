<?php

namespace App\Filament\Resources\Leads\Actions;

use App\Actions\Sales\ConvertLead;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Lead;
use App\Models\Pipeline;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Convert: contact + company + deal in one step.
 */
class ConvertLeadAction
{
    public static function make(): Action
    {
        return Action::make('convert')
            ->label('Convert')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('success')
            ->authorize('convert')
            ->modalHeading(fn (Lead $record): string => 'Convert '.$record->name)
            ->modalDescription('Creates (or links) the contact and company, and opens a deal in the first stage.')
            ->fillForm(fn (Lead $record): array => [
                'deal_title' => $record->company_name ?: $record->name,
                'pipeline_id' => Pipeline::defaultForCurrentWorkspace()?->getKey(),
                'amount' => $record->estimated_value,
                'create_company' => filled($record->company_name),
            ])
            ->schema([
                TextInput::make('deal_title')
                    ->label('Deal title')
                    ->required()
                    ->maxLength(255),
                Select::make('pipeline_id')
                    ->label('Pipeline')
                    ->options(fn (): array => Pipeline::query()->orderBy('sort')->pluck('name', 'id')->all())
                    ->required(),
                MoneyInput::make('amount')
                    ->label('Deal value'),
                Toggle::make('create_company')
                    ->label('Create or link the company'),
            ])
            ->action(function (Lead $record, array $data, Action $action): void {
                try {
                    $result = app(ConvertLead::class)->handle($record, [
                        'deal_title' => $data['deal_title'],
                        'pipeline_id' => (int) $data['pipeline_id'],
                        'amount' => (int) $data['amount'],
                        'create_company' => (bool) $data['create_company'],
                    ]);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Lead not converted')->body($exception->getMessage())->warning()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Lead converted')
                    ->body(e($record->name).' is now a contact with the deal '.e($result['deal']->title).'.')
                    ->success()
                    ->actions([
                        Action::make('viewContact')
                            ->label('View contact')
                            ->button()
                            ->url(ContactResource::getUrl('view', ['record' => $result['contact']])),
                        Action::make('viewDeal')
                            ->label('View deal')
                            ->link()
                            ->url(DealResource::getUrl('view', ['record' => $result['deal']])),
                    ])
                    ->send();
            });
    }
}
