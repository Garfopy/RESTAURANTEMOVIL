import { apiClient, formatImageUrl } from './api';
import type { Sucursal } from '@amare/types';

export async function getBranches(): Promise<Sucursal[]> {
  const { data } = await apiClient.get<{ success: boolean; data: { branches: Sucursal[] } }>('/branches');
  return data.data.branches.map(formatImages);
}

export async function getBranchById(id: number): Promise<Sucursal> {
  const { data } = await apiClient.get<{ success: boolean; data: { branch: Sucursal } }>(`/branches/${id}`);
  return formatImages(data.data.branch);
}

/** Normaliza URLs de imágenes de las sucursales */
function formatImages(branch: Sucursal): Sucursal {
  return {
    ...branch,
    imagen_banner: formatImageUrl(branch.imagen_banner) ?? branch.imagen_banner,
    lat: branch.lat ? Number(branch.lat) : null,
    lng: branch.lng ? Number(branch.lng) : null,
  };
}

/**
 * ⚠️ La PHP API no tiene endpoint de sucursales cercanas.
 * Se obtienen todas las sucursales y se filtran localmente.
 */
export async function getNearestBranches(lat: number, lng: number): Promise<Sucursal[]> {
  const branches = await getBranches();
  return branches
    .filter((b) => b.lat != null && b.lng != null)
    .sort((a, b) => {
      const distA = haversine(lat, lng, a.lat!, a.lng!);
      const distB = haversine(lat, lng, b.lat!, b.lng!);
      return distA - distB;
    });
}

/** Fórmula de Haversine para distancia entre coordenadas (km) */
function haversine(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371;
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLng = ((lng2 - lng1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}