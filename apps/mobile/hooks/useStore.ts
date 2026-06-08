import { useQuery } from '@tanstack/react-query';
import { getStoreCategories, getStoreProducts, getStoreProductById } from '../services/store.service';
import type { GetStoreProductsParams } from '../services/store.service';

export function useStoreCategories() {
  return useQuery({
    queryKey: ['store', 'categories'],
    queryFn: getStoreCategories,
    staleTime: 5 * 60 * 1000, // 5 min
  });
}

export function useStoreProducts(params?: GetStoreProductsParams) {
  return useQuery({
    queryKey: ['store', 'products', params],
    queryFn: () => getStoreProducts(params),
    staleTime: 2 * 60 * 1000, // 2 min
  });
}

export function useStoreProduct(id: number) {
  return useQuery({
    queryKey: ['store', 'product', id],
    queryFn: () => getStoreProductById(id),
    enabled: !!id,
    staleTime: 5 * 60 * 1000,
  });
}