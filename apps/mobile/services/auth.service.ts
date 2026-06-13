import { apiClient } from './api';
import type {
  Sesion,
  MobileUser,
  GoogleLoginPayload,
  EmailLoginPayload,
  RegisterPayload,
} from '@amare/types';

type AuthUserPayload = Partial<MobileUser> & { user_id?: number };

type AuthResponse =
  | {
      success?: boolean;
      data?: {
        user?: AuthUserPayload;
        token?: string;
      };
    }
  | (AuthUserPayload & {
      token: string;
    });

type MeResponse =
  | {
      success?: boolean;
      data?: {
        user?: AuthUserPayload;
      };
    }
  | AuthUserPayload;

function hasAuthEnvelope(response: AuthResponse): response is Extract<AuthResponse, { data?: unknown }> {
  return typeof response === 'object' && response !== null && 'data' in response;
}

function hasMeEnvelope(response: MeResponse): response is Extract<MeResponse, { data?: unknown }> {
  return typeof response === 'object' && response !== null && 'data' in response;
}

function normalizeUser(user: AuthUserPayload | undefined): MobileUser {
  return {
    id: Number(user?.id ?? user?.user_id ?? 0),
    nombre: user?.nombre ?? '',
    email: user?.email ?? '',
    telefono: user?.telefono ?? null,
    foto_url: user?.foto_url ?? null,
    google_id: user?.google_id ?? null,
    activo: user?.activo ?? true,
    created_at: user?.created_at ?? '',
    edad: user?.edad ?? null,
    genero: user?.genero ?? null,
    gustos: user?.gustos ?? null,
    biografia: user?.biografia ?? null,
    instagram: user?.instagram ?? null,
    tiktok: user?.tiktok ?? null,
    is_social_active: user?.is_social_active ?? user?.modo_social ?? false,
    modo_social: user?.modo_social ?? user?.is_social_active ?? false,
  };
}

function parseSesion(response: AuthResponse): Sesion {
  const token = hasAuthEnvelope(response) ? response.data?.token : response.token;
  const user = hasAuthEnvelope(response) ? response.data?.user : response;

  if (!token || !user) {
    throw new Error('La respuesta del servidor no contiene una sesion valida.');
  }

  return {
    token,
    user: normalizeUser(user),
    expires_at: '',
  };
}

export async function loginWithEmail(payload: EmailLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<AuthResponse>('/auth/login', {
    email: payload.email,
    password: payload.password,
  });
  return parseSesion(data);
}

export async function loginWithGoogle(payload: GoogleLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<AuthResponse>('/auth/login', {
    email: payload.id_token,
    password: 'google_oauth',
  });
  return parseSesion(data);
}

export async function register(payload: RegisterPayload): Promise<Sesion> {
  const { data } = await apiClient.post<AuthResponse>('/auth/register', {
    nombre: payload.nombre,
    email: payload.email,
    password: payload.password,
  });
  return parseSesion(data);
}

export async function getMe(): Promise<MobileUser> {
  const { data } = await apiClient.get<MeResponse>('/auth/me');
  const user = hasMeEnvelope(data) ? data.data?.user : data;

  if (!user) {
    throw new Error('No se pudo obtener el usuario actual.');
  }

  return normalizeUser(user);
}

export async function logout(): Promise<void> {
  // El cliente limpia el token localmente en el store
}
