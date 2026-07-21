import { Platform } from 'react-native';
import { useStripe } from '@stripe/stripe-react-native';
import { NATIVE_WALLETS_ENABLED, STRIPE_IS_CONFIGURED, STRIPE_LIVE_MODE } from '../constants/stripe';

export type AmarePaymentSheetParams = {
  clientSecret: string;
  customerName?: string | null;
  customerEmail?: string | null;
};

type PaymentSheetStripe = Pick<ReturnType<typeof useStripe>, 'initPaymentSheet' | 'presentPaymentSheet'>;

export async function presentAmarePaymentSheet(
  stripe: PaymentSheetStripe,
  params: AmarePaymentSheetParams
): Promise<void> {
  if (!STRIPE_IS_CONFIGURED) {
    throw new Error('Stripe no esta configurado para esta compilacion.');
  }
  if (!params.clientSecret) {
    throw new Error('No se recibio la autorizacion de pago de Stripe.');
  }

  const { error: initializationError } = await stripe.initPaymentSheet({
    merchantDisplayName: 'Amare',
    paymentIntentClientSecret: params.clientSecret,
    allowsDelayedPaymentMethods: false,
    returnURL: 'amare://stripe-redirect',
    defaultBillingDetails: {
      name: params.customerName || undefined,
      email: params.customerEmail || undefined,
    },
    applePay:
      Platform.OS === 'ios' && NATIVE_WALLETS_ENABLED
        ? { merchantCountryCode: 'MX' }
        : undefined,
    googlePay:
      Platform.OS === 'android' && NATIVE_WALLETS_ENABLED
        ? { merchantCountryCode: 'MX', testEnv: !STRIPE_LIVE_MODE }
        : undefined,
  });

  if (initializationError) {
    throw new Error(initializationError.message || 'No se pudo preparar el metodo de pago.');
  }

  const { error: presentationError } = await stripe.presentPaymentSheet();
  if (!presentationError) return;

  if (presentationError.code === 'Canceled') {
    const cancelled = new Error('Pago cancelado.');
    cancelled.name = 'PaymentCanceledError';
    throw cancelled;
  }

  throw new Error(presentationError.message || 'Stripe no pudo completar el pago.');
}

export function stripePaymentLabel(): string {
  if (Platform.OS === 'ios' && NATIVE_WALLETS_ENABLED) return 'Tarjeta y Apple Pay';
  if (Platform.OS === 'android' && NATIVE_WALLETS_ENABLED) return 'Tarjeta y Google Pay';
  return 'Tarjeta';
}
