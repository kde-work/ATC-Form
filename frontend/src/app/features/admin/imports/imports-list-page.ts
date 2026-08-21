import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import {
  ApiClientError,
  ImportStatus,
  TariffImportListItemDto,
} from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import {
  formatIsoDate,
  importStatusLabel,
} from '../../../core/utils/admin-display';

const STATUS_OPTIONS: Array<{ value: ImportStatus | ''; label: string }> = [
  { value: '', label: 'All statuses' },
  { value: 'uploaded', label: 'Uploaded' },
  { value: 'processing', label: 'Processing' },
  { value: 'validated', label: 'Validated' },
  { value: 'validation_failed', label: 'Validation failed' },
  { value: 'activated', label: 'Activated' },
  { value: 'failed', label: 'Failed' },
];

@Component({
  selector: 'app-imports-list-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './imports-list-page.html',
  styleUrl: './imports-list-page.scss',
})
export class ImportsListPage implements OnInit {
  private readonly api = inject(AdminApiService);

  readonly statusOptions = STATUS_OPTIONS;
  readonly items = signal<TariffImportListItemDto[]>([]);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly statusFilter = signal<ImportStatus | ''>('');
  readonly page = signal(1);
  readonly lastPage = signal(1);
  readonly total = signal(0);

  readonly formatIsoDate = formatIsoDate;
  readonly importStatusLabel = importStatusLabel;

  ngOnInit(): void {
    this.load();
  }

  onStatusChange(value: string): void {
    this.statusFilter.set((value || '') as ImportStatus | '');
    this.page.set(1);
    this.load();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) {
      return;
    }
    this.page.set(page);
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .getImports({
        status: this.statusFilter(),
        page: this.page(),
        perPage: 15,
      })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (response) => {
          this.items.set(response.data);
          this.page.set(response.meta.current_page);
          this.lastPage.set(response.meta.last_page);
          this.total.set(response.meta.total);
        },
        error: (err: unknown) => {
          this.items.set([]);
          if (err instanceof ApiClientError) {
            this.error.set(err.message);
            return;
          }
          this.error.set('Failed to load imports.');
        },
      });
  }
}
