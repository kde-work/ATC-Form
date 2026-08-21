import { Component, inject, OnInit, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { finalize } from 'rxjs';
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
  private readonly router = inject(Router);

  readonly userEmail = signal<string | null>(null);
  readonly loggingOut = signal(false);

  ngOnInit(): void {
    const cached = this.auth.user();
    if (cached) {
      this.userEmail.set(cached.email);
      return;
    }

    this.auth.me().subscribe({
      next: (user) => this.userEmail.set(user.email),
      error: () => this.userEmail.set(null),
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
