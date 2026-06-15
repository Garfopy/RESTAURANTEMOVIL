import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { API_BASE_URL } from '../constants/api';
import { useUserStore } from '../store/user.store';

// Ensure URL ends with /
const normalizedBaseURL = API_BASE_URL.endsWith('/') ? API_BASE_URL : API_BASE_URL + '/';
const publicBaseURL = getPublicBaseURL(normalizedBaseURL);

// When the firewall returns a 409 challenge, we persist the cookie here.
let imunifyCookie: string | null = null;

export const apiClient = axios.create({
  baseURL: normalizedBaseURL,
  timeout: 20000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json, text/plain, */*',
    'Accept-Language': 'es-MX,es;q=0.9,en-US;q=0.8,en;q=0.7',
    'User-Agent':
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Cache-Control': 'no-cache',
  },
});

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = useUserStore.getState().token;

  if (config.url?.startsWith('/')) config.url = config.url.substring(1);

  console.log(`[API] Request: ${config.method?.toUpperCase()} ${config.baseURL}${config.url}`);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  if (imunifyCookie) {
    config.headers.Cookie = imunifyCookie;
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => {
    if (response.data && typeof response.data === 'object') {
      response.data = transformStringsToNumbers(response.data);
    }
    return response;
  },
  async (error: AxiosError) => {
    const originalRequest = error.config as (InternalAxiosRequestConfig & {
      _retry?: boolean;
      _imunifyWarmup?: boolean;
      _suppressConsoleError?: boolean;
    }) | null;

    if (
      error.response?.status === 409 &&
      typeof error.response.data === 'string' &&
      originalRequest &&
      !originalRequest._retry &&
      !originalRequest._imunifyWarmup
    ) {
      const match = (error.response.data as string).match(/(humans_\d+=\d+)/);

      if (match?.[1]) {
        originalRequest._retry = true;
        imunifyCookie = match[1];

        console.log('Firewall Imunify360 detectado. Cookie encontrada:', imunifyCookie);
        console.log('Simulando lectura humana (1.5s)...');
        await new Promise((resolve) => setTimeout(resolve, 1500));

        // Only warm up POST/PUT/PATCH/DELETE requests. Doing this for GET can recurse forever.
        if (originalRequest.method?.toLowerCase() !== 'get') {
          console.log(`Estableciendo cookie Imunify360 con GET...`);
          try {
            await apiClient.get(originalRequest.url ?? '', {
              _imunifyWarmup: true,
            } as any);
          } catch {
            // Best effort only.
          }
        }

        console.log(
          `Reintentando ${originalRequest.method?.toUpperCase()} con cookie establecida: ${originalRequest.url}`
        );
        return apiClient.request(originalRequest);
      }
    }

    if (error.response?.status === 401) {
      await useUserStore.getState().logout();
    }

    if (!originalRequest?._suppressConsoleError) {
      console.error(`[API] Error ${error.response?.status} en ${error.config?.url}:`, error.message);
      console.log('Detalle del error:', JSON.stringify(error.response?.data, null, 2));
    }

    return Promise.reject(error);
  }
);

function transformStringsToNumbers(obj: any): any {
  if (Array.isArray(obj)) return obj.map(transformStringsToNumbers);

  if (obj !== null && typeof obj === 'object') {
    return Object.keys(obj).reduce((acc: any, key) => {
      const value = obj[key];
      if (typeof value === 'string' && /^-?\d+\.?\d*$/.test(value) && !isNaN(parseFloat(value))) {
        acc[key] = parseFloat(value);
      } else {
        acc[key] = transformStringsToNumbers(value);
      }
      return acc;
    }, {});
  }

  return obj;
}

export function getApiError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { detail?: string; message?: string; error?: string } | undefined;
    return data?.detail ?? data?.message ?? data?.error ?? error.message;
  }
  return String(error);
}

/**
 * Convierte rutas relativas del backend en URLs absolutas para la app.
 * Mantiene compatibilidad con la API anterior y con el backend PHP actual.
 */
export function formatImageUrl(path?: string | null): string | undefined {
  if (!path) return undefined;
  if (path.startsWith('http')) return path;

  try {
    const apiUrl = new URL(normalizedBaseURL);

    if (path.startsWith('/')) {
      return `${apiUrl.origin}${path}`;
    }

    if (path.startsWith('api_asocial/')) {
      return `${apiUrl.origin}/${path}`;
    }
  } catch {
    // Fallback to the legacy concatenation below.
  }

  const cleanPath = path.replace(/^\//, '');
  if (cleanPath.startsWith('public/')) {
    return `${publicBaseURL}${cleanPath}`;
  }

  if (
    cleanPath.startsWith('uploads/promos/') ||
    cleanPath.startsWith('uploads/promotions/') ||
    cleanPath.startsWith('uploads/promociones/')
  ) {
    const filename = cleanPath.split('/').pop();
    return filename ? `${publicBaseURL}public/uploads/promociones/${filename}` : undefined;
  }

  return `${normalizedBaseURL}${cleanPath}`;
}

function getPublicBaseURL(baseURL: string): string {
  try {
    const apiUrl = new URL(baseURL);
    const publicPath = apiUrl.pathname
      .replace(/\/+$/, '')
      .replace(/\/api_restaurante$/, '')
      .replace(/\/backend_php$/, '');

    return `${apiUrl.origin}${publicPath ? `${publicPath}/` : '/'}`;
  } catch {
    return baseURL
      .replace(/\/api_restaurante\/?$/, '/')
      .replace(/\/backend_php\/?$/, '/');
  }
}
