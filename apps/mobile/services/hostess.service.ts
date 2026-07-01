import { apiClient } from './api';
import { normalizeBranch } from './branches.service';
import type { Sucursal } from '@amare/types';

type Envelope<T> =
  | { success?: boolean; data?: T; message?: string }
  | T;

function unwrap<T>(payload: Envelope<T>): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data?: T }).data as T;
  }

  return payload as T;
}

export type HostessReservationStatus = 'pendiente' | 'confirmada' | 'cancelada' | 'completada';

export type HostessReservation = {
  id: number;
  restaurante_id: number;
  mesa_id?: number | null;
  mesa_label?: string | null;
  nombre: string;
  telefono?: string | null;
  email?: string | null;
  fecha?: string | null;
  hora?: string | null;
  personas: number;
  estado: HostessReservationStatus;
  origen?: string | null;
  notas?: string | null;
  confirmacion_enviada?: boolean;
  recordatorio_enviado?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export type HostessReleaseOrder = {
  id: number;
  restaurante_id: number;
  folio?: string | null;
  estado?: string | null;
  tipo_pedido: 'pickup' | 'delivery';
  cliente_nombre?: string | null;
  notas?: string | null;
  direccion_entrega?: string | null;
  metodo_pago?: string | null;
  subtotal: number;
  total: number;
  items_count: number;
  created_at?: string | null;
  updated_at?: string | null;
};

export async function getHostessBranches(): Promise<Sucursal[]> {
  const { data } = await apiClient.get<Envelope<{ branches: Sucursal[] }>>('/hostess/branches');
  return (unwrap(data).branches ?? []).map(normalizeBranch);
}

export async function getHostessReservations(restaurantId: number): Promise<HostessReservation[]> {
  const { data } = await apiClient.get<Envelope<{ reservations: HostessReservation[] }>>(
    '/hostess/reservations',
    { params: { restaurant_id: restaurantId } }
  );

  return (unwrap(data).reservations ?? []).map(normalizeReservation);
}

export async function getHostessReleaseOrders(restaurantId: number): Promise<HostessReleaseOrder[]> {
  const { data } = await apiClient.get<Envelope<{ orders: HostessReleaseOrder[] }>>(
    '/hostess/orders',
    { params: { restaurant_id: restaurantId } }
  );

  return (unwrap(data).orders ?? []).map(normalizeReleaseOrder);
}

export async function completeHostessReleaseOrder(orderId: number, restaurantId: number): Promise<void> {
  await apiClient.post(`/hostess/orders/${orderId}/complete`, { restaurant_id: restaurantId });
}

export async function completeHostessReservation(
  reservationId: number,
  restaurantId: number
): Promise<HostessReservation> {
  const { data } = await apiClient.post<Envelope<{ reservation: HostessReservation }>>(
    `/hostess/reservations/${reservationId}/complete`,
    { restaurant_id: restaurantId }
  );

  return normalizeReservation(unwrap(data).reservation);
}

function normalizeReservation(reservation: HostessReservation): HostessReservation {
  return {
    ...reservation,
    id: Number(reservation.id || 0),
    restaurante_id: Number(reservation.restaurante_id || 0),
    mesa_id: reservation.mesa_id != null ? Number(reservation.mesa_id) : null,
    personas: Number(reservation.personas || 0),
    confirmacion_enviada: Boolean(reservation.confirmacion_enviada),
    recordatorio_enviado: Boolean(reservation.recordatorio_enviado),
  };
}

function normalizeReleaseOrder(order: HostessReleaseOrder): HostessReleaseOrder {
  return {
    ...order,
    id: Number(order.id || 0),
    restaurante_id: Number(order.restaurante_id || 0),
    subtotal: Number(order.subtotal || 0),
    total: Number(order.total || 0),
    items_count: Number(order.items_count || 0),
  };
}
