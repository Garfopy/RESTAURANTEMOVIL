import { create } from 'zustand';

interface FavoritesState {
  ids: Set<number>;
  toggle: (platilloId: number) => void;
  syncFromServer: (ids: number[]) => void;
  isFavorite: (platilloId: number) => boolean;
}

export const useFavoritesStore = create<FavoritesState>((set, get) => ({
  ids: new Set<number>(),

  toggle: (platilloId) => {
    const ids = new Set(get().ids);
    if (ids.has(platilloId)) {
      ids.delete(platilloId);
    } else {
      ids.add(platilloId);
    }
    set({ ids });
  },

  syncFromServer: (serverIds) => {
    set({ ids: new Set(serverIds) });
  },

  isFavorite: (platilloId) => get().ids.has(platilloId),
}));
