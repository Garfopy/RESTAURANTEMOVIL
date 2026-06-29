import { apiClient, formatImageUrl } from './api';
import type { ModificadorSeleccionado, Sucursal } from '@amare/types';

function flattenModifierSelection(modifiers: ModificadorSeleccionado[]): Array<{ modificador_id: number; cantidad: number }> {
  return modifiers.flatMap((group) => group.opciones.map((option) => ({
    modificador_id: Number(option.opcion_id),
    cantidad: Math.max(1, Number(option.cantidad ?? 1)),
  })));
}

type Envelope<T> =
  | { success?: boolean; data?: T; message?: string }
  | T;

function unwrap<T>(payload: Envelope<T>): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data?: T }).data as T;
  }

  return payload as T;
}

export type WaiterTableStatus = 'libre' | 'mia' | 'ocupada_por_otro' | 'cuenta_abierta';

export type WaiterTable = {
  id: number;
  label: string;
  value: string;
  status: WaiterTableStatus;
  estado?: string | null;
  zona_id?: number | null;
  zona_nombre?: string | null;
  mesero_usuario_id?: number | null;
  mesero_nombre?: string | null;
  cliente_nombre?: string | null;
  reclamada_at?: string | null;
  cuenta_abierta: boolean;
  consumo_id?: string | null;
  total: number;
};

export type WaiterAccountItem = {
  id: number;
  pedido_id: number;
  pedido_folio?: string | null;
  pedido_created_at?: string | null;
  pedido_cliente_nombre?: string | null;
  pedido_mobile_usuario_id?: number | null;
  platillo_id: number;
  nombre: string;
  imagen?: string | null;
  cantidad: number;
  precio_unit: number;
  subtotal: number;
  notas?: string | null;
  estado?: string | null;
  modificadores?: ModificadorSeleccionado[];
};

export type WaiterAccountOrder = {
  id: number;
  folio?: string | null;
  estado?: string | null;
  subtotal: number;
  total: number;
  created_at?: string | null;
  items: WaiterAccountItem[];
};

export type WaiterAccount = {
  table: WaiterTable;
  orders: WaiterAccountOrder[];
  items: WaiterAccountItem[];
  total: number;
  orders_count: number;
  cliente_nombre?: string | null;
  mesero_nombre?: string | null;
  active_split?: WaiterSplit | null;
};

export type WaiterIncomingOrder = {
  id: number;
  folio?: string | null;
  estado?: string | null;
  subtotal: number;
  total: number;
  table_id: number;
  table_label: string;
  cliente_nombre?: string | null;
  mesero_usuario_id?: number | null;
  mesero_nombre?: string | null;
  claimed_by_me?: boolean;
  is_claimed?: boolean;
  is_ready?: boolean;
  kitchen_status?: 'en_cocina' | 'listo' | string;
  pedido_origen?: string | null;
  consumo_id?: string | null;
  created_at?: string | null;
  items_count: number;
};

export type WaiterOrderItemPayload = {
  platillo_id: number;
  cantidad: number;
  precio_unit: number;
  notas?: string | null;
  modificadores: ModificadorSeleccionado[];
};

export type WaiterPaymentMethod = 'efectivo' | 'tarjeta' | 'transferencia';

export type WaiterSplitItem = {
  pedido_item_id: number;
  cantidad: number;
  precio_unit: number;
  subtotal: number;
};

export type WaiterSplitAccount = {
  id: number;
  numero: number;
  nombre: string;
  total: number;
  estado: 'pendiente' | 'pagada';
  metodo_pago?: WaiterPaymentMethod | null;
  pagado_por_nombre?: string | null;
  pagado_at?: string | null;
  items: WaiterSplitItem[];
};

export type WaiterSplit = {
  id: number;
  restaurant_id: number;
  table_id: number;
  estado: 'activa' | 'pagada' | 'cancelada';
  total: number;
  paid_count: number;
  accounts_count: number;
  created_at?: string | null;
  completed_at?: string | null;
  accounts: WaiterSplitAccount[];
};

export type WaiterSplitDraftAccount = {
  name: string;
  items: Array<{ pedido_item_id: number; cantidad: number }>;
};

