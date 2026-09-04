import { Component, computed, inject, input } from '@angular/core';
import { LocaleService } from '../../core/i18n/locale.service';
import { CalculationResultDto } from '../../core/models/api.models';
import { formatDecimalString, formatMoney } from '../../core/utils/money-display';

@Component({
  selector: 'app-result-card',
  templateUrl: './result-card.html',
  styleUrl: './result-card.scss',
})
export class ResultCard {
  readonly result = input.required<CalculationResultDto>();

  protected readonly locale = inject(LocaleService);

  readonly isOzon = computed(() => this.result().platform.code === 'ozon');
  readonly isYandex = computed(() => this.result().platform.code === 'yandex_market');

  protected formatDecimal = formatDecimalString;
  protected formatMoney = formatMoney;
}
