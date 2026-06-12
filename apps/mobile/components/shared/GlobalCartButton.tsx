import React from 'react';
import { useSegments } from 'expo-router';
import { useUserStore } from '../../store/user.store';
import { CartButton } from './CartButton';

export function GlobalCartButton() {
  const segments = useSegments();
  const { isAuthenticated, isLoading } = useUserStore();
  const [rootSegment, childSegment] = segments;

  if (isLoading || !isAuthenticated) return null;

  if (
    rootSegment === '(auth)' ||
    rootSegment === 'branch-selector' ||
    rootSegment === 'cart' ||
    rootSegment === 'checkout' ||
    rootSegment === 'order' ||
    (rootSegment === 'store' && childSegment === 'checkout')
  ) {
    return null;
  }

  return <CartButton />;
}
