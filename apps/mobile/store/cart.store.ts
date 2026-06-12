import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { CarritoItem, Platillo, ModificadorSeleccionado, TipoPedido } from '@amare/types';

const CART_KEY = 'amare_cart';

function calcSubtotal(platillo: Platillo, mods: ModificadorSeleccionado[], cantidad: number): number {
  const modExtra = mods.reduce(
    (sum, mod) => sum + mod.opciones.reduce((s, o) => s + o.precio_extra, 0),
    0
  );
  return (platillo.precio + modExtra) * cantidad;
}

interface CartState {
  items: CarritoItem[];
  restauranteId: number | null;
  tipoPedido: TipoPedido;
  total: number;
  itemCount: number;

  addItem: (
    platillo: Platillo,
    cantidad: number,
    mods: ModificadorSeleccionado[],
    notas: string
  ) => void;
  removeItem: (itemId: string) => void;
  updateQty: (itemId: string, cantidad: number) => void;
  clear: () => void;
  setTipoPedido: (tipo: TipoPedido) => void;
  _persist: () => void;
}

function computeTotals(items: CarritoItem[]): { total: number; itemCount: number } {
  return items.reduce(
    (acc, i) => ({
      total: acc.total + i.subtotal,
      itemCount: acc.itemCount + i.cantidad,
    }),
    { total: 0, itemCount: 0 }
  );
}

export const useCartStore = create<CartState>((set, get) => ({
  items: [],
  restauranteId: null,
  tipoPedido: 'pickup',
  total: 0,
  itemCount: 0,

  addItem: (platillo, cantidad, mods, notas) => {
    const current = get();

    // Si el carrito tiene items de otro restaurante, limpiar primero
    const items =
      current.restauranteId && current.restauranteId !== platillo.restaurante_id
        ? []
        : [...current.items];

    // Buscar si ya existe el mismo platillo con las mismas opciones
    const existing = items.find(
      (i) =>
        i.platillo.id === platillo.id &&
        JSON.stringify(i.modificadores_seleccionados) === JSON.stringify(mods) &&
        i.notas === notas
    );

    if (existing) {
      const updated = items.map((i) =>
        i.id === existing.id
          ? {
              ...i,
              cantidad: i.cantidad + cantidad,
              subtotal: calcSubtotal(platillo, mods, i.cantidad + cantidad),
            }
          : i
      );
      const { total, itemCount } = computeTotals(updated);
      set({ items: updated, restauranteId: platillo.restaurante_id, total, itemCount });
    } else {
      const modExtraUnitario = mods.reduce(
        (sum, mod) => sum + mod.opciones.reduce((s, o) => s + o.precio_extra, 0),
        0
      );
      const newItem: CarritoItem = {
        id: `${platillo.id}-${Date.now()}`,
        platillo,
        cantidad,
        modificadores_seleccionados: mods,
        notas,
        precio_unitario: platillo.precio + modExtraUnitario,
        subtotal: calcSubtotal(platillo, mods, cantidad),
      };
      const updated = [...items, newItem];
      const { total, itemCount } = computeTotals(updated);
      set({ items: updated, restauranteId: platillo.restaurante_id, total, itemCount });
    }

    get()._persist();
  },

  removeItem: (itemId) => {
    const updated = get().items.filter((i) => i.id !== itemId);
    const { total, itemCount } = computeTotals(updated);
    set({ items: updated, total, itemCount });
    get()._persist();
  },

  updateQty: (itemId, cantidad) => {
    if (cantidad <= 0) {
      get().removeItem(itemId);
      return;
    }
    const updated = get().items.map((i) =>
      i.id === itemId
        ? { ...i, cantidad, subtotal: calcSubtotal(i.platillo, i.modificadores_seleccionados, cantidad) }
        : i
    );
    const { total, itemCount } = computeTotals(updated);
    set({ items: updated, total, itemCount });
    get()._persist();
  },

  clear: () => {
    set({ items: [], restauranteId: null, total: 0, itemCount: 0 });
    AsyncStorage.removeItem(CART_KEY).catch(() => {});
  },

  setTipoPedido: (tipo) => set({ tipoPedido: tipo }),

  _persist: () => {
    const { items, restauranteId, tipoPedido } = get();
    AsyncStorage.setItem(CART_KEY, JSON.stringify({ items, restauranteId, tipoPedido })).catch(() => {});
  },
}));

// Hidratar carrito desde AsyncStorage al arrancar
export async function hydrateCart(): Promise<void> {
  try {
    const json = await AsyncStorage.getItem(CART_KEY);
    if (json) {
      const { items, restauranteId, tipoPedido } = JSON.parse(json);
      const { total, itemCount } = computeTotals(items ?? []);
      useCartStore.setState({ items: items ?? [], restauranteId, tipoPedido, total, itemCount });
    }
  } catch {
    // sin datos guardados
  }
}
