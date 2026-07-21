export const STRIPE_PUBLISHABLE_KEY = process.env.EXPO_PUBLIC_STRIPE_KEY?.trim() ?? '';

export const CARD_PAYMENTS_ENABLED = process.env.EXPO_PUBLIC_ENABLE_CARD_PAYMENTS === 'true';
export const STRIPE_LIVE_MODE = process.env.EXPO_PUBLIC_STRIPE_LIVE_MODE === 'true';

export const STRIPE_KEY_MODE = STRIPE_PUBLISHABLE_KEY.startsWith('pk_live_')
  ? 'live'
  : STRIPE_PUBLISHABLE_KEY.startsWith('pk_test_')
    ? 'test'
    : 'invalid';

export const STRIPE_IS_CONFIGURED =
  CARD_PAYMENTS_ENABLED &&
  STRIPE_KEY_MODE !== 'invalid' &&
  (!STRIPE_LIVE_MODE || STRIPE_KEY_MODE === 'live');

export const NATIVE_WALLETS_ENABLED =
  STRIPE_IS_CONFIGURED && process.env.EXPO_PUBLIC_ENABLE_NATIVE_WALLETS === 'true';
