import { useQuery } from '@tanstack/react-query';
import { getCategories, getDishes, getDishById, getFeaturedDishes, GetDishesParams } from '../services/menu.service';
import type { Categoria, Platillo } from '@amare/types';

export const menuKeys = {
  categories: (restauranteId: number) => ['menu', restauranteId, 'categories'] as const,
  dishes: (restauranteId: number, params?: GetDishesParams) =>
    ['menu', restauranteId, 'dishes', params] as const,
  dish: (restauranteId: number, dishId: number) =>
    ['menu', restauranteId, 'dishes', dishId] as const,
  featured: (restauranteId: number) => ['menu', restauranteId, 'featured'] as const,
};

export function useCategories(restauranteId?: number) {
  return useQuery<Categoria[]>({
    queryKey: menuKeys.categories(restauranteId ?? 0),
    queryFn: () => getCategories(restauranteId!),
    enabled: restauranteId !== undefined,
    staleTime: 5 * 60 * 1000,
  });
}

export function useDishes(restauranteId?: number, params?: GetDishesParams) {
  return useQuery<Platillo[]>({
    queryKey: menuKeys.dishes(restauranteId ?? 0, params),
    queryFn: () => getDishes(restauranteId!, params),
    enabled: restauranteId !== undefined,
    staleTime: 3 * 60 * 1000,
  });
}

export function useDish(restauranteId?: number, dishId?: number) {
  return useQuery<Platillo>({
    queryKey: menuKeys.dish(restauranteId ?? 0, dishId ?? 0),
    queryFn: () => getDishById(restauranteId!, dishId!),
    enabled: restauranteId !== undefined && dishId !== undefined,
    staleTime: 5 * 60 * 1000,
  });
}

export function useFeaturedDishes(restauranteId?: number) {
  return useQuery<Platillo[]>({
    queryKey: menuKeys.featured(restauranteId ?? 0),
    queryFn: () => getFeaturedDishes(restauranteId!),
    enabled: restauranteId !== undefined,
    staleTime: 5 * 60 * 1000,
  });
}
