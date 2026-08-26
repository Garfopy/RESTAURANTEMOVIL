import React from 'react';
import { useSegments } from 'expo-router';
import { useUserStore } from '../../store/user.store';
import { CartButton } from './CartButton';

export function GlobalCartButton() {
  const segments = useSegments();
  const { isLoading } = useUserStore();
  const [rootSegment] = segments as string[];

  if (isLoading) return null;

  if (
    rootSegment === '(auth)' ||
    rootSegment === 'cart' ||
    rootSegment === 'checkout' ||
    rootSegment === 'order'
  ) {
    return null;
  }

  return <CartButton />;
}
