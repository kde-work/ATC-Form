<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\TariffImportFileException;
use App\Exceptions\TariffRevisionActivationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ImportIndexRequest;
use App\Http\Requests\Api\V1\Admin\StoreImportRequest;
use App\Http\Resources\Api\V1\Admin\TariffImportDetailResource;
use App\Http\Resources\Api\V1\Admin\TariffImportListResource;
use App\Models\TariffImport;
use App\Models\User;
use App\Services\TariffImport\TariffImportService;
use App\Services\TariffImport\TariffImportStorage;
use App\Services\TariffImport\TariffRevisionActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin API импортов: список, upload, детали, скачивание файла, activate, rollback.
 */
final class ImportController extends Controller
{
    public function __construct(
        private readonly TariffImportService $importService,
        private readonly TariffRevisionActivationService $activationService,
        private readonly TariffImportStorage $importStorage,
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

    /**
     * Отдаёт исходный XLSX с private disk под оригинальным именем файла.
     */
    public function download(TariffImport $import): BinaryFileResponse
    {
        $this->authorize('view', $import);

        try {
            $absolutePath = $this->importStorage->absolutePath($import);
        } catch (TariffImportFileException $exception) {
            throw new NotFoundHttpException(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Stored import file is missing.',
            );
        }

        return response()->download(
            $absolutePath,
            $import->original_filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
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
