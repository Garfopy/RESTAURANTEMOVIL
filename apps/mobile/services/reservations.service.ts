import { apiClient } from './api';
import { API_BASE_URL } from '../constants/api';

type ApiEnvelope<T> = {
  ok?: boolean;
  success?: boolean;
  message?: string;
  data?: T;
};

export type ReservationTable = {
  id: number;
  label: string;
  capacity: number;
};

export type Reservation = {
  id: number;
  restaurante_id: number;
  mesa_id?: number | null;
  nombre: string;
  telefono: string;
  email?: string | null;
  fecha: string;
  hora: string;
  personas: number;
  estado: string;
  origen?: string | null;
  notas?: string | null;
};

function unwrap<T>(payload: ApiEnvelope<T> | T): T {
  if (payload && typeof payload === 'object' && 'data' in (payload as ApiEnvelope<T>) && (payload as ApiEnvelope<T>).data) {
    return (payload as ApiEnvelope<T>).data as T;
  }
  return payload as T;
}

export async function getReservationAvailability(params: {
  restaurantId: number;
  fecha: string;
  hora: string;
  personas: number;
}): Promise<{ tables: ReservationTable[]; block_window: { before_seconds: number; after_seconds: number } }> {
  const response = await apiClient.get<
    ApiEnvelope<{ tables: ReservationTable[]; block_window?: { before_seconds: number; after_seconds: number } }>
  >(
    '/reservations/availability',
    {
      params: {
        restaurant_id: params.restaurantId,
        fecha: params.fecha,
        hora: params.hora,
        personas: params.personas,
      },
    }
  );

  const data = unwrap(response.data);
  return {
    tables: data.tables ?? [],
    block_window: data.block_window ?? { before_seconds: 7200, after_seconds: 9000 },
  };
}

export async function createReservation(params: {
  restaurantId: number;
  mesaId?: number | null;
  nombre: string;
  telefono: string;
  email?: string;
  fecha: string;
  hora: string;
  personas: number;
  notas?: string;
}): Promise<Reservation | null> {
  const response = await apiClient.post<ApiEnvelope<{
    reservation?: Reservation | null;
    reservacion?: Reservation | null;
    reserva?: Reservation | null;
  }>>(apiV1Url('/reservaciones'), {
    restaurante_id: params.restaurantId,
    mesa_id: params.mesaId,
    nombre: params.nombre,
    telefono: params.telefono,
    email: params.email,
    fecha: params.fecha,
    hora: params.hora,
    personas: params.personas,
    notas: params.notas,
  });

  const data = unwrap(response.data);
  return data.reservation ?? data.reservacion ?? data.reserva ?? null;
}

function apiV1Url(path: string): string {
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  try {
    const apiUrl = new URL(API_BASE_URL);
    return `${apiUrl.origin}/api/v1${cleanPath}`;
  } catch {
    return `/api/v1${cleanPath}`;
  }
}
