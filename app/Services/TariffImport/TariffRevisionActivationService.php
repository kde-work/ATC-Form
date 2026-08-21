<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Enums\ImportStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\TariffRevisionActivationException;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use App\Models\User;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Support\Facades\DB;

/**
 * Активация draft/archived ревизии и rollback в транзакции.
 */
final class TariffRevisionActivationService
{
    public function __construct(
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    /**
     * Activate: текущая active -> archived, выбранная draft -> active.
     */
    public function activate(TariffImport $import, User $actor): TariffRevision
    {
        $revision = $import->revision;
        if ($revision === null) {
            throw new TariffRevisionActivationException('Import has no revision to activate.');
        }

        if ($import->status !== ImportStatus::Validated) {
            throw new TariffRevisionActivationException('Only validated imports can be activated.');
        }

        if ($revision->status !== RevisionStatus::Draft) {
            throw new TariffRevisionActivationException('Only draft revisions can be activated from import.');
        }

        if ($import->errors()->exists()) {
            throw new TariffRevisionActivationException('Cannot activate revision with validation errors.');
        }

        if (! $revision->channels()->exists()) {
            throw new TariffRevisionActivationException('Cannot activate an empty revision.');
        }

        $result = $this->switchActive($revision, $actor, markImportActivated: true);
        $this->formDataCache->forget();

        return $result;
    }

    /**
     * Rollback: активировать ранее валидную archived revision.
     */
    public function rollback(TariffRevision $revision, User $actor): TariffRevision
    {
        if ($revision->status !== RevisionStatus::Archived) {
            throw new TariffRevisionActivationException('Only archived revisions can be rolled back to.');
        }

        if (! $revision->channels()->exists()) {
            throw new TariffRevisionActivationException('Cannot activate an empty revision.');
        }

        $result = $this->switchActive($revision, $actor, markImportActivated: false);
        $this->formDataCache->forget();

        return $result;
    }

    private function switchActive(
        TariffRevision $target,
        User $actor,
        bool $markImportActivated,
    ): TariffRevision {
        return DB::transaction(function () use ($target, $actor, $markImportActivated): TariffRevision {
            $now = now();

            /** @var TariffRevision|null $currentActive */
            $currentActive = TariffRevision::query()
                ->where('status', RevisionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($currentActive !== null && $currentActive->id !== $target->id) {
                $currentActive->status = RevisionStatus::Archived;
                $currentActive->archived_at = $now;
                $currentActive->save();
            }

            $lockedTarget = TariffRevision::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTarget->status = RevisionStatus::Active;
            $lockedTarget->activated_by_user_id = $actor->id;
            $lockedTarget->activated_at = $now;
            $lockedTarget->archived_at = null;
            $lockedTarget->save();

            if ($markImportActivated && $lockedTarget->tariff_import_id !== null) {
                TariffImport::query()
                    ->whereKey($lockedTarget->tariff_import_id)
                    ->update([
                        'status' => ImportStatus::Activated->value,
                        'finished_at' => $now,
                    ]);
            }

            return $lockedTarget->refresh();
        });
    }
}
