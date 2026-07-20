import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getOrders, getOrderById, getOrderTracking } from '../services/orders.service';
import type { Pedido } from '@amare/types';
import { useUserStore } from '../store/user.store';

export const orderKeys = {
  list: ['orders'] as const,
  detail: (id: number) => ['orders', id] as const,
  tracking: (id: number) => ['orders', id, 'tracking'] as const,
};

export function useOrders() {
  const token = useUserStore((state) => state.token);

  return useQuery<Pedido[]>({
    queryKey: orderKeys.list,
    queryFn: () => getOrders(),
    enabled: Boolean(token),
    staleTime: 15 * 1000,
    refetchOnMount: true,
    refetchOnReconnect: true,
    refetchInterval: (query) => {
      const orders = query.state.data as Pedido[] | undefined;
      const hasActiveOrder = orders?.some((order) => {
        if (order.estado === 'cancelado' || order.salida_validado_at || order.pagado_at || order.cerrado_at) {
          return false;
        }

        return (
          order.estado !== 'entregado' ||
          Number(order.cuenta_abierta ?? 0) === 1 ||
          Boolean(order.salida_qr_generado_at)
        );
      });

      return hasActiveOrder ? 5000 : false;
    },
  });
}

export function useOrder(id?: number) {
  const token = useUserStore((state) => state.token);

  return useQuery<Pedido>({
    queryKey: orderKeys.detail(id ?? 0),
    queryFn: () => getOrderById(id!),
    enabled: Boolean(token) && id !== undefined,
    staleTime: 5 * 1000,
    refetchOnMount: 'always',
    refetchOnReconnect: true,
    refetchInterval: (query) => {
      // Actualizar con más frecuencia mientras el pedido está activo.
      const estado = (query.state.data as Pedido | undefined)?.estado;
      const inactivo = estado === 'entregado' || estado === 'cancelado';
      return inactivo ? false : 5000;
    },
  });
}

export function useOrderTracking(id?: number) {
  const token = useUserStore((state) => state.token);

  return useQuery({
    queryKey: orderKeys.tracking(id ?? 0),
    queryFn: () => getOrderTracking(id!),
    enabled: Boolean(token) && id !== undefined,
    staleTime: 5 * 1000,
    refetchOnMount: 'always',
    refetchOnReconnect: true,
    refetchInterval: 5000,
  });
}
