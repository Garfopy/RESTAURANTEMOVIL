import { apiClient } from './api';

type ApiEnvelope<T> = {
  success?: boolean;
  data?: T;
};

export type RewardsContext = 'food' | 'gift';

export type RewardTransaction = {
  type: string;
  funding_type?: 'purchased' | 'promotional' | 'mixed' | null;
  context?: string | null;
  reference_type?: string | null;
  reference_id?: number | null;
  amount_mxn: number;
  points_delta: number;
  balance_after_mxn: number;
  points_after: number;
  description?: string | null;
  created_at: string;
};

export type RewardsTopupOption = {
  amount_mxn: number;
};

export type RewardsRedeemOption = {
  points_cost: number;
  balance_credit_mxn: number;
  can_redeem: boolean;
};

export type RewardsWallet = {
  balance_mxn: number;
  purchased_balance_mxn: number;
  promotional_balance_mxn: number;
  points: number;
  points_value_mxn: number;
  discount_rate: number;
  simulated: boolean;
  transactions: RewardTransaction[];
  topup_options: RewardsTopupOption[];
  redeem_options: RewardsRedeemOption[];
};

export type RewardsQuote = {
  context: RewardsContext;
  original_total: number;
  discount_rate: number;
  discount_amount: number;
  points_redeemed: number;
  points_discount: number;
  wallet_total: number;
  points_earned: number;
  can_pay: boolean;
  balance_mxn: number;
  points: number;
  points_value_mxn: number;
  minimum_payable_total?: number;
  points_limited_by_minimum?: boolean;
  simulated: boolean;
};

type QuoteItem = {
  quantity?: number;
  cantidad?: number;
  unit_price?: number;
  precio_unit?: number;
  amount?: number;
};

function unwrapEnvelope<T>(payload: ApiEnvelope<T> | T): T {
  if (
    payload &&
    typeof payload === 'object' &&
    'data' in (payload as ApiEnvelope<T>) &&
    (payload as ApiEnvelope<T>).data !== undefined
  ) {
    return (payload as ApiEnvelope<T>).data as T;
  }
  return payload as T;
}

function normalizeWallet(wallet: RewardsWallet): RewardsWallet {
  return {
    ...wallet,
    balance_mxn: Number(wallet.balance_mxn || 0),
    purchased_balance_mxn: Number(wallet.purchased_balance_mxn || 0),
    promotional_balance_mxn: Number(wallet.promotional_balance_mxn || 0),
    points: Number(wallet.points || 0),
    points_value_mxn: Number(wallet.points_value_mxn || 0),
    discount_rate: Number(wallet.discount_rate || 0),
    simulated: Boolean(wallet.simulated),
    transactions: Array.isArray(wallet.transactions)
      ? wallet.transactions.map((tx) => ({
          ...tx,
          amount_mxn: Number(tx.amount_mxn || 0),
          points_delta: Number(tx.points_delta || 0),
          balance_after_mxn: Number(tx.balance_after_mxn || 0),
          points_after: Number(tx.points_after || 0),
        }))
      : [],
    topup_options: Array.isArray(wallet.topup_options)
      ? wallet.topup_options.map((option) => ({
          amount_mxn: Number(option.amount_mxn || 0),
        }))
      : [],
    redeem_options: Array.isArray(wallet.redeem_options)
      ? wallet.redeem_options.map((option) => ({
          points_cost: Number(option.points_cost || 0),
          balance_credit_mxn: Number(option.balance_credit_mxn || 0),
          can_redeem: Boolean(option.can_redeem),
        }))
      : [],
  };
}

function normalizeQuote(quote: RewardsQuote): RewardsQuote {
  return {
    ...quote,
    original_total: Number(quote.original_total || 0),
    discount_rate: Number(quote.discount_rate || 0),
    discount_amount: Number(quote.discount_amount || 0),
    points_redeemed: Number(quote.points_redeemed || 0),
    points_discount: Number(quote.points_discount || 0),
    wallet_total: Number(quote.wallet_total || 0),
    points_earned: Number(quote.points_earned || 0),
    can_pay: Boolean(quote.can_pay),
    balance_mxn: Number(quote.balance_mxn || 0),
    points: Number(quote.points || 0),
    points_value_mxn: Number(quote.points_value_mxn || 0),
    minimum_payable_total:
      quote.minimum_payable_total === undefined ? undefined : Number(quote.minimum_payable_total || 0),
    points_limited_by_minimum: Boolean(quote.points_limited_by_minimum),
    simulated: Boolean(quote.simulated),
  };
}

export async function getRewardsWallet(): Promise<RewardsWallet> {
  const response = await apiClient.get<ApiEnvelope<RewardsWallet> | RewardsWallet>('/rewards/wallet');
  return normalizeWallet(unwrapEnvelope(response.data));
}

export async function quoteRewards(params: {
  context: RewardsContext;
  amount: number;
  use_points?: boolean;
  payment_mode?: 'wallet' | 'external' | 'stripe';
  items?: QuoteItem[];
}): Promise<RewardsQuote> {
  const response = await apiClient.post<ApiEnvelope<RewardsQuote> | RewardsQuote>('/rewards/quote', {
    context: params.context,
    amount: params.amount,
    use_points: params.use_points ?? false,
    payment_mode: params.payment_mode ?? 'wallet',
    items: params.items ?? [],
  });
  return normalizeQuote(unwrapEnvelope(response.data));
}

export async function createRewardsTopupIntent(amountMxn: number, requestKey: string): Promise<{
  client_secret: string;
  payment_intent_id: string;
  amount_mxn: number;
}> {
  const response = await apiClient.post<
    ApiEnvelope<{
      client_secret: string;
      payment_intent_id: string;
      amount_mxn: number;
    }>
  >('/rewards/topups/create-intent', {
    amount: amountMxn,
    request_key: requestKey,
  });

  return unwrapEnvelope(response.data);
}

export async function confirmRewardsTopup(paymentIntentId: string): Promise<RewardsWallet> {
  const response = await apiClient.post<
    ApiEnvelope<{
      wallet: RewardsWallet;
    }>
  >('/rewards/topups/confirm', {
    payment_intent_id: paymentIntentId,
  });

  return normalizeWallet(unwrapEnvelope(response.data).wallet);
}

export async function redeemRewardsPoints(params: {
  points_cost: number;
  balance_credit_mxn: number;
}): Promise<RewardsWallet> {
  const response = await apiClient.post<
    ApiEnvelope<{
      wallet: RewardsWallet;
    }>
  >('/rewards/redeem', params);

  return normalizeWallet(unwrapEnvelope(response.data).wallet);
}
