export const STRIPE_PUBLISHABLE_KEY = process.env.EXPO_PUBLIC_STRIPE_KEY?.trim() ?? '';

export const STRIPE_IS_CONFIGURED = STRIPE_PUBLISHABLE_KEY.length > 0;
