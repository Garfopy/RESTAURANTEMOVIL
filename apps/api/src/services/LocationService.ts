const EARTH_RADIUS_KM = 6371;

function toRad(deg: number): number {
  return (deg * Math.PI) / 180;
}

/**
 * Distancia Haversine entre dos coordenadas (en km)
 */
export function haversineDistance(
  lat1: number,
  lng1: number,
  lat2: number,
  lng2: number
): number {
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) *
      Math.cos(toRad(lat2)) *
      Math.sin(dLng / 2) ** 2;

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return EARTH_RADIUS_KM * c;
}

/**
 * Ordena un arreglo de sucursales por distancia a las coordenadas del usuario.
 * Agrega el campo `distancia_km` a cada elemento.
 */
export function sortByDistance<T extends { lat: number | null; lng: number | null }>(
  items: T[],
  userLat: number,
  userLng: number
): (T & { distancia_km: number })[] {
  return items
    .filter((item) => item.lat !== null && item.lng !== null)
    .map((item) => ({
      ...item,
      distancia_km: parseFloat(
        haversineDistance(userLat, userLng, item.lat as number, item.lng as number).toFixed(2)
      ),
    }))
    .sort((a, b) => a.distancia_km - b.distancia_km);
}
