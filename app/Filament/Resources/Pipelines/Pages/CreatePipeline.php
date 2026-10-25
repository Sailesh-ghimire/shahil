<?php

namespace App\Filament\Resources\Pipelines\Pages;

use App\Filament\Resources\Pipelines\Pages\Concerns\SavesPipelineStages;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Pipeline;
use Filament\Resources\Pages\CreateRecord;

class CreatePipeline extends CreateRecord
{
    use SavesPipelineStages;

    protected static string $resource = PipelineResource::class;

    protected function afterCreate(): void
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->getRecord();

        $this->keepSingleDefault($pipeline);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pipeline created';
    }
}
