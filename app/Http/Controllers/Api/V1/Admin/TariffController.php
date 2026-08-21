<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TariffIndexRequest;
use App\Http\Resources\Api\V1\Admin\AdminTariffResource;
use App\Models\DeliveryChannel;
use App\Models\TariffRevision;
use App\Services\ActiveTariffQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only список тарифов с фильтром platform и revision.
 */
final class TariffController extends Controller
{
    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
    ) {
    }

    public function index(TariffIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryChannel::class);

        $revisionId = $request->revisionId();
        if ($revisionId !== null) {
            $revision = TariffRevision::query()->findOrFail($revisionId);
        } else {
            $revision = $this->activeTariffQuery->activeRevision();
        }

        if ($revision === null) {
            return AdminTariffResource::collection(collect());
        }

        /** @var Builder<DeliveryChannel> $query */
        $query = DeliveryChannel::query()
            ->where('tariff_revision_id', $revision->id)
            ->orderBy('platform')
            ->orderBy('name');

        $platform = $request->platform();
        if ($platform !== null) {
            $query->where('platform', $platform);
        }

        return AdminTariffResource::collection($query->get());
    }
}
