import { apiClient, formatImageUrl } from './api';
import type { Sucursal, TipoPedido } from '@amare/types';

export async function getBranches(): Promise<Sucursal[]> {
  const { data } = await apiClient.get<{ success: boolean; data: { branches: Sucursal[] } }>('/branches');
  return data.data.branches.map(normalizeBranch);
}

export async function getBranchById(id: number): Promise<Sucursal> {
  const { data } = await apiClient.get<{ success: boolean; data: { branch: Sucursal } }>(`/branches/${id}`);
  return normalizeBranch(data.data.branch);
}

export async function getNearestBranches(
  lat: number,
  lng: number,
  tipoPedido?: TipoPedido
): Promise<Sucursal[]> {
  const { data } = await apiClient.get<{ success: boolean; data: { branches: Sucursal[] } }>(
    '/branches/nearest',
    { params: { lat, lng, tipo_pedido: tipoPedido } }
  );
  return data.data.branches.map(normalizeBranch);
}

/** Normaliza la forma de la sucursal para que coincida con el tipo compartido */
export function normalizeBranch(branch: Partial<Sucursal>): Sucursal {
  return {
    id: Number(branch.id ?? 0),
    nombre: branch.nombre ?? 'Sucursal',
    slug: branch.slug ?? '',
    descripcion: branch.descripcion ?? null,
    direccion: branch.direccion ?? branch.descripcion ?? '',
    telefono: branch.telefono ?? null,
    logo: branch.logo ?? null,
    imagen_banner: formatImageUrl(branch.imagen_banner) ?? branch.imagen_banner ?? null,
    color_primario: branch.color_primario ?? '#111827',
    color_secundario: branch.color_secundario ?? '#FFFFFF',
    horario_apertura: branch.horario_apertura ?? null,
    horario_cierre: branch.horario_cierre ?? null,
    horarios_json: branch.horarios_json ?? null,
    lat: branch.lat != null ? Number(branch.lat) : null,
    lng: branch.lng != null ? Number(branch.lng) : null,
    mesas_habilitadas: Boolean(branch.mesas_habilitadas),
    reservas_habilitadas: Boolean(branch.reservas_habilitadas),
    activo: Boolean(branch.activo),
    tipos_entrega: branch.tipos_entrega ?? ['delivery', 'pickup'],
    distancia_km: branch.distancia_km,
  };
}