export type WaiterGiftStatus = 'listo' | 'reclamado' | 'entregado';

export type WaiterGift = {
  id: number;
  folio?: string | null;
  restaurant_id: number;
  table_id: number;
  gift_name: string;
  gift_description?: string | null;
  gift_price: number;
  gift_image?: string | null;
  sender_name: string;
  recipient_name: string;
  sender_table?: string | null;
  recipient_table?: string | null;
  status: WaiterGiftStatus;
  claimed_by?: number | null;
  claimed_by_name?: string | null;
  claimed_by_me: boolean;
  claimed_at?: string | null;
  delivered_by?: number | null;
  delivered_by_name?: string | null;
  delivered_at?: string | null;
  created_at?: string | null;
  paid_at?: string | null;
};

export type WaiterGiftInbox = {
  active: WaiterGift[];
  history: WaiterGift[];
  pending_count: number;
};

export type WaiterCloseAccountResponse = {
  table_id: number;
  restaurant_id: number;
  metodo_pago: WaiterPaymentMethod;
  total: number;
  orders_count: number;
  closed: boolean;
};

export async function getWaiterBranches(): Promise<Sucursal[]> {
  const { data } = await apiClient.get<Envelope<{ branches: Sucursal[] }>>('/waiter/branches');
  return unwrap(data).branches ?? [];
}

export async function getWaiterTables(restaurantId: number): Promise<WaiterTable[]> {
  const { data } = await apiClient.get<Envelope<{ tables: WaiterTable[] }>>('/waiter/tables', {
    params: { restaurant_id: restaurantId },
  });
  return unwrap(data).tables ?? [];
}

export async function claimWaiterTable(params: {
  tableId: number;
  restaurantId: number;
  clienteNombre: string;
}): Promise<WaiterTable> {
  const { data } = await apiClient.post<Envelope<{ table: WaiterTable }>>(
    `/waiter/tables/${params.tableId}/claim`,
    {
      restaurant_id: params.restaurantId,
      cliente_nombre: params.clienteNombre,
    }
  );
  return unwrap(data).table;
}

export async function getWaiterAccount(tableId: number, restaurantId: number): Promise<WaiterAccount> {
  const { data } = await apiClient.get<Envelope<{ account: WaiterAccount }>>(
    `/waiter/tables/${tableId}/account`,
    { params: { restaurant_id: restaurantId } }
  );
  const account = unwrap(data).account;

  return {
    ...account,
    items: account.items.map((item) => ({
      ...item,
      imagen: formatImageUrl(item.imagen ?? null) ?? item.imagen ?? null,
    })),
  };
}

export async function getWaiterIncomingOrders(restaurantId: number): Promise<WaiterIncomingOrder[]> {
  const { data } = await apiClient.get<Envelope<{ orders: WaiterIncomingOrder[] }>>('/waiter/orders', {
    params: { restaurant_id: restaurantId },
    _suppressConsoleError: true,
  } as any).catch((error) => {
    if (error?.response?.status === 404) {
      return { data: { orders: [] } as { orders: WaiterIncomingOrder[] } };
    }
    throw error;
  });
  return (unwrap(data).orders ?? []).map((order) => ({
    ...order,
    subtotal: Number(order.subtotal || 0),
    total: Number(order.total || 0),
    table_id: Number(order.table_id || 0),
    items_count: Number(order.items_count || 0),
  }));
}

export async function claimWaiterIncomingOrder(orderId: number, restaurantId: number): Promise<WaiterIncomingOrder> {
  const { data } = await apiClient.post<Envelope<{ order: WaiterIncomingOrder }>>(
    `/waiter/orders/${orderId}/claim`,
    { restaurant_id: restaurantId }
  );
  return unwrap(data).order;
}

export async function deliverWaiterIncomingOrder(orderId: number, restaurantId: number): Promise<void> {
  await apiClient.post(`/waiter/orders/${orderId}/deliver`, { restaurant_id: restaurantId });
}

