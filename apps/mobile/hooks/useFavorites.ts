import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../services/api';

export function useFavorites() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['favorites'],
    queryFn: async () => {
      const res = await apiClient.get('/favorites');
      return res.data.data;
    },
  });

  const toggle = async (id: number) => {
    const previous = queryClient.getQueryData<any[]>(['favorites']) || [];

    // optimistic update
    queryClient.setQueryData(['favorites'], (old: any[] = []) => {
      const exists = old.some((p) => p.id === id);

      if (exists) {
        return old.filter((p) => p.id !== id);
      }

      return [...old, { id }];
    });

    try {
      await apiClient.post('/favorites/toggle', {
        product_id: id, 
      });

      // sync final
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
    } catch (error) {
      // rollback si falla
      queryClient.setQueryData(['favorites'], previous);
      throw error;
    }
  };

  return {
    ...query,
    toggle,
  };
}