import { apiClient } from './api';

export type FiscalData = {
  id?: number | null;
  usuario_id?: number | null;
  rfc: string;
  nombre_fiscal: string;
  regimen_fiscal: string;
  codigo_postal: string;
  uso_cfdi: string;
  email: string;
  created_at?: string | null;
  updated_at?: string | null;
};

export type InvoiceRequestPayload = {
  required: boolean;
  save_to_profile?: boolean;
  receptor: FiscalData;
};

type Envelope<T> = { success?: boolean; data?: T; message?: string };

export const EMPTY_FISCAL_DATA: FiscalData = {
  rfc: '',
  nombre_fiscal: '',
  regimen_fiscal: '',
  codigo_postal: '',
  uso_cfdi: '',
  email: '',
};

export function normalizeFiscalData(input?: Partial<FiscalData> | null): FiscalData {
  return {
    rfc: (input?.rfc ?? '').toUpperCase(),
    nombre_fiscal: input?.nombre_fiscal ?? '',
    regimen_fiscal: (input?.regimen_fiscal ?? '').toUpperCase(),
    codigo_postal: input?.codigo_postal ?? '',
    uso_cfdi: (input?.uso_cfdi ?? '').toUpperCase(),
    email: (input?.email ?? '').toLowerCase(),
  };
}

export function validateFiscalData(data: FiscalData): string | null {
  const rfc = data.rfc.trim().toUpperCase();
  if (!/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/.test(rfc)) return 'Ingresa un RFC válido.';
  if (!data.nombre_fiscal.trim()) return 'Ingresa la razón social o nombre fiscal.';
  if (!data.regimen_fiscal.trim()) return 'Ingresa el régimen fiscal.';
  if (!/^\d{5}$/.test(data.codigo_postal.trim())) return 'Ingresa un código postal fiscal de 5 dígitos.';
  if (!data.uso_cfdi.trim()) return 'Ingresa el uso CFDI.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) return 'Ingresa un email válido.';
  return null;
}

export function buildInvoiceRequest(
  required: boolean,
  data: FiscalData,
  saveToProfile: boolean
): InvoiceRequestPayload | null {
  if (!required) return null;
  return {
    required: true,
    save_to_profile: saveToProfile,
    receptor: normalizeFiscalData(data),
  };
}

export async function getFiscalData(): Promise<FiscalData | null> {
  const { data } = await apiClient.get<Envelope<{ fiscal_data: FiscalData | null }>>('/profile/fiscal-data');
  return data.data?.fiscal_data ? normalizeFiscalData(data.data.fiscal_data) : null;
}

export async function saveFiscalData(payload: FiscalData): Promise<FiscalData> {
  const { data } = await apiClient.put<Envelope<{ fiscal_data: FiscalData }>>(
    '/profile/fiscal-data',
    normalizeFiscalData(payload)
  );
  if (!data.data?.fiscal_data) throw new Error(data.message || 'No se pudieron guardar los datos fiscales.');
  return normalizeFiscalData(data.data.fiscal_data);
}

export async function deleteFiscalData(): Promise<void> {
  await apiClient.delete('/profile/fiscal-data');
}
