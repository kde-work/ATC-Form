<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\TariffRevisionActivationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ImportIndexRequest;
use App\Http\Requests\Api\V1\Admin\StoreImportRequest;
use App\Http\Resources\Api\V1\Admin\TariffImportDetailResource;
use App\Http\Resources\Api\V1\Admin\TariffImportListResource;
use App\Models\TariffImport;
use App\Models\User;
use App\Services\TariffImport\TariffImportService;
use App\Services\TariffImport\TariffRevisionActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin API импортов: список, upload, детали, activate, rollback.
 */
final class ImportController extends Controller
{
    public function __construct(
        private readonly TariffImportService $importService,
        private readonly TariffRevisionActivationService $activationService,
    ) {
    }

    public function index(ImportIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TariffImport::class);

        $query = TariffImport::query()
            ->with(['uploadedBy', 'revision', 'errors'])
            ->orderByDesc('id');

        $status = $request->status();
        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($request->perPage());

        return TariffImportListResource::collection($paginator);
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $this->authorize('create', TariffImport::class);

        /** @var User $user */
        $user = $request->user();

        $import = $this->importService->upload($request->file('file'), $user);
        $import->refresh();
        $import->load(['uploadedBy', 'revision.channels', 'revision.activatedBy', 'errors']);

        return (new TariffImportDetailResource($import))
            ->response()
            ->setStatusCode(201);
    }

    public function show(TariffImport $import): TariffImportDetailResource
    {
        $this->authorize('view', $import);

        $import->load(['uploadedBy', 'revision.channels', 'revision.activatedBy', 'errors']);

        return new TariffImportDetailResource($import);
    }

    public function activate(Request $request, TariffImport $import): TariffImportDetailResource
    {
        $this->authorize('activate', $import);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->activationService->activate($import, $user);
        } catch (TariffRevisionActivationException $exception) {
            throw $exception;
        }

        $import->refresh();
        $import->load(['uploadedBy', 'revision.channels', 'revision.activatedBy', 'errors']);

        return new TariffImportDetailResource($import);
    }

    public function rollback(Request $request, TariffImport $import): TariffImportDetailResource
    {
        $this->authorize('rollback', $import);

        /** @var User $user */
        $user = $request->user();

        $revision = $import->revision;
        if ($revision === null) {
            throw new TariffRevisionActivationException('Import has no revision to roll back to.');
        }

        try {
            $this->activationService->rollback($revision, $user);
        } catch (TariffRevisionActivationException $exception) {
            throw $exception;
        }

        $import->refresh();
        $import->load(['uploadedBy', 'revision.channels', 'revision.activatedBy', 'errors']);

        return new TariffImportDetailResource($import);
    }
}
