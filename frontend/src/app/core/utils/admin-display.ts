import { ImportStatus } from '../models/api.models';

const IMPORT_STATUS_LABELS: Record<ImportStatus, string> = {
  uploaded: 'Uploaded',
  processing: 'Processing',
  validation_failed: 'Validation failed',
  validated: 'Validated',
  activated: 'Activated',
  failed: 'Failed',
};

export function importStatusLabel(status: ImportStatus | string): string {
  if (status in IMPORT_STATUS_LABELS) {
    return IMPORT_STATUS_LABELS[status as ImportStatus];
  }

  return status;
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  const kb = bytes / 1024;
  if (kb < 1024) {
    return `${kb.toFixed(kb < 10 ? 1 : 0)} KB`;
  }

  const mb = kb / 1024;
  return `${mb.toFixed(mb < 10 ? 2 : 1)} MB`;
}

export function formatIsoDate(value: string | null | undefined): string {
  if (!value) {
    return '-';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('en-GB', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}
