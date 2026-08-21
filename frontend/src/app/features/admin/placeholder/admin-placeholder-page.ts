import { Component } from '@angular/core';
import { SiteHeader } from '../../../shared/components/site-header/site-header';

/** Заглушка защищённых admin-маршрутов до этапа 10. */
@Component({
  selector: 'app-admin-placeholder-page',
  imports: [SiteHeader],
  templateUrl: './admin-placeholder-page.html',
  styleUrl: './admin-placeholder-page.scss',
})
export class AdminPlaceholderPage {}
