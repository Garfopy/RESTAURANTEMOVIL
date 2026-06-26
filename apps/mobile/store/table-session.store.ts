import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { Sucursal, TableScanResult } from '@amare/types';

const TABLE_SESSION_KEY = 'amare_table_session';
const DEFERRED_SCAN_BRANCH_KEY = 'amare_deferred_scan_branch';

export type TableSession = {
  restauranteId: number;
  mesaId: number;
  mesaLabel: string;
  mesaValue: string;
  branch: Sucursal | null;
};

interface TableSessionState {
  session: TableSession | null;
  deferredBranch: Sucursal | null;
  setSession: (result: TableScanResult) => void;
  deferScan: (branch: Sucursal | null) => void;
  clearSession: () => void;
}

export const useTableSessionStore = create<TableSessionState>((set) => ({
  session: null,
  deferredBranch: null,

  setSession: (result) => {
    const nextSession: TableSession = {
      restauranteId: result.restaurante_id,
      mesaId: result.mesa_id,
      mesaLabel: result.mesa_label,
      mesaValue: result.mesa_value,
      branch: result.branch ?? null,
    };
    set({ session: nextSession, deferredBranch: null });
    AsyncStorage.setItem(TABLE_SESSION_KEY, JSON.stringify(nextSession)).catch(() => {});
    AsyncStorage.removeItem(DEFERRED_SCAN_BRANCH_KEY).catch(() => {});
  },

  deferScan: (branch) => {
    set({ deferredBranch: branch });
    if (branch) {
      AsyncStorage.setItem(DEFERRED_SCAN_BRANCH_KEY, JSON.stringify(branch)).catch(() => {});
    } else {
      AsyncStorage.removeItem(DEFERRED_SCAN_BRANCH_KEY).catch(() => {});
    }
  },

  clearSession: () => {
    set({ session: null });
    AsyncStorage.removeItem(TABLE_SESSION_KEY).catch(() => {});
  },
}));

export async function hydrateTableSession(): Promise<void> {
  try {
    const json = await AsyncStorage.getItem(TABLE_SESSION_KEY);
    if (json) {
      const session = JSON.parse(json) as TableSession;
      if (session?.restauranteId && session?.mesaId && session?.mesaValue) {
        useTableSessionStore.setState({ session });
      }
    }
  } catch {
    // sin mesa guardada
  }

  try {
    const json = await AsyncStorage.getItem(DEFERRED_SCAN_BRANCH_KEY);
    if (!json) return;

    const branch = JSON.parse(json) as Sucursal;
    if (!branch?.id) return;

    useTableSessionStore.setState({ deferredBranch: branch });
  } catch {
    // sin escaneo pospuesto
  }
}