export async function createWaiterOrder(params: {
  tableId: number;
  restaurantId: number;
  clienteNombre: string;
  items: WaiterOrderItemPayload[];
}): Promise<WaiterAccount> {
  const { data } = await apiClient.post<Envelope<{ account: WaiterAccount }>>(
    `/waiter/tables/${params.tableId}/orders`,
    {
      restaurant_id: params.restaurantId,
      cliente_nombre: params.clienteNombre,
      items: params.items.map((item) => ({
        ...item,
        modificadores: flattenModifierSelection(item.modificadores),
      })),
    }
  );
  return unwrap(data).account;
}

export async function closeWaiterAccount(params: {
  tableId: number;
  restaurantId: number;
  metodoPago: WaiterPaymentMethod;
}): Promise<WaiterCloseAccountResponse> {
  const { data } = await apiClient.post<Envelope<WaiterCloseAccountResponse>>(
    `/waiter/tables/${params.tableId}/close`,
    {
      restaurant_id: params.restaurantId,
      metodo_pago: params.metodoPago,
    }
  );
  return unwrap(data);
}

export async function createWaiterSplit(params: {
  tableId: number;
  restaurantId: number;
  accounts: WaiterSplitDraftAccount[];
}): Promise<WaiterSplit> {
  const { data } = await apiClient.post<Envelope<{ split: WaiterSplit }>>(
    `/waiter/tables/${params.tableId}/splits`,
    { restaurant_id: params.restaurantId, accounts: params.accounts }
  );
  return unwrap(data).split;
}

export async function payWaiterSplitAccount(params: {
  tableId: number;
  restaurantId: number;
  splitId: number;
  accountId: number;
  metodoPago: WaiterPaymentMethod;
}): Promise<{ split: WaiterSplit; closed: boolean }> {
  const { data } = await apiClient.post<Envelope<{ split: WaiterSplit; closed: boolean }>>(
    `/waiter/tables/${params.tableId}/splits/${params.splitId}/accounts/${params.accountId}/pay`,
    { restaurant_id: params.restaurantId, metodo_pago: params.metodoPago }
  );
  return unwrap(data);
}

export async function cancelWaiterSplit(params: {
  tableId: number;
  restaurantId: number;
  splitId: number;
}): Promise<void> {
  await apiClient.delete(`/waiter/tables/${params.tableId}/splits/${params.splitId}`, {
    data: { restaurant_id: params.restaurantId },
  });
}

export async function getWaiterGifts(restaurantId: number): Promise<WaiterGiftInbox> {
  const { data } = await apiClient.get<Envelope<WaiterGiftInbox>>('/waiter/gifts', {
    params: { restaurant_id: restaurantId },
  });
  const inbox = unwrap(data);
  const normalize = (gift: WaiterGift): WaiterGift => ({
    ...gift,
    gift_price: Number(gift.gift_price || 0),
    gift_image: formatImageUrl(gift.gift_image ?? null) ?? gift.gift_image ?? null,
  });
  return {
    active: (inbox.active ?? []).map(normalize),
    history: (inbox.history ?? []).map(normalize),
    pending_count: Number(inbox.pending_count || 0),
  };
}

async function updateWaiterGift(params: {
  giftId: number;
  restaurantId: number;
  action: 'claim' | 'release' | 'deliver';
}): Promise<WaiterGift> {
  const { data } = await apiClient.post<Envelope<{ gift: WaiterGift }>>(
    `/waiter/gifts/${params.giftId}/${params.action}`,
    { restaurant_id: params.restaurantId }
  );
  const gift = unwrap(data).gift;
  return {
    ...gift,
    gift_price: Number(gift.gift_price || 0),
    gift_image: formatImageUrl(gift.gift_image ?? null) ?? gift.gift_image ?? null,
  };
}

export const claimWaiterGift = (giftId: number, restaurantId: number) =>
  updateWaiterGift({ giftId, restaurantId, action: 'claim' });

export const releaseWaiterGift = (giftId: number, restaurantId: number) =>
  updateWaiterGift({ giftId, restaurantId, action: 'release' });

export const deliverWaiterGift = (giftId: number, restaurantId: number) =>
  updateWaiterGift({ giftId, restaurantId, action: 'deliver' });
