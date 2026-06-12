import { apiClient } from './api';
import type { RestaurantConfig } from '@amare/types';

/**
 * Obtiene la configuración de métodos de pago y tipos de entrega de un restaurante.
 */
export async function getRestaurantConfig(restauranteId: number): Promise<RestaurantConfig> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { config: RestaurantConfig };
  }>(`/branches/${restauranteId}/config`);

  return data.data.config;
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