import { Component, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { LocaleService } from '../../../core/i18n/locale.service';

@Component({
  selector: 'app-site-header',
  imports: [RouterLink],
  templateUrl: './site-header.html',
  styleUrl: './site-header.scss',
})
export class SiteHeader {
  /** Подзаголовок рядом с логотипом (админка и т.п.). */
  readonly title = input('Delivery cost calculator');

  /** Показать переключатель языка (калькулятор). */
  readonly showLocaleSwitch = input(false);

  protected readonly locale = inject(LocaleService);
}
