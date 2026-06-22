import { apiClient } from './api';
import type { RestaurantConfig } from '@amare/types';

export type RestaurantConfigFetchResult = {
  config: RestaurantConfig | null;
  etag: string | null;
  notModified: boolean;
};

/**
 * Obtiene la configuración de métodos de pago y tipos de entrega de un restaurante.
 */
export async function fetchRestaurantConfig(restauranteId: number, etag?: string | null): Promise<RestaurantConfigFetchResult> {
  const response = await apiClient.get<{
    success: boolean;
    data: { config: RestaurantConfig };
  }>(`/branches/${restauranteId}/config`, {
    params: { _ts: Date.now() },
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      Pragma: 'no-cache',
      ...(etag ? { 'If-None-Match': etag } : {}),
    },
    validateStatus: (status) => (status >= 200 && status < 300) || status === 304,
  });

  if (response.status === 304) {
    return { config: null, etag: etag ?? null, notModified: true };
  }

  return {
    config: response.data.data.config,
    etag: typeof response.headers.etag === 'string' ? response.headers.etag : null,
    notModified: false,
  };
}

export async function getRestaurantConfig(restauranteId: number): Promise<RestaurantConfig> {
  const result = await fetchRestaurantConfig(restauranteId);
  if (!result.config) throw new Error('La configuracion de la sucursal no esta disponible.');
  return result.config;
}

/**
 * Actualiza la configuración de un restaurante (requiere autenticación).
 */
export async function updateRestaurantConfig(
  restauranteId: number,
  payload: Partial<RestaurantConfig>
): Promise<RestaurantConfig> {
  const { data } = await apiClient.put<{
    success: boolean;
    data: { config: RestaurantConfig };
  }>(`/branches/${restauranteId}/config`, payload);

  return data.data.config;
}
