<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ImportErrorDraft;
use App\Dto\TariffImport\ParsedChannelRow;
use App\Dto\TariffImport\WorkbookParseResult;
use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use App\Support\ChannelCode;
use App\Support\Decimal;

/**
 * Бизнес-валидация нормализованных строк импорта (раздел 8 ТЗ).
 */
final class TariffImportValidationService
{
    /**
     * @return list<ImportErrorDraft>
     */
    public function validate(WorkbookParseResult $parseResult): array
    {
        $errors = $parseResult->errors;
        $channels = $parseResult->channels;

        $errors = array_merge($errors, $this->validateDuplicates($channels));
        $errors = array_merge($errors, $this->validateChannelFields($channels));

        if (! $this->hasErrorCode($parseResult->errors, 'ozon_sheet_missing')) {
            $errors = array_merge($errors, $this->validateRequiredOzonChannels($channels));
        }

        if (! $this->hasErrorCode($parseResult->errors, 'yandex_section_missing')) {
            $errors = array_merge($errors, $this->validateRequiredYandexChannels($channels));
        }

        if ($channels === [] && ! $this->hasWorkbookLevelMissingSheet($errors)) {
            $errors[] = new ImportErrorDraft(
                message: 'Workbook: revision would be empty (no channels parsed).',
                field: 'workbook',
                errorCode: 'empty_revision',
            );
        }

        return $errors;
    }

    /**
     * @param list<ParsedChannelRow> $channels
     * @return list<ImportErrorDraft>
     */
    private function validateDuplicates(array $channels): array
    {
        $errors = [];
        $seen = [];

        foreach ($channels as $channel) {
            $key = $channel->platform->value . ':' . $channel->code;
            if (isset($seen[$key])) {
                $sheet = $channel->sheetName ?? 'unknown';
                $row = $channel->importSourceRow;
                $message = $row === null
                    ? "Sheet '{$sheet}': duplicate channel code '{$channel->code}'."
                    : "Sheet '{$sheet}', row {$row}: duplicate channel code '{$channel->code}'.";

                $errors[] = new ImportErrorDraft(
                    message: $message,
                    sheetName: $channel->sheetName,
                    rowNumber: $channel->importSourceRow,
                    field: 'code',
                    errorCode: 'duplicate_code',
                );
            }

            $seen[$key] = true;
        }

        return $errors;
    }

