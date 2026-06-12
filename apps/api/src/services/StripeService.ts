import Stripe from 'stripe';

function getStripe(): Stripe {
  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) throw new Error('STRIPE_SECRET_KEY no configurada');
  return new Stripe(key, { apiVersion: '2024-06-20' });
}

export async function createPaymentIntent(
  amount: number,
  currency: string = 'mxn',
  metadata: Record<string, string> = {}
): Promise<{
  id: string;
  client_secret: string;
  amount: number;
  currency: string;
  status: string;
}> {
  const stripe = getStripe();
  const pi = await stripe.paymentIntents.create({
    amount: Math.round(amount * 100), // Stripe trabaja en centavos
    currency: currency.toLowerCase(),
    metadata,
    automatic_payment_methods: { enabled: true },
  });

  return {
    id: pi.id,
    client_secret: pi.client_secret!,
    amount: pi.amount,
    currency: pi.currency,
    status: pi.status,
  };
}

export async function retrievePaymentIntent(intentId: string): Promise<Stripe.PaymentIntent> {
  const stripe = getStripe();
  return stripe.paymentIntents.retrieve(intentId);
}

export async function cancelPaymentIntent(intentId: string): Promise<void> {
  const stripe = getStripe();
  await stripe.paymentIntents.cancel(intentId);
}
