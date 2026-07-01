import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getOrders } from '../../services/orders.service';
import {
  getTableSessionDiagnostic,
  tableSessionKeys,
  type TableSessionDiagnostic,
} from '../../services/table-session.service';
import { useTableSessionStore } from '../../store/table-session.store';
import { useUserStore } from '../../store/user.store';

const STAFF_ROLES = ['mesero', 'hostess', 'hostes', 'host', 'anfitrion', 'anfitriona'];

export function TableSessionRuntime() {
  const queryClient = useQueryClient();
  const session = useTableSessionStore((state) => state.session);
  const clearSession = useTableSessionStore((state) => state.clearSession);
  const isAuthenticated = useUserStore((state) => state.isAuthenticated);
  const token = useUserStore((state) => state.token);
  const user = useUserStore((state) => state.user);
  const updateProfile = useUserStore((state) => state.updateProfile);
  const handledClosedVisitRef = useRef<string | null>(null);

  const enabled = Boolean(
    isAuthenticated &&
    token &&
    session?.restauranteId &&
    session?.mesaId &&
    !STAFF_ROLES.includes(String(user?.rol ?? '').toLowerCase())
  );

  const diagnosticQuery = useQuery({
    queryKey: [...tableSessionKeys.diagnostic, session?.restauranteId ?? 0, session?.mesaId ?? 0],
    queryFn: () =>
      getTableSessionDiagnostic({
        restaurantId: session?.restauranteId ?? null,
        tableId: session?.mesaId ?? null,
        mesa: session?.mesaValue ?? null,
      }),
    enabled,
    staleTime: 5 * 1000,
    refetchInterval: enabled ? 15_000 : false,
    refetchIntervalInBackground: false,
    refetchOnMount: 'always',
    refetchOnReconnect: true,
  });

  useEffect(() => {
    if (!enabled) {
      return undefined;
    }

    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        void diagnosticQuery.refetch();
      }
    });

    return () => subscription.remove();
  }, [diagnosticQuery.refetch, enabled]);

  useEffect(() => {
    const diagnostic = diagnosticQuery.data;
    if (!enabled || !session || !diagnostic) {
      return;
    }

    if (!shouldReleaseLocalTableSession(diagnostic)) {
      handledClosedVisitRef.current = null;
      return;
    }

    const visitKey = buildVisitKey(diagnostic);
    if (handledClosedVisitRef.current === visitKey) {
      return;
    }

    handledClosedVisitRef.current = visitKey;
    clearSession();
    updateProfile({
      mesa: null,
      current_restaurante_id: null,
      is_social_active: false,
      modo_social: false,
    });
    void queryClient.invalidateQueries({ queryKey: ['orders'] });
    void queryClient.invalidateQueries({ queryKey: ['social'] });
    void queryClient.invalidateQueries({ queryKey: tableSessionKeys.diagnostic });
  }, [clearSession, diagnosticQuery.data, enabled, queryClient, session, updateProfile]);

  useEffect(() => {
    const diagnostic = diagnosticQuery.data;
    if (!enabled || !session?.createdAt || !diagnostic || diagnostic.active_visit) {
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        const orders = await queryClient.fetchQuery({
          queryKey: ['orders'],
          queryFn: getOrders,
          staleTime: 5 * 1000,
        });
        if (cancelled || !hasValidatedVisitAfterSession(orders, session)) {
          return;
        }

        clearSession();
        updateProfile({
          mesa: null,
          current_restaurante_id: null,
          is_social_active: false,
          modo_social: false,
        });
        void queryClient.invalidateQueries({ queryKey: ['social'] });
        void queryClient.invalidateQueries({ queryKey: tableSessionKeys.diagnostic });
      } catch {
        // Best effort: keep the local table session if reconciliation cannot be confirmed.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [clearSession, diagnosticQuery.data, enabled, queryClient, session, updateProfile]);

  return null;
}

function shouldReleaseLocalTableSession(diagnostic: TableSessionDiagnostic): boolean {
  const visit = diagnostic.active_visit;
  if (!visit) {
    return false;
  }

  return Boolean(visit.salida_validado_at);
}

function buildVisitKey(diagnostic: TableSessionDiagnostic): string {
  const visit = diagnostic.active_visit;
  return [
    visit?.pedido_id ?? 'sin-pedido',
    visit?.consumo_id ?? 'sin-consumo',
    visit?.salida_validado_at ?? visit?.cerrado_at ?? 'cerrada',
  ].join(':');
}

function hasValidatedVisitAfterSession(
  orders: Awaited<ReturnType<typeof getOrders>>,
  session: NonNullable<ReturnType<typeof useTableSessionStore.getState>['session']>
): boolean {
  const sessionTime = Date.parse(session.createdAt ?? '');
  if (!Number.isFinite(sessionTime)) {
    return false;
  }

  return orders.some((order) => {
    const orderTableId = Number(order.mesa_id ?? 0);
    const validatedAt = typeof order.salida_validado_at === 'string' ? order.salida_validado_at : null;
    const validatedTime = validatedAt ? Date.parse(validatedAt) : NaN;

    return (
      order.tipo_pedido === 'eat_in' &&
      orderTableId === session.mesaId &&
      Number(order.restaurante_id ?? 0) === session.restauranteId &&
      Number.isFinite(validatedTime) &&
      validatedTime >= sessionTime
    );
  });
}
