export type AccountSuspensionNotice = {
  title: string;
  reason: string;
  explanation: string;
  details?: string | null;
  reasonCode?: string | null;
  supportHint?: string | null;
};

type ApiSuspensionPayload = {
  code?: string | null;
  message?: string | null;
  data?: {
    title?: string | null;
    reason?: string | null;
    reason_code?: string | null;
    explanation?: string | null;
    details?: string | null;
    support_hint?: string | null;
  } | null;
};

const SUSPENSION_CODES = new Set(['ACCOUNT_SUSPENDED', 'ACCOUNT_DISABLED', 'USER_SUSPENDED']);

export function extractAccountSuspension(errorOrData: unknown): AccountSuspensionNotice | null {
  const responseData = getResponseData(errorOrData);
  if (!responseData || typeof responseData !== 'object') return null;

  const payload = responseData as ApiSuspensionPayload;
  const code = String(payload.code ?? payload.data?.reason_code ?? '').toUpperCase();
  const message = String(payload.message ?? payload.data?.explanation ?? '').toLowerCase();

  const looksSuspended =
    SUSPENSION_CODES.has(code) ||
    message.includes('suspendida') ||
    message.includes('suspendido') ||
    message.includes('desactivada') ||
    message.includes('desactivado') ||
    message.includes('banead');

  if (!looksSuspended) return null;

  return {
    title: payload.data?.title?.trim() || 'Cuenta suspendida',
    reason: payload.data?.reason?.trim() || 'Cuenta desactivada por moderacion',
    explanation:
      payload.data?.explanation?.trim() ||
      payload.message?.trim() ||
      'Tu cuenta fue suspendida y no puede acceder a la app en este momento.',
    details: payload.data?.details?.trim() || null,
    reasonCode: payload.data?.reason_code ?? payload.code ?? null,
    supportHint: payload.data?.support_hint?.trim() || 'Contacta al restaurante para revisar tu caso.',
  };
}

function getResponseData(value: unknown): unknown {
  if (value && typeof value === 'object' && 'response' in value) {
    return (value as { response?: { data?: unknown } }).response?.data;
  }

  return value;
}
