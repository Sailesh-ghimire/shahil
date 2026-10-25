<?php

namespace App\Filament\Actions;

use App\Support\Spreadsheets\XlsxToCsv;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Filament's importer reads CSV. This action also accepts .xlsx: the first sheet is converted
 * to CSV (OpenSpout) and then goes through exactly the same importer.
 */
class SpreadsheetImportAction extends ImportAction
{
    /**
     * @var list<string>
     */
    public const XLSX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $parentSchema = $this->schema;

        $this->schema(function (ImportAction $action) use ($parentSchema): array {
            $components = $action->evaluate($parentSchema);

            foreach ($components as $component) {
                if ($component instanceof FileUpload && $component->getName() === 'file') {
                    $component
                        ->acceptedFileTypes([...$component->getAcceptedFileTypes() ?? [], ...self::XLSX_MIME_TYPES])
                        ->helperText('CSV or Excel (.xlsx). For Excel files the first sheet is imported.');
                }
            }

            return $components;
        });

        $this->chunkSize(100);
    }

    /**
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            return XlsxToCsv::convert($file->getRealPath());
        }

        return parent::getUploadedFileStream($file);
    }

    /**
     * @return array<mixed>
     */
    public function getFileValidationRules(): array
    {
        return array_map(
            fn (mixed $rule): mixed => $rule === 'extensions:csv,txt' ? 'extensions:csv,txt,xlsx' : $rule,
            parent::getFileValidationRules(),
        );
    }
}
