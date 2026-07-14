import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getCategories, getDishes, getDishById, getFeaturedDishes, GetDishesParams } from '../services/menu.service';
import type { Categoria, Platillo } from '@amare/types';
import { useBranchConfigStore } from '../store/branch.store';

export const menuKeys = {
  categories: (restauranteId: number, version = 0) => ['menu', restauranteId, 'categories', version] as const,
  dishes: (restauranteId: number, params?: GetDishesParams, version = 0) =>
    ['menu', restauranteId, 'dishes', params, version] as const,
  dish: (restauranteId: number, dishId: number, version = 0) =>
    ['menu', restauranteId, 'dishes', dishId, version] as const,
  featured: (restauranteId: number, version = 0) => ['menu', restauranteId, 'featured', version] as const,
};

function useMenuConfigVersion(restauranteId?: number): number {
  const version = useBranchConfigStore((state) => state.branchId === restauranteId ? state.version : 0);
  const refresh = useBranchConfigStore((state) => state.refresh);
  useEffect(() => {
    if (restauranteId) void refresh(restauranteId).catch(() => undefined);
  }, [refresh, restauranteId]);
  return version;
}

export function useCategories(restauranteId?: number) {
  const version = useMenuConfigVersion(restauranteId);
  return useQuery<Categoria[]>({
    queryKey: menuKeys.categories(restauranteId ?? 0, version),
    queryFn: () => getCategories(restauranteId!),
    enabled: restauranteId !== undefined,
    staleTime: 5 * 60 * 1000,
  });
}

export function useDishes(restauranteId?: number, params?: GetDishesParams) {
  const version = useMenuConfigVersion(restauranteId);
  return useQuery<Platillo[]>({
    queryKey: menuKeys.dishes(restauranteId ?? 0, params, version),
    queryFn: () => getDishes(restauranteId!, params),
    enabled: restauranteId !== undefined,
    staleTime: 3 * 60 * 1000,
  });
}

export function useDish(restauranteId?: number, dishId?: number) {
  const version = useMenuConfigVersion(restauranteId);
  return useQuery<Platillo>({
    queryKey: menuKeys.dish(restauranteId ?? 0, dishId ?? 0, version),
    queryFn: () => getDishById(restauranteId!, dishId!),
    enabled: dishId !== undefined && Number.isFinite(dishId),
    staleTime: 5 * 60 * 1000,
  });
}

export function useFeaturedDishes(restauranteId?: number) {
  const version = useMenuConfigVersion(restauranteId);
  return useQuery<Platillo[]>({
    queryKey: menuKeys.featured(restauranteId ?? 0, version),
    queryFn: () => getFeaturedDishes(restauranteId!),
    enabled: restauranteId !== undefined,
    staleTime: 5 * 60 * 1000,
  });
}
