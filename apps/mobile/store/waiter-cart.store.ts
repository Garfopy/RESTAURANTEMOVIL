import { create } from 'zustand';
import type { ModificadorSeleccionado, Platillo } from '@amare/types';

export type WaiterCartItem = {
  id: string;
  platillo: Platillo;
  cantidad: number;
  modificadores: ModificadorSeleccionado[];
  notas: string;
  precio_unitario: number;
  subtotal: number;
};

type WaiterCartState = {
  tableId: number | null;
  restaurantId: number | null;
  clienteNombre: string;
  items: WaiterCartItem[];
  total: number;
  addItem: (params: {
    tableId: number;
    restaurantId: number;
    clienteNombre: string;
    platillo: Platillo;
    cantidad: number;
    modificadores: ModificadorSeleccionado[];
    notas: string;
  }) => void;
  removeItem: (id: string) => void;
  updateQty: (id: string, cantidad: number) => void;
  clear: () => void;
};

function money(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function modifiersTotal(modificadores: ModificadorSeleccionado[]): number {
  return modificadores.reduce(
    (sum, mod) => sum + mod.opciones.reduce((inner, option) => inner + money(option.precio_extra), 0),
    0
  );
}

function buildUnitPrice(platillo: Platillo, modificadores: ModificadorSeleccionado[]): number {
  return money(platillo.precio) + modifiersTotal(modificadores);
}

function totals(items: WaiterCartItem[]): number {
  return items.reduce((sum, item) => sum + item.subtotal, 0);
}

export const useWaiterCartStore = create<WaiterCartState>((set, get) => ({
  tableId: null,
  restaurantId: null,
  clienteNombre: '',
  items: [],
  total: 0,

  addItem: ({ tableId, restaurantId, clienteNombre, platillo, cantidad, modificadores, notas }) => {
    const current = get();
    const sameContext = current.tableId === tableId && current.restaurantId === restaurantId;
    const baseItems = sameContext ? current.items : [];
    const unitPrice = buildUnitPrice(platillo, modificadores);

    const existing = baseItems.find(
      (item) =>
        item.platillo.id === platillo.id &&
        item.notas === notas &&
        JSON.stringify(item.modificadores) === JSON.stringify(modificadores)
    );

    const nextItems = existing
      ? baseItems.map((item) =>
          item.id === existing.id
            ? {
                ...item,
                cantidad: item.cantidad + cantidad,
                subtotal: unitPrice * (item.cantidad + cantidad),
              }
            : item
        )
      : [
          ...baseItems,
          {
            id: `${platillo.id}-${Date.now()}`,
            platillo,
            cantidad,
            modificadores,
            notas,
            precio_unitario: unitPrice,
            subtotal: unitPrice * cantidad,
          },
        ];

    set({
      tableId,
      restaurantId,
      clienteNombre,
      items: nextItems,
      total: totals(nextItems),
    });
  },

  removeItem: (id) => {
    const items = get().items.filter((item) => item.id !== id);
    set({ items, total: totals(items) });
  },

  updateQty: (id, cantidad) => {
    if (cantidad <= 0) {
      get().removeItem(id);
      return;
    }

    const items = get().items.map((item) =>
      item.id === id
        ? {
            ...item,
            cantidad,
            subtotal: item.precio_unitario * cantidad,
          }
        : item
    );
    set({ items, total: totals(items) });
  },

  clear: () => set({ tableId: null, restaurantId: null, clienteNombre: '', items: [], total: 0 }),
}));
