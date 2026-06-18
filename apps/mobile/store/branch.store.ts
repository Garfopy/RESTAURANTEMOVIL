import { create } from 'zustand';
import type { Sucursal } from '@amare/types';
import { apiClient } from '../services/api';
import { normalizeBranch } from '../services/branches.service';

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

  seleccionar: (branch) => set({ seleccionada: branch }),

  clearSelection: () => set({ seleccionada: null }),

  autoSeleccionarSiUnica: () => {
    const { sucursales, seleccionada } = get();
    if (!seleccionada && sucursales.length === 1) {
      set({ seleccionada: sucursales[0] });
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
      console.error("🔴 Error en fetchSucursales de Zustand:", error);
    } finally {
      set({ loading: false });
    }
  }
}));
