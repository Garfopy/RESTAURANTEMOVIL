import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Sucursal, TableScanResult } from '@amare/types';

const TABLE_SESSION_KEY = 'amare_table_session';

export type TableSession = {
  restauranteId: number;
  mesaId: number;
  mesaLabel: string;
  mesaValue: string;
  branch: Sucursal | null;
};

interface TableSessionState {
  session: TableSession | null;
  setSession: (result: TableScanResult) => void;
  clearSession: () => void;
}

export const useTableSessionStore = create<TableSessionState>((set) => ({
  session: null,

  setSession: (result) => {
    const nextSession: TableSession = {
      restauranteId: result.restaurante_id,
      mesaId: result.mesa_id,
      mesaLabel: result.mesa_label,
      mesaValue: result.mesa_value,
      branch: result.branch ?? null,
    };
    set({ session: nextSession });
    AsyncStorage.setItem(TABLE_SESSION_KEY, JSON.stringify(nextSession)).catch(() => {});
  },

  clearSession: () => {
    set({ session: null });
    AsyncStorage.removeItem(TABLE_SESSION_KEY).catch(() => {});
  },
}));

export async function hydrateTableSession(): Promise<void> {
  try {
    const json = await AsyncStorage.getItem(TABLE_SESSION_KEY);
    if (!json) return;

    const session = JSON.parse(json) as TableSession;
    if (!session?.restauranteId || !session?.mesaId || !session?.mesaValue) {
      return;
    }

    useTableSessionStore.setState({ session });
  } catch {
    // sin mesa guardada
  }
}
