import { API_URL } from '../config/api';

export type ApiError = {
  status: number;
  error: string;
  code?: number;
  violations?: Record<string, string>;
};

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const res = await fetch(`${API_URL}${path}`, { ...options, headers });
  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    const message = body.message ?? body.error ?? 'Request failed';
    const err: ApiError = {
      status: res.status,
      error:
        res.status === 401
          ? message.includes('expired')
            ? message
            : `Authentication failed (${message}). Log in again and ensure the app sends Authorization: Bearer <token>.`
          : res.status === 403
            ? `Access denied (${message}). Use a customer account, not staff/admin.`
            : message,
      code: body.code ?? res.status,
      violations: body.violations,
    };
    throw err;
  }

  return (body.data ?? body) as T;
}
