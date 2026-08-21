<?php

declare(strict_types=1);

namespace App\Services\Calculation;

use App\Dto\Calculation\CalculationInput;
use App\Dto\Calculation\PricingResult;
use App\Services\ActiveTariffQuery;
use Illuminate\Validation\ValidationException;

/**
 * Оркестрация публичного расчёта: канал из active-ревизии + курс + PricingService.
 */
final class PublicCalculationService
{
    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
        private readonly PricingService $pricingService,
    ) {
    }

    public function calculate(CalculationInput $input): PricingResult
    {
        $channel = $this->activeTariffQuery->findActiveChannel(
            $input->platform,
            $input->deliveryChannelCode,
        );

        if ($channel === null) {
            throw ValidationException::withMessages([
                'delivery_channel_code' => [
                    'The selected delivery channel is not available for the active tariff revision.',
                ],
            ]);
        }

        $rate = $this->activeTariffQuery->rubToCnyRate();

        return $this->pricingService->calculate($channel, $input, $rate);
    }
}
