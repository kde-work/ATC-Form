import { Component, inject, OnInit, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { finalize, forkJoin } from 'rxjs';
import { AdminApiService } from '../../../core/services/admin-api.service';
import { AdminAuthService } from '../../../core/services/admin-auth.service';

/** Каркас админки: логотип, навигация, logout, outlet. */
@Component({
  selector: 'app-admin-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './admin-shell.html',
  styleUrl: './admin-shell.scss',
})
export class AdminShell implements OnInit {
  private readonly auth = inject(AdminAuthService);
  private readonly api = inject(AdminApiService);
  private readonly router = inject(Router);

  readonly userEmail = signal<string | null>(null);
  readonly loggingOut = signal(false);

  ngOnInit(): void {
    const cached = this.auth.user();
    if (cached) {
      this.userEmail.set(cached.email);
    }

    // Прогрев кэша admin GET при входе в shell (параллельно с дочерним роутом).
    forkJoin({
      me: this.auth.me(),
      warmup: this.api.prefetchWarmup(),
    }).subscribe({
      next: ({ me }) => this.userEmail.set(me.email),
      error: () => {
        if (!this.userEmail()) {
          this.userEmail.set(null);
        }
      },
    });
  }

  logout(): void {
    if (this.loggingOut()) {
      return;
    }

    this.loggingOut.set(true);
    this.auth
      .logout()
      .pipe(finalize(() => this.loggingOut.set(false)))
      .subscribe({
        next: () => void this.router.navigateByUrl('/admin/login'),
        error: () => void this.router.navigateByUrl('/admin/login'),
      });
  }
}
