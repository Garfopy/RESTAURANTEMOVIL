import { apiClient } from './api';
import type { Categoria, Platillo } from '@amare/types';

export async function getCategories(restauranteId: number): Promise<Categoria[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Categoria[] }>(
    `/menu/${restauranteId}/categories`
  );
  return data.data;
}

export interface GetDishesParams {
  categoria_id?: number;
  q?: string;
  orden?: 'precio_asc' | 'precio_desc' | 'nombre' | 'populares';
}

export async function getDishes(restauranteId: number, params?: GetDishesParams): Promise<Platillo[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Platillo[] }>(
    `/menu/${restauranteId}/dishes`,
    { params }
  );
  return data.data;
}

export async function getDishById(restauranteId: number, dishId: number): Promise<Platillo> {
  const { data } = await apiClient.get<{ ok: boolean; data: Platillo }>(
    `/menu/${restauranteId}/dishes/${dishId}`
  );
  return data.data;
}

export async function getFeaturedDishes(restauranteId: number): Promise<Platillo[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Platillo[] }>(
    `/menu/${restauranteId}/featured`
  );
  return data.data;
}
