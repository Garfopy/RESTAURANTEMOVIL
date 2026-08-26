import type { Sucursal } from '@amare/types';

export interface BranchOpenStatus {
  isOpen: boolean;
  opensAtLabel: string | null;
  closesAtLabel: string | null;
}

function parseMinutes(time: string | null): number | null {
  if (!time) return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(time.trim());
  if (!match) return null;
  const hours = Number(match[1]);
  const minutes = Number(match[2]);
  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return null;
  return hours * 60 + minutes;
}

function formatLabel(time: string | null): string | null {
  const minutes = parseMinutes(time);
  if (minutes === null) return null;
  const date = new Date();
  date.setHours(Math.floor(minutes / 60), minutes % 60, 0, 0);
  return date.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });
}

/**
 * Calcula si la sucursal está abierta ahora según horario_apertura/horario_cierre.
 * Si no hay horario configurado, se considera siempre abierta (sin restricción).
 * Soporta horarios que cruzan medianoche (ej. abre 08:00, cierra 02:00).
 */
export function getBranchOpenStatus(branch: Pick<Sucursal, 'horario_apertura' | 'horario_cierre'> | null | undefined): BranchOpenStatus {
  const opensAt = parseMinutes(branch?.horario_apertura ?? null);
  const closesAt = parseMinutes(branch?.horario_cierre ?? null);

  if (opensAt === null || closesAt === null || opensAt === closesAt) {
    return { isOpen: true, opensAtLabel: null, closesAtLabel: null };
  }

  const now = new Date();
  const nowMinutes = now.getHours() * 60 + now.getMinutes();

  const isOpen = opensAt < closesAt
    ? nowMinutes >= opensAt && nowMinutes < closesAt
    : nowMinutes >= opensAt || nowMinutes < closesAt; // horario que cruza medianoche

  return {
    isOpen,
    opensAtLabel: formatLabel(branch?.horario_apertura ?? null),
    closesAtLabel: formatLabel(branch?.horario_cierre ?? null),
  };
}
