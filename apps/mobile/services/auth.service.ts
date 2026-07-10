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

type PasswordResetResponse = {
  success?: boolean;
  message?: string;
  data?: {
    expires_in_minutes?: number;
    reset_code?: string;
  } | null;
};

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
    rol: user?.rol ?? 'user',
    telefono: user?.telefono ?? null,
    foto_url: user?.foto_url ?? null,
    social_photos: user?.social_photos ?? [],
    google_id: user?.google_id ?? null,
    activo: user?.activo ?? true,
    created_at: user?.created_at ?? '',
    edad: user?.edad ?? null,
    genero: user?.genero ?? null,
    sexualidad: user?.sexualidad ?? null,
    gustos: user?.gustos ?? null,
    biografia: user?.biografia ?? null,
    que_busca: user?.que_busca ?? null,
    redes_sociales: user?.redes_sociales ?? null,
    instagram: user?.instagram ?? null,
    tiktok: user?.tiktok ?? null,
    is_social_active: user?.is_social_active ?? user?.modo_social ?? false,
    modo_social: user?.modo_social ?? user?.is_social_active ?? false,
    current_restaurante_id: user?.current_restaurante_id ?? null,
    mesa: user?.mesa ?? null,
    social_consent_accepted_at: user?.social_consent_accepted_at ?? null,
    social_consent_version: user?.social_consent_version ?? null,
    requires_social_consent: user?.requires_social_consent ?? null,
  };
}

function parseSesion(response: AuthResponse): Sesion {
  const token = hasAuthEnvelope(response) ? response.data?.token : response.token;
  const user = hasAuthEnvelope(response) ? response.data?.user : response;

  if (!token || !user) {
    throw new Error('La respuesta del servidor no contiene una sesión válida.');
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
    identifier: payload.email,
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
    name: payload.nombre,
    nombre: payload.nombre,
    email: payload.email ?? null,
    password: payload.password,
    phone: payload.telefono ?? null,
    telefono: payload.telefono ?? null,
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

export async function requestPasswordReset(identifier: string): Promise<{
  expiresInMinutes: number;
  resetCode?: string;
}> {
  const { data } = await apiClient.post<PasswordResetResponse>('/auth/password-reset/request', {
    identifier,
  });

  return {
    expiresInMinutes: Number(data.data?.expires_in_minutes ?? 15),
    resetCode: data.data?.reset_code,
  };
}

export async function confirmPasswordReset(payload: {
  identifier: string;
  code: string;
  newPassword: string;
}): Promise<void> {
  await apiClient.post('/auth/password-reset/confirm', {
    identifier: payload.identifier,
    code: payload.code,
    new_password: payload.newPassword,
  });
}

export async function logout(): Promise<void> {
  // El cliente limpia el token localmente en el store
}
