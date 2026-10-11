const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export class ApiError extends Error {
  readonly status: number;
  readonly payload: unknown;

  constructor(message: string, status: number, payload: unknown = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

type ApiOptions = RequestInit & { cacheTtlMs?: number; skipCache?: boolean };
const responseCache = new Map<string, { expiresAt: number; value: unknown }>();

export function invalidateApiCache(pathPrefix?: string) {
  for (const key of responseCache.keys()) {
    if (!pathPrefix || key.startsWith(pathPrefix)) responseCache.delete(key);
  }
}

function dispatchUnauthorized() {
  if (typeof window !== 'undefined') window.dispatchEvent(new Event('hotel:unauthorized'));
}

function isRetryable(method: string, error: unknown) {
  if (method !== 'GET') return false;
  if (error instanceof ApiError) return error.status >= 500;
  return error instanceof TypeError;
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase();
  const cacheKey = `${method}:${path}`;
  const cacheTtlMs = options.cacheTtlMs ?? (method === 'GET' ? 15_000 : 0);
  const useCache = method === 'GET' && !options.skipCache && cacheTtlMs > 0;
  const cached = useCache ? responseCache.get(cacheKey) : undefined;

  if (cached && cached.expiresAt > Date.now()) return cached.value as T;
  if (cached) responseCache.delete(cacheKey);

  const request = (async () => {
    let lastError: unknown;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        const requestInit = { ...options };
        delete requestInit.cacheTtlMs;
        delete requestInit.skipCache;
        const response = await fetch(`${API_URL}${path}`, {
          ...requestInit,
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...options.headers,
          },
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          if (response.status === 401) dispatchUnauthorized();
          const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
          throw new ApiError(validation || payload.message || 'Terjadi kesalahan pada server.', response.status, payload);
        }

        if (response.status === 204) return undefined as T;
        const value = await response.json() as T;
        if (method !== 'GET') invalidateApiCache();
        if (useCache) responseCache.set(cacheKey, { expiresAt: Date.now() + cacheTtlMs, value });
        return value;
      } catch (error) {
        lastError = error;
        if (error instanceof DOMException && error.name === 'AbortError') throw error;
        if (!isRetryable(method, error) || attempt === 2) throw error;
        await new Promise((resolve) => setTimeout(resolve, 250 * 2 ** attempt));
      }
    }
    throw lastError;
  })();

  return request;
}

export function apiCached<T>(path: string, signal?: AbortSignal, cacheTtlMs = 30_000) {
  return api<T>(path, { signal, cacheTtlMs });
}

export { API_URL };
