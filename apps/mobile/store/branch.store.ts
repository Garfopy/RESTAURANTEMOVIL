import { create } from 'zustand';
import type { Sucursal } from '@amare/types';

interface BranchState {
  sucursales: Sucursal[];
  seleccionada: Sucursal | null;
  loading: boolean;
  setSucursales: (branches: Sucursal[]) => void;
  seleccionar: (branch: Sucursal) => void;
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

  autoSeleccionarSiUnica: () => {
    const { sucursales, seleccionada } = get();
    if (!seleccionada && sucursales.length === 1) {
      set({ seleccionada: sucursales[0] });
    }
  },

  // 🔄 CONEXIÓN CORREGIDA CON TU BACKEND EXPRESS
  fetchSucursales: async () => {
    try {
      set({ loading: true });
      
      // ⚠️ Cambia "192.168.X.X:3000" por tu IP local real y el puerto de tu Express
      // Ej: 'http://192.168.1.45:3000/branches'
      const response = await fetch('http://192.168.1.100:3001/branches'); 
      
      if (!response.ok) throw new Error('Error al conectar con la API de sucursales');
      
      const resJson = await response.json();
      
      // 🌟 CORRECCIÓN: Extraemos "resJson.data" porque tu backend responde con { ok: true, data: [...] }
      const listaSucursales = resJson.data || [];
      
      set({ sucursales: listaSucursales });
      get().autoSeleccionarSiUnica();
    } catch (error) {
      console.error("🔴 Error en fetchSucursales de Zustand:", error);
    } finally {
      set({ loading: false });
    }
  }
}));