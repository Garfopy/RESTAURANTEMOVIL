import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Sucursal } from '@amare/types';
import type { DishModifierConfig, RestaurantConfig } from '@amare/types';
import { apiClient } from '../services/api';
import { fetchRestaurantConfig } from '../services/config.service';
import { normalizeBranch } from '../services/branches.service';

const SELECTED_BRANCH_KEY = 'amare_selected_branch';

interface BranchState {
  sucursales: Sucursal[];
  seleccionada: Sucursal | null;
  loading: boolean;
  setSucursales: (branches: Sucursal[]) => void;
  seleccionar: (branch: Sucursal) => void;
  clearSelection: () => void;
  autoSeleccionarSiUnica: () => void;
  fetchSucursales: () => Promise<void>;
}

export const useBranchStore = create<BranchState>((set, get) => ({
  sucursales: [],
  seleccionada: null,
  loading: false,

  setSucursales: (branches) => {
    set({ sucursales: branches });
    get().autoSeleccionarSiUnica();
  },

  seleccionar: (branch) => {
    const normalized = normalizeBranch(branch);
    set({ seleccionada: normalized });
    AsyncStorage.setItem(SELECTED_BRANCH_KEY, JSON.stringify(normalized)).catch(() => {});
  },

  clearSelection: () => {
    set({ seleccionada: null });
    AsyncStorage.removeItem(SELECTED_BRANCH_KEY).catch(() => {});
  },

  autoSeleccionarSiUnica: () => {
    const { sucursales, seleccionada } = get();
    if (seleccionada) {
      const updated = sucursales.find((item) => Number(item.id) === Number(seleccionada.id));
      if (updated) {
        set({ seleccionada: updated });
      }
      return;
    }
    if (!seleccionada && sucursales.length === 1) {
      get().seleccionar(sucursales[0]);
    }
  },

  fetchSucursales: async () => {
    try {
      set({ loading: true });
      
      // Usar el apiClient de axios que ya tiene la URL base de la PHP API
      const { data } = await apiClient.get<{ success: boolean; data: { branches: Sucursal[] } }>('/branches');
      
      const listaSucursales = (data.data?.branches || []).map(normalizeBranch);
      
      set({ sucursales: listaSucursales });
      get().autoSeleccionarSiUnica();
    } catch (error) {
      if (__DEV__) {
        console.error('Error en fetchSucursales de Zustand:', error);
      }
    } finally {
      set({ loading: false });
    }
  }
}));

export async function hydrateBranchSelection(): Promise<void> {
  try {
    const json = await AsyncStorage.getItem(SELECTED_BRANCH_KEY);
    if (!json) return;

    const branch = normalizeBranch(JSON.parse(json));
    if (!branch?.id) return;

    useBranchStore.setState({ seleccionada: branch });
  } catch {
    // sin sucursal guardada
  }
}

type BranchConfigEvent = { branch_id: number; version: number };
type BranchConfigListener = (event: BranchConfigEvent) => void;
const configListeners = new Set<BranchConfigListener>();
let pollingTimer: ReturnType<typeof setInterval> | null = null;
const requests = new Map<number, Promise<void>>();

export function notifyBranchConfigUpdated(event: BranchConfigEvent): void {
  configListeners.forEach((listener) => listener(event));
}

export function subscribeBranchConfigUpdated(listener: BranchConfigListener): () => void {
  configListeners.add(listener);
  return () => configListeners.delete(listener);
}

interface BranchConfigState {
  branchId: number | null;
  config: RestaurantConfig | null;
  modificadores: RestaurantConfig['modificadores'] | null;
  platillosModificadores: Record<string, DishModifierConfig>;
  selector: RestaurantConfig['selector'] | null;
  version: number;
  updatedAt: string | null;
  etag: string | null;
  loading: boolean;
  lastCheckedAt: number | null;
  error: string | null;
  refresh: (branchId: number, options?: { force?: boolean }) => Promise<void>;
  startPolling: (branchId: number) => void;
  stopPolling: () => void;
  clear: () => void;
}

export const useBranchConfigStore = create<BranchConfigState>((set, get) => ({
  branchId: null,
  config: null,
  modificadores: null,
  platillosModificadores: {},
  selector: null,
  version: 0,
  updatedAt: null,
  etag: null,
  loading: false,
  lastCheckedAt: null,
  error: null,

  refresh: async (branchId, options) => {
    const existing = requests.get(branchId);
    if (existing) return existing;

    const request = (async () => {
      const state = get();
      const sameBranch = state.branchId === branchId;
      set({ loading: true, error: null, ...(sameBranch ? {} : {
        branchId,
        config: null,
        modificadores: null,
        platillosModificadores: {},
        selector: null,
        version: 0,
        updatedAt: null,
        etag: null,
      }) });
      try {
        const result = await fetchRestaurantConfig(
          branchId,
          options?.force || !sameBranch ? null : get().etag
        );
        if (result.notModified || !result.config) {
          set({ loading: false, lastCheckedAt: Date.now() });
          return;
        }

        const current = get();
        const incomingVersion = Number(result.config.version || 0);
        if (current.branchId !== branchId || !current.config || incomingVersion > current.version) {
          set({
            branchId,
            config: result.config,
            modificadores: result.config.modificadores,
            platillosModificadores: result.config.platillos_modificadores ?? {},
            selector: result.config.selector,
            version: incomingVersion,
            updatedAt: result.config.updated_at,
            etag: result.etag,
            loading: false,
            lastCheckedAt: Date.now(),
          });
        } else {
          set({ loading: false, etag: result.etag ?? current.etag, lastCheckedAt: Date.now() });
        }
      } catch (error) {
        set({ loading: false, error: error instanceof Error ? error.message : String(error) });
        throw error;
      } finally {
        requests.delete(branchId);
      }
    })();

    requests.set(branchId, request);
    return request;
  },

  startPolling: (branchId) => {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => void get().refresh(branchId).catch(() => undefined), 10_000);
  },

  stopPolling: () => {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = null;
  },

  clear: () => {
    get().stopPolling();
    set({
      branchId: null, config: null, modificadores: null, platillosModificadores: {},
      selector: null, version: 0, updatedAt: null, etag: null, loading: false,
      lastCheckedAt: null, error: null,
    });
  },
}));
