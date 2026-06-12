import { useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getBranches, getNearestBranches, getBranchById } from '../services/branches.service';
import { useBranchStore } from '../store/branch.store';
import type { Sucursal } from '@amare/types';

export const branchKeys = {
  all: ['branches'] as const,
  nearest: (lat: number, lng: number) => ['branches', 'nearest', lat, lng] as const,
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

export function useNearestBranches(lat?: number, lng?: number) {
  return useQuery({
    queryKey: branchKeys.nearest(lat ?? 0, lng ?? 0),
    queryFn: () => getNearestBranches(lat!, lng!),
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
