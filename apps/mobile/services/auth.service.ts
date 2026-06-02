import { apiClient } from './api';
import type {
  Sesion,
  MobileUser,
  GoogleLoginPayload,
  EmailLoginPayload,
  RegisterPayload,
} from '@amare/types';

export async function loginWithGoogle(payload: GoogleLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<{ ok: boolean; data: Sesion }>('/auth/google', payload);
  return data.data;
}

export async function loginWithEmail(payload: EmailLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<{ ok: boolean; data: Sesion }>('/auth/email', payload);
  return data.data;
}

export async function register(payload: RegisterPayload): Promise<Sesion> {
  const { data } = await apiClient.post<{ ok: boolean; data: Sesion }>('/auth/register', payload);
  return data.data;
}

export async function getMe(): Promise<MobileUser> {
  const { data } = await apiClient.get<{ ok: boolean; data: MobileUser }>('/auth/me');
  return data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}
