import type { TableScanResult } from '@amare/types';
import { apiClient } from './api';
import { normalizeBranch } from './branches.service';

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
