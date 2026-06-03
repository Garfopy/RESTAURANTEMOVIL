import { apiClient } from './api';
import { API_BASE_URL } from '../constants/api';
import type { Categoria, Platillo } from '@amare/types';

/** Convierte rutas relativas de BD (public/...) a URL completa de la API */
function resolveImg<T extends { imagen: string | null }>(item: T): T {
  if (item.imagen && !item.imagen.startsWith('http')) {
    return { ...item, imagen: `${API_BASE_URL}/${item.imagen}` };
  }
  return item;
}

export async function getCategories(restauranteId: number): Promise<Categoria[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Categoria[] }>(
    `/menu/${restauranteId}/categories`
  );
  return data.data.map(resolveImg);
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
  return data.data.map(resolveImg);
}

export async function getDishById(restauranteId: number, dishId: number): Promise<Platillo> {
  const { data } = await apiClient.get<{ ok: boolean; data: Platillo }>(
    `/menu/${restauranteId}/dishes/${dishId}`
  );
  return resolveImg(data.data);
}

export async function getFeaturedDishes(restauranteId: number): Promise<Platillo[]> {
  const { data } = await apiClient.get<{ ok: boolean; data: Platillo[] }>(
    `/menu/${restauranteId}/featured`
  );
  return data.data.map(resolveImg);
}
