// URL de la API — configura EXPO_PUBLIC_API_URL en apps/mobile/.env para desarrollo
export const API_BASE_URL = __DEV__
  ? (process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.1.100:3001')
  : 'https://api.amare.app';
