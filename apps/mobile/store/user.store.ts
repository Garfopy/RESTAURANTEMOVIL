import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { MobileUser, Sesion } from '@amare/types';
import { API_BASE_URL } from '../constants/api';
import { useCartStore } from './cart.store';
import { useTableSessionStore } from './table-session.store';
import { useWaiterCartStore } from './waiter-cart.store';

const TOKEN_KEY = 'amare_auth_token';
const API_SOURCE_KEY = 'amare_auth_api_url';

interface UserState {
  user: MobileUser | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (sesion: Sesion) => Promise<void>;
  logout: () => Promise<void>;
  setUser: (user: MobileUser) => void;
  updateProfile: (data: Partial<MobileUser>) => void;
  hydrateFromStorage: () => Promise<void>;
}

export const useUserStore = create<UserState>((set, get) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  isLoading: true,

  login: async (sesion: Sesion) => {
    clearSessionState();
    await SecureStore.setItemAsync(TOKEN_KEY, sesion.token);
    await SecureStore.setItemAsync(API_SOURCE_KEY, normalizeApiBase(API_BASE_URL));
    set({ user: sesion.user, token: sesion.token, isAuthenticated: true });
  },

  logout: async () => {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(API_SOURCE_KEY);
    clearSessionState();
    set({ user: null, token: null, isAuthenticated: false, isLoading: false });
  },

  setUser: (user: MobileUser) => {
    set({ user, isLoading: false });
  },

  updateProfile: (data: Partial<MobileUser>) => {
    const current = get().user;
    if (current) set({ user: { ...current, ...data } });
  },

  hydrateFromStorage: async () => {
    try {
      const token = await SecureStore.getItemAsync(TOKEN_KEY);
      const storedApiBase = await SecureStore.getItemAsync(API_SOURCE_KEY);

      if (token && storedApiBase && storedApiBase !== normalizeApiBase(API_BASE_URL)) {
        await SecureStore.deleteItemAsync(TOKEN_KEY);
        await SecureStore.deleteItemAsync(API_SOURCE_KEY);
        set({ user: null, token: null, isAuthenticated: false, isLoading: false });
        return;
      }

      if (token) {
        // Solo restauramos el token; _layout valida con el servidor antes de continuar.
        set({ token, isAuthenticated: true });
        return;
      }
    } catch {
      // Token no disponible.
    }

    set({ isLoading: false });
  },
}));

function normalizeApiBase(url: string): string {
  return url.trim().replace(/\/+$/, '').toLowerCase();
}

function clearSessionState(): void {
  useTableSessionStore.getState().clearSession();
  useCartStore.getState().clear();
  useWaiterCartStore.getState().clear();
}
