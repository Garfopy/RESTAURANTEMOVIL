import { create } from 'zustand';
import { apiClient } from '../services/api';

interface FavoritesState {
  ids: Set<number>;
  toggle: (platilloId: number) => Promise<void>;
  syncFromServer: (ids: number[]) => void;
  isFavorite: (platilloId: number) => boolean;
}

export const useFavoritesStore = create<FavoritesState>((set, get) => ({
  ids: new Set<number>(),

  toggle: async (platilloId) => {
    const ids = new Set(get().ids);
    const isAdding = !ids.has(platilloId);
    
    // Actualización optimista (UI rápida)
    if (isAdding) ids.add(platilloId); else ids.delete(platilloId);
    set({ ids: new Set(ids) });

    try {
      // Llamada real a la API que creamos
      await apiClient.post(`/favorites/${platilloId}`);
    } catch (error) {
      // Si falla, revertimos el estado local
      const rollbackIds = new Set(get().ids);
      if (isAdding) rollbackIds.delete(platilloId); else rollbackIds.add(platilloId);
      set({ ids: rollbackIds });
    }
  },

  syncFromServer: (serverIds) => {
    set({ ids: new Set(serverIds) });
  },

  isFavorite: (platilloId) => get().ids.has(platilloId),
}));
