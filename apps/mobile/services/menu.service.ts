import { apiClient, formatImageUrl } from './api';
import type { Categoria, Platillo } from '@amare/types';

/** Convierte rutas relativas de BD (uploads/...) a URL completa de la API */
function resolveImg<T extends { imagen: string | null }>(item: T): T {
  if (item.imagen && !item.imagen.startsWith('http')) {
    return { ...item, imagen: formatImageUrl(item.imagen) ?? item.imagen };
  }
  return item;
}

/**
 * Obtiene categorías del menú.
 * Nota: La PHP API no filtra por restaurante, devuelve todas las categorías.
 */
export async function getCategories(_restauranteId?: number): Promise<Categoria[]> {
  const { data } = await apiClient.get<{ success: boolean; data: { categories: Categoria[] } }>(
    '/menu/categories'
  );
  return data.data.categories.map(resolveImg);
}

export interface GetDishesParams {
  categoria_id?: number;
  branch_id?: number;
  q?: string;
  orden?: 'precio_asc' | 'precio_desc' | 'nombre' | 'populares';
}

/**
 * Obtiene productos del menú.
 * Nota: La PHP API no filtra por restaurante, usa query params: category_id, branch_id.
 */
export async function getDishes(_restauranteId?: number, params?: GetDishesParams): Promise<Platillo[]> {
  const queryParams: Record<string, string | number> = {};
  if (params?.categoria_id) queryParams.category_id = params.categoria_id;
  if (params?.branch_id) queryParams.branch_id = params.branch_id;
  if (params?.q) queryParams.q = params.q;

  const { data } = await apiClient.get<{ success: boolean; data: { products: Platillo[] } }>(
    '/menu/products',
    { params: queryParams }
  );
  return data.data.products.map(resolveImg);
}

/**
 * Obtiene un producto por ID.
 * Nota: La PHP API no requiere restaurante_id en la ruta.
 */
export async function getDishById(_restauranteId?: number, dishId?: number): Promise<Platillo> {
  const { data } = await apiClient.get<{ success: boolean; data: { product: Platillo } }>(
    `/menu/products/${dishId}`
  );
  return resolveImg(data.data.product);
}

/**
 * ⚠️ La PHP API no tiene endpoint de platillos destacados.
 * Se obtienen todos los productos y se devuelven los primeros 8 como destacados.
 */
export async function getFeaturedDishes(_restauranteId?: number): Promise<Platillo[]> {
  const dishes = await getDishes();
  return dishes.slice(0, 8);
}