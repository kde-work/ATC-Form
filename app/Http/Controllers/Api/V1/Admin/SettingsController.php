<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateExchangeRateRequest;
use App\Http\Resources\Api\V1\Admin\AdminSettingsResource;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\ActiveTariffQuery;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Http\Request;

/**
 * Admin settings: чтение и смена курса RUB → CNY.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    public function show(Request $request): AdminSettingsResource
    {
        $this->authorize('viewAny', AppSetting::class);

        $setting = $this->activeTariffQuery->exchangeRateSetting();
        $setting->load('updatedBy');

        return new AdminSettingsResource($setting);
    }

    public function updateExchangeRate(UpdateExchangeRateRequest $request): AdminSettingsResource
    {
        $setting = $this->activeTariffQuery->exchangeRateSetting();
        $this->authorize('update', $setting);

        /** @var User $user */
        $user = $request->user();

        $setting->value = $request->rate();
        $setting->updated_by_user_id = $user->id;
        $setting->save();
        $setting->load('updatedBy');
        $this->formDataCache->forget();

        return new AdminSettingsResource($setting);
    }
}
