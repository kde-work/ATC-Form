<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Enums\ImportStatus;
use App\Enums\RevisionStatus;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Строка списка импортов для таблицы UI.
 *
 * @mixin TariffImport
 */
final class TariffImportListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TariffImport $import */
        $import = $this->resource;

        /** @var TariffRevision|null $revision */
        $revision = $import->relationLoaded('revision') ? $import->revision : null;

        $summary = is_array($import->summary_json) ? $import->summary_json : [];
        $totalParsed = isset($summary['total']) && is_numeric($summary['total'])
            ? (int) $summary['total']
            : ($revision?->metadata_json['channel_count'] ?? null);

        $errorsCount = $import->relationLoaded('errors')
            ? $import->errors->count()
            : $import->errors()->count();

        return [
            'id' => $import->id,
            'uploaded_at' => $import->created_at?->toIso8601String(),
            'original_filename' => $import->original_filename,
            'file_size_bytes' => $import->file_size_bytes,
            'status' => $import->status->value,
            'uploaded_by' => new AdminUserResource($import->uploadedBy),
            'total_parsed' => $totalParsed,
            'errors_count' => $errorsCount,
            'revision' => $this->revisionSummary($revision),
            'actions' => [
                'can_activate' => $this->canActivate($import, $revision),
                'can_rollback' => $this->canRollback($revision),
            ],
        ];
    }

    /**
     * @return array{id: int, version_number: int, status: string, is_active: bool}|null
     */
    private function revisionSummary(?TariffRevision $revision): ?array
    {
        if ($revision === null) {
            return null;
        }

        return [
            'id' => $revision->id,
            'version_number' => $revision->version_number,
            'status' => $revision->status->value,
            'is_active' => $revision->status === RevisionStatus::Active,
        ];
    }

    private function canActivate(TariffImport $import, ?TariffRevision $revision): bool
    {
        return $import->status === ImportStatus::Validated
            && $revision !== null
            && $revision->status === RevisionStatus::Draft;
    }

    private function canRollback(?TariffRevision $revision): bool
    {
        return $revision !== null && $revision->status === RevisionStatus::Archived;
    }
}
