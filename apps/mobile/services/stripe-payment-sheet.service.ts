import { Alert, Platform } from 'react-native';
import { useStripe } from '@stripe/stripe-react-native';
import { NATIVE_WALLETS_ENABLED, STRIPE_IS_CONFIGURED, STRIPE_LIVE_MODE } from '../constants/stripe';

export type AmarePaymentSheetParams = {
  clientSecret: string;
  amountMxn?: number;
  customerName?: string | null;
  customerEmail?: string | null;
};

export const STRIPE_MINIMUM_PAYMENT_MXN = 10;

export class StripeMinimumAmountError extends Error {
  readonly amountMxn: number;

  constructor(amountMxn: number) {
    super(
      `El total de $${amountMxn.toFixed(2)} MXN es menor al mínimo de $${STRIPE_MINIMUM_PAYMENT_MXN.toFixed(2)} MXN.`
    );
    this.name = 'StripeMinimumAmountError';
    this.amountMxn = amountMxn;
  }
}

export function assertStripeMinimumPaymentAmount(amountMxn?: number): void {
  if (
    typeof amountMxn === 'number' &&
    Number.isFinite(amountMxn) &&
    amountMxn > 0 &&
    amountMxn < STRIPE_MINIMUM_PAYMENT_MXN
  ) {
    throw new StripeMinimumAmountError(amountMxn);
  }
}

type PaymentSheetStripe = Pick<
  ReturnType<typeof useStripe>,
  'initPaymentSheet' | 'presentPaymentSheet' | 'isPlatformPaySupported'
>;

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
  assertStripeMinimumPaymentAmount(params.amountMxn);

  let nativeWalletSupported = false;
  if (NATIVE_WALLETS_ENABLED && (Platform.OS === 'ios' || Platform.OS === 'android')) {
    try {
      nativeWalletSupported = await stripe.isPlatformPaySupported(
        Platform.OS === 'android'
          ? {
              googlePay: {
                testEnv: !STRIPE_LIVE_MODE,
                existingPaymentMethodRequired: false,
              },
            }
          : undefined
      );
    } catch (error) {
      if (__DEV__) {
        console.warn('[Stripe] No se pudo comprobar la wallet nativa:', error);
      }
    }
  }

  const { error: initializationError } = await stripe.initPaymentSheet({
    merchantDisplayName: 'Amare',
    paymentIntentClientSecret: params.clientSecret,
    allowsDelayedPaymentMethods: false,
    returnURL: 'amare://stripe-redirect',
    defaultBillingDetails: {
      name: params.customerName || undefined,
      email: params.customerEmail || undefined,
      address: {
        country: 'MX',
      },
    },
    applePay:
      Platform.OS === 'ios' && NATIVE_WALLETS_ENABLED && nativeWalletSupported
        ? { merchantCountryCode: 'MX' }
        : undefined,
    googlePay:
      Platform.OS === 'android' && NATIVE_WALLETS_ENABLED && nativeWalletSupported
        ? {
            merchantCountryCode: 'MX',
            currencyCode: 'MXN',
            label: 'Amare',
            testEnv: !STRIPE_LIVE_MODE,
          }
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

function errorRecord(value: unknown): Record<string, unknown> | null {
  return typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : null;
}

export function isStripeMinimumAmountError(error: unknown): boolean {
  if (error instanceof StripeMinimumAmountError) return true;

  const root = errorRecord(error);
  if (!root) return false;
  if (root.name === 'StripeMinimumAmountError') return true;

  const response = errorRecord(root.response);
  const data = errorRecord(response?.data);
  if (data?.code === 'PAYMENT_AMOUNT_BELOW_MINIMUM') return true;

  const text = [root.message, data?.message]
    .filter((value): value is string => typeof value === 'string')
    .join(' ')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

  return (
    text.includes('payment_amount_below_minimum') ||
    text.includes('monto minimo') ||
    text.includes('menor al minimo') ||
    text.includes('minimum charge amount') ||
    text.includes('amount must be at least')
  );
}

export function showStripeMinimumAmountAlert(error: unknown): boolean {
  if (!isStripeMinimumAmountError(error)) return false;

  const amount =
    error instanceof StripeMinimumAmountError && Number.isFinite(error.amountMxn)
      ? `Tu total actual es $${error.amountMxn.toFixed(2)} MXN. `
      : '';

  Alert.alert(
    `Monto mínimo de $${STRIPE_MINIMUM_PAYMENT_MXN.toFixed(2)}`,
    `${amount}Para pagar con tarjeta, Apple Pay o Google Pay, el importe debe ser de al menos $${STRIPE_MINIMUM_PAYMENT_MXN.toFixed(2)} MXN. Ajusta tu compra o elige otro método de pago.`,
    [{ text: 'Entendido' }]
  );
  return true;
}

export function stripePaymentLabel(): string {
  if (Platform.OS === 'ios' && NATIVE_WALLETS_ENABLED) return 'Tarjeta y Apple Pay';
  if (Platform.OS === 'android' && NATIVE_WALLETS_ENABLED) return 'Tarjeta y Google Pay';
  return 'Tarjeta';
}
