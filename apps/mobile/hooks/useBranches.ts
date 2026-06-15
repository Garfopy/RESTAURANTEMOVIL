import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getBranches, getNearestBranches, getBranchById } from '../services/branches.service';
import { useBranchStore } from '../store/branch.store';
import type { TipoPedido } from '@amare/types';

export const branchKeys = {
  all: ['branches'] as const,
  nearest: (lat: number, lng: number, tipoPedido?: TipoPedido) =>
    ['branches', 'nearest', lat, lng, tipoPedido] as const,
  detail: (id: number) => ['branches', id] as const,
};

export function useBranches() {
  const query = useQuery({
    queryKey: branchKeys.all,
    queryFn: getBranches,
    staleTime: 5 * 60 * 1000,
  });

  useEffect(() => {
    if (query.data) {
      useBranchStore.getState().setSucursales(query.data);
    }
  }, [query.data]);

  return query;
}

export function useNearestBranches(lat?: number, lng?: number, tipoPedido?: TipoPedido) {
  return useQuery({
    queryKey: branchKeys.nearest(lat ?? 0, lng ?? 0, tipoPedido),
    queryFn: () => getNearestBranches(lat!, lng!, tipoPedido),
    enabled: lat !== undefined && lng !== undefined,
    staleTime: 2 * 60 * 1000,
  });
}

export function useBranch(id?: number) {
  return useQuery({
    queryKey: branchKeys.detail(id ?? 0),
    queryFn: () => getBranchById(id!),
    enabled: id !== undefined,
    staleTime: 10 * 60 * 1000,
  });
}
