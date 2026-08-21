import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize, forkJoin } from 'rxjs';
import {
  AdminTariffDto,
  ApiClientError,
  PlatformCode,
  TariffImportListItemDto,
} from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import { formatDecimalString } from '../../../core/utils/money-display';

interface RevisionOption {
  id: number | null;
  label: string;
}

@Component({
  selector: 'app-tariffs-page',
  imports: [FormsModule],
  templateUrl: './tariffs-page.html',
  styleUrl: './tariffs-page.scss',
})
export class TariffsPage implements OnInit {
  private readonly api = inject(AdminApiService);

  readonly tariffs = signal<AdminTariffDto[]>([]);
  readonly revisionOptions = signal<RevisionOption[]>([
    { id: null, label: 'Active revision (default)' },
  ]);
  readonly platformFilter = signal<PlatformCode | ''>('');
  readonly revisionFilter = signal<number | null>(null);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);

  readonly formatDecimalString = formatDecimalString;

  revisionValue(id: number | null): string {
    return id === null ? '' : `${id}`;
  }

  ngOnInit(): void {
    this.bootstrap();
  }

  onPlatformChange(value: string): void {
    this.platformFilter.set((value || '') as PlatformCode | '');
    this.loadTariffs();
  }

  onRevisionChange(value: string): void {
    this.revisionFilter.set(value === '' ? null : Number(value));
    this.loadTariffs();
  }

  private bootstrap(): void {
    this.loading.set(true);
    this.error.set(null);

    forkJoin({
      imports: this.api.getImports({ perPage: 100 }),
      tariffs: this.api.getTariffs(),
    })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: ({ imports, tariffs }) => {
          this.revisionOptions.set(this.buildRevisionOptions(imports.data));
          this.tariffs.set(tariffs);
        },
        error: (err: unknown) => {
          if (err instanceof ApiClientError) {
            this.error.set(err.message);
            return;
          }
          this.error.set('Failed to load tariffs.');
        },
      });
  }

  private loadTariffs(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .getTariffs({
        platform: this.platformFilter(),
        revisionId: this.revisionFilter(),
      })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (tariffs) => this.tariffs.set(tariffs),
        error: (err: unknown) => {
          this.tariffs.set([]);
          if (err instanceof ApiClientError) {
            this.error.set(err.message);
            return;
          }
          this.error.set('Failed to load tariffs.');
        },
      });
  }

  private buildRevisionOptions(items: TariffImportListItemDto[]): RevisionOption[] {
    const options: RevisionOption[] = [{ id: null, label: 'Active revision (default)' }];
    const seen = new Set<number>();

    for (const item of items) {
      const revision = item.revision;
      if (!revision || seen.has(revision.id)) {
        continue;
      }
      seen.add(revision.id);
      const activeMark = revision.is_active ? ' · active' : '';
      options.push({
        id: revision.id,
        label: `v${revision.version_number} (#${revision.id}, ${revision.status}${activeMark})`,
      });
    }

    return options;
  }
}
