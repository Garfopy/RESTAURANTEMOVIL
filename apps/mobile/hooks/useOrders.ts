import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getOrders, getOrderById, getOrderTracking } from '../services/orders.service';
import type { Pedido } from '@amare/types';

export const orderKeys = {
  list: ['orders'] as const,
  detail: (id: number) => ['orders', id] as const,
  tracking: (id: number) => ['orders', id, 'tracking'] as const,
};

export function useOrders() {
  return useQuery<Pedido[]>({
    queryKey: orderKeys.list,
    queryFn: () => getOrders(),
    staleTime: 30 * 1000,
  });
}

export function useOrder(id?: number) {
  return useQuery<Pedido>({
    queryKey: orderKeys.detail(id ?? 0),
    queryFn: () => getOrderById(id!),
    enabled: id !== undefined,
    refetchInterval: (query) => {
      // Actualizar cada 15s mientras el pedido esté activo
      const estado = (query.state.data as Pedido | undefined)?.estado;
      const inactivo = estado === 'entregado' || estado === 'cancelado';
      return inactivo ? false : 15000;
    },
  });
}

export function useOrderTracking(id?: number) {
  return useQuery({
    queryKey: orderKeys.tracking(id ?? 0),
    queryFn: () => getOrderTracking(id!),
    enabled: id !== undefined,
    refetchInterval: 15000,
  });
}
