import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../services/api';
import { isSignedIn } from '../services/auth-gate.service';
import { useUserStore } from '../store/user.store';

type FavoriteItem = {
  id: number;
};

export function useFavorites() {
  const queryClient = useQueryClient();
  const token = useUserStore((state) => state.token);
  const signedIn = Boolean(token);

  const query = useQuery<FavoriteItem[]>({
    queryKey: ['favorites'],
    queryFn: async () => {
      const res = await apiClient.get('/favorites');
      return (res.data.data ?? []) as FavoriteItem[];
    },
    enabled: signedIn,
  });

  const toggle = async (id: number) => {
    if (!isSignedIn()) return;
    const previous = queryClient.getQueryData<FavoriteItem[]>(['favorites']) || [];

    // optimistic update
    queryClient.setQueryData<FavoriteItem[]>(['favorites'], (old = []) => {
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
