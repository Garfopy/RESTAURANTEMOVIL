import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { CarritoItem, Platillo, ModificadorSeleccionado, TipoPedido } from '@amare/types';

const CART_KEY = 'amare_cart';

export type DeliveryAddressSelection = {
  id?: number | string | null;
  alias?: string | null;
  text: string;
  lat?: number | null;
  lng?: number | null;
  instrucciones?: string | null;
};

function money(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function getModExtraTotal(mods: ModificadorSeleccionado[]): number {
  return mods.reduce(
    (sum, mod) => sum + mod.opciones.reduce((s, o) => s + money(o.precio_extra), 0),
    0
  );
}

function calcSubtotal(platillo: Platillo, mods: ModificadorSeleccionado[], cantidad: number): number {
  return (money(platillo.precio) + getModExtraTotal(mods)) * cantidad;
}

interface CartState {
  items: CarritoItem[];
  restauranteId: number | null;
  tipoPedido: TipoPedido | null;
  deliveryAddress: DeliveryAddressSelection | null;
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
  setTipoPedido: (tipo: TipoPedido | null) => void;
  setDeliveryAddress: (address: DeliveryAddressSelection | null) => void;
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
  tipoPedido: null,
  deliveryAddress: null,
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
      const modExtraUnitario = getModExtraTotal(mods);
      const newItem: CarritoItem = {
        id: `${platillo.id}-${Date.now()}`,
        platillo,
        cantidad,
        modificadores_seleccionados: mods,
        notas,
        precio_unitario: money(platillo.precio) + modExtraUnitario,
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
    set({ items: [], restauranteId: null, tipoPedido: null, deliveryAddress: null, total: 0, itemCount: 0 });
    AsyncStorage.removeItem(CART_KEY).catch(() => {});
  },

  setTipoPedido: (tipo) => {
    set((state) => ({
      tipoPedido: tipo,
      deliveryAddress: tipo === 'delivery' ? state.deliveryAddress : null,
    }));
    get()._persist();
  },

  setDeliveryAddress: (address) => {
    set({ deliveryAddress: address });
    get()._persist();
  },

  _persist: () => {
    const { items, restauranteId, tipoPedido, deliveryAddress } = get();
    AsyncStorage.setItem(CART_KEY, JSON.stringify({ items, restauranteId, tipoPedido, deliveryAddress })).catch(() => {});
  },
}));

// Hidratar carrito desde AsyncStorage al arrancar
export async function hydrateCart(): Promise<void> {
  try {
    const json = await AsyncStorage.getItem(CART_KEY);
    if (json) {
      const { items, restauranteId, tipoPedido, deliveryAddress } = JSON.parse(json);
      const derivedRestaurantId =
        restauranteId ??
        items?.[0]?.platillo?.restaurante_id ??
        items?.[0]?.platillo?.restaurant_id ??
        null;
      const restoredItems = (items ?? []).map((item: CarritoItem) => {
        const mods = item.modificadores_seleccionados ?? [];
        const precioUnitario = money(item.platillo?.precio) + getModExtraTotal(mods);

        return {
          ...item,
          modificadores_seleccionados: mods,
          precio_unitario: precioUnitario,
          subtotal: precioUnitario * money(item.cantidad || 1),
        };
      });
      const { total, itemCount } = computeTotals(restoredItems);
      useCartStore.setState({
        items: restoredItems,
        restauranteId:
          derivedRestaurantId == null ? null : Number.isNaN(Number(derivedRestaurantId)) ? null : Number(derivedRestaurantId),
        tipoPedido: restoredItems.length > 0 ? tipoPedido ?? null : null,
        deliveryAddress: tipoPedido === 'delivery' ? deliveryAddress ?? null : null,
        total,
        itemCount,
      });
    }
  } catch {
    // sin datos guardados
  }
}
