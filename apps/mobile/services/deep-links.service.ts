const PROMOTION_SEGMENTS = new Set(['promocion', 'promociones', 'promotion', 'promotions', 'promo']);
const PRODUCT_SEGMENTS = new Set(['producto', 'productos', 'product', 'products', 'platillo', 'platillos']);
const ORDER_SEGMENTS = new Set(['orden', 'ordenes', 'pedido', 'pedidos', 'order', 'orders']);

export function normalizeAppDeepLink(rawDeepLink?: string | null): string | null {
  const raw = rawDeepLink?.trim();
  if (!raw) return null;

  const parsed = parseDeepLink(raw);
  if (!parsed) {
    return normalizeInternalPath(raw);
  }

  const normalized = normalizeSegments(parsed.segments, parsed.params);
  if (normalized) return normalized;

  if (parsed.path.startsWith('/')) {
    return normalizeInternalPath(withQuery(parsed.path, parsed.params));
  }

  return null;
}

function parseDeepLink(raw: string): { path: string; segments: string[]; params: URLSearchParams } | null {
  if (raw.startsWith('/') && !raw.startsWith('//')) {
    const [path = '', query = ''] = raw.split('?');
    return {
      path,
      segments: splitSegments(path),
      params: new URLSearchParams(query),
    };
  }

  try {
    const url = new URL(raw);
    const hostSegment = url.hostname ? [url.hostname] : [];
    const pathSegments = splitSegments(url.pathname);

    return {
      path: url.pathname,
      segments: [...hostSegment, ...pathSegments],
      params: url.searchParams,
    };
  } catch {
    return null;
  }
}

function normalizeSegments(segments: string[], params: URLSearchParams): string | null {
  const [first] = segments.map((segment) => segment.toLowerCase());
  const originalSecond = segments[1];

  if (!first) return null;

  if (PROMOTION_SEGMENTS.has(first)) {
    const code = params.get('code') ?? params.get('codigo') ?? null;
    const promotionId = params.get('promotion_id') ?? params.get('promo_id') ?? params.get('promocion_id') ?? params.get('id') ?? null;
    if (code) return `/promotions?code=${encodeURIComponent(code)}`;
    if (promotionId) return `/promotions?promotionId=${encodeURIComponent(promotionId)}`;
    if (!originalSecond) return '/promotions';
    return /^\d+$/.test(originalSecond)
      ? `/promotions?promotionId=${encodeURIComponent(originalSecond)}`
      : `/promotions?code=${encodeURIComponent(originalSecond)}`;
  }

  if (PRODUCT_SEGMENTS.has(first) && originalSecond) {
    return `/product/${encodeURIComponent(originalSecond)}`;
  }

  if (ORDER_SEGMENTS.has(first) && originalSecond) {
    return `/order/${encodeURIComponent(originalSecond)}`;
  }

  if (first === 'cart' || first === 'carrito') {
    return '/cart';
  }

  return null;
}

function normalizeInternalPath(path: string): string | null {
  const [basePath = '', query = ''] = path.trim().split('?');
  const normalized = normalizeSegments(splitSegments(basePath), new URLSearchParams(query));
  if (normalized) return normalized;

  if (!basePath.startsWith('/')) return null;
  return withQuery(basePath, new URLSearchParams(query));
}

function splitSegments(path: string): string[] {
  return path
    .split('/')
    .map((segment) => decodeSegment(segment.trim()))
    .filter(Boolean);
}

function withQuery(path: string, params: URLSearchParams): string {
  const query = params.toString();
  return query ? `${path}?${query}` : path;
}

function decodeSegment(segment: string): string {
  try {
    return decodeURIComponent(segment);
  } catch {
    return segment;
  }
}
