import { apiClient, formatImageUrl } from './api';
import type { Categoria, Platillo } from '@amare/types';

/** Convierte rutas relativas de BD (uploads/...) a URL completa de la API */
function resolveImg<T extends { imagen: string | null }>(item: T): T {
  if (item.imagen && !item.imagen.startsWith('http')) {
    return { ...item, imagen: formatImageUrl(item.imagen) ?? item.imagen };
  }
  return item;
}

export async function getCategories(restauranteId?: number): Promise<Categoria[]> {
  const { data } = await apiClient.get<{ success: boolean; data: { categories: Categoria[] } }>(
    '/menu/categories',
    { params: restauranteId ? { branch_id: restauranteId } : undefined }
  );
  return data.data.categories.map(resolveImg);
}

export interface GetDishesParams {
  categoria_id?: number;
  branch_id?: number;
  q?: string;
  orden?: 'precio_asc' | 'precio_desc' | 'nombre' | 'populares';
}

export async function getDishes(restauranteId?: number, params?: GetDishesParams): Promise<Platillo[]> {
  const branchId = params?.branch_id ?? restauranteId;
  const queryParams: Record<string, string | number> = {};

  if (params?.categoria_id) queryParams.category_id = params.categoria_id;
  if (branchId) queryParams.branch_id = branchId;
  if (params?.q) queryParams.q = params.q;

  const { data } = await apiClient.get<{ success: boolean; data: { products: Platillo[] } }>(
    '/menu/products',
    { params: queryParams }
  );
  return data.data.products.map(resolveImg);
}

export async function getDishById(restauranteId?: number, dishId?: number): Promise<Platillo> {
  const { data } = await apiClient.get<{ success: boolean; data: { product: Platillo } }>(
    `/menu/products/${dishId}`,
    { params: restauranteId ? { branch_id: restauranteId } : undefined }
  );
  return resolveImg(data.data.product);
}

export async function getFeaturedDishes(restauranteId?: number): Promise<Platillo[]> {
  const dishes = await getDishes(restauranteId);
  return dishes.slice(0, 8);
}
