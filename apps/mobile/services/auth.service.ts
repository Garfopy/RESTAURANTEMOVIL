import { apiClient } from './api';
import type {
  Sesion,
  MobileUser,
  AppleLoginPayload,
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

export function normalizeUser(user: AuthUserPayload | undefined): MobileUser {
  const requiresExternalOnboarding = Boolean(
    user?.requires_onboarding ??
      ((user?.google_id || user?.apple_id) && (!user?.telefono || !user?.fecha_nacimiento || !user?.terms_accepted_at))
  );

  return {
    id: Number(user?.id ?? user?.user_id ?? 0),
    nombre: user?.nombre ?? '',
    email: user?.email ?? '',
    rol: user?.rol ?? 'user',
    telefono: user?.telefono ?? null,
    fecha_nacimiento: user?.fecha_nacimiento ?? null,
    onboarding_completed_at: user?.onboarding_completed_at ?? null,
    terms_accepted_at: user?.terms_accepted_at ?? null,
    marketing_opt_in: user?.marketing_opt_in ?? false,
    requires_onboarding: requiresExternalOnboarding,
    foto_url: user?.foto_url ?? null,
    social_photos: user?.social_photos ?? [],
    google_id: user?.google_id ?? null,
    apple_id: user?.apple_id ?? null,
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
  const { data } = await apiClient.post<AuthResponse>('/auth/google', {
    token: payload.id_token,
    id_token: payload.id_token,
    device_info: payload.device_info,
    platform: payload.platform,
  });
  return parseSesion(data);
}

export async function loginWithApple(payload: AppleLoginPayload): Promise<Sesion> {
  const { data } = await apiClient.post<AuthResponse>('/auth/apple', {
    identity_token: payload.identity_token,
    authorization_code: payload.authorization_code,
    full_name: payload.full_name,
    platform: payload.platform ?? 'ios',
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
    fecha_nacimiento: payload.fecha_nacimiento,
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

type ProfileUpdatePayload = {
  telefono?: string;
  fecha_nacimiento?: string;
  terms_accepted?: boolean;
  marketing_opt_in?: boolean;
};

type ProfileResponse =
  | {
      success?: boolean;
      data?: {
        profile?: AuthUserPayload;
      };
    }
  | {
      profile?: AuthUserPayload;
    };

export async function completeProfile(payload: ProfileUpdatePayload): Promise<MobileUser> {
  const profile = await updateProfileSettings(payload);
  return normalizeUser({ ...profile, requires_onboarding: false });
}

export async function updateProfileSettings(payload: ProfileUpdatePayload): Promise<MobileUser> {
  const { data } = await apiClient.put<ProfileResponse>('/profile', payload);
  const profile = 'data' in data && data.data?.profile
    ? data.data.profile
    : (data as { profile?: AuthUserPayload }).profile;

  if (!profile) {
    throw new Error('No se pudo actualizar el perfil.');
  }

  return normalizeUser(profile);
}

export async function cancelProfileOnboarding(): Promise<void> {
  await apiClient.delete('/profile/onboarding');
}

export async function deleteAccount(): Promise<void> {
  await apiClient.delete('/profile/account');
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

export async function verifyPasswordResetCode(payload: {
  identifier: string;
  code: string;
}): Promise<void> {
  await apiClient.post('/auth/password-reset/verify', {
    identifier: payload.identifier,
    code: payload.code,
  });
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
