<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Enums\ImportStatus;
use App\Enums\RevisionStatus;
use App\Models\DeliveryChannel;
use App\Models\TariffImport;
use App\Models\TariffImportError;
use App\Models\TariffRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Детали импорта: summary, parsed, diff, errors для UI раздела 10.
 *
 * @mixin TariffImport
 */
final class TariffImportDetailResource extends JsonResource
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

        /** @var Collection<int, DeliveryChannel> $channels */
        $channels = $revision !== null && $revision->relationLoaded('channels')
            ? $revision->channels
            : new Collection();

        /** @var Collection<int, TariffImportError> $errors */
        $errors = $import->relationLoaded('errors')
            ? $import->errors
            : new Collection();

        $summary = is_array($import->summary_json) ? $import->summary_json : null;

        return [
            'id' => $import->id,
            'uploaded_at' => $import->created_at?->toIso8601String(),
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
            'original_filename' => $import->original_filename,
            'file_hash' => $import->file_hash,
            'file_size_bytes' => $import->file_size_bytes,
            'status' => $import->status->value,
            'uploaded_by' => new AdminUserResource($import->uploadedBy),
            'summary' => $summary,
            'diff' => $summary,
            'parsed' => [
                'total' => $channels->count(),
                'by_platform' => $this->countByPlatform($channels),
                'tariffs' => AdminTariffResource::collection($channels),
            ],
            'errors' => TariffImportErrorResource::collection($errors),
            'revision' => $this->revisionPayload($revision),
            'actions' => [
                'can_activate' => $this->canActivate($import, $revision),
                'can_rollback' => $this->canRollback($revision),
            ],
        ];
    }

    /**
     * @param  Collection<int, DeliveryChannel>  $channels
     * @return array<string, int>
     */
    private function countByPlatform(Collection $channels): array
    {
        $counts = [];
        foreach ($channels as $channel) {
            $key = $channel->platform->value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function revisionPayload(?TariffRevision $revision): ?array
    {
        if ($revision === null) {
            return null;
        }

        return [
            'id' => $revision->id,
            'version_number' => $revision->version_number,
            'status' => $revision->status->value,
            'is_active' => $revision->status === RevisionStatus::Active,
            'activated_at' => $revision->activated_at?->toIso8601String(),
            'archived_at' => $revision->archived_at?->toIso8601String(),
            'activated_by' => $revision->relationLoaded('activatedBy') && $revision->activatedBy !== null
                ? new AdminUserResource($revision->activatedBy)
                : null,
            'metadata' => $revision->metadata_json,
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
