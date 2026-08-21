import { Component } from '@angular/core';
import { SiteHeader } from '../../../shared/components/site-header/site-header';

/** Заглушка login до этапа 10. */
@Component({
  selector: 'app-admin-login-page',
  imports: [SiteHeader],
  templateUrl: './admin-login-page.html',
  styleUrl: './admin-login-page.scss',
})
export class AdminLoginPage {}
