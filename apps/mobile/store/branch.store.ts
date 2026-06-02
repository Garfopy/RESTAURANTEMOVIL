import { create } from 'zustand';
import type { Sucursal } from '@amare/types';

interface BranchState {
  sucursales: Sucursal[];
  seleccionada: Sucursal | null;
  setSucursales: (branches: Sucursal[]) => void;
  seleccionar: (branch: Sucursal) => void;
  autoSeleccionarSiUnica: () => void;
}

export const useBranchStore = create<BranchState>((set, get) => ({
  sucursales: [],
  seleccionada: null,

  setSucursales: (branches) => {
    set({ sucursales: branches });
    get().autoSeleccionarSiUnica();
  },

  seleccionar: (branch) => set({ seleccionada: branch }),

  autoSeleccionarSiUnica: () => {
    const { sucursales, seleccionada } = get();
    if (!seleccionada && sucursales.length === 1) {
      set({ seleccionada: sucursales[0] });
    }
  },
}));
