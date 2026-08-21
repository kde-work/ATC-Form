<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Exceptions\TariffImportFileException;
use App\Models\TariffImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Сохранение XLSX на private disk без публикации в public/.
 */
final class TariffImportStorage
{
    public const DISK = 'local';

    public const DIRECTORY = 'tariff-imports';

    /**
     * Проверяет расширение и размер, сохраняет файл, возвращает метаданные.
     *
     * @return array{stored_path: string, file_hash: string, file_size_bytes: int, original_filename: string}
     */
    public function store(UploadedFile|File $file, ?string $originalFilename = null): array
    {
        $original = $originalFilename ?? $this->resolveOriginalFilename($file);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($extension !== 'xlsx') {
            throw new TariffImportFileException('Only .xlsx files are allowed.');
        }

        $size = $file->getSize();
        if ($size === false || $size <= 0) {
            throw new TariffImportFileException('Uploaded file is empty or unreadable.');
        }

        $maxBytes = (int) config('atc.tariff_import_max_bytes', 5_242_880);
        if ($size > $maxBytes) {
            throw new TariffImportFileException(sprintf(
                'File size exceeds the limit of %d bytes.',
                $maxBytes,
            ));
        }

        $absolutePath = $file instanceof UploadedFile
            ? $file->getRealPath()
            : $file->getPathname();

        if ($absolutePath === false || $absolutePath === '' || ! is_readable($absolutePath)) {
            throw new TariffImportFileException('Uploaded file is not readable.');
        }

        $hash = hash_file('sha256', $absolutePath);
        if ($hash === false) {
            throw new TariffImportFileException('Failed to compute file hash.');
        }

        $storedPath = self::DIRECTORY . '/' . Str::uuid()->toString() . '.xlsx';
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new TariffImportFileException('Failed to open uploaded file stream.');
        }

        try {
            $written = Storage::disk(self::DISK)->put($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $written) {
            throw new TariffImportFileException('Failed to store import file on private disk.');
        }

        return [
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'file_size_bytes' => $size,
            'original_filename' => $original,
        ];
    }

    /**
     * Абсолютный путь к сохранённому файлу импорта.
     */
    public function absolutePath(TariffImport $import): string
    {
        $path = Storage::disk(self::DISK)->path($import->stored_path);
        if (! is_file($path)) {
            throw new TariffImportFileException('Stored import file is missing.');
        }

        return $path;
    }

    private function resolveOriginalFilename(UploadedFile|File $file): string
    {
        if ($file instanceof UploadedFile) {
            return (string) $file->getClientOriginalName();
        }

        return $file->getFilename();
    }
}
