import { apiClient } from './api';

type ApiEnvelope<T> = {
  success?: boolean;
  data?: T;
};

export type RewardsContext = 'food' | 'gift';

export type RewardTransaction = {
  type: string;
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

export type RewardsWallet = {
  balance_mxn: number;
  points: number;
  points_value_mxn: number;
  discount_rate: number;
  simulated: boolean;
  transactions: RewardTransaction[];
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
  simulated: boolean;
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
}): Promise<RewardsQuote> {
  const response = await apiClient.post<ApiEnvelope<RewardsQuote> | RewardsQuote>('/rewards/quote', {
    context: params.context,
    amount: params.amount,
    use_points: params.use_points ?? false,
  });
  return normalizeQuote(unwrapEnvelope(response.data));
}
