import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { MobileUser, Sesion } from '@amare/types';

const TOKEN_KEY = 'amare_auth_token';

interface UserState {
  user: MobileUser | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (sesion: Sesion) => Promise<void>;
  logout: () => Promise<void>;
  updateProfile: (data: Partial<MobileUser>) => void;
  hydrateFromStorage: () => Promise<void>;
}

export const useUserStore = create<UserState>((set, get) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  isLoading: true,

  login: async (sesion: Sesion) => {
    await SecureStore.setItemAsync(TOKEN_KEY, sesion.token);
    set({ user: sesion.user, token: sesion.token, isAuthenticated: true });
  },

  logout: async () => {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    set({ user: null, token: null, isAuthenticated: false });
  },

  updateProfile: (data: Partial<MobileUser>) => {
    const current = get().user;
    if (current) set({ user: { ...current, ...data } });
  },

  hydrateFromStorage: async () => {
    try {
      const token = await SecureStore.getItemAsync(TOKEN_KEY);
      if (token) {
        set({ token, isAuthenticated: true });
      }
    } catch {
      // token no disponible
    } finally {
      set({ isLoading: false });
    }
  },
}));