    /**
     * @param list<ParsedChannelRow> $channels
     * @return list<ImportErrorDraft>
     */
    private function validateChannelFields(array $channels): array
    {
        $errors = [];

        foreach ($channels as $channel) {
            $sheet = $channel->sheetName ?? 'unknown';
            $row = $channel->importSourceRow;

            if (trim($channel->name) === '') {
                $errors[] = $this->rowError($sheet, $row, 'name', 'channel name is empty.', 'empty_name');
            }

            if (Decimal::compare($channel->fixedFee, '0', 6) < 0) {
                $errors[] = $this->rowError($sheet, $row, 'fixed_fee', 'fixed fee cannot be negative.', 'fixed_fee_negative');
            }

            if ($channel->platform === Platform::Ozon) {
                $errors = array_merge($errors, $this->validateOzonChannel($channel, $sheet, $row));
            }

            if ($channel->platform === Platform::YandexMarket) {
                $errors = array_merge($errors, $this->validateYandexChannel($channel, $sheet, $row));
            }

            if (
                $channel->minWeightGrams !== null
                && $channel->maxWeightGrams !== null
                && Decimal::compare($channel->minWeightGrams, $channel->maxWeightGrams, 3) > 0
            ) {
                $errors[] = $this->rowError(
                    $sheet,
                    $row,
                    'min_weight_grams',
                    'min weight cannot exceed max weight.',
                    'weight_range_invalid',
                );
            }

            $errors = array_merge($errors, $this->validateOrderCostPair(
                $sheet,
                $row,
                $channel->minOrderCostCny,
                $channel->maxOrderCostCny,
                'min_order_cost_cny',
            ));
            $errors = array_merge($errors, $this->validateOrderCostPair(
                $sheet,
                $row,
                $channel->minOrderCostRub,
                $channel->maxOrderCostRub,
                'min_order_cost_rub',
            ));

            if ($channel->maxLengthCm !== null && Decimal::compare($channel->maxLengthCm, '0', 3) <= 0) {
                $errors[] = $this->rowError(
                    $sheet,
                    $row,
                    'max_length_cm',
                    'max length must be greater than 0 when set.',
                    'max_length_invalid',
                );
            }

            if (
                $channel->maxSumDimensionsCm !== null
                && Decimal::compare($channel->maxSumDimensionsCm, '0', 3) <= 0
            ) {
                $errors[] = $this->rowError(
                    $sheet,
                    $row,
                    'max_sum_dimensions_cm',
                    'max sum of dimensions must be greater than 0 when set.',
                    'max_sum_invalid',
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<ImportErrorDraft>
     */
    private function validateOzonChannel(ParsedChannelRow $channel, string $sheet, ?int $row): array
    {
        $errors = [];

        if (strtoupper($channel->currency) !== 'CNY') {
            $errors[] = $this->rowError(
                $sheet,
                $row,
                'currency',
                'currency must be CNY for Ozon.',
                'currency_mismatch',
            );
        }

        if ($channel->perGramFee === null || Decimal::compare($channel->perGramFee, '0', 8) <= 0) {
            $errors[] = $this->rowError(
                $sheet,
                $row,
                'per_gram_fee',
                'per-gram fee must be greater than 0.',
                'per_gram_invalid',
            );
        }

        $isBig = str_contains($channel->code, 'big');
        if ($isBig) {
            if ($channel->chargeableWeightType !== WeightCalculationType::MaxPhysicalOrVolumetric) {
                $errors[] = $this->rowError(
                    $sheet,
                    $row,
                    'chargeable_weight_type',
                    'Big tariff must use max_physical_or_volumetric.',
                    'weight_type_invalid',
                );
            }

            if (
                $channel->volumetricDivisor === null
                || Decimal::compare($channel->volumetricDivisor, '0', 4) <= 0
            ) {
                $errors[] = $this->rowError(
                    $sheet,
                    $row,
                    'volumetric_divisor',
                    'Big tariff requires a positive volumetric divisor.',
                    'divisor_missing',
                );
            }
        } elseif ($channel->chargeableWeightType !== WeightCalculationType::Physical) {
            $errors[] = $this->rowError(
                $sheet,
                $row,
                'chargeable_weight_type',
                'Non-Big Ozon tariff must use physical weight type.',
                'weight_type_invalid',
            );
        }

        return $errors;
    }

    /**
     * @return list<ImportErrorDraft>
     */
    private function validateYandexChannel(ParsedChannelRow $channel, string $sheet, ?int $row): array
    {
        $errors = [];

        if (strtoupper($channel->currency) !== 'RUB') {
            $errors[] = $this->rowError(
                $sheet,
                $row,
                'currency',
                'currency must be RUB for Yandex Market.',
                'currency_mismatch',
            );
        }

        if ($channel->perKgFee === null || Decimal::compare($channel->perKgFee, '0', 6) <= 0) {
            $message = $channel->name . ' tariff is missing a per-kg fee or it is not positive.';
            $errors[] = new ImportErrorDraft(
                message: $row === null
                    ? "Yandex Market section: {$message}"
                    : "Sheet '{$sheet}', row {$row}: {$message}",
                sheetName: $sheet,
                rowNumber: $row,
                field: 'per_kg_fee',
                errorCode: 'per_kg_invalid',
            );
        }

        return $errors;
    }

    /**
     * @param list<ParsedChannelRow> $channels
     * @return list<ImportErrorDraft>
     */
    private function validateRequiredOzonChannels(array $channels): array
    {
        $errors = [];
        $presentCodes = [];

        foreach ($channels as $channel) {
            if ($channel->platform === Platform::Ozon) {
                $presentCodes[$channel->code] = true;
            }
        }

        foreach (OzonTariffSheetParser::REQUIRED_CHANNEL_NAMES as $name) {
            $code = ChannelCode::fromName($name);
            if (! isset($presentCodes[$code])) {
                $errors[] = new ImportErrorDraft(
                    message: "Workbook: Ozon channel '{$name}' is missing.",
                    field: 'workbook',
                    errorCode: 'ozon_channel_missing',
                );
            }
        }

        return $errors;
    }

    /**
     * @param list<ParsedChannelRow> $channels
     * @return list<ImportErrorDraft>
     */
    private function validateRequiredYandexChannels(array $channels): array
    {
        // Дубли с парсером не создаём, если парсер уже добавил missing: сверяем только коды.
        $present = [];
        foreach ($channels as $channel) {
            if ($channel->platform === Platform::YandexMarket) {
                $present[$channel->code] = true;
            }
        }

        $errors = [];
        foreach (YandexTariffSheetParser::REQUIRED_CODES as $code) {
            if (! isset($present[$code])) {
                $label = $code === 'super-express' ? 'Super Express' : 'Express';
                // Если парсер уже сообщил об отсутствии, ValidationService не дублирует.
                // Здесь ошибка нужна, когда секция нашлась, но канал отфильтрован валидацией полей.
                $errors[] = new ImportErrorDraft(
                    message: "Yandex Market section: {$label} tariff is missing.",
                    field: 'workbook',
                    errorCode: 'yandex_channel_missing',
                );
            }
        }

        return $errors;
    }

    /**
     * @param numeric-string|null $min
     * @param numeric-string|null $max
     * @return list<ImportErrorDraft>
     */
    private function validateOrderCostPair(
        string $sheet,
        ?int $row,
        ?string $min,
        ?string $max,
        string $field,
    ): array {
        if ($min === null || $max === null) {
            return [];
        }

        if (Decimal::compare($min, $max, 4) > 0) {
            return [
                $this->rowError(
                    $sheet,
                    $row,
                    $field,
                    'min order cost cannot exceed max order cost.',
                    'order_cost_range_invalid',
                ),
            ];
        }

        return [];
    }

    /**
     * @param list<ImportErrorDraft> $errors
     */
    private function hasWorkbookLevelMissingSheet(array $errors): bool
    {
        return $this->hasErrorCode($errors, 'ozon_sheet_missing')
            || $this->hasErrorCode($errors, 'yandex_section_missing');
    }

    /**
     * @param list<ImportErrorDraft> $errors
     */
    private function hasErrorCode(array $errors, string $errorCode): bool
    {
        foreach ($errors as $error) {
            if ($error->errorCode === $errorCode) {
                return true;
            }
        }

        return false;
    }

    private function rowError(
        string $sheet,
        ?int $row,
        string $field,
        string $detail,
        string $errorCode,
    ): ImportErrorDraft {
        $message = $row === null
            ? "Sheet '{$sheet}': {$detail}"
            : "Sheet '{$sheet}', row {$row}: {$detail}";

        return new ImportErrorDraft(
            message: $message,
            sheetName: $sheet,
            rowNumber: $row,
            field: $field,
            errorCode: $errorCode,
        );
    }
}
