import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { MobileUser, Sesion } from '@amare/types';
import { API_BASE_URL } from '../constants/api';
import { useBranchConfigStore, useBranchStore } from './branch.store';
import { useCartStore } from './cart.store';
import type { AccountSuspensionNotice } from '../services/account-suspension.service';

const TOKEN_KEY = 'amare_auth_token';
const API_SOURCE_KEY = 'amare_auth_api_url';

interface UserState {
  user: MobileUser | null;
  token: string | null;
  accountSuspension: AccountSuspensionNotice | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (sesion: Sesion) => Promise<void>;
  logout: (options?: { accountSuspension?: AccountSuspensionNotice | null }) => Promise<void>;
  setAccountSuspension: (notice: AccountSuspensionNotice | null) => void;
  setUser: (user: MobileUser) => void;
  updateProfile: (data: Partial<MobileUser>) => void;
  hydrateFromStorage: () => Promise<void>;
}

export const useUserStore = create<UserState>((set, get) => ({
  user: null,
  token: null,
  accountSuspension: null,
  isAuthenticated: false,
  isLoading: true,

  login: async (sesion: Sesion) => {
    clearSessionState();
    await Promise.all([
      SecureStore.setItemAsync(TOKEN_KEY, sesion.token),
      SecureStore.setItemAsync(API_SOURCE_KEY, normalizeApiBase(API_BASE_URL)),
    ]);
    set({ user: sesion.user, token: sesion.token, accountSuspension: null, isAuthenticated: true });
  },

  logout: async (options) => {
    await Promise.all([
      SecureStore.deleteItemAsync(TOKEN_KEY),
      SecureStore.deleteItemAsync(API_SOURCE_KEY),
    ]);
    clearSessionState();
    set({
      user: null,
      token: null,
      accountSuspension: options?.accountSuspension ?? null,
      isAuthenticated: false,
      isLoading: false,
    });
  },

  setAccountSuspension: (notice) => {
    set({ accountSuspension: notice });
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
      const [token, storedApiBase] = await Promise.all([
        SecureStore.getItemAsync(TOKEN_KEY),
        SecureStore.getItemAsync(API_SOURCE_KEY),
      ]);

      if (token && storedApiBase && storedApiBase !== normalizeApiBase(API_BASE_URL)) {
        await Promise.all([
          SecureStore.deleteItemAsync(TOKEN_KEY),
          SecureStore.deleteItemAsync(API_SOURCE_KEY),
        ]);
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
  useBranchConfigStore.getState().clear();
  useBranchStore.getState().clearSelection();
  useCartStore.getState().clear();
}
