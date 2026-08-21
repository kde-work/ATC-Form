import { Component, computed, input } from '@angular/core';
import { CalculationResultDto } from '../../core/models/api.models';
import { formatDecimalString, formatMoney } from '../../core/utils/money-display';

@Component({
  selector: 'app-result-card',
  templateUrl: './result-card.html',
  styleUrl: './result-card.scss',
})
export class ResultCard {
  readonly result = input.required<CalculationResultDto>();

  readonly isOzon = computed(() => this.result().platform.code === 'ozon');
  readonly isYandex = computed(() => this.result().platform.code === 'yandex_market');

  protected formatDecimal = formatDecimalString;
  protected formatMoney = formatMoney;
}
