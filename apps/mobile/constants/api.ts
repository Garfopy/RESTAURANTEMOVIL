// Cambia a tu IP de red local para pruebas en dispositivo real
// Ejemplo: 'http://192.168.1.100:3001'
export const API_BASE_URL = __DEV__
  ? 'http://localhost:3001'
  : 'https://api.amare.app'; // TODO: cambiar a producción
