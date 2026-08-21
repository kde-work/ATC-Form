import { Component, input, output } from '@angular/core';

/** Простой confirm-диалог для activate / rollback / settings. */
@Component({
  selector: 'app-confirm-dialog',
  templateUrl: './confirm-dialog.html',
  styleUrl: './confirm-dialog.scss',
})
export class ConfirmDialog {
  readonly open = input(false);
  readonly title = input('Confirm');
  readonly message = input('');
  readonly confirmLabel = input('Confirm');
  readonly cancelLabel = input('Cancel');
  readonly busy = input(false);

  readonly confirmed = output<void>();
  readonly cancelled = output<void>();

  onConfirm(): void {
    if (this.busy()) {
      return;
    }
    this.confirmed.emit();
  }

  onCancel(): void {
    if (this.busy()) {
      return;
    }
    this.cancelled.emit();
  }

  onBackdropClick(): void {
    this.onCancel();
  }
}
