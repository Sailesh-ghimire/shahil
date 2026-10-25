<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Actions\Sales\MoveDealToStage;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use App\Models\Stage;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * A stage change from the form goes through MoveDealToStage, like a move on the board.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Deal $record */
        $stageId = (int) $data['stage_id'];

        if ((int) $data['pipeline_id'] !== $record->pipeline_id) {
            $record->pipeline_id = (int) $data['pipeline_id'];
        }

        $record->update(Arr::except($data, ['stage_id', 'lost_reason', 'pipeline_id']));

        if ($stageId !== $record->stage_id || filled($data['lost_reason'] ?? null)) {
            app(MoveDealToStage::class)->handle($record, Stage::query()->findOrFail($stageId), lostReason: $data['lost_reason'] ?? null);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Deal saved';
    }
}
