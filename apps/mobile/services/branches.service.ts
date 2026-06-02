import { apiClient } from './api';
import type { Sucursal } from '@amare/types';

export async function getBranches(): Promise<Sucursal[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Sucursal[] }>('/branches');
  return data.data;
}

export async function getNearestBranches(lat: number, lng: number): Promise<Sucursal[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Sucursal[] }>('/branches/nearest', {
    params: { lat, lng },
  });
  return data.data;
}

export async function getBranchById(id: number): Promise<Sucursal> {
  const { data } = await apiClient.get<{ ok: boolean; data: Sucursal }>(`/branches/${id}`);
  return data.data;
}
