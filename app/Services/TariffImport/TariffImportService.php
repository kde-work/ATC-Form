<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ImportErrorDraft;
use App\Dto\TariffImport\ParsedChannelRow;
use App\Enums\ImportStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\TariffImportFileException;
use App\Jobs\ProcessTariffImportJob;
use App\Models\DeliveryChannel;
use App\Models\TariffImport;
use App\Models\TariffImportError;
use App\Models\TariffRevision;
use App\Models\User;
use App\Services\ActiveTariffQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Оркестрация upload -> process -> draft/invalid revision.
 * MVP синхронный; ProcessTariffImportJob готов к очереди.
 */
final class TariffImportService
{
    public function __construct(
        private readonly TariffImportStorage $storage,
        private readonly TariffWorkbookParser $workbookParser,
        private readonly TariffImportValidationService $validationService,
        private readonly TariffRevisionDiffService $diffService,
        private readonly ActiveTariffQuery $activeTariffQuery,
    ) {
    }

    /**
     * Сохраняет файл, создаёт import и ставит обработку в очередь (sync по умолчанию).
     */
    public function upload(UploadedFile|File $file, User $uploader, bool $dispatch = true): TariffImport
    {
        $meta = $this->storage->store($file);

        $import = TariffImport::query()->create([
            'uploaded_by_user_id' => $uploader->id,
            'original_filename' => $meta['original_filename'],
            'stored_path' => $meta['stored_path'],
            'file_hash' => $meta['file_hash'],
            'file_size_bytes' => $meta['file_size_bytes'],
            'status' => ImportStatus::Uploaded,
            'summary_json' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        Log::info('Tariff import uploaded', [
            'tariff_import_id' => $import->id,
            'original_filename' => $import->original_filename,
            'file_size_bytes' => $import->file_size_bytes,
            'file_hash' => $import->file_hash,
        ]);

        if ($dispatch) {
            ProcessTariffImportJob::dispatch($import->id);
        }

        return $import->refresh();
    }

    /**
     * Синхронный upload + process для тестов и MVP без ожидания очереди.
     */
    public function uploadAndProcess(UploadedFile|File $file, User $uploader): TariffImport
    {
        $import = $this->upload($file, $uploader, dispatch: false);

        return $this->process($import);
    }

    /**
     * Парсинг, валидация, создание ревизии и summary.
     */
    public function process(TariffImport $import): TariffImport
    {
        $import->status = ImportStatus::Processing;
        $import->started_at = now();
        $import->finished_at = null;
        $import->save();

        try {
            $absolutePath = $this->storage->absolutePath($import);
            $parseResult = $this->workbookParser->parseFile($absolutePath);
            $errors = $this->validationService->validate($parseResult);

            return $this->persistResult($import, $parseResult->channels, $errors);
        } catch (TariffImportFileException $exception) {
            return $this->markFailed($import, $exception->getMessage(), 'file_unreadable');
        } catch (Throwable $exception) {
            Log::error('Tariff import processing failed', [
                'tariff_import_id' => $import->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->markFailed($import, 'Import processing failed.', 'processing_exception');
        }
    }

    /**
     * @param list<ParsedChannelRow> $channels
     * @param list<ImportErrorDraft> $errors
     */
    private function persistResult(TariffImport $import, array $channels, array $errors): TariffImport
    {
        return DB::transaction(function () use ($import, $channels, $errors): TariffImport {
            $this->replaceErrors($import, $errors);

            $hasErrors = $errors !== [];
            $versionNumber = $this->nextVersionNumber();

            $activeRevision = $this->activeTariffQuery->activeRevision();
            if ($activeRevision !== null) {
                $activeRevision->load('channels');
            }

            $summary = $this->diffService->summarize($channels, $activeRevision);

            $revision = TariffRevision::query()->create([
                'tariff_import_id' => $import->id,
                'version_number' => $versionNumber,
                'status' => $hasErrors ? RevisionStatus::Invalid : RevisionStatus::Draft,
                'metadata_json' => [
                    'channel_count' => count($channels),
                    'error_count' => count($errors),
                ],
            ]);

            foreach ($this->uniqueChannels($channels) as $channel) {
                DeliveryChannel::query()->create(array_merge(
                    ['tariff_revision_id' => $revision->id],
                    $channel->toChannelAttributes(),
                ));
            }

            $import->status = $hasErrors ? ImportStatus::ValidationFailed : ImportStatus::Validated;
            $import->summary_json = $summary;
            $import->finished_at = now();
            $import->save();

            return $import->refresh();
        });
    }

    /**
     * @param list<ImportErrorDraft> $errors
     */
    private function replaceErrors(TariffImport $import, array $errors): void
    {
        TariffImportError::query()
            ->where('tariff_import_id', $import->id)
            ->delete();

        foreach ($errors as $error) {
            TariffImportError::query()->create([
                'tariff_import_id' => $import->id,
                'sheet_name' => $error->sheetName,
                'row_number' => $error->rowNumber,
                'field' => $error->field,
                'error_code' => $error->errorCode,
                'message' => $error->message,
                'context_json' => $error->context,
            ]);
        }
    }

    private function markFailed(TariffImport $import, string $message, string $errorCode): TariffImport
    {
        return DB::transaction(function () use ($import, $message, $errorCode): TariffImport {
            TariffImportError::query()
                ->where('tariff_import_id', $import->id)
                ->delete();

            TariffImportError::query()->create([
                'tariff_import_id' => $import->id,
                'sheet_name' => null,
                'row_number' => null,
                'field' => 'workbook',
                'error_code' => $errorCode,
                'message' => $message,
                'context_json' => null,
            ]);

            $import->status = ImportStatus::Failed;
            $import->summary_json = null;
            $import->finished_at = now();
            $import->save();

            return $import->refresh();
        });
    }

    private function nextVersionNumber(): int
    {
        $max = TariffRevision::query()->max('version_number');

        return $max === null ? 1 : ((int) $max + 1);
    }

    /**
     * Дубли platform+code уже в ошибках валидации; в БД пишем только первое вхождение.
     *
     * @param list<ParsedChannelRow> $channels
     * @return list<ParsedChannelRow>
     */
    private function uniqueChannels(array $channels): array
    {
        $unique = [];
        $seen = [];

        foreach ($channels as $channel) {
            $key = $channel->platform->value . ':' . $channel->code;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $channel;
        }

        return $unique;
    }
}
