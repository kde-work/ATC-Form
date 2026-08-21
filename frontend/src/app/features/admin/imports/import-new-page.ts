import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { ApiClientError } from '../../../core/models/api.models';
import { AdminApiService } from '../../../core/services/admin-api.service';
import { formatBytes } from '../../../core/utils/admin-display';

@Component({
  selector: 'app-import-new-page',
  imports: [RouterLink],
  templateUrl: './import-new-page.html',
  styleUrl: './import-new-page.scss',
})
export class ImportNewPage {
  private readonly api = inject(AdminApiService);
  private readonly router = inject(Router);

  readonly maxBytes = environment.tariffImportMaxBytes;
  readonly maxBytesLabel = formatBytes(this.maxBytes);
  readonly selectedFile = signal<File | null>(null);
  readonly dragOver = signal(false);
  readonly uploading = signal(false);
  readonly error = signal<string | null>(null);

  onFileInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    this.applyFile(file);
    input.value = '';
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(true);
  }

  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
    const file = event.dataTransfer?.files?.[0] ?? null;
    this.applyFile(file);
  }

  clearFile(): void {
    this.selectedFile.set(null);
    this.error.set(null);
  }

  upload(): void {
    const file = this.selectedFile();
    if (!file || this.uploading()) {
      return;
    }

    const clientError = this.validateFile(file);
    if (clientError) {
      this.error.set(clientError);
      return;
    }

    this.uploading.set(true);
    this.error.set(null);

    this.api
      .uploadImport(file)
      .pipe(finalize(() => this.uploading.set(false)))
      .subscribe({
        next: (detail) => void this.router.navigateByUrl(`/admin/imports/${detail.id}`),
        error: (err: unknown) => {
          if (err instanceof ApiClientError) {
            this.error.set(err.flatMessages().join(' '));
            return;
          }
          this.error.set('Upload failed. Please try again.');
        },
      });
  }

  private applyFile(file: File | null): void {
    this.error.set(null);
    if (!file) {
      this.selectedFile.set(null);
      return;
    }

    const clientError = this.validateFile(file);
    if (clientError) {
      this.selectedFile.set(null);
      this.error.set(clientError);
      return;
    }

    this.selectedFile.set(file);
  }

  private validateFile(file: File): string | null {
    const name = file.name.toLowerCase();
    if (!name.endsWith('.xlsx')) {
      return 'Only .xlsx files are allowed.';
    }

    if (file.size > this.maxBytes) {
      return `The file exceeds the maximum allowed size of ${this.maxBytesLabel}.`;
    }

    if (file.size === 0) {
      return 'The selected file is empty.';
    }

    return null;
  }
}
