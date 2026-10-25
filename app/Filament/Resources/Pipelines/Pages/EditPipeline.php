<?php

namespace App\Filament\Resources\Pipelines\Pages;

use App\Filament\Resources\Pipelines\Pages\Concerns\SavesPipelineStages;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Pipeline;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPipeline extends EditRecord
{
    use SavesPipelineStages;

    protected static string $resource = PipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->getRecord();

        $this->ensureRemovedStagesAreEmpty($pipeline);
    }

    protected function afterSave(): void
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->getRecord();

        $this->keepSingleDefault($pipeline);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pipeline saved';
    }
}
