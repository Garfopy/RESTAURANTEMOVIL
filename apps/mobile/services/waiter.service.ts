import { apiClient, formatImageUrl } from './api';
import type { ModificadorSeleccionado, Sucursal } from '@amare/types';

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
};

export type WaiterOrderItemPayload = {
  platillo_id: number;
  cantidad: number;
  precio_unit: number;
  notas?: string | null;
  modificadores: ModificadorSeleccionado[];
};

export type WaiterPaymentMethod = 'efectivo' | 'tarjeta' | 'transferencia';

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
      items: params.items,
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
