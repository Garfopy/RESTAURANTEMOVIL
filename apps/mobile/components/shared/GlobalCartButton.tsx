import React from 'react';
import { useSegments } from 'expo-router';
import { useUserStore } from '../../store/user.store';
import { CartButton } from './CartButton';

export function GlobalCartButton() {
  const segments = useSegments();
  const { isAuthenticated, isLoading, user } = useUserStore();
  const [rootSegment, childSegment] = segments as string[];

  if (isLoading || !isAuthenticated) return null;
  const role = String(user?.rol ?? '').toLowerCase();
  if (role === 'mesero' || ['hostess', 'hostes', 'host', 'anfitrion', 'anfitriona'].includes(role)) return null;

  if (
    rootSegment === '(auth)' ||
    rootSegment === '(waiter)' ||
    rootSegment === '(hostess)' ||
    rootSegment === 'branch-selector' ||
    rootSegment === 'cart' ||
    rootSegment === 'checkout' ||
    rootSegment === 'order' ||
    (rootSegment === '(tabs)' && childSegment === 'social') ||
    (rootSegment === 'profile' && childSegment === 'social') ||
    (rootSegment === 'store' && childSegment === 'checkout')
  ) {
    return null;
  }

  return <CartButton />;
}
