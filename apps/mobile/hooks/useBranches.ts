import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getBranches } from '../services/branches.service';
import { useBranchStore } from '../store/branch.store';

export const branchKeys = {
  all: ['branches'] as const,
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
