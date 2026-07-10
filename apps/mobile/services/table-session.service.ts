import type { TableScanResult } from '@amare/types';
import { apiClient } from './api';
import { normalizeBranch } from './branches.service';

export const tableSessionKeys = {
  diagnostic: ['table-session', 'diagnostic'] as const,
};

type ApiEnvelope<T> = {
  success?: boolean;
  ok?: boolean;
  data: T;
};

export type TableSessionDiagnostic = {
  blocked: boolean;
  reason_code: string | null;
  message: string;
  active_visit: {
    pedido_id: number | null;
    folio: string | null;
    restaurante_id: number | null;
    mesa_id: number | null;
    mesa_label: string | null;
    consumo_id: string | null;
    estado: string | null;
    tipo_pedido: string | null;
    cuenta_abierta: boolean | null;
    salida_qr_generado_at: string | null;
    salida_validado_at: string | null;
    pagado_at: string | null;
    cerrado_at: string | null;
    metodo_pago: string | null;
    subtotal: number | null;
    total: number | null;
    notas: string | null;
    created_at: string | null;
    block_reasons: string[];
    social_gifts: Array<{
      id: number | null;
      folio: string | null;
      status: string | null;
      gift_nombre: string | null;
      gift_precio: number | null;
      recipient_nombre: string | null;
      cargado_cuenta_at: string | null;
      pagado_at: string | null;
      pedido_item_id: number | null;
      amare_wallet_used_mxn: number | null;
    }>;
  } | null;
  next_table: {
    restaurante_id: number | null;
    mesa_id: number | null;
    mesa_label: string | null;
  };
};

export async function scanTableQr(payload: string, restauranteId?: number | null): Promise<TableScanResult> {
  const { data } = await apiClient.post<{
    success?: boolean;
    ok?: boolean;
    data: TableScanResult;
  }>(
    '/restaurants/tables/scan',
    {
      payload,
      restaurante_id: restauranteId ?? null,
    },
    {
      _suppressConsoleError: true,
    } as any
  );

  return {
    ...data.data,
    branch: normalizeBranch(data.data.branch),
  };
}

export async function getTableSessionDiagnostic(params?: {
  restaurantId?: number | null;
  tableId?: number | null;
  mesa?: string | null;
}): Promise<TableSessionDiagnostic> {
  const { data } = await apiClient.get<ApiEnvelope<TableSessionDiagnostic>>(
    '/restaurants/tables/session-diagnostic',
    {
      params: {
        restaurant_id: params?.restaurantId ?? undefined,
        table_id: params?.tableId ?? undefined,
        mesa: params?.mesa ?? undefined,
      },
      _suppressConsoleError: true,
    } as any
  );

  return data.data;
}

export async function resetTableSessionForTesting(): Promise<{
  reset: boolean;
  affected_orders: number;
}> {
  const { data } = await apiClient.post<ApiEnvelope<{ reset: boolean; affected_orders: number }>>(
    '/restaurants/tables/session-reset-test',
    {},
    {
      _suppressConsoleError: true,
    } as any
  );

  return data.data;
}
