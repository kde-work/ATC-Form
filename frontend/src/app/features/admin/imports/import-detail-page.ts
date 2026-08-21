import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import {
  ApiClientError,
  TariffImportDetailDto,
} from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import {
  formatBytes,
  formatIsoDate,
  importStatusLabel,
} from '../../../core/utils/admin-display';
import { formatDecimalString } from '../../../core/utils/money-display';
import { ConfirmDialog } from '../../../shared/components/confirm-dialog/confirm-dialog';

type DetailTab = 'summary' | 'parsed' | 'changes' | 'errors';

@Component({
  selector: 'app-import-detail-page',
  imports: [RouterLink, ConfirmDialog],
  templateUrl: './import-detail-page.html',
  styleUrl: './import-detail-page.scss',
})
export class ImportDetailPage implements OnInit {
  private readonly api = inject(AdminApiService);
  private readonly route = inject(ActivatedRoute);

  readonly detail = signal<TariffImportDetailDto | null>(null);
  readonly loading = signal(false);
  readonly actionBusy = signal(false);
  readonly error = signal<string | null>(null);
  readonly actionError = signal<string | null>(null);
  readonly tab = signal<DetailTab>('summary');

  readonly confirmOpen = signal(false);
  readonly confirmMode = signal<'activate' | 'rollback' | null>(null);

  readonly formatIsoDate = formatIsoDate;
  readonly formatBytes = formatBytes;
  readonly importStatusLabel = importStatusLabel;
  readonly formatDecimalString = formatDecimalString;

  readonly confirmTitle = computed(() =>
    this.confirmMode() === 'rollback' ? 'Confirm rollback' : 'Confirm activation',
  );

  readonly confirmMessage = computed(() => {
    if (this.confirmMode() === 'rollback') {
      return 'Roll back to this archived revision? The current active revision will be archived.';
    }
    return 'Activate this draft revision? The current active revision will be archived.';
  });

  readonly confirmLabel = computed(() =>
    this.confirmMode() === 'rollback' ? 'Rollback' : 'Activate',
  );

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!Number.isInteger(id) || id < 1) {
      this.error.set('Invalid import id.');
      return;
    }
    this.load(id);
  }

  setTab(tab: DetailTab): void {
    this.tab.set(tab);
  }

  openActivateConfirm(): void {
    this.confirmMode.set('activate');
    this.confirmOpen.set(true);
  }

  openRollbackConfirm(): void {
    this.confirmMode.set('rollback');
    this.confirmOpen.set(true);
  }

  closeConfirm(): void {
    if (this.actionBusy()) {
      return;
    }
    this.confirmOpen.set(false);
    this.confirmMode.set(null);
  }

  onConfirmAction(): void {
    const detail = this.detail();
    const mode = this.confirmMode();
    if (!detail || !mode || this.actionBusy()) {
      return;
    }

    this.actionBusy.set(true);
    this.actionError.set(null);

    const request =
      mode === 'activate'
        ? this.api.activateImport(detail.id)
        : this.api.rollbackImport(detail.id);

    request.pipe(finalize(() => this.actionBusy.set(false))).subscribe({
      next: (updated) => {
        this.detail.set(updated);
        this.confirmOpen.set(false);
        this.confirmMode.set(null);
      },
      error: (err: unknown) => {
        if (err instanceof ApiClientError) {
          this.actionError.set(err.message);
        } else {
          this.actionError.set('Action failed.');
        }
        this.confirmOpen.set(false);
        this.confirmMode.set(null);
      },
    });
  }

  platformEntries(byPlatform: Record<string, number>): Array<{ key: string; value: number }> {
    return Object.entries(byPlatform).map(([key, value]) => ({ key, value }));
  }

  private load(id: number): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .getImport(id)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (detail) => this.detail.set(detail),
        error: (err: unknown) => {
          this.detail.set(null);
          if (err instanceof ApiClientError) {
            this.error.set(err.message);
            return;
          }
          this.error.set('Failed to load import details.');
        },
      });
  }
}
